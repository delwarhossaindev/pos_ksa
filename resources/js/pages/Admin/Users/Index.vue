<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import PosShell from '@/layouts/PosShell.vue';
import AdminTabs from '@/components/AdminTabs.vue';
import { SETTINGS_TABS } from '@/lib/settingsTabs';
import { useI18n } from '@/lib/i18n';
import { confirmDialog } from '@/lib/dialog';

defineOptions({ layout: PosShell });
defineProps({ users: { type: Array, required: true } });

const { t } = useI18n();

async function destroy(user) {
    const ok = await confirmDialog({
        title: t('Delete user'),
        message: t('Delete this user?'),
        detail: `${user.name} — ${user.email}`,
        confirmText: t('Delete'),
        variant: 'danger',
    });
    if (ok) router.delete(route('admin.users.destroy', user.id), { preserveScroll: true });
}
</script>

<template>
    <Head :title="__('Users')" />
    <AdminTabs :tabs="SETTINGS_TABS" />
    <div class="page-head">
        <h1>{{ __('Users') }}</h1>
        <Link :href="route('admin.users.create')" class="btn btn-primary">+ {{ __('Add user') }}</Link>
    </div>

    <div class="card table-wrap" style="padding:0">
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Email') }}</th>
                    <th>{{ __('Role') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th />
                </tr>
            </thead>
            <tbody>
                <tr v-for="u in users" :key="u.id">
                    <td>{{ u.name }}</td>
                    <td dir="ltr" style="text-align:start">{{ u.email }}</td>
                    <td><span class="badge" :class="{ 'badge-primary': u.role === 'admin' }">{{ u.role === 'admin' ? __('Administrator') : __('Cashier') }}</span></td>
                    <td><span class="badge" :class="u.is_active ? 'badge-success' : 'badge-danger'">{{ u.is_active ? __('Active') : __('Inactive') }}</span></td>
                    <td>
                        <div class="actions">
                            <Link :href="route('admin.users.edit', u.id)" class="btn btn-sm">{{ __('Edit') }}</Link>
                            <button v-if="u.id !== $page.props.auth.user.id" class="btn btn-sm btn-danger" @click="destroy(u)">{{ __('Delete') }}</button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
