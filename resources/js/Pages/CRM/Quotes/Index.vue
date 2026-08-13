<template>
  <AppLayout>
    <Head title="Devis CPQ" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Devis — CPQ</h1>
        <p class="wh-page-subtitle">Configure-Price-Quote : gérez vos devis commerciaux</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="openNew">
          <i class="pi pi-plus" style="font-size:13px" />
          Nouveau devis
        </button>
      </div>
    </div>

    <!-- Quote list -->
    <div class="wh-panel">
      <div style="padding:12px 16px;border-bottom:1px solid var(--border-1);display:flex;gap:8px;align-items:center">
        <span style="font-weight:600;font-size:15px">Devis</span>
        <div style="margin-left:auto;display:flex;gap:8px">
          <InputText v-model="search" placeholder="Chercher référence…" style="width:200px" @input="onSearch" />
          <Dropdown v-model="statusFilter" :options="statusOptions" option-label="label" option-value="value" placeholder="Statut" show-clear style="width:140px" @change="load" />
        </div>
      </div>

      <DataTable
        :value="quotes"
        :loading="loading"
        striped-rows
        style="font-size:13px"
        @row-click="goToQuote"
        :row-class="() => 'cursor-pointer'"
      >
        <Column field="reference" header="Référence" style="width:160px">
          <template #body="{ data }">
            <span style="font-family:monospace;font-weight:600">{{ data.reference }}</span>
          </template>
        </Column>
        <Column header="Contact" style="min-width:160px">
          <template #body="{ data }">{{ data.contact?.full_name ?? '—' }}</template>
        </Column>
        <Column header="Montant TTC" style="width:140px;text-align:right">
          <template #body="{ data }">
            <strong>{{ fmtCurrency(data.total) }}</strong>
          </template>
        </Column>
        <Column header="Statut" style="width:110px">
          <template #body="{ data }">
            <Tag :value="data.status" :severity="statusSeverity(data.status)" />
          </template>
        </Column>
        <Column header="Échéance" style="width:120px">
          <template #body="{ data }">
            <span :style="{ color: isExpired(data.valid_until) ? '#ef4444' : 'inherit' }">
              {{ data.valid_until ?? '—' }}
            </span>
          </template>
        </Column>
        <Column header="Créé le" style="width:110px">
          <template #body="{ data }">{{ fmtDate(data.created_at) }}</template>
        </Column>
      </DataTable>

      <!-- Pagination -->
      <div v-if="pagination.total > pagination.per_page" style="padding:12px 16px;display:flex;justify-content:center">
        <button v-if="pagination.current_page > 1" class="btn btn-secondary" style="margin-right:8px" @click="page--; load()">Précédent</button>
        <span style="line-height:32px;font-size:13px;color:var(--fg-4)">Page {{ pagination.current_page }} / {{ Math.ceil(pagination.total / pagination.per_page) }}</span>
        <button v-if="pagination.current_page < Math.ceil(pagination.total / pagination.per_page)" class="btn btn-secondary" style="margin-left:8px" @click="page++; load()">Suivant</button>
      </div>
    </div>

    <!-- New quote dialog -->
    <Dialog v-model:visible="showNewDialog" header="Nouveau devis" :style="{ width: '440px' }" modal>
      <div style="display:flex;flex-direction:column;gap:14px">
        <div>
          <label class="wh-label">Contact ID (optionnel)</label>
          <InputText v-model="newForm.contact_id" class="w-full" placeholder="ID du contact" />
        </div>
        <div>
          <label class="wh-label">Opportunité ID (optionnel)</label>
          <InputText v-model="newForm.opportunity_id" class="w-full" placeholder="ID de l'opportunité" />
        </div>
        <div>
          <label class="wh-label">Valable jusqu'au</label>
          <InputText v-model="newForm.valid_until" type="date" class="w-full" />
        </div>
        <div>
          <label class="wh-label">Notes</label>
          <Textarea v-model="newForm.notes" rows="3" class="w-full" />
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" @click="showNewDialog = false" />
        <Button label="Créer le devis" :loading="creating" @click="createQuote" />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, DataTable, Column, Dialog, Tag, InputText, Textarea, Dropdown } from 'primevue'
import axios from 'axios'

const quotes = ref([])
const loading = ref(false)
const creating = ref(false)
const showNewDialog = ref(false)
const search = ref('')
const statusFilter = ref(null)
const page = ref(1)
const pagination = ref({ current_page: 1, per_page: 25, total: 0 })

const newForm = ref({ contact_id: '', opportunity_id: '', valid_until: '', notes: '' })

const statusOptions = [
  { label: 'Brouillon', value: 'draft' },
  { label: 'Envoyé', value: 'sent' },
  { label: 'Accepté', value: 'accepted' },
  { label: 'Refusé', value: 'rejected' },
  { label: 'Expiré', value: 'expired' },
]

function statusSeverity(status) {
  return { draft: 'secondary', sent: 'info', accepted: 'success', rejected: 'danger', expired: 'warn' }[status] ?? 'secondary'
}

function isExpired(date) {
  return date && new Date(date) < new Date()
}

function fmtCurrency(val) {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(val || 0)
}

function fmtDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('fr-FR')
}

let searchTimer = null
function onSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(load, 400)
}

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/crm/quotes', {
      params: { page: page.value, search: search.value || undefined, status: statusFilter.value || undefined },
    })
    quotes.value = data.data || []
    pagination.value = data.meta || data
  } finally {
    loading.value = false
  }
}

function openNew() {
  newForm.value = { contact_id: '', opportunity_id: '', valid_until: '', notes: '' }
  showNewDialog.value = true
}

async function createQuote() {
  creating.value = true
  try {
    const payload = {
      contact_id: newForm.value.contact_id || undefined,
      opportunity_id: newForm.value.opportunity_id || undefined,
      valid_until: newForm.value.valid_until || undefined,
      notes: newForm.value.notes || undefined,
    }
    const { data } = await axios.post('/api/v1/crm/quotes', payload)
    showNewDialog.value = false
    router.visit(`/crm/quotes/${data.id}`)
  } catch (e) {
    console.error(e)
  } finally {
    creating.value = false
  }
}

function goToQuote({ data }) {
  router.visit(`/crm/quotes/${data.id}`)
}

onMounted(load)
</script>
