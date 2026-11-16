<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $products = Product::query()
            ->with('category')
            ->when($request->filled('category'), fn ($q) => $q->where('category_id', $request->integer('category')))
            ->when($request->filled('q'), fn ($q) => $q->search((string) $request->input('q')))
            ->orderBy('category_id')
            ->ordered()
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Product $p) => [
                'id' => $p->id,
                'name_en' => $p->name_en,
                'name_ar' => $p->name_ar,
                'category' => $p->category?->name,
                'price' => (float) $p->price,
                'is_active' => $p->is_active,
            ]);

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
            'categories' => $this->categoryOptions(),
            'filters' => [
                'category' => (string) $request->input('category', ''),
                'q' => (string) $request->input('q', ''),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Products/Form', [
            'product' => null,
            'categories' => $this->categoryOptions(),
            'defaultCategory' => $request->integer('category') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Product::create($this->validated($request));

        return redirect()->route('admin.products.index')->with('status', __('Saved successfully.'));
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('Admin/Products/Form', [
            'product' => [
                ...$product->only('id', 'category_id', 'name_en', 'name_ar', 'sort_order', 'is_active'),
                'price' => (float) $product->price,
            ],
            'categories' => $this->categoryOptions(),
            'defaultCategory' => null,
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $product->update($this->validated($request));

        return redirect()->route('admin.products.index')->with('status', __('Saved successfully.'));
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return back()->with('status', __('Deleted successfully.'));
    }

    private function categoryOptions()
    {
        return Category::query()->ordered()->get()->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name]);
    }

    private function validated(Request $request): array
    {
        return [
            ...$request->validate([
                'category_id' => ['required', 'exists:categories,id'],
                'name_en' => ['required', 'string', 'max:150'],
                'name_ar' => ['required', 'string', 'max:150'],
                'price' => ['required', 'numeric', 'min:0', 'max:99999999'],
                'sort_order' => ['nullable', 'integer', 'min:0'],
            ]),
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
