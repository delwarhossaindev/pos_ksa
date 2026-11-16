<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ZatcaEnvBadge from '@/components/ZatcaEnvBadge.vue';
import PosShell from '@/layouts/PosShell.vue';
import AdminTabs from '@/components/AdminTabs.vue';
import Pagination from '@/components/Pagination.vue';
import ZatcaStatus from '@/components/ZatcaStatus.vue';
import { SETTINGS_TABS } from '@/lib/settingsTabs';
import { money } from '@/lib/format';
import { useI18n } from '@/lib/i18n';
import { confirmDialog } from '@/lib/dialog';

defineOptions({ layout: PosShell });

const props = defineProps({
    invoices: { type: Object, required: true },
    filters: { type: Object, required: true },
    stats: { type: Object, required: true },
    environment: { type: String, required: true },
    environments: { type: Array, required: true },
    canIssue: { type: Boolean, default: false },
    canSubmit: { type: Boolean, default: false },
});

const { t } = useI18n();
const q = ref(props.filters.q);

const STATS = [
    ['', 'All documents', 'all'],
    ['accepted', 'Accepted', 'accepted'],
    ['pending', 'Pending', 'pending'],
    ['rejected', 'Rejected', 'rejected'],
];

const filter = (status, environment = props.filters.environment) => router.get(route('admin.invoices.index'), {
    status: status || undefined,
    q: q.value || undefined,
    environment: environment === props.environment ? undefined : environment,
}, { preserveState: true, preserveScroll: true });
const search = () => filter(props.filters.status);
const viewingOther = computed(() => props.filters.environment !== props.environment);

async function submitPending() {
    const ok = await confirmDialog({
        title: t('Submit pending invoices'),
        message: t('Report / clear every invoice that ZATCA has not accepted yet?'),
        confirmText: t('Submit'),
    });
    if (ok) router.post(route('admin.invoices.submit-pending'), {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="__('E-Invoices')" />
    <AdminTabs :tabs="SETTINGS_TABS" />
    <div class="page-head">
        <h1>{{ __('E-Invoices') }} <ZatcaEnvBadge :environment="environment" style="vertical-align:middle" /></h1>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <button v-if="canSubmit && stats.all - stats.accepted > 0" type="button" class="btn" @click="submitPending">
                {{ __('Submit pending to ZATCA') }}
            </button>
            <Link v-if="canIssue" :href="route('admin.invoices.create')" class="btn btn-primary">+ {{ __('New invoice') }}</Link>
            <span v-else class="btn btn-primary" style="opacity:.5" :title="__('Complete ZATCA onboarding first.')">+ {{ __('New invoice') }}</span>
        </div>
    </div>

    <div v-if="!canSubmit" class="alert alert-danger" style="background:#fdf3dc;color:#8a5d00;border-color:#f0d9a2">
        {{ __('Complete ZATCA onboarding to get a Production CSID before issuing invoices.') }}
        <Link :href="route('admin.zatca.edit')">{{ __('ZATCA onboarding') }} ›</Link>
    </div>

    <div class="stats">
        <button
            v-for="[status, label, key] in STATS"
            :key="key"
            type="button"
            class="stat"
            style="text-align:start;cursor:pointer;font:inherit"
            :style="filters.status === status ? 'border-color:var(--primary);box-shadow:0 0 0 2px var(--primary-soft)' : ''"
            @click="filter(status)"
        >
            <div class="label">{{ __(label) }}</div>
            <div class="value num">{{ stats[key] }}</div>
        </button>
    </div>

    <form class="filters" @submit.prevent="search">
        <input v-model="q" class="input" style="max-width:280px" :placeholder="__('Search number or buyer…')">
        <button class="btn">{{ __('Search') }}</button>
        <select
            class="input"
            style="max-width:240px;margin-inline-start:auto"
            :value="filters.environment"
            :aria-label="__('Environment')"
            @change="filter(filters.status, $event.target.value)"
        >
            <option v-for="env in environments" :key="env.value" :value="env.value">
                {{ __(env.label) }}{{ env.value === environment ? ' ✓' : '' }}
            </option>
        </select>
    </form>
    <div v-if="viewingOther" class="alert alert-danger" style="background:#fdf3dc;color:#8a5d00;border-color:#f0d9a2">
        {{ __('These invoices belong to another environment. They are read-only and can no longer be sent to ZATCA.') }}
    </div>

    <div class="card table-wrap" style="padding:0">
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('Number') }}</th>
                    <th>{{ __('Type') }}</th>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Buyer') }}</th>
                    <th class="text-end">{{ __('Total') }}</th>
                    <th>{{ __('ICV') }}</th>
                    <th>{{ __('ZATCA status') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="inv in invoices.data" :key="inv.id" class="z-row-link" @click="router.visit(route('admin.invoices.show', inv.id))">
                    <td><strong class="num">{{ inv.invoice_number }}</strong></td>
                    <td>{{ __(inv.label) }}</td>
                    <td class="num">{{ inv.issue_date }}</td>
                    <td>{{ inv.buyer_name || '—' }}</td>
                    <td class="text-end num">{{ money(inv.total_amount) }}</td>
                    <td class="num muted">#{{ inv.icv }}</td>
                    <td><ZatcaStatus :status="inv.zatca_status" /></td>
                </tr>
                <tr v-if="!invoices.data.length">
                    <td colspan="7" class="text-center muted" style="padding:2rem">{{ __('No invoices yet') }}</td>
                </tr>
            </tbody>
        </table>
    </div>
    <Pagination :paginator="invoices" />
</template>
