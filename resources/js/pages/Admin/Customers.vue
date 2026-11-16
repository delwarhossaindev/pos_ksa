<script setup>
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import PosShell from '@/layouts/PosShell.vue';
import Pagination from '@/components/Pagination.vue';
import { money } from '@/lib/format';

defineOptions({ layout: PosShell });
const props = defineProps({
    customers: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const form = reactive({ ...props.filters });
const filter = () => router.get(route('admin.customers.index'), form, { preserveState: true, preserveScroll: true });
</script>

<template>
    <Head :title="__('Customers')" />
    <div class="page-head">
        <h1>{{ __('Customers') }}</h1>
    </div>
    <p class="muted" style="margin-top:-.5rem">{{ __('Customers are collected automatically from the phone number entered on orders.') }}</p>

    <form class="filters card" @submit.prevent="filter">
        <div class="field" style="flex:1;min-width:220px">
            <label for="q">{{ __('Search') }}</label>
            <input id="q" v-model="form.q" type="search" class="input" :placeholder="__('Name or phone')">
        </div>
        <button class="btn btn-primary">{{ __('Filter') }}</button>
    </form>

    <div class="card table-wrap" style="padding:0">
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('Customer name') }}</th>
                    <th>{{ __('Phone') }}</th>
                    <th class="text-end">{{ __('Orders') }}</th>
                    <th class="text-end">{{ __('Total spent') }}</th>
                    <th>{{ __('Last order') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="c in customers.data" :key="c.phone">
                    <td>{{ c.name || '—' }}</td>
                    <td class="num" dir="ltr" style="text-align:start">{{ c.phone }}</td>
                    <td class="text-end num">{{ c.orders }}</td>
                    <td class="text-end num"><strong>{{ money(c.spent) }}</strong> <small class="muted">{{ $page.props.store.currency }}</small></td>
                    <td class="num">{{ c.last_order_at }}</td>
                </tr>
                <tr v-if="!customers.data.length">
                    <td colspan="5" class="text-center muted" style="padding:2rem">{{ __('No records yet.') }}</td>
                </tr>
            </tbody>
        </table>
    </div>
    <Pagination :paginator="customers" />
</template>
