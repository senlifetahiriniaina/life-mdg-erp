<template>
  <AppLayout>
    <Head title="Règles d'alerte" />

    <div class="wh-page">
      <div class="wh-page-header">
        <div>
          <h1 class="wh-page-title">Règles d'alerte</h1>
          <p class="wh-page-subtitle">Alertes temps réel basées sur des seuils, conditions, destinataires et escalades</p>
        </div>
        <div class="wh-header-actions">
          <button class="wh-btn wh-btn-primary" @click="openCreate">
            <i class="pi pi-plus" /> Nouvelle règle
          </button>
        </div>
      </div>

      <div class="wh-stat-row">
        <div class="wh-stat-card">
          <span class="wh-stat-label">Règles totales</span>
          <span class="wh-stat-value">{{ rules.length }}</span>
        </div>
        <div class="wh-stat-card">
          <span class="wh-stat-label">Actives</span>
          <span class="wh-stat-value">{{ activeCount }}</span>
        </div>
        <div class="wh-stat-card">
          <span class="wh-stat-label">En pause</span>
          <span class="wh-stat-value">{{ pausedCount }}</span>
        </div>
      </div>

      <section class="wh-section">
        <h2 class="wh-section-title">Règles configurées</h2>
        <div v-if="loadingRules" class="wh-loading-state"><i class="pi pi-spin pi-spinner" /> Chargement...</div>
        <table v-else class="wh-table">
          <thead>
            <tr>
              <th>Nom</th><th>Source</th><th>Conditions</th><th>Statut</th><th>Public</th><th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in rules" :key="r.id">
              <td>
                {{ r.name }}
                <div v-if="r.description" class="wh-row-subtext">{{ r.description }}</div>
              </td>
              <td>{{ r.metric_source }} #{{ r.metric_source_id }}</td>
              <td>{{ r.condition_count ?? 0 }}</td>
              <td>
                <span :class="['wh-badge', statusBadgeClass(r.status)]">{{ statusLabel(r.status) }}</span>
              </td>
              <td>
                <span :class="['wh-badge', r.is_public ? 'wh-badge-green' : 'wh-badge-slate']">
                  {{ r.is_public ? 'Oui' : 'Non' }}
                </span>
              </td>
              <td class="wh-row-actions">
                <button class="wh-row-btn" title="Historique" @click="openHistory(r)"><i class="pi pi-history" /></button>
                <button v-if="r.status !== 'active'" class="wh-row-btn" title="Activer" @click="activateRule(r)"><i class="pi pi-play" style="color:#059669" /></button>
                <button v-if="r.status === 'active'" class="wh-row-btn" title="Mettre en pause" @click="pauseRule(r)"><i class="pi pi-pause" style="color:#D97706" /></button>
                <button v-if="r.status !== 'inactive'" class="wh-row-btn" title="Désactiver" @click="deactivateRule(r)"><i class="pi pi-power-off" style="color:#6B7280" /></button>
                <button class="wh-row-btn" title="Modifier" @click="openEdit(r)"><i class="pi pi-pencil" /></button>
                <button class="wh-row-btn" title="Supprimer" @click="deleteRule(r)"><i class="pi pi-trash" style="color:#DC2626" /></button>
              </td>
            </tr>
            <tr v-if="rules.length === 0">
              <td colspan="6" class="wh-empty-state">Aucune règle d'alerte configurée.</td>
            </tr>
          </tbody>
        </table>
      </section>

      <section v-if="historyRule" class="wh-section">
        <h2 class="wh-section-title">Historique — {{ historyRule.name }}</h2>
        <div v-if="loadingHistory" class="wh-loading-state"><i class="pi pi-spin pi-spinner" /> Chargement...</div>
        <table v-else class="wh-table">
          <thead>
            <tr>
              <th>Déclenchée le</th><th>Sévérité</th><th>Valeur</th><th>Message</th><th>Statut</th><th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="h in history" :key="h.id">
              <td>{{ formatDate(h.triggered_at) }}</td>
              <td>
                <span :class="['wh-badge', severityBadgeClass(h.severity)]">{{ h.severity ?? '—' }}</span>
              </td>
              <td>{{ h.triggered_value ?? '—' }}</td>
              <td>{{ h.message ?? '—' }}</td>
              <td>
                <span :class="['wh-badge', historyStatusBadgeClass(h.status)]">{{ h.status }}</span>
              </td>
              <td class="wh-row-actions">
                <button v-if="h.status === 'triggered'" class="wh-row-btn" title="Acquitter" @click="acknowledgeAlert(h)"><i class="pi pi-check" style="color:#059669" /></button>
                <button v-if="h.status !== 'resolved'" class="wh-row-btn" title="Résoudre" @click="resolveAlert(h)"><i class="pi pi-check-circle" style="color:#4F46E5" /></button>
              </td>
            </tr>
            <tr v-if="history.length === 0">
              <td colspan="6" class="wh-empty-state">Aucun historique pour cette règle.</td>
            </tr>
          </tbody>
        </table>
      </section>
    </div>

    <Dialog v-model:visible="showDialog" :header="editing ? 'Modifier la règle' : 'Nouvelle règle d\'alerte'" :modal="true" :style="{ width: '480px' }">
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
          <label>Source de la métrique</label>
          <input v-model="form.metric_source" class="wh-input" :disabled="!!editing" placeholder="ex: bi_kpi, inventory_stock" />
        </div>
        <div class="wh-form-field">
          <label>ID de la source</label>
          <input v-model.number="form.metric_source_id" type="number" min="1" class="wh-input" :disabled="!!editing" />
        </div>
        <div v-if="editing" class="wh-form-field">
          <label>Statut</label>
          <select v-model="form.status" class="wh-input">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="paused">En pause</option>
          </select>
        </div>
        <div class="wh-form-field wh-form-field-checkbox">
          <label><input v-model="form.is_public" type="checkbox" /> Visible par toute l'équipe</label>
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
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

const toast = useToast()

const rules = ref([])
const history = ref([])
const historyRule = ref(null)

const loadingRules = ref(true)
const loadingHistory = ref(false)
const saving = ref(false)
const showDialog = ref(false)
const editing = ref(null)

const activeCount = computed(() => rules.value.filter((r) => r.status === 'active').length)
const pausedCount = computed(() => rules.value.filter((r) => r.status === 'paused').length)

const defaultForm = () => ({
  name: '',
  description: '',
  metric_source: '',
  metric_source_id: null,
  status: 'active',
  is_public: false,
})

const form = ref(defaultForm())

const loadRules = async () => {
  loadingRules.value = true
  try {
    const { data } = await axios.get('/api/v1/bi/alert-rules')
    rules.value = data.data ?? data
  } catch (error) {
    console.error('Error loading alert rules:', error)
  } finally {
    loadingRules.value = false
  }
}

onMounted(() => {
  loadRules()
})

const openCreate = () => {
  editing.value = null
  form.value = defaultForm()
  showDialog.value = true
}

const openEdit = (r) => {
  editing.value = r
  form.value = {
    name: r.name,
    description: r.description ?? '',
    metric_source: r.metric_source,
    metric_source_id: r.metric_source_id,
    status: r.status,
    is_public: !!r.is_public,
  }
  showDialog.value = true
}

const submitForm = async () => {
  saving.value = true
  try {
    if (editing.value) {
      await axios.put(`/api/v1/bi/alert-rules/${editing.value.id}`, {
        name: form.value.name,
        description: form.value.description,
        status: form.value.status,
        is_public: form.value.is_public,
      })
      toast.add({ severity: 'success', summary: 'Modifiée', detail: 'Règle d\'alerte mise à jour', life: 3000 })
    } else {
      await axios.post('/api/v1/bi/alert-rules', {
        name: form.value.name,
        description: form.value.description,
        metric_source: form.value.metric_source,
        metric_source_id: form.value.metric_source_id,
        is_public: form.value.is_public,
      })
      toast.add({ severity: 'success', summary: 'Créée', detail: 'Règle d\'alerte créée', life: 3000 })
    }
    showDialog.value = false
    await loadRules()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec de l\'enregistrement', life: 4000 })
  } finally {
    saving.value = false
  }
}

const deleteRule = async (r) => {
  if (!confirm(`Supprimer la règle "${r.name}" ?`)) return
  try {
    await axios.delete(`/api/v1/bi/alert-rules/${r.id}`)
    toast.add({ severity: 'success', summary: 'Supprimée', detail: 'Règle d\'alerte supprimée', life: 3000 })
    if (historyRule.value?.id === r.id) historyRule.value = null
    await loadRules()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec de la suppression', life: 4000 })
  }
}

const activateRule = async (r) => {
  try {
    await axios.post(`/api/v1/bi/alert-rules/${r.id}/activate`)
    await loadRules()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec de l\'activation', life: 4000 })
  }
}

const deactivateRule = async (r) => {
  try {
    await axios.post(`/api/v1/bi/alert-rules/${r.id}/deactivate`)
    await loadRules()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec de la désactivation', life: 4000 })
  }
}

const pauseRule = async (r) => {
  try {
    await axios.post(`/api/v1/bi/alert-rules/${r.id}/pause`)
    await loadRules()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec de la mise en pause', life: 4000 })
  }
}

const openHistory = async (r) => {
  historyRule.value = r
  loadingHistory.value = true
  try {
    const { data } = await axios.get(`/api/v1/bi/alert-rules/${r.id}/history`)
    history.value = data.data ?? data
  } catch (error) {
    console.error('Error loading alert history:', error)
  } finally {
    loadingHistory.value = false
  }
}

const acknowledgeAlert = async (h) => {
  try {
    await axios.post(`/api/v1/bi/alert-history/${h.id}/acknowledge`, { acknowledgment_note: '' })
    toast.add({ severity: 'success', summary: 'Acquittée', detail: 'Alerte acquittée', life: 3000 })
    if (historyRule.value) await openHistory(historyRule.value)
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec de l\'acquittement', life: 4000 })
  }
}

const resolveAlert = async (h) => {
  try {
    await axios.post(`/api/v1/bi/alert-history/${h.id}/resolve`)
    toast.add({ severity: 'success', summary: 'Résolue', detail: 'Alerte résolue', life: 3000 })
    if (historyRule.value) await openHistory(historyRule.value)
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec de la résolution', life: 4000 })
  }
}

const statusLabel = (status) => ({ active: 'Active', inactive: 'Inactive', paused: 'En pause' }[status] ?? status)
const statusBadgeClass = (status) => ({ active: 'wh-badge-green', inactive: 'wh-badge-slate', paused: 'wh-badge-amber' }[status] ?? 'wh-badge-slate')
const severityBadgeClass = (severity) => ({ low: 'wh-badge-slate', medium: 'wh-badge-amber', high: 'wh-badge-amber', critical: 'wh-badge-red' }[severity] ?? 'wh-badge-slate')
const historyStatusBadgeClass = (status) => ({ triggered: 'wh-badge-red', acknowledged: 'wh-badge-amber', resolved: 'wh-badge-green', escalated: 'wh-badge-red' }[status] ?? 'wh-badge-slate')

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
.wh-row-subtext { font-size: 12px; color: #6B7280; margin-top: 2px; }
.wh-badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
.wh-badge-green { background: #D1FAE5; color: #065F46; }
.wh-badge-slate { background: #F1F5F9; color: #475569; }
.wh-badge-amber { background: #FEF3C7; color: #92400E; }
.wh-badge-red { background: #FEE2E2; color: #991B1B; }
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
.wh-input:disabled { background: #F9FAFB; color: #9CA3AF; }
</style>
