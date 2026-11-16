<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\ZatcaSetting;
use App\Services\Zatca\QrCode;
use App\Support\ZatcaQr;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * ZATCA Phase 2 (ported from zatca_app): onboarding, signing, reporting and the POS order link.
 * The Fatoora API is faked; the certificate is self-signed with the generated EGS key.
 */
class ZatcaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /** ZATCA onboarding and e-invoices are Settings screens: superadmin only. */
    private function admin(): User
    {
        return User::where('role', 'superadmin')->first();
    }

    private function cashier(): User
    {
        return User::where('role', 'cashier')->first();
    }

    private function sellerData(): array
    {
        return [
            'environment' => 'sandbox',
            'seller_name' => 'مطعم شاورما',
            'vat_number' => '399999999900003',
            'crn' => '1010010000',
            'street' => 'King Fahd Road',
            'building_number' => '1234',
            'plot_identification' => '5678',
            'district' => 'Al Olaya',
            'city' => 'Riyadh',
            'postal_code' => '12345',
            'egs_name' => 'EGS1-TEST',
            'egs_serial' => '1-POS|2-1.0|3-abc',
            'branch_name' => 'Riyadh Branch',
            'industry' => 'Restaurant',
            'invoice_types' => '0100',
        ];
    }

    /** binarySecurityToken as ZATCA returns it: base64 of the base64 DER certificate. */
    private function fakeToken(ZatcaSetting $setting): string
    {
        $config = tempnam(sys_get_temp_dir(), 'zt');
        file_put_contents($config, "[ req ]\ndistinguished_name = dn\n[ dn ]\n");
        $cert = openssl_csr_sign($setting->csr, null, $setting->private_key, 365, ['digest_alg' => 'sha256', 'config' => $config]);
        @unlink($config);
        $this->assertNotFalse($cert, 'Could not self-sign the test certificate: '.openssl_error_string());
        openssl_x509_export($cert, $pem);

        return base64_encode(preg_replace('/-----(BEGIN|END) CERTIFICATE-----|\s+/', '', $pem));
    }

    private function onboard(string $environment = 'sandbox'): ZatcaSetting
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put(route('admin.zatca.update'), ['environment' => $environment] + $this->sellerData())->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.zatca.csr'))->assertSessionHas('status');

        $setting = ZatcaSetting::current();
        $this->assertSame($environment, $setting->environment);
        $this->assertTrue($setting->hasCsr());
        $token = $this->fakeToken($setting);

        Http::fake([
            '*/compliance/invoices' => Http::response(['validationResults' => ['status' => 'PASS', 'errorMessages' => [], 'warningMessages' => []], 'reportingStatus' => 'REPORTED']),
            '*/compliance' => Http::response(['requestID' => 1234567890123, 'binarySecurityToken' => $token, 'secret' => 'compliance-secret']),
            '*/production/csids' => Http::response(['requestID' => 99, 'binarySecurityToken' => $token, 'secret' => 'production-secret']),
            '*/invoices/reporting/single' => Http::response(['validationResults' => ['status' => 'PASS', 'errorMessages' => [], 'warningMessages' => []], 'reportingStatus' => 'REPORTED']),
        ]);

        $this->actingAs($admin)->post(route('admin.zatca.compliance-csid'), ['otp' => '123345'])->assertSessionHas('status');
        $this->actingAs($admin)->post(route('admin.zatca.compliance-checks'))->assertSessionHas('status');
        $this->actingAs($admin)->post(route('admin.zatca.production-csid'))->assertSessionHas('status');

        return $setting->fresh();
    }

    public function test_onboarding_pages_are_superadmin_only(): void
    {
        $plainAdmin = User::where('role', 'admin')->first();
        foreach ([$this->cashier(), $plainAdmin] as $user) {
            $this->actingAs($user)->get(route('admin.zatca.edit'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.invoices.index'))->assertForbidden();
        }

        $this->actingAs($this->admin())->get(route('admin.zatca.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Zatca')
                ->where('status.saved', false)
                ->where('setting.environment', 'sandbox')
                ->where('setting.vat_number', '399999999900003') // sandbox: ZATCA's test seller VAT
                ->where('fixedSellerVat', ['sandbox' => '399999999900003'])
                ->where('storeVat', '302256240800003') // what simulation / production start from
                ->has('environments', 3));

        $this->actingAs($this->admin())->get(route('admin.invoices.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Invoices/Index')->where('canIssue', false));
    }

    public function test_settings_validation(): void
    {
        // Production: the VAT number is the seller's own and is validated.
        $this->actingAs($this->admin())
            ->put(route('admin.zatca.update'), ['vat_number' => '123', 'environment' => 'production'] + $this->sellerData())
            ->assertSessionHasErrors('vat_number');

        $this->actingAs($this->admin())
            ->post(route('admin.zatca.csr'))
            ->assertSessionHas('error');
    }

    private function sale(array $items, array $extra = []): Order
    {
        $id = $this->actingAs($this->cashier())->postJson(route('orders.store'), [
            'order_type' => 'takeaway',
            'items' => $items,
            'payment_method' => 'card',
            'card_type' => 'mada',
            ...$extra,
        ])->assertCreated()->json('id');

        return Order::findOrFail($id);
    }

    public function test_full_onboarding_then_pos_order_e_invoice_is_signed_reported_and_printed(): void
    {
        $fries = Product::where('name_en', 'Fries Plate Large')->firstOrFail(); // 22.00 incl. VAT

        // A sale before onboarding: no e-invoice, the receipt keeps the Phase 1 QR.
        $early = $this->sale([['product_id' => $fries->id, 'quantity' => 1]]);
        $this->assertNull($early->einvoice);
        $this->assertArrayNotHasKey(6, QrCode::decode(ZatcaQr::payload($early)));

        $setting = $this->onboard();
        $this->assertTrue($setting->hasProductionCsid());
        $this->assertCount(3, $setting->compliance_checks); // simplified invoice, credit and debit note

        // That earlier sale can still get its e-invoice by hand: the form is prefilled with net prices.
        $this->actingAs($this->admin())->get(route('admin.invoices.create', ['order' => $early->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Invoices/Create')
                ->where('defaults.order_id', $early->id)
                ->where('defaults.payment_means', '48')
                ->where('defaults.items.0.quantity', 1)
                ->where('defaults.items.0.unit_price', 19.13)
                ->where('allowedTypes', ['simplified']));
        $this->actingAs($this->admin())->post(route('admin.invoices.store'), [
            'invoice_type' => 'simplified',
            'document_type' => 'invoice',
            'issue_date' => $early->created_at->toDateString(),
            'payment_means' => '48',
            'order_id' => $early->id,
            'items' => [['name' => 'Fries', 'quantity' => 1, 'unit_price' => 19.13]],
            'submit_now' => true,
        ])->assertRedirect();
        $manual = $early->fresh()->einvoice;
        $this->assertSame($early->invoice_no, $manual->invoice_number);
        $this->assertSame(1, (int) $manual->icv);
        $this->assertSame(ZatcaSetting::INITIAL_PIH, $manual->previous_invoice_hash);

        // From now on every sale is e-invoiced by itself: signed before the receipt prints, reported after.
        $order = $this->sale([['product_id' => $fries->id, 'quantity' => 2]]);
        $invoice = $order->einvoice;
        $this->assertNotNull($invoice);
        $this->assertSame($order->invoice_no, $invoice->invoice_number); // receipt and e-invoice share the number
        $this->assertSame('simplified', $invoice->invoice_type);
        $this->assertSame('48', $invoice->payment_means);
        $this->assertSame('REPORTED', $invoice->zatca_status);
        $this->assertSame(2, (int) $invoice->icv);
        $this->assertSame($manual->invoice_hash, $invoice->previous_invoice_hash);
        $this->assertStringContainsString('<ds:SignatureValue>', $invoice->signed_xml);
        $this->assertSame('44.00', number_format((float) $invoice->total_amount, 2));
        $this->assertSame('0.00', number_format((float) $invoice->rounding_amount, 2));
        $this->assertSame([[2, '19.13']], $invoice->items->map(fn ($i) => [(int) $i->quantity, number_format((float) $i->unit_price, 2)])->all());

        // Phase 2 QR (tags 1–9) is on the printed receipt.
        $tags = QrCode::decode(ZatcaQr::payload($order->fresh()));
        $this->assertSame('399999999900003', $tags[2]);
        $this->assertSame('44.00', $tags[4]);
        $this->assertArrayHasKey(9, $tags);
        $this->actingAs($this->cashier())->get(route('orders.receipt', $order))->assertOk()->assertSee('data:image/svg+xml;base64', false);

        // Hash chain continues on the next document.
        $this->assertSame($invoice->invoice_hash, ZatcaSetting::current()->previous_invoice_hash);

        // Order page links the e-invoice; a second invoice for the same order is refused.
        $this->actingAs($this->admin())->get(route('orders.show', $order))
            ->assertInertia(fn (Assert $page) => $page->where('einvoice.invoice_number', $order->invoice_no));
        $this->actingAs($this->admin())->get(route('admin.invoices.create', ['order' => $order->id]))
            ->assertRedirect(route('admin.invoices.show', $invoice));
        $orderId = $order->id;

        // Credit note for the invoice
        $this->actingAs($this->admin())->post(route('admin.invoices.store'), [
            'invoice_type' => 'simplified',
            'document_type' => 'credit',
            'issue_date' => now()->toDateString(),
            'payment_means' => '48',
            'original_invoice_number' => $invoice->invoice_number,
            'note_reason' => 'Returned items',
            'order_id' => $orderId,
            'items' => [['name' => 'Fries', 'quantity' => 1, 'unit_price' => 19.13]],
        ])->assertRedirect();
        $credit = Invoice::where('document_type', 'credit')->firstOrFail();
        $this->assertSame('CRN-000001', $credit->invoice_number);
        $this->assertSame(3, (int) $credit->icv);
        $this->assertSame('pending', $credit->zatca_status);

        // Pages render, XML downloads, pending submit reports the credit note.
        $this->actingAs($this->admin())->get(route('admin.invoices.show', $credit))
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Invoices/Show')->where('invoice.is_note', true));
        $this->actingAs($this->admin())->get(route('admin.invoices.xml', $invoice))
            ->assertOk()->assertHeader('Content-Type', 'application/xml');
        $this->actingAs($this->admin())->post(route('admin.invoices.submit-pending'))->assertSessionHas('status');
        $this->assertSame('REPORTED', $credit->fresh()->zatca_status);

        $this->actingAs($this->admin())->get(route('admin.invoices.index', ['status' => 'accepted']))
            ->assertInertia(fn (Assert $page) => $page->where('stats.accepted', 3)->has('invoices.data', 3));

        Http::assertSent(fn ($request) => $request->hasHeader('Accept-Version', 'V2'));
    }

    public function test_pos_e_invoice_always_charges_exactly_what_was_paid(): void
    {
        $this->onboard();
        $fries = Product::where('name_en', 'Fries Plate Large')->firstOrFail();   // 22.00
        $shawarma = Product::where('name_en', 'Shawarma Sandwich')->firstOrFail(); // 7.00

        $payable = fn (Invoice $i) => preg_match('#<cbc:PayableAmount currencyID="SAR">([\d.]+)<#', $i->signed_xml, $m) ? $m[1] : null;

        // 10.00 cannot be split into net + 15% VAT to the halala (8.70 + 1.31 = 10.01):
        // the e-invoice says 10.01 incl. VAT, rounds −0.01, and the payable amount is the 10.00 paid.
        $order = $this->sale([['product_id' => $fries->id, 'quantity' => 1]], ['discount' => 12]);
        $this->assertSame('10.00', number_format((float) $order->total, 2));
        $invoice = $order->einvoice;
        $this->assertSame('8.70', number_format((float) $invoice->line_extension_amount, 2));
        $this->assertSame('10.01', number_format((float) $invoice->total_amount, 2));
        $this->assertSame('-0.01', number_format((float) $invoice->rounding_amount, 2));
        $this->assertStringContainsString('<cbc:PayableRoundingAmount currencyID="SAR">-0.01</cbc:PayableRoundingAmount>', $invoice->signed_xml);
        $this->assertSame('10.00', $payable($invoice));
        $this->assertSame('REPORTED', $invoice->zatca_status);
        // QR tag 4 is the amount due (BT-115), so the receipt's QR shows the 10.00 paid.
        $this->assertSame('10.00', QrCode::decode(ZatcaQr::payload($order->fresh()))[4]);

        // 2 × 7.00 = 14.00 → net 12.17, which 2 × a 2-decimal price cannot make: one unit is split off.
        $order = $this->sale([['product_id' => $shawarma->id, 'quantity' => 2]]);
        $invoice = $order->einvoice;
        $this->assertSame([[1, '6.08'], [1, '6.09']], $invoice->items->map(fn ($i) => [(int) $i->quantity, number_format((float) $i->unit_price, 2)])->all());
        $this->assertSame('12.17', number_format((float) $invoice->line_extension_amount, 2));
        $this->assertSame('14.00', number_format((float) $invoice->total_amount, 2));
        $this->assertNull($invoice->rounding_amount == 0 ? null : $invoice->rounding_amount);
        $this->assertStringNotContainsString('PayableRoundingAmount', $invoice->signed_xml);
        $this->assertSame('14.00', $payable($invoice));

        // Line and order discounts: the lines still add up to the order's net exactly.
        $order = $this->sale([
            ['product_id' => $fries->id, 'quantity' => 3, 'discount_percent' => 10],
            ['product_id' => $shawarma->id, 'quantity' => 1],
        ], ['discount' => 2.5]);
        $invoice = $order->einvoice;
        $this->assertSame(number_format((float) $order->net_amount, 2), number_format((float) $invoice->line_extension_amount, 2));
        $this->assertSame(number_format((float) $order->total, 2), $payable($invoice));
    }

    public function test_a_zatca_failure_never_blocks_the_sale(): void
    {
        $this->onboard();
        // ZATCA goes down: a fresh fake, so onboarding's "REPORTED" answer no longer matches first.
        Http::swap(new \Illuminate\Http\Client\Factory(app('events')));
        Http::fake(['*' => Http::response(['message' => 'Service unavailable'], 503)]);
        $fries = Product::where('name_en', 'Fries Plate Large')->firstOrFail();

        $order = $this->sale([['product_id' => $fries->id, 'quantity' => 1]]);

        // Sold and signed (Phase 2 QR on the receipt); reporting waits in Settings → E-Invoices.
        $invoice = $order->einvoice;
        $this->assertNotNull($invoice);
        $this->assertArrayHasKey(9, QrCode::decode(ZatcaQr::payload($order)));
        $this->assertContains($invoice->zatca_status, ['pending', 'ERROR']);
    }

    public static function environments(): array
    {
        return [
            'sandbox' => ['sandbox', 'TSTZATCA-Code-Signing', 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal/'],
            'simulation' => ['simulation', 'PREZATCA-Code-Signing', 'https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation/'],
            'production' => ['production', 'ZATCA-Code-Signing', 'https://gw-fatoora.zatca.gov.sa/e-invoicing/core/'],
        ];
    }

    public function test_sandbox_locks_shop_and_customer_vat_and_production_lets_them_change(): void
    {
        $invoice = fn (array $extra = []) => $this->actingAs($this->admin())->post(route('admin.invoices.store'), [
            'invoice_type' => 'simplified',
            'document_type' => 'invoice',
            'issue_date' => now()->toDateString(),
            'payment_means' => '10',
            'buyer_name' => 'Customer Co',
            'items' => [['name' => 'Shawarma', 'quantity' => 1, 'unit_price' => 20]],
            ...$extra,
        ])->assertSessionHasNoErrors();

        // Sandbox: whatever is typed, the shop gets 399999999900003 and the customer 311111111111113.
        $this->onboard('sandbox');
        $this->actingAs($this->admin())->put(route('admin.zatca.update'), ['vat_number' => '300000000000003'] + $this->sellerData());
        $this->assertSame('399999999900003', ZatcaSetting::current()->vat_number);

        $this->actingAs($this->admin())->get(route('admin.invoices.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('fixedBuyerVat', '311111111111113')
                ->where('defaults.buyer_vat_number', '311111111111113'));

        $invoice(['buyer_vat_number' => '300000000000003']);
        $sandboxInvoice = Invoice::latest('id')->firstOrFail();
        $this->assertSame('311111111111113', $sandboxInvoice->buyer_vat_number);
        $this->assertStringContainsString('<cbc:CompanyID>311111111111113</cbc:CompanyID>', $sandboxInvoice->signed_xml);
        $this->assertStringContainsString('<cbc:CompanyID>399999999900003</cbc:CompanyID>', $sandboxInvoice->signed_xml);

        // Production: both are the real numbers, typed by hand.
        $this->onboard('production');
        $this->actingAs($this->admin())->put(route('admin.zatca.update'), ['environment' => 'production', 'vat_number' => '300000000000003'] + $this->sellerData());
        $this->assertSame('300000000000003', ZatcaSetting::current()->vat_number);

        $this->actingAs($this->admin())->get(route('admin.invoices.create'))
            ->assertInertia(fn (Assert $page) => $page->where('fixedBuyerVat', null)->where('defaults.buyer_vat_number', ''));

        $invoice(['buyer_vat_number' => '310000000000003']);
        $this->assertSame('310000000000003', Invoice::latest('id')->firstOrFail()->buyer_vat_number);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('environments')]
    public function test_every_environment_onboards_signs_and_reports_against_its_own_url(string $env, string $template, string $baseUrl): void
    {
        $setting = $this->onboard($env);

        // The CSR carries the environment's certificate template.
        $der = base64_decode(preg_replace('/-----(BEGIN|END) CERTIFICATE REQUEST-----|\s+/', '', $setting->csr));
        $this->assertStringContainsString($template, $der);
        foreach (array_diff(['TSTZATCA-Code-Signing', 'PREZATCA-Code-Signing'], [$template]) as $other) {
            $this->assertStringNotContainsString($other, $der);
        }

        $this->actingAs($this->admin())->post(route('admin.invoices.store'), [
            'invoice_type' => 'simplified',
            'document_type' => 'invoice',
            'issue_date' => now()->toDateString(),
            'payment_means' => '10',
            'items' => [['name' => 'Shawarma', 'quantity' => 1, 'unit_price' => 20]],
            'submit_now' => true,
        ])->assertRedirect();

        $invoice = Invoice::firstOrFail();
        $this->assertSame($env, $invoice->environment);
        $this->assertSame('REPORTED', $invoice->zatca_status);

        // Every call (compliance, checks, production CSID, reporting) went to this environment only.
        Http::assertSentCount(6); // compliance CSID + 3 checks + production CSID + reporting
        Http::assertSent(fn ($request) => str_starts_with($request->url(), $baseUrl.'invoices/reporting/single'));
        Http::assertNotSent(fn ($request) => ! str_starts_with($request->url(), $baseUrl));

        $this->actingAs($this->admin())->get(route('admin.zatca.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('savedEnvironment', $env)->where('status.production', true));
    }

    public function test_switching_environment_resets_onboarding_and_keeps_test_invoices_out_of_production(): void
    {
        $this->onboard('sandbox');
        $admin = $this->admin();

        // A till sale is e-invoiced and reported in the sandbox by itself.
        $fries = Product::where('name_en', 'Fries Plate Large')->firstOrFail();
        $orderId = $this->actingAs($this->cashier())->postJson(route('orders.store'), [
            'order_type' => 'takeaway',
            'items' => [['product_id' => $fries->id, 'quantity' => 1]],
            'payment_method' => 'cash',
            'paid_amount' => 22,
        ])->json('id');
        $sandboxSale = Order::find($orderId)->einvoice;
        $this->assertSame('sandbox', $sandboxSale->environment);
        $this->assertSame('REPORTED', $sandboxSale->zatca_status);

        // And one issued by hand, signed but not submitted.
        $invoiceData = [
            'invoice_type' => 'simplified',
            'document_type' => 'invoice',
            'issue_date' => now()->toDateString(),
            'payment_means' => '10',
            'items' => [['name' => 'Fries', 'quantity' => 1, 'unit_price' => 19.13]],
        ];
        $this->actingAs($admin)->post(route('admin.invoices.store'), $invoiceData)->assertRedirect();
        $sandboxInvoice = Invoice::where('zatca_status', 'pending')->firstOrFail();
        $this->assertSame('sandbox', $sandboxInvoice->environment);

        // Go live: key, certificates and the ICV/PIH chain of the sandbox EGS are gone.
        $this->actingAs($admin)->put(route('admin.zatca.update'), ['environment' => 'production'] + $this->sellerData())
            ->assertSessionHas('status');
        $setting = ZatcaSetting::current();
        $this->assertSame('production', $setting->environment);
        $this->assertFalse($setting->hasCsr());
        $this->assertFalse($setting->hasComplianceCsid());
        $this->assertFalse($setting->hasProductionCsid());
        $this->assertSame(0, (int) $setting->icv);
        $this->assertSame(ZatcaSetting::INITIAL_PIH, $setting->previousInvoiceHash());

        // The sandbox invoice is out of the production list and can't be issued against.
        $this->actingAs($admin)->get(route('admin.invoices.index'))
            ->assertInertia(fn (Assert $page) => $page->where('environment', 'production')->where('stats.all', 0));
        $this->actingAs($admin)->get(route('admin.invoices.index', ['environment' => 'sandbox']))
            ->assertInertia(fn (Assert $page) => $page->where('stats.all', 2)->where('canSubmit', false));
        $this->actingAs($admin)->post(route('admin.invoices.store'), $invoiceData)->assertSessionHas('error'); // not onboarded yet

        // Onboard production; the sandbox invoice is never sent there, even by "submit pending".
        $this->onboard('production');
        $this->actingAs($admin)->post(route('admin.invoices.submit', $sandboxInvoice))->assertSessionHas('error');
        $this->actingAs($admin)->get(route('admin.invoices.show', $sandboxInvoice))
            ->assertInertia(fn (Assert $page) => $page->where('canSubmit', false)->where('currentEnvironment', 'production'));
        $this->actingAs($admin)->post(route('admin.invoices.submit-pending'))->assertSessionHas('status');
        $this->assertSame('pending', $sandboxInvoice->fresh()->zatca_status);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/core/invoices/reporting'));

        // The same order gets its real production invoice, starting a fresh chain at ICV 1.
        $this->actingAs($admin)->get(route('orders.show', $orderId))->assertInertia(fn (Assert $page) => $page->where('einvoice', null));
        $this->actingAs($admin)->post(route('admin.invoices.store'), $invoiceData + ['order_id' => $orderId, 'submit_now' => true])->assertRedirect();
        $live = Invoice::where('environment', 'production')->firstOrFail();
        $this->assertSame(1, (int) $live->icv);
        $this->assertSame(ZatcaSetting::INITIAL_PIH, $live->previous_invoice_hash);
        $this->assertSame('REPORTED', $live->zatca_status);
        $this->assertSame($live->qr_code, ZatcaQr::payload(Order::find($orderId)));
    }
}
