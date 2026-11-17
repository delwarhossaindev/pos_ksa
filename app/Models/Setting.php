<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'value'])]
class Setting extends Model
{
    public const DEFAULTS = [
        'store_name_en' => 'My Restaurant',
        'store_name_ar' => 'مطعمي',
        'address_en' => '',
        'address_ar' => '',
        'phone' => '',
        'vat_number' => '',
        'cr_number' => '',
        'vat_rate' => '15',
        'currency_en' => 'SAR',
        'currency_ar' => 'ر.س',
        'receipt_footer_en' => 'Thank you for your visit',
        'receipt_footer_ar' => 'شكراً لزيارتكم',
    ];

    public static function allValues(): array
    {
        return Cache::rememberForever('settings.all', function () {
            return array_merge(self::DEFAULTS, self::query()->pluck('value', 'key')->all());
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::allValues()[$key] ?? $default;
    }

    /** Locale-aware value: get('store_name') → store_name_ar / store_name_en */
    public static function localized(string $key): string
    {
        $values = self::allValues();
        $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';

        return (string) ($values["{$key}_{$locale}"] ?? $values["{$key}_en"] ?? '');
    }

    /**
     * The shop's VAT number as printed on receipts and in their QR code. While ZATCA runs in the
     * sandbox it is ZATCA's test seller VAT, so receipts match the e-invoices; otherwise the store's own.
     */
    public static function vatNumber(): string
    {
        return ZatcaSetting::fixedVat(ZatcaSetting::currentEnvironment(), 'seller') ?? (string) self::get('vat_number');
    }

    /**
     * The shop logo (public/images/logo.png, with its tab icons icon-32 / icon-180), or null to fall back
     * to the initial-letter emblem. The file time busts browser caches when the logo is replaced.
     */
    public static function logoUrl(string $file = 'logo.png'): ?string
    {
        $path = public_path("images/{$file}");

        return is_file($path) ? asset("images/{$file}").'?v='.filemtime($path) : null;
    }

    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            self::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget('settings.all');
    }
}
