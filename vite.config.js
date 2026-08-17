import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { VitePWA } from 'vite-plugin-pwa';
import { resolve } from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        VitePWA({
            registerType: 'autoUpdate',
            devOptions: { enabled: true },
            workbox: {
                globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2}'],
                skipWaiting: true,
                clientsClaim: true,
                runtimeCaching: [
                    {
                        // API : StaleWhileRevalidate (cache first, refresh in background)
                        urlPattern: /\/api\/v1\/.*/,
                        handler: 'StaleWhileRevalidate',
                        options: {
                            cacheName: 'api-cache-v1',
                            expiration: { maxEntries: 500, maxAgeSeconds: 30 * 60 }, // 30 min
                            cacheableResponse: {
                                statuses: [0, 200],
                            }
                        },
                    },
                    {
                        // Assets statiques : CacheFirst longue durée
                        urlPattern: /\.(png|jpg|jpeg|svg|gif|webp|ico)$/,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'images-cache',
                            expiration: { maxEntries: 100, maxAgeSeconds: 86400 * 30 },
                        },
                    },
                    {
                        // Fonts : CacheFirst
                        urlPattern: /\.(woff2?|ttf|eot)$/,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'fonts-cache',
                            expiration: { maxAgeSeconds: 86400 * 365 },
                        },
                    },
                ],
            },
            manifest: {
                name: 'WideHalo ERP',
                short_name: 'WideHalo',
                description: 'ERP complet multi-modules',
                theme_color: '#1e40af',
                background_color: '#ffffff',
                display: 'standalone',
                orientation: 'portrait-primary',
                icons: [
                    { src: '/icons/icon-72x72.png', sizes: '72x72', type: 'image/png' },
                    { src: '/icons/icon-96x96.png', sizes: '96x96', type: 'image/png' },
                    { src: '/icons/icon-128x128.png', sizes: '128x128', type: 'image/png' },
                    { src: '/icons/icon-144x144.png', sizes: '144x144', type: 'image/png' },
                    { src: '/icons/icon-152x152.png', sizes: '152x152', type: 'image/png' },
                    { src: '/icons/icon-192x192.png', sizes: '192x192', type: 'image/png', purpose: 'any maskable' },
                    { src: '/icons/icon-384x384.png', sizes: '384x384', type: 'image/png' },
                    { src: '/icons/icon-512x512.png', sizes: '512x512', type: 'image/png', purpose: 'any maskable' },
                ],
                shortcuts: [
                    { name: 'CRM', url: '/crm/contacts', icons: [{ src: '/icons/icon-96x96.png', sizes: '96x96' }] },
                    { name: 'Helpdesk', url: '/helpdesk/tickets', icons: [{ src: '/icons/icon-96x96.png', sizes: '96x96' }] },
                    { name: 'Inventory', url: '/inventory/products', icons: [{ src: '/icons/icon-96x96.png', sizes: '96x96' }] },
                ],
            },
        }),
    ],
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/js'),
            '@modules': resolve(__dirname, 'Modules'),
        },
    },
    test: {
        environment: 'jsdom',
        globals: true,
        include: ['resources/js/**/*.spec.js', 'Modules/*/resources/js/**/*.spec.js'],
    },
    build: {
        rollupOptions: {
            external: ['vue-router', 'chart.js', 'chart.js/auto'],
            output: {
                manualChunks: (id) => {
                    // Core framework — single chunk for optimal caching
                    if (id.includes('node_modules/vue') || id.includes('@inertiajs/vue3') || id.includes('pinia') || id.includes('vue-i18n')) {
                        return 'vendor-core'
                    }

                    // Utilities — reusable across all modules
                    if (id.includes('lodash-es') || id.includes('dayjs') || id.includes('zod') || id.includes('vee-validate')) {
                        return 'vendor-utils'
                    }

                    // API & data fetching
                    if (id.includes('axios') || id.includes('@tanstack/vue-query') || id.includes('graphql')) {
                        return 'vendor-http'
                    }

                    // Monitoring & Sentry
                    if (id.includes('@sentry')) {
                        return 'vendor-monitoring'
                    }

                    // PrimeVue — aggressive splitting to reduce initial load
                    // Table components (39+ uses of DataTable) — heavy, lazy load
                    if (id.includes('primevue/datatable') || id.includes('primevue/column')) {
                        return 'primevue-table'
                    }
                    // Form inputs (47+ uses of InputText, 23+ Dropdown) — frequently used
                    if (id.includes('primevue/inputtext') || id.includes('primevue/input') ||
                        id.includes('primevue/dropdown') || id.includes('primevue/select') ||
                        id.includes('primevue/calendar') || id.includes('primevue/datepicker')) {
                        return 'primevue-inputs'
                    }
                    // Dialog/modals (57+ uses of Dialog) — modal-heavy app
                    if (id.includes('primevue/dialog') || id.includes('primevue/sidebar') ||
                        id.includes('primevue/drawer') || id.includes('primevue/tooltip')) {
                        return 'primevue-dialogs'
                    }
                    // Button and tags (49+ Button, 42+ Tag uses) — basic components
                    if (id.includes('primevue/button') || id.includes('primevue/tag') ||
                        id.includes('primevue/badge')) {
                        return 'primevue-buttons'
                    }
                    // Text components
                    if (id.includes('primevue/textarea') || id.includes('primevue/inputnumber')) {
                        return 'primevue-text'
                    }
                    // Everything else (theme, misc components) — theme must load early
                    if (id.includes('primevue') || id.includes('@primevue')) {
                        return 'primevue-misc'
                    }

                    // Charts & visualizations — lazy loaded (Dashboard only)
                    if (id.includes('apexcharts') || id.includes('vue3-apexcharts')) {
                        return 'charts'
                    }

                    // Icons — lightweight, fast
                    if (id.includes('lucide-vue-next') || id.includes('primeicons')) {
                        return 'vendor-icons'
                    }

                    // Module chunks — split at page level to reduce chunk size and enable lazy loading
                    // ACCOUNTING: Split each page separately for fine-grained lazy loading
                    if (id.includes('/Modules/Accounting/resources/js/Pages/Invoices/Form')) {
                        return 'accounting-invoices-form'
                    }
                    if (id.includes('/Modules/Accounting/resources/js/Pages/Invoices/Index')) {
                        return 'accounting-invoices-list'
                    }
                    if (id.includes('/Modules/Accounting/resources/js/Pages/Invoices/')) {
                        return 'accounting-invoices'
                    }
                    if (id.includes('/Modules/Accounting/resources/js/Pages/Expenses/')) {
                        return 'accounting-expenses'
                    }
                    if (id.includes('/Modules/Accounting/resources/js/Pages/Reports/')) {
                        return 'accounting-reports'
                    }
                    if (id.includes('/Modules/Accounting/')) {
                        return 'accounting'
                    }

                    // CRM: Split accounts and contacts from opportunities
                    if (id.includes('/Modules/CRM/resources/js/Pages/Accounts/')) {
                        return 'crm-accounts'
                    }
                    if (id.includes('/Modules/CRM/resources/js/Pages/Opportunities/')) {
                        return 'crm-opportunities'
                    }
                    if (id.includes('/Modules/CRM/resources/js/Pages/Contacts/')) {
                        return 'crm-contacts'
                    }
                    if (id.includes('/Modules/CRM/resources/js/Pages/Leads/')) {
                        return 'crm-leads'
                    }
                    if (id.includes('/Modules/CRM/')) {
                        return 'crm'
                    }

                    // Other modules
                    if (id.includes('/Modules/Inventory/')) {
                        return 'inventory'
                    }
                    if (id.includes('/Modules/HR/')) {
                        return 'hr'
                    }
                    if (id.includes('/Modules/Manufacturing/')) {
                        return 'manufacturing'
                    }
                    if (id.includes('/Modules/BI/')) {
                        return 'bi'
                    }
                    if (id.includes('/Modules/POS/')) {
                        return 'pos'
                    }
                    if (id.includes('/Modules/')) {
                        // Catch-all for other modules
                        return 'modules-other'
                    }
                },
                chunkFileNames: 'js/[name]-[hash].js',
                entryFileNames: 'js/[name]-[hash].js',
                assetFileNames: 'assets/[name]-[hash][extname]',
            }
        },
        // Code splitting & optimization
        cssCodeSplit: true,
        chunkSizeWarningLimit: 500, // Warn if any chunk exceeds 500 KB
        cssMinify: 'lightningcss', // Faster CSS minification
        minify: 'terser', // Terser for better JS minification
        // Compression options for smaller downloads
        terserOptions: {
            compress: {
                drop_console: true, // Remove console.log in production
                drop_debugger: true,
            },
            mangle: true,
        },
    },
});
