<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PosScreenTest extends TestCase
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

    private function sell(User $user, array $items, array $extra = []): int
    {
        return $this->actingAs($user)->postJson(route('orders.store'), [
            'order_type' => 'takeaway',
            'items' => $items,
            'payment_method' => 'card',
            ...$extra,
        ])->assertCreated()->json('id');
    }

    public function test_per_line_discount_percent_is_applied_and_stored(): void
    {
        $plate = Product::where('name_en', 'Shawarma Plate Large')->firstOrFail(); // 34.00
        $water = Product::where('name_en', 'Water')->firstOrFail();                 // 1.00

        $id = $this->sell($this->cashier(), [
            ['product_id' => $plate->id, 'quantity' => 2, 'discount_percent' => 10], // 68.00 − 6.80
            ['product_id' => $water->id, 'quantity' => 3],                           // 3.00
        ]);

        $order = Order::with('items')->find($id);
        $this->assertSame('71.00', $order->subtotal);   // items total (gross)
        $this->assertSame('6.80', $order->discount);    // discount total
        $this->assertSame('64.20', $order->total);
        $this->assertSame('8.37', $order->vat_amount);  // 64.20 × 15 / 115
        $this->assertSame('55.83', $order->net_amount);

        $line = $order->items->firstWhere('product_id', $plate->id);
        $this->assertSame('10.00', $line->discount_percent);
        $this->assertSame('6.80', $line->discount_amount);
        $this->assertSame('61.20', $line->line_total);

        $this->actingAs($this->cashier())->get(route('orders.receipt', $id))
            ->assertOk()
            ->assertSee('10% (-6.80)')
            ->assertSee('68.00'); // item shown at full price, discount on its own line
    }

    public function test_discount_percent_is_validated(): void
    {
        $p = Product::first();
        $this->actingAs($this->cashier())->postJson(route('orders.store'), [
            'order_type' => 'takeaway',
            'items' => [['product_id' => $p->id, 'quantity' => 1, 'discount_percent' => 150]],
            'payment_method' => 'card',
        ])->assertUnprocessable()->assertJsonValidationErrors('items.0.discount_percent');
    }

    public function test_pos_screen_props_for_notifications_and_last_order(): void
    {
        $p = Product::first();
        $cashier = $this->cashier();

        $this->actingAs($cashier)->get('/')->assertInertia(fn (Assert $page) => $page
            ->component('Pos/Index')
            ->where('todayCount', 0)
            ->where('recentOrders', [])
            ->where('lastOrder', null)
            ->has('menu', 10));

        $first = $this->sell($cashier, [['product_id' => $p->id, 'quantity' => 1]]);
        $second = $this->sell($cashier, [['product_id' => $p->id, 'quantity' => 2]]);
        $this->sell($this->admin(), [['product_id' => $p->id, 'quantity' => 1]]); // not this cashier's

        $this->actingAs($cashier)->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('todayCount', 2)
            ->has('recentOrders', 2)
            ->where('recentOrders.0.id', $second)
            ->where('recentOrders.1.id', $first)
            ->where('lastOrder.id', $second)
            ->etc());

        // Admin sees every order of the day.
        $this->actingAs($this->admin())->get('/')->assertInertia(fn (Assert $page) => $page->where('todayCount', 3)->etc());
    }

    public function test_ramadan_items_and_add_ons_are_on_the_till(): void
    {
        $this->actingAs($this->cashier())->withSession(['locale' => 'ar'])->get('/')
            ->assertInertia(fn (Assert $page) => $page
                ->where('menu.8.name', 'رمضانيات')
                ->has('menu.8.products', 10)
                ->where('menu.9.name', 'إضافات')
                ->has('menu.9.products', 10)
                ->etc());
    }

    public function test_till_header_data_is_shared_on_every_screen(): void
    {
        $id = $this->sell($this->admin(), [['product_id' => Product::first()->id, 'quantity' => 1]]);

        // Bottom-bar screens render inside the same frame, so the header needs its data there too.
        foreach (['orders.index', 'admin.reports', 'admin.inventory.index', 'admin.customers.index', 'admin.products.index', 'admin.settings.edit'] as $name) {
            $this->actingAs($this->admin())->get(route($name))->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('todayCount', 1)
                ->where('recentOrders.0.id', $id)
                ->where('lastOrder.id', $id)
                ->etc());
        }
    }

    public function test_guests_get_no_till_header_data(): void
    {
        // Separate test: within one test the app (and Inertia's shared props) is reused across requests.
        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->missing('todayCount')->missing('lastOrder')->etc());
    }

    public function test_cash_drawer_slip_and_receipt_download(): void
    {
        $this->actingAs($this->cashier())->get(route('pos.drawer'))
            ->assertOk()
            ->assertSee('NO SALE')
            ->assertSee('كاشير 1');

        $id = $this->sell($this->cashier(), [['product_id' => Product::first()->id, 'quantity' => 1]]);
        $invoice = Order::find($id)->invoice_no;

        $this->actingAs($this->cashier())->get(route('orders.receipt', ['order' => $id, 'download' => 1]))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="'.$invoice.'.html"')
            ->assertSee('فاتورة ضريبية مبسطة');
    }

    public function test_customers_page_aggregates_orders_by_phone(): void
    {
        $p = Product::where('name_en', 'Water')->firstOrFail();
        $this->sell($this->cashier(), [['product_id' => $p->id, 'quantity' => 2]], ['customer_name' => 'أحمد', 'customer_phone' => '0551234567']);
        $this->sell($this->cashier(), [['product_id' => $p->id, 'quantity' => 3]], ['customer_phone' => '٠٥٥١٢٣٤٥٦٧']); // Arabic digits → same customer
        $this->sell($this->cashier(), [['product_id' => $p->id, 'quantity' => 1]]); // no phone → not a customer

        $this->actingAs($this->admin())->get(route('admin.customers.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Customers')
                ->has('customers.data', 1)
                ->where('customers.data.0.phone', '0551234567')
                ->where('customers.data.0.name', 'أحمد')
                ->where('customers.data.0.orders', 2)
                ->where('customers.data.0.spent', 5)
                ->etc());

        $this->actingAs($this->cashier())->get(route('admin.customers.index'))->assertForbidden();
    }

    public function test_inventory_toggle_hides_item_from_pos(): void
    {
        $p = Product::where('name_en', 'Shawarma Sandwich')->firstOrFail();

        $this->actingAs($this->admin())->get(route('admin.inventory.index'))
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Inventory')->has('categories', 10));

        $this->actingAs($this->admin())->patch(route('admin.products.toggle', $p))->assertRedirect();
        $this->assertFalse($p->fresh()->is_active);

        $this->actingAs($this->cashier())->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('menu.0.products.0.name', 'ساندوتش شاورما بالجبن') // first item now the next one
            ->etc());

        $this->actingAs($this->admin())->patch(route('admin.products.toggle', $p));
        $this->assertTrue($p->fresh()->is_active);

        $this->actingAs($this->cashier())->patch(route('admin.products.toggle', $p))->assertForbidden();
    }
}
