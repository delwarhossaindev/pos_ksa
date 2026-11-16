<?php

namespace App\Services\Zatca;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\ZatcaSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Issues the ZATCA simplified tax invoice (B2C) of a POS sale the moment it is paid, so the printed
 * receipt carries the Phase 2 QR (tags 1–9: hash, signature and key) instead of the Phase 1 one.
 *
 * Till prices include VAT. The e-invoice needs net lines + 15% VAT, so the order's net amount is spread
 * over the lines to the halala, and whatever net + VAT cannot match of the paid total (at most ±0.01,
 * ZATCA rounds VAT on the whole net) goes into the rounding amount — the payable amount always equals
 * what the customer paid.
 */
class OrderInvoicer
{
    public function __construct(private ZatcaService $zatca) {}

    /** Signed and stored, or null when ZATCA is not onboarded / simplified invoices are not enabled. */
    public function issue(Order $order): ?Invoice
    {
        $setting = ZatcaSetting::current();
        $simplifiedEnabled = ($setting?->invoice_types[1] ?? '0') === '1';

        if (! $setting?->signingCertificate() || ! $simplifiedEnabled || $order->isVoid() || (float) $order->total <= 0) {
            return null;
        }
        if ($order->einvoice()->exists()) {
            return $order->einvoice;
        }

        $order->loadMissing('items');

        return DB::transaction(function () use ($order) {
            $environment = ZatcaSetting::currentEnvironment();

            $invoice = new Invoice([
                'order_id' => $order->id,
                'uuid' => (string) Str::uuid(),
                'invoice_number' => Invoice::where('invoice_number', $order->invoice_no)->exists()
                    ? $order->invoice_no.'-'.Str::upper(Str::random(4))
                    : $order->invoice_no, // the receipt and the e-invoice share the number
                'invoice_type' => Invoice::TYPE_SIMPLIFIED,
                'document_type' => 'invoice',
                'issue_date' => $order->created_at->toDateString(),
                'issue_time' => $order->created_at->format('H:i:s'),
                'payment_means' => $order->payment_method === 'card' ? '48' : '10',
                'buyer_name' => $order->customer_name ?: null,
                'buyer_vat_number' => ZatcaSetting::fixedVat($environment, 'buyer'),
            ]);

            $items = $this->lines($order);
            $invoice->calculateTotals($items);
            $invoice->rounding_amount = round((float) $order->total - (float) $invoice->total_amount, 2);
            $invoice->save();
            $invoice->items()->saveMany($items);
            $invoice->setRelation('items', $items);

            $this->zatca->prepare($invoice);

            return $invoice;
        });
    }

    /** Report to ZATCA (needs the Production CSID). Failures stay "pending" for Settings → E-Invoices. */
    public function report(Invoice $invoice): void
    {
        if (! ZatcaSetting::current()?->hasProductionCsid()) {
            return;
        }

        try {
            $this->zatca->submit($invoice);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Net invoice lines that add up to the order's net amount exactly, in halalas.
     *
     * @return Collection<int, InvoiceItem>
     */
    private function lines(Order $order): Collection
    {
        $vatPercent = (float) $order->vat_rate;
        $netTarget = (int) round((float) $order->net_amount * 100);
        $weights = $order->items->map(fn ($i) => max(0, (int) round((float) $i->line_total * 100)))->all();
        $weightSum = array_sum($weights);

        // Spread the order's net (after the order-level discount) over the lines by their value.
        $nets = [];
        $remainders = [];
        foreach ($weights as $k => $w) {
            $exact = $weightSum > 0 ? $netTarget * $w / $weightSum : 0;
            $nets[$k] = (int) floor($exact);
            $remainders[$k] = $exact - $nets[$k];
        }
        arsort($remainders);
        foreach (array_keys(array_slice($remainders, 0, $netTarget - array_sum($nets), true)) as $k) {
            $nets[$k]++;
        }

        // Each line is quantity × unit price (2 dp); collect what that cannot express.
        $lines = [];
        $leftover = 0;
        foreach ($order->items->values() as $k => $item) {
            $qty = max(1, (int) $item->quantity);
            $price = intdiv($nets[$k], $qty);
            $leftover += $nets[$k] - $price * $qty;
            $lines[] = ['name' => trim($item->name_ar.($item->name_en ? ' - '.$item->name_en : '')), 'qty' => $qty, 'price' => $price];
        }

        // Put the leftover halalas on a single unit: a line of one, or one unit split off the first line.
        if ($leftover > 0) {
            $single = collect($lines)->search(fn ($l) => $l['qty'] === 1);
            if ($single !== false) {
                $lines[$single]['price'] += $leftover;
            } else {
                $lines[0]['qty']--;
                $lines[] = ['name' => $lines[0]['name'], 'qty' => 1, 'price' => $lines[0]['price'] + $leftover];
            }
        }

        return collect($lines)->map(fn ($l) => new InvoiceItem([
            'name' => $l['name'],
            'quantity' => $l['qty'],
            'unit_price' => $l['price'] / 100,
            'vat_category' => 'S',
            'vat_percent' => $vatPercent,
        ]));
    }
}
