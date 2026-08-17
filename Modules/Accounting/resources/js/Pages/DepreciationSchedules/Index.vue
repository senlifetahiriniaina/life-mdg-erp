<template>
  <AppLayout>
    <Head title="Plans d'amortissement" />

    <div class="wh-page">
      <div class="wh-page-header">
        <div>
          <h1 class="wh-page-title">Plans d'amortissement</h1>
          <p class="wh-page-subtitle">Échéanciers d'amortissement par immobilisation</p>
        </div>
        <button v-if="can('accounting', 'depreciation', 'create')" class="wh-btn wh-btn-primary" @click="openCreate">
          <i class="pi pi-plus" /> Nouveau plan
        </button>
      </div>

      <div v-if="loading" class="wh-loading-state"><i class="pi pi-spin pi-spinner" /> Chargement...</div>

      <table v-else class="wh-table">
        <thead>
          <tr><th>Immobilisation</th><th>Méthode</th><th>Dotation annuelle</th><th>Valeur comptable</th><th>Statut</th><th></th></tr>
        </thead>
        <tbody>
          <tr v-for="s in items" :key="s.id">
            <td>{{ s.fixed_asset?.name ?? `#${s.fixed_asset_id}` }}</td>
            <td>{{ methodLabel(s.depreciation_method) }}</td>
            <td>{{ fmt(s.annual_depreciation_amount) }}</td>
            <td>{{ fmt(s.book_value) }}</td>
            <td><span :class="['wh-badge', statusClass(s.status)]">{{ statusLabel(s.status) }}</span></td>
            <td class="wh-row-actions">
              <button v-if="s.status === 'active' && can('accounting', 'depreciation', 'record')" class="wh-row-btn" title="Enregistrer une échéance" @click="openRecord(s)">
                <i class="pi pi-file-edit" />
              </button>
            </td>
          </tr>
          <tr v-if="items.length === 0"><td colspan="6" class="wh-empty-state">Aucun plan d'amortissement.</td></tr>
        </tbody>
      </table>
    </div>

    <Dialog v-model:visible="showDialog" header="Nouveau plan d'amortissement" :modal="true" :style="{ width: '480px' }">
      <div class="wh-dialog-form">
        <div class="wh-form-field">
          <label>Immobilisation</label>
          <select v-model.number="form.fixed_asset_id" class="wh-input">
            <option :value="null" disabled>Sélectionner...</option>
            <option v-for="a in fixedAssets" :key="a.id" :value="a.id">{{ a.name }}</option>
          </select>
        </div>
        <div class="wh-form-field">
          <label>Méthode</label>
          <select v-model="form.depreciation_method" class="wh-input">
            <option value="straight_line">Linéaire</option>
            <option value="declining_balance">Dégressif</option>
            <option value="units_of_production">Unités de production</option>
            <option value="sum_of_years">Somme des chiffres</option>
            <option value="macrs">MACRS</option>
          </select>
        </div>
        <div class="wh-form-field"><label>Durée de vie (années)</label><input v-model.number="form.useful_life_years" type="number" min="1" class="wh-input" /></div>
        <div class="wh-form-field"><label>Valeur résiduelle</label><input v-model.number="form.residual_value" type="number" step="0.01" class="wh-input" /></div>
        <div class="wh-form-field"><label>Date de début</label><input v-model="form.depreciation_start_date" type="date" class="wh-input" /></div>
        <div class="wh-form-field"><label>Dotation annuelle</label><input v-model.number="form.annual_depreciation_amount" type="number" step="0.01" class="wh-input" /></div>
        <div class="wh-form-field">
          <label>Compte de charge (ID compte GL)</label>
          <select v-model.number="form.depreciation_expense_account_id" class="wh-input">
            <option :value="null" disabled>Sélectionner...</option>
            <option v-for="g in glAccounts" :key="g.id" :value="g.id">{{ g.account_number }} — {{ g.account_name ?? g.name }}</option>
          </select>
        </div>
        <div class="wh-form-field">
          <label>Compte d'amortissement cumulé (ID compte GL)</label>
          <select v-model.number="form.accumulated_depreciation_account_id" class="wh-input">
            <option :value="null" disabled>Sélectionner...</option>
            <option v-for="g in glAccounts" :key="g.id" :value="g.id">{{ g.account_number }} — {{ g.account_name ?? g.name }}</option>
          </select>
        </div>
      </div>
      <template #footer>
        <button class="wh-btn wh-btn-secondary" @click="showDialog = false">Annuler</button>
        <button class="wh-btn wh-btn-primary" :disabled="saving" @click="submitForm">{{ saving ? 'Enregistrement...' : 'Enregistrer' }}</button>
      </template>
    </Dialog>

    <Dialog v-model:visible="showRecordDialog" header="Enregistrer une échéance" :modal="true" :style="{ width: '420px' }">
      <div class="wh-dialog-form">
        <div class="wh-form-field"><label>Date de la période</label><input v-model="recordForm.period_date" type="date" class="wh-input" /></div>
        <div class="wh-form-field"><label>ID de l'écriture de journal (optionnel)</label><input v-model.number="recordForm.journal_entry_id" type="number" class="wh-input" /></div>
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
const glAccounts = ref([])
const loading = ref(true)
const saving = ref(false)
const showDialog = ref(false)
const showRecordDialog = ref(false)
const recordingItem = ref(null)

const emptyForm = () => ({
  fixed_asset_id: null, depreciation_method: 'straight_line', useful_life_years: 5,
  residual_value: 0, depreciation_start_date: new Date().toISOString().slice(0, 10),
  annual_depreciation_amount: 0, depreciation_expense_account_id: null,
  accumulated_depreciation_account_id: null,
})
const form = ref(emptyForm())
const recordForm = ref({ period_date: new Date().toISOString().slice(0, 10), journal_entry_id: null })

const load = async () => {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/accounting/depreciation-schedules')
    items.value = data.data ?? data
  } catch (e) { console.error(e) } finally { loading.value = false }
}

const loadRefs = async () => {
  try {
    const [fa, gl] = await Promise.all([
      axios.get('/api/v1/accounting/fixed-assets'),
      axios.get('/api/v1/accounting/gl-accounts'),
    ])
    fixedAssets.value = fa.data.data ?? fa.data
    glAccounts.value = gl.data.data ?? gl.data
  } catch (e) { console.error(e) }
}

onMounted(() => { load(); loadRefs() })

const openCreate = () => { form.value = emptyForm(); showDialog.value = true }

const submitForm = async () => {
  saving.value = true
  try {
    await axios.post('/api/v1/accounting/depreciation-schedules', form.value)
    toast.add({ severity: 'success', summary: 'Créé', life: 3000 })
    showDialog.value = false
    await load()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec', life: 4000 })
  } finally { saving.value = false }
}

const openRecord = (s) => {
  recordingItem.value = s
  recordForm.value = { period_date: new Date().toISOString().slice(0, 10), journal_entry_id: null }
  showRecordDialog.value = true
}

const submitRecord = async () => {
  saving.value = true
  try {
    await axios.post(`/api/v1/accounting/depreciation-schedules/${recordingItem.value.id}/record`, recordForm.value)
    toast.add({ severity: 'success', summary: 'Échéance enregistrée', life: 3000 })
    showRecordDialog.value = false
    await load()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec', life: 4000 })
  } finally { saving.value = false }
}

const fmt = (n) => new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n ?? 0)
const methodLabel = (m) => ({
  straight_line: 'Linéaire', declining_balance: 'Dégressif', units_of_production: 'Unités de production',
  sum_of_years: 'Somme des chiffres', macrs: 'MACRS', custom: 'Personnalisé',
}[m] ?? m)
const statusLabel = (s) => ({ active: 'Actif', paused: 'En pause', completed: 'Terminé', retired: 'Retiré' }[s] ?? s)
const statusClass = (s) => ({ active: 'wh-badge-green', paused: 'wh-badge-slate', completed: 'wh-badge-blue', retired: 'wh-badge-slate' }[s] ?? 'wh-badge-slate')
</script>

<style scoped>
.wh-page { max-width: 1100px; margin: 0 auto; padding: 24px; }
.wh-page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; }
.wh-page-title { font-size: 20px; font-weight: 700; margin: 0; }
.wh-page-subtitle { font-size: 13px; color: #6B7280; margin-top: 2px; }
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
