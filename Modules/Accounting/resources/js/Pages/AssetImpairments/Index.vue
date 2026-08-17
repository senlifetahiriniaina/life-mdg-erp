<template>
  <AppLayout>
    <Head title="Dépréciations d'actifs" />

    <div class="wh-page">
      <div class="wh-page-header">
        <div>
          <h1 class="wh-page-title">Dépréciations d'actifs</h1>
          <p class="wh-page-subtitle">Tests de dépréciation d'immobilisations (juste valeur vs. valeur comptable)</p>
        </div>
        <button v-if="can('accounting', 'asset_impairment', 'create')" class="wh-btn wh-btn-primary" @click="openCreate">
          <i class="pi pi-plus" /> Nouveau test
        </button>
      </div>

      <div class="wh-filters">
        <select v-model="filters.status" class="wh-input" @change="load">
          <option value="">Tous les statuts</option>
          <option value="draft">Brouillon</option>
          <option value="approved">Approuvé</option>
          <option value="recorded">Enregistré</option>
        </select>
      </div>

      <div v-if="loading" class="wh-loading-state"><i class="pi pi-spin pi-spinner" /> Chargement...</div>

      <table v-else class="wh-table">
        <thead>
          <tr>
            <th>Immobilisation</th><th>Date</th><th>Valeur comptable</th><th>Juste valeur</th><th>Perte</th><th>Statut</th><th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in items" :key="item.id">
            <td>{{ item.fixed_asset?.name ?? `#${item.fixed_asset_id}` }}</td>
            <td>{{ formatDate(item.impairment_date) }}</td>
            <td>{{ fmt(item.book_value_before) }}</td>
            <td>{{ fmt(item.fair_value) }}</td>
            <td style="color:#DC2626">{{ fmt(item.impairment_loss) }}</td>
            <td><span :class="['wh-badge', statusClass(item.status)]">{{ statusLabel(item.status) }}</span></td>
            <td class="wh-row-actions">
              <button v-if="item.status === 'draft' && can('accounting', 'asset_impairment', 'approve')" class="wh-row-btn" title="Approuver" @click="approve(item)">
                <i class="pi pi-check" style="color:#059669" />
              </button>
              <button v-if="item.status === 'approved' && can('accounting', 'asset_impairment', 'record')" class="wh-row-btn" title="Enregistrer" @click="openRecord(item)">
                <i class="pi pi-file-edit" />
              </button>
            </td>
          </tr>
          <tr v-if="items.length === 0"><td colspan="7" class="wh-empty-state">Aucun test de dépréciation.</td></tr>
        </tbody>
      </table>
    </div>

    <Dialog v-model:visible="showDialog" header="Nouveau test de dépréciation" :modal="true" :style="{ width: '480px' }">
      <div class="wh-dialog-form">
        <div class="wh-form-field">
          <label>Immobilisation</label>
          <select v-model.number="form.fixed_asset_id" class="wh-input">
            <option :value="null" disabled>Sélectionner...</option>
            <option v-for="a in fixedAssets" :key="a.id" :value="a.id">{{ a.name }}</option>
          </select>
        </div>
        <div class="wh-form-field"><label>Date du test</label><input v-model="form.impairment_date" type="date" class="wh-input" /></div>
        <div class="wh-form-field"><label>Coût d'origine</label><input v-model.number="form.original_cost" type="number" step="0.01" class="wh-input" /></div>
        <div class="wh-form-field"><label>Amortissement cumulé (avant)</label><input v-model.number="form.accumulated_depreciation_before" type="number" step="0.01" class="wh-input" /></div>
        <div class="wh-form-field"><label>Valeur comptable (avant)</label><input v-model.number="form.book_value_before" type="number" step="0.01" class="wh-input" /></div>
        <div class="wh-form-field"><label>Juste valeur</label><input v-model.number="form.fair_value" type="number" step="0.01" class="wh-input" /></div>
        <div class="wh-form-field"><label>Motif</label><textarea v-model="form.impairment_reason" class="wh-input" rows="2" /></div>
      </div>
      <template #footer>
        <button class="wh-btn wh-btn-secondary" @click="showDialog = false">Annuler</button>
        <button class="wh-btn wh-btn-primary" :disabled="saving" @click="submitForm">{{ saving ? 'Enregistrement...' : 'Enregistrer' }}</button>
      </template>
    </Dialog>

    <Dialog v-model:visible="showRecordDialog" header="Enregistrer l'écriture comptable" :modal="true" :style="{ width: '420px' }">
      <div class="wh-dialog-form">
        <div class="wh-form-field"><label>ID de l'écriture de journal</label><input v-model.number="recordForm.journal_entry_id" type="number" class="wh-input" /></div>
      </div>
      <template #footer>
        <button class="wh-btn wh-btn-secondary" @click="showRecordDialog = false">Annuler</button>
        <button class="wh-btn wh-btn-primary" :disabled="saving" @click="submitRecord">{{ saving ? 'Enregistrement...' : 'Confirmer' }}</button>
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

const items = ref([])
const fixedAssets = ref([])
const loading = ref(true)
const saving = ref(false)
const showDialog = ref(false)
const showRecordDialog = ref(false)
const recordingItem = ref(null)
const filters = ref({ status: '' })

const form = ref({
  fixed_asset_id: null, impairment_date: new Date().toISOString().slice(0, 10),
  original_cost: 0, accumulated_depreciation_before: 0, book_value_before: 0,
  fair_value: 0, impairment_reason: '',
})
const recordForm = ref({ journal_entry_id: null })

const load = async () => {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/accounting/asset-impairments', {
      params: { status: filters.value.status || undefined },
    })
    items.value = data.data ?? data
  } catch (e) { console.error(e) } finally { loading.value = false }
}

const loadFixedAssets = async () => {
  try {
    const { data } = await axios.get('/api/v1/accounting/fixed-assets')
    fixedAssets.value = data.data ?? data
  } catch (e) { console.error(e) }
}

onMounted(() => { load(); loadFixedAssets() })

const openCreate = () => { showDialog.value = true }

const submitForm = async () => {
  saving.value = true
  try {
    await axios.post('/api/v1/accounting/asset-impairments', form.value)
    toast.add({ severity: 'success', summary: 'Créé', detail: 'Test de dépréciation créé', life: 3000 })
    showDialog.value = false
    await load()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec', life: 4000 })
  } finally { saving.value = false }
}

const approve = async (item) => {
  try {
    await axios.post(`/api/v1/accounting/asset-impairments/${item.id}/approve`)
    toast.add({ severity: 'success', summary: 'Approuvé', life: 3000 })
    await load()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec', life: 4000 })
  }
}

const openRecord = (item) => { recordingItem.value = item; recordForm.value = { journal_entry_id: null }; showRecordDialog.value = true }

const submitRecord = async () => {
  saving.value = true
  try {
    await axios.post(`/api/v1/accounting/asset-impairments/${recordingItem.value.id}/record`, recordForm.value)
    toast.add({ severity: 'success', summary: 'Enregistré', life: 3000 })
    showRecordDialog.value = false
    await load()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec', life: 4000 })
  } finally { saving.value = false }
}

const fmt = (n) => new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n ?? 0)
const formatDate = (d) => d ? new Date(d).toLocaleDateString('fr-FR') : '—'
const statusLabel = (s) => ({ draft: 'Brouillon', approved: 'Approuvé', recorded: 'Enregistré' }[s] ?? s)
const statusClass = (s) => ({ draft: 'wh-badge-slate', approved: 'wh-badge-blue', recorded: 'wh-badge-green' }[s] ?? 'wh-badge-slate')
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
.wh-badge-blue { background: #DBEAFE; color: #1E40AF; }
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
