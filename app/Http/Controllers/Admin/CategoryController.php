<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Categories/Index', [
            'categories' => Category::query()->withCount('products')->ordered()->get()
                ->map(fn (Category $c) => [
                    'id' => $c->id,
                    'name_en' => $c->name_en,
                    'name_ar' => $c->name_ar,
                    'sort_order' => $c->sort_order,
                    'is_active' => $c->is_active,
                    'products_count' => $c->products_count,
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Categories/Form', ['category' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        Category::create($this->validated($request));

        return redirect()->route('admin.categories.index')->with('status', __('Saved successfully.'));
    }

    public function edit(Category $category): Response
    {
        return Inertia::render('Admin/Categories/Form', [
            'category' => $category->only('id', 'name_en', 'name_ar', 'sort_order', 'is_active'),
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $category->update($this->validated($request));

        return redirect()->route('admin.categories.index')->with('status', __('Saved successfully.'));
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        return redirect()->route('admin.categories.index')->with('status', __('Deleted successfully.'));
    }

    private function validated(Request $request): array
    {
        return [
            ...$request->validate([
                'name_en' => ['required', 'string', 'max:100'],
                'name_ar' => ['required', 'string', 'max:100'],
                'sort_order' => ['nullable', 'integer', 'min:0'],
            ]),
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
