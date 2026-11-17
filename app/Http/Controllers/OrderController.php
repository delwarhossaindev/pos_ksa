<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Setting;
use App\Services\Zatca\OrderInvoicer;
use App\Support\Arabic;
use App\Support\Tafqeet;
use App\Support\ZatcaQr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $date = $request->date('date')?->toDateString() ?? now()->toDateString();

        $orders = Order::query()
            ->with('user')
            ->where('business_date', $date)
            // Cashiers only see their own sales; admins see everything.
            ->when(! $request->user()->isAdmin(), fn ($q) => $q->where('user_id', $request->user()->id))
            ->when($request->filled('q'), fn ($q) => $q->where(function ($q) use ($request) {
                $term = $request->string('q');
                $q->where('invoice_no', 'like', "%{$term}%")->orWhere('order_no', $term)->orWhere('customer_phone', 'like', "%{$term}%");
            }))
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Order $o) => $this->summary($o));

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
            'filters' => ['date' => $date, 'q' => (string) $request->input('q', '')],
        ]);
    }

    public function show(Request $request, Order $order): Response
    {
        $this->authorizeView($request, $order);
        $order->load('items', 'user', 'einvoice');

        return Inertia::render('Orders/Show', [
            // ZATCA e-invoice for this sale (superadmins only — the e-invoice screens are theirs).
            'einvoice' => $request->user()->isSuperAdmin() && $order->einvoice ? [
                'id' => $order->einvoice->id,
                'invoice_number' => $order->einvoice->invoice_number,
                'zatca_status' => $order->einvoice->zatca_status,
            ] : null,
            'order' => [
                ...$this->summary($order),
                'date' => $order->created_at->format('d/m/Y h:i A'),
                'hijri' => Arabic::hijri($order->created_at),
                'amount_words' => Tafqeet::sar((float) $order->total),
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'table_no' => $order->table_no,
                'note' => $order->note,
                'subtotal' => (float) $order->subtotal,
                'discount' => (float) $order->discount,
                'net_amount' => (float) $order->net_amount,
                'vat_rate' => (float) $order->vat_rate,
                'vat_amount' => (float) $order->vat_amount,
                'paid_amount' => (float) $order->paid_amount,
                'change_amount' => (float) $order->change_amount,
                'items' => $order->items->map(fn (OrderItem $i) => [
                    'id' => $i->id,
                    'name' => $i->name,
                    'quantity' => $i->quantity,
                    'unit_price' => (float) $i->unit_price,
                    'discount_percent' => (float) $i->discount_percent,
                    'discount_amount' => (float) $i->discount_amount,
                    'line_total' => (float) $i->line_total,
                    'note' => $i->note,
                ]),
            ],
        ]);
    }

    /** Printable 80mm receipt — a plain Blade page, loaded into a hidden iframe by the SPA. */
    public function receipt(Request $request, Order $order): View|HttpResponse
    {
        $this->authorizeView($request, $order);

        $view = view('orders.receipt', [
            'order' => $order->load('items', 'user'),
            'settings' => ['vat_number' => Setting::vatNumber()] + Setting::allValues(),
            'qr' => ZatcaQr::dataUri($order),
            'autoPrint' => $request->boolean('print'),
        ]);

        // ?download=1 → self-contained HTML file (QR inlined) that opens in any browser.
        if ($request->boolean('download')) {
            return response($view->render(), 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$order->invoice_no.'.html"',
            ]);
        }

        return $view;
    }

    /**
     * Voids a sale. If it was e-invoiced, a ZATCA credit note cancels the invoice (signed now, reported
     * after the response); a ZATCA problem never blocks the void, it is shown instead.
     */
    public function void(Order $order, OrderInvoicer $invoicer): RedirectResponse
    {
        if ($order->isVoid()) {
            return back()->with('status', __('Order voided.'));
        }

        $order->update(['status' => 'void']);

        try {
            if ($credit = $invoicer->credit($order)) {
                defer(fn () => $invoicer->report($credit));

                return back()->with('status', __('Order voided. Credit note :no cancels its e-invoice with ZATCA.', ['no' => $credit->invoice_number]));
            }
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('Order voided, but the credit note for its e-invoice could not be created: :error', ['error' => $e->getMessage()]));
        }

        return back()->with('status', __('Order voided.'));
    }

    private function summary(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_no' => $order->order_no,
            'invoice_no' => $order->invoice_no,
            'time' => $order->created_at->format('h:i A'),
            'order_type' => $order->order_type,
            'cashier' => $order->user?->name,
            'payment_method' => $order->payment_method,
            'card_type' => $order->card_type,
            'total' => (float) $order->total,
            'status' => $order->status,
            'receipt_url' => route('orders.receipt', $order),
        ];
    }

    private function authorizeView(Request $request, Order $order): void
    {
        abort_unless($request->user()->isAdmin() || $order->user_id === $request->user()->id, 403);
    }
}
