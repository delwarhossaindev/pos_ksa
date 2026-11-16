<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Order;
use App\Models\Setting;
use App\Services\Zatca\OrderInvoicer;
use App\Support\OrderService;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PosController extends Controller
{
    public function index(): Response
    {
        // Closure: a partial reload of the header activity props (shared, see TillActivity) skips the menu.
        return Inertia::render('Pos/Index', ['menu' => fn () => $this->menu()]);
    }

    private function menu()
    {
        return Category::query()
            ->active()
            ->ordered()
            ->with(['products' => fn ($q) => $q->active()->ordered()])
            ->get()
            ->filter(fn (Category $c) => $c->products->isNotEmpty())
            ->values()
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'products' => $c->products->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'price' => (float) $p->price,
                ])->values(),
            ]);
    }

    /** "No sale" slip: printing it makes most receipt printers kick the cash drawer open. */
    public function drawer(Request $request): View
    {
        return view('pos.drawer', [
            'settings' => Setting::allValues(),
            'user' => $request->user(),
        ]);
    }

    /** JSON endpoint called from the POS screen; the page stays put and prints the receipt. */
    public function store(Request $request, OrderService $service, OrderInvoicer $invoicer): JsonResponse
    {
        $data = $request->validate([
            'order_type' => ['required', Rule::in(Order::TYPES)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', Rule::in(Order::PAYMENT_METHODS)],
            'card_type' => ['nullable', Rule::in(Order::CARD_TYPES)],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'customer_name' => ['nullable', 'string', 'max:100'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'table_no' => ['nullable', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $order = $service->create($data, $request->user());

        // ZATCA Phase 2: sign the sale's e-invoice now, so the receipt printed next carries its QR;
        // report it to ZATCA after the response, so the till does not wait on the network.
        // A ZATCA problem never fails the sale: the receipt then keeps the Phase 1 QR, and the till says
        // why (the detail only to admins), so a broken setup is not silent.
        $einvoiceError = null;
        try {
            if ($invoice = $invoicer->issue($order)) {
                defer(fn () => $invoicer->report($invoice));
            }
        } catch (Throwable $e) {
            report($e);
            $einvoiceError = __('The e-invoice could not be created; the receipt has the basic QR.')
                .($request->user()->isAdmin() ? ' '.$e->getMessage() : '');
        }

        return response()->json([
            'id' => $order->id,
            'order_no' => $order->order_no,
            'invoice_no' => $order->invoice_no,
            'total' => (float) $order->total,
            'change' => (float) $order->change_amount,
            'receipt_url' => route('orders.receipt', $order),
            'einvoice_error' => $einvoiceError,
        ], 201);
    }
}
