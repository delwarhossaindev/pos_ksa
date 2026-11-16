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

    private function admin(): User
    {
        return User::where('role', 'admin')->first();
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

    public function test_onboarding_pages_are_admin_only(): void
    {
        $this->actingAs($this->cashier())->get(route('admin.zatca.edit'))->assertForbidden();
        $this->actingAs($this->cashier())->get(route('admin.invoices.index'))->assertForbidden();

        $this->actingAs($this->admin())->get(route('admin.zatca.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Zatca')
                ->where('status.saved', false)
                ->where('setting.vat_number', '302256240800003') // prefilled from the store settings
                ->has('environments', 3));

        $this->actingAs($this->admin())->get(route('admin.invoices.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Invoices/Index')->where('canIssue', false));
    }

    public function test_settings_validation(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.zatca.update'), ['vat_number' => '123'] + $this->sellerData())
            ->assertSessionHasErrors('vat_number');

        $this->actingAs($this->admin())
            ->post(route('admin.zatca.csr'))
            ->assertSessionHas('error');
    }

    public function test_full_onboarding_then_pos_order_e_invoice_is_signed_reported_and_printed(): void
    {
        $setting = $this->onboard();
        $this->assertTrue($setting->hasProductionCsid());
        $this->assertCount(3, $setting->compliance_checks); // simplified invoice, credit and debit note

        // A sale on the till (22.00 SAR incl. VAT)
        $fries = Product::where('name_en', 'Fries Plate Large')->firstOrFail();
        $orderId = $this->actingAs($this->cashier())->postJson(route('orders.store'), [
            'order_type' => 'takeaway',
            'items' => [['product_id' => $fries->id, 'quantity' => 2]],
            'payment_method' => 'card',
            'card_type' => 'mada',
        ])->assertCreated()->json('id');
        $order = Order::findOrFail($orderId);

        // The create form is prefilled with net prices from the order.
        $this->actingAs($this->admin())->get(route('admin.invoices.create', ['order' => $orderId]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Invoices/Create')
                ->where('defaults.order_id', $orderId)
                ->where('defaults.payment_means', '48')
                ->where('defaults.items.0.quantity', 2)
                ->where('defaults.items.0.unit_price', 19.13)
                ->where('allowedTypes', ['simplified']));

        $this->actingAs($this->admin())->post(route('admin.invoices.store'), [
            'invoice_type' => 'simplified',
            'document_type' => 'invoice',
            'issue_date' => $order->created_at->toDateString(),
            'payment_means' => '48',
            'order_id' => $orderId,
            'items' => [['name' => 'Fries', 'quantity' => 2, 'unit_price' => 19.13]],
            'submit_now' => true,
        ])->assertRedirect();

        $invoice = Invoice::firstOrFail();
        $this->assertSame($order->invoice_no, $invoice->invoice_number); // receipt and e-invoice share the number
        $this->assertSame('REPORTED', $invoice->zatca_status);
        $this->assertSame(1, (int) $invoice->icv);
        $this->assertSame(ZatcaSetting::INITIAL_PIH, $invoice->previous_invoice_hash);
        $this->assertStringContainsString('<ds:SignatureValue>', $invoice->signed_xml);
        $this->assertSame('44.00', number_format((float) $invoice->total_amount, 2));

        // Phase 2 QR (tags 1–9) now replaces the phase 1 QR on the printed receipt.
        $tags = QrCode::decode(ZatcaQr::payload($order->fresh()));
        $this->assertSame('399999999900003', $tags[2]);
        $this->assertSame('44.00', $tags[4]);
        $this->assertArrayHasKey(9, $tags);
        $this->actingAs($this->cashier())->get(route('orders.receipt', $orderId))->assertOk();

        // Hash chain continues on the next document.
        $this->assertSame($invoice->invoice_hash, ZatcaSetting::current()->previous_invoice_hash);

        // Order page links the e-invoice; a second invoice for the same order is refused.
        $this->actingAs($this->admin())->get(route('orders.show', $orderId))
            ->assertInertia(fn (Assert $page) => $page->where('einvoice.invoice_number', $order->invoice_no));
        $this->actingAs($this->admin())->get(route('admin.invoices.create', ['order' => $orderId]))
            ->assertRedirect(route('admin.invoices.show', $invoice));

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
        $this->assertSame(2, (int) $credit->icv);
        $this->assertSame('pending', $credit->zatca_status);

        // Pages render, XML downloads, pending submit reports the credit note.
        $this->actingAs($this->admin())->get(route('admin.invoices.show', $credit))
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Invoices/Show')->where('invoice.is_note', true));
        $this->actingAs($this->admin())->get(route('admin.invoices.xml', $invoice))
            ->assertOk()->assertHeader('Content-Type', 'application/xml');
        $this->actingAs($this->admin())->post(route('admin.invoices.submit-pending'))->assertSessionHas('status');
        $this->assertSame('REPORTED', $credit->fresh()->zatca_status);

        $this->actingAs($this->admin())->get(route('admin.invoices.index', ['status' => 'accepted']))
            ->assertInertia(fn (Assert $page) => $page->where('stats.accepted', 2)->has('invoices.data', 2));

        Http::assertSent(fn ($request) => $request->hasHeader('Accept-Version', 'V2'));
    }

    public static function environments(): array
    {
        return [
            'sandbox' => ['sandbox', 'TSTZATCA-Code-Signing', 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal/'],
            'simulation' => ['simulation', 'PREZATCA-Code-Signing', 'https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation/'],
            'production' => ['production', 'ZATCA-Code-Signing', 'https://gw-fatoora.zatca.gov.sa/e-invoicing/core/'],
        ];
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

        $fries = Product::where('name_en', 'Fries Plate Large')->firstOrFail();
        $orderId = $this->actingAs($this->cashier())->postJson(route('orders.store'), [
            'order_type' => 'takeaway',
            'items' => [['product_id' => $fries->id, 'quantity' => 1]],
            'payment_method' => 'cash',
            'paid_amount' => 22,
        ])->json('id');
        $invoiceData = [
            'invoice_type' => 'simplified',
            'document_type' => 'invoice',
            'issue_date' => now()->toDateString(),
            'payment_means' => '10',
            'order_id' => $orderId,
            'items' => [['name' => 'Fries', 'quantity' => 1, 'unit_price' => 19.13]],
        ];
        $this->actingAs($admin)->post(route('admin.invoices.store'), $invoiceData)->assertRedirect(); // signed, not submitted
        $sandboxInvoice = Invoice::firstOrFail();
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
            ->assertInertia(fn (Assert $page) => $page->where('stats.all', 1)->where('canSubmit', false));
        $this->actingAs($admin)->post(route('admin.invoices.store'), $invoiceData)->assertSessionHas('error'); // not onboarded yet

        // Onboard production; the sandbox invoice is never sent there, even by "submit pending".
        $this->onboard('production');
        $this->actingAs($admin)->post(route('admin.invoices.submit', $sandboxInvoice))->assertSessionHas('error');
        $this->actingAs($admin)->get(route('admin.invoices.show', $sandboxInvoice))
            ->assertInertia(fn (Assert $page) => $page->where('canSubmit', false)->where('currentEnvironment', 'production'));
        $this->actingAs($admin)->post(route('admin.invoices.submit-pending'))->assertSessionHas('status');
        $this->assertSame('pending', $sandboxInvoice->fresh()->zatca_status);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'reporting'));

        // The same order gets its real production invoice, starting a fresh chain at ICV 1.
        $this->actingAs($admin)->get(route('orders.show', $orderId))->assertInertia(fn (Assert $page) => $page->where('einvoice', null));
        $this->actingAs($admin)->post(route('admin.invoices.store'), $invoiceData + ['submit_now' => true])->assertRedirect();
        $live = Invoice::where('environment', 'production')->firstOrFail();
        $this->assertSame(1, (int) $live->icv);
        $this->assertSame(ZatcaSetting::INITIAL_PIH, $live->previous_invoice_hash);
        $this->assertSame('REPORTED', $live->zatca_status);
        $this->assertSame($live->qr_code, ZatcaQr::payload(Order::find($orderId)));
    }
}
