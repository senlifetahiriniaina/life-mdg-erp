<template>
  <AppLayout>
    <Head title="Data Stories" />

    <div class="wh-page">
      <div class="wh-page-header">
        <div>
          <h1 class="wh-page-title">Data Stories</h1>
          <p class="wh-page-subtitle">Présentations narratives de données : diapositives, flux narratifs et analyse d'audience</p>
        </div>
        <div class="wh-header-actions">
          <button class="wh-btn wh-btn-primary" @click="openCreate">
            <i class="pi pi-plus" /> Nouvelle story
          </button>
        </div>
      </div>

      <section class="wh-section">
        <div v-if="loading" class="wh-loading-state"><i class="pi pi-spin pi-spinner" /> Chargement...</div>
        <table v-else class="wh-table">
          <thead>
            <tr>
              <th>Titre</th><th>Statut</th><th>Diapositives</th><th>Vues</th><th>Public</th><th>Mise à jour</th><th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in stories" :key="s.id">
              <td>
                <div class="wh-cell-title">{{ s.title }}</div>
                <div v-if="s.description" class="wh-cell-subtitle">{{ s.description }}</div>
              </td>
              <td>
                <span :class="['wh-badge', statusBadgeClass(s.status)]">{{ statusLabel(s.status) }}</span>
              </td>
              <td>{{ s.slide_count ?? 0 }}</td>
              <td>{{ totalViews(s) }}</td>
              <td>
                <span :class="['wh-badge', s.is_public ? 'wh-badge-green' : 'wh-badge-slate']">
                  {{ s.is_public ? 'Oui' : 'Non' }}
                </span>
              </td>
              <td>{{ formatDate(s.updated_at) }}</td>
              <td class="wh-row-actions">
                <button class="wh-row-btn" title="Modifier" @click="openEdit(s)"><i class="pi pi-pencil" /></button>
                <button
                  v-if="s.status !== 'published'"
                  class="wh-row-btn"
                  title="Publier"
                  :disabled="publishingId === s.id"
                  @click="publishStory(s)"
                >
                  <i class="pi pi-send" />
                </button>
                <button class="wh-row-btn" title="Supprimer" @click="deleteStory(s)"><i class="pi pi-trash" style="color:#DC2626" /></button>
              </td>
            </tr>
            <tr v-if="stories.length === 0">
              <td colspan="7" class="wh-empty-state">Aucune data story pour l'instant.</td>
            </tr>
          </tbody>
        </table>
      </section>
    </div>

    <Dialog v-model:visible="showDialog" :header="editing ? 'Modifier la story' : 'Nouvelle data story'" :modal="true" :style="{ width: '480px' }">
      <div class="wh-dialog-form">
        <div class="wh-form-field">
          <label>Titre</label>
          <input v-model="form.title" class="wh-input" />
        </div>
        <div class="wh-form-field">
          <label>Description</label>
          <textarea v-model="form.description" class="wh-input" rows="2" />
        </div>
        <div class="wh-form-field">
          <label>Résumé</label>
          <textarea v-model="form.summary" class="wh-input" rows="2" />
        </div>
        <div class="wh-form-field wh-form-field-checkbox">
          <label><input v-model="form.is_public" type="checkbox" /> Publique (accessible sans authentification)</label>
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

const stories = ref([])
const loading = ref(true)
const saving = ref(false)
const publishingId = ref(null)
const showDialog = ref(false)
const editing = ref(null)

const defaultForm = () => ({
  title: '',
  description: '',
  summary: '',
  is_public: false,
})

const form = ref(defaultForm())

const loadStories = async () => {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/bi/data-stories')
    stories.value = data.data ?? data
  } catch (error) {
    console.error('Error loading data stories:', error)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadStories()
})

const openCreate = () => {
  editing.value = null
  form.value = defaultForm()
  showDialog.value = true
}

const openEdit = (s) => {
  editing.value = s
  form.value = {
    title: s.title,
    description: s.description ?? '',
    summary: s.summary ?? '',
    is_public: !!s.is_public,
  }
  showDialog.value = true
}

const submitForm = async () => {
  saving.value = true
  try {
    if (editing.value) {
      await axios.put(`/api/v1/bi/data-stories/${editing.value.id}`, form.value)
      toast.add({ severity: 'success', summary: 'Modifiée', detail: 'Data story mise à jour', life: 3000 })
    } else {
      await axios.post('/api/v1/bi/data-stories', form.value)
      toast.add({ severity: 'success', summary: 'Créée', detail: 'Data story créée', life: 3000 })
    }
    showDialog.value = false
    await loadStories()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec de l\'enregistrement', life: 4000 })
  } finally {
    saving.value = false
  }
}

const deleteStory = async (s) => {
  if (!confirm(`Supprimer la story "${s.title}" ?`)) return
  try {
    await axios.delete(`/api/v1/bi/data-stories/${s.id}`)
    toast.add({ severity: 'success', summary: 'Supprimée', detail: 'Data story supprimée', life: 3000 })
    await loadStories()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec de la suppression', life: 4000 })
  }
}

const publishStory = async (s) => {
  publishingId.value = s.id
  try {
    await axios.post(`/api/v1/bi/data-stories/${s.id}/publish`)
    toast.add({ severity: 'success', summary: 'Publiée', detail: 'Data story publiée', life: 3000 })
    await loadStories()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec de la publication', life: 4000 })
  } finally {
    publishingId.value = null
  }
}

const totalViews = (s) => {
  if (Array.isArray(s.analytics) && s.analytics.length > 0) {
    return s.analytics.reduce((sum, a) => sum + (a.total_views ?? 0), 0)
  }
  return 0
}

const statusLabel = (status) => ({
  draft: 'Brouillon',
  published: 'Publiée',
  archived: 'Archivée',
})[status] ?? status

const statusBadgeClass = (status) => ({
  draft: 'wh-badge-slate',
  published: 'wh-badge-green',
  archived: 'wh-badge-amber',
})[status] ?? 'wh-badge-slate'

const formatDate = (d) => d ? new Date(d).toLocaleString('fr-FR') : '—'
</script>

<style scoped>
.wh-page { max-width: 1100px; margin: 0 auto; padding: 24px; }
.wh-page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; }
.wh-header-actions { display: flex; gap: 8px; }
.wh-page-title { font-size: 20px; font-weight: 700; margin: 0; }
.wh-page-subtitle { font-size: 13px; color: #6B7280; margin-top: 2px; }
.wh-section { margin-bottom: 32px; }
.wh-table { width: 100%; border-collapse: collapse; }
.wh-table th, .wh-table td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #E5E7EB; font-size: 13px; }
.wh-table th { color: #6B7280; font-weight: 600; font-size: 12px; text-transform: uppercase; }
.wh-cell-title { font-weight: 600; }
.wh-cell-subtitle { font-size: 12px; color: #6B7280; margin-top: 2px; }
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
