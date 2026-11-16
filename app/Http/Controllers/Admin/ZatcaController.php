<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\ZatcaSetting;
use App\Services\Zatca\Certificate;
use App\Services\Zatca\ZatcaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

/**
 * ZATCA Phase 2 onboarding: seller & EGS details → CSR → Compliance CSID → compliance checks → Production CSID.
 */
class ZatcaController extends Controller
{
    private const FIELDS = [
        'environment', 'seller_name', 'vat_number', 'crn', 'street', 'building_number', 'plot_identification',
        'district', 'city', 'postal_code', 'egs_name', 'egs_serial', 'branch_name', 'industry', 'invoice_types',
    ];

    public function __construct(private ZatcaService $zatca) {}

    public function edit(): Response
    {
        $setting = ZatcaSetting::current() ?? new ZatcaSetting([
            'environment' => config('zatca.env'),
            // Prefill from the store settings so the admin types as little as possible.
            'seller_name' => Setting::get('store_name_ar') ?: Setting::get('store_name_en'),
            'vat_number' => Setting::get('vat_number'),
            'crn' => Setting::get('cr_number'),
            'egs_name' => 'EGS1-'.Str::upper(Str::random(6)),
            'egs_model' => '1',
            'egs_serial' => '1-'.Str::slug(config('app.name')).'|2-1.0|3-'.Str::uuid(),
            'industry' => 'Restaurant',
            'invoice_types' => '1100',
        ]);

        $certificate = null;
        try {
            $certificate = $setting->signingCertificate() ? new Certificate($setting->signingCertificate()) : null;
        } catch (Throwable) {
            // An unreadable certificate just isn't shown; onboarding again replaces it.
        }

        $checks = $setting->compliance_checks ?? [];
        $checksPassed = filled($checks) && collect($checks)->every(fn ($r) => empty($r['errors']));

        return Inertia::render('Admin/Zatca', [
            'setting' => collect(self::FIELDS)->mapWithKeys(fn ($f) => [$f => (string) ($setting->{$f} ?? '')]),
            'status' => [
                'saved' => $setting->exists,
                'csr' => $setting->hasCsr(),
                'compliance' => $setting->hasComplianceCsid(),
                'checks' => $checksPassed,
                'production' => $setting->hasProductionCsid(),
            ],
            'csr' => $setting->csr,
            'complianceRequestId' => $setting->compliance_request_id,
            'complianceChecks' => collect($checks)->map(fn ($r, $label) => [
                'label' => $label,
                'status' => $r['status'] ?? '',
                'errors' => $r['errors'] ?? [],
                'warnings' => $r['warnings'] ?? [],
            ])->values(),
            'certificate' => $certificate ? [
                'type' => $setting->hasProductionCsid() ? 'Production' : 'Compliance',
                'issuer' => $certificate->issuerName(),
                'valid_to' => $certificate->validTo(),
                'icv' => (int) $setting->icv,
            ] : null,
            // The environment onboarding is currently for (null until first saved) — the page warns before a switch.
            'savedEnvironment' => $setting->exists ? $setting->environment : null,
            'environments' => collect(config('zatca.environments'))->map(fn ($e, $key) => ['value' => $key, 'label' => $e['label'], 'url' => $e['url']])->values(),
            'sandboxOtp' => config('zatca.sandbox_otp'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'environment' => ['required', Rule::in(array_keys(config('zatca.environments')))],
            'seller_name' => ['required', 'string', 'max:255'],
            'vat_number' => ['required', 'regex:/^3\d{13}3$/'],
            'crn' => ['nullable', 'digits:10'],
            'street' => ['required', 'string', 'max:255'],
            'building_number' => ['required', 'digits:4'],
            'plot_identification' => ['nullable', 'digits:4'],
            'district' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'digits:5'],
            'egs_name' => ['required', 'string', 'max:255'],
            'egs_serial' => ['required', 'regex:/^1-.+\|2-.+\|3-.+$/'],
            'branch_name' => ['required', 'string', 'max:255'],
            'industry' => ['required', 'string', 'max:255'],
            'invoice_types' => ['required', Rule::in(['1100', '1000', '0100'])],
        ], [
            'vat_number.regex' => __('VAT number must be 15 digits, starting and ending with 3.'),
            'egs_serial.regex' => __('Serial number format: 1-SolutionName|2-Model|3-SerialNumber'),
        ]);

        $setting = ZatcaSetting::current() ?? new ZatcaSetting;
        $setting->fill($data);

        // New environment = new EGS onboarding: drop the old key, certificates and invoice chain so
        // nothing is ever signed with (or sent to) the wrong environment.
        $environmentChanged = $setting->exists && $setting->isDirty('environment');
        if ($environmentChanged) {
            $setting->resetOnboarding();
        }

        $csrFields = ['seller_name', 'vat_number', 'street', 'city', 'egs_name', 'egs_serial', 'branch_name', 'industry', 'invoice_types'];
        $needsNewCsr = $setting->exists && $setting->hasCsr() && $setting->isDirty($csrFields);

        $setting->save();

        return back()->with('status', match (true) {
            $environmentChanged => __('Environment changed to :env. The old certificates were removed — generate a new CSR and onboard again.', [
                'env' => __(config("zatca.environments.{$setting->environment}.label")),
            ]),
            $needsNewCsr => __('Settings saved. CSR related fields changed, generate a new CSR and onboard again.'),
            default => __('Saved successfully.'),
        });
    }

    public function generateCsr(): RedirectResponse
    {
        return $this->step(function (ZatcaSetting $setting) {
            $this->zatca->generateCsr($setting);

            return __('Private key and CSR generated. Now request the Compliance CSID with your OTP.');
        });
    }

    public function complianceCsid(Request $request): RedirectResponse
    {
        $otp = $request->validate(['otp' => ['required', 'digits:6']])['otp'];

        return $this->step(function (ZatcaSetting $setting) use ($otp) {
            if (! $setting->hasCsr()) {
                throw new RuntimeException(__('Generate the CSR first.'));
            }
            $this->zatca->requestComplianceCsid($setting, $otp);

            return __('Compliance CSID received (request ID :id). Run the compliance checks next.', ['id' => $setting->compliance_request_id]);
        });
    }

    public function complianceChecks(): RedirectResponse
    {
        return $this->step(function (ZatcaSetting $setting) {
            if (! $setting->hasComplianceCsid()) {
                throw new RuntimeException(__('Request the Compliance CSID first.'));
            }
            $results = $this->zatca->runComplianceChecks($setting);
            $failed = collect($results)->filter(fn ($result) => ! empty($result['errors']))->count();

            if ($failed) {
                throw new RuntimeException(__(':count compliance check(s) failed. See the results below.', ['count' => $failed]));
            }

            return __('All compliance checks passed. Request the Production CSID next.');
        });
    }

    public function productionCsid(): RedirectResponse
    {
        return $this->step(function (ZatcaSetting $setting) {
            if (! $setting->hasComplianceCsid()) {
                throw new RuntimeException(__('Request the Compliance CSID first.'));
            }
            $this->zatca->requestProductionCsid($setting);

            return __('Production CSID received. Your EGS unit is onboarded and ready to report/clear invoices.');
        });
    }

    private function step(callable $callback): RedirectResponse
    {
        $setting = ZatcaSetting::current();

        if (! $setting) {
            return back()->with('error', __('Save the seller and EGS settings first.'));
        }

        try {
            return back()->with('status', $callback($setting));
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', $e->getMessage());
        }
    }
}
