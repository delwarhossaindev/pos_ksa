<script setup>
import { computed } from 'vue';

/** ZATCA reporting / clearance status as a coloured badge. */
const props = defineProps({ status: { type: String, required: true } });

const LABELS = {
    REPORTED: 'Reported',
    CLEARED: 'Cleared',
    NOT_REPORTED: 'Not reported',
    NOT_CLEARED: 'Not cleared',
    ERROR: 'Error',
    pending: 'Pending',
};

const kind = computed(() => {
    if (['REPORTED', 'CLEARED'].includes(props.status)) return 'badge-success';
    if (['NOT_REPORTED', 'NOT_CLEARED', 'ERROR'].includes(props.status)) return 'badge-danger';
    if (props.status === 'pending') return '';
    return 'badge-warning';
});
</script>

<template>
    <span class="badge" :class="kind">{{ __(LABELS[status] || status.replaceAll('_', ' ')) }}</span>
</template>
