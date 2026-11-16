<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Admin/Settings', ['settings' => Setting::allValues()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'store_name_en' => ['required', 'string', 'max:150'],
            'store_name_ar' => ['required', 'string', 'max:150'],
            'address_en' => ['nullable', 'string', 'max:255'],
            'address_ar' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'vat_number' => ['nullable', 'string', 'max:30'],
            'cr_number' => ['nullable', 'string', 'max:30'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'currency_en' => ['required', 'string', 'max:10'],
            'currency_ar' => ['required', 'string', 'max:10'],
            'receipt_footer_en' => ['nullable', 'string', 'max:255'],
            'receipt_footer_ar' => ['nullable', 'string', 'max:255'],
        ]);

        Setting::put($data);

        return back()->with('status', __('Saved successfully.'));
    }
}
