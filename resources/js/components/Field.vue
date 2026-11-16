<script setup>
defineProps({
    label: { type: String, required: true },
    error: { type: String, default: '' },
    id: { type: String, default: undefined },
});
</script>

<template>
    <div class="field">
        <label :for="id">{{ label }}</label>
        <slot />
        <div v-if="error" class="error">{{ error }}</div>
    </div>
</template>
