import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/** Translate an English source string using the translations shared by the server for the active locale. */
export function translate(translations, key, replace = {}) {
    let text = (translations && translations[key]) || key;
    Object.entries(replace).forEach(([k, v]) => {
        text = text.split(':' + k).join(String(v));
    });
    return text;
}

/** Composable for <script setup>: const { t, locale } = useI18n() */
export function useI18n() {
    const page = usePage();
    return {
        t: (key, replace) => translate(page.props.translations, key, replace),
        locale: computed(() => page.props.locale),
    };
}

/** Vue plugin: exposes __() in every template. */
export const I18nPlugin = {
    install(app) {
        app.config.globalProperties.__ = function (key, replace) {
            return translate(this.$page?.props?.translations, key, replace);
        };
    },
};
