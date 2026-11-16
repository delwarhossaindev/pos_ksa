<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Support\Arabic;
use App\Support\Tafqeet;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ArabicTest extends TestCase
{
    use RefreshDatabase;

    public static function amounts(): array
    {
        return [
            [22, 'فقط اثنان وعشرون ريالاً لا غير'],
            [15.5, 'فقط خمسة عشر ريالاً وخمسون هللة لا غير'],
            [1, 'فقط ريال واحد لا غير'],
            [2, 'فقط ريالان لا غير'],
            [3, 'فقط ثلاثة ريالات لا غير'],
            [11, 'فقط أحد عشر ريالاً لا غير'],
            [21, 'فقط واحد وعشرون ريالاً لا غير'],
            [100, 'فقط مائة ريال لا غير'],
            [200, 'فقط مائتا ريال لا غير'],
            [1150, 'فقط ألف ومائة وخمسون ريالاً لا غير'],
            [2000, 'فقط ألفا ريال لا غير'],
            [3000, 'فقط ثلاثة آلاف ريال لا غير'],
            [11000, 'فقط أحد عشر ألف ريال لا غير'],
            [0.01, 'فقط هللة واحدة لا غير'],
            [0.21, 'فقط إحدى وعشرون هللة لا غير'],
            [22.25, 'فقط اثنان وعشرون ريالاً وخمس وعشرون هللة لا غير'],
        ];
    }

    #[DataProvider('amounts')]
    public function test_amount_in_arabic_words(float $amount, string $expected): void
    {
        $this->assertSame($expected, Tafqeet::sar($amount));
    }

    public function test_arabic_normalization_and_hijri(): void
    {
        $this->assertSame('احمد بالجبنه اضافي', Arabic::normalizeSearch('أَحْمَد بالجبنة إضافى'));
        $this->assertSame('15.5', Arabic::normalizeDigits('١٥٫٥'));
        $this->assertSame('053', Arabic::normalizeDigits('۰۵۳'));

        $date = Carbon::parse('2026-11-16 12:00', 'Asia/Riyadh');
        $this->assertSame('الاثنين 6 جمادى الآخرة 1448 هـ', Arabic::hijri($date, 'ar', true));
        $this->assertSame('6 Jumada II 1448 AH', Arabic::hijri($date, 'en'));
    }

    public function test_arabic_digits_are_accepted_in_forms_and_search_is_arabic_aware(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('role', 'admin')->first();
        $categoryId = Product::first()->category_id;

        // Price typed on an Arabic keyboard.
        $this->actingAs($admin)->post(route('admin.products.store'), [
            'category_id' => $categoryId, 'name_en' => 'Garlic Bread', 'name_ar' => 'خبز بالثوم',
            'price' => '١٢٫٥', 'sort_order' => '٣', 'is_active' => 1,
        ])->assertRedirect(route('admin.products.index'));
        $this->assertDatabaseHas('products', ['name_ar' => 'خبز بالثوم', 'price' => 12.5, 'sort_order' => 3]);

        // Search folds spelling variants of the same letter (ة/ه, أ/ا); every word must match.
        $this->actingAs($admin)->get(route('admin.products.index', ['q' => 'برجر لحم بالجبن']))
            ->assertInertia(fn (Assert $page) => $page->where('products.data.0.name_ar', 'برجر لحم بالجبن')->etc());

        $this->actingAs($admin)->get(route('admin.products.index', ['q' => 'ورق عنب صحن']))
            ->assertInertia(fn (Assert $page) => $page->where('products.total', 4)->etc());

        $this->actingAs($admin)->get(route('admin.products.index', ['q' => 'سلطه زيتون']))   // ه instead of ة
            ->assertInertia(fn (Assert $page) => $page->where('products.total', 3)->etc());

        $this->actingAs($admin)->get(route('admin.products.index', ['q' => 'بروستد اصابع'])) // ا instead of أ
            ->assertInertia(fn (Assert $page) => $page->where('products.data.0.name_ar', 'بروستد أصابع')->etc());
    }

    public function test_receipt_and_order_details_show_hijri_date_and_amount_in_words(): void
    {
        $this->seed(DatabaseSeeder::class);
        $cashier = User::where('role', 'cashier')->first();
        $fries = Product::where('name_en', 'Fries Plate Large')->firstOrFail();

        $id = $this->actingAs($cashier)->postJson(route('orders.store'), [
            'order_type' => 'takeaway',
            'items' => [['product_id' => $fries->id, 'quantity' => 1]],
            'payment_method' => 'card',
        ])->json('id');

        $this->actingAs($cashier)->get(route('orders.receipt', $id))
            ->assertOk()
            ->assertSee('التاريخ الهجري')
            ->assertSee(' هـ')
            ->assertSee('فقط اثنان وعشرون ريالاً لا غير');

        $this->actingAs($cashier)->withSession(['locale' => 'ar'])->get(route('orders.show', $id))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Orders/Show')
                ->where('order.amount_words', 'فقط اثنان وعشرون ريالاً لا غير')
                ->where('order.hijri', fn ($v) => str_ends_with($v, ' هـ'))
                ->etc());
    }

    public function test_error_pages_are_translated(): void
    {
        $this->get('/no-such-page')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', 404)
                ->where('dir', 'rtl')
                ->where('translations.Page not found', 'الصفحة غير موجودة')
                ->etc());

        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::where('role', 'cashier')->first())
            ->withSession(['locale' => 'en'])
            ->get(route('admin.reports'))
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 403)->where('dir', 'ltr')->etc());
    }
}
