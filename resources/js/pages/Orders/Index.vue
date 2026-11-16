<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import PosShell from '@/layouts/PosShell.vue';
import Pagination from '@/components/Pagination.vue';
import { money } from '@/lib/format';

defineOptions({ layout: PosShell });

const props = defineProps({
    orders: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const TYPE_LABELS = { takeaway: 'Takeaway', dine_in: 'Dine-in', delivery: 'Delivery' };
const form = reactive({ ...props.filters });

const filter = () => router.get(route('orders.index'), form, { preserveState: true, preserveScroll: true });
</script>

<template>
    <Head :title="__('Orders')" />
    <div class="page-head">
        <h1>{{ __('Orders') }}</h1>
        <Link :href="route('pos')" class="btn btn-primary">{{ __('New order') }}</Link>
    </div>

    <form class="filters card" @submit.prevent="filter">
        <div class="field">
            <label for="date">{{ __('Date') }}</label>
            <input id="date" v-model="form.date" type="date" class="input" @change="filter">
        </div>
        <div class="field" style="flex:1;min-width:200px">
            <label for="q">{{ __('Search') }}</label>
            <input id="q" v-model="form.q" type="search" class="input" :placeholder="__('Order no., invoice no. or phone')">
        </div>
        <button class="btn btn-primary">{{ __('Filter') }}</button>
    </form>

    <div class="card table-wrap" style="padding:0">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('Invoice') }}</th>
                    <th>{{ __('Time') }}</th>
                    <th>{{ __('Type') }}</th>
                    <th>{{ __('Cashier') }}</th>
                    <th>{{ __('Payment') }}</th>
                    <th class="text-end">{{ __('Total') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th />
                </tr>
            </thead>
            <tbody>
                <tr v-for="o in orders.data" :key="o.id">
                    <td class="num"><strong>{{ o.order_no }}</strong></td>
                    <td class="num">{{ o.invoice_no }}</td>
                    <td class="num">{{ o.time }}</td>
                    <td>{{ __(TYPE_LABELS[o.order_type] || o.order_type) }}</td>
                    <td>{{ o.cashier }}</td>
                    <td>{{ o.payment_method === 'card' ? __('Card') : __('Cash') }}</td>
                    <td class="text-end num"><strong>{{ money(o.total) }}</strong></td>
                    <td>
                        <span v-if="o.status === 'void'" class="badge badge-danger">{{ __('Void') }}</span>
                        <span v-else class="badge badge-success">{{ __('Completed') }}</span>
                    </td>
                    <td>
                        <div class="actions">
                            <Link :href="route('orders.show', o.id)" class="btn btn-sm">{{ __('View') }}</Link>
                            <a :href="o.receipt_url + '?print=1'" target="_blank" class="btn btn-sm">{{ __('Print') }}</a>
                        </div>
                    </td>
                </tr>
                <tr v-if="!orders.data.length">
                    <td colspan="9" class="text-center muted" style="padding:2rem">{{ __('No orders for this day.') }}</td>
                </tr>
            </tbody>
        </table>
    </div>
    <Pagination :paginator="orders" />
</template>
