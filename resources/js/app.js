import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from 'ziggy-js';
import { createPinia } from 'pinia';
import PrimeVue from 'primevue/config';
import Aura from '@primevue/themes/aura';
import ConfirmationService from 'primevue/confirmationservice';
import ToastService from 'primevue/toastservice';
import { createI18n } from 'vue-i18n';
import * as Sentry from '@sentry/vue';
import { registerSW } from 'virtual:pwa-register';
// Web Vitals tracking moved to resources/js/composables/useWebVitals.js (Phase 4)
// Note: web-vitals library v5.x API differs from v4.x - will be updated in Phase 5.2

// Import translations
import en from '../../lang/en.json';
import fr from '../../lang/fr.json';
import pt from '../../lang/pt.json';
import es from '../../lang/es.json';

// Register PWA Service Worker
registerSW({ onNeedRefresh() {}, onOfflineReady() {} });

// Initialize Sentry for error tracking and Web Vitals monitoring (Phase 4)
const SENTRY_DSN = import.meta.env.VITE_SENTRY_DSN || '';
if (SENTRY_DSN) {
    Sentry.init({
        dsn: SENTRY_DSN,
        environment: import.meta.env.MODE,
        integrations: [
            new Sentry.Replay({
                maskAllText: true,
                blockAllMedia: true,
            }),
        ],
        tracesSampleRate: 0.1, // 10% performance sampling
        replaysSessionSampleRate: 0.1,
        replaysOnErrorSampleRate: 1.0,
    });

    // Track Core Web Vitals metrics
    // TODO: Phase 5.2 - Update web-vitals API usage for v5.x
    // The web-vitals library v5.x changed its export API
    // Will be updated in Phase 5 to use: onCLS, onFCP, onLCP, onFID from 'web-vitals'
}

const appName = import.meta.env.VITE_APP_NAME || 'WideHalo';
const savedLocale = localStorage.getItem('locale') || document.documentElement.lang || 'en';

const i18n = createI18n({
    legacy: false,
    locale: savedLocale,
    fallbackLocale: 'en',
    messages: { en, fr, pt, es },
});

createInertiaApp({
    title: (title) => `${title} — ${appName}`,

    resolve: (name) => {
        const pages = import.meta.glob([
            './Pages/**/*.vue',
            '../../Modules/*/resources/js/Pages/**/*.vue',
        ]);

        // Standard resolution
        const key = `./Pages/${name}.vue`;
        if (pages[key]) return resolvePageComponent(key, pages);

        // Module-prefixed resolution: "CRM/Contacts/Index"
        const parts = name.split('/');
        if (parts.length >= 2) {
            const [module, ...rest] = parts;
            const moduleKey = `../../Modules/${module}/resources/js/Pages/${rest.join('/')}.vue`;
            if (pages[moduleKey]) return pages[moduleKey]();
        }

        return resolvePageComponent(key, pages);
    },

    setup({ el, App, props, plugin }) {
        const pinia = createPinia();

        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(pinia)
            .use(i18n)
            .use(PrimeVue, {
                theme: {
                    preset: Aura,
                    options: { darkModeSelector: '.dark' },
                },
            })
            .use(ConfirmationService)
            .use(ToastService);

        // Integrate Sentry with Vue for error tracking
        if (SENTRY_DSN) {
            app.use(Sentry.vueIntegration());
        }

        // ApexCharts lazy-loaded on Dashboard only (see Pages/Dashboard.vue)
        return app.mount(el);
    },

    progress: {
        color: '#3B82F6',
    },
});
