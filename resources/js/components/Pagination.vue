<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({ paginator: { type: Object, required: true } });
</script>

<template>
    <nav v-if="paginator.last_page > 1" class="pagination" style="display:flex;gap:.5rem;align-items:center;justify-content:center">
        <Link v-if="paginator.prev_page_url" class="btn btn-sm" :href="paginator.prev_page_url" preserve-scroll>{{ __('Previous') }}</Link>
        <span v-else class="btn btn-sm" style="opacity:.5">{{ __('Previous') }}</span>

        <span class="muted num">{{ paginator.current_page }} / {{ paginator.last_page }}</span>

        <Link v-if="paginator.next_page_url" class="btn btn-sm" :href="paginator.next_page_url" preserve-scroll>{{ __('Next') }}</Link>
        <span v-else class="btn btn-sm" style="opacity:.5">{{ __('Next') }}</span>
    </nav>
</template>
