<template>
  <AppLayout>
    <Head title="Compensations intersociétés" />

    <div class="wh-page">
      <div class="wh-page-header">
        <div>
          <h1 class="wh-page-title">Compensations intersociétés</h1>
          <p class="wh-page-subtitle">Transactions entre entités du groupe et leur solde</p>
        </div>
        <button v-if="can('accounting', 'intercompany', 'create')" class="wh-btn wh-btn-primary" @click="openCreate">
          <i class="pi pi-plus" /> Nouvelle transaction
        </button>
      </div>

      <div class="wh-filters">
        <select v-model="filters.status" class="wh-input" @change="load">
          <option value="">Tous les statuts</option>
          <option value="pending">En attente</option>
          <option value="matched">Rapproché</option>
          <option value="cleared">Soldé</option>
          <option value="disputed">Contesté</option>
          <option value="reversed">Annulé</option>
        </select>
      </div>

      <div v-if="loading" class="wh-loading-state"><i class="pi pi-spin pi-spinner" /> Chargement...</div>

      <table v-else class="wh-table">
        <thead>
          <tr><th>Date</th><th>Émettrice</th><th>Réceptrice</th><th>Type</th><th>Montant</th><th>Statut</th><th></th></tr>
        </thead>
        <tbody>
          <tr v-for="c in items" :key="c.id">
            <td>{{ formatDate(c.transaction_date) }}</td>
            <td>{{ c.sending_company?.name ?? `#${c.sending_company_id}` }}</td>
            <td>{{ c.receiving_company?.name ?? `#${c.receiving_company_id}` }}</td>
            <td>{{ c.transaction_type }}</td>
            <td>{{ fmt(c.amount) }} {{ c.currency }}</td>
            <td><span :class="['wh-badge', statusClass(c.status)]">{{ statusLabel(c.status) }}</span></td>
            <td class="wh-row-actions">
              <button v-if="c.status !== 'cleared' && can('accounting', 'intercompany', 'clear')" class="wh-row-btn" title="Solder" @click="clear(c)">
                <i class="pi pi-check" style="color:#059669" />
              </button>
            </td>
          </tr>
          <tr v-if="items.length === 0"><td colspan="7" class="wh-empty-state">Aucune transaction intersociétés.</td></tr>
        </tbody>
      </table>
    </div>

    <Dialog v-model:visible="showDialog" header="Nouvelle transaction intersociétés" :modal="true" :style="{ width: '480px' }">
      <div class="wh-dialog-form">
        <div class="wh-form-field"><label>Société émettrice (ID)</label><input v-model.number="form.sending_company_id" type="number" class="wh-input" /></div>
        <div class="wh-form-field"><label>Société réceptrice (ID)</label><input v-model.number="form.receiving_company_id" type="number" class="wh-input" /></div>
        <div class="wh-form-field"><label>Date</label><input v-model="form.transaction_date" type="date" class="wh-input" /></div>
        <div class="wh-form-field"><label>Type de transaction</label><input v-model="form.transaction_type" class="wh-input" placeholder="ex: loan, service_fee, dividend" /></div>
        <div class="wh-form-field"><label>Montant</label><input v-model.number="form.amount" type="number" step="0.01" class="wh-input" /></div>
        <div class="wh-form-field"><label>Devise</label><input v-model="form.currency" class="wh-input" maxlength="3" /></div>
        <div class="wh-form-field">
          <label>Compte GL émetteur</label>
          <select v-model.number="form.sending_gl_account_id" class="wh-input">
            <option :value="null" disabled>Sélectionner...</option>
            <option v-for="g in glAccounts" :key="g.id" :value="g.id">{{ g.account_number }} — {{ g.account_name ?? g.name }}</option>
          </select>
        </div>
        <div class="wh-form-field">
          <label>Compte GL récepteur</label>
          <select v-model.number="form.receiving_gl_account_id" class="wh-input">
            <option :value="null" disabled>Sélectionner...</option>
            <option v-for="g in glAccounts" :key="g.id" :value="g.id">{{ g.account_number }} — {{ g.account_name ?? g.name }}</option>
          </select>
        </div>
        <div class="wh-form-field"><label>Description</label><textarea v-model="form.description" class="wh-input" rows="2" /></div>
      </div>
      <template #footer>
        <button class="wh-btn wh-btn-secondary" @click="showDialog = false">Annuler</button>
        <button class="wh-btn wh-btn-primary" :disabled="saving" @click="submitForm">{{ saving ? 'Enregistrement...' : 'Enregistrer' }}</button>
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
const glAccounts = ref([])
const loading = ref(true)
const saving = ref(false)
const showDialog = ref(false)
const filters = ref({ status: '' })

const emptyForm = () => ({
  sending_company_id: null, receiving_company_id: null,
  transaction_date: new Date().toISOString().slice(0, 10), transaction_type: '',
  amount: 0, currency: 'XOF', sending_gl_account_id: null, receiving_gl_account_id: null,
  description: '',
})
const form = ref(emptyForm())

const load = async () => {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/accounting/intercompany-clearances', {
      params: { status: filters.value.status || undefined },
    })
    items.value = data.data ?? data
  } catch (e) { console.error(e) } finally { loading.value = false }
}

const loadRefs = async () => {
  try {
    const { data } = await axios.get('/api/v1/accounting/gl-accounts')
    glAccounts.value = data.data ?? data
  } catch (e) { console.error(e) }
}

onMounted(() => { load(); loadRefs() })

const openCreate = () => { form.value = emptyForm(); showDialog.value = true }

const submitForm = async () => {
  saving.value = true
  try {
    await axios.post('/api/v1/accounting/intercompany-clearances', form.value)
    toast.add({ severity: 'success', summary: 'Créée', life: 3000 })
    showDialog.value = false
    await load()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec', life: 4000 })
  } finally { saving.value = false }
}

const clear = async (c) => {
  if (!confirm('Solder cette transaction ?')) return
  try {
    await axios.post(`/api/v1/accounting/intercompany-clearances/${c.id}/clear`)
    toast.add({ severity: 'success', summary: 'Soldée', life: 3000 })
    await load()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message ?? 'Échec', life: 4000 })
  }
}

const fmt = (n) => new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n ?? 0)
const formatDate = (d) => d ? new Date(d).toLocaleDateString('fr-FR') : '—'
const statusLabel = (s) => ({ pending: 'En attente', matched: 'Rapproché', cleared: 'Soldé', disputed: 'Contesté', reversed: 'Annulé' }[s] ?? s)
const statusClass = (s) => ({ pending: 'wh-badge-slate', matched: 'wh-badge-blue', cleared: 'wh-badge-green', disputed: 'wh-badge-red', reversed: 'wh-badge-slate' }[s] ?? 'wh-badge-slate')
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
.wh-badge-red { background: #FEE2E2; color: #991B1B; }
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
