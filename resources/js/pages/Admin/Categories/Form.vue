<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import PosShell from '@/layouts/PosShell.vue';
import Field from '@/components/Field.vue';

defineOptions({ layout: PosShell });
const props = defineProps({ category: { type: Object, default: null } });

const editing = computed(() => !!props.category);
const form = useForm({
    name_en: props.category?.name_en ?? '',
    name_ar: props.category?.name_ar ?? '',
    sort_order: props.category?.sort_order ?? 0,
    is_active: props.category?.is_active ?? true,
});

const submit = () => editing.value
    ? form.put(route('admin.categories.update', props.category.id))
    : form.post(route('admin.categories.store'));
</script>

<template>
    <Head :title="editing ? __('Edit category') : __('Add category')" />
    <div class="page-head">
        <h1>{{ editing ? __('Edit category') : __('Add category') }}</h1>
        <Link :href="route('admin.categories.index')" class="btn">{{ __('Back') }}</Link>
    </div>

    <form class="card" style="max-width:640px" @submit.prevent="submit">
        <div class="grid-2">
            <Field id="name_en" :label="__('Name (English)')" :error="form.errors.name_en">
                <input id="name_en" v-model="form.name_en" class="input" dir="ltr" required>
            </Field>
            <Field id="name_ar" :label="__('Name (Arabic)')" :error="form.errors.name_ar">
                <input id="name_ar" v-model="form.name_ar" class="input" dir="rtl" required>
            </Field>
        </div>
        <Field id="sort_order" :label="__('Display order')" :error="form.errors.sort_order" style="max-width:200px">
            <input id="sort_order" v-model="form.sort_order" type="text" inputmode="numeric" class="input num">
        </Field>
        <div class="field">
            <label class="check"><input v-model="form.is_active" type="checkbox"> {{ __('Active (shown on POS)') }}</label>
        </div>
        <button class="btn btn-primary" :disabled="form.processing">{{ __('Save') }}</button>
    </form>
</template>
