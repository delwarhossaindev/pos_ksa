<?php

namespace App\Support;

use App\Models\Order;
use App\Models\User;

/**
 * Data for the till header on every screen: today's latest orders (Notifications),
 * the count badge, and the user's last order (reprint / download).
 * Values are closures so Inertia only runs the queries when the props are actually sent,
 * and a partial reload (`only: [...]`) refreshes just these after a sale.
 */
class TillActivity
{
    public static function props(User $user): array
    {
        $today = fn () => Order::query()
            ->where('business_date', now()->toDateString())
            ->when(! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id));

        return [
            'todayCount' => fn () => $today()->completed()->count(),
            'recentOrders' => fn () => $today()->latest('id')->limit(8)->get()->map(fn (Order $o) => [
                'id' => $o->id,
                'order_no' => $o->order_no,
                'order_type' => $o->order_type,
                'total' => (float) $o->total,
                'time' => $o->created_at->format('h:i A'),
                'status' => $o->status,
            ]),
            'lastOrder' => function () use ($user) {
                $o = Order::query()->where('user_id', $user->id)->latest('id')->first();

                return $o ? [
                    'id' => $o->id,
                    'order_no' => $o->order_no,
                    'receipt_url' => route('orders.receipt', $o),
                ] : null;
            },
        ];
    }
}
