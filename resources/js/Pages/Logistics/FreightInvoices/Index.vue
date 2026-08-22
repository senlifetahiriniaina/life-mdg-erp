<script setup lang="ts">
import { ref, reactive } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Select from 'primevue/select'
import Paginator from 'primevue/paginator'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'

interface FreightInvoice {
  id: number
  invoice_number: string
  type: string
  status: string
  invoiced_amount: number
  quoted_amount?: number
  variance_amount?: number
  currency: string
  invoice_date: string
  carrier?: { id: number; name: string }
  shipment?: { id: number; reference: string }
}

interface Paginated<T> { data: T[]; total: number; current_page: number; last_page: number; per_page: number }

const invoices = ref<Paginated<FreightInvoice>>({ data: [], total: 0, current_page: 1, last_page: 1, per_page: 20 })
const loading  = ref(false)
const statusFilter = ref<string | null>(null)
const typeFilter   = ref<string | null>(null)

const statusOptions = [
  { label: 'Brouillon', value: 'draft' },
  { label: 'En révision', value: 'pending_review' },
  { label: 'Approuvé', value: 'approved' },
  { label: 'En litige', value: 'disputed' },
  { label: 'Payé', value: 'paid' },
  { label: 'Annulé', value: 'cancelled' },
]

const typeOptions = [
  { label: 'À payer', value: 'payable' },
  { label: 'À recevoir', value: 'receivable' },
]

function statusBadgeClass(status: string): string {
  const map: Record<string, string> = {
    draft: 'badge-gray', pending_review: 'badge-orange', approved: 'badge-green',
    disputed: 'badge-red', paid: 'badge-blue', cancelled: 'badge-gray',
  }
  return map[status] ?? 'badge-gray'
}

function typeBadgeClass(type: string): string {
  return type === 'payable' ? 'badge-orange' : 'badge-blue'
}

function varianceClass(variance?: number): string {
  if (!variance) return ''
  return variance > 0 ? 'color:var(--red-500)' : 'color:var(--green-500)'
}

function formatAmount(amount: number, currency: string): string {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency }).format(amount)
}

function formatDate(d?: string): string {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('fr-FR')
}

// Chantier 19 Lot 4: this page's POST fetch() calls (approve/dispute) sent
// no CSRF token at all — same fix pattern as Carriers/Index.vue, confirmed
// empirically via a real php artisan serve + login session (419 without it).
function getCsrf(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? ''
}

async function load(page = 1) {
  loading.value = true
  try {
    const params = new URLSearchParams()
    params.set('page', String(page))
    if (statusFilter.value) params.set('status', statusFilter.value)
    if (typeFilter.value) params.set('type', typeFilter.value)
    const res = await fetch(`/api/v1/logistics/freight-invoices?${params}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    invoices.value = await res.json()
  } finally { loading.value = false }
}

async function approveInvoice(invoice: FreightInvoice) {
  const res = await fetch(`/api/v1/logistics/freight-invoices/${invoice.id}/approve`, {
    method: 'POST',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': getCsrf() },
    credentials: 'same-origin',
  })
  if (res.ok) load(invoices.value.current_page)
}

async function disputeInvoice(invoice: FreightInvoice) {
  const reason = prompt('Raison du litige :')
  if (!reason) return
  const res = await fetch(`/api/v1/logistics/freight-invoices/${invoice.id}/dispute`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': getCsrf() },
    credentials: 'same-origin',
    body: JSON.stringify({ reason }),
  })
  if (res.ok) load(invoices.value.current_page)
}

function reset() {
  statusFilter.value = null
  typeFilter.value = null
  load()
}

const onPageChange = (e: { page: number }) => load(e.page + 1)

// Chantier 19 Lot 4: "Nouvelle facture" and "Voir" both router.visit()'d to
// /logistics/freight-invoices/create and /logistics/freight-invoices/{id} —
// neither route nor page has ever existed anywhere in this app (confirmed
// via `php artisan route:list` + a repo-wide Glob), a guaranteed 404 on
// every click despite the real, already-tested FreightInvoiceController
// store()/show() API existing. Fixed with a compact inline create modal and
// a read-only detail modal (matching Carriers/DeliveryRounds precedent).
const showFormModal = ref(false)
const savingInvoice = ref(false)
const invoiceForm = reactive({
  carrier_id: '', shipment_id: '', type: null as string | null, invoice_date: '', currency: 'MGA', invoiced_amount: '', invoice_number: '',
})
const invoiceErrors = reactive<Record<string, string>>({})

function openCreateInvoice() {
  invoiceForm.carrier_id = ''
  invoiceForm.shipment_id = ''
  invoiceForm.type = null
  invoiceForm.invoice_date = ''
  invoiceForm.currency = 'MGA'
  invoiceForm.invoiced_amount = ''
  invoiceForm.invoice_number = ''
  Object.keys(invoiceErrors).forEach(k => delete invoiceErrors[k])
  showFormModal.value = true
}

async function submitInvoiceForm() {
  savingInvoice.value = true
  Object.keys(invoiceErrors).forEach(k => delete invoiceErrors[k])
  try {
    const res = await fetch('/api/v1/logistics/freight-invoices', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': getCsrf() },
      credentials: 'same-origin',
      body: JSON.stringify({
        ...invoiceForm,
        carrier_id: Number(invoiceForm.carrier_id),
        shipment_id: invoiceForm.shipment_id ? Number(invoiceForm.shipment_id) : null,
        invoiced_amount: Number(invoiceForm.invoiced_amount),
      }),
    })
    if (!res.ok) {
      const data = await res.json()
      if (data.errors) Object.assign(invoiceErrors, data.errors)
      return
    }
    showFormModal.value = false
    load(invoices.value.current_page)
  } finally {
    savingInvoice.value = false
  }
}

const showDetailModal = ref(false)
const detailLoading = ref(false)
const detailInvoice = ref<any>(null)

async function viewInvoice(invoice: FreightInvoice) {
  showDetailModal.value = true
  detailLoading.value = true
  detailInvoice.value = null
  try {
    const res = await fetch(`/api/v1/logistics/freight-invoices/${invoice.id}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    if (res.ok) detailInvoice.value = await res.json()
  } finally {
    detailLoading.value = false
  }
}

load()

// Chantier 32.23 (deep 14-layer audit, layer 13 — IA): real, routed backend
// (POST /api/v1/logistics/ai/assist), never called from this page before.
const { guidance } = useAiAssistant('Logistics', 'manage_freight_invoices')
const showAiPanel = ref(true)
</script>

<template>
  <AppLayout>
    <Head title="Factures de fret" />
    <AIAssistantPanel v-if="showAiPanel" :guidance="guidance" @close="showAiPanel = false" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Factures de fret</h1>
        <p class="wh-page-subtitle">{{ invoices.total }} facture{{ invoices.total !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="openCreateInvoice">
          <i class="pi pi-plus" style="font-size:13px" /> Nouvelle facture
        </button>
      </div>
    </div>

    <div class="wh-panel" style="margin-bottom:16px">
      <div style="padding:12px 16px;display:flex;gap:10px;align-items:center">
        <Select v-model="statusFilter" :options="statusOptions" option-label="label" option-value="value" placeholder="Statut" show-clear style="width:160px" @change="load()" />
        <Select v-model="typeFilter" :options="typeOptions" option-label="label" option-value="value" placeholder="Type" show-clear style="width:140px" @change="load()" />
        <button class="btn btn-secondary" @click="reset">
          <i class="pi pi-filter-slash" style="font-size:13px" /> Réinitialiser
        </button>
      </div>
    </div>

    <div class="wh-panel" style="position:relative">
      <div v-if="loading" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(247,248,251,0.6);z-index:1">
        <i class="pi pi-spin pi-spinner" style="font-size:20px;color:var(--halo-500)" />
      </div>
      <table class="wh-dt" :class="{ 'wh-dt-loading': loading }">
        <thead>
          <tr>
            <th>N° Facture</th>
            <th>Transporteur</th>
            <th>Expédition</th>
            <th>Type</th>
            <th>Montant facturé</th>
            <th>Écart</th>
            <th>Statut</th>
            <th>Date</th>
            <th style="width:100px">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!loading && invoices.data.length === 0">
            <td colspan="9" style="text-align:center;padding:48px;color:var(--fg-3);font-size:14px">Aucune facture</td>
          </tr>
          <tr v-for="inv in invoices.data" :key="inv.id" class="wh-dt-row">
            <td>
              <span style="font-family:var(--font-mono);font-weight:600;color:var(--halo-600)">{{ inv.invoice_number }}</span>
            </td>
            <td>{{ inv.carrier?.name || '—' }}</td>
            <td>
              <span v-if="inv.shipment" style="font-family:var(--font-mono);font-size:12px">{{ inv.shipment.reference }}</span>
              <span v-else style="color:var(--fg-3)">—</span>
            </td>
            <td><span :class="['wh-badge', typeBadgeClass(inv.type)]">{{ inv.type }}</span></td>
            <td style="font-weight:600">{{ formatAmount(inv.invoiced_amount, inv.currency) }}</td>
            <td>
              <span v-if="inv.variance_amount" :style="varianceClass(inv.variance_amount)">
                {{ inv.variance_amount > 0 ? '+' : '' }}{{ formatAmount(inv.variance_amount, inv.currency) }}
              </span>
              <span v-else style="color:var(--fg-3)">—</span>
            </td>
            <td><span :class="['wh-badge', statusBadgeClass(inv.status)]">{{ inv.status }}</span></td>
            <td style="font-size:12px;color:var(--fg-3)">{{ formatDate(inv.invoice_date) }}</td>
            <td>
              <div style="display:flex;gap:4px">
                <button
                  v-if="inv.status === 'pending_review'"
                  class="wh-row-btn"
                  title="Approuver"
                  @click="approveInvoice(inv)"
                >
                  <i class="pi pi-check" style="font-size:13px" />
                </button>
                <button
                  v-if="inv.status === 'pending_review'"
                  class="wh-row-btn wh-row-btn-danger"
                  title="Contester"
                  @click="disputeInvoice(inv)"
                >
                  <i class="pi pi-times" style="font-size:13px" />
                </button>
                <button class="wh-row-btn" title="Voir" @click="viewInvoice(inv)">
                  <i class="pi pi-eye" style="font-size:13px" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <div
        v-if="invoices.total > invoices.per_page"
        style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-top:1px solid var(--border-subtle)"
      >
        <span style="font-size:13px;color:var(--fg-3)">
          {{ (invoices.current_page - 1) * invoices.per_page + 1 }}–{{ Math.min(invoices.current_page * invoices.per_page, invoices.total) }}
          sur {{ invoices.total }}
        </span>
        <Paginator
          :rows="invoices.per_page"
          :total-records="invoices.total"
          :first="(invoices.current_page - 1) * invoices.per_page"
          @page="onPageChange"
        />
      </div>
    </div>

    <Dialog v-model:visible="showFormModal" header="Nouvelle facture de fret" modal style="width: 30rem">
      <form class="inv-form" @submit.prevent="submitInvoiceForm">
        <div class="inv-field">
          <label>Transporteur (ID) *</label>
          <InputText v-model="invoiceForm.carrier_id" :class="{ 'p-invalid': invoiceErrors.carrier_id }" />
          <small v-if="invoiceErrors.carrier_id" class="inv-error">{{ invoiceErrors.carrier_id }}</small>
        </div>
        <div class="inv-field">
          <label>Expédition (ID)</label>
          <InputText v-model="invoiceForm.shipment_id" />
        </div>
        <div class="inv-field">
          <label>Type *</label>
          <Select v-model="invoiceForm.type" :options="typeOptions" option-label="label" option-value="value" class="w-full" :class="{ 'p-invalid': invoiceErrors.type }" />
        </div>
        <div class="inv-field">
          <label>N° Facture</label>
          <InputText v-model="invoiceForm.invoice_number" />
        </div>
        <div class="inv-field">
          <label>Date de facture *</label>
          <InputText v-model="invoiceForm.invoice_date" type="date" :class="{ 'p-invalid': invoiceErrors.invoice_date }" />
        </div>
        <div class="inv-field">
          <label>Devise (ISO 3) *</label>
          <InputText v-model="invoiceForm.currency" maxlength="3" :class="{ 'p-invalid': invoiceErrors.currency }" />
        </div>
        <div class="inv-field">
          <label>Montant facturé *</label>
          <InputText v-model="invoiceForm.invoiced_amount" :class="{ 'p-invalid': invoiceErrors.invoiced_amount }" />
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px">
          <button type="button" class="btn btn-secondary" @click="showFormModal = false">Annuler</button>
          <button type="submit" class="btn btn-primary" :disabled="savingInvoice">Créer</button>
        </div>
      </form>
    </Dialog>

    <Dialog v-model:visible="showDetailModal" :header="`Facture ${detailInvoice?.invoice_number ?? ''}`" modal style="width: 32rem">
      <div v-if="detailLoading" style="text-align:center;padding:24px"><i class="pi pi-spin pi-spinner" /></div>
      <div v-else-if="detailInvoice">
        <p><strong>Transporteur :</strong> {{ detailInvoice.carrier?.name ?? '—' }}</p>
        <p><strong>Type :</strong> {{ detailInvoice.type }}</p>
        <p><strong>Statut :</strong> {{ detailInvoice.status }}</p>
        <p><strong>Montant facturé :</strong> {{ formatAmount(detailInvoice.invoiced_amount, detailInvoice.currency) }}</p>
        <p v-if="detailInvoice.quoted_amount"><strong>Montant devisé :</strong> {{ formatAmount(detailInvoice.quoted_amount, detailInvoice.currency) }}</p>
        <p><strong>Date d'échéance :</strong> {{ formatDate(detailInvoice.due_date) }}</p>
      </div>
      <p v-else style="color:var(--fg-3)">Aucune donnée disponible.</p>
    </Dialog>
  </AppLayout>
</template>

<style scoped>
.wh-row-btn { width:28px; height:28px; border-radius:var(--r-sm); border:1px solid var(--border-subtle); background:var(--bg-canvas); color:var(--fg-2); cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:all var(--dur-fast); }
.wh-row-btn:hover { background:var(--bg-sunken); color:var(--fg-1); border-color:var(--border-strong); }
.wh-row-btn-danger { color:var(--red-500); }
.wh-row-btn-danger:hover { background:var(--red-50); border-color:var(--red-300); color:var(--red-600); }
.inv-form { display:flex; flex-direction:column; gap:12px; }
.inv-field { display:flex; flex-direction:column; gap:4px; }
.inv-field label { font-size:12px; font-weight:500; color:var(--fg-2); }
.inv-error { color:var(--red-500); font-size:11px; }
</style>
