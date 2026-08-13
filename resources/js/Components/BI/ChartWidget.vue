<template>
  <div class="chart-widget">
    <div v-if="title" class="chart-widget__head">
      <span class="chart-widget__title">{{ title }}</span>
      <slot name="actions" />
    </div>

    <div v-if="loading" class="chart-widget__skeleton" :style="{ height: height + 'px' }" />

    <div v-else-if="error" class="chart-widget__error">
      <i class="pi pi-exclamation-circle" style="font-size:18px" />
      <span>{{ error }}</span>
    </div>

    <div v-else-if="!series || !series.length || isSeriesEmpty" class="chart-widget__empty" :style="{ height: height + 'px' }">
      <i class="pi pi-chart-bar" style="font-size:24px;color:var(--fg-4,var(--fg-3))" />
      <span>Aucune donnée disponible</span>
    </div>

    <VueApexCharts
      v-else
      :type="type"
      :height="height"
      :options="mergedOptions"
      :series="series"
    />
  </div>
</template>

<script setup>
import { computed, defineAsyncComponent } from 'vue'

// Lazy load ApexCharts to keep bundle smaller
const VueApexCharts = defineAsyncComponent(() => import('vue-apexcharts'))

const props = defineProps({
  type:       { type: String,  default: 'bar' },   // bar | line | area | pie | donut | radialBar
  title:      { type: String,  default: '' },
  series:     { type: Array,   required: true },
  options:    { type: Object,  default: () => ({}) },
  height:     { type: Number,  default: 260 },
  colors:     { type: Array,   default: () => ['#2E5BE8', '#1F9D55', '#C99403', '#D6442C', '#6B7488'] },
  loading:    { type: Boolean, default: false },
  error:      { type: String,  default: '' },
})

const isSeriesEmpty = computed(() => {
  if (!props.series) return true
  // For pie/donut, series is a flat array of numbers
  if (['pie', 'donut', 'radialBar'].includes(props.type)) {
    return props.series.every(v => v === 0 || v === null || v === undefined)
  }
  // For other chart types, series is an array of { name, data }
  return props.series.every(s => !s.data || s.data.length === 0)
})

const baseOptions = computed(() => ({
  chart: {
    toolbar:    { show: false },
    fontFamily: 'var(--font-sans, system-ui)',
    background: 'transparent',
    animations: { enabled: true, speed: 400 },
  },
  theme:      { mode: document.documentElement.classList.contains('dark') ? 'dark' : 'light' },
  colors:     props.colors,
  grid:       { borderColor: 'var(--border-subtle, #e5e7eb)', strokeDashArray: 3 },
  xaxis:      { labels: { style: { colors: 'var(--fg-3, #6b7280)', fontSize: '11px' } }, axisBorder: { show: false } },
  yaxis:      { labels: { style: { colors: 'var(--fg-3, #6b7280)', fontSize: '11px' } } },
  legend:     { position: 'bottom', fontSize: '12px', fontFamily: 'var(--font-sans, system-ui)' },
  dataLabels: { enabled: false },
  stroke:     props.type === 'line' || props.type === 'area'
    ? { curve: 'smooth', width: 2 }
    : { width: 0 },
  plotOptions: props.type === 'bar'
    ? { bar: { borderRadius: 4, columnWidth: '60%' } }
    : {},
  fill: props.type === 'area'
    ? { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.03 } }
    : {},
}))

const mergedOptions = computed(() => {
  // Deep merge base options with provided overrides
  return mergeDeep(baseOptions.value, props.options)
})

function mergeDeep(target, source) {
  const out = { ...target }
  for (const key in source) {
    if (source[key] && typeof source[key] === 'object' && !Array.isArray(source[key])) {
      out[key] = mergeDeep(target[key] ?? {}, source[key])
    } else {
      out[key] = source[key]
    }
  }
  return out
}
</script>

<style scoped>
.chart-widget {
  display: flex;
  flex-direction: column;
}
.chart-widget__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}
.chart-widget__title {
  font-size: 13px;
  font-weight: 600;
  color: var(--fg-1);
}
.chart-widget__skeleton {
  background: var(--bg-sunken);
  border-radius: var(--r-md);
  animation: pulse 1.4s ease-in-out infinite;
}
.chart-widget__error {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 16px;
  color: var(--danger-fg);
  font-size: 13px;
}
.chart-widget__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  color: var(--fg-3);
  font-size: 13px;
}
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.5} }
</style>
