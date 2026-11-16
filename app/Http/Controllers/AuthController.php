<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    /** Seeded accounts offered as one-click buttons when DEMO_LOGINS=true (see DatabaseSeeder). */
    private const DEMO_ACCOUNTS = [
        ['role' => 'admin', 'email' => 'admin@pos.test', 'password' => 'password'],
        ['role' => 'cashier', 'email' => 'cashier@pos.test', 'password' => 'password'],
    ];

    public function create(): Response
    {
        $demoAccounts = [];

        if (config('app.demo_logins')) {
            $active = User::query()
                ->whereIn('email', array_column(self::DEMO_ACCOUNTS, 'email'))
                ->where('is_active', true)
                ->pluck('name', 'email');

            // Only offer accounts that still exist and are active.
            foreach (self::DEMO_ACCOUNTS as $account) {
                if ($active->has($account['email'])) {
                    $demoAccounts[] = [...$account, 'name' => $active[$account['email']]];
                }
            }
        }

        return Inertia::render('Auth/Login', ['demoAccounts' => $demoAccounts]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt([...$credentials, 'is_active' => true], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => __('These credentials do not match our records.')]);
        }

        $locale = $request->session()->get('locale');
        $request->session()->regenerate();
        if ($locale) {
            $request->session()->put('locale', $locale);
        }

        return redirect()->intended(route('pos'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $locale = $request->session()->get('locale');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        if ($locale) {
            $request->session()->put('locale', $locale);
        }

        return redirect()->route('login');
    }
}
