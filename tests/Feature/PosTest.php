<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\ZatcaQr;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function admin(): User
    {
        return User::where('role', 'admin')->first();
    }

    private function cashier(): User
    {
        return User::where('role', 'cashier')->first();
    }

    private function superadmin(): User
    {
        return User::where('role', 'superadmin')->first();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    }

    public function test_demo_login_buttons_follow_the_demo_logins_setting(): void
    {
        config(['app.demo_logins' => true]);
        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->has('demoAccounts', 3)
            ->where('demoAccounts.0.email', 'superadmin@pos.test')
            ->where('demoAccounts.0.role', 'superadmin')
            ->where('demoAccounts.1.email', 'admin@pos.test')
            ->where('demoAccounts.1.role', 'admin')
            ->where('demoAccounts.2.email', 'cashier@pos.test')
            ->where('demoAccounts.2.role', 'cashier'));

        // Each button really signs in with its role.
        foreach (['superadmin', 'admin', 'cashier'] as $role) {
            $this->post(route('login'), ['email' => "$role@pos.test", 'password' => 'password'])->assertRedirect(route('pos'));
            $this->assertSame($role, auth()->user()->role);
            $this->post(route('logout'));
        }

        // A deactivated account is not offered.
        $this->cashier()->update(['is_active' => false]);
        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->has('demoAccounts', 2));

        // Off (the production default): no credentials are sent to the browser at all.
        config(['app.demo_logins' => false]);
        $this->get(route('login'))
            ->assertDontSee('admin@pos.test')
            ->assertInertia(fn (Assert $page) => $page->where('demoAccounts', []));
    }

    public function test_user_can_log_in_and_inactive_user_cannot(): void
    {
        $this->post(route('login'), ['email' => 'cashier@pos.test', 'password' => 'password'])
            ->assertRedirect(route('pos'));
        $this->post(route('logout'));

        $this->cashier()->update(['is_active' => false]);
        $this->post(route('login'), ['email' => 'cashier@pos.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');
    }

    public function test_pos_renders_in_arabic_rtl_and_english(): void
    {
        $this->actingAs($this->cashier())
            ->withSession(['locale' => 'ar'])
            ->get('/')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Pos/Index')
                ->where('locale', 'ar')
                ->where('dir', 'rtl')
                ->has('menu', 10)
                ->where('menu.0.name', 'الشاورما')
                ->where('menu.0.products.0.name', 'ساندوتش شاورما')
                ->where('translations.Takeaway', 'سفري')
                ->where('store.name', 'مطعم شاورما علوش للوجبات السريعة')
                ->where('store.vat_rate', 15)
                ->etc());

        $this->actingAs($this->cashier())
            ->withSession(['locale' => 'en'])
            ->get('/')
            ->assertOk()
            ->assertSee('dir="ltr"', false)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Pos/Index')
                ->where('dir', 'ltr')
                ->where('menu.0.products.0.name', 'Shawarma Sandwich')
                ->where('translations', [])
                ->etc());
    }

    public function test_language_switch_stores_locale_in_session(): void
    {
        $this->get(route('locale', 'en'))->assertSessionHas('locale', 'en');
        $this->get(route('locale', 'ar'))->assertSessionHas('locale', 'ar');
        $this->get('/lang/fr')->assertNotFound();
    }

    public function test_order_vat_is_extracted_from_inclusive_price_like_the_real_receipt(): void
    {
        $fries = Product::where('name_en', 'Fries Plate Large')->firstOrFail(); // 22.00 SAR

        $response = $this->actingAs($this->cashier())->postJson(route('orders.store'), [
            'order_type' => 'takeaway',
            'items' => [['product_id' => $fries->id, 'quantity' => 1]],
            'payment_method' => 'card',
            'card_type' => 'visa',
        ])->assertCreated();

        $order = Order::findOrFail($response->json('id'));
        $this->assertSame('22.00', $order->total);
        $this->assertSame('2.87', $order->vat_amount);
        $this->assertSame('19.13', $order->net_amount);
        $this->assertSame(1, $order->order_no);
        $this->assertSame('INV-'.str_pad((string) $order->id, 7, '0', STR_PAD_LEFT), $order->invoice_no);
    }

    public function test_cash_order_with_discount_and_change_and_daily_numbering(): void
    {
        $p = Product::where('name_en', 'Shawarma Sandwich')->firstOrFail(); // 7.00

        $payload = [
            'order_type' => 'dine_in',
            'items' => [['product_id' => $p->id, 'quantity' => 3, 'note' => 'no garlic']],
            'discount' => 1,
            'payment_method' => 'cash',
            'paid_amount' => 50,
            'table_no' => '5',
        ];

        $first = $this->actingAs($this->cashier())->postJson(route('orders.store'), $payload)->assertCreated();
        $this->assertSame(1, $first->json('order_no'));
        $this->assertEquals(20, $first->json('total'));
        $this->assertEquals(30, $first->json('change'));

        $second = $this->actingAs($this->cashier())->postJson(route('orders.store'), $payload)->assertCreated();
        $this->assertSame(2, $second->json('order_no'));

        $this->assertDatabaseHas('order_items', ['name_ar' => 'ساندوتش شاورما', 'quantity' => 3, 'note' => 'no garlic']);
    }

    public function test_cash_payment_below_total_is_rejected(): void
    {
        $p = Product::where('name_en', 'Water')->firstOrFail();

        $this->actingAs($this->cashier())->postJson(route('orders.store'), [
            'order_type' => 'takeaway',
            'items' => [['product_id' => $p->id, 'quantity' => 5]],
            'payment_method' => 'cash',
            'paid_amount' => 2,
        ])->assertUnprocessable()->assertJsonValidationErrors('paid_amount');

        $this->actingAs($this->cashier())->postJson(route('orders.store'), [
            'order_type' => 'takeaway',
            'items' => [],
            'payment_method' => 'cash',
        ])->assertUnprocessable()->assertJsonValidationErrors('items');
    }

    public function test_inactive_product_cannot_be_sold(): void
    {
        $p = Product::first();
        $p->update(['is_active' => false]);

        $this->actingAs($this->cashier())->postJson(route('orders.store'), [
            'order_type' => 'takeaway',
            'items' => [['product_id' => $p->id, 'quantity' => 1]],
            'payment_method' => 'card',
        ])->assertUnprocessable();
    }

    public function test_receipt_shows_tax_invoice_and_valid_zatca_qr(): void
    {
        $p = Product::where('name_en', 'Fries Plate Large')->firstOrFail();
        $id = $this->actingAs($this->cashier())->postJson(route('orders.store'), [
            'order_type' => 'takeaway',
            'items' => [['product_id' => $p->id, 'quantity' => 1]],
            'payment_method' => 'cash',
            'paid_amount' => 22,
        ])->json('id');

        $this->actingAs($this->cashier())->get(route('orders.receipt', $id))
            ->assertOk()
            ->assertSee('فاتورة ضريبية مبسطة')
            ->assertSee('399999999900003') // ZATCA_ENV=sandbox: the shop's VAT is ZATCA's test number
            ->assertDontSee('302256240800003')
            ->assertSee('صحن بطاطس كبير')
            ->assertSee('data:image/svg+xml;base64', false);

        // Decode the TLV payload and check every ZATCA field.
        $tlv = base64_decode(ZatcaQr::payload(Order::find($id)));
        $fields = [];
        for ($i = 0; $i < strlen($tlv);) {
            $tag = ord($tlv[$i]);
            $len = ord($tlv[$i + 1]);
            $fields[$tag] = substr($tlv, $i + 2, $len);
            $i += 2 + $len;
        }
        $this->assertSame('مطعم شاورما علوش للوجبات السريعة', $fields[1]);
        $this->assertSame('399999999900003', $fields[2]);

        // Production: the store's own VAT number from Settings.
        config(['zatca.env' => 'production']);
        $this->actingAs($this->cashier())->get(route('orders.receipt', $id))->assertSee('302256240800003');
        $this->assertStringContainsString('302256240800003', base64_decode(ZatcaQr::payload(Order::find($id))));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $fields[3]);
        $this->assertSame('22.00', $fields[4]);
        $this->assertSame('2.87', $fields[5]);
    }

    public function test_cashier_cannot_access_admin_or_other_cashiers_orders(): void
    {
        $cashier = $this->cashier();
        $this->actingAs($cashier)->get(route('admin.reports'))->assertForbidden();
        $this->actingAs($cashier)->get(route('admin.products.index'))->assertForbidden();

        $p = Product::first();
        $adminOrderId = $this->actingAs($this->admin())->postJson(route('orders.store'), [
            'order_type' => 'takeaway',
            'items' => [['product_id' => $p->id, 'quantity' => 1]],
            'payment_method' => 'card',
        ])->json('id');

        $this->actingAs($cashier)->get(route('orders.receipt', $adminOrderId))->assertForbidden();
        $this->actingAs($this->admin())->get(route('orders.receipt', $adminOrderId))->assertOk();
    }

    public function test_admin_pages_render_in_both_languages(): void
    {
        $p = Product::first();
        $orderId = $this->actingAs($this->admin())->postJson(route('orders.store'), [
            'order_type' => 'delivery',
            'items' => [['product_id' => $p->id, 'quantity' => 2]],
            'payment_method' => 'card',
            'card_type' => 'mada',
        ])->json('id');

        foreach (['en', 'ar'] as $locale) {
            $urls = [
                route('orders.index'),
                route('orders.show', $orderId),
                route('admin.reports'),
                route('admin.categories.index'),
                route('admin.categories.create'),
                route('admin.categories.edit', $p->category_id),
                route('admin.products.index'),
                route('admin.products.create'),
                route('admin.products.edit', $p),
            ];
            foreach ($urls as $url) {
                $this->actingAs($this->admin())->withSession(['locale' => $locale])->get($url)->assertOk();
            }

            $settingsUrls = [
                route('admin.users.index'),
                route('admin.users.create'),
                route('admin.users.edit', $this->cashier()),
                route('admin.settings.edit'),
            ];
            foreach ($settingsUrls as $url) {
                $this->actingAs($this->superadmin())->withSession(['locale' => $locale])->get($url)->assertOk();
            }
        }
    }

    public function test_void_order_is_excluded_from_report(): void
    {
        $p = Product::where('name_en', 'Water')->firstOrFail();
        $make = fn () => $this->actingAs($this->admin())->postJson(route('orders.store'), [
            'order_type' => 'takeaway',
            'items' => [['product_id' => $p->id, 'quantity' => 1]],
            'payment_method' => 'card',
        ])->json('id');

        $make();
        $voidId = $make();
        $this->actingAs($this->admin())->post(route('admin.orders.void', $voidId))->assertRedirect();
        $this->assertSame('void', Order::find($voidId)->status);

        $this->actingAs($this->admin())->withSession(['locale' => 'en'])->get(route('admin.reports'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Reports')
                ->where('summary.orders', 1)
                ->where('summary.total', 1)
                ->where('voided', 1)
                ->etc());
    }

    public function test_admin_crud_and_settings(): void
    {
        $admin = $this->admin();
        $catId = Product::first()->category_id;

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'category_id' => $catId, 'name_en' => 'Extra Cheese', 'name_ar' => 'جبن إضافي', 'price' => 2, 'is_active' => 1,
        ])->assertRedirect(route('admin.products.index'));
        $this->assertDatabaseHas('products', ['name_ar' => 'جبن إضافي', 'price' => 2]);

        $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name_en' => 'Add-ons', 'name_ar' => 'إضافات', 'sort_order' => 9, 'is_active' => 1,
        ])->assertRedirect();
        $this->assertDatabaseHas('categories', ['name_ar' => 'إضافات']);

        $super = $this->superadmin();

        $this->actingAs($super)->post(route('admin.users.store'), [
            'name' => 'Cashier 2', 'email' => 'c2@pos.test', 'role' => 'cashier',
            'password' => 'secret12', 'password_confirmation' => 'secret12', 'is_active' => 1,
        ])->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'c2@pos.test', 'role' => 'cashier']);

        // A superadmin cannot demote or deactivate themselves.
        $this->actingAs($super)->put(route('admin.users.update', $super), [
            'name' => 'Super', 'email' => $super->email, 'role' => 'cashier',
        ])->assertRedirect();
        $this->assertTrue($super->fresh()->isSuperAdmin());
        $this->assertTrue($super->fresh()->is_active);

        $this->actingAs($super)->put(route('admin.settings.update'), [
            'store_name_en' => 'New Name', 'store_name_ar' => 'اسم جديد', 'vat_rate' => 15,
            'currency_en' => 'SAR', 'currency_ar' => 'ر.س',
        ])->assertRedirect();
        $this->actingAs($admin)->withSession(['locale' => 'ar'])->get('/')
            ->assertInertia(fn (Assert $page) => $page->where('store.name', 'اسم جديد')->etc());
    }
}
