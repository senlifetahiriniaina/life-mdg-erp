/**
 * Lazy-loaded chart utilities
 * Splits chart libraries into separate chunks to reduce main bundle size
 */

import { defineAsyncComponent } from 'vue'

// Lazy-load PrimeVue Chart component
export const LazyChart = defineAsyncComponent(() =>
  import('primevue/chart').then(m => ({ default: m.default }))
)

// Lazy-load ApexCharts for BI module
export const LazyApexCharts = defineAsyncComponent(() =>
  import('vue3-apexcharts').then(m => ({
    default: m.default,
    VueApexCharts: m.default
  }))
)

// Pre-fetch chart libraries when user navigates to analytics pages
export function preloadCharts() {
  // Dynamic import without defining a component - just loads the library
  import('apexcharts')
  import('vue3-apexcharts')
}

// Composable for using lazy charts with suspense
export function useLazyChart() {
  return {
    LazyChart,
    LazyApexCharts,
    preloadCharts
  }
}
