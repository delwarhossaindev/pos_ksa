<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import PosShell from '@/layouts/PosShell.vue';
import Field from '@/components/Field.vue';

defineOptions({ layout: PosShell });
const props = defineProps({
    product: { type: Object, default: null },
    categories: { type: Array, required: true },
    defaultCategory: { type: Number, default: null },
});

const editing = computed(() => !!props.product);
const form = useForm({
    category_id: props.product?.category_id ?? props.defaultCategory ?? '',
    name_en: props.product?.name_en ?? '',
    name_ar: props.product?.name_ar ?? '',
    price: props.product?.price ?? '',
    sort_order: props.product?.sort_order ?? 0,
    is_active: props.product?.is_active ?? true,
});

const submit = () => editing.value
    ? form.put(route('admin.products.update', props.product.id))
    : form.post(route('admin.products.store'));
</script>

<template>
    <Head :title="editing ? __('Edit product') : __('Add product')" />
    <div class="page-head">
        <h1>{{ editing ? __('Edit product') : __('Add product') }}</h1>
        <Link :href="route('admin.products.index')" class="btn">{{ __('Back') }}</Link>
    </div>

    <form class="card" style="max-width:640px" @submit.prevent="submit">
        <Field id="category_id" :label="__('Category')" :error="form.errors.category_id">
            <select id="category_id" v-model="form.category_id" class="input" required>
                <option value="">—</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
        </Field>
        <div class="grid-2">
            <Field id="name_en" :label="__('Name (English)')" :error="form.errors.name_en">
                <input id="name_en" v-model="form.name_en" class="input" dir="ltr" required>
            </Field>
            <Field id="name_ar" :label="__('Name (Arabic)')" :error="form.errors.name_ar">
                <input id="name_ar" v-model="form.name_ar" class="input" dir="rtl" required>
            </Field>
        </div>
        <div class="grid-2">
            <Field id="price" :label="__('Price (incl. VAT)')" :error="form.errors.price">
                <input id="price" v-model="form.price" type="text" inputmode="decimal" class="input num" required>
            </Field>
            <Field id="sort_order" :label="__('Display order')" :error="form.errors.sort_order">
                <input id="sort_order" v-model="form.sort_order" type="text" inputmode="numeric" class="input num">
            </Field>
        </div>
        <div class="field">
            <label class="check"><input v-model="form.is_active" type="checkbox"> {{ __('Active (shown on POS)') }}</label>
        </div>
        <button class="btn btn-primary" :disabled="form.processing">{{ __('Save') }}</button>
    </form>
</template>
