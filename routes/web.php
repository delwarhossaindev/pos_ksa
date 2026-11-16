<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ZatcaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PosController;
use Illuminate\Support\Facades\Route;

Route::get('lang/{locale}', LocaleController::class)->name('locale');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'create'])->name('login');
    Route::post('login', [AuthController::class, 'store'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'destroy'])->name('logout');

    Route::get('/', [PosController::class, 'index'])->name('pos');
    Route::get('drawer', [PosController::class, 'drawer'])->name('pos.drawer');
    Route::post('orders', [PosController::class, 'store'])->name('orders.store');

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('orders/{order}/receipt', [OrderController::class, 'receipt'])->name('orders.receipt');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::post('orders/{order}/void', [OrderController::class, 'void'])->name('orders.void');
        Route::get('reports', [ReportController::class, 'index'])->name('reports');
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::patch('products/{product}/toggle', [InventoryController::class, 'toggle'])->name('products.toggle');
        Route::resource('categories', CategoryController::class)->except('show');
        Route::resource('products', ProductController::class)->except('show');
        Route::resource('users', UserController::class)->except('show');
        Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');

        // ZATCA Phase 2 onboarding (Settings → ZATCA)
        Route::prefix('zatca')->name('zatca.')->controller(ZatcaController::class)->group(function () {
            Route::get('/', 'edit')->name('edit');
            Route::put('/', 'update')->name('update');
            Route::post('csr', 'generateCsr')->name('csr');
            Route::post('compliance-csid', 'complianceCsid')->name('compliance-csid');
            Route::post('compliance-checks', 'complianceChecks')->name('compliance-checks');
            Route::post('production-csid', 'productionCsid')->name('production-csid');
        });

        // ZATCA e-invoices, credit and debit notes (Settings → E-Invoices)
        Route::post('invoices/submit-pending', [InvoiceController::class, 'submitPending'])->name('invoices.submit-pending');
        Route::resource('invoices', InvoiceController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('invoices/{invoice}/submit', [InvoiceController::class, 'submit'])->name('invoices.submit');
        Route::get('invoices/{invoice}/xml/{version?}', [InvoiceController::class, 'xml'])
            ->whereIn('version', ['signed', 'cleared'])
            ->name('invoices.xml');
    });
});
