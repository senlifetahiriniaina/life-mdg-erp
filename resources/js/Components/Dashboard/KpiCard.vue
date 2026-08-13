<template>
  <div :class="['dash-kpi-card', `dash-kpi--${severity}`]">
    <div class="dash-kpi-icon">
      <i :class="icon" />
    </div>
    <div class="dash-kpi-body">
      <div class="dash-kpi-label">{{ title }}</div>
      <div class="dash-kpi-value font-display">
        <template v-if="loading">
          <div class="dash-kpi-skeleton" />
        </template>
        <template v-else>
          <span ref="valueEl">{{ displayValue }}</span>
        </template>
      </div>
      <div v-if="trend !== undefined && !loading" :class="['dash-kpi-trend', trend > 0 ? 'up' : trend < 0 ? 'down' : 'neutral']">
        <i v-if="trend !== 0" :class="['pi', trend > 0 ? 'pi-arrow-up' : 'pi-arrow-down']" style="font-size: 10px" />
        <span>{{ Math.abs(trend) }}%</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted, computed } from 'vue'

const props = withDefaults(defineProps<{
  title:     string
  value:     number | string
  icon?:     string
  trend?:    number
  severity?: 'success' | 'warning' | 'danger' | 'info' | 'default'
  loading?:  boolean
  format?:   'number' | 'currency' | 'percent' | 'raw'
}>(), {
  icon:     'pi pi-chart-bar',
  severity: 'default',
  loading:  false,
  format:   'raw',
})

const animatedValue = ref(0)

const displayValue = computed(() => {
  const v = props.value
  if (typeof v === 'string') return v
  if (props.format === 'currency') return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(animatedValue.value)
  if (props.format === 'percent') return `${Math.round(animatedValue.value)}%`
  if (props.format === 'number') return new Intl.NumberFormat('fr-FR').format(Math.round(animatedValue.value))
  return typeof props.value === 'number' ? Math.round(animatedValue.value).toLocaleString('fr-FR') : props.value
})

function animateTo(target: number) {
  const start = animatedValue.value
  const diff = target - start
  const duration = 600
  const startTime = performance.now()

  function step(now: number) {
    const elapsed = now - startTime
    const progress = Math.min(elapsed / duration, 1)
    const ease = 1 - Math.pow(1 - progress, 3)
    animatedValue.value = start + diff * ease
    if (progress < 1) requestAnimationFrame(step)
    else animatedValue.value = target
  }

  requestAnimationFrame(step)
}

onMounted(() => {
  if (typeof props.value === 'number') animateTo(props.value)
  else animatedValue.value = 0
})

watch(() => props.value, (v) => {
  if (typeof v === 'number') animateTo(v)
})
</script>

<style scoped>
.dash-kpi-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 18px 20px;
  border-radius: var(--r-lg);
  background: var(--bg-canvas);
  border: 1px solid var(--border-subtle);
  transition: box-shadow var(--dur-fast);
}
.dash-kpi-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.06); }

.dash-kpi-icon {
  width: 44px; height: 44px; border-radius: var(--r-md);
  display: flex; align-items: center; justify-content: center;
  font-size: 18px; flex-shrink: 0;
}
.dash-kpi--default .dash-kpi-icon  { background: var(--halo-50);  color: var(--halo-600); }
.dash-kpi--success .dash-kpi-icon  { background: var(--success-bg);         color: var(--green-500); }
.dash-kpi--warning .dash-kpi-icon  { background: var(--warn-bg);         color: #ca8a04; }
.dash-kpi--danger  .dash-kpi-icon  { background: var(--danger-bg);         color: var(--red-500); }
.dash-kpi--info    .dash-kpi-icon  { background: var(--halo-100);         color: var(--halo-500); }

.dash-kpi-body { flex: 1; min-width: 0; }
.dash-kpi-label { font-size: 12px; color: var(--fg-3); font-weight: 500; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 4px; }
.dash-kpi-value { font-size: 24px; font-weight: 700; color: var(--fg-1); font-variant-numeric: tabular-nums; line-height: 1.1; }
.dash-kpi-skeleton { height: 28px; width: 80px; border-radius: var(--r-sm); background: var(--bg-sunken); }

.dash-kpi-trend { font-size: 11px; font-weight: 600; margin-top: 4px; display: flex; align-items: center; gap: 3px; }
.dash-kpi-trend.up      { color: var(--green-500); }
.dash-kpi-trend.down    { color: var(--red-500); }
.dash-kpi-trend.neutral { color: var(--fg-3); }
</style>
