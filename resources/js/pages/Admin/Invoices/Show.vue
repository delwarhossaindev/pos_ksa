<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PosShell from '@/layouts/PosShell.vue';
import ZatcaStatus from '@/components/ZatcaStatus.vue';
import ZatcaEnvBadge from '@/components/ZatcaEnvBadge.vue';
import { money } from '@/lib/format';

defineOptions({ layout: PosShell });

const props = defineProps({
    invoice: { type: Object, required: true },
    seller: { type: Object, default: null },
    currentEnvironment: { type: String, required: true },
    canSubmit: { type: Boolean, default: false },
});

const ARABIC_TITLES = { credit: 'إشعار دائن', debit: 'إشعار مدين' };
const arabicTitle = computed(() => ARABIC_TITLES[props.invoice.document_type]
    || (props.invoice.invoice_type === 'simplified' ? 'فاتورة ضريبية مبسطة' : 'فاتورة ضريبية'));

const submitting = ref(false);
function submit() {
    router.post(route('admin.invoices.submit', props.invoice.id), {}, {
        preserveScroll: true,
        onStart: () => { submitting.value = true; },
        onFinish: () => { submitting.value = false; },
    });
}

const buyerAddress = computed(() => {
    const b = props.invoice.buyer;
    return [b.buyer_building_number, b.buyer_street, b.buyer_district, b.buyer_city, b.buyer_postal_code].filter(Boolean).join(', ');
});
const qty = (v) => +Number(v).toFixed(6);
</script>

<template>
    <Head :title="invoice.invoice_number" />
    <div class="page-head">
        <h1>
            <span class="num">{{ invoice.invoice_number }}</span>
            <ZatcaStatus :status="invoice.zatca_status" style="margin-inline-start:.5rem;vertical-align:middle" />
            <ZatcaEnvBadge :environment="invoice.environment" style="margin-inline-start:.3rem;vertical-align:middle" />
        </h1>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <button v-if="!invoice.accepted" type="button" class="btn btn-primary" :disabled="!canSubmit || submitting" @click="submit">
                {{ invoice.invoice_type === 'simplified' ? __('Report to ZATCA') : __('Clear with ZATCA') }}
            </button>
            <a :href="route('admin.invoices.xml', invoice.id)" class="btn">{{ __('Signed XML') }}</a>
            <a v-if="invoice.has_cleared_xml" :href="route('admin.invoices.xml', [invoice.id, 'cleared'])" class="btn">{{ __('Cleared XML') }}</a>
            <template v-if="!invoice.is_note && invoice.environment === currentEnvironment">
                <Link :href="route('admin.invoices.create', { original: invoice.id, document_type: 'credit' })" class="btn">{{ __('Credit note') }}</Link>
                <Link :href="route('admin.invoices.create', { original: invoice.id, document_type: 'debit' })" class="btn">{{ __('Debit note') }}</Link>
            </template>
            <Link :href="route('admin.invoices.index')" class="btn">{{ __('Back') }}</Link>
        </div>
    </div>

    <div v-if="invoice.environment !== currentEnvironment" class="alert alert-danger" style="background:#fdf3dc;color:#8a5d00;border-color:#f0d9a2">
        {{ __('This invoice was signed in another ZATCA environment, so it cannot be sent from the current one.') }}
    </div>
    <div v-else-if="invoice.environment !== 'production'" class="alert alert-danger" style="background:#fdf3dc;color:#8a5d00;border-color:#f0d9a2">
        {{ __('Test environment: this invoice is not a legal tax invoice.') }}
    </div>

    <div v-if="invoice.errors.length || invoice.warnings.length" class="card" style="margin-bottom:1rem">
        <h2>{{ __('ZATCA validation messages') }}</h2>
        <ul class="z-msgs">
            <li v-for="(m, i) in invoice.errors" :key="'e' + i" class="err"><strong>{{ m.code }}</strong>: {{ m.message }}</li>
            <li v-for="(m, i) in invoice.warnings" :key="'w' + i" class="warn"><strong>{{ m.code }}</strong>: {{ m.message }}</li>
        </ul>
    </div>

    <div class="card z-invoice-head">
        <div>
            <div dir="rtl" style="font-size:1.5rem;font-weight:800;color:var(--primary)">{{ arabicTitle }}</div>
            <div class="muted" style="font-weight:700;margin-bottom:.75rem">{{ __(invoice.label) }}</div>
            <dl class="z-dl">
                <dt>{{ __('Invoice number') }}</dt><dd class="num">{{ invoice.invoice_number }}</dd>
                <dt>{{ __('Issue date') }}</dt><dd class="num">{{ invoice.issue_date }} {{ invoice.issue_time }}</dd>
                <template v-if="invoice.is_note">
                    <dt>{{ __('Original invoice') }}</dt><dd class="num">{{ invoice.original_invoice_number }}</dd>
                    <dt>{{ __('Reason') }}</dt><dd>{{ invoice.note_reason }}</dd>
                </template>
                <dt>{{ __('Payment means') }}</dt><dd>{{ __(invoice.payment_means) }}</dd>
                <template v-if="invoice.order">
                    <dt>{{ __('POS order') }}</dt>
                    <dd><Link :href="route('orders.show', invoice.order.id)">#<span class="num">{{ invoice.order.order_no }}</span> · <span class="num">{{ invoice.order.invoice_no }}</span></Link></dd>
                </template>
                <dt>{{ __('UUID') }}</dt><dd class="mono">{{ invoice.uuid }}</dd>
            </dl>
        </div>
        <img v-if="invoice.qr_image" :src="invoice.qr_image" class="z-qr" alt="ZATCA QR">
    </div>

    <div class="cols-2" style="margin-top:1rem">
        <div class="z-party card">
            <h3>{{ __('Seller') }}</h3>
            <template v-if="seller">
                <div style="font-weight:700">{{ seller.seller_name }}</div>
                <div>{{ __('VAT number') }}: <span class="num">{{ seller.vat_number }}</span><template v-if="seller.crn"> · CR: <span class="num">{{ seller.crn }}</span></template></div>
                <div class="muted">{{ [seller.building_number, seller.street, seller.district, seller.city, seller.postal_code].filter(Boolean).join(', ') }}</div>
            </template>
        </div>
        <div class="z-party card">
            <h3>{{ __('Buyer') }}</h3>
            <template v-if="invoice.buyer.buyer_name">
                <div style="font-weight:700">{{ invoice.buyer.buyer_name }}</div>
                <div v-if="invoice.buyer.buyer_vat_number">{{ __('VAT number') }}: <span class="num">{{ invoice.buyer.buyer_vat_number }}</span></div>
                <div v-if="buyerAddress" class="muted">{{ buyerAddress }}</div>
            </template>
            <div v-else class="muted">{{ __('Cash customer') }}</div>
        </div>
    </div>

    <div class="card table-wrap" style="padding:0;margin-top:1rem">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('Description') }}</th>
                    <th class="text-end">{{ __('Qty') }}</th>
                    <th class="text-end">{{ __('Unit price') }}</th>
                    <th class="text-end">{{ __('Net') }}</th>
                    <th class="text-end">{{ __('VAT') }}</th>
                    <th class="text-end">{{ __('Total') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(item, i) in invoice.items" :key="item.id">
                    <td class="muted num">{{ i + 1 }}</td>
                    <td>{{ item.name }}</td>
                    <td class="text-end num">{{ qty(item.quantity) }}</td>
                    <td class="text-end num">{{ money(item.unit_price) }}</td>
                    <td class="text-end num">{{ money(item.line_extension_amount) }}</td>
                    <td class="text-end num">{{ money(item.vat_amount) }} <span class="muted">({{ item.vat_percent }}%)</span></td>
                    <td class="text-end num"><strong>{{ money(item.line_extension_amount + item.vat_amount) }}</strong></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="z-totals" style="margin-top:1rem">
        <div><span>{{ __('Total excl. VAT') }}</span><span class="num">{{ money(invoice.line_extension_amount) }}</span></div>
        <div><span>{{ __('VAT') }}</span><span class="num">{{ money(invoice.vat_amount) }}</span></div>
        <div class="grand"><span>{{ __('Total incl. VAT') }} ({{ invoice.currency }})</span><span class="num">{{ money(invoice.total_amount) }}</span></div>
    </div>

    <div class="card" style="margin-top:1rem">
        <h2>{{ __('ZATCA data') }}</h2>
        <dl class="z-dl">
            <dt>ICV</dt><dd class="num">{{ invoice.icv }}</dd>
            <dt>{{ __('Invoice hash') }}</dt><dd class="mono">{{ invoice.invoice_hash }}</dd>
            <dt>{{ __('Previous invoice hash') }}</dt><dd class="mono">{{ invoice.previous_invoice_hash }}</dd>
            <dt>{{ __('Submitted at') }}</dt><dd class="num">{{ invoice.submitted_at || '—' }}</dd>
            <dt>{{ __('QR (TLV base64)') }}</dt><dd class="mono">{{ invoice.qr_code }}</dd>
        </dl>
    </div>
</template>
