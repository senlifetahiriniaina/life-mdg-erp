<template>
  <AppLayout>
    <Head title="Prévisions avancées" />

    <div class="wh-page">
      <div class="wh-page-header">
        <div>
          <h1 class="wh-page-title">Prévisions avancées</h1>
          <p class="wh-page-subtitle">Modèles de prévision, scénarios, saisonnalité et analyse de tendance</p>
        </div>
        <div class="wh-header-actions">
          <button class="wh-btn wh-btn-primary" @click="openCreate">
            <i class="pi pi-plus" /> Nouveau modèle
          </button>
        </div>
      </div>

      <div class="wh-stat-row" v-if="models.length">
        <div class="wh-stat-card">
          <span class="wh-stat-label">Modèles</span>
          <span class="wh-stat-value">{{ models.length }}</span>
        </div>
        <div class="wh-stat-card">
          <span class="wh-stat-label">Déployés</span>
          <span class="wh-stat-value">{{ countByStatus('deployed') }}</span>
        </div>
        <div class="wh-stat-card">
          <span class="wh-stat-label">Entraînés</span>
          <span class="wh-stat-value">{{ countByStatus('trained') }}</span>
        </div>
        <div class="wh-stat-card">
          <span class="wh-stat-label">Brouillons</span>
          <span class="wh-stat-value">{{ countByStatus('draft') }}</span>
        </div>
      </div>

      <section class="wh-section">
        <h2 class="wh-section-title">Modèles de prévision</h2>
        <div v-if="loadingModels" class="wh-loading-state"><i class="pi pi-spin pi-spinner" /> Chargement...</div>
        <table v-else class="wh-table">
          <thead>
            <tr>
              <th>Nom</th><th>Algorithme</th><th>Métrique</th><th>Fréquence</th><th>Horizon</th><th>Statut</th><th>Précision</th><th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="m in models" :key="m.id">
              <td>{{ m.name }}</td>
              <td>{{ algorithmLabel(m.model_type) }}</td>
              <td>{{ m.metric_name }}</td>
              <td>{{ frequencyLabel(m.data_frequency) }}</td>
              <td>{{ m.forecast_horizon }} j</td>
              <td>
                <span :class="['wh-badge', statusBadgeClass(m.status)]">{{ statusLabel(m.status) }}</span>
              </td>
              <td>{{ accuracyLabel(m) }}</td>
              <td class="wh-row-actions">
                <button
                  class="wh-row-btn"
                  title="Entraîner"
                  :disabled="training === m.id"
                  @click="trainModel(m)"
                >
                  <i class="pi pi-play" />
                </button>
                <button
                  v-if="m.status === 'trained'"
                  class="wh-row-btn"
                  title="Déployer"
                  :disabled="deploying === m.id"
                  @click="deployModel(m)"
                >
                  <i class="pi pi-cloud-upload" />
                </button>
                <button
                  v-if="m.status !== 'archived'"
                  class="wh-row-btn"
                  title="Archiver"
                  :disabled="archiving === m.id"
                  @click="archiveModel(m)"
                >
                  <i class="pi pi-inbox" />
                </button>
                <button class="wh-row-btn" title="Supprimer" @click="deleteModel(m)">
                  <i class="pi pi-trash" style="color:#DC2626" />
                </button>
              </td>
            </tr>
            <tr v-if="models.length === 0">
              <td colspan="8" class="wh-empty-state">Aucun modèle de prévision.</td>
            </tr>
          </tbody>
        </table>
      </section>
    </div>

    <Dialog v-model:visible="showDialog" header="Nouveau modèle de prévision" :modal="true" :style="{ width: '520px' }">
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
          <label>Algorithme</label>
          <select v-model="form.model_type" class="wh-input">
            <option value="linear_regression">Régression linéaire</option>
            <option value="arima">ARIMA</option>
            <option value="exponential_smoothing">Lissage exponentiel</option>
            <option value="prophet">Prophet</option>
            <option value="lstm">LSTM</option>
          </select>
        </div>
        <div class="wh-form-field">
          <label>Métrique suivie</label>
          <input v-model="form.metric_name" class="wh-input" placeholder="ex: revenue, ticket_volume" />
        </div>
        <div class="wh-form-field">
          <label>Source de la métrique (ID)</label>
          <input v-model.number="form.metric_source_id" type="number" min="1" class="wh-input" />
        </div>
        <div class="wh-form-field">
          <label>Fréquence des données</label>
          <select v-model="form.data_frequency" class="wh-input">
            <option value="hourly">Horaire</option>
            <option value="daily">Quotidienne</option>
            <option value="weekly">Hebdomadaire</option>
            <option value="monthly">Mensuelle</option>
          </select>
        </div>
        <div class="wh-form-field">
          <label>Historique analysé (jours)</label>
          <input v-model.number="form.lookback_days" type="number" min="7" class="wh-input" />
        </div>
        <div class="wh-form-field">
          <label>Horizon de prévision (jours)</label>
          <input v-model.number="form.forecast_horizon" type="number" min="1" class="wh-input" />
        </div>
      </div>
      <template #footer>
        <button class="wh-btn wh-btn-secondary" @click="showDialog = false">Annuler</button>
        <button class="wh-btn wh-btn-primary" :disabled="saving" @click="submitForm">
          {{ saving ? 'Enregistrement...' : 'Créer' }}
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

const models = ref([])
const loadingModels = ref(true)
const saving = ref(false)
const training = ref(null)
const deploying = ref(null)
const archiving = ref(null)
const showDialog = ref(false)

const defaultForm = () => ({
  name: '',
  description: '',
  model_type: 'linear_regression',
  metric_name: '',
  metric_source_id: null,
  data_frequency: 'daily',
  lookback_days: 30,
  forecast_horizon: 7,
})

const form = ref(defaultForm())

const loadModels = async () => {
  loadingModels.value = true
  try {
    const { data } = await axios.get('/api/v1/bi/forecast-models')
    models.value = data.data ?? data
  } catch (error) {
    console.error('Error loading forecast models:', error)
  } finally {
    loadingModels.value = false
  }
}

onMounted(() => {
  loadModels()
})

const openCreate = () => {
  form.value = defaultForm()
  showDialog.value = true
}

const submitForm = async () => {
  saving.value = true
  try {
    await axios.post('/api/v1/bi/forecast-models', form.value)
    toast.add({ severity: 'success', summary: 'Créé', detail: 'Modèle de prévision créé', life: 3000 })
    showDialog.value = false
    await loadModels()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec de la création', life: 4000 })
  } finally {
    saving.value = false
  }
}

const trainModel = async (m) => {
  training.value = m.id
  try {
    await axios.post(`/api/v1/bi/forecast-models/${m.id}/train`)
    toast.add({ severity: 'success', summary: 'Entraînement lancé', detail: `Entraînement de "${m.name}" démarré`, life: 3000 })
    await loadModels()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec de l\'entraînement', life: 4000 })
  } finally {
    training.value = null
  }
}

const deployModel = async (m) => {
  deploying.value = m.id
  try {
    await axios.post(`/api/v1/bi/forecast-models/${m.id}/deploy`)
    toast.add({ severity: 'success', summary: 'Déployé', detail: `"${m.name}" est maintenant déployé`, life: 3000 })
    await loadModels()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec du déploiement', life: 4000 })
  } finally {
    deploying.value = null
  }
}

const archiveModel = async (m) => {
  archiving.value = m.id
  try {
    await axios.post(`/api/v1/bi/forecast-models/${m.id}/archive`)
    toast.add({ severity: 'success', summary: 'Archivé', detail: `"${m.name}" a été archivé`, life: 3000 })
    await loadModels()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec de l\'archivage', life: 4000 })
  } finally {
    archiving.value = null
  }
}

const deleteModel = async (m) => {
  if (!confirm(`Supprimer le modèle "${m.name}" ?`)) return
  try {
    await axios.delete(`/api/v1/bi/forecast-models/${m.id}`)
    toast.add({ severity: 'success', summary: 'Supprimé', detail: 'Modèle de prévision supprimé', life: 3000 })
    await loadModels()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec de la suppression', life: 4000 })
  }
}

const countByStatus = (status) => models.value.filter((m) => m.status === status).length

const algorithmLabel = (type) => ({
  linear_regression: 'Régression linéaire',
  arima: 'ARIMA',
  exponential_smoothing: 'Lissage exponentiel',
  prophet: 'Prophet',
  lstm: 'LSTM',
}[type] ?? type)

const frequencyLabel = (freq) => ({
  hourly: 'Horaire',
  daily: 'Quotidienne',
  weekly: 'Hebdomadaire',
  monthly: 'Mensuelle',
}[freq] ?? freq)

const statusLabel = (status) => ({
  draft: 'Brouillon',
  trained: 'Entraîné',
  deployed: 'Déployé',
  archived: 'Archivé',
}[status] ?? status)

const statusBadgeClass = (status) => ({
  draft: 'wh-badge-slate',
  trained: 'wh-badge-amber',
  deployed: 'wh-badge-green',
  archived: 'wh-badge-slate',
}[status] ?? 'wh-badge-slate')

const accuracyLabel = (m) => {
  if (m.mape === null || m.mape === undefined) return '—'
  const accuracy = Math.max(0, 100 - Number(m.mape))
  return `${accuracy.toFixed(1)}%`
}
</script>

<style scoped>
.wh-page { max-width: 1200px; margin: 0 auto; padding: 24px; }
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
.wh-row-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.wh-loading-state, .wh-empty-state { padding: 16px; text-align: center; color: #6B7280; font-size: 13px; }
.wh-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; border: 1px solid transparent; }
.wh-btn-primary { background: #4F46E5; color: #fff; }
.wh-btn-secondary { background: #F3F4F6; color: #1F2937; border-color: #E5E7EB; }
.wh-dialog-form { display: flex; flex-direction: column; gap: 12px; }
.wh-form-field { display: flex; flex-direction: column; gap: 4px; }
.wh-input { border: 1px solid #E5E7EB; border-radius: 6px; padding: 8px 10px; font-size: 13px; }
</style>
