import { reactive } from 'vue';

/**
 * Themed replacement for window.confirm / window.prompt.
 *
 *   if (await confirmDialog({ title, message, detail, confirmText, variant: 'danger' })) { ... }
 *   const note = await promptDialog({ title, message, value, placeholder }); // string | null
 *
 * Rendered once by <DialogHost /> (mounted in app.js).
 */
const DEFAULTS = {
    type: 'confirm', // confirm | prompt
    variant: 'primary', // primary | danger
    icon: null, // question | warning | danger | note (defaults from variant/type)
    title: '',
    message: '',
    detail: '', // highlighted line, e.g. the item being deleted
    confirmText: '',
    cancelText: '',
    value: '',
    placeholder: '',
    maxlength: 255,
};

export const dialogState = reactive({ open: false, ...DEFAULTS, resolve: null });

function open(options) {
    // A new dialog replaces any open one; the old promise resolves as "cancelled".
    if (dialogState.resolve) settle(dialogState.type === 'prompt' ? null : false);

    return new Promise((resolve) => {
        Object.assign(dialogState, DEFAULTS, options, { open: true, resolve });
    });
}

function settle(result) {
    const resolve = dialogState.resolve;
    dialogState.resolve = null;
    dialogState.open = false;
    resolve?.(result);
}

export const confirmDialog = (options) => open({ ...options, type: 'confirm' });

export const promptDialog = (options) => open({ ...options, type: 'prompt' });

export const acceptDialog = () => settle(dialogState.type === 'prompt' ? String(dialogState.value ?? '') : true);

export const cancelDialog = () => settle(dialogState.type === 'prompt' ? null : false);
