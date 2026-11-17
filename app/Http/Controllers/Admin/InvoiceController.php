<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\ZatcaSetting;
use App\Services\Zatca\ZatcaService;
use App\Support\ZatcaQr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

/**
 * ZATCA e-invoices: tax invoices, credit and debit notes — signed locally, then reported (B2C) or cleared (B2B).
 */
class InvoiceController extends Controller
{
    private const VAT_PERCENT = 15;

    public function __construct(private ZatcaService $zatca) {}

    public function index(Request $request): Response
    {
        $setting = ZatcaSetting::current();
        $current = ZatcaSetting::currentEnvironment();
        // Each environment has its own list; the active one is shown unless another is picked.
        $environment = array_key_exists((string) $request->query('environment'), config('zatca.environments'))
            ? $request->query('environment') : $current;

        $counts = Invoice::query()->where('environment', $environment)
            ->selectRaw('zatca_status, count(*) as total')->groupBy('zatca_status')->pluck('total', 'zatca_status');
        $filter = $request->string('status')->toString();

        $invoices = Invoice::query()
            ->where('environment', $environment)
            ->when($filter === 'accepted', fn ($q) => $q->whereIn('zatca_status', Invoice::ACCEPTED))
            ->when($filter === 'pending', fn ($q) => $q->where('zatca_status', 'pending'))
            ->when($filter === 'rejected', fn ($q) => $q->whereIn('zatca_status', Invoice::REJECTED))
            ->when($request->filled('q'), fn ($q) => $q->where(function ($q) use ($request) {
                $term = $request->string('q');
                $q->where('invoice_number', 'like', "%{$term}%")->orWhere('buyer_name', 'like', "%{$term}%");
            }))
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Invoice $i) => [
                'id' => $i->id,
                'invoice_number' => $i->invoice_number,
                'label' => $i->documentLabel(),
                'document_type' => $i->document_type,
                'issue_date' => $i->issue_date->format('Y-m-d'),
                'buyer_name' => $i->buyer_name,
                'total_amount' => (float) $i->total_amount,
                'icv' => $i->icv,
                'zatca_status' => $i->zatca_status,
            ]);

        return Inertia::render('Admin/Invoices/Index', [
            'invoices' => $invoices,
            'filters' => ['status' => $filter, 'q' => (string) $request->input('q', ''), 'environment' => $environment],
            'environment' => $current,
            'environments' => $this->environments(),
            'stats' => [
                'all' => $counts->sum(),
                'accepted' => $counts->only(Invoice::ACCEPTED)->sum(),
                'pending' => $counts->get('pending', 0),
                'rejected' => $counts->only(Invoice::REJECTED)->sum(),
            ],
            'canIssue' => (bool) $setting?->signingCertificate(),
            'canSubmit' => $setting?->hasProductionCsid() && $environment === $current,
        ]);
    }

    /** @return array<int, array{value: string, label: string}> */
    private function environments(): array
    {
        return collect(config('zatca.environments'))->map(fn ($e, $key) => ['value' => $key, 'label' => $e['label']])->values()->all();
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $setting = ZatcaSetting::current();
        $environment = ZatcaSetting::currentEnvironment();
        $fixedBuyerVat = ZatcaSetting::fixedVat($environment, 'buyer');
        $documentType = in_array($request->query('document_type'), array_keys(Invoice::DOCUMENT_CODES), true)
            ? $request->query('document_type') : 'invoice';

        $defaults = [
            'invoice_type' => Invoice::TYPE_SIMPLIFIED,
            'document_type' => $documentType,
            'issue_date' => now()->toDateString(),
            'payment_means' => '10',
            'original_invoice_number' => '',
            'note_reason' => '',
            'buyer_name' => '', 'buyer_vat_number' => '', 'buyer_street' => '', 'buyer_building_number' => '',
            'buyer_district' => '', 'buyer_city' => '', 'buyer_postal_code' => '',
            'order_id' => null,
            'items' => [['name' => '', 'quantity' => 1, 'unit_price' => '']],
        ];
        $source = null;

        // Credit / debit note for an existing invoice: copy buyer and lines from it.
        if ($request->filled('original') && ($original = Invoice::with('items')->find($request->integer('original')))) {
            $defaults = array_merge($defaults, $original->only([
                'invoice_type', 'payment_means', 'buyer_name', 'buyer_vat_number', 'buyer_street', 'buyer_building_number',
                'buyer_district', 'buyer_city', 'buyer_postal_code', 'order_id',
            ]), [
                'original_invoice_number' => $original->invoice_number,
                'items' => $original->items->map(fn (InvoiceItem $i) => [
                    'name' => $i->name, 'quantity' => (float) $i->quantity, 'unit_price' => (float) $i->unit_price,
                ])->all(),
            ]);
            $source = ['type' => 'invoice', 'id' => $original->id, 'label' => $original->invoice_number];
        }
        // Tax invoice for a POS sale: the till prices include VAT, so lines are converted to net prices.
        elseif ($request->filled('order') && ($order = Order::with('items', 'einvoice')->find($request->integer('order')))) {
            if ($order->einvoice && $documentType === 'invoice') {
                return redirect()->route('admin.invoices.show', $order->einvoice);
            }
            $defaults = array_merge($defaults, [
                'issue_date' => $order->created_at->toDateString(),
                'payment_means' => $order->payment_method === 'card' ? '48' : '10',
                'buyer_name' => (string) $order->customer_name,
                'order_id' => $order->id,
                'items' => $this->orderLines($order),
            ]);
            $source = ['type' => 'order', 'id' => $order->id, 'label' => $order->invoice_no, 'total' => (float) $order->total];
        }

        if ($fixedBuyerVat) {
            $defaults['buyer_vat_number'] = $fixedBuyerVat;
        }

        $types = $setting?->invoice_types ?? '1100';

        return Inertia::render('Admin/Invoices/Create', [
            'fixedBuyerVat' => $fixedBuyerVat,
            'defaults' => $defaults,
            'source' => $source,
            'allowedTypes' => array_values(array_filter([
                $types[1] === '1' ? Invoice::TYPE_SIMPLIFIED : null,
                $types[0] === '1' ? Invoice::TYPE_STANDARD : null,
            ])),
            'paymentMeans' => collect(Invoice::PAYMENT_MEANS)->map(fn ($label, $code) => ['value' => (string) $code, 'label' => $label])->values(),
            'vatPercent' => self::VAT_PERCENT,
            'environment' => $environment,
            'canIssue' => (bool) $setting?->signingCertificate(),
            'canSubmit' => (bool) $setting?->hasProductionCsid(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $standard = $request->input('invoice_type') === Invoice::TYPE_STANDARD;

        // Sandbox: every customer gets ZATCA's test buyer VAT number, whatever was sent.
        if ($fixedBuyerVat = ZatcaSetting::fixedVat(ZatcaSetting::currentEnvironment(), 'buyer')) {
            $request->merge(['buyer_vat_number' => $fixedBuyerVat]);
        }

        $data = $request->validate([
            'invoice_type' => ['required', Rule::in([Invoice::TYPE_STANDARD, Invoice::TYPE_SIMPLIFIED])],
            'document_type' => ['required', Rule::in(array_keys(Invoice::DOCUMENT_CODES))],
            'issue_date' => ['required', 'date', 'before_or_equal:today'],
            'payment_means' => ['required', Rule::in(array_map('strval', array_keys(Invoice::PAYMENT_MEANS)))],
            'original_invoice_number' => ['required_unless:document_type,invoice', 'nullable', 'string', 'max:255'],
            'note_reason' => ['required_unless:document_type,invoice', 'nullable', 'string', 'max:1000'],
            'buyer_name' => [Rule::requiredIf($standard), 'nullable', 'string', 'max:255'],
            'buyer_vat_number' => ['nullable', 'regex:/^3\d{13}3$/'],
            'buyer_street' => [Rule::requiredIf($standard), 'nullable', 'string', 'max:255'],
            'buyer_building_number' => [Rule::requiredIf($standard), 'nullable', 'digits:4'],
            'buyer_district' => [Rule::requiredIf($standard), 'nullable', 'string', 'max:255'],
            'buyer_city' => [Rule::requiredIf($standard), 'nullable', 'string', 'max:255'],
            'buyer_postal_code' => [Rule::requiredIf($standard), 'nullable', 'digits:5'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ], [
            'buyer_vat_number.regex' => __('Buyer VAT number must be 15 digits, starting and ending with 3.'),
        ]);

        if (! ZatcaSetting::current()?->signingCertificate()) {
            return back()->with('error', __('Complete ZATCA onboarding before issuing invoices.'));
        }

        $order = isset($data['order_id']) ? Order::find($data['order_id']) : null;
        if ($order && $data['document_type'] === 'invoice' && $order->einvoice()->exists()) {
            return back()->with('error', __('This order already has an e-invoice.'));
        }
        if ($order && $data['document_type'] === 'invoice' && $order->isVoid()) {
            return back()->with('error', __('A voided order cannot get a tax invoice.'));
        }

        try {
            $invoice = DB::transaction(function () use ($data, $order) {
                $fromOrder = $order && $data['document_type'] === 'invoice';
                $invoice = new Invoice(collect($data)->except('items')->all() + [
                    'uuid' => (string) Str::uuid(),
                    'issue_time' => $fromOrder && $order->created_at->toDateString() === $data['issue_date']
                        ? $order->created_at->format('H:i:s')
                        : now()->format('H:i:s'),
                ]);
                // A sale keeps its receipt number, so the printed receipt and the e-invoice match.
                $invoice->invoice_number = $fromOrder && ! Invoice::where('invoice_number', $order->invoice_no)->exists()
                    ? $order->invoice_no
                    : Invoice::nextNumber($invoice->document_type);

                $items = collect($data['items'])->map(fn ($item) => new InvoiceItem($item + [
                    'vat_category' => 'S',
                    'vat_percent' => self::VAT_PERCENT,
                ]));

                $invoice->calculateTotals($items);
                $invoice->save();
                $invoice->items()->saveMany($items);
                $invoice->setRelation('items', $items);

                $this->zatca->prepare($invoice);

                return $invoice;
            });
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', $e->getMessage());
        }

        if ($request->boolean('submit_now')) {
            return $this->submit($invoice);
        }

        return redirect()->route('admin.invoices.show', $invoice)->with('status', __('Invoice signed and ready to submit.'));
    }

    public function show(Invoice $invoice): Response
    {
        $invoice->load('items', 'order');
        $setting = ZatcaSetting::current();
        $validation = $invoice->zatca_response['validationResults'] ?? [];
        $messages = fn ($items) => collect($items ?? [])->map(fn ($m) => ['code' => $m['code'] ?? '', 'message' => $m['message'] ?? ''])->values();

        return Inertia::render('Admin/Invoices/Show', [
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'label' => $invoice->documentLabel(),
                'environment' => $invoice->environment,
                'invoice_type' => $invoice->invoice_type,
                'document_type' => $invoice->document_type,
                'is_note' => $invoice->isNote(),
                'issue_date' => $invoice->issue_date->format('Y-m-d'),
                'issue_time' => $invoice->issue_time,
                'original_invoice_number' => $invoice->original_invoice_number,
                'note_reason' => $invoice->note_reason,
                'payment_means' => Invoice::PAYMENT_MEANS[$invoice->payment_means] ?? $invoice->payment_means,
                'currency' => $invoice->currency,
                'uuid' => $invoice->uuid,
                'buyer' => $invoice->only(['buyer_name', 'buyer_vat_number', 'buyer_street', 'buyer_building_number', 'buyer_district', 'buyer_city', 'buyer_postal_code']),
                'line_extension_amount' => (float) $invoice->line_extension_amount,
                'vat_amount' => (float) $invoice->vat_amount,
                'total_amount' => (float) $invoice->total_amount,
                'items' => $invoice->items->map(fn (InvoiceItem $i) => [
                    'id' => $i->id,
                    'name' => $i->name,
                    'quantity' => (float) $i->quantity,
                    'unit_price' => (float) $i->unit_price,
                    'vat_percent' => (float) $i->vat_percent,
                    'line_extension_amount' => (float) $i->line_extension_amount,
                    'vat_amount' => (float) $i->vat_amount,
                ]),
                'icv' => $invoice->icv,
                'invoice_hash' => $invoice->invoice_hash,
                'previous_invoice_hash' => $invoice->previous_invoice_hash,
                'qr_code' => $invoice->qr_code,
                'qr_image' => $invoice->qr_code ? ZatcaQr::svgDataUri($invoice->qr_code) : null,
                'zatca_status' => $invoice->zatca_status,
                'accepted' => $invoice->isAccepted(),
                'submitted_at' => $invoice->submitted_at?->format('Y-m-d H:i:s'),
                'has_cleared_xml' => filled($invoice->cleared_xml),
                'errors' => $messages($validation['errorMessages'] ?? []),
                'warnings' => $messages($validation['warningMessages'] ?? []),
                'order' => $invoice->order ? ['id' => $invoice->order->id, 'order_no' => $invoice->order->order_no, 'invoice_no' => $invoice->order->invoice_no] : null,
            ],
            'seller' => $setting ? $setting->only(['seller_name', 'vat_number', 'crn', 'street', 'building_number', 'district', 'city', 'postal_code']) : null,
            'currentEnvironment' => ZatcaSetting::currentEnvironment(),
            // Only submittable with this environment's Production CSID (a sandbox invoice never goes to production).
            'canSubmit' => $setting?->hasProductionCsid() && $invoice->environment === $setting->environment,
        ]);
    }

    public function submit(Invoice $invoice): RedirectResponse
    {
        try {
            $result = $this->zatca->submit($invoice);
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('admin.invoices.show', $invoice)->with('error', $e->getMessage());
        }

        $message = $invoice->isSimplified()
            ? __('ZATCA reporting result: :status', ['status' => $result['status']])
            : __('ZATCA clearance result: :status', ['status' => $result['status']]);

        return redirect()->route('admin.invoices.show', $invoice)->with(empty($result['errors']) ? 'status' : 'error', $message);
    }

    /** Report / clear every invoice that has not been accepted yet (simplified invoices are due within 24 hours). */
    public function submitPending(): RedirectResponse
    {
        try {
            ['total' => $total, 'accepted' => $accepted] = $this->zatca->submitPending();
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', $e->getMessage());
        }

        $failed = $total - $accepted;

        return back()->with($failed ? 'error' : 'status', __('Submitted :total invoice(s): :ok accepted, :failed failed.', [
            'total' => $total, 'ok' => $accepted, 'failed' => $failed,
        ]));
    }

    public function xml(Invoice $invoice, string $version = 'signed'): HttpResponse
    {
        $xml = $version === 'cleared' ? $invoice->cleared_xml : $invoice->signed_xml;
        abort_unless($xml, 404);

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="'.$invoice->invoice_number.'-'.$version.'.xml"',
        ]);
    }

    /**
     * Net (VAT exclusive) lines for a POS order. Line discounts are already in line_total; an order-level
     * discount is spread over the lines in proportion to their amounts.
     *
     * @return array<int, array{name: string, quantity: int, unit_price: float}>
     */
    private function orderLines(Order $order): array
    {
        $rate = 1 + (float) $order->vat_rate / 100;
        $linesSum = (float) $order->items->sum(fn ($i) => (float) $i->line_total);
        $factor = $linesSum > 0 ? (float) $order->total / $linesSum : 1;

        return $order->items->map(fn ($i) => [
            'name' => trim($i->name_ar.($i->name_en ? ' - '.$i->name_en : '')),
            'quantity' => (int) $i->quantity,
            'unit_price' => round((float) $i->line_total * $factor / max(1, (int) $i->quantity) / $rate, 2),
        ])->values()->all();
    }

}
