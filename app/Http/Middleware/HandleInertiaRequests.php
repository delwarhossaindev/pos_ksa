<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Support\TillActivity;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $locale = app()->getLocale();
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role,
                    'is_admin' => $user->isAdmin(),
                    'is_superadmin' => $user->isSuperAdmin(),
                ] : null,
            ],
            'locale' => $locale,
            'dir' => $locale === 'ar' ? 'rtl' : 'ltr',
            'translations' => fn () => self::translationsFor($locale),
            'store' => fn () => [
                'name' => Setting::localized('store_name'),
                'currency' => Setting::localized('currency'),
                'vat_rate' => (float) Setting::get('vat_rate', 15),
                'timezone' => config('app.timezone'),
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'error' => fn () => $request->session()->get('error'),
            ],
            // Till header (Notifications badge/list, reprint/download last receipt) on every screen.
            ...($user ? TillActivity::props($user) : []),
        ];
    }

    /** UI strings for the active locale (English keys are the source text, so English needs none). */
    public static function translationsFor(string $locale): array
    {
        if ($locale === 'en') {
            return [];
        }

        static $cache = [];

        return $cache[$locale] ??= (function () use ($locale) {
            $file = lang_path("{$locale}.json");

            return is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
        })();
    }
}
