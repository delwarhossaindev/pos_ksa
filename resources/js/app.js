import { createInertiaApp, router } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import { I18nPlugin } from './lib/i18n';
import DialogHost from './components/DialogHost.vue';

createInertiaApp({
    title: (title, page) => {
        const store = page?.props?.store?.name || '';
        return title ? `${title} · ${store}` : store;
    },
    resolve: (name) => {
        const pages = import.meta.glob('./pages/**/*.vue', { eager: true });
        const page = pages[`./pages/${name}.vue`];
        if (!page) throw new Error(`Page not found: ${name}`);
        return page;
    },
    setup({ el, App, props, plugin }) {
        // DialogHost renders the themed confirm/prompt dialogs (see lib/dialog.js) for every page.
        createApp({ render: () => [h(App, props), h(DialogHost)] })
            .use(plugin)
            .use(ZiggyVue)
            .use(I18nPlugin)
            .mount(el);
    },
    progress: { color: '#c9a227', delay: 150 },
});

// Keep <html lang/dir> in sync when the language is switched without a page reload.
// 'navigate' covers history/back; 'success' covers visits that keep the same URL (e.g. the language switch).
const syncHtmlAttributes = (event) => {
    const props = event.detail?.page?.props;
    if (!props) return;
    if (props.locale) document.documentElement.lang = props.locale;
    if (props.dir) document.documentElement.dir = props.dir;
};
router.on('navigate', syncHtmlAttributes);
router.on('success', syncHtmlAttributes);
