<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import PosShell from '@/layouts/PosShell.vue';
import AdminTabs from '@/components/AdminTabs.vue';
import { SETTINGS_TABS } from '@/lib/settingsTabs';
import Field from '@/components/Field.vue';

defineOptions({ layout: PosShell });
const props = defineProps({ settings: { type: Object, required: true } });

const KEYS = [
    'store_name_en', 'store_name_ar', 'address_en', 'address_ar', 'phone',
    'vat_number', 'cr_number', 'vat_rate', 'currency_en', 'currency_ar',
    'receipt_footer_en', 'receipt_footer_ar',
];
const form = useForm(Object.fromEntries(KEYS.map((k) => [k, props.settings[k] ?? ''])));

const sections = [
    { title: 'Store', fields: [
        ['store_name_en', 'Store name (English)', 'ltr'],
        ['store_name_ar', 'Store name (Arabic)', 'rtl'],
        ['address_en', 'Address (English)', 'ltr'],
        ['address_ar', 'Address (Arabic)', 'rtl'],
        ['phone', 'Phone', 'ltr'],
    ] },
    { title: 'Tax & currency', note: 'Product prices include VAT. The VAT amount is extracted from the total on each invoice.', fields: [
        ['vat_number', 'VAT number', 'ltr'],
        ['cr_number', 'Commercial registration (CR)', 'ltr'],
        ['vat_rate', 'VAT rate (%)', 'ltr', 'number'],
        ['currency_en', 'Currency (English)', 'ltr'],
        ['currency_ar', 'Currency (Arabic)', 'rtl'],
    ] },
    { title: 'Receipt', fields: [
        ['receipt_footer_en', 'Footer (English)', 'ltr'],
        ['receipt_footer_ar', 'Footer (Arabic)', 'rtl'],
    ] },
];

const submit = () => form.put(route('admin.settings.update'), { preserveScroll: true });
</script>

<template>
    <Head :title="__('Settings')" />
    <AdminTabs :tabs="SETTINGS_TABS" />
    <div class="page-head">
        <h1>{{ __('Settings') }}</h1>
    </div>

    <form style="max-width:820px" @submit.prevent="submit">
        <div v-for="section in sections" :key="section.title" class="card">
            <h2>{{ __(section.title) }}</h2>
            <div class="grid-2">
                <Field v-for="[key, label, dir, type] in section.fields" :id="key" :key="key" :label="__(label)" :error="form.errors[key]">
                    <input
                        :id="key"
                        v-model="form[key]"
                        type="text"
                        class="input"
                        :dir="dir"
                        :inputmode="type === 'number' ? 'decimal' : undefined"
                    >
                </Field>
            </div>
            <p v-if="section.note" class="muted" style="margin:0">{{ __(section.note) }}</p>
        </div>

        <div style="margin-top:1rem">
            <button class="btn btn-primary btn-lg" :disabled="form.processing">{{ __('Save settings') }}</button>
        </div>
    </form>
</template>
