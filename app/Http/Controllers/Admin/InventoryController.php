<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Item availability: switch dishes to "sold out" (hidden from the POS) and back with one tap. */
class InventoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Inventory', [
            'categories' => Category::query()->ordered()->with(['products' => fn ($q) => $q->ordered()])->get()
                ->map(fn (Category $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'products' => $c->products->map(fn (Product $p) => [
                        'id' => $p->id,
                        'name' => $p->name,
                        'price' => (float) $p->price,
                        'is_active' => $p->is_active,
                    ]),
                ]),
        ]);
    }

    public function toggle(Product $product): RedirectResponse
    {
        $product->update(['is_active' => ! $product->is_active]);

        return back();
    }
}
