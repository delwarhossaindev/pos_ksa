<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import PosShell from '@/layouts/PosShell.vue';
import ZatcaStatus from '@/components/ZatcaStatus.vue';
import { useI18n } from '@/lib/i18n';
import { money } from '@/lib/format';
import { confirmDialog } from '@/lib/dialog';

defineOptions({ layout: PosShell });

const props = defineProps({
    order: { type: Object, required: true },
    einvoice: { type: Object, default: null },
});

const { t } = useI18n();
const TYPE_LABELS = { takeaway: 'Takeaway', dine_in: 'Dine-in', delivery: 'Delivery' };
const CARDS = { mada: 'mada', visa: 'Visa', mastercard: 'MasterCard' };

async function voidOrder() {
    const ok = await confirmDialog({
        title: t('Void order'),
        message: t('Void this order? It will be excluded from sales reports.'),
        detail: `${t('Order')} #${props.order.order_no} · ${money(props.order.total)}`,
        confirmText: t('Void order'),
        variant: 'danger',
        icon: 'warning',
    });
    if (ok) router.post(route('admin.orders.void', props.order.id), {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`${__('Order')} #${order.order_no}`" />
    <div class="page-head">
        <h1>
            {{ __('Order') }} <span class="num">#{{ order.order_no }}</span>
            <span v-if="order.status === 'void'" class="badge badge-danger">{{ __('Void') }}</span>
        </h1>
        <div style="display:flex;gap:.5rem">
            <a :href="order.receipt_url + '?print=1'" target="_blank" class="btn btn-primary">{{ __('Print receipt') }}</a>
            <button v-if="$page.props.auth.user.is_admin && order.status !== 'void'" class="btn btn-danger" @click="voidOrder">{{ __('Void order') }}</button>
        </div>
    </div>

    <div class="cols-2">
        <div class="card">
            <table class="table">
                <tr><th>{{ __('Invoice') }}</th><td class="num">{{ order.invoice_no }}</td></tr>
                <tr><th>{{ __('Date') }}</th><td class="num">{{ order.date }}</td></tr>
                <tr><th>{{ __('Hijri date') }}</th><td>{{ order.hijri }}</td></tr>
                <tr><th>{{ __('Type') }}</th><td>{{ __(TYPE_LABELS[order.order_type] || order.order_type) }}</td></tr>
                <tr><th>{{ __('Cashier') }}</th><td>{{ order.cashier }}</td></tr>
                <tr>
                    <th>{{ __('Payment') }}</th>
                    <td>{{ order.payment_method === 'card' ? __('Card') + (order.card_type ? ` (${CARDS[order.card_type]})` : '') : __('Cash') }}</td>
                </tr>
                <tr v-if="order.customer_name || order.customer_phone">
                    <th>{{ __('Customer') }}</th><td>{{ order.customer_name }} <span class="num">{{ order.customer_phone }}</span></td>
                </tr>
                <tr v-if="order.table_no"><th>{{ __('Table no.') }}</th><td>{{ order.table_no }}</td></tr>
                <tr v-if="order.note"><th>{{ __('Order note') }}</th><td>{{ order.note }}</td></tr>
                <tr v-if="$page.props.auth.user.is_admin">
                    <th>{{ __('ZATCA e-invoice') }}</th>
                    <td>
                        <Link v-if="einvoice" :href="route('admin.invoices.show', einvoice.id)">
                            <span class="num">{{ einvoice.invoice_number }}</span>
                        </Link>
                        <ZatcaStatus v-if="einvoice" :status="einvoice.zatca_status" style="margin-inline-start:.4rem" />
                        <Link v-else-if="order.status !== 'void'" :href="route('admin.invoices.create', { order: order.id })" class="btn btn-sm">
                            + {{ __('Create e-invoice') }}
                        </Link>
                        <span v-else class="muted">—</span>
                    </td>
                </tr>
            </table>
        </div>
        <div class="card">
            <table class="table">
                <tr><th>{{ __('Subtotal') }}</th><td class="text-end num">{{ money(order.subtotal) }}</td></tr>
                <tr><th>{{ __('Discount') }}</th><td class="text-end num">-{{ money(order.discount) }}</td></tr>
                <tr><th>{{ __('Total excl. VAT') }}</th><td class="text-end num">{{ money(order.net_amount) }}</td></tr>
                <tr><th>{{ __('VAT') }} ({{ order.vat_rate }}%)</th><td class="text-end num">{{ money(order.vat_amount) }}</td></tr>
                <tr>
                    <th>{{ __('Total') }}</th>
                    <td class="text-end">
                        <strong class="num">{{ money(order.total) }}</strong>
                        <div class="amount-words" dir="rtl">{{ order.amount_words }}</div>
                    </td>
                </tr>
                <template v-if="order.payment_method === 'cash'">
                    <tr><th>{{ __('Amount received') }}</th><td class="text-end num">{{ money(order.paid_amount) }}</td></tr>
                    <tr><th>{{ __('Change') }}</th><td class="text-end num">{{ money(order.change_amount) }}</td></tr>
                </template>
            </table>
        </div>
    </div>

    <div class="card table-wrap" style="padding:0;margin-top:1rem">
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('Item') }}</th>
                    <th class="text-end">{{ __('Qty') }}</th>
                    <th class="text-end">{{ __('Price') }}</th>
                    <th class="text-end">{{ __('Discount (%)') }}</th>
                    <th class="text-end">{{ __('Total') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="item in order.items" :key="item.id">
                    <td>{{ item.name }} <div v-if="item.note" class="muted">✎ {{ item.note }}</div></td>
                    <td class="text-end num">{{ item.quantity }}</td>
                    <td class="text-end num">{{ money(item.unit_price) }}</td>
                    <td class="text-end num">
                        <template v-if="item.discount_amount > 0">{{ +item.discount_percent.toFixed(2) }}% (-{{ money(item.discount_amount) }})</template>
                        <span v-else class="muted">—</span>
                    </td>
                    <td class="text-end num">{{ money(item.line_total) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
