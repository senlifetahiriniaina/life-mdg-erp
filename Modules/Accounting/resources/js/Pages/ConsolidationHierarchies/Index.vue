<template>
  <AppLayout>
    <Head title="Hiérarchies de consolidation" />

    <div class="wh-page">
      <div class="wh-page-header">
        <div>
          <h1 class="wh-page-title">Hiérarchies de consolidation</h1>
          <p class="wh-page-subtitle">Arbre de détention (holding / filiale / succursale / division) avec périodes et éliminations</p>
        </div>
        <button v-if="can('accounting', 'consolidation', 'create')" class="wh-btn wh-btn-primary" @click="openCreate">
          <i class="pi pi-plus" /> Nouvelle hiérarchie
        </button>
      </div>

      <div class="wh-filters">
        <input v-model="filters.search" class="wh-input" placeholder="Rechercher par nom..." @keyup.enter="loadHierarchies" />
        <select v-model="filters.type" class="wh-input" @change="loadHierarchies">
          <option value="">Tous les types</option>
          <option value="holding">Holding</option>
          <option value="subsidiary">Filiale</option>
          <option value="branch">Succursale</option>
          <option value="division">Division</option>
        </select>
        <select v-model="filters.is_active" class="wh-input" @change="loadHierarchies">
          <option value="">Tous les statuts</option>
          <option value="1">Actif</option>
          <option value="0">Inactif</option>
        </select>
        <button class="wh-btn wh-btn-secondary" @click="loadHierarchies">Rechercher</button>
      </div>

      <div v-if="loading" class="wh-loading-state"><i class="pi pi-spin pi-spinner" /> Chargement...</div>

      <table v-else class="wh-table">
        <thead>
          <tr>
            <th>Nom</th><th>Type</th><th>Société</th><th>Société mère</th><th>% Détention</th><th>Date d'effet</th><th>Statut</th><th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="h in hierarchies" :key="h.id">
            <td>{{ h.name }}</td>
            <td>{{ typeLabel(h.type) }}</td>
            <td>{{ h.company?.name ?? '—' }}</td>
            <td>{{ h.parent_company?.name ?? '—' }}</td>
            <td>{{ h.ownership_percentage }}%</td>
            <td>{{ formatDate(h.effective_date) }}</td>
            <td>
              <span :class="['wh-badge', h.is_active ? 'wh-badge-green' : 'wh-badge-slate']">
                {{ h.is_active ? 'Actif' : 'Inactif' }}
              </span>
            </td>
            <td class="wh-row-actions">
              <button v-if="can('accounting', 'consolidation', 'update') && h.status === 'draft'" class="wh-row-btn" title="Modifier" @click="openEdit(h)">
                <i class="pi pi-pencil" />
              </button>
              <button v-if="can('accounting', 'consolidation', 'delete') && h.status === 'draft'" class="wh-row-btn" title="Supprimer" @click="deleteHierarchy(h)">
                <i class="pi pi-trash" style="color:#DC2626" />
              </button>
            </td>
          </tr>
          <tr v-if="hierarchies.length === 0">
            <td colspan="8" class="wh-empty-state">Aucune hiérarchie de consolidation trouvée.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <Dialog v-model:visible="showDialog" :header="editing ? 'Modifier la hiérarchie' : 'Nouvelle hiérarchie'" :modal="true" :style="{ width: '480px' }">
      <div class="wh-dialog-form">
        <div class="wh-form-field">
          <label>Nom</label>
          <input v-model="form.name" class="wh-input" />
        </div>
        <div class="wh-form-field">
          <label>Description</label>
          <textarea v-model="form.description" class="wh-input" rows="2" />
        </div>
        <div class="wh-form-field" v-if="!editing">
          <label>Type</label>
          <select v-model="form.type" class="wh-input">
            <option value="holding">Holding</option>
            <option value="subsidiary">Filiale</option>
            <option value="branch">Succursale</option>
            <option value="division">Division</option>
          </select>
        </div>
        <div class="wh-form-field">
          <label>% Détention</label>
          <input v-model.number="form.ownership_percentage" type="number" min="0" max="100" step="0.01" class="wh-input" />
        </div>
        <div class="wh-form-field" v-if="!editing">
          <label>Date d'effet</label>
          <input v-model="form.effective_date" type="date" class="wh-input" />
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
import { useRoleAccess } from '@/composables/useRoleAccess'

const { can } = useRoleAccess()
const toast = useToast()

const hierarchies = ref([])
const loading = ref(true)
const saving = ref(false)
const showDialog = ref(false)
const editing = ref(null)

const filters = ref({ search: '', type: '', is_active: '' })

// company_id is always derived server-side from the authenticated user
// (ConsolidationHierarchyController::store() overwrites whatever is sent),
// so the form never asks for it.
const form = ref({
  name: '',
  description: '',
  type: 'subsidiary',
  ownership_percentage: 100,
  effective_date: new Date().toISOString().slice(0, 10),
})

const loadHierarchies = async () => {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/accounting/consolidation-hierarchies', {
      params: {
        search: filters.value.search || undefined,
        type: filters.value.type || undefined,
        is_active: filters.value.is_active !== '' ? filters.value.is_active : undefined,
      },
    })
    hierarchies.value = data.data ?? data
  } catch (error) {
    console.error('Error loading hierarchies:', error)
  } finally {
    loading.value = false
  }
}

onMounted(loadHierarchies)

const resetForm = () => {
  form.value = {
    name: '', description: '', type: 'subsidiary',
    ownership_percentage: 100, effective_date: new Date().toISOString().slice(0, 10),
  }
}

const openCreate = () => {
  editing.value = null
  resetForm()
  showDialog.value = true
}

const openEdit = (h) => {
  editing.value = h
  form.value = {
    name: h.name,
    description: h.description ?? '',
    ownership_percentage: h.ownership_percentage,
  }
  showDialog.value = true
}

const submitForm = async () => {
  saving.value = true
  try {
    if (editing.value) {
      await axios.put(`/api/v1/accounting/consolidation-hierarchies/${editing.value.id}`, form.value)
      toast.add({ severity: 'success', summary: 'Modifiée', detail: 'Hiérarchie mise à jour', life: 3000 })
    } else {
      await axios.post('/api/v1/accounting/consolidation-hierarchies', form.value)
      toast.add({ severity: 'success', summary: 'Créée', detail: 'Hiérarchie de consolidation créée', life: 3000 })
    }
    showDialog.value = false
    await loadHierarchies()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec de l\'enregistrement', life: 4000 })
  } finally {
    saving.value = false
  }
}

const deleteHierarchy = async (h) => {
  if (!confirm(`Supprimer la hiérarchie "${h.name}" ?`)) return
  try {
    await axios.delete(`/api/v1/accounting/consolidation-hierarchies/${h.id}`)
    toast.add({ severity: 'success', summary: 'Supprimée', detail: 'Hiérarchie supprimée', life: 3000 })
    await loadHierarchies()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec de la suppression', life: 4000 })
  }
}

const typeLabel = (t) => ({ holding: 'Holding', subsidiary: 'Filiale', branch: 'Succursale', division: 'Division' }[t] ?? t)
const formatDate = (d) => d ? new Date(d).toLocaleDateString('fr-FR') : '—'
</script>

<style scoped>
.wh-page { max-width: 1100px; margin: 0 auto; padding: 24px; }
.wh-page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; }
.wh-page-title { font-size: 20px; font-weight: 700; margin: 0; }
.wh-page-subtitle { font-size: 13px; color: #6B7280; margin-top: 2px; }
.wh-filters { display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
.wh-input { padding: 7px 10px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px; }
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
