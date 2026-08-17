<template>
  <AppLayout>
    <Head :title="company?.name ?? 'Consolidation'" />

    <div v-if="loading" class="wh-loading-state"><i class="pi pi-spin pi-spinner" /> Chargement...</div>

    <div v-else-if="company" class="wh-page">
      <div class="wh-page-header">
        <div>
          <h1 class="wh-page-title">{{ company.name }}</h1>
          <p class="wh-page-subtitle">
            <span class="wh-mono">{{ company.code }}</span> · {{ company.company_type }}
            <span v-if="company.parent"> · Filiale de {{ company.parent.name }}</span>
          </p>
        </div>
        <span :class="['wh-badge', company.is_active ? 'wh-badge-green' : 'wh-badge-slate']">
          {{ company.is_active ? 'Active' : 'Inactive' }}
        </span>
      </div>

      <div class="wh-tabs">
        <button v-for="tab in tabs" :key="tab" :class="['wh-tab', activeTab === tab ? 'active' : '']" @click="activeTab = tab">
          {{ tab }}
        </button>
      </div>

      <!-- Information -->
      <div v-if="activeTab === 'Informations'" class="wh-tab-content">
        <div class="wh-info-grid">
          <div class="wh-info-item"><label>Devise</label><p>{{ company.currency }}</p></div>
          <div class="wh-info-item"><label>% Détention</label><p>{{ company.ownership_percentage }}%</p></div>
          <div class="wh-info-item"><label>Mois de début d'exercice</label><p>{{ company.fiscal_year_start_month }}</p></div>
          <div class="wh-info-item"><label>Société mère</label><p>{{ company.parent?.name ?? '—' }}</p></div>
        </div>
      </div>

      <!-- Subsidiaries -->
      <div v-if="activeTab === 'Filiales'" class="wh-tab-content">
        <div v-if="summary" class="wh-summary-row">
          <div class="wh-summary-item"><span>{{ subsidiaries.length }}</span><label>Filiales</label></div>
          <div class="wh-summary-item"><span>{{ pendingEliminationsTotal }}</span><label>Éliminations en attente</label></div>
        </div>
        <table class="wh-table">
          <thead>
            <tr><th>Société</th><th>Code</th><th>Type</th><th>% Détention</th><th>Filiales</th></tr>
          </thead>
          <tbody>
            <tr v-for="s in summary ?? []" :key="s.company_id" class="wh-row" @click="router.get(`/accounting/consolidations/${s.company_id}`)">
              <td>{{ s.company_name }}</td>
              <td><span class="wh-mono">{{ s.company_code }}</span></td>
              <td>{{ s.company_type }}</td>
              <td>{{ s.ownership_percentage }}%</td>
              <td>{{ s.subsidiary_count }}</td>
            </tr>
            <tr v-if="!summary || summary.length === 0"><td colspan="5" class="wh-empty-state">Aucune filiale</td></tr>
          </tbody>
        </table>
      </div>

      <!-- Transactions -->
      <div v-if="activeTab === 'Transactions'" class="wh-tab-content">
        <div class="wh-tab-actions">
          <button v-if="canManage" class="wh-btn wh-btn-primary wh-btn-sm" @click="showTransactionForm = !showTransactionForm">
            <i class="pi pi-plus" /> Enregistrer une transaction
          </button>
          <button v-if="canManage" class="wh-btn wh-btn-secondary wh-btn-sm" :disabled="eliminating" @click="eliminateTransactions">
            {{ eliminating ? 'Élimination...' : 'Éliminer les transactions inter-sociétés' }}
          </button>
        </div>

        <form v-if="showTransactionForm" class="wh-inline-form" @submit.prevent="recordTransaction">
          <select v-model="newTransaction.to_company_id" class="wh-input" required>
            <option value="">— Société contrepartie —</option>
            <option v-for="s in summary ?? []" :key="s.company_id" :value="s.company_id">{{ s.company_name }}</option>
          </select>
          <input v-model.number="newTransaction.amount" type="number" step="0.01" placeholder="Montant" class="wh-input" required />
          <input v-model="newTransaction.description" placeholder="Description" class="wh-input" />
          <input v-model="newTransaction.transaction_date" type="date" class="wh-input" />
          <button type="submit" class="wh-btn wh-btn-primary wh-btn-sm">Enregistrer</button>
          <button type="button" class="wh-btn wh-btn-secondary wh-btn-sm" @click="showTransactionForm = false">Annuler</button>
        </form>

        <table class="wh-table">
          <thead>
            <tr><th>De</th><th>Vers</th><th>Montant</th><th>Date</th><th>Statut</th></tr>
          </thead>
          <tbody>
            <tr v-for="tx in transactions" :key="tx.id">
              <td>{{ tx.from_company?.name ?? tx.from_company_id }}</td>
              <td>{{ tx.to_company?.name ?? tx.to_company_id }}</td>
              <td>{{ Number(tx.amount).toLocaleString('fr-FR') }} {{ tx.currency }}</td>
              <td>{{ formatDate(tx.transaction_date) }}</td>
              <td>
                <span :class="['wh-badge', tx.is_eliminated ? 'wh-badge-green' : 'wh-badge-amber']">
                  {{ tx.is_eliminated ? 'Éliminée' : 'En attente' }}
                </span>
              </td>
            </tr>
            <tr v-if="transactions.length === 0"><td colspan="5" class="wh-empty-state">Aucune transaction</td></tr>
          </tbody>
        </table>
      </div>

      <!-- Reports -->
      <div v-if="activeTab === 'Rapports'" class="wh-tab-content">
        <div v-if="canManage" class="wh-tab-actions">
          <input v-model="reportForm.period_start" type="date" class="wh-input" />
          <input v-model="reportForm.period_end" type="date" class="wh-input" />
          <button class="wh-btn wh-btn-primary wh-btn-sm" :disabled="generatingReport" @click="generateReport">
            {{ generatingReport ? 'Génération...' : 'Générer un rapport consolidé' }}
          </button>
        </div>

        <div v-if="reports.length > 0" class="wh-reports-list">
          <div v-for="report in reports" :key="report.id" class="wh-report-card">
            <h4>{{ report.report_type }}</h4>
            <p><strong>Statut :</strong> {{ report.status }}</p>
            <p><strong>Créé le :</strong> {{ formatDate(report.created_at) }}</p>
          </div>
        </div>
        <p v-else class="wh-empty-state">Aucun rapport généré</p>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'
import { useRoleAccess } from '@/composables/useRoleAccess'

const props = defineProps({ companyId: { type: [Number, String], required: true } })

const toast = useToast()
const { isSuperAdmin } = useRoleAccess()
const canManage = isSuperAdmin

const loading = ref(true)
const company = ref(null)
const subsidiaries = ref([])
const summary = ref(null)
const transactions = ref([])
const reports = ref([])
const activeTab = ref('Informations')
const tabs = ['Informations', 'Filiales', 'Transactions', 'Rapports']

const showTransactionForm = ref(false)
const eliminating = ref(false)
const generatingReport = ref(false)

const newTransaction = ref({
  to_company_id: '',
  amount: 0,
  description: '',
  transaction_date: new Date().toISOString().slice(0, 10),
})

const reportForm = ref({
  period_start: new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10),
  period_end: new Date().toISOString().slice(0, 10),
})

const pendingEliminationsTotal = computed(() => {
  if (!summary.value) return '0'
  const total = summary.value.reduce((sum, s) => sum + (s.pending_eliminations ?? 0), 0)
  return total.toLocaleString('fr-FR')
})

const loadAll = async () => {
  loading.value = true
  try {
    const [companyRes, subsRes, summaryRes, txRes, reportsRes] = await Promise.all([
      axios.get(`/api/v1/accounting/consolidations/${props.companyId}`),
      axios.get(`/api/v1/accounting/consolidations/${props.companyId}/subsidiaries`),
      axios.get(`/api/v1/accounting/consolidations/${props.companyId}/summary`),
      axios.get('/api/v1/accounting/intercompany-transactions', { params: { from_company_id: props.companyId } }),
      axios.get('/api/v1/accounting/consolidation-reports', { params: { parent_company_id: props.companyId } }),
    ])
    company.value = companyRes.data
    subsidiaries.value = subsRes.data.subsidiaries ?? []
    summary.value = summaryRes.data.group_summary ?? []
    transactions.value = txRes.data.data ?? txRes.data
    reports.value = reportsRes.data.data ?? reportsRes.data
  } catch (error) {
    console.error('Error loading consolidation detail:', error)
  } finally {
    loading.value = false
  }
}

onMounted(loadAll)

const formatDate = (d) => d ? new Date(d).toLocaleDateString('fr-FR') : '—'

const recordTransaction = async () => {
  try {
    await axios.post('/api/v1/accounting/intercompany-transactions', {
      from_company_id: props.companyId,
      to_company_id: newTransaction.value.to_company_id,
      amount: newTransaction.value.amount,
      description: newTransaction.value.description,
      transaction_date: newTransaction.value.transaction_date,
    })
    toast.add({ severity: 'success', summary: 'Enregistrée', detail: 'Transaction inter-sociétés enregistrée', life: 3000 })
    showTransactionForm.value = false
    newTransaction.value = { to_company_id: '', amount: 0, description: '', transaction_date: new Date().toISOString().slice(0, 10) }
    await loadAll()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec de l\'enregistrement', life: 4000 })
  }
}

const eliminateTransactions = async () => {
  eliminating.value = true
  try {
    const { data } = await axios.post('/api/v1/accounting/consolidations/eliminate', { parent_company_id: props.companyId })
    toast.add({ severity: 'success', summary: 'Éliminées', detail: data.message, life: 3000 })
    await loadAll()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec de l\'élimination', life: 4000 })
  } finally {
    eliminating.value = false
  }
}

const generateReport = async () => {
  generatingReport.value = true
  try {
    await axios.post(`/api/v1/accounting/consolidations/${props.companyId}/report`, {
      period_start: reportForm.value.period_start,
      period_end: reportForm.value.period_end,
    })
    toast.add({ severity: 'success', summary: 'Généré', detail: 'Rapport consolidé généré', life: 3000 })
    await loadAll()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec de la génération', life: 4000 })
  } finally {
    generatingReport.value = false
  }
}
</script>

<style scoped>
.wh-page { max-width: 1000px; margin: 0 auto; padding: 24px; }
.wh-page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 16px; }
.wh-page-title { font-size: 22px; font-weight: 700; margin: 0; }
.wh-page-subtitle { font-size: 13px; color: #6B7280; margin-top: 4px; }
.wh-mono { font-family: monospace; }
.wh-tabs { display: flex; gap: 4px; border-bottom: 1px solid #E5E7EB; margin-bottom: 20px; }
.wh-tab { padding: 8px 16px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; background: none; color: #6B7280; border-bottom: 2px solid transparent; margin-bottom: -1px; }
.wh-tab.active { color: #2563EB; border-bottom-color: #2563EB; }
.wh-tab-content { margin-bottom: 20px; }
.wh-tab-actions { display: flex; gap: 8px; margin-bottom: 14px; align-items: center; flex-wrap: wrap; }
.wh-inline-form { display: flex; gap: 8px; margin-bottom: 14px; flex-wrap: wrap; align-items: center; }
.wh-info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
.wh-info-item { padding: 14px; background: #F9FAFB; border-radius: 8px; }
.wh-info-item label { display: block; font-size: 11px; text-transform: uppercase; color: #6B7280; margin-bottom: 4px; }
.wh-info-item p { margin: 0; font-size: 15px; color: #111827; }
.wh-summary-row { display: flex; gap: 24px; margin-bottom: 16px; }
.wh-summary-item { text-align: center; }
.wh-summary-item span { display: block; font-size: 22px; font-weight: 700; }
.wh-summary-item label { font-size: 12px; color: #6B7280; }
.wh-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.wh-table th { text-align: left; font-size: 11px; text-transform: uppercase; color: #6B7280; padding: 10px 12px; border-bottom: 1px solid #E5E7EB; background: #F9FAFB; }
.wh-row td, .wh-table td { padding: 10px 12px; border-bottom: 1px solid #E5E7EB; }
.wh-row { cursor: pointer; }
.wh-row:hover { background: #F9FAFB; }
.wh-badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.wh-badge-green { background: #D1FAE5; color: #065F46; }
.wh-badge-amber { background: #FEF3C7; color: #92400E; }
.wh-badge-slate { background: #F3F4F6; color: #6B7280; }
.wh-empty-state { text-align: center; padding: 30px; color: #9CA3AF; }
.wh-loading-state { text-align: center; padding: 60px; color: #9CA3AF; }
.wh-reports-list { display: flex; flex-direction: column; gap: 10px; }
.wh-report-card { padding: 14px; background: #F9FAFB; border-left: 4px solid #2563EB; border-radius: 6px; }
.wh-report-card h4 { margin: 0 0 6px; }
.wh-report-card p { margin: 2px 0; font-size: 13px; }
.wh-input { padding: 7px 10px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px; }
.wh-btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px; border-radius: 7px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; }
.wh-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.wh-btn-sm { padding: 5px 12px; font-size: 12px; }
.wh-btn-primary { background: #2563EB; color: #fff; }
.wh-btn-secondary { background: #F3F4F6; color: #374151; border: 1px solid #E5E7EB; }
</style>
