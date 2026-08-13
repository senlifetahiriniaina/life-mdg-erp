<script setup lang="ts">
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Select from 'primevue/select'
import Paginator from 'primevue/paginator'

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
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin',
  })
  if (res.ok) load(invoices.value.current_page)
}

async function disputeInvoice(invoice: FreightInvoice) {
  const reason = prompt('Raison du litige :')
  if (!reason) return
  const res = await fetch(`/api/v1/logistics/freight-invoices/${invoice.id}/dispute`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
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

load()
</script>

<template>
  <AppLayout>
    <Head title="Factures de fret" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Factures de fret</h1>
        <p class="wh-page-subtitle">{{ invoices.total }} facture{{ invoices.total !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="router.visit('/logistics/freight-invoices/create')">
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
                <button class="wh-row-btn" title="Voir" @click="router.visit(`/logistics/freight-invoices/${inv.id}`)">
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
  </AppLayout>
</template>

<style scoped>
.wh-row-btn { width:28px; height:28px; border-radius:var(--r-sm); border:1px solid var(--border-subtle); background:var(--bg-canvas); color:var(--fg-2); cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:all var(--dur-fast); }
.wh-row-btn:hover { background:var(--bg-sunken); color:var(--fg-1); border-color:var(--border-strong); }
.wh-row-btn-danger { color:var(--red-500); }
.wh-row-btn-danger:hover { background:var(--red-50); border-color:var(--red-300); color:var(--red-600); }
</style>
