<template>
  <AppLayout>
    <Head title="Visualisations personnalisées" />

    <div class="wh-page">
      <div class="wh-page-header">
        <div>
          <h1 class="wh-page-title">Visualisations personnalisées</h1>
          <p class="wh-page-subtitle">Heatmaps, graphiques 3D et cascades — créez, partagez et suivez leur performance</p>
        </div>
        <div class="wh-header-actions">
          <button class="wh-btn wh-btn-primary" @click="openCreate">
            <i class="pi pi-plus" /> Nouvelle visualisation
          </button>
        </div>
      </div>

      <div class="wh-stat-row" v-if="!loadingVisualizations">
        <div class="wh-stat-card">
          <span class="wh-stat-label">Total visualisations</span>
          <span class="wh-stat-value">{{ visualizations.length }}</span>
        </div>
        <div class="wh-stat-card">
          <span class="wh-stat-label">Temps réel activé</span>
          <span class="wh-stat-value">{{ realTimeCount }}</span>
        </div>
        <div class="wh-stat-card">
          <span class="wh-stat-label">Score de performance moyen</span>
          <span class="wh-stat-value">{{ avgPerformanceScore }}</span>
        </div>
      </div>

      <section class="wh-section">
        <h2 class="wh-section-title">Mes visualisations</h2>
        <div v-if="loadingVisualizations" class="wh-loading-state"><i class="pi pi-spin pi-spinner" /> Chargement...</div>
        <table v-else class="wh-table">
          <thead>
            <tr>
              <th>Nom</th><th>Type</th><th>Temps réel</th><th>Intervalle (s)</th><th>Score perf.</th><th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="v in visualizations" :key="v.id">
              <td>{{ v.name }}</td>
              <td><span class="wh-badge wh-badge-slate">{{ typeLabel(v.type) }}</span></td>
              <td>
                <span :class="['wh-badge', v.real_time_enabled ? 'wh-badge-green' : 'wh-badge-slate']">
                  {{ v.real_time_enabled ? 'Actif' : 'Inactif' }}
                </span>
              </td>
              <td>{{ v.refresh_interval }}</td>
              <td>{{ v.performance_score ?? '—' }}</td>
              <td class="wh-row-actions">
                <button class="wh-row-btn" title="Modifier" @click="openEdit(v)"><i class="pi pi-pencil" /></button>
                <button class="wh-row-btn" title="Partager" @click="openShare(v)"><i class="pi pi-share-alt" /></button>
                <button class="wh-row-btn" title="Exporter" @click="exportVisualization(v)"><i class="pi pi-download" /></button>
                <button class="wh-row-btn" title="Supprimer" @click="deleteVisualization(v)"><i class="pi pi-trash" style="color:#DC2626" /></button>
              </td>
            </tr>
            <tr v-if="visualizations.length === 0">
              <td colspan="6" class="wh-empty-state">Aucune visualisation personnalisée.</td>
            </tr>
          </tbody>
        </table>
      </section>

      <section class="wh-section">
        <h2 class="wh-section-title">Modèles disponibles</h2>
        <div v-if="loadingTemplates" class="wh-loading-state"><i class="pi pi-spin pi-spinner" /> Chargement...</div>
        <table v-else class="wh-table">
          <thead>
            <tr>
              <th>Nom</th><th>Type de graphique</th><th>Public</th><th>Utilisations</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in templates" :key="t.id">
              <td>{{ t.name }}</td>
              <td>{{ typeLabel(t.chart_type) }}</td>
              <td>
                <span :class="['wh-badge', t.is_public ? 'wh-badge-green' : 'wh-badge-slate']">
                  {{ t.is_public ? 'Oui' : 'Non' }}
                </span>
              </td>
              <td>{{ t.usage_count ?? 0 }}</td>
            </tr>
            <tr v-if="templates.length === 0">
              <td colspan="4" class="wh-empty-state">Aucun modèle disponible.</td>
            </tr>
          </tbody>
        </table>
      </section>
    </div>

    <Dialog v-model:visible="showDialog" :header="editing ? 'Modifier la visualisation' : 'Nouvelle visualisation'" :modal="true" :style="{ width: '520px' }">
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
          <label>Type de graphique</label>
          <select v-model="form.type" class="wh-input" :disabled="!!editing">
            <option value="heatmap">Heatmap</option>
            <option value="3d_scatter">Nuage de points 3D</option>
            <option value="3d_surface">Surface 3D</option>
            <option value="bubble">Bulles</option>
            <option value="waterfall">Cascade</option>
          </select>
        </div>
        <div class="wh-form-field">
          <label>Configuration (JSON)</label>
          <textarea v-model="configText" class="wh-input" rows="3" placeholder='{"x_axis": "region", "y_axis": "month"}' />
        </div>
        <div class="wh-form-field">
          <label>Source de données (JSON)</label>
          <textarea v-model="dataSourceText" class="wh-input" rows="3" placeholder='{"module": "Sales", "metric": "revenue"}' />
        </div>
        <div class="wh-form-field">
          <label>Intervalle de rafraîchissement (secondes)</label>
          <input v-model.number="form.refresh_interval" type="number" min="10" class="wh-input" />
        </div>
        <div class="wh-form-field wh-form-field-checkbox">
          <label><input v-model="form.real_time_enabled" type="checkbox" /> Temps réel activé</label>
        </div>
      </div>
      <template #footer>
        <button class="wh-btn wh-btn-secondary" @click="showDialog = false">Annuler</button>
        <button class="wh-btn wh-btn-primary" :disabled="saving" @click="submitForm">
          {{ saving ? 'Enregistrement...' : 'Enregistrer' }}
        </button>
      </template>
    </Dialog>

    <Dialog v-model:visible="showShareDialog" header="Partager la visualisation" :modal="true" :style="{ width: '420px' }">
      <div class="wh-dialog-form">
        <div class="wh-form-field">
          <label>Destinataires (e-mails séparés par des virgules)</label>
          <input v-model="shareForm.recipientsText" class="wh-input" placeholder="alice@example.com, bob@example.com" />
        </div>
        <div class="wh-form-field">
          <label>Message (optionnel)</label>
          <textarea v-model="shareForm.message" class="wh-input" rows="2" />
        </div>
      </div>
      <template #footer>
        <button class="wh-btn wh-btn-secondary" @click="showShareDialog = false">Annuler</button>
        <button class="wh-btn wh-btn-primary" :disabled="sharing" @click="submitShare">
          {{ sharing ? 'Envoi...' : 'Partager' }}
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

const visualizations = ref([])
const templates = ref([])

const loadingVisualizations = ref(true)
const loadingTemplates = ref(true)
const saving = ref(false)
const sharing = ref(false)
const showDialog = ref(false)
const showShareDialog = ref(false)
const editing = ref(null)
const sharingTarget = ref(null)

const defaultForm = () => ({
  name: '',
  description: '',
  type: 'heatmap',
  refresh_interval: 60,
  real_time_enabled: false,
})

const form = ref(defaultForm())
const configText = ref('{}')
const dataSourceText = ref('{}')

const defaultShareForm = () => ({ recipientsText: '', message: '' })
const shareForm = ref(defaultShareForm())

const typeLabels = {
  heatmap: 'Heatmap',
  '3d_scatter': 'Nuage de points 3D',
  '3d_surface': 'Surface 3D',
  bubble: 'Bulles',
  waterfall: 'Cascade',
}
const typeLabel = (type) => typeLabels[type] ?? type

const realTimeCount = computed(() => visualizations.value.filter((v) => v.real_time_enabled).length)
const avgPerformanceScore = computed(() => {
  const scored = visualizations.value.filter((v) => v.performance_score !== null && v.performance_score !== undefined)
  if (scored.length === 0) return '—'
  return Math.round(scored.reduce((sum, v) => sum + v.performance_score, 0) / scored.length)
})

const loadVisualizations = async () => {
  loadingVisualizations.value = true
  try {
    const { data } = await axios.get('/api/v1/bi/visualizations')
    visualizations.value = data.data ?? data
  } catch (error) {
    console.error('Error loading visualizations:', error)
  } finally {
    loadingVisualizations.value = false
  }
}

const loadTemplates = async () => {
  loadingTemplates.value = true
  try {
    const { data } = await axios.get('/api/v1/bi/visualizations/templates')
    templates.value = data.data ?? data
  } catch (error) {
    console.error('Error loading visualization templates:', error)
  } finally {
    loadingTemplates.value = false
  }
}

onMounted(() => {
  loadVisualizations()
  loadTemplates()
})

const openCreate = () => {
  editing.value = null
  form.value = defaultForm()
  configText.value = '{}'
  dataSourceText.value = '{}'
  showDialog.value = true
}

const openEdit = (v) => {
  editing.value = v
  form.value = {
    name: v.name,
    description: v.description ?? '',
    type: v.type,
    refresh_interval: v.refresh_interval,
    real_time_enabled: !!v.real_time_enabled,
  }
  configText.value = JSON.stringify(v.config ?? {}, null, 2)
  dataSourceText.value = JSON.stringify(v.data_source ?? {}, null, 2)
  showDialog.value = true
}

const parseJsonField = (text, label) => {
  try {
    return JSON.parse(text || '{}')
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: `${label} : JSON invalide`, life: 4000 })
    throw e
  }
}

const submitForm = async () => {
  let config
  let dataSource
  try {
    config = parseJsonField(configText.value, 'Configuration')
    dataSource = parseJsonField(dataSourceText.value, 'Source de données')
  } catch {
    return
  }

  saving.value = true
  try {
    if (editing.value) {
      await axios.put(`/api/v1/bi/visualizations/${editing.value.id}`, {
        name: form.value.name,
        description: form.value.description,
        config,
        real_time_enabled: form.value.real_time_enabled,
        refresh_interval: form.value.refresh_interval,
      })
      toast.add({ severity: 'success', summary: 'Modifiée', detail: 'Visualisation mise à jour', life: 3000 })
    } else {
      await axios.post('/api/v1/bi/visualizations', {
        name: form.value.name,
        description: form.value.description,
        type: form.value.type,
        config,
        data_source: dataSource,
        real_time_enabled: form.value.real_time_enabled,
        refresh_interval: form.value.refresh_interval,
      })
      toast.add({ severity: 'success', summary: 'Créée', detail: 'Visualisation créée', life: 3000 })
    }
    showDialog.value = false
    await loadVisualizations()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? "Échec de l'enregistrement", life: 4000 })
  } finally {
    saving.value = false
  }
}

const deleteVisualization = async (v) => {
  if (!confirm(`Supprimer la visualisation "${v.name}" ?`)) return
  try {
    await axios.delete(`/api/v1/bi/visualizations/${v.id}`)
    toast.add({ severity: 'success', summary: 'Supprimée', detail: 'Visualisation supprimée', life: 3000 })
    await loadVisualizations()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec de la suppression', life: 4000 })
  }
}

const exportVisualization = async (v) => {
  try {
    await axios.post(`/api/v1/bi/visualizations/${v.id}/export`, { format: 'png' })
    toast.add({ severity: 'success', summary: 'Export lancé', detail: `Export de "${v.name}" en cours`, life: 3000 })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? "Échec de l'export", life: 4000 })
  }
}

const openShare = (v) => {
  sharingTarget.value = v
  shareForm.value = defaultShareForm()
  showShareDialog.value = true
}

const submitShare = async () => {
  const recipients = shareForm.value.recipientsText
    .split(',')
    .map((r) => r.trim())
    .filter(Boolean)

  if (recipients.length === 0) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Au moins un destinataire est requis', life: 4000 })
    return
  }

  sharing.value = true
  try {
    await axios.post(`/api/v1/bi/visualizations/${sharingTarget.value.id}/share`, {
      recipients,
      message: shareForm.value.message,
    })
    toast.add({ severity: 'success', summary: 'Partagée', detail: 'Visualisation partagée avec succès', life: 3000 })
    showShareDialog.value = false
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec du partage', life: 4000 })
  } finally {
    sharing.value = false
  }
}
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
