<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import LangSwitch from '@/components/LangSwitch.vue';
import Field from '@/components/Field.vue';
import { ROLE_LABELS } from '@/lib/roles';

defineProps({
    demoAccounts: { type: Array, default: () => [] },
});

const form = useForm({ email: '', password: '', remember: false });

const submit = () => form.post(route('login'), { onFinish: () => form.reset('password') });

/** One click: fill the form with a demo account and sign in. */
function loginAs(account) {
    if (form.processing) return;
    form.email = account.email;
    form.password = account.password;
    submit();
}
</script>

<template>
    <Head :title="__('Login')" />
    <div class="auth-wrap">
        <div class="card auth-card">
            <div style="display:flex;justify-content:flex-end;margin-bottom:.5rem">
                <LangSwitch />
            </div>
            <img v-if="$page.props.store.logo" class="auth-logo" :src="$page.props.store.logo" :alt="$page.props.store.name">
            <div v-else class="emblem">{{ ($page.props.store.name || '').trim().charAt(0) }}</div>
            <h1>{{ $page.props.store.name }}</h1>
            <p class="muted text-center" style="margin-top:-.4rem">{{ __('Sign in to start selling') }}</p>

            <form @submit.prevent="submit">
                <Field id="email" :label="__('Email')" :error="form.errors.email">
                    <input id="email" v-model="form.email" class="input" type="email" required autofocus autocomplete="username" dir="ltr">
                </Field>
                <Field id="password" :label="__('Password')" :error="form.errors.password">
                    <input id="password" v-model="form.password" class="input" type="password" required autocomplete="current-password" dir="ltr">
                </Field>
                <div class="field">
                    <label class="check"><input v-model="form.remember" type="checkbox"> {{ __('Remember me') }}</label>
                </div>
                <button class="btn btn-primary btn-block btn-lg" :disabled="form.processing">{{ __('Login') }}</button>
            </form>

            <div v-if="demoAccounts.length" class="demo-logins">
                <div class="demo-divider"><span>{{ __('Demo accounts') }}</span></div>
                <div class="demo-grid">
                    <button
                        v-for="account in demoAccounts"
                        :key="account.email"
                        type="button"
                        class="demo-btn"
                        :class="account.role"
                        :disabled="form.processing"
                        @click="loginAs(account)"
                    >
                        <span class="demo-role">{{ __(ROLE_LABELS[account.role] || account.role) }}</span>
                        <span class="demo-name">{{ account.name }}</span>
                        <span class="demo-cred" dir="ltr">{{ account.email }}</span>
                        <span class="demo-cred">{{ __('Password') }}: <bdi dir="ltr">{{ account.password }}</bdi></span>
                    </button>
                </div>
                <p class="demo-note">{{ __('Click an account to sign in instantly.') }}</p>
            </div>
        </div>
    </div>
</template>
