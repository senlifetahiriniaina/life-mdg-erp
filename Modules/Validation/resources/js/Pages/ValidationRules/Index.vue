<template>
  <AppLayout>
    <Head title="Règles de validation" />

    <div class="wh-page">
      <div class="wh-page-header">
        <div>
          <h1 class="wh-page-title">Règles de validation</h1>
          <p class="wh-page-subtitle">Moteur générique de validation de données — champ, type de règle, paramètres</p>
        </div>
        <button class="wh-btn wh-btn-primary" @click="openCreate">
          <i class="pi pi-plus" /> Nouvelle règle
        </button>
      </div>

      <AIAssistantPanel v-if="guidance" :guidance="guidance" />

      <div v-if="loading" class="wh-loading-state"><i class="pi pi-spin pi-spinner" /> Chargement...</div>

      <table v-else class="wh-table">
        <thead>
          <tr><th>Nom</th><th>Champ</th><th>Type</th><th>Message</th><th></th></tr>
        </thead>
        <tbody>
          <tr v-for="rule in rules" :key="rule.id">
            <td>{{ rule.name }}</td>
            <td><span class="wh-mono">{{ rule.field }}</span></td>
            <td><span class="wh-badge">{{ rule.type }}</span></td>
            <td>{{ rule.message ?? '—' }}</td>
            <td class="wh-row-actions">
              <button class="wh-row-btn" title="Modifier" @click="openEdit(rule)"><i class="pi pi-pencil" /></button>
              <button class="wh-row-btn" title="Supprimer" @click="deleteRule(rule)"><i class="pi pi-trash" style="color:#DC2626" /></button>
            </td>
          </tr>
          <tr v-if="rules.length === 0">
            <td colspan="5" class="wh-empty-state">Aucune règle de validation. Créez-en une pour commencer.</td>
          </tr>
        </tbody>
      </table>

      <div v-if="pagination.last_page > 1" class="wh-pagination">
        <button class="wh-btn wh-btn-secondary wh-btn-sm" :disabled="pagination.current_page <= 1" @click="loadRules(pagination.current_page - 1)">Précédent</button>
        <span>Page {{ pagination.current_page }} / {{ pagination.last_page }}</span>
        <button class="wh-btn wh-btn-secondary wh-btn-sm" :disabled="pagination.current_page >= pagination.last_page" @click="loadRules(pagination.current_page + 1)">Suivant</button>
      </div>
    </div>

    <Dialog v-model:visible="showDialog" :header="editing ? 'Modifier la règle' : 'Nouvelle règle'" :modal="true" :style="{ width: '460px' }">
      <div class="wh-dialog-form">
        <div class="wh-form-field">
          <label>Nom</label>
          <input v-model="form.name" class="wh-input" />
        </div>
        <div class="wh-form-field">
          <label>Champ</label>
          <input v-model="form.field" class="wh-input" placeholder="ex: email, amount, quantity" />
        </div>
        <div class="wh-form-field" v-if="!editing">
          <label>Type</label>
          <select v-model="form.type" class="wh-input">
            <option v-for="t in types" :key="t" :value="t">{{ t }}</option>
          </select>
        </div>
        <div class="wh-form-field" v-if="needsParams">
          <label>Paramètres (JSON)</label>
          <textarea v-model="paramsText" class="wh-input" rows="2" placeholder='{"min": 3}' />
          <span v-if="paramsError" class="wh-field-error">{{ paramsError }}</span>
        </div>
        <div class="wh-form-field">
          <label>Message d'erreur (optionnel)</label>
          <input v-model="form.message" class="wh-input" />
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
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import axios from 'axios'

const { guidance } = useAiAssistant('Validation', 'manage_validation_rules')
const toast = useToast()

const rules = ref([])
const loading = ref(true)
const saving = ref(false)
const showDialog = ref(false)
const editing = ref(null)
const paramsText = ref('{}')
const paramsError = ref('')

const types = ['required', 'email', 'required_if', 'confirmed', 'regex', 'min', 'between', 'min_items', 'unique']
// Types whose behavior is driven entirely by their params object rather than
// name/field/message alone.
const needsParams = computed(() => ['regex', 'min', 'between', 'min_items', 'unique', 'required_if'].includes(form.value.type))

const pagination = ref({ current_page: 1, last_page: 1 })

const form = ref({ name: '', field: '', type: 'required', message: '' })

const resetForm = () => {
  form.value = { name: '', field: '', type: 'required', message: '' }
  paramsText.value = '{}'
  paramsError.value = ''
}

const loadRules = async (page = 1) => {
  loading.value = true
  try {
    // Raw Laravel paginator (not the {"data":...} envelope used elsewhere in
    // this app) — ValidationRuleController::index() returns the paginator
    // object directly.
    const { data } = await axios.get('/api/v1/validation-rules', { params: { page } })
    rules.value = data.data
    pagination.value = { current_page: data.current_page, last_page: data.last_page }
  } catch (error) {
    console.error('Error loading validation rules:', error)
  } finally {
    loading.value = false
  }
}

onMounted(() => loadRules())

const openCreate = () => {
  editing.value = null
  resetForm()
  showDialog.value = true
}

const openEdit = (rule) => {
  editing.value = rule
  form.value = { name: rule.name, field: rule.field, type: rule.type, message: rule.message ?? '' }
  paramsText.value = JSON.stringify(rule.params ?? {}, null, 0)
  showDialog.value = true
}

const submitForm = async () => {
  paramsError.value = ''
  let params = {}
  if (needsParams.value) {
    try {
      params = JSON.parse(paramsText.value || '{}')
    } catch {
      paramsError.value = 'JSON invalide'
      return
    }
  }

  saving.value = true
  try {
    const payload = { name: form.value.name, field: form.value.field, message: form.value.message || null, params }
    if (editing.value) {
      await axios.put(`/api/v1/validation-rules/${editing.value.id}`, payload)
      toast.add({ severity: 'success', summary: 'Modifiée', detail: 'Règle mise à jour', life: 3000 })
    } else {
      await axios.post('/api/v1/validation-rules', { ...payload, type: form.value.type })
      toast.add({ severity: 'success', summary: 'Créée', detail: 'Règle de validation créée', life: 3000 })
    }
    showDialog.value = false
    await loadRules(pagination.value.current_page)
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec de l\'enregistrement', life: 4000 })
  } finally {
    saving.value = false
  }
}

const deleteRule = async (rule) => {
  if (!confirm(`Supprimer la règle "${rule.name}" ?`)) return
  try {
    await axios.delete(`/api/v1/validation-rules/${rule.id}`)
    toast.add({ severity: 'success', summary: 'Supprimée', detail: 'Règle supprimée', life: 3000 })
    await loadRules(pagination.value.current_page)
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec de la suppression', life: 4000 })
  }
}
</script>

<style scoped>
.wh-page { max-width: 900px; margin: 0 auto; padding: 24px; }
.wh-page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; }
.wh-page-title { font-size: 20px; font-weight: 700; margin: 0; }
.wh-page-subtitle { font-size: 13px; color: #6B7280; margin-top: 2px; }
.wh-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.wh-table th { text-align: left; font-size: 11px; text-transform: uppercase; color: #6B7280; padding: 10px 12px; border-bottom: 1px solid #E5E7EB; background: #F9FAFB; }
.wh-table td { padding: 10px 12px; border-bottom: 1px solid #E5E7EB; }
.wh-mono { font-family: monospace; font-size: 12px; color: #6B7280; }
.wh-row-actions { display: flex; gap: 4px; }
.wh-row-btn { width: 28px; height: 28px; border-radius: 6px; border: 1px solid #E5E7EB; background: #fff; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
.wh-row-btn:hover { background: #F9FAFB; }
.wh-badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; background: #EFF6FF; color: #1D4ED8; }
.wh-loading-state, .wh-empty-state { text-align: center; padding: 40px; color: #9CA3AF; font-size: 13px; }
.wh-pagination { display: flex; align-items: center; gap: 12px; justify-content: center; margin-top: 16px; font-size: 13px; color: #6B7280; }
.wh-btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px; border-radius: 7px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; }
.wh-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.wh-btn-sm { padding: 4px 12px; font-size: 12px; }
.wh-btn-primary { background: #2563EB; color: #fff; }
.wh-btn-secondary { background: #F3F4F6; color: #374151; border: 1px solid #E5E7EB; }
.wh-dialog-form { display: flex; flex-direction: column; gap: 12px; }
.wh-form-field { display: flex; flex-direction: column; gap: 4px; }
.wh-form-field label { font-size: 13px; font-weight: 500; color: #374151; }
.wh-input { padding: 7px 10px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px; }
.wh-field-error { font-size: 11px; color: #EF4444; }
</style>
