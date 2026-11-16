<script setup>
import { router, usePage } from '@inertiajs/vue3';

/**
 * Section tabs inside a bottom-bar screen, e.g. Item List → Items | Categories,
 * Settings → Store | Users (those two have no button of their own in the bottom bar).
 */
defineProps({ tabs: { type: Array, required: true } }); // [{ label, route, active: [patterns] }]

const page = usePage();
const isActive = (patterns) => page.url && patterns.some((p) => route().current(p));
</script>

<template>
    <div class="admin-tabs" role="tablist">
        <button
            v-for="tab in tabs"
            :key="tab.route"
            type="button"
            role="tab"
            :aria-selected="isActive(tab.active)"
            :class="{ active: isActive(tab.active) }"
            @click="router.visit(route(tab.route))"
        >
            {{ __(tab.label) }}
        </button>
    </div>
</template>
