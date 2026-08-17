<template>
  <AppLayout>
    <Head title="Planification de scénarios" />

    <div class="wh-page">
      <div class="wh-page-header">
        <div>
          <h1 class="wh-page-title">Planification de scénarios budgétaires</h1>
          <p class="wh-page-subtitle">Simuler, comparer et analyser la sensibilité de scénarios "et si"</p>
        </div>
        <button v-if="can('accounting', 'budget_scenario', 'create')" class="wh-btn wh-btn-primary" @click="showCreate = true">
          <i class="pi pi-plus" /> Nouveau scénario
        </button>
      </div>

      <div v-if="loading" class="wh-loading-state"><i class="pi pi-spin pi-spinner" /> Chargement...</div>

      <table v-else class="wh-table">
        <thead><tr><th><input type="checkbox" disabled /></th><th>Nom</th><th>Type</th><th>Ajustement</th><th>Statut</th><th></th></tr></thead>
        <tbody>
          <tr v-for="s in scenarios" :key="s.id">
            <td><input type="checkbox" :value="s.id" v-model="selectedForCompare" /></td>
            <td>{{ s.name }}</td>
            <td>{{ s.scenario_type }}</td>
            <td>rev {{ pct(s.revenue_adjustment) }} / exp {{ pct(s.expense_adjustment) }}</td>
            <td><span :class="['wh-badge', s.status === 'approved' ? 'wh-badge-green' : 'wh-badge-slate']">{{ s.status }}</span></td>
            <td class="wh-row-actions">
              <button class="wh-row-btn" title="Simuler" @click="simulate(s)"><i class="pi pi-play" /></button>
              <button v-if="s.status !== 'approved' && can('accounting', 'budget_scenario', 'approve')" class="wh-row-btn" title="Approuver" @click="approve(s)"><i class="pi pi-check" style="color:#059669" /></button>
            </td>
          </tr>
          <tr v-if="scenarios.length === 0"><td colspan="6" class="wh-empty-state">Aucun scénario.</td></tr>
        </tbody>
      </table>

      <button class="wh-btn wh-btn-secondary" :disabled="selectedForCompare.length < 2" style="margin-top: 12px" @click="compare">
        Comparer la sélection ({{ selectedForCompare.length }})
      </button>

      <div v-if="simulation" class="wh-panel">
        <h2 class="wh-section-title">Résultat de la simulation — {{ simulation.scenario_id ? scenarios.find(s => s.id === simulation.scenario_id)?.name : '' }}</h2>
        <div class="wh-kpi-row">
          <div class="wh-kpi"><div class="wh-kpi-label">Revenu projeté</div><div class="wh-kpi-value">{{ fmt(simulation.projected_revenue) }}</div></div>
          <div class="wh-kpi"><div class="wh-kpi-label">Dépenses projetées</div><div class="wh-kpi-value">{{ fmt(simulation.projected_expenses) }}</div></div>
          <div class="wh-kpi"><div class="wh-kpi-label">Profit projeté</div><div class="wh-kpi-value">{{ fmt(simulation.projected_profit) }}</div></div>
        </div>
      </div>

      <div v-if="comparison.length" class="wh-panel">
        <h2 class="wh-section-title">Comparaison de scénarios</h2>
        <table class="wh-table">
          <thead><tr><th>Scénario</th><th>Revenu projeté</th><th>Dépenses projetées</th><th>Profit projeté</th></tr></thead>
          <tbody>
            <tr v-for="c in comparison" :key="c.scenario_id">
              <td>{{ c.scenario_name }}</td><td>{{ fmt(c.projected_revenue) }}</td><td>{{ fmt(c.projected_expenses) }}</td><td>{{ fmt(c.projected_profit) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="wh-panel">
        <h2 class="wh-section-title">Analyse de sensibilité</h2>
        <div class="wh-filters">
          <select v-model="sensitivity.variable" class="wh-input">
            <option value="revenue">Revenu</option>
            <option value="expense">Dépenses</option>
          </select>
          <input v-model.number="sensitivity.range_min" type="number" class="wh-input" style="width:80px" placeholder="Min %" />
          <input v-model.number="sensitivity.range_max" type="number" class="wh-input" style="width:80px" placeholder="Max %" />
          <input v-model.number="sensitivity.step" type="number" class="wh-input" style="width:80px" placeholder="Pas" />
          <button class="wh-btn wh-btn-secondary" @click="runSensitivity">Analyser</button>
        </div>
        <table class="wh-table" v-if="sensitivityResults.length">
          <thead><tr><th>Variation %</th><th>Revenu projeté</th><th>Dépenses projetées</th><th>Profit projeté</th></tr></thead>
          <tbody>
            <tr v-for="(r, i) in sensitivityResults" :key="i">
              <td>{{ r.value }}%</td><td>{{ fmt(r.projected_revenue) }}</td><td>{{ fmt(r.projected_expenses) }}</td><td>{{ fmt(r.projected_profit) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <Dialog v-model:visible="showCreate" header="Nouveau scénario" :modal="true" :style="{ width: '480px' }">
      <div class="wh-dialog-form">
        <div class="wh-form-field"><label>Nom</label><input v-model="form.name" class="wh-input" /></div>
        <div class="wh-form-field">
          <label>Type</label>
          <select v-model="form.type" class="wh-input">
            <option value="revenue">Revenu</option>
            <option value="cost">Coût</option>
            <option value="pricing">Tarification</option>
            <option value="volume">Volume</option>
          </select>
        </div>
        <div class="wh-form-field">
          <label>Budget de référence</label>
          <select v-model.number="form.parameters.base_budget_id" class="wh-input">
            <option :value="null" disabled>Sélectionner...</option>
            <option v-for="b in budgets" :key="b.id" :value="b.id">{{ b.name }}</option>
          </select>
        </div>
        <div class="wh-form-field"><label>Ajustement revenu (%)</label><input v-model.number="revenueAdjPct" type="number" step="0.1" class="wh-input" /></div>
        <div class="wh-form-field"><label>Ajustement dépenses (%)</label><input v-model.number="expenseAdjPct" type="number" step="0.1" class="wh-input" /></div>
        <div class="wh-form-field"><label>Description</label><textarea v-model="form.parameters.description" class="wh-input" rows="2" /></div>
      </div>
      <template #footer>
        <button class="wh-btn wh-btn-secondary" @click="showCreate = false">Annuler</button>
        <button class="wh-btn wh-btn-primary" :disabled="saving" @click="submitCreate">{{ saving ? 'Enregistrement...' : 'Créer' }}</button>
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'
import { useRoleAccess } from '@/composables/useRoleAccess'

const { can } = useRoleAccess()
const toast = useToast()

const scenarios = ref([])
const budgets = ref([])
const loading = ref(true)
const saving = ref(false)
const showCreate = ref(false)
const selectedForCompare = ref([])
const simulation = ref(null)
const comparison = ref([])
const sensitivity = ref({ variable: 'revenue', range_min: -20, range_max: 20, step: 10 })
const sensitivityResults = ref([])

const form = ref({ name: '', type: 'revenue', parameters: { base_budget_id: null, adjustment_type: 'percentage', description: '' } })
const revenueAdjPct = computed({
  get: () => (form.value.parameters.revenue_adjustment ?? 0) * 100,
  set: (v) => { form.value.parameters.revenue_adjustment = v / 100 },
})
const expenseAdjPct = computed({
  get: () => (form.value.parameters.expense_adjustment ?? 0) * 100,
  set: (v) => { form.value.parameters.expense_adjustment = v / 100 },
})

const loadScenarios = async () => {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/accounting/scenario-planning')
    scenarios.value = data.data ?? data
  } catch (e) { console.error(e) } finally { loading.value = false }
}

const loadBudgets = async () => {
  try {
    const { data } = await axios.get('/api/v1/accounting/budgets')
    budgets.value = data.data ?? data
  } catch (e) { console.error(e) }
}

onMounted(() => { loadScenarios(); loadBudgets() })

const submitCreate = async () => {
  saving.value = true
  try {
    await axios.post('/api/v1/accounting/scenario-planning', form.value)
    toast.add({ severity: 'success', summary: 'Créé', detail: 'Scénario créé', life: 3000 })
    showCreate.value = false
    await loadScenarios()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec', life: 4000 })
  } finally { saving.value = false }
}

const simulate = async (s) => {
  try {
    const { data } = await axios.post('/api/v1/accounting/scenario-planning/simulate', { scenario_id: s.id })
    simulation.value = { scenario_id: s.id, ...data }
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec', life: 4000 })
  }
}

const compare = async () => {
  try {
    const { data } = await axios.post('/api/v1/accounting/scenario-planning/compare', { scenario_ids: selectedForCompare.value })
    comparison.value = data
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec', life: 4000 })
  }
}

const approve = async (s) => {
  try {
    await axios.post('/api/v1/accounting/scenario-planning/approve', { scenario_id: s.id })
    s.status = 'approved'
    toast.add({ severity: 'success', summary: 'Approuvé', life: 3000 })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec', life: 4000 })
  }
}

const runSensitivity = async () => {
  try {
    const { data } = await axios.post('/api/v1/accounting/scenario-planning/sensitivity', sensitivity.value)
    sensitivityResults.value = data
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec', life: 4000 })
  }
}

const fmt = (n) => new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n ?? 0)
const pct = (v) => `${((v ?? 0) * 100).toFixed(1)}%`
</script>

<style scoped>
.wh-page { max-width: 1100px; margin: 0 auto; padding: 24px; }
.wh-page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; }
.wh-page-title { font-size: 20px; font-weight: 700; margin: 0; }
.wh-page-subtitle { font-size: 13px; color: #6B7280; margin-top: 2px; }
.wh-filters { display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; align-items: center; }
.wh-input { padding: 7px 10px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px; }
.wh-section-title { font-size: 15px; font-weight: 600; margin: 0 0 12px; }
.wh-panel { margin-top: 24px; padding: 16px; border: 1px solid #E5E7EB; border-radius: 8px; background: #fff; }
.wh-kpi-row { display: flex; gap: 16px; margin-bottom: 8px; flex-wrap: wrap; }
.wh-kpi { flex: 1; min-width: 160px; padding: 14px 16px; border: 1px solid #E5E7EB; border-radius: 8px; background: #F9FAFB; }
.wh-kpi-label { font-size: 11px; text-transform: uppercase; color: #6B7280; margin-bottom: 4px; }
.wh-kpi-value { font-size: 18px; font-weight: 700; }
.wh-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.wh-table th { text-align: left; font-size: 11px; text-transform: uppercase; color: #6B7280; padding: 10px 12px; border-bottom: 1px solid #E5E7EB; background: #F9FAFB; }
.wh-table td { padding: 10px 12px; border-bottom: 1px solid #E5E7EB; }
.wh-row-actions { display: flex; gap: 4px; }
.wh-row-btn { width: 28px; height: 28px; border-radius: 6px; border: 1px solid #E5E7EB; background: #fff; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
.wh-row-btn:hover { background: #F9FAFB; }
.wh-badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.wh-badge-green { background: #D1FAE5; color: #065F46; }
.wh-badge-slate { background: #F3F4F6; color: #6B7280; }
.wh-loading-state, .wh-empty-state { text-align: center; padding: 40px; color: #9CA3AF; font-size: 13px; }
.wh-btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px; border-radius: 7px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; }
.wh-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.wh-btn-primary { background: #2563EB; color: #fff; }
.wh-btn-secondary { background: #F3F4F6; color: #374151; border: 1px solid #E5E7EB; }
.wh-dialog-form { display: flex; flex-direction: column; gap: 12px; }
.wh-form-field { display: flex; flex-direction: column; gap: 4px; }
.wh-form-field label { font-size: 13px; font-weight: 500; color: #374151; }
</style>
