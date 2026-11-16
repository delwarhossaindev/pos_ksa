<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import PosShell from '@/layouts/PosShell.vue';
import Field from '@/components/Field.vue';
import ZatcaEnvBadge from '@/components/ZatcaEnvBadge.vue';
import { money } from '@/lib/format';

defineOptions({ layout: PosShell });

const props = defineProps({
    defaults: { type: Object, required: true },
    source: { type: Object, default: null },
    allowedTypes: { type: Array, required: true },
    paymentMeans: { type: Array, required: true },
    vatPercent: { type: Number, required: true },
    environment: { type: String, required: true },
    canIssue: { type: Boolean, default: false },
    canSubmit: { type: Boolean, default: false },
    fixedBuyerVat: { type: String, default: null }, // sandbox: ZATCA's test buyer VAT, locked
});

const form = useForm({
    ...props.defaults,
    invoice_type: props.allowedTypes.includes(props.defaults.invoice_type) ? props.defaults.invoice_type : props.allowedTypes[0],
    items: props.defaults.items.map((i) => ({ ...i })),
    submit_now: props.canSubmit,
});

const isNote = computed(() => form.document_type !== 'invoice');
const standard = computed(() => form.invoice_type === 'standard');
const title = computed(() => ({ credit: 'New credit note', debit: 'New debit note' }[form.document_type] || 'New invoice'));

const DOCUMENT_TYPES = [['invoice', 'Tax invoice (388)'], ['credit', 'Credit note (381)'], ['debit', 'Debit note (383)']];
const INVOICE_TYPES = { simplified: 'Simplified (B2C) — reported', standard: 'Standard (B2B) — cleared' };

// Same rounding as the server (App\Models\Invoice::calculateTotals).
const num = (v) => {
    const n = parseFloat(String(v ?? '').replace(/[٠-٩]/g, (d) => '٠١٢٣٤٥٦٧٨٩'.indexOf(d)));
    return Number.isFinite(n) ? n : 0;
};
const round2 = (v) => Math.round((v + Number.EPSILON) * 100) / 100;
const lineNet = (item) => round2(num(item.quantity) * num(item.unit_price));
const totals = computed(() => {
    const net = round2(form.items.reduce((s, i) => s + lineNet(i), 0));
    const vat = round2(net * props.vatPercent / 100);
    return { net, vat, total: round2(net + vat) };
});

const addItem = () => form.items.push({ name: '', quantity: 1, unit_price: '' });
const removeItem = (i) => form.items.length > 1 && form.items.splice(i, 1);
const itemError = (i, field) => form.errors[`items.${i}.${field}`];

const submit = () => form.post(route('admin.invoices.store'), { preserveScroll: true });
</script>

<template>
    <Head :title="__(title)" />
    <div class="page-head">
        <h1>{{ __(title) }} <ZatcaEnvBadge :environment="environment" style="vertical-align:middle" /></h1>
        <Link :href="route('admin.invoices.index')" class="btn">{{ __('Back') }}</Link>
    </div>

    <div v-if="!canIssue" class="alert alert-danger">
        {{ __('Complete ZATCA onboarding before issuing invoices.') }}
        <Link :href="route('admin.zatca.edit')">{{ __('ZATCA onboarding') }} ›</Link>
    </div>
    <div v-if="source?.type === 'order'" class="alert alert-success">
        {{ __('From POS order :no. Till prices include VAT; the lines below are the net prices.', { no: source.label }) }}
        <span v-if="Math.abs(totals.total - source.total) >= 0.005">
            {{ __('Receipt total') }}: <span class="num">{{ money(source.total) }}</span>
        </span>
    </div>

    <form @submit.prevent="submit">
        <div class="cols-2">
            <div class="card">
                <h2>{{ __('Document') }}</h2>
                <div class="grid-2">
                    <Field id="invoice_type" :label="__('Invoice type')" :error="form.errors.invoice_type">
                        <select id="invoice_type" v-model="form.invoice_type" class="input">
                            <option v-for="type in allowedTypes" :key="type" :value="type">{{ __(INVOICE_TYPES[type]) }}</option>
                        </select>
                    </Field>
                    <Field id="document_type" :label="__('Document type')" :error="form.errors.document_type">
                        <select id="document_type" v-model="form.document_type" class="input">
                            <option v-for="[value, label] in DOCUMENT_TYPES" :key="value" :value="value">{{ __(label) }}</option>
                        </select>
                    </Field>
                    <Field id="issue_date" :label="__('Issue date')" :error="form.errors.issue_date">
                        <input id="issue_date" v-model="form.issue_date" type="date" class="input" required>
                    </Field>
                    <Field id="payment_means" :label="__('Payment means')" :error="form.errors.payment_means">
                        <select id="payment_means" v-model="form.payment_means" class="input">
                            <option v-for="pm in paymentMeans" :key="pm.value" :value="pm.value">{{ __(pm.label) }}</option>
                        </select>
                    </Field>
                </div>
                <template v-if="isNote">
                    <Field id="original_invoice_number" :label="__('Original invoice number')" :error="form.errors.original_invoice_number">
                        <input id="original_invoice_number" v-model="form.original_invoice_number" class="input num" dir="ltr" required>
                    </Field>
                    <Field id="note_reason" :label="__('Reason')" :error="form.errors.note_reason">
                        <input id="note_reason" v-model="form.note_reason" class="input" :placeholder="__('e.g. Returned items')" required>
                    </Field>
                </template>
            </div>

            <div class="card">
                <h2>{{ __('Buyer') }} <span v-if="!standard" class="muted" style="font-size:.85rem;font-weight:600">({{ __('optional for simplified') }})</span></h2>
                <div class="grid-2">
                    <Field id="buyer_name" :label="__('Name')" :error="form.errors.buyer_name">
                        <input id="buyer_name" v-model="form.buyer_name" class="input" :required="standard">
                    </Field>
                    <Field id="buyer_vat_number" :label="__('VAT number')" :error="form.errors.buyer_vat_number">
                        <input id="buyer_vat_number" v-model="form.buyer_vat_number" class="input num" inputmode="numeric" maxlength="15" placeholder="3XXXXXXXXXXXXX3" dir="ltr" :readonly="!!fixedBuyerVat">
                        <div v-if="fixedBuyerVat" class="muted" style="font-size:.82rem;margin-top:.25rem">🔒 {{ __('Sandbox test VAT, set automatically.') }}</div>
                    </Field>
                </div>
                <Field id="buyer_street" :label="__('Street')" :error="form.errors.buyer_street">
                    <input id="buyer_street" v-model="form.buyer_street" class="input" :required="standard">
                </Field>
                <div class="grid-2">
                    <Field id="buyer_building_number" :label="__('Building no.')" :error="form.errors.buyer_building_number">
                        <input id="buyer_building_number" v-model="form.buyer_building_number" class="input num" inputmode="numeric" maxlength="4" dir="ltr" :required="standard">
                    </Field>
                    <Field id="buyer_postal_code" :label="__('Postal code')" :error="form.errors.buyer_postal_code">
                        <input id="buyer_postal_code" v-model="form.buyer_postal_code" class="input num" inputmode="numeric" maxlength="5" dir="ltr" :required="standard">
                    </Field>
                    <Field id="buyer_district" :label="__('District')" :error="form.errors.buyer_district">
                        <input id="buyer_district" v-model="form.buyer_district" class="input" :required="standard">
                    </Field>
                    <Field id="buyer_city" :label="__('City')" :error="form.errors.buyer_city">
                        <input id="buyer_city" v-model="form.buyer_city" class="input" :required="standard">
                    </Field>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top:1rem">
            <h2>{{ __('Items') }}</h2>
            <div v-if="form.errors.items" class="alert alert-danger">{{ form.errors.items }}</div>
            <div class="table-wrap">
                <table class="table z-items">
                    <thead>
                        <tr>
                            <th>{{ __('Description') }}</th>
                            <th style="width:110px">{{ __('Qty') }}</th>
                            <th style="width:150px">{{ __('Unit price (excl. VAT)') }}</th>
                            <th class="text-end">{{ __('Net') }}</th>
                            <th class="text-end">{{ __('VAT') }} ({{ vatPercent }}%)</th>
                            <th />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, i) in form.items" :key="i">
                            <td>
                                <input v-model="item.name" class="input" required>
                                <div v-if="itemError(i, 'name')" class="error" style="color:var(--danger);font-size:.85rem">{{ itemError(i, 'name') }}</div>
                            </td>
                            <td>
                                <input v-model="item.quantity" class="input num" inputmode="decimal" required>
                                <div v-if="itemError(i, 'quantity')" style="color:var(--danger);font-size:.85rem">{{ itemError(i, 'quantity') }}</div>
                            </td>
                            <td>
                                <input v-model="item.unit_price" class="input num" inputmode="decimal" required>
                                <div v-if="itemError(i, 'unit_price')" style="color:var(--danger);font-size:.85rem">{{ itemError(i, 'unit_price') }}</div>
                            </td>
                            <td class="text-end num">{{ money(lineNet(item)) }}</td>
                            <td class="text-end num">{{ money(round2(lineNet(item) * vatPercent / 100)) }}</td>
                            <td>
                                <button type="button" class="btn btn-sm btn-danger" :disabled="form.items.length === 1" :title="__('Remove')" @click="removeItem(i)">✕</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-sm" style="margin-top:.6rem" @click="addItem">+ {{ __('Add item') }}</button>

            <div class="z-totals" style="margin-top:1rem">
                <div><span>{{ __('Total excl. VAT') }}</span><span class="num">{{ money(totals.net) }}</span></div>
                <div><span>{{ __('VAT') }}</span><span class="num">{{ money(totals.vat) }}</span></div>
                <div class="grand"><span>{{ __('Total incl. VAT') }}</span><span class="num">{{ money(totals.total) }}</span></div>
            </div>
        </div>

        <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;margin-top:1rem">
            <button class="btn btn-primary btn-lg" :disabled="form.processing || !canIssue">{{ __('Sign invoice') }}</button>
            <label v-if="canSubmit" class="check"><input v-model="form.submit_now" type="checkbox"> {{ __('Submit to ZATCA right away') }}</label>
        </div>
    </form>
</template>
