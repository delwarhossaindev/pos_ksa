<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { acceptDialog, cancelDialog, dialogState as d } from '@/lib/dialog';

const confirmBtn = ref(null);
const input = ref(null);

const icon = computed(() => d.icon || (d.type === 'prompt' ? 'note' : d.variant === 'danger' ? 'danger' : 'question'));

// Focus the right control when a dialog opens (and select the prompt text for quick replace).
watch(() => d.open, async (open) => {
    if (!open) return;
    await nextTick();
    if (d.type === 'prompt') {
        input.value?.focus();
        input.value?.select();
    } else {
        confirmBtn.value?.focus();
    }
});

function onKey(e) {
    if (!d.open) return;
    if (e.key === 'Escape') {
        e.preventDefault();
        e.stopImmediatePropagation(); // don't also close a modal underneath
        cancelDialog();
    } else if (e.key === 'Enter' && d.type === 'prompt' && e.target === input.value) {
        e.preventDefault();
        acceptDialog();
    }
}
onMounted(() => document.addEventListener('keydown', onKey, true));
onBeforeUnmount(() => document.removeEventListener('keydown', onKey, true));
</script>

<template>
    <Teleport to="body">
        <Transition name="dlg">
            <div v-if="d.open" class="dlg-backdrop" @click.self="cancelDialog">
                <div class="dlg" :class="`dlg-${d.variant}`" role="alertdialog" aria-modal="true" :aria-labelledby="'dlg-title'">
                    <div class="dlg-badge" :class="`dlg-icon-${icon}`" aria-hidden="true">
                        <svg v-if="icon === 'danger'" viewBox="0 0 24 24"><path d="M9 3h6m-9 4h12m-1 0-.7 11.2A2 2 0 0 1 14.3 20H9.7a2 2 0 0 1-2-1.8L7 7m3 4v5m4-5v5" /></svg>
                        <svg v-else-if="icon === 'warning'" viewBox="0 0 24 24"><path d="M12 4 2.5 20h19L12 4Zm0 6v4.5m0 2.5v.5" /></svg>
                        <svg v-else-if="icon === 'note'" viewBox="0 0 24 24"><path d="M4 20h4L19 9l-4-4L4 16v4Zm9-13 4 4" /></svg>
                        <svg v-else viewBox="0 0 24 24"><path d="M9.2 9a3 3 0 1 1 4.3 2.7c-.9.5-1.5 1.2-1.5 2.3v.5m0 3v.5" /></svg>
                    </div>

                    <h2 id="dlg-title" class="dlg-title">{{ d.title }}</h2>
                    <p v-if="d.message" class="dlg-message">{{ d.message }}</p>
                    <div v-if="d.detail" class="dlg-detail">{{ d.detail }}</div>

                    <input
                        v-if="d.type === 'prompt'"
                        ref="input"
                        v-model="d.value"
                        class="input dlg-input"
                        :placeholder="d.placeholder"
                        :maxlength="d.maxlength"
                        autocomplete="off"
                    >

                    <div class="dlg-actions">
                        <button type="button" class="btn dlg-cancel" @click="cancelDialog">{{ d.cancelText || __('Cancel') }}</button>
                        <button
                            ref="confirmBtn"
                            type="button"
                            class="btn"
                            :class="d.variant === 'danger' ? 'btn-danger' : 'btn-primary'"
                            @click="acceptDialog"
                        >
                            {{ d.confirmText || __('OK') }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
