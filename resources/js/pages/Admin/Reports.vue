<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import PosShell from '@/layouts/PosShell.vue';
import { money } from '@/lib/format';

defineOptions({ layout: PosShell });

const props = defineProps({
    filters: Object,
    summary: Object,
    byPayment: Array,
    byType: Array,
    byCashier: Array,
    topProducts: Array,
    voided: Number,
});

const TYPE_LABELS = { takeaway: 'Takeaway', dine_in: 'Dine-in', delivery: 'Delivery' };
const CARDS = { mada: 'mada', visa: 'Visa', mastercard: 'MasterCard' };
const form = reactive({ ...props.filters });

const show = () => router.get(route('admin.reports'), form, { preserveState: true, preserveScroll: true });
</script>

<template>
    <Head :title="__('Reports')" />
    <div class="page-head">
        <h1>{{ __('Sales report') }}</h1>
    </div>

    <form class="filters card" @submit.prevent="show">
        <div class="field">
            <label for="from">{{ __('From') }}</label>
            <input id="from" v-model="form.from" type="date" class="input">
        </div>
        <div class="field">
            <label for="to">{{ __('To') }}</label>
            <input id="to" v-model="form.to" type="date" class="input">
        </div>
        <button class="btn btn-primary">{{ __('Show') }}</button>
        <Link class="btn" :href="route('admin.reports')">{{ __('Today') }}</Link>
        <button type="button" class="btn" onclick="window.print()">{{ __('Print') }}</button>
    </form>

    <div class="stats">
        <div class="stat"><div class="label">{{ __('Orders') }}</div><div class="value num">{{ summary.orders }}</div></div>
        <div class="stat">
            <div class="label">{{ __('Total sales (incl. VAT)') }}</div>
            <div class="value"><span class="num">{{ money(summary.total) }}</span> <small class="muted">{{ $page.props.store.currency }}</small></div>
        </div>
        <div class="stat"><div class="label">{{ __('Net sales (excl. VAT)') }}</div><div class="value num">{{ money(summary.net) }}</div></div>
        <div class="stat"><div class="label">{{ __('VAT collected') }}</div><div class="value num">{{ money(summary.vat) }}</div></div>
        <div class="stat"><div class="label">{{ __('Discounts') }}</div><div class="value num">{{ money(summary.discount) }}</div></div>
        <div class="stat"><div class="label">{{ __('Average order') }}</div><div class="value num">{{ money(summary.orders ? summary.total / summary.orders : 0) }}</div></div>
    </div>
    <p v-if="voided" class="muted">{{ __(':count voided order(s) excluded.', { count: voided }) }}</p>

    <div class="cols-2">
        <div class="card table-wrap">
            <h2>{{ __('By payment method') }}</h2>
            <table class="table">
                <thead><tr><th>{{ __('Payment') }}</th><th class="text-end">{{ __('Orders') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
                <tbody>
                    <tr v-for="(r, i) in byPayment" :key="i">
                        <td>{{ r.payment_method === 'card' ? __('Card') + (r.card_type ? ` (${CARDS[r.card_type] || r.card_type})` : '') : __('Cash') }}</td>
                        <td class="text-end num">{{ r.orders }}</td>
                        <td class="text-end num">{{ money(r.total) }}</td>
                    </tr>
                    <tr v-if="!byPayment.length"><td colspan="3" class="muted text-center">—</td></tr>
                </tbody>
            </table>
        </div>

        <div class="card table-wrap">
            <h2>{{ __('By order type') }}</h2>
            <table class="table">
                <thead><tr><th>{{ __('Type') }}</th><th class="text-end">{{ __('Orders') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
                <tbody>
                    <tr v-for="r in byType" :key="r.order_type">
                        <td>{{ __(TYPE_LABELS[r.order_type] || r.order_type) }}</td>
                        <td class="text-end num">{{ r.orders }}</td>
                        <td class="text-end num">{{ money(r.total) }}</td>
                    </tr>
                    <tr v-if="!byType.length"><td colspan="3" class="muted text-center">—</td></tr>
                </tbody>
            </table>
        </div>

        <div class="card table-wrap">
            <h2>{{ __('By cashier') }}</h2>
            <table class="table">
                <thead><tr><th>{{ __('Cashier') }}</th><th class="text-end">{{ __('Orders') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
                <tbody>
                    <tr v-for="(r, i) in byCashier" :key="i">
                        <td>{{ r.name }}</td>
                        <td class="text-end num">{{ r.orders }}</td>
                        <td class="text-end num">{{ money(r.total) }}</td>
                    </tr>
                    <tr v-if="!byCashier.length"><td colspan="3" class="muted text-center">—</td></tr>
                </tbody>
            </table>
        </div>

        <div class="card table-wrap">
            <h2>{{ __('Top products') }}</h2>
            <table class="table">
                <thead><tr><th>{{ __('Item') }}</th><th class="text-end">{{ __('Qty') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
                <tbody>
                    <tr v-for="(r, i) in topProducts" :key="i">
                        <td>{{ r.name }}</td>
                        <td class="text-end num">{{ r.qty }}</td>
                        <td class="text-end num">{{ money(r.total) }}</td>
                    </tr>
                    <tr v-if="!topProducts.length"><td colspan="3" class="muted text-center">—</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
