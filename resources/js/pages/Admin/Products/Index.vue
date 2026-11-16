<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import PosShell from '@/layouts/PosShell.vue';
import AdminTabs from '@/components/AdminTabs.vue';
import Pagination from '@/components/Pagination.vue';
import { useI18n } from '@/lib/i18n';
import { money } from '@/lib/format';
import { confirmDialog } from '@/lib/dialog';

defineOptions({ layout: PosShell });
const props = defineProps({
    products: { type: Object, required: true },
    categories: { type: Array, required: true },
    filters: { type: Object, required: true },
});

const { t } = useI18n();
const form = reactive({ ...props.filters });

const filter = () => router.get(route('admin.products.index'), form, { preserveState: true, preserveScroll: true });

async function destroy(product) {
    const ok = await confirmDialog({
        title: t('Delete product'),
        message: t('Delete this product?'),
        detail: `${product.name_ar} — ${product.name_en}`,
        confirmText: t('Delete'),
        variant: 'danger',
    });
    if (ok) router.delete(route('admin.products.destroy', product.id), { preserveScroll: true });
}
</script>

<template>
    <Head :title="__('Products')" />
    <AdminTabs :tabs="[{ label: 'Items', route: 'admin.products.index', active: ['admin.products.*'] }, { label: 'Categories', route: 'admin.categories.index', active: ['admin.categories.*'] }]" />
    <div class="page-head">
        <h1>{{ __('Products') }}</h1>
        <Link :href="route('admin.products.create', form.category ? { category: form.category } : {})" class="btn btn-primary">+ {{ __('Add product') }}</Link>
    </div>

    <form class="filters card" @submit.prevent="filter">
        <div class="field">
            <label for="category">{{ __('Category') }}</label>
            <select id="category" v-model="form.category" class="input" @change="filter">
                <option value="">{{ __('All') }}</option>
                <option v-for="c in categories" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
            </select>
        </div>
        <div class="field" style="flex:1;min-width:200px">
            <label for="q">{{ __('Search') }}</label>
            <input id="q" v-model="form.q" type="search" class="input">
        </div>
        <button class="btn btn-primary">{{ __('Filter') }}</button>
    </form>

    <div class="card table-wrap" style="padding:0">
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('Name (English)') }}</th>
                    <th>{{ __('Name (Arabic)') }}</th>
                    <th>{{ __('Category') }}</th>
                    <th class="text-end">{{ __('Price') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th />
                </tr>
            </thead>
            <tbody>
                <tr v-for="p in products.data" :key="p.id">
                    <td>{{ p.name_en }}</td>
                    <td dir="rtl">{{ p.name_ar }}</td>
                    <td>{{ p.category }}</td>
                    <td class="text-end num"><strong>{{ money(p.price) }}</strong></td>
                    <td>
                        <span class="badge" :class="p.is_active ? 'badge-success' : 'badge-danger'">{{ p.is_active ? __('Active') : __('Inactive') }}</span>
                    </td>
                    <td>
                        <div class="actions">
                            <Link :href="route('admin.products.edit', p.id)" class="btn btn-sm">{{ __('Edit') }}</Link>
                            <button class="btn btn-sm btn-danger" @click="destroy(p)">{{ __('Delete') }}</button>
                        </div>
                    </td>
                </tr>
                <tr v-if="!products.data.length">
                    <td colspan="6" class="text-center muted" style="padding:2rem">{{ __('No records yet.') }}</td>
                </tr>
            </tbody>
        </table>
    </div>
    <Pagination :paginator="products" />
</template>
