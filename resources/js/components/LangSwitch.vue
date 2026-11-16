<script setup>
import { router, usePage } from '@inertiajs/vue3';

const page = usePage();

function switchTo(locale) {
    if (locale === page.props.locale) return;
    // Server stores the choice in the session and redirects back; state (e.g. the POS cart) is kept.
    router.get(route('locale', locale), {}, { preserveState: true, preserveScroll: true, replace: true });
}
</script>

<template>
    <div class="lang-switch">
        <a href="#" :class="{ active: $page.props.locale === 'en' }" @click.prevent="switchTo('en')">EN</a>
        <a href="#" :class="{ active: $page.props.locale === 'ar' }" @click.prevent="switchTo('ar')">ع</a>
    </div>
</template>
