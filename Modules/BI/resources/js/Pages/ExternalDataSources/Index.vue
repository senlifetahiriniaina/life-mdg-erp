<template>
  <AppLayout>
    <Head title="Sources de données externes" />

    <div class="wh-page">
      <div class="wh-page-header">
        <div>
          <h1 class="wh-page-title">Sources de données externes</h1>
          <p class="wh-page-subtitle">Intégrations tierces (Google Analytics, Shopify, Salesforce, HubSpot, Stripe, API personnalisée) — connexion, statut et synchronisation</p>
        </div>
        <div class="wh-header-actions">
          <button class="wh-btn wh-btn-primary" @click="openCreate">
            <i class="pi pi-plus" /> Nouvelle source
          </button>
        </div>
      </div>

      <div class="wh-stat-row" v-if="!loading">
        <div class="wh-stat-card">
          <span class="wh-stat-label">Sources connectées</span>
          <span class="wh-stat-value">{{ connectedCount }}</span>
        </div>
        <div class="wh-stat-card">
          <span class="wh-stat-label">Total des sources</span>
          <span class="wh-stat-value">{{ sources.length }}</span>
        </div>
      </div>

      <section class="wh-section">
        <h2 class="wh-section-title">Sources configurées</h2>
        <div v-if="loading" class="wh-loading-state"><i class="pi pi-spin pi-spinner" /> Chargement...</div>
        <table v-else class="wh-table">
          <thead>
            <tr>
              <th>Nom</th><th>Type</th><th>Authentification</th><th>Statut</th><th>Dernière synchro réussie</th><th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in sources" :key="s.id">
              <td>
                {{ s.name }}
                <div v-if="s.description" class="wh-row-subtext">{{ s.description }}</div>
              </td>
              <td>{{ sourceTypeLabel(s.source_type) }}</td>
              <td>{{ authTypeLabel(s.authentication_type) }}</td>
              <td>
                <span :class="['wh-badge', statusBadgeClass(s.status)]">
                  {{ statusLabel(s.status) }}
                </span>
              </td>
              <td>{{ formatDate(s.last_successful_sync) }}</td>
              <td class="wh-row-actions">
                <button
                  v-if="s.status !== 'connected'"
                  class="wh-row-btn"
                  title="Connecter"
                  :disabled="actingId === s.id"
                  @click="connectSource(s)"
                >
                  <i class="pi pi-link" />
                </button>
                <button
                  v-else
                  class="wh-row-btn"
                  title="Déconnecter"
                  :disabled="actingId === s.id"
                  @click="disconnectSource(s)"
                >
                  <i class="pi pi-unlock" />
                </button>
                <button class="wh-row-btn" title="Supprimer" @click="deleteSource(s)">
                  <i class="pi pi-trash" style="color:#DC2626" />
                </button>
              </td>
            </tr>
            <tr v-if="sources.length === 0">
              <td colspan="6" class="wh-empty-state">Aucune source de données externe configurée.</td>
            </tr>
          </tbody>
        </table>
      </section>
    </div>

    <Dialog v-model:visible="showDialog" header="Nouvelle source de données externe" :modal="true" :style="{ width: '480px' }">
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
          <label>Type de source</label>
          <select v-model="form.source_type" class="wh-input">
            <option value="google_analytics">Google Analytics</option>
            <option value="shopify">Shopify</option>
            <option value="salesforce">Salesforce</option>
            <option value="hubspot">HubSpot</option>
            <option value="stripe">Stripe</option>
            <option value="custom_api">API personnalisée</option>
          </select>
        </div>
        <div class="wh-form-field">
          <label>Type d'authentification</label>
          <select v-model="form.authentication_type" class="wh-input">
            <option value="oauth">OAuth</option>
            <option value="api_key">Clé API</option>
            <option value="basic_auth">Authentification basique</option>
            <option value="bearer_token">Jeton Bearer</option>
          </select>
        </div>
        <div class="wh-form-field">
          <label>Configuration de connexion (JSON)</label>
          <textarea v-model="connectionConfigText" class="wh-input" rows="4" placeholder='{"api_endpoint": "https://..."}' />
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

const sources = ref([])
const loading = ref(true)
const saving = ref(false)
const actingId = ref(null)
const showDialog = ref(false)

const defaultForm = () => ({
  name: '',
  description: '',
  source_type: 'custom_api',
  authentication_type: 'api_key',
})

const form = ref(defaultForm())
const connectionConfigText = ref('{}')

const connectedCount = computed(() => sources.value.filter((s) => s.status === 'connected').length)

const loadSources = async () => {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/bi/external-data-sources')
    sources.value = data.data ?? data
  } catch (error) {
    console.error('Error loading external data sources:', error)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadSources()
})

const openCreate = () => {
  form.value = defaultForm()
  connectionConfigText.value = '{}'
  showDialog.value = true
}

const submitForm = async () => {
  let connectionConfig
  try {
    connectionConfig = connectionConfigText.value.trim() === '' ? {} : JSON.parse(connectionConfigText.value)
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'La configuration de connexion doit être un JSON valide', life: 4000 })
    return
  }

  saving.value = true
  try {
    await axios.post('/api/v1/bi/external-data-sources', {
      ...form.value,
      connection_config: connectionConfig,
    })
    toast.add({ severity: 'success', summary: 'Créée', detail: 'Source de données créée', life: 3000 })
    showDialog.value = false
    await loadSources()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec de l\'enregistrement', life: 4000 })
  } finally {
    saving.value = false
  }
}

const connectSource = async (s) => {
  actingId.value = s.id
  try {
    await axios.post(`/api/v1/bi/external-data-sources/${s.id}/connect`)
    toast.add({ severity: 'success', summary: 'Connectée', detail: `${s.name} est maintenant connectée`, life: 3000 })
    await loadSources()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec de la connexion', life: 4000 })
  } finally {
    actingId.value = null
  }
}

const disconnectSource = async (s) => {
  actingId.value = s.id
  try {
    await axios.post(`/api/v1/bi/external-data-sources/${s.id}/disconnect`)
    toast.add({ severity: 'success', summary: 'Déconnectée', detail: `${s.name} a été déconnectée`, life: 3000 })
    await loadSources()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec de la déconnexion', life: 4000 })
  } finally {
    actingId.value = null
  }
}

const deleteSource = async (s) => {
  if (!confirm(`Supprimer la source "${s.name}" ?`)) return
  try {
    await axios.delete(`/api/v1/bi/external-data-sources/${s.id}`)
    toast.add({ severity: 'success', summary: 'Supprimée', detail: 'Source de données supprimée', life: 3000 })
    await loadSources()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec de la suppression', life: 4000 })
  }
}

const sourceTypeLabel = (type) => ({
  google_analytics: 'Google Analytics',
  shopify: 'Shopify',
  salesforce: 'Salesforce',
  hubspot: 'HubSpot',
  stripe: 'Stripe',
  custom_api: 'API personnalisée',
}[type] ?? type)

const authTypeLabel = (type) => ({
  oauth: 'OAuth',
  api_key: 'Clé API',
  basic_auth: 'Authentification basique',
  bearer_token: 'Jeton Bearer',
}[type] ?? type)

const statusLabel = (status) => ({
  inactive: 'Inactive',
  connected: 'Connectée',
  syncing: 'Synchronisation...',
  error: 'Erreur',
}[status] ?? status)

const statusBadgeClass = (status) => ({
  connected: 'wh-badge-green',
  syncing: 'wh-badge-amber',
  error: 'wh-badge-red',
  inactive: 'wh-badge-slate',
}[status] ?? 'wh-badge-slate')

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
.wh-row-subtext { font-size: 11px; color: #9CA3AF; margin-top: 2px; }
.wh-badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
.wh-badge-green { background: #D1FAE5; color: #065F46; }
.wh-badge-slate { background: #F1F5F9; color: #475569; }
.wh-badge-amber { background: #FEF3C7; color: #92400E; }
.wh-badge-red { background: #FEE2E2; color: #991B1B; }
.wh-row-actions { display: flex; gap: 6px; }
.wh-row-btn { background: none; border: none; cursor: pointer; padding: 4px; }
.wh-row-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.wh-loading-state, .wh-empty-state { padding: 16px; text-align: center; color: #6B7280; font-size: 13px; }
.wh-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; border: 1px solid transparent; }
.wh-btn-primary { background: #4F46E5; color: #fff; }
.wh-btn-secondary { background: #F3F4F6; color: #1F2937; border-color: #E5E7EB; }
.wh-dialog-form { display: flex; flex-direction: column; gap: 12px; }
.wh-form-field { display: flex; flex-direction: column; gap: 4px; }
.wh-input { border: 1px solid #E5E7EB; border-radius: 6px; padding: 8px 10px; font-size: 13px; }
</style>
