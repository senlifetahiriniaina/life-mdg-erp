<template>
  <AppLayout>
    <Head title="Cockpit Stratégique" />

    <div class="wh-page">
      <div class="wh-page-header">
        <div>
          <h1 class="wh-page-title">Cockpit Stratégique</h1>
          <p class="wh-page-subtitle">Ratios KPI, plan actif, alertes et recommandations IA en un coup d'œil</p>
        </div>
        <nav class="wh-subnav">
          <Link href="/strategy/plans" class="wh-subnav-link">Plans</Link>
          <Link href="/strategy/ratios" class="wh-subnav-link">Ratios</Link>
          <Link href="/strategy/benchmarks" class="wh-subnav-link">Benchmarks</Link>
          <Link href="/strategy/correlations" class="wh-subnav-link">Corrélations</Link>
          <Link href="/strategy/objectives" class="wh-subnav-link">Objectifs</Link>
          <Link href="/strategy/cascade" class="wh-subnav-link">Cascade</Link>
          <Link href="/strategy/sector-kpi" class="wh-subnav-link">KPI sectoriels</Link>
        </nav>
      </div>

      <AIAssistantPanel v-if="guidance" :guidance="guidance" />

      <!-- Active plan health -->
      <div class="wh-card" v-if="activePlan">
        <div class="wh-card-header">
          <div>
            <h2 class="wh-card-title">{{ activePlan.name }}</h2>
            <p class="wh-card-subtitle">{{ activePlan.vision || 'Plan stratégique actif' }}</p>
          </div>
          <Link :href="`/strategy/plans/${activePlan.id}`" class="wh-btn wh-btn-secondary">Voir le plan</Link>
        </div>
        <div class="wh-health-row">
          <div class="wh-health-score" :class="healthClass(activePlan.health_score)">
            {{ activePlan.health_score ?? 0 }}%
          </div>
          <ProgressBar :value="activePlan.health_score ?? 0" :showValue="false" style="height: 10px; flex: 1" />
        </div>
      </div>
      <div v-else class="wh-empty-state">Aucun plan stratégique actif. <Link href="/strategy/plans" class="wh-link">Créer un plan</Link></div>

      <!-- Alerts -->
      <div class="wh-card" v-if="alerts && alerts.length">
        <h2 class="wh-card-title">Alertes actives</h2>
        <ul class="wh-alert-list">
          <li v-for="alert in alerts" :key="alert.id" class="wh-alert-item" :class="`wh-alert-${alert.severity || 'warning'}`">
            <i class="pi pi-exclamation-triangle" />
            <span>{{ alert.message }}</span>
          </li>
        </ul>
      </div>

      <!-- Signals -->
      <div class="wh-card" v-if="signals && signals.length">
        <h2 class="wh-card-title">Signaux détectés</h2>
        <div class="wh-signal-grid">
          <div v-for="(signal, i) in signals" :key="i" class="wh-signal-card" :class="`wh-signal-${signal.type}`">
            <p class="wh-signal-title">{{ signal.title }}</p>
            <p class="wh-signal-desc">{{ signal.description }}</p>
            <p class="wh-signal-reco" v-if="signal.recommendation">→ {{ signal.recommendation }}</p>
          </div>
        </div>
      </div>

      <!-- Ratios by module -->
      <div class="wh-card">
        <h2 class="wh-card-title">Ratios stratégiques par module</h2>
        <div v-for="(moduleRatios, module) in ratios" :key="module" class="wh-ratio-module">
          <h3 class="wh-ratio-module-title">{{ module }}</h3>
          <div class="wh-ratio-grid">
            <div v-for="ratio in moduleRatios" :key="ratio.key" class="wh-ratio-tile" :class="`wh-rag-${ratio.status}`">
              <p class="wh-ratio-name">{{ ratio.name }}</p>
              <p class="wh-ratio-value">{{ formatValue(ratio.current_value, ratio.unit) }}</p>
              <p class="wh-ratio-benchmark" v-if="ratio.benchmark_value != null">
                Médiane secteur : {{ formatValue(ratio.benchmark_value, ratio.unit) }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- OKR tree summary -->
      <div class="wh-card" v-if="okrTree && okrTree.objectives && okrTree.objectives.length">
        <div class="wh-card-header">
          <h2 class="wh-card-title">Objectifs — {{ okrTree.plan_name }}</h2>
          <Link href="/strategy/objectives" class="wh-link">Voir tous les OKR →</Link>
        </div>
        <ul class="wh-okr-summary">
          <li v-for="obj in okrTree.objectives" :key="obj.id" class="wh-okr-summary-item">
            <span class="wh-okr-title">{{ obj.title }}</span>
            <ProgressBar :value="obj.progress ?? 0" :showValue="true" style="height: 8px; width: 160px" />
          </li>
        </ul>
      </div>

      <!-- Correlations -->
      <div class="wh-card" v-if="correlations && correlations.length">
        <div class="wh-card-header">
          <h2 class="wh-card-title">Corrélations clés</h2>
          <Link href="/strategy/correlations" class="wh-link">Voir la matrice →</Link>
        </div>
        <ul class="wh-corr-list">
          <li v-for="(c, i) in correlations" :key="i" class="wh-corr-item">
            <span class="wh-corr-pair">{{ c.kpi_a }} ↔ {{ c.kpi_b }}</span>
            <span class="wh-corr-coef" :class="c.coefficient >= 0 ? 'wh-corr-pos' : 'wh-corr-neg'">
              r = {{ formatCoef(c.coefficient) }}
            </span>
          </li>
        </ul>
      </div>

      <!-- AI recommendations -->
      <div class="wh-card" v-if="aiRecommendations">
        <h2 class="wh-card-title">
          Recommandations IA
          <span v-if="!aiRecommendations.enabled" class="wh-badge wh-badge-slate">Mode hors-ligne</span>
        </h2>
        <div class="wh-ai-columns">
          <div v-if="aiRecommendations.recommendations?.length">
            <h3 class="wh-ai-col-title">Recommandations</h3>
            <ul class="wh-ai-list">
              <li v-for="(r, i) in aiRecommendations.recommendations" :key="i">
                <strong>{{ typeof r === 'string' ? r : r.title }}</strong>
                <span v-if="typeof r === 'object' && r.description"> — {{ r.description }}</span>
              </li>
            </ul>
          </div>
          <div v-if="aiRecommendations.risks?.length">
            <h3 class="wh-ai-col-title">Risques</h3>
            <ul class="wh-ai-list wh-ai-list-risk">
              <li v-for="(r, i) in aiRecommendations.risks" :key="i">{{ typeof r === 'string' ? r : r.title }}</li>
            </ul>
          </div>
          <div v-if="aiRecommendations.opportunities?.length">
            <h3 class="wh-ai-col-title">Opportunités</h3>
            <ul class="wh-ai-list wh-ai-list-opp">
              <li v-for="(r, i) in aiRecommendations.opportunities" :key="i">{{ typeof r === 'string' ? r : r.title }}</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import ProgressBar from 'primevue/progressbar'
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'

defineProps({
  ratios: { type: Object, default: () => ({}) },
  activePlan: { type: Object, default: null },
  okrTree: { type: Object, default: () => ({}) },
  alerts: { type: Array, default: () => [] },
  correlations: { type: Array, default: () => [] },
  signals: { type: Array, default: () => [] },
  aiRecommendations: { type: Object, default: null },
})

const { guidance } = useAiAssistant('Strategy', 'view_dashboard')

function healthClass(score) {
  if (score >= 70) return 'wh-health-green'
  if (score >= 40) return 'wh-health-amber'
  return 'wh-health-red'
}

function formatValue(value, unit) {
  if (value == null) return '—'
  const n = Number(value)
  const formatted = unit === 'XOF' || unit === 'XOF/jour'
    ? new Intl.NumberFormat('fr-FR').format(Math.round(n))
    : (Number.isInteger(n) ? n : n.toFixed(1))
  return `${formatted} ${unit || ''}`.trim()
}

function formatCoef(c) {
  const n = Number(c)
  return (n >= 0 ? '+' : '') + n.toFixed(2)
}
</script>

<style scoped>
.wh-page { max-width: 1200px; margin: 0 auto; padding: 24px; display: flex; flex-direction: column; gap: 20px; }
.wh-page-header { display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
.wh-page-title { font-size: 22px; font-weight: 700; margin: 0; color: #111827; }
.wh-page-subtitle { font-size: 13px; color: #6B7280; margin-top: 2px; }
.wh-subnav { display: flex; gap: 4px; flex-wrap: wrap; }
.wh-subnav-link { padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 500; color: #4B5563; background: #F3F4F6; text-decoration: none; }
.wh-subnav-link:hover { background: #E5E7EB; }
.wh-card { background: #fff; border: 1px solid #E5E7EB; border-radius: 10px; padding: 18px 20px; }
.wh-card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.wh-card-title { font-size: 16px; font-weight: 700; color: #111827; margin: 0 0 4px; display: flex; align-items: center; gap: 8px; }
.wh-card-subtitle { font-size: 12px; color: #6B7280; margin: 0; }
.wh-link { font-size: 13px; color: #2563EB; text-decoration: none; font-weight: 500; }
.wh-link:hover { text-decoration: underline; }
.wh-health-row { display: flex; align-items: center; gap: 16px; margin-top: 8px; }
.wh-health-score { font-size: 28px; font-weight: 800; width: 80px; text-align: center; }
.wh-health-green { color: #16A34A; }
.wh-health-amber { color: #D97706; }
.wh-health-red { color: #DC2626; }
.wh-empty-state { text-align: center; padding: 24px; color: #9CA3AF; font-size: 13px; background: #fff; border: 1px dashed #D1D5DB; border-radius: 10px; }
.wh-alert-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
.wh-alert-item { display: flex; align-items: center; gap: 8px; padding: 10px 12px; border-radius: 8px; font-size: 13px; }
.wh-alert-warning { background: #FEF3C7; color: #92400E; }
.wh-alert-critical { background: #FEE2E2; color: #991B1B; }
.wh-alert-info { background: #DBEAFE; color: #1E40AF; }
.wh-signal-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 12px; }
.wh-signal-card { border-radius: 8px; padding: 12px; border-left: 3px solid #9CA3AF; background: #F9FAFB; }
.wh-signal-warning { border-color: #D97706; background: #FFFBEB; }
.wh-signal-critical { border-color: #DC2626; background: #FEF2F2; }
.wh-signal-title { font-size: 13px; font-weight: 700; margin: 0 0 4px; color: #111827; }
.wh-signal-desc { font-size: 12px; color: #4B5563; margin: 0 0 4px; }
.wh-signal-reco { font-size: 12px; color: #2563EB; margin: 0; }
.wh-ratio-module { margin-bottom: 16px; }
.wh-ratio-module:last-child { margin-bottom: 0; }
.wh-ratio-module-title { font-size: 13px; font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: 0.03em; margin: 0 0 8px; }
.wh-ratio-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px; }
.wh-ratio-tile { border-radius: 8px; padding: 10px 12px; border-left: 4px solid #9CA3AF; background: #F9FAFB; }
.wh-rag-green { border-color: #16A34A; }
.wh-rag-amber { border-color: #D97706; }
.wh-rag-red { border-color: #DC2626; }
.wh-ratio-name { font-size: 12px; color: #6B7280; margin: 0 0 2px; }
.wh-ratio-value { font-size: 16px; font-weight: 700; color: #111827; margin: 0; }
.wh-ratio-benchmark { font-size: 11px; color: #9CA3AF; margin: 4px 0 0; }
.wh-okr-summary { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
.wh-okr-summary-item { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.wh-okr-title { font-size: 13px; color: #374151; }
.wh-corr-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; }
.wh-corr-item { display: flex; justify-content: space-between; font-size: 13px; padding: 6px 0; border-bottom: 1px solid #F3F4F6; }
.wh-corr-pair { color: #374151; }
.wh-corr-coef { font-weight: 700; }
.wh-corr-pos { color: #16A34A; }
.wh-corr-neg { color: #DC2626; }
.wh-badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 20px; font-size: 10px; font-weight: 600; }
.wh-badge-slate { background: #F3F4F6; color: #6B7280; }
.wh-ai-columns { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
.wh-ai-col-title { font-size: 12px; font-weight: 700; text-transform: uppercase; color: #6B7280; margin: 0 0 6px; }
.wh-ai-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; font-size: 13px; color: #374151; }
.wh-ai-list-risk li { color: #B91C1C; }
.wh-ai-list-opp li { color: #047857; }
.wh-btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px; border-radius: 7px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; text-decoration: none; }
.wh-btn-secondary { background: #F3F4F6; color: #374151; border: 1px solid #E5E7EB; }
</style>
