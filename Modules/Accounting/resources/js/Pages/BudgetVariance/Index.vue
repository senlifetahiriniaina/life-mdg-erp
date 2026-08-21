<template>
  <AppLayout>
    <Head title="Analyse des écarts budgétaires" />

    <div class="wh-page">
      <div class="wh-page-header">
        <div>
          <h1 class="wh-page-title">Analyse des écarts budgétaires</h1>
          <p class="wh-page-subtitle">Budget vs. réalisé, tendance mensuelle, top écarts</p>
        </div>
      </div>

      <div class="wh-filters">
        <select v-model.number="selectedBudgetId" class="wh-input" @change="loadBudgetViews">
          <option :value="null" disabled>Sélectionner un budget...</option>
          <option v-for="b in budgets" :key="b.id" :value="b.id">{{ b.name }} ({{ b.fiscal_year }})</option>
        </select>
        <input v-model.number="generateFiscalYear" type="number" class="wh-input" style="width: 100px" placeholder="Année" />
        <button class="wh-input" style="cursor: pointer; background: #F0F9FF; border-color: #2E5BE8; color: #2E5BE8;" :disabled="generating" @click="generateFromHistory">
          {{ generating ? 'Génération…' : 'Générer un budget depuis l\'historique' }}
        </button>
        <a href="/accounting/finance-review" class="wh-input" style="text-decoration: none; background: #ECFDF5; border-color: #059669; color: #059669;">
          Revue finance mensuelle/trimestrielle
        </a>
      </div>
      <p v-if="generateFeedback" class="wh-generate-feedback" :class="{ 'wh-generate-error': generateFeedbackIsError }">{{ generateFeedback }}</p>

      <div v-if="!selectedBudgetId" class="wh-empty-state">Sélectionnez un budget pour voir son analyse d'écarts.</div>

      <div v-else-if="loading" class="wh-loading-state"><i class="pi pi-spin pi-spinner" /> Chargement...</div>

      <template v-else>
        <div class="wh-kpi-row" v-if="overview">
          <div class="wh-kpi"><div class="wh-kpi-label">Budgété</div><div class="wh-kpi-value">{{ fmt(overview.budgeted_amount) }}</div></div>
          <div class="wh-kpi"><div class="wh-kpi-label">Réalisé</div><div class="wh-kpi-value">{{ fmt(overview.actual_amount) }}</div></div>
          <div class="wh-kpi"><div class="wh-kpi-label">Écart</div><div class="wh-kpi-value" :style="{ color: overview.is_over_budget ? '#DC2626' : '#059669' }">{{ fmt(overview.variance_amount) }} ({{ overview.variance_percent }}%)</div></div>
          <div class="wh-kpi"><div class="wh-kpi-label">Utilisation</div><div class="wh-kpi-value">{{ overview.budget_utilization_percent }}%</div></div>
        </div>

        <h2 class="wh-section-title">Top écarts par ligne</h2>
        <table class="wh-table">
          <thead><tr><th>Compte</th><th>Budgété</th><th>Réalisé</th><th>Écart</th><th>Écart %</th></tr></thead>
          <tbody>
            <tr v-for="line in topVariances" :key="line.line_id">
              <td>{{ line.account_id ?? '—' }}</td>
              <td>{{ fmt(line.budgeted_amount) }}</td>
              <td>{{ fmt(line.actual_amount) }}</td>
              <td :style="{ color: line.variance > 0 ? '#DC2626' : '#059669' }">{{ fmt(line.variance) }}</td>
              <td>{{ line.variance_pct }}%</td>
            </tr>
            <tr v-if="topVariances.length === 0"><td colspan="5" class="wh-empty-state">Aucune ligne budgétaire.</td></tr>
          </tbody>
        </table>

        <h2 class="wh-section-title">Tendance mensuelle</h2>
        <table class="wh-table">
          <thead><tr><th>Mois</th><th>Budgété</th><th>Réalisé</th><th>Écart</th><th>Écart cumulé</th></tr></thead>
          <tbody>
            <tr v-for="m in trending" :key="m.month">
              <td>{{ m.month_name }}</td>
              <td>{{ fmt(m.budgeted) }}</td>
              <td>{{ fmt(m.actual) }}</td>
              <td :style="{ color: m.variance > 0 ? '#DC2626' : '#059669' }">{{ fmt(m.variance) }}</td>
              <td>{{ fmt(m.cumulative_variance) }}</td>
            </tr>
          </tbody>
        </table>
      </template>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

const budgets = ref([])
const selectedBudgetId = ref(null)
const loading = ref(false)
const overview = ref(null)
const topVariances = ref([])
const trending = ref([])

// Chantier 26 (volet C) — générer un budget depuis l'historique réel,
// corrélé aux objectifs commerciaux validés (Modules\Sales).
const generateFiscalYear = ref(new Date().getFullYear() + 1)
const generating = ref(false)
const generateFeedback = ref('')
const generateFeedbackIsError = ref(false)

const generateFromHistory = async () => {
  generating.value = true
  generateFeedback.value = ''
  try {
    const { data } = await axios.post('/api/v1/accounting/budgets/generate-from-history', {
      fiscal_year: generateFiscalYear.value,
    })
    generateFeedback.value = `Budget "${data.data.name}" généré (revenus : ${fmt(data.data.total_revenue_budget)}, dépenses : ${fmt(data.data.total_expense_budget)}).`
    generateFeedbackIsError.value = false
    await loadBudgets()
    selectedBudgetId.value = data.data.id
    await loadBudgetViews()
  } catch (err) {
    generateFeedback.value = err.response?.data?.message || 'Échec de la génération du budget.'
    generateFeedbackIsError.value = true
  } finally {
    generating.value = false
  }
}

const loadBudgets = async () => {
  try {
    const { data } = await axios.get('/api/v1/accounting/budgets')
    budgets.value = data.data ?? data
    if (budgets.value.length > 0) {
      selectedBudgetId.value = budgets.value[0].id
      await loadBudgetViews()
    }
  } catch (e) { console.error(e) }
}

const loadBudgetViews = async () => {
  if (!selectedBudgetId.value) return
  loading.value = true
  try {
    const [analyze, top, trend] = await Promise.all([
      axios.get('/api/v1/accounting/budget-variance/analyze', { params: { budget_id: selectedBudgetId.value } }),
      axios.get('/api/v1/accounting/budget-variance/top', { params: { budget_id: selectedBudgetId.value } }),
      axios.get('/api/v1/accounting/budget-variance/trending', { params: { budget_id: selectedBudgetId.value } }),
    ])
    overview.value = analyze.data
    topVariances.value = top.data
    trending.value = trend.data
  } catch (e) {
    console.error(e)
  } finally { loading.value = false }
}

onMounted(loadBudgets)

const fmt = (n) => new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n ?? 0)
</script>

<style scoped>
.wh-page { max-width: 1100px; margin: 0 auto; padding: 24px; }
.wh-page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; }
.wh-page-title { font-size: 20px; font-weight: 700; margin: 0; }
.wh-page-subtitle { font-size: 13px; color: #6B7280; margin-top: 2px; }
.wh-filters { display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
.wh-input { padding: 7px 10px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px; }
.wh-section-title { font-size: 15px; font-weight: 600; margin: 24px 0 12px; }
.wh-kpi-row { display: flex; gap: 16px; margin-bottom: 16px; flex-wrap: wrap; }
.wh-kpi { flex: 1; min-width: 160px; padding: 14px 16px; border: 1px solid #E5E7EB; border-radius: 8px; background: #fff; }
.wh-kpi-label { font-size: 11px; text-transform: uppercase; color: #6B7280; margin-bottom: 4px; }
.wh-kpi-value { font-size: 18px; font-weight: 700; }
.wh-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.wh-table th { text-align: left; font-size: 11px; text-transform: uppercase; color: #6B7280; padding: 10px 12px; border-bottom: 1px solid #E5E7EB; background: #F9FAFB; }
.wh-table td { padding: 10px 12px; border-bottom: 1px solid #E5E7EB; }
.wh-loading-state, .wh-empty-state { text-align: center; padding: 40px; color: #9CA3AF; font-size: 13px; }
.wh-generate-feedback { font-size: 13px; color: #059669; margin: -8px 0 16px; }
.wh-generate-error { color: #DC2626; }
</style>
