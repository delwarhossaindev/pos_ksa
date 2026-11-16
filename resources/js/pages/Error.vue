<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';

const props = defineProps({
    status: { type: Number, required: true },
    locale: { type: String, default: 'ar' },
    dir: { type: String, default: 'rtl' },
});

const MESSAGES = {
    403: ['Access denied', 'You do not have permission to open this page.'],
    404: ['Page not found', 'The page you are looking for does not exist.'],
    429: ['Too many attempts', 'Please wait a minute and try again.'],
    500: ['Something went wrong', 'An unexpected error occurred. Please try again.'],
    503: ['Under maintenance', 'The system is being updated. Please try again shortly.'],
};
const message = computed(() => MESSAGES[props.status] || MESSAGES[500]);

onMounted(() => {
    document.documentElement.lang = props.locale;
    document.documentElement.dir = props.dir;
});
</script>

<template>
    <Head :title="__(message[0])" />
    <div class="error-wrap" :dir="dir">
        <div class="card error-card">
            <div class="code num">{{ status }}</div>
            <h1 style="margin-top:.5rem">{{ __(message[0]) }}</h1>
            <p class="muted">{{ __(message[1]) }}</p>
            <div style="display:flex;gap:.5rem;justify-content:center;flex-wrap:wrap">
                <button type="button" class="btn" onclick="history.back()">{{ __('Go back') }}</button>
                <Link :href="route('pos')" class="btn btn-primary">{{ __('Home') }}</Link>
            </div>
        </div>
    </div>
</template>
