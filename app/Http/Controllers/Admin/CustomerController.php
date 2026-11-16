<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Customers are derived from the phone numbers captured on orders (no separate sign-up). */
class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $customers = Order::query()
            ->completed()
            ->whereNotNull('customer_phone')
            ->where('customer_phone', '!=', '')
            ->when($request->filled('q'), fn ($q) => $q->where(function ($q) use ($request) {
                $term = (string) $request->input('q');
                $q->where('customer_phone', 'like', "%{$term}%")->orWhere('customer_name', 'like', "%{$term}%");
            }))
            ->selectRaw('customer_phone, MAX(customer_name) as name, COUNT(*) as orders, SUM(total) as spent, MAX(created_at) as last_order_at')
            ->groupBy('customer_phone')
            ->orderByDesc('last_order_at')
            ->paginate(30)
            ->withQueryString()
            ->through(fn ($row) => [
                'phone' => $row->customer_phone,
                'name' => $row->name,
                'orders' => (int) $row->orders,
                'spent' => (float) $row->spent,
                'last_order_at' => \Illuminate\Support\Carbon::parse($row->last_order_at)->format('d/m/Y h:i A'),
            ]);

        return Inertia::render('Admin/Customers', [
            'customers' => $customers,
            'filters' => ['q' => (string) $request->input('q', '')],
        ]);
    }
}
