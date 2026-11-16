<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    /**
     * Create a completed order. Prices are VAT inclusive; VAT is extracted from the total.
     *
     * Discounts: each line may carry a discount_percent (set on the POS); an optional order-level
     * "discount" amount is applied on top. subtotal = gross of all lines, discount = all discounts.
     *
     * @param  array{order_type:string, items:array<int,array{product_id:int,quantity:int,discount_percent?:float,note?:?string}>, discount?:float, payment_method:string, card_type?:?string, paid_amount?:float, customer_name?:?string, customer_phone?:?string, table_no?:?string, note?:?string}  $data
     */
    public function create(array $data, User $user): Order
    {
        $products = Product::query()
            ->whereIn('id', collect($data['items'])->pluck('product_id'))
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $lines = [];
        $subtotal = 0.0;
        $lineDiscounts = 0.0;

        foreach ($data['items'] as $item) {
            $product = $products->get($item['product_id']);
            if (! $product) {
                throw ValidationException::withMessages(['items' => __('One of the products is no longer available.')]);
            }
            $qty = (int) $item['quantity'];
            $percent = round(min(max((float) ($item['discount_percent'] ?? 0), 0), 100), 2);
            $gross = round((float) $product->price * $qty, 2);
            $lineDiscount = round($gross * $percent / 100, 2);
            $subtotal += $gross;
            $lineDiscounts += $lineDiscount;

            $lines[] = [
                'product_id' => $product->id,
                'name_en' => $product->name_en,
                'name_ar' => $product->name_ar,
                'unit_price' => $product->price,
                'quantity' => $qty,
                'discount_percent' => $percent,
                'discount_amount' => $lineDiscount,
                'line_total' => round($gross - $lineDiscount, 2),
                'note' => $item['note'] ?? null,
            ];
        }

        $subtotal = round($subtotal, 2);
        $lineDiscounts = round($lineDiscounts, 2);
        $orderDiscount = round(min(max((float) ($data['discount'] ?? 0), 0), $subtotal - $lineDiscounts), 2);
        $discount = round($lineDiscounts + $orderDiscount, 2);
        $total = round($subtotal - $discount, 2);
        $vatRate = (float) Setting::get('vat_rate', 15);
        $vat = round($total * $vatRate / (100 + $vatRate), 2);
        $net = round($total - $vat, 2);

        $isCash = $data['payment_method'] === 'cash';
        $paid = $isCash ? round((float) ($data['paid_amount'] ?? $total), 2) : $total;

        if ($isCash && $paid < $total) {
            throw ValidationException::withMessages(['paid_amount' => __('Paid amount is less than the total.')]);
        }

        return DB::transaction(function () use ($data, $user, $lines, $subtotal, $discount, $total, $vatRate, $vat, $net, $isCash, $paid) {
            $today = now()->toDateString();

            // Lock today's orders so two tills never get the same daily number.
            $nextNo = (int) Order::query()->where('business_date', $today)->lockForUpdate()->max('order_no') + 1;

            $order = Order::create([
                'invoice_no' => 'TMP-'.uniqid(),
                'order_no' => $nextNo,
                'business_date' => $today,
                'order_type' => $data['order_type'],
                'user_id' => $user->id,
                'customer_name' => $data['customer_name'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'table_no' => $data['table_no'] ?? null,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'vat_rate' => $vatRate,
                'vat_amount' => $vat,
                'net_amount' => $net,
                'payment_method' => $data['payment_method'],
                'card_type' => $isCash ? null : ($data['card_type'] ?? null),
                'paid_amount' => $paid,
                'change_amount' => $isCash ? round($paid - $total, 2) : 0,
                'note' => $data['note'] ?? null,
            ]);

            $order->update(['invoice_no' => 'INV-'.str_pad((string) $order->id, 7, '0', STR_PAD_LEFT)]);
            $order->items()->createMany($lines);

            return $order->load('items');
        });
    }
}
