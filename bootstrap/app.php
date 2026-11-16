<?php

use App\Http\Middleware\ConvertArabicDigits;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use App\Models\Setting;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SetLocale::class, ConvertArabicDigits::class, HandleInertiaRequests::class]);
        $middleware->alias(['admin' => EnsureAdmin::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('pos'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Friendly, translated (Arabic/English) error pages instead of Laravel's English defaults.
        $exceptions->respond(function (SymfonyResponse $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return $response; // POS JSON endpoint handles its own errors
            }

            if ($status === 419) {
                return back()->with('status', __('The page expired, please try again.'));
            }

            $handled = [403, 404, 429, 503];
            if (! config('app.debug')) {
                $handled[] = 500;
            }
            if (! in_array($status, $handled, true)) {
                return $response;
            }

            // Route-less errors (404) never ran the web middleware, so pass what the page needs directly.
            $locale = $request->hasSession() ? $request->session()->get('locale', app()->getLocale()) : app()->getLocale();
            app()->setLocale(in_array($locale, SetLocale::SUPPORTED, true) ? $locale : config('app.locale'));

            return Inertia::render('Error', [
                'status' => $status,
                'locale' => app()->getLocale(),
                'dir' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr',
                'translations' => HandleInertiaRequests::translationsFor(app()->getLocale()),
                'store' => ['name' => Setting::localized('store_name')],
            ])->toResponse($request)->setStatusCode($status);
        });
    })->create();
