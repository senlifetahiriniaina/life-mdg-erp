<template>
  <AppLayout>
    <Head title="Corrélations KPI" />

    <div class="wh-page">
      <div class="wh-page-header">
        <div>
          <Link href="/strategy" class="wh-back-link">← Cockpit</Link>
          <h1 class="wh-page-title">Corrélations entre KPI</h1>
          <p class="wh-page-subtitle">Coefficient de Pearson entre indicateurs cross-module, base de connaissance SME pré-alimentée</p>
        </div>
      </div>

      <div class="wh-columns">
        <div class="wh-card">
          <h2 class="wh-card-title wh-card-title-pos">Corrélations positives</h2>
          <ul v-if="topPositive.length" class="wh-corr-list">
            <li v-for="(c, i) in topPositive" :key="i" class="wh-corr-item">
              <div class="wh-corr-pair-row">
                <span class="wh-corr-pair">{{ c.kpi_a }}</span>
                <span class="wh-corr-arrow">↔</span>
                <span class="wh-corr-pair">{{ c.kpi_b }}</span>
              </div>
              <div class="wh-corr-bar-row">
                <div class="wh-corr-bar-track">
                  <div class="wh-corr-bar-fill wh-corr-bar-pos" :style="`width:${Math.abs(c.coefficient) * 100}%`" />
                </div>
                <span class="wh-corr-coef wh-corr-pos">r = {{ formatCoef(c.coefficient) }}</span>
              </div>
              <p v-if="c.interpretation" class="wh-corr-interp">{{ c.interpretation }}</p>
              <p v-if="c.lag_periods || c.lag" class="wh-corr-lag">Décalage : {{ c.lag_periods ?? c.lag }} période(s)</p>
            </li>
          </ul>
          <p v-else class="wh-empty-inline">Aucune corrélation positive.</p>
        </div>

        <div class="wh-card">
          <h2 class="wh-card-title wh-card-title-neg">Corrélations négatives</h2>
          <ul v-if="topNegative.length" class="wh-corr-list">
            <li v-for="(c, i) in topNegative" :key="i" class="wh-corr-item">
              <div class="wh-corr-pair-row">
                <span class="wh-corr-pair">{{ c.kpi_a }}</span>
                <span class="wh-corr-arrow">↔</span>
                <span class="wh-corr-pair">{{ c.kpi_b }}</span>
              </div>
              <div class="wh-corr-bar-row">
                <div class="wh-corr-bar-track">
                  <div class="wh-corr-bar-fill wh-corr-bar-neg" :style="`width:${Math.abs(c.coefficient) * 100}%`" />
                </div>
                <span class="wh-corr-coef wh-corr-neg">r = {{ formatCoef(c.coefficient) }}</span>
              </div>
              <p v-if="c.interpretation" class="wh-corr-interp">{{ c.interpretation }}</p>
              <p v-if="c.lag_periods || c.lag" class="wh-corr-lag">Décalage : {{ c.lag_periods ?? c.lag }} période(s)</p>
            </li>
          </ul>
          <p v-else class="wh-empty-inline">Aucune corrélation négative.</p>
        </div>
      </div>

      <!-- Full matrix -->
      <div class="wh-card" v-if="matrix.all && matrix.all.length">
        <h2 class="wh-card-title">Matrice complète</h2>
        <div class="wh-table-wrap">
          <table class="wh-table">
            <thead>
              <tr>
                <th>KPI A</th>
                <th>KPI B</th>
                <th>Coefficient</th>
                <th>Force</th>
                <th>Confiance</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(c, i) in matrix.all" :key="i">
                <td>{{ c.kpi_a }}</td>
                <td>{{ c.kpi_b }}</td>
                <td :class="c.coefficient >= 0 ? 'wh-corr-pos' : 'wh-corr-neg'">{{ formatCoef(c.coefficient) }}</td>
                <td>{{ strengthLabel(c.coefficient) }}</td>
                <td>{{ c.confidence != null ? `${c.confidence}%` : '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineProps({
  matrix: { type: Object, default: () => ({ positive: [], negative: [], all: [] }) },
  topPositive: { type: Array, default: () => [] },
  topNegative: { type: Array, default: () => [] },
})

function formatCoef(c) {
  const n = Number(c)
  return (n >= 0 ? '+' : '') + n.toFixed(2)
}

function strengthLabel(c) {
  const abs = Math.abs(Number(c))
  if (abs >= 0.8) return 'Très forte'
  if (abs >= 0.6) return 'Forte'
  if (abs >= 0.4) return 'Modérée'
  if (abs >= 0.2) return 'Faible'
  return 'Négligeable'
}
</script>

<style scoped>
.wh-page { max-width: 1100px; margin: 0 auto; padding: 24px; display: flex; flex-direction: column; gap: 20px; }
.wh-page-header { display: flex; align-items: flex-start; justify-content: space-between; }
.wh-back-link { font-size: 12px; color: #6B7280; text-decoration: none; }
.wh-page-title { font-size: 22px; font-weight: 700; margin: 6px 0 2px; color: #111827; }
.wh-page-subtitle { font-size: 13px; color: #6B7280; margin: 0; }
.wh-columns { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
@media (max-width: 800px) { .wh-columns { grid-template-columns: 1fr; } }
.wh-card { background: #fff; border: 1px solid #E5E7EB; border-radius: 10px; padding: 18px 20px; }
.wh-card-title { font-size: 15px; font-weight: 700; margin: 0 0 12px; color: #111827; }
.wh-card-title-pos { color: #047857; }
.wh-card-title-neg { color: #B91C1C; }
.wh-corr-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 14px; }
.wh-corr-item { border-bottom: 1px solid #F3F4F6; padding-bottom: 12px; }
.wh-corr-item:last-child { border-bottom: none; padding-bottom: 0; }
.wh-corr-pair-row { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #374151; margin-bottom: 6px; flex-wrap: wrap; }
.wh-corr-pair { font-weight: 600; }
.wh-corr-arrow { color: #9CA3AF; }
.wh-corr-bar-row { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
.wh-corr-bar-track { flex: 1; height: 6px; background: #F3F4F6; border-radius: 3px; overflow: hidden; }
.wh-corr-bar-fill { height: 100%; }
.wh-corr-bar-pos { background: #16A34A; }
.wh-corr-bar-neg { background: #DC2626; }
.wh-corr-coef { font-size: 12px; font-weight: 700; width: 56px; text-align: right; }
.wh-corr-pos { color: #16A34A; }
.wh-corr-neg { color: #DC2626; }
.wh-corr-interp { font-size: 12px; color: #6B7280; margin: 0 0 4px; }
.wh-corr-lag { font-size: 11px; color: #9CA3AF; margin: 0; }
.wh-empty-inline { font-size: 13px; color: #9CA3AF; text-align: center; padding: 20px; }
.wh-table-wrap { overflow-x: auto; }
.wh-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.wh-table th { text-align: left; font-size: 11px; text-transform: uppercase; color: #6B7280; padding: 10px 12px; border-bottom: 1px solid #E5E7EB; background: #F9FAFB; }
.wh-table td { padding: 10px 12px; border-bottom: 1px solid #E5E7EB; }
</style>
