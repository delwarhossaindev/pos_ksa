<?php

namespace App\Models;

use App\Models\Concerns\HasTranslatedName;
use App\Support\Arabic;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['category_id', 'name_en', 'name_ar', 'price', 'sort_order', 'is_active'])]
class Product extends Model
{
    use HasTranslatedName;

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            $product->search_text = Arabic::normalizeSearch($product->name_ar.' '.$product->name_en);
        });
    }

    /** Arabic-aware search: folds spelling variants (ة/ه, أ/إ/آ/ا, ى/ي, tashkeel), so "بالجبنه" finds "بالجبنة". Every word must match. */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        foreach (explode(' ', Arabic::normalizeSearch($term)) as $word) {
            if ($word !== '') {
                $query->where('search_text', 'like', '%'.$word.'%');
            }
        }

        return $query;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
