<template>
  <AppLayout>
    <Head title="Revue finance" />

    <div class="wh-page">
      <div class="wh-page-header">
        <div>
          <h1 class="wh-page-title">Revue finance mensuelle / trimestrielle</h1>
          <p class="wh-page-subtitle">Réalisation des objectifs commerciaux et du budget, calculée en direct</p>
        </div>
      </div>

      <div class="wh-filters">
        <select v-model="cadence" class="wh-input">
          <option value="monthly">Mensuelle</option>
          <option value="quarterly">Trimestrielle</option>
        </select>
        <input v-model="periodStart" type="date" class="wh-input" />
        <input v-model="periodEnd" type="date" class="wh-input" />
        <select v-model.number="budgetId" class="wh-input">
          <option :value="null">Sans budget lié</option>
          <option v-for="b in budgets" :key="b.id" :value="b.id">{{ b.name }} ({{ b.fiscal_year }})</option>
        </select>
        <button class="wh-input wh-btn-primary" :disabled="loading" @click="loadRealization">
          {{ loading ? 'Calcul…' : 'Calculer la réalisation' }}
        </button>
      </div>

      <template v-if="realization">
        <h2 class="wh-section-title">Réalisation des objectifs commerciaux</h2>
        <table class="wh-table">
          <thead><tr><th>Périmètre</th><th>Proposition</th><th>Cible proratisée</th><th>Réel</th><th>Réalisation</th></tr></thead>
          <tbody>
            <tr v-for="o in realization.objectives" :key="o.objective_id">
              <td>{{ scopeLabel(o.scope) }}</td>
              <td>{{ o.proposal_label || '—' }}</td>
              <td>{{ fmt(o.prorated_target) }} {{ o.currency }}</td>
              <td>{{ fmt(o.actual) }} {{ o.currency }}</td>
              <td :style="{ color: realizationColor(o.realization_percent) }">
                {{ o.realization_percent !== null ? o.realization_percent + '%' : '—' }}
              </td>
            </tr>
            <tr v-if="realization.objectives.length === 0"><td colspan="5" class="wh-empty-state">Aucun objectif validé sur cette période.</td></tr>
          </tbody>
        </table>

        <h2 class="wh-section-title">Réalisation du budget</h2>
        <table class="wh-table" v-if="budgetId">
          <thead><tr><th>Compte</th><th>Période</th><th>Budgété</th><th>Réel</th><th>Écart</th><th>Réalisation</th></tr></thead>
          <tbody>
            <tr v-for="l in realization.budget_lines" :key="l.budget_line_id">
              <td>{{ l.account_code }} — {{ l.account_name }}</td>
              <td>{{ l.period }}</td>
              <td>{{ fmt(l.budgeted_amount) }}</td>
              <td>{{ fmt(l.actual_amount) }}</td>
              <td :style="{ color: l.variance > 0 ? '#DC2626' : '#059669' }">{{ fmt(l.variance) }}</td>
              <td>{{ l.realization_percent !== null ? l.realization_percent + '%' : '—' }}</td>
            </tr>
            <tr v-if="realization.budget_lines.length === 0"><td colspan="6" class="wh-empty-state">Aucune ligne budgétaire sur cette période.</td></tr>
          </tbody>
        </table>
        <p v-else class="wh-empty-state">Sélectionnez un budget pour voir sa réalisation.</p>

        <h2 class="wh-section-title">Enregistrer cette revue</h2>
        <textarea v-model="comments" class="wh-input" style="width: 100%; min-height: 80px" placeholder="Commentaires de l'équipe finance…" />
        <button class="wh-input wh-btn-primary" style="margin-top: 8px" :disabled="saving" @click="saveReview">
          {{ saving ? 'Enregistrement…' : 'Enregistrer la revue' }}
        </button>
        <p v-if="saveFeedback" class="wh-generate-feedback" :class="{ 'wh-generate-error': saveFeedbackIsError }">{{ saveFeedback }}</p>
      </template>

      <h2 class="wh-section-title">Revues précédentes</h2>
      <table class="wh-table">
        <thead><tr><th>Date</th><th>Cadence</th><th>Période</th><th>Par</th><th>Commentaires</th></tr></thead>
        <tbody>
          <tr v-for="r in reviews" :key="r.id">
            <td>{{ r.review_date }}</td>
            <td>{{ r.cadence === 'monthly' ? 'Mensuelle' : 'Trimestrielle' }}</td>
            <td>{{ r.period_start }} → {{ r.period_end }}</td>
            <td>{{ r.reviewer?.name || '—' }}</td>
            <td>{{ r.comments || '—' }}</td>
          </tr>
          <tr v-if="reviews.length === 0"><td colspan="5" class="wh-empty-state">Aucune revue enregistrée.</td></tr>
        </tbody>
      </table>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

const today = new Date()
const firstOfMonth = new Date(today.getFullYear(), today.getMonth(), 1)
const lastOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0)
const toDateInput = (d) => d.toISOString().slice(0, 10)

const cadence = ref('monthly')
const periodStart = ref(toDateInput(firstOfMonth))
const periodEnd = ref(toDateInput(lastOfMonth))
const budgetId = ref(null)
const budgets = ref([])
const realization = ref(null)
const reviews = ref([])
const loading = ref(false)
const saving = ref(false)
const comments = ref('')
const saveFeedback = ref('')
const saveFeedbackIsError = ref(false)

const loadBudgets = async () => {
  try {
    const { data } = await axios.get('/api/v1/accounting/budgets')
    budgets.value = data.data ?? data
  } catch (e) { console.error(e) }
}

const loadReviews = async () => {
  try {
    const { data } = await axios.get('/api/v1/accounting/finance-reviews')
    reviews.value = data.data ?? data
  } catch (e) { console.error(e) }
}

const loadRealization = async () => {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/accounting/finance-reviews/realization', {
      params: { period_start: periodStart.value, period_end: periodEnd.value, budget_id: budgetId.value },
    })
    realization.value = data
  } catch (e) {
    console.error(e)
  } finally { loading.value = false }
}

const saveReview = async () => {
  saving.value = true
  saveFeedback.value = ''
  try {
    await axios.post('/api/v1/accounting/finance-reviews', {
      cadence: cadence.value,
      period_start: periodStart.value,
      period_end: periodEnd.value,
      budget_id: budgetId.value,
      comments: comments.value || null,
    })
    saveFeedback.value = 'Revue enregistrée.'
    saveFeedbackIsError.value = false
    comments.value = ''
    await loadReviews()
  } catch (err) {
    saveFeedback.value = err.response?.data?.message || "Échec de l'enregistrement."
    saveFeedbackIsError.value = true
  } finally { saving.value = false }
}

const scopeLabel = (scope) => ({ global: "Toute l'équipe", rep: 'Commercial', client: 'Client', category: 'Catégorie' }[scope] || scope)
const realizationColor = (pct) => {
  if (pct === null) return '#6B7280'
  return pct >= 100 ? '#059669' : pct >= 70 ? '#D97706' : '#DC2626'
}
const fmt = (n) => new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n ?? 0)

onMounted(() => {
  loadBudgets()
  loadReviews()
  loadRealization()
})
</script>

<style scoped>
.wh-page { max-width: 1100px; margin: 0 auto; padding: 24px; }
.wh-page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; }
.wh-page-title { font-size: 20px; font-weight: 700; margin: 0; }
.wh-page-subtitle { font-size: 13px; color: #6B7280; margin-top: 2px; }
.wh-filters { display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
.wh-input { padding: 7px 10px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px; }
.wh-btn-primary { cursor: pointer; background: #F0F9FF; border-color: #2E5BE8; color: #2E5BE8; }
.wh-section-title { font-size: 15px; font-weight: 600; margin: 24px 0 12px; }
.wh-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.wh-table th { text-align: left; font-size: 11px; text-transform: uppercase; color: #6B7280; padding: 10px 12px; border-bottom: 1px solid #E5E7EB; background: #F9FAFB; }
.wh-table td { padding: 10px 12px; border-bottom: 1px solid #E5E7EB; }
.wh-empty-state { text-align: center; padding: 40px; color: #9CA3AF; font-size: 13px; }
.wh-generate-feedback { font-size: 13px; color: #059669; margin-top: 8px; }
.wh-generate-error { color: #DC2626; }
</style>
