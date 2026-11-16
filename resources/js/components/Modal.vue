<script setup>
import { onBeforeUnmount, onMounted } from 'vue';

defineProps({ title: { type: String, required: true } });
const emit = defineEmits(['close']);

const onKey = (e) => { if (e.key === 'Escape') emit('close'); };
onMounted(() => document.addEventListener('keydown', onKey));
onBeforeUnmount(() => document.removeEventListener('keydown', onKey));
</script>

<template>
    <Teleport to="body">
        <div class="modal-backdrop" @click.self="emit('close')">
            <div class="modal" role="dialog" aria-modal="true">
                <div class="modal-head">
                    <h2>{{ title }}</h2>
                    <button type="button" class="btn btn-ghost" @click="emit('close')">✕</button>
                </div>
                <div class="modal-body"><slot /></div>
                <div v-if="$slots.footer" class="modal-foot"><slot name="footer" /></div>
            </div>
        </div>
    </Teleport>
</template>
