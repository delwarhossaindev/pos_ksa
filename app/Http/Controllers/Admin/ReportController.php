<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $from = $request->date('from')?->toDateString() ?? now()->toDateString();
        $to = $request->date('to')?->toDateString() ?? $from;
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }

        $base = fn () => Order::query()->completed()->whereBetween('business_date', [$from, $to]);

        $summary = $base()->selectRaw('COUNT(*) as orders, COALESCE(SUM(subtotal),0) as gross, COALESCE(SUM(discount),0) as discount, COALESCE(SUM(total),0) as total, COALESCE(SUM(vat_amount),0) as vat, COALESCE(SUM(net_amount),0) as net')->first();

        $byPayment = $base()->selectRaw("payment_method, COALESCE(card_type, '') as card_type, COUNT(*) as orders, SUM(total) as total")
            ->groupBy('payment_method', 'card_type')->orderBy('payment_method')->get();

        $byType = $base()->selectRaw('order_type, COUNT(*) as orders, SUM(total) as total')
            ->groupBy('order_type')->get();

        $byCashier = $base()->with('user')->selectRaw('user_id, COUNT(*) as orders, SUM(total) as total')
            ->groupBy('user_id')->get();

        $topProducts = OrderItem::query()
            ->whereHas('order', fn ($q) => $q->completed()->whereBetween('business_date', [$from, $to]))
            ->selectRaw('product_id, name_en, name_ar, SUM(quantity) as qty, SUM(line_total) as total')
            ->groupBy('product_id', 'name_en', 'name_ar')
            ->orderByDesc('qty')
            ->limit(15)
            ->get();

        $voided = Order::query()->where('status', 'void')->whereBetween('business_date', [$from, $to])->count();

        return Inertia::render('Admin/Reports', [
            'filters' => ['from' => $from, 'to' => $to],
            'summary' => [
                'orders' => (int) $summary->orders,
                'gross' => (float) $summary->gross,
                'discount' => (float) $summary->discount,
                'total' => (float) $summary->total,
                'vat' => (float) $summary->vat,
                'net' => (float) $summary->net,
            ],
            'byPayment' => $byPayment->map(fn ($r) => [
                'payment_method' => $r->payment_method,
                'card_type' => $r->card_type ?: null,
                'orders' => (int) $r->orders,
                'total' => (float) $r->total,
            ]),
            'byType' => $byType->map(fn ($r) => [
                'order_type' => $r->order_type,
                'orders' => (int) $r->orders,
                'total' => (float) $r->total,
            ]),
            'byCashier' => $byCashier->map(fn ($r) => [
                'name' => $r->user?->name ?? '—',
                'orders' => (int) $r->orders,
                'total' => (float) $r->total,
            ]),
            'topProducts' => $topProducts->map(fn ($r) => [
                'name' => $r->name,
                'qty' => (int) $r->qty,
                'total' => (float) $r->total,
            ]),
            'voided' => $voided,
        ]);
    }
}
