import { onBeforeUnmount, reactive, watchEffect } from 'vue';

/**
 * State shared between the till frame (layouts/PosShell.vue) and the page inside it:
 * - title: big text at the start of the header (order type on the POS, page name elsewhere)
 * - customer handler: the header "Customer Service" button opens the POS customer dialog
 */
export const shell = reactive({
    title: '',
    customerHandler: null,
    openCustomerOnArrival: false,
});

/** Keep the header title in sync with a getter (re-runs when locale/state changes). */
export function useShellTitle(getter) {
    const stop = watchEffect(() => { shell.title = getter(); });
    onBeforeUnmount(stop);
}

/** Register the page's customer dialog for the header button (POS page only). */
export function useCustomerHandler(handler) {
    shell.customerHandler = handler;
    onBeforeUnmount(() => { if (shell.customerHandler === handler) shell.customerHandler = null; });
}
