<template>
  <AppLayout>
    <Head title="Ratios stratégiques" />

    <div class="wh-page">
      <div class="wh-page-header">
        <div>
          <Link href="/strategy" class="wh-back-link">← Cockpit</Link>
          <h1 class="wh-page-title">Ratios stratégiques</h1>
          <p class="wh-page-subtitle">Valeur actuelle vs benchmarks P25 / Médiane / P75, tendance sur 6 mois</p>
        </div>
        <select v-model="moduleFilter" class="wh-input" @change="applyModuleFilter">
          <option value="">Tous les modules</option>
          <option v-for="m in modules" :key="m" :value="m">{{ m }}</option>
        </select>
      </div>

      <div v-for="(moduleRatios, module) in displayedRatios" :key="module" class="wh-card">
        <h2 class="wh-card-title">{{ module }}</h2>
        <div class="wh-ratio-list">
          <div v-for="ratio in moduleRatios" :key="ratio.key" class="wh-ratio-row">
            <div class="wh-ratio-row-header">
              <div>
                <p class="wh-ratio-name">{{ ratio.name }}</p>
                <p class="wh-ratio-formula">{{ ratio.formula }}</p>
              </div>
              <div class="wh-ratio-values">
                <span class="wh-ratio-current" :class="`wh-rag-text-${ratio.status}`">{{ formatValue(ratio.current_value, ratio.unit) }}</span>
                <span v-if="ratio.percentile != null" class="wh-badge wh-badge-slate">P{{ ratio.percentile }}</span>
              </div>
            </div>

            <!-- Benchmark bar: P25 — median — P75 with current value marker -->
            <div v-if="ratio.benchmark_p25 != null && ratio.benchmark_p75 != null" class="wh-benchmark-bar">
              <div class="wh-benchmark-track">
                <div class="wh-benchmark-fill" :style="benchmarkFillStyle(ratio)" :class="`wh-rag-bg-${ratio.status}`" />
                <div
                  v-if="ratio.benchmark_value != null"
                  class="wh-benchmark-median-tick"
                  :style="`left:${positionPct(ratio.benchmark_value, ratio)}%`"
                  title="Médiane secteur"
                />
              </div>
              <div class="wh-benchmark-labels">
                <span>P25 : {{ formatValue(ratio.benchmark_p25, ratio.unit) }}</span>
                <span>Médiane : {{ formatValue(ratio.benchmark_value, ratio.unit) }}</span>
                <span>P75 : {{ formatValue(ratio.benchmark_p75, ratio.unit) }}</span>
              </div>
            </div>
            <p v-else class="wh-no-benchmark">Aucun benchmark disponible pour ce ratio.</p>

            <!-- Trend sparkline -->
            <svg v-if="ratio.trend && ratio.trend.length > 1" class="wh-sparkline" :viewBox="`0 0 ${ratio.trend.length * 20} 40`" preserveAspectRatio="none">
              <polyline
                :points="sparklinePoints(ratio.trend)"
                fill="none"
                :stroke="sparklineColor(ratio)"
                stroke-width="2"
              />
            </svg>
          </div>
        </div>
      </div>

      <div v-if="!Object.keys(displayedRatios).length" class="wh-empty-state">Aucun ratio pour ce filtre.</div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  ratios: { type: Object, default: () => ({}) },
  modules: { type: Array, default: () => [] },
  selectedModule: { type: String, default: null },
  filters: { type: Object, default: () => ({}) },
})

const moduleFilter = ref(props.selectedModule || '')

const displayedRatios = computed(() => props.ratios || {})

function applyModuleFilter() {
  router.get('/strategy/ratios', moduleFilter.value ? { module: moduleFilter.value } : {}, {
    preserveState: true,
    preserveScroll: true,
  })
}

function formatValue(value, unit) {
  if (value == null) return '—'
  const n = Number(value)
  const formatted = unit === 'XOF' || unit === 'XOF/jour'
    ? new Intl.NumberFormat('fr-FR').format(Math.round(n))
    : (Number.isInteger(n) ? n : n.toFixed(1))
  return `${formatted} ${unit || ''}`.trim()
}

function positionPct(value, ratio) {
  const p25 = Number(ratio.benchmark_p25)
  const p75 = Number(ratio.benchmark_p75)
  if (p75 === p25) return 50
  const pct = ((Number(value) - p25) / (p75 - p25)) * 100
  return Math.max(0, Math.min(100, pct))
}

function benchmarkFillStyle(ratio) {
  const pct = positionPct(ratio.current_value, ratio)
  return `width:${pct}%`
}

function sparklinePoints(trend) {
  const min = Math.min(...trend)
  const max = Math.max(...trend)
  const range = max - min || 1
  return trend.map((v, i) => {
    const x = i * 20 + 10
    const y = 36 - ((v - min) / range) * 32
    return `${x},${y}`
  }).join(' ')
}

function sparklineColor(ratio) {
  return { green: '#16A34A', amber: '#D97706', red: '#DC2626' }[ratio.status] ?? '#9CA3AF'
}
</script>

<style scoped>
.wh-page { max-width: 1000px; margin: 0 auto; padding: 24px; display: flex; flex-direction: column; gap: 20px; }
.wh-page-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.wh-back-link { font-size: 12px; color: #6B7280; text-decoration: none; }
.wh-page-title { font-size: 22px; font-weight: 700; margin: 6px 0 2px; color: #111827; }
.wh-page-subtitle { font-size: 13px; color: #6B7280; margin: 0; }
.wh-input { padding: 7px 10px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px; }
.wh-card { background: #fff; border: 1px solid #E5E7EB; border-radius: 10px; padding: 18px 20px; }
.wh-card-title { font-size: 15px; font-weight: 700; margin: 0 0 12px; color: #111827; }
.wh-ratio-list { display: flex; flex-direction: column; gap: 16px; }
.wh-ratio-row { border-bottom: 1px solid #F3F4F6; padding-bottom: 14px; }
.wh-ratio-row:last-child { border-bottom: none; padding-bottom: 0; }
.wh-ratio-row-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 8px; }
.wh-ratio-name { font-size: 13px; font-weight: 700; color: #111827; margin: 0; }
.wh-ratio-formula { font-size: 11px; color: #9CA3AF; margin: 2px 0 0; font-family: monospace; }
.wh-ratio-values { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.wh-ratio-current { font-size: 16px; font-weight: 700; }
.wh-rag-text-green { color: #16A34A; }
.wh-rag-text-amber { color: #D97706; }
.wh-rag-text-red { color: #DC2626; }
.wh-benchmark-bar { margin-bottom: 8px; }
.wh-benchmark-track { position: relative; height: 8px; background: #F3F4F6; border-radius: 4px; overflow: visible; }
.wh-benchmark-fill { position: absolute; top: 0; left: 0; height: 100%; border-radius: 4px; }
.wh-rag-bg-green { background: #16A34A; }
.wh-rag-bg-amber { background: #D97706; }
.wh-rag-bg-red { background: #DC2626; }
.wh-benchmark-median-tick { position: absolute; top: -3px; width: 2px; height: 14px; background: #374151; }
.wh-benchmark-labels { display: flex; justify-content: space-between; font-size: 10px; color: #9CA3AF; margin-top: 4px; }
.wh-no-benchmark { font-size: 11px; color: #9CA3AF; font-style: italic; margin: 0 0 8px; }
.wh-sparkline { width: 100%; height: 32px; }
.wh-badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 20px; font-size: 10px; font-weight: 600; }
.wh-badge-slate { background: #F3F4F6; color: #6B7280; }
.wh-empty-state { text-align: center; padding: 40px; color: #9CA3AF; font-size: 13px; background: #fff; border: 1px dashed #D1D5DB; border-radius: 10px; }
</style>
