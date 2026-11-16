<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import PosShell from '@/layouts/PosShell.vue';
import AdminTabs from '@/components/AdminTabs.vue';
import Field from '@/components/Field.vue';
import ZatcaEnvBadge from '@/components/ZatcaEnvBadge.vue';
import { SETTINGS_TABS } from '@/lib/settingsTabs';
import { useI18n } from '@/lib/i18n';
import { confirmDialog } from '@/lib/dialog';

defineOptions({ layout: PosShell });

const props = defineProps({
    setting: { type: Object, required: true },
    status: { type: Object, required: true },
    csr: { type: String, default: null },
    complianceRequestId: { type: String, default: null },
    complianceChecks: { type: Array, default: () => [] },
    certificate: { type: Object, default: null },
    environments: { type: Array, required: true },
    savedEnvironment: { type: String, default: null },
    sandboxOtp: { type: String, default: '' },
});

const { t } = useI18n();

const form = useForm({ ...props.setting });
const otpForm = useForm({ otp: props.savedEnvironment === 'sandbox' ? props.sandboxOtp : '' });
// After switching environment the sandbox OTP must not stay in the box (and vice versa).
watch(() => props.savedEnvironment, (env) => { otpForm.otp = env === 'sandbox' ? props.sandboxOtp : ''; });
const busy = computed(() => form.processing || otpForm.processing);

// What each environment means and where its OTP comes from.
const ENV_HELP = {
    sandbox: 'Developer portal for testing. No Fatoora account needed; the OTP is always the same. Use the sandbox test VAT number. Invoices are not legally valid.',
    simulation: 'Fatoora simulation portal for a final test with your real VAT number. Get the OTP from fatoora.zatca.gov.sa → Simulation → Onboard new solution unit (valid for 1 hour). Invoices are not legally valid.',
    production: 'Live ZATCA. Every reported invoice is a legal tax invoice. Get the OTP from fatoora.zatca.gov.sa → Onboard new solution unit (valid for 1 hour).',
};
const envUrl = computed(() => props.environments.find((e) => e.value === form.environment)?.url);

const STEPS = [
    ['saved', 'Seller & EGS details'],
    ['csr', 'Private key & CSR'],
    ['compliance', 'Compliance CSID'],
    ['checks', 'Compliance checks'],
    ['production', 'Production CSID'],
];
const current = computed(() => STEPS.findIndex(([key]) => !props.status[key]));

const INVOICE_TYPES = [
    ['1100', 'Standard + Simplified'],
    ['1000', 'Standard (B2B) only'],
    ['0100', 'Simplified (B2C) only'],
];

const SANDBOX_VAT = '399999999900003';

async function save() {
    // Switching environment wipes the key, certificates and invoice counter of the old one.
    if (props.savedEnvironment && form.environment !== props.savedEnvironment) {
        const label = (v) => t(props.environments.find((e) => e.value === v)?.label || v);
        const ok = await confirmDialog({
            title: t('Change ZATCA environment'),
            message: form.environment === 'production'
                ? t('Switch to Production? From now on reported invoices are real tax invoices. The current certificates are removed and you must onboard again with an OTP from the Fatoora portal.')
                : t('Switch the environment? The current certificates and invoice counter are removed and you must onboard again.'),
            detail: `${label(props.savedEnvironment)} → ${label(form.environment)}`,
            confirmText: t('Change environment'),
            variant: 'danger',
            icon: 'warning',
        });
        if (!ok) return;
    }
    form.put(route('admin.zatca.update'), { preserveScroll: true });
}

let posting = false;
function post(name, data = {}) {
    if (posting) return;
    posting = true;
    router.post(route(`admin.zatca.${name}`), data, {
        preserveScroll: true,
        onFinish: () => { posting = false; },
    });
}

async function generateCsr() {
    if (props.status.csr) {
        const ok = await confirmDialog({
            title: t('Regenerate CSR'),
            message: t('Generating a new CSR removes the current certificates. Continue?'),
            confirmText: t('Regenerate CSR'),
            variant: 'danger',
            icon: 'warning',
        });
        if (!ok) return;
    }
    post('csr');
}

const requestCompliance = () => otpForm.post(route('admin.zatca.compliance-csid'), { preserveScroll: true });
</script>

<template>
    <Head :title="__('ZATCA')" />
    <AdminTabs :tabs="SETTINGS_TABS" />
    <div class="page-head">
        <h1>{{ __('ZATCA Phase 2 Onboarding') }}</h1>
        <div style="display:flex;gap:.4rem">
            <ZatcaEnvBadge v-if="savedEnvironment" :environment="savedEnvironment" />
            <span v-if="status.production" class="badge badge-success">{{ __('Onboarded') }}</span>
        </div>
    </div>

    <div class="z-steps">
        <div
            v-for="([key, label], i) in STEPS"
            :key="key"
            class="z-step"
            :class="{ done: status[key], current: i === current }"
        >
            <span class="z-step-dot">{{ status[key] ? '✓' : i + 1 }}</span>
            <span>{{ __(label) }}</span>
        </div>
    </div>

    <div class="z-layout">
        <!-- Step 1: seller & EGS details -->
        <form class="card" @submit.prevent="save">
            <h2><span class="z-chip" :class="{ done: status.saved }">1</span>{{ __('Seller & EGS unit details') }}</h2>
            <div class="grid-2">
                <Field id="environment" :label="__('Environment')" :error="form.errors.environment">
                    <select id="environment" v-model="form.environment" class="input">
                        <option v-for="env in environments" :key="env.value" :value="env.value">{{ __(env.label) }}</option>
                    </select>
                </Field>
                <Field id="invoice_types" :label="__('Invoice types')" :error="form.errors.invoice_types">
                    <select id="invoice_types" v-model="form.invoice_types" class="input">
                        <option v-for="[value, label] in INVOICE_TYPES" :key="value" :value="value">{{ __(label) }} ({{ value }})</option>
                    </select>
                </Field>
            </div>

            <p class="z-hint" :class="{ 'alert alert-danger': form.environment === 'production' }" style="font-weight:600">
                {{ __(ENV_HELP[form.environment]) }}
                <span class="mono" style="display:block;font-weight:400;margin-top:.2rem">{{ envUrl }}</span>
            </p>

            <h3>{{ __('Seller') }}</h3>
            <Field id="seller_name" :label="__('Seller name (registration name)')" :error="form.errors.seller_name">
                <input id="seller_name" v-model="form.seller_name" class="input" required>
            </Field>
            <div class="grid-2">
                <Field id="vat_number" :label="__('VAT number')" :error="form.errors.vat_number">
                    <input id="vat_number" v-model="form.vat_number" class="input num" inputmode="numeric" maxlength="15" placeholder="3XXXXXXXXXXXXX3" dir="ltr" required>
                    <div v-if="form.environment === 'sandbox'" class="muted" style="font-size:.82rem;margin-top:.25rem">
                        {{ __('Sandbox test VAT:') }}
                        <a href="#" class="num" @click.prevent="form.vat_number = SANDBOX_VAT">{{ SANDBOX_VAT }}</a>
                    </div>
                </Field>
                <Field id="crn" :label="__('Commercial registration (CR)')" :error="form.errors.crn">
                    <input id="crn" v-model="form.crn" class="input num" inputmode="numeric" maxlength="10" dir="ltr">
                </Field>
            </div>
            <Field id="street" :label="__('Street')" :error="form.errors.street">
                <input id="street" v-model="form.street" class="input" required>
            </Field>
            <div class="grid-3">
                <Field id="building_number" :label="__('Building no.')" :error="form.errors.building_number">
                    <input id="building_number" v-model="form.building_number" class="input num" inputmode="numeric" maxlength="4" dir="ltr" required>
                </Field>
                <Field id="plot_identification" :label="__('Additional no.')" :error="form.errors.plot_identification">
                    <input id="plot_identification" v-model="form.plot_identification" class="input num" inputmode="numeric" maxlength="4" dir="ltr">
                </Field>
                <Field id="postal_code" :label="__('Postal code')" :error="form.errors.postal_code">
                    <input id="postal_code" v-model="form.postal_code" class="input num" inputmode="numeric" maxlength="5" dir="ltr" required>
                </Field>
            </div>
            <div class="grid-2">
                <Field id="district" :label="__('District')" :error="form.errors.district">
                    <input id="district" v-model="form.district" class="input" required>
                </Field>
                <Field id="city" :label="__('City')" :error="form.errors.city">
                    <input id="city" v-model="form.city" class="input" required>
                </Field>
            </div>

            <h3>{{ __('EGS unit (CSR)') }}</h3>
            <div class="grid-2">
                <Field id="egs_name" :label="__('Common name (EGS name)')" :error="form.errors.egs_name">
                    <input id="egs_name" v-model="form.egs_name" class="input" dir="ltr" required>
                </Field>
                <Field id="branch_name" :label="__('Branch / organization unit')" :error="form.errors.branch_name">
                    <input id="branch_name" v-model="form.branch_name" class="input" :placeholder="__('Riyadh Branch')" required>
                </Field>
            </div>
            <Field id="egs_serial" :label="__('EGS serial number')" :error="form.errors.egs_serial">
                <input id="egs_serial" v-model="form.egs_serial" class="input mono" dir="ltr" required>
                <div class="muted" style="font-size:.82rem;margin-top:.25rem">{{ __('Format: 1-SolutionName|2-Model/Version|3-SerialNumber') }}</div>
            </Field>
            <Field id="industry" :label="__('Industry / business category')" :error="form.errors.industry">
                <input id="industry" v-model="form.industry" class="input" required>
            </Field>

            <button class="btn btn-primary btn-lg" :disabled="busy">{{ __('Save settings') }}</button>
        </form>

        <div>
            <!-- Step 2: key + CSR -->
            <div class="card" :class="{ locked: !status.saved }">
                <h2><span class="z-chip" :class="{ done: status.csr }">2</span>{{ __('Private key & CSR') }}</h2>
                <p class="z-hint">{{ __('Generates a secp256k1 key pair and a CSR with the ZATCA certificate template and EGS details.') }}</p>
                <textarea v-if="csr" class="input mono" rows="4" readonly :value="csr" style="margin-bottom:.6rem" />
                <button type="button" class="btn" :disabled="!status.saved" @click="generateCsr">
                    {{ status.csr ? __('Regenerate CSR') : __('Generate CSR') }}
                </button>
            </div>

            <!-- Step 3: compliance CSID -->
            <div class="card" :class="{ locked: !status.csr }">
                <h2><span class="z-chip" :class="{ done: status.compliance }">3</span>{{ __('Compliance CSID') }}</h2>
                <p class="z-hint">
                    <template v-if="savedEnvironment === 'sandbox'">
                        {{ __('In the sandbox the OTP is') }} <span class="num">{{ sandboxOtp }}</span>.
                    </template>
                    <template v-else>{{ __('Get the OTP from the Fatoora portal (Onboard new solution unit).') }}</template>
                </p>
                <form style="display:flex;gap:.5rem;align-items:flex-start" @submit.prevent="requestCompliance">
                    <Field id="otp" :label="__('OTP')" :error="otpForm.errors.otp" style="flex:1;margin:0">
                        <input id="otp" v-model="otpForm.otp" class="input num" inputmode="numeric" maxlength="6" dir="ltr" required>
                    </Field>
                    <button class="btn" style="margin-top:1.55rem" :disabled="!status.csr || busy">{{ __('Get CSID') }}</button>
                </form>
                <div v-if="complianceRequestId" class="muted" style="font-size:.85rem;margin-top:.5rem">
                    {{ __('Request ID') }}: <span class="mono">{{ complianceRequestId }}</span>
                </div>
            </div>

            <!-- Step 4: compliance checks -->
            <div class="card" :class="{ locked: !status.compliance }">
                <h2><span class="z-chip" :class="{ done: status.checks }">4</span>{{ __('Compliance checks') }}</h2>
                <p class="z-hint">{{ __('Signs and submits a sample of each required document (invoice, credit note, debit note) to the compliance API.') }}</p>
                <button type="button" class="btn" :disabled="!status.compliance" @click="post('compliance-checks')">{{ __('Run compliance checks') }}</button>
                <ul v-if="complianceChecks.length" class="z-msgs">
                    <li v-for="check in complianceChecks" :key="check.label">
                        <span :style="{ color: check.errors.length ? 'var(--danger)' : 'var(--success)' }">{{ check.errors.length ? '✗' : '✓' }}</span>
                        {{ __(check.label) }} — <strong>{{ check.status }}</strong>
                        <div v-for="m in check.errors" :key="m" class="err mono">{{ m }}</div>
                        <div v-for="m in check.warnings" :key="m" class="warn mono">{{ m }}</div>
                    </li>
                </ul>
            </div>

            <!-- Step 5: production CSID -->
            <div class="card" :class="{ locked: !status.checks }">
                <h2><span class="z-chip" :class="{ done: status.production }">5</span>{{ __('Production CSID') }}</h2>
                <button type="button" class="btn btn-primary" :disabled="!status.checks" @click="post('production-csid')">{{ __('Get Production CSID') }}</button>
                <dl v-if="certificate" class="z-dl" style="margin-top:1rem">
                    <dt>{{ __('Active certificate') }}</dt><dd>{{ __(certificate.type) }}</dd>
                    <dt>{{ __('Issuer') }}</dt><dd class="mono">{{ certificate.issuer }}</dd>
                    <dt>{{ __('Valid until') }}</dt><dd class="num">{{ certificate.valid_to }}</dd>
                    <dt>{{ __('Invoice counter (ICV)') }}</dt><dd class="num">{{ certificate.icv }}</dd>
                </dl>
            </div>
        </div>
    </div>
</template>
