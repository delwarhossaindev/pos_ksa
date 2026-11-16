<script setup>
/** Which ZATCA environment a screen / invoice belongs to. Production is the only legally valid one. */
defineProps({ environment: { type: String, required: true } });

const LABELS = { sandbox: 'Sandbox', simulation: 'Simulation', production: 'Production' };
const KINDS = { sandbox: 'badge-warning', simulation: 'badge-primary', production: 'badge-success' };
</script>

<template>
    <span class="badge" :class="KINDS[environment]">{{ __(LABELS[environment] || environment) }}</span>
</template>
