<template>
  <AppLayout>
    <Head title="Automatisation SLA" />

    <div class="wh-page">
      <div class="wh-page-header">
        <div>
          <h1 class="wh-page-title">Automatisation SLA</h1>
          <p class="wh-page-subtitle">Politiques SLA, détection de dépassements, conformité et performance par priorité</p>
        </div>
        <div class="wh-header-actions">
          <button class="wh-btn wh-btn-secondary" :disabled="escalating" @click="runEscalations">
            <i class="pi pi-forward" /> {{ escalating ? 'Exécution...' : 'Exécuter les escalades' }}
          </button>
          <button class="wh-btn wh-btn-primary" @click="openCreate">
            <i class="pi pi-plus" /> Nouvelle politique
          </button>
        </div>
      </div>

      <div class="wh-stat-row" v-if="stats">
        <div class="wh-stat-card">
          <span class="wh-stat-label">Tickets en dépassement</span>
          <span class="wh-stat-value">{{ stats.breached ?? 0 }}</span>
        </div>
        <div class="wh-stat-card">
          <span class="wh-stat-label">Taux de conformité</span>
          <span class="wh-stat-value">{{ stats.compliance_rate ?? 0 }}%</span>
        </div>
        <div class="wh-stat-card">
          <span class="wh-stat-label">Dépassements en attente</span>
          <span class="wh-stat-value">{{ pendingBreaches.length }}</span>
        </div>
      </div>

      <section class="wh-section">
        <h2 class="wh-section-title">Politiques SLA</h2>
        <div v-if="loadingPolicies" class="wh-loading-state"><i class="pi pi-spin pi-spinner" /> Chargement...</div>
        <table v-else class="wh-table">
          <thead>
            <tr>
              <th>Nom</th><th>Priorité</th><th>Réponse (min)</th><th>Résolution (min)</th><th>Heures ouvrées</th><th>Statut</th><th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in policies" :key="p.id">
              <td>{{ p.name }}</td>
              <td>{{ p.priority }}</td>
              <td>{{ p.response_time_minutes }}</td>
              <td>{{ p.resolution_time_minutes }}</td>
              <td>{{ p.business_hours_only ? 'Oui' : 'Non' }}</td>
              <td>
                <span :class="['wh-badge', p.is_active ? 'wh-badge-green' : 'wh-badge-slate']">
                  {{ p.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="wh-row-actions">
                <button class="wh-row-btn" title="Modifier" @click="openEdit(p)"><i class="pi pi-pencil" /></button>
                <button class="wh-row-btn" title="Supprimer" @click="deletePolicy(p)"><i class="pi pi-trash" style="color:#DC2626" /></button>
              </td>
            </tr>
            <tr v-if="policies.length === 0">
              <td colspan="7" class="wh-empty-state">Aucune politique SLA.</td>
            </tr>
          </tbody>
        </table>
      </section>

      <section class="wh-section">
        <h2 class="wh-section-title">Dépassements en attente</h2>
        <div v-if="loadingBreaches" class="wh-loading-state"><i class="pi pi-spin pi-spinner" /> Chargement...</div>
        <table v-else class="wh-table">
          <thead>
            <tr>
              <th>Ticket</th><th>Type</th><th>Dépassement (min)</th><th>Date</th><th>Escaladé</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="b in pendingBreaches" :key="b.id">
              <td>#{{ b.ticket_id }}</td>
              <td>{{ b.breach_type === 'response' ? 'Réponse' : 'Résolution' }}</td>
              <td>{{ b.breach_minutes }}</td>
              <td>{{ formatDate(b.breached_at) }}</td>
              <td>
                <span :class="['wh-badge', b.escalated ? 'wh-badge-amber' : 'wh-badge-slate']">
                  {{ b.escalated ? 'Oui' : 'Non' }}
                </span>
              </td>
            </tr>
            <tr v-if="pendingBreaches.length === 0">
              <td colspan="5" class="wh-empty-state">Aucun dépassement en attente.</td>
            </tr>
          </tbody>
        </table>
      </section>

      <section class="wh-section">
        <h2 class="wh-section-title">Performance par priorité</h2>
        <div v-if="loadingReport" class="wh-loading-state"><i class="pi pi-spin pi-spinner" /> Chargement...</div>
        <table v-else class="wh-table">
          <thead>
            <tr>
              <th>Priorité</th><th>Temps de réponse moyen (min)</th><th>Temps de résolution moyen (min)</th><th>Nb dépassements</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in performanceReport" :key="row.priority">
              <td>{{ row.priority }}</td>
              <td>{{ row.avg_response_minutes ?? '—' }}</td>
              <td>{{ row.avg_resolution_minutes ?? '—' }}</td>
              <td>{{ row.breach_count }}</td>
            </tr>
            <tr v-if="performanceReport.length === 0">
              <td colspan="4" class="wh-empty-state">Aucune donnée de performance.</td>
            </tr>
          </tbody>
        </table>
      </section>
    </div>

    <Dialog v-model:visible="showDialog" :header="editing ? 'Modifier la politique' : 'Nouvelle politique SLA'" :modal="true" :style="{ width: '480px' }">
      <div class="wh-dialog-form">
        <div class="wh-form-field">
          <label>Nom</label>
          <input v-model="form.name" class="wh-input" />
        </div>
        <div class="wh-form-field">
          <label>Description</label>
          <textarea v-model="form.description" class="wh-input" rows="2" />
        </div>
        <div class="wh-form-field">
          <label>Priorité</label>
          <select v-model="form.priority" class="wh-input">
            <option value="low">Basse</option>
            <option value="normal">Normale</option>
            <option value="medium">Moyenne</option>
            <option value="high">Haute</option>
            <option value="urgent">Urgente</option>
            <option value="critical">Critique</option>
          </select>
        </div>
        <div class="wh-form-field">
          <label>Temps de réponse (minutes)</label>
          <input v-model.number="form.response_time_minutes" type="number" min="1" class="wh-input" />
        </div>
        <div class="wh-form-field">
          <label>Temps de résolution (minutes)</label>
          <input v-model.number="form.resolution_time_minutes" type="number" min="1" class="wh-input" />
        </div>
        <div class="wh-form-field wh-form-field-checkbox">
          <label><input v-model="form.business_hours_only" type="checkbox" /> Heures ouvrées uniquement</label>
        </div>
        <div class="wh-form-field wh-form-field-checkbox">
          <label><input v-model="form.is_active" type="checkbox" /> Active</label>
        </div>
      </div>
      <template #footer>
        <button class="wh-btn wh-btn-secondary" @click="showDialog = false">Annuler</button>
        <button class="wh-btn wh-btn-primary" :disabled="saving" @click="submitForm">
          {{ saving ? 'Enregistrement...' : 'Enregistrer' }}
        </button>
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

const toast = useToast()

const policies = ref([])
const pendingBreaches = ref([])
const performanceReport = ref([])
const stats = ref(null)

const loadingPolicies = ref(true)
const loadingBreaches = ref(true)
const loadingReport = ref(true)
const saving = ref(false)
const escalating = ref(false)
const showDialog = ref(false)
const editing = ref(null)

const defaultForm = () => ({
  name: '',
  description: '',
  priority: 'normal',
  response_time_minutes: 60,
  resolution_time_minutes: 480,
  business_hours_only: false,
  is_active: true,
})

const form = ref(defaultForm())

const loadPolicies = async () => {
  loadingPolicies.value = true
  try {
    const { data } = await axios.get('/api/v1/helpdesk/sla/policies')
    policies.value = data.data ?? data
  } catch (error) {
    console.error('Error loading SLA policies:', error)
  } finally {
    loadingPolicies.value = false
  }
}

const loadBreaches = async () => {
  loadingBreaches.value = true
  try {
    const { data } = await axios.get('/api/v1/helpdesk/sla/breaches/pending')
    pendingBreaches.value = data.data ?? data
  } catch (error) {
    console.error('Error loading pending breaches:', error)
  } finally {
    loadingBreaches.value = false
  }
}

const loadPerformanceReport = async () => {
  loadingReport.value = true
  try {
    const { data } = await axios.get('/api/v1/helpdesk/sla/stats/performance')
    performanceReport.value = data.data ?? data
  } catch (error) {
    console.error('Error loading performance report:', error)
  } finally {
    loadingReport.value = false
  }
}

const loadComplianceStats = async () => {
  try {
    const { data } = await axios.get('/api/v1/helpdesk/sla/stats/compliance')
    stats.value = data.data ?? data
  } catch (error) {
    console.error('Error loading compliance stats:', error)
  }
}

onMounted(() => {
  loadPolicies()
  loadBreaches()
  loadPerformanceReport()
  loadComplianceStats()
})

const openCreate = () => {
  editing.value = null
  form.value = defaultForm()
  showDialog.value = true
}

const openEdit = (p) => {
  editing.value = p
  form.value = {
    name: p.name,
    description: p.description ?? '',
    priority: p.priority,
    response_time_minutes: p.response_time_minutes,
    resolution_time_minutes: p.resolution_time_minutes,
    business_hours_only: !!p.business_hours_only,
    is_active: !!p.is_active,
  }
  showDialog.value = true
}

const submitForm = async () => {
  saving.value = true
  try {
    if (editing.value) {
      await axios.put(`/api/v1/helpdesk/sla/policies/${editing.value.id}`, form.value)
      toast.add({ severity: 'success', summary: 'Modifiée', detail: 'Politique SLA mise à jour', life: 3000 })
    } else {
      await axios.post('/api/v1/helpdesk/sla/policies', form.value)
      toast.add({ severity: 'success', summary: 'Créée', detail: 'Politique SLA créée', life: 3000 })
    }
    showDialog.value = false
    await loadPolicies()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec de l\'enregistrement', life: 4000 })
  } finally {
    saving.value = false
  }
}

const deletePolicy = async (p) => {
  if (!confirm(`Supprimer la politique "${p.name}" ?`)) return
  try {
    await axios.delete(`/api/v1/helpdesk/sla/policies/${p.id}`)
    toast.add({ severity: 'success', summary: 'Supprimée', detail: 'Politique SLA supprimée', life: 3000 })
    await loadPolicies()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec de la suppression', life: 4000 })
  }
}

const runEscalations = async () => {
  escalating.value = true
  try {
    const { data } = await axios.post('/api/v1/helpdesk/sla/escalate')
    toast.add({ severity: 'success', summary: 'Escalades exécutées', detail: `${data.escalated_count ?? 0} dépassement(s) escaladé(s)`, life: 3000 })
    await loadBreaches()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec de l\'exécution', life: 4000 })
  } finally {
    escalating.value = false
  }
}

const formatDate = (d) => d ? new Date(d).toLocaleString('fr-FR') : '—'
</script>

<style scoped>
.wh-page { max-width: 1100px; margin: 0 auto; padding: 24px; }
.wh-page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; }
.wh-header-actions { display: flex; gap: 8px; }
.wh-page-title { font-size: 20px; font-weight: 700; margin: 0; }
.wh-page-subtitle { font-size: 13px; color: #6B7280; margin-top: 2px; }
.wh-stat-row { display: flex; gap: 16px; margin-bottom: 24px; }
.wh-stat-card { flex: 1; background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px; padding: 16px; display: flex; flex-direction: column; gap: 4px; }
.wh-stat-label { font-size: 12px; color: #6B7280; }
.wh-stat-value { font-size: 22px; font-weight: 700; }
.wh-section { margin-bottom: 32px; }
.wh-section-title { font-size: 15px; font-weight: 600; margin-bottom: 10px; }
.wh-table { width: 100%; border-collapse: collapse; }
.wh-table th, .wh-table td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #E5E7EB; font-size: 13px; }
.wh-table th { color: #6B7280; font-weight: 600; font-size: 12px; text-transform: uppercase; }
.wh-badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
.wh-badge-green { background: #D1FAE5; color: #065F46; }
.wh-badge-slate { background: #F1F5F9; color: #475569; }
.wh-badge-amber { background: #FEF3C7; color: #92400E; }
.wh-row-actions { display: flex; gap: 6px; }
.wh-row-btn { background: none; border: none; cursor: pointer; padding: 4px; }
.wh-loading-state, .wh-empty-state { padding: 16px; text-align: center; color: #6B7280; font-size: 13px; }
.wh-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; border: 1px solid transparent; }
.wh-btn-primary { background: #4F46E5; color: #fff; }
.wh-btn-secondary { background: #F3F4F6; color: #1F2937; border-color: #E5E7EB; }
.wh-dialog-form { display: flex; flex-direction: column; gap: 12px; }
.wh-form-field { display: flex; flex-direction: column; gap: 4px; }
.wh-form-field-checkbox { flex-direction: row; align-items: center; gap: 8px; }
.wh-input { border: 1px solid #E5E7EB; border-radius: 6px; padding: 8px 10px; font-size: 13px; }
</style>
