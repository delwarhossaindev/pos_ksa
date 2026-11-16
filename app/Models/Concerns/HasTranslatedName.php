<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Exposes a virtual `name` attribute that returns name_ar or name_en
 * depending on the active application locale.
 */
trait HasTranslatedName
{
    protected function name(): Attribute
    {
        return Attribute::get(fn () => app()->getLocale() === 'ar'
            ? ($this->name_ar ?: $this->name_en)
            : ($this->name_en ?: $this->name_ar));
    }
}
