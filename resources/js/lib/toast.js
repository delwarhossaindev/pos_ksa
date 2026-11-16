import { reactive } from 'vue';

/** One toast for the whole till frame (rendered by layouts/PosShell.vue). */
export const toastState = reactive({ message: '', error: false, visible: false });
let timer;

export function showToast(message, error = false) {
    Object.assign(toastState, { message, error, visible: true });
    clearTimeout(timer);
    timer = setTimeout(() => { toastState.visible = false; }, error ? 4500 : 2500);
}
