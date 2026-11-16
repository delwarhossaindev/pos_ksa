<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import PosShell from '@/layouts/PosShell.vue';
import Field from '@/components/Field.vue';

defineOptions({ layout: PosShell });
const props = defineProps({ user: { type: Object, default: null } });

const editing = computed(() => !!props.user);
const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    role: props.user?.role ?? 'cashier',
    password: '',
    password_confirmation: '',
    is_active: props.user?.is_active ?? true,
});

const submit = () => {
    const options = { onFinish: () => form.reset('password', 'password_confirmation') };
    editing.value
        ? form.put(route('admin.users.update', props.user.id), options)
        : form.post(route('admin.users.store'), options);
};
</script>

<template>
    <Head :title="editing ? __('Edit user') : __('Add user')" />
    <div class="page-head">
        <h1>{{ editing ? __('Edit user') : __('Add user') }}</h1>
        <Link :href="route('admin.users.index')" class="btn">{{ __('Back') }}</Link>
    </div>

    <form class="card" style="max-width:640px" @submit.prevent="submit">
        <div class="grid-2">
            <Field id="name" :label="__('Name')" :error="form.errors.name">
                <input id="name" v-model="form.name" class="input" required>
            </Field>
            <Field id="email" :label="__('Email')" :error="form.errors.email">
                <input id="email" v-model="form.email" type="email" class="input" dir="ltr" required>
            </Field>
        </div>
        <Field id="role" :label="__('Role')" :error="form.errors.role" style="max-width:300px">
            <select id="role" v-model="form.role" class="input">
                <option value="cashier">{{ __('Cashier') }}</option>
                <option value="admin">{{ __('Administrator') }}</option>
            </select>
        </Field>
        <div class="grid-2">
            <Field id="password" :label="__('Password') + (editing ? ` (${__('leave blank to keep')})` : '')" :error="form.errors.password">
                <input id="password" v-model="form.password" type="password" class="input" dir="ltr" autocomplete="new-password" :required="!editing">
            </Field>
            <Field id="password_confirmation" :label="__('Confirm password')">
                <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="input" dir="ltr" autocomplete="new-password">
            </Field>
        </div>
        <div class="field">
            <label class="check"><input v-model="form.is_active" type="checkbox"> {{ __('Active (can log in)') }}</label>
        </div>
        <button class="btn btn-primary" :disabled="form.processing">{{ __('Save') }}</button>
    </form>
</template>
