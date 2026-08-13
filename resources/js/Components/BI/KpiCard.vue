<template>
  <div class="kpi-card" :class="{ 'kpi-card--alert': isAlert }">
    <div class="kpi-card__header">
      <span class="kpi-card__label">{{ label }}</span>
      <span v-if="unit" class="kpi-card__unit">{{ unit }}</span>
    </div>

    <div class="kpi-card__value">{{ formattedValue }}</div>

    <div v-if="trend !== undefined" class="kpi-card__trend" :class="trendClass">
      <i :class="trendIcon" style="font-size:10px" />
      <span>{{ trendLabel }}</span>
    </div>

    <div v-if="threshold !== undefined" class="kpi-card__threshold">
      <span>Seuil : {{ threshold }}{{ unit ? ' ' + unit : '' }}</span>
      <span v-if="isAlert" class="kpi-card__alert-badge">
        <i class="pi pi-exclamation-triangle" style="font-size:10px" />
        Alerte
      </span>
    </div>

    <!-- Sparkline mini chart -->
    <div v-if="sparkline && sparkline.length" class="kpi-card__sparkline">
      <VueApexCharts
        type="line"
        height="40"
        :options="sparklineOptions"
        :series="sparklineSeries"
      />
    </div>
  </div>
</template>

<script setup>
import { computed, defineAsyncComponent } from 'vue'

// Lazy load ApexCharts sparklines
const VueApexCharts = defineAsyncComponent(() => import('vue-apexcharts'))

const props = defineProps({
  label:     { type: String,  required: true },
  value:     { type: [Number, String], required: true },
  unit:      { type: String,  default: '' },
  trend:     { type: Number,  default: undefined },
  threshold: { type: Number,  default: undefined },
  sparkline: { type: Array,   default: () => [] },
  format:    { type: String,  default: 'raw' }, // raw | number | currency | percent
})

const formattedValue = computed(() => {
  const v = props.value
  if (typeof v === 'string') return v
  switch (props.format) {
    case 'currency': return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(v)
    case 'percent':  return `${v}%`
    case 'number':   return new Intl.NumberFormat('fr-FR').format(v)
    default:         return v !== null && v !== undefined ? String(v) : '—'
  }
})

const isAlert = computed(() =>
  props.threshold !== undefined && typeof props.value === 'number' && props.value < props.threshold
)

const trendClass = computed(() => {
  if (props.trend === undefined) return ''
  if (props.trend > 0) return 'kpi-card__trend--up'
  if (props.trend < 0) return 'kpi-card__trend--down'
  return 'kpi-card__trend--stable'
})

const trendIcon = computed(() => {
  if (props.trend === undefined) return 'pi pi-minus'
  if (props.trend > 0) return 'pi pi-arrow-up'
  if (props.trend < 0) return 'pi pi-arrow-down'
  return 'pi pi-minus'
})

const trendLabel = computed(() => {
  if (props.trend === undefined) return ''
  const abs = Math.abs(props.trend)
  if (props.trend > 0) return `+${abs}%`
  if (props.trend < 0) return `-${abs}%`
  return 'Stable'
})

const sparklineSeries = computed(() => [{
  name: props.label,
  data: props.sparkline,
}])

const sparklineOptions = {
  chart: {
    type: 'line',
    sparkline: { enabled: true },
    toolbar: { show: false },
    animations: { enabled: false },
  },
  stroke:     { curve: 'smooth', width: 2 },
  colors:     ['var(--halo-500, #2E5BE8)'],
  tooltip:    { enabled: false },
  grid:       { show: false },
  xaxis:      { labels: { show: false }, axisBorder: { show: false }, axisTicks: { show: false } },
  yaxis:      { labels: { show: false } },
  dataLabels: { enabled: false },
}
</script>

<style scoped>
.kpi-card {
  background: var(--bg-canvas);
  border: 1px solid var(--border-subtle);
  border-radius: var(--r-lg);
  padding: 16px 18px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  transition: border-color var(--dur-fast);
}
.kpi-card--alert {
  border-color: var(--red-400, #f87171);
  background: var(--danger-bg, #fff1f0);
}
.kpi-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.kpi-card__label {
  font-size: 11px;
  font-weight: 600;
  color: var(--fg-3);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.kpi-card__unit {
  font-size: 11px;
  color: var(--fg-4, var(--fg-3));
}
.kpi-card__value {
  font-size: 28px;
  font-weight: 700;
  color: var(--fg-1);
  line-height: 1;
  font-family: var(--font-display);
}
.kpi-card__trend {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  font-weight: 500;
  width: fit-content;
  padding: 2px 7px;
  border-radius: var(--r-pill);
}
.kpi-card__trend--up    { background: var(--success-bg); color: var(--success-fg); }
.kpi-card__trend--down  { background: var(--danger-bg);  color: var(--danger-fg);  }
.kpi-card__trend--stable{ background: var(--bg-sunken);  color: var(--fg-2);       }
.kpi-card__threshold {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 11px;
  color: var(--fg-3);
}
.kpi-card__alert-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 10px;
  font-weight: 600;
  padding: 1px 6px;
  border-radius: var(--r-pill);
  background: var(--danger-bg);
  color: var(--danger-fg);
}
.kpi-card__sparkline {
  margin-top: 4px;
}
</style>
