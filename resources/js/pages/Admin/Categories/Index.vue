<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import PosShell from '@/layouts/PosShell.vue';
import AdminTabs from '@/components/AdminTabs.vue';
import { useI18n } from '@/lib/i18n';
import { confirmDialog } from '@/lib/dialog';

defineOptions({ layout: PosShell });
defineProps({ categories: { type: Array, required: true } });

const { t } = useI18n();

async function destroy(category) {
    const ok = await confirmDialog({
        title: t('Delete category'),
        message: t('Delete this category and all its products?'),
        detail: `${category.name_ar} — ${category.name_en} (${t(':count products', { count: category.products_count })})`,
        confirmText: t('Delete'),
        variant: 'danger',
    });
    if (ok) router.delete(route('admin.categories.destroy', category.id), { preserveScroll: true });
}
</script>

<template>
    <Head :title="__('Categories')" />
    <AdminTabs :tabs="[{ label: 'Items', route: 'admin.products.index', active: ['admin.products.*'] }, { label: 'Categories', route: 'admin.categories.index', active: ['admin.categories.*'] }]" />
    <div class="page-head">
        <h1>{{ __('Categories') }}</h1>
        <Link :href="route('admin.categories.create')" class="btn btn-primary">+ {{ __('Add category') }}</Link>
    </div>

    <div class="card table-wrap" style="padding:0">
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('Display order') }}</th>
                    <th>{{ __('Name (English)') }}</th>
                    <th>{{ __('Name (Arabic)') }}</th>
                    <th>{{ __('Products') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th />
                </tr>
            </thead>
            <tbody>
                <tr v-for="c in categories" :key="c.id">
                    <td class="num">{{ c.sort_order }}</td>
                    <td>{{ c.name_en }}</td>
                    <td dir="rtl">{{ c.name_ar }}</td>
                    <td><Link :href="route('admin.products.index', { category: c.id })">{{ c.products_count }}</Link></td>
                    <td>
                        <span class="badge" :class="c.is_active ? 'badge-success' : 'badge-danger'">{{ c.is_active ? __('Active') : __('Inactive') }}</span>
                    </td>
                    <td>
                        <div class="actions">
                            <Link :href="route('admin.categories.edit', c.id)" class="btn btn-sm">{{ __('Edit') }}</Link>
                            <button class="btn btn-sm btn-danger" @click="destroy(c)">{{ __('Delete') }}</button>
                        </div>
                    </td>
                </tr>
                <tr v-if="!categories.length">
                    <td colspan="6" class="text-center muted" style="padding:2rem">{{ __('No records yet.') }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
