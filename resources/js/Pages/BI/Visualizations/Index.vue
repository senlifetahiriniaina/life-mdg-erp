<template>
  <AppLayout>
    <Head title="Galerie de visualisations" />

    <div class="p-6">
      <!-- Header -->
      <div class="mb-6">
        <div class="flex items-center gap-3 mb-1">
          <a href="/bi" class="text-gray-400 hover:text-gray-600 dark:text-surface-300 dark:text-surface-300">
            <i class="pi pi-arrow-left text-sm" />
          </a>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-surface-50 dark:text-surface-50">Galerie de visualisations</h1>
        </div>
        <p class="text-gray-500 dark:text-surface-400 text-sm ml-8">
          Explorez les types de graphiques disponibles et leurs cas d'usage recommandés.
        </p>
      </div>

      <!-- Section Avancé -->
      <div class="mb-3 flex items-center gap-2">
        <span class="text-xs font-semibold uppercase tracking-widest text-gray-400">Graphiques avancés</span>
        <div class="flex-1 h-px bg-gray-100 dark:bg-surface-700" />
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-8">
        <!-- Heatmap -->
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl shadow-sm border border-gray-100 p-4">
          <h3 class="font-semibold text-gray-800 dark:text-surface-100 mb-1">Heatmap</h3>
          <p class="text-xs text-gray-500 dark:text-surface-400 mb-3">Intensité par matrice temps/jour</p>
          <VueApexCharts type="heatmap" height="120" :options="heatmapOptions" :series="heatmapSeries" />
          <div class="mt-2 flex gap-1 flex-wrap">
            <span class="text-xs bg-blue-50 dark:bg-surface-800 text-blue-700 px-2 py-0.5 rounded">Analytics</span>
            <span class="text-xs bg-blue-50 dark:bg-surface-800 text-blue-700 px-2 py-0.5 rounded">Temporel</span>
          </div>
        </div>

        <!-- Treemap -->
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl shadow-sm border border-gray-100 p-4">
          <h3 class="font-semibold text-gray-800 dark:text-surface-100 mb-1">Treemap</h3>
          <p class="text-xs text-gray-500 dark:text-surface-400 mb-3">Répartition hiérarchique par surface</p>
          <VueApexCharts type="treemap" height="120" :options="treemapOptions" :series="treemapSeries" />
          <div class="mt-2 flex gap-1 flex-wrap">
            <span class="text-xs bg-green-50 dark:bg-surface-800 text-green-700 px-2 py-0.5 rounded">CA</span>
            <span class="text-xs bg-green-50 dark:bg-surface-800 text-green-700 px-2 py-0.5 rounded">Répartition</span>
          </div>
        </div>

        <!-- Scatter -->
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl shadow-sm border border-gray-100 p-4">
          <h3 class="font-semibold text-gray-800 dark:text-surface-100 mb-1">Scatter Plot</h3>
          <p class="text-xs text-gray-500 dark:text-surface-400 mb-3">Corrélation entre deux variables</p>
          <VueApexCharts type="scatter" height="120" :options="scatterOptions" :series="scatterSeries" />
          <div class="mt-2 flex gap-1 flex-wrap">
            <span class="text-xs bg-purple-50 text-purple-700 px-2 py-0.5 rounded">Corrélation</span>
            <span class="text-xs bg-purple-50 text-purple-700 px-2 py-0.5 rounded">Clients</span>
          </div>
        </div>

        <!-- Funnel -->
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl shadow-sm border border-gray-100 p-4">
          <h3 class="font-semibold text-gray-800 dark:text-surface-100 mb-1">Funnel (Entonnoir)</h3>
          <p class="text-xs text-gray-500 dark:text-surface-400 mb-3">Étapes de conversion décroissantes</p>
          <VueApexCharts type="bar" height="120" :options="funnelOptions" :series="funnelSeries" />
          <div class="mt-2 flex gap-1 flex-wrap">
            <span class="text-xs bg-orange-50 text-orange-700 px-2 py-0.5 rounded">CRM</span>
            <span class="text-xs bg-orange-50 text-orange-700 px-2 py-0.5 rounded">Conversion</span>
          </div>
        </div>

        <!-- Waterfall -->
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl shadow-sm border border-gray-100 p-4">
          <h3 class="font-semibold text-gray-800 dark:text-surface-100 mb-1">Waterfall</h3>
          <p class="text-xs text-gray-500 dark:text-surface-400 mb-3">Variation cumulative positif/négatif</p>
          <VueApexCharts type="bar" height="120" :options="waterfallOptions" :series="waterfallSeries" />
          <div class="mt-2 flex gap-1 flex-wrap">
            <span class="text-xs bg-teal-50 text-teal-700 px-2 py-0.5 rounded">Trésorerie</span>
            <span class="text-xs bg-teal-50 text-teal-700 px-2 py-0.5 rounded">Finance</span>
          </div>
        </div>

        <!-- Radar -->
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl shadow-sm border border-gray-100 p-4">
          <h3 class="font-semibold text-gray-800 dark:text-surface-100 mb-1">Radar Chart</h3>
          <p class="text-xs text-gray-500 dark:text-surface-400 mb-3">Comparaison multi-axes par département</p>
          <VueApexCharts type="radar" height="120" :options="radarOptions" :series="radarSeries" />
          <div class="mt-2 flex gap-1 flex-wrap">
            <span class="text-xs bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded">KPI</span>
            <span class="text-xs bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded">Comparaison</span>
          </div>
        </div>

        <!-- Candlestick -->
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl shadow-sm border border-gray-100 p-4">
          <h3 class="font-semibold text-gray-800 dark:text-surface-100 mb-1">Candlestick</h3>
          <p class="text-xs text-gray-500 dark:text-surface-400 mb-3">Cours OHLC — change ou matières premières</p>
          <VueApexCharts type="candlestick" height="120" :options="candlestickOptions" :series="candlestickSeries" />
          <div class="mt-2 flex gap-1 flex-wrap">
            <span class="text-xs bg-yellow-50 text-yellow-700 px-2 py-0.5 rounded">Financier</span>
            <span class="text-xs bg-yellow-50 text-yellow-700 px-2 py-0.5 rounded">OHLC</span>
          </div>
        </div>

        <!-- Boxplot -->
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl shadow-sm border border-gray-100 p-4">
          <h3 class="font-semibold text-gray-800 dark:text-surface-100 mb-1">Boxplot</h3>
          <p class="text-xs text-gray-500 dark:text-surface-400 mb-3">Distribution statistique par catégorie</p>
          <VueApexCharts type="boxPlot" height="120" :options="boxplotOptions" :series="boxplotSeries" />
          <div class="mt-2 flex gap-1 flex-wrap">
            <span class="text-xs bg-red-50 dark:bg-surface-800 text-red-700 px-2 py-0.5 rounded">Distribution</span>
            <span class="text-xs bg-red-50 dark:bg-surface-800 text-red-700 px-2 py-0.5 rounded">Délais</span>
          </div>
        </div>
      </div>

      <!-- Call to action -->
      <div class="flex justify-center">
        <a
          href="/bi/builder"
          class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors"
        >
          <i class="pi pi-plus-circle text-sm" />
          Créer un dashboard avec ces graphiques
        </a>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { defineAsyncComponent } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

// Lazy load ApexCharts to reduce bundle size
const VueApexCharts = defineAsyncComponent(() => import('vue-apexcharts'))

// ── Shared chart base options ──────────────────────────────────────────────────
const baseOptions = {
  chart: { toolbar: { show: false }, background: 'transparent', animations: { enabled: false } },
  dataLabels: { enabled: false },
  legend: { show: false },
  grid: { show: false },
}

// ── 1. Heatmap — activité par heure/jour ──────────────────────────────────────
const heatmapOptions = {
  ...baseOptions,
  chart: { ...baseOptions.chart, type: 'heatmap' },
  colors: ['#2E5BE8'],
  xaxis: { categories: ['L', 'M', 'Me', 'J', 'V', 'S', 'D'], labels: { style: { fontSize: '10px' } } },
  yaxis: { labels: { style: { fontSize: '10px' } } },
  tooltip: { enabled: false },
}
const heatmapSeries = [
  { name: 'Matin', data: [{ x: 'L', y: 18 }, { x: 'M', y: 32 }, { x: 'Me', y: 45 }, { x: 'J', y: 60 }, { x: 'V', y: 55 }, { x: 'S', y: 10 }, { x: 'D', y: 3 }] },
  { name: 'AM', data: [{ x: 'L', y: 55 }, { x: 'M', y: 70 }, { x: 'Me', y: 80 }, { x: 'J', y: 90 }, { x: 'V', y: 75 }, { x: 'S', y: 20 }, { x: 'D', y: 5 }] },
  { name: 'Soir', data: [{ x: 'L', y: 30 }, { x: 'M', y: 25 }, { x: 'Me', y: 35 }, { x: 'J', y: 40 }, { x: 'V', y: 65 }, { x: 'S', y: 50 }, { x: 'D', y: 15 }] },
]

// ── 2. Treemap — répartition CA par région ────────────────────────────────────
const treemapOptions = {
  ...baseOptions,
  chart: { ...baseOptions.chart, type: 'treemap' },
  colors: ['#2E5BE8', '#16a34a', '#c2410c', '#7c3aed', '#0369a1'],
  tooltip: { enabled: false },
}
const treemapSeries = [{
  data: [
    { x: 'France', y: 420 },
    { x: 'Allemagne', y: 310 },
    { x: 'Espagne', y: 185 },
    { x: 'Italie', y: 145 },
    { x: 'Autres', y: 95 },
  ],
}]

// ── 3. Scatter — corrélation montant/nombre clients ───────────────────────────
const scatterOptions = {
  ...baseOptions,
  chart: { ...baseOptions.chart, type: 'scatter' },
  colors: ['#7c3aed'],
  xaxis: { title: { text: 'Clients', style: { fontSize: '9px' } }, tickAmount: 4, labels: { style: { fontSize: '9px' } } },
  yaxis: { title: { text: '€', style: { fontSize: '9px' } }, labels: { style: { fontSize: '9px' } } },
  tooltip: { enabled: false },
}
const scatterSeries = [{
  name: 'Commandes',
  data: [[10, 1200], [25, 3400], [40, 5600], [15, 2100], [60, 8900], [30, 4200], [50, 7100], [20, 2800], [35, 5000], [45, 6500]],
}]

// ── 4. Funnel — entonnoir de conversion (bar horizontal décroissant) ───────────
const funnelOptions = {
  ...baseOptions,
  chart: { ...baseOptions.chart, type: 'bar' },
  plotOptions: {
    bar: {
      horizontal: true,
      distributed: true,
      barHeight: '70%',
    },
  },
  colors: ['#2E5BE8', '#4f80f0', '#7ca3f5', '#a9c3f8', '#d4e1fc'],
  xaxis: { categories: ['Visiteurs', 'Leads', 'Prospects', 'Offres', 'Clients'], labels: { style: { fontSize: '9px' } } },
  yaxis: { labels: { style: { fontSize: '9px' } } },
  tooltip: { enabled: false },
}
const funnelSeries = [{ name: 'Conversion', data: [5000, 2400, 850, 320, 110] }]

// ── 5. Waterfall — variation de trésorerie ────────────────────────────────────
const waterfallOptions = {
  ...baseOptions,
  chart: { ...baseOptions.chart, type: 'bar' },
  plotOptions: {
    bar: {
      columnWidth: '70%',
      colors: {
        ranges: [
          { from: -100000, to: 0, color: '#ef4444' },
          { from: 0, to: 100000, color: '#22c55e' },
        ],
      },
    },
  },
  xaxis: { categories: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun'], labels: { style: { fontSize: '9px' } } },
  yaxis: { labels: { style: { fontSize: '9px' } } },
  tooltip: { enabled: false },
}
const waterfallSeries = [{ name: 'Trésorerie', data: [12000, -4500, 8200, -2100, 15000, -3400] }]

// ── 6. Radar — comparaison KPIs par département ───────────────────────────────
const radarOptions = {
  ...baseOptions,
  chart: { ...baseOptions.chart, type: 'radar' },
  colors: ['#2E5BE8', '#16a34a'],
  xaxis: { categories: ['Ventes', 'RH', 'Support', 'Prod.', 'Finance', 'IT'] },
  yaxis: { show: false },
  tooltip: { enabled: false },
}
const radarSeries = [
  { name: 'Q1 2026', data: [80, 65, 90, 75, 70, 85] },
  { name: 'Q4 2025', data: [70, 72, 78, 80, 65, 75] },
]

// ── 7. Candlestick — cours de change EUR/USD ──────────────────────────────────
const candlestickOptions = {
  ...baseOptions,
  chart: { ...baseOptions.chart, type: 'candlestick' },
  xaxis: {
    type: 'datetime',
    labels: { style: { fontSize: '9px' }, datetimeUTC: false },
  },
  yaxis: { labels: { style: { fontSize: '9px' }, formatter: (v) => v.toFixed(4) } },
  tooltip: { enabled: false },
}
const candlestickSeries = [{
  data: [
    { x: new Date('2026-04-28'), y: [1.0845, 1.0910, 1.0820, 1.0890] },
    { x: new Date('2026-04-29'), y: [1.0890, 1.0950, 1.0870, 1.0920] },
    { x: new Date('2026-04-30'), y: [1.0920, 1.0935, 1.0850, 1.0865] },
    { x: new Date('2026-05-01'), y: [1.0865, 1.0900, 1.0840, 1.0885] },
    { x: new Date('2026-05-02'), y: [1.0885, 1.0960, 1.0880, 1.0945] },
    { x: new Date('2026-05-05'), y: [1.0945, 1.0980, 1.0910, 1.0930] },
    { x: new Date('2026-05-06'), y: [1.0930, 1.0955, 1.0895, 1.0910] },
    { x: new Date('2026-05-07'), y: [1.0910, 1.0940, 1.0875, 1.0925] },
  ],
}]

// ── 8. Boxplot — distribution des délais de livraison ─────────────────────────
const boxplotOptions = {
  ...baseOptions,
  chart: { ...baseOptions.chart, type: 'boxPlot' },
  colors: ['#2E5BE8'],
  xaxis: { categories: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai'], labels: { style: { fontSize: '9px' } } },
  yaxis: { labels: { style: { fontSize: '9px' }, formatter: (v) => `${v}j` } },
  tooltip: { enabled: false },
}
const boxplotSeries = [{
  type: 'boxPlot',
  data: [
    { x: 'Jan', y: [2, 4, 5, 7, 12] },
    { x: 'Fév', y: [1, 3, 5, 6, 9] },
    { x: 'Mar', y: [3, 5, 7, 9, 15] },
    { x: 'Avr', y: [2, 4, 6, 8, 11] },
    { x: 'Mai', y: [1, 3, 4, 5, 8] },
  ],
}]
</script>
