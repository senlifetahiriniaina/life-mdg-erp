<template>
  <AppLayout>
    <Head title="Factures" />

    <!-- Page header -->
    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Ventes · Factures</h1>
        <p class="wh-page-subtitle">{{ invoices.total }} facture{{ invoices.total !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-secondary" @click="downloadExcel" :disabled="exporting">
          <i :class="['pi', exporting ? 'pi-spin pi-spinner' : 'pi-file-excel']" style="font-size:13px" /> Export Excel
        </button>
        <button class="btn btn-primary" @click="router.get('/accounting/invoices/create')"><i class="pi pi-plus" style="font-size:13px" /> Nouvelle facture</button>
      </div>
    </div>

    <!-- KPI row -->
    <div class="wh-kpi-grid" style="margin-bottom:16px">
      <div class="wh-kpi" v-for="s in statusCards" :key="s.label">
        <div class="wh-kpi-label">{{ s.label }}</div>
        <div class="wh-kpi-num font-display">{{ s.amount }}</div>
        <div style="font-size:12px;color:var(--fg-3);margin-top:4px">{{ s.count }} facture{{ s.count !== 1 ? 's' : '' }}</div>
      </div>
    </div>

    <!-- Filter pills -->
    <div style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap">
      <button
        v-for="f in filterOptions" :key="f.value"
        :class="['filter-pill', activeFilter === f.value ? 'filter-pill-on' : '']"
        @click="activeFilter = f.value"
      >{{ f.label }}</button>
    </div>

    <!-- Table -->
    <div class="wh-panel" style="position:relative">
      <table class="wh-dt" :class="{ 'wh-dt-loading': loading }">
        <thead>
          <tr>
            <th>Numéro</th>
            <th>Client</th>
            <th>Date</th>
            <th class="num">Total</th>
            <th class="num">Solde dû</th>
            <th>Statut</th>
            <th style="width:60px"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="inv in filteredInvoices" :key="inv.id" class="wh-dt-row" @click="router.get(`/accounting/invoices/${inv.id}`)">
            <td><span style="font-family:var(--font-mono);font-size:12px;color:var(--fg-3)">{{ inv.number }}</span></td>
            <td style="font-weight:500;color:var(--fg-1)">{{ inv.partner_name }}</td>
            <td style="color:var(--fg-2)">{{ formatDate(inv.invoice_date) }}</td>
            <td class="num" style="font-variant-numeric:tabular-nums;font-weight:500">{{ formatMoney(inv.total) }}</td>
            <td class="num" style="font-variant-numeric:tabular-nums" :class="inv.status === 'overdue' ? 'text-danger' : ''">{{ formatMoney(inv.amount_due) }}</td>
            <td>
              <span :class="['wh-badge', invoiceStatusClass(inv.status)]">
                <span class="wh-badge-dot" />{{ statusLabel(inv.status) }}
              </span>
            </td>
            <td style="display:flex;gap:4px" @click.stop>
              <button class="wh-row-btn" title="Voir" @click="router.get(`/accounting/invoices/${inv.id}`)"><i class="pi pi-eye" style="font-size:13px" /></button>
              <a :href="`/api/v1/accounting/invoices/${inv.id}/pdf`" target="_blank" class="wh-row-btn" title="Télécharger PDF">
                <i class="pi pi-file-pdf" style="font-size:13px;color:#e53e3e" />
              </a>
            </td>
          </tr>
          <tr v-if="filteredInvoices.length === 0">
            <td colspan="7" style="text-align:center;padding:48px;color:var(--fg-3);font-size:14px">Aucune facture trouvée.</td>
          </tr>
        </tbody>
      </table>
      <div v-if="loading" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(247,248,251,0.6)">
        <i class="pi pi-spin pi-spinner" style="font-size:20px;color:var(--halo-500)" />
      </div>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-top:1px solid var(--border-subtle)">
        <span style="font-size:13px;color:var(--fg-3)">{{ invoices.total }} résultat{{ invoices.total !== 1 ? 's' : '' }}</span>
        <Paginator :rows="invoices.per_page" :total-records="invoices.total" :first="(invoices.current_page - 1) * invoices.per_page" />
      </div>
    </div>
    <GuidedTour tour-id="accounting-invoices" :steps="tourSteps" />
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import Paginator from 'primevue/paginator'
import AppLayout from '@/Layouts/AppLayout.vue'
import GuidedTour from '@/Components/UI/GuidedTour.vue'

const props = defineProps({
  invoices: { type: Object, required: true },
  summary:  { type: Object, default: () => ({}) },
})

const loading      = ref(false)
const activeFilter = ref('all')
const exporting    = ref(false)

async function downloadExcel() {
  exporting.value = true
  try {
    const params = new URLSearchParams()
    if (activeFilter.value !== 'all') params.set('status', activeFilter.value)
    const url = `/api/v1/accounting/invoices/export/excel?${params.toString()}`
    const res = await fetch(url, { headers: { Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' } })
    if (!res.ok) throw new Error('Export failed')
    const blob = await res.blob()
    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob)
    a.download = 'invoices.xlsx'
    a.click()
  } finally {
    exporting.value = false
  }
}

const tourSteps = [
  { tag: 'KPIs · Étape 1 / 5', icon: 'pi-chart-bar', title: 'Vue d\'ensemble des montants', description: 'Les tuiles KPI affichent les totaux par statut : brouillons, envoyées, payées et en retard.' },
  { tag: 'Filtres · Étape 2 / 5', icon: 'pi-filter', title: 'Filtrer par statut', description: 'Cliquez sur un statut pour afficher uniquement les factures correspondantes.' },
  { tag: 'Tableau · Étape 3 / 5', icon: 'pi-list', title: 'Liste des factures', description: 'Numéro, client, date d\'échéance, montant et solde dû en un coup d\'œil.' },
  { tag: 'PDF · Étape 4 / 5', icon: 'pi-file-pdf', title: 'Télécharger en PDF', description: 'L\'icône PDF sur chaque ligne génère et télécharge la facture au format A4 en un clic.' },
  { tag: 'Export · Étape 5 / 5', icon: 'pi-file-excel', title: 'Export Excel groupé', description: 'Le bouton « Export Excel » exporte toutes les factures filtrées dans un fichier XLSX stylisé.' },
]

const filterOptions = [
  { label: 'Toutes',     value: 'all' },
  { label: 'En retard',  value: 'overdue' },
  { label: 'Envoyées',   value: 'sent' },
  { label: 'Payées',     value: 'paid' },
  { label: 'Brouillons', value: 'draft' },
]

const statusCards = [
  { label: 'Brouillons', amount: '0 €', count: 0 },
  { label: 'Envoyées',   amount: '0 €', count: 0 },
  { label: 'Payées',     amount: '0 €', count: 0 },
  { label: 'En retard',  amount: '0 €', count: 0 },
]

const filteredInvoices = computed(() => {
  const data = props.invoices.data ?? []
  if (activeFilter.value === 'all') return data
  return data.filter(i => i.status === activeFilter.value)
})

const invoiceStatusClass = (s) => ({
  paid:      'wh-badge-green',
  sent:      'wh-badge-blue',
  overdue:   'wh-badge-amber',
  draft:     'wh-badge-slate',
  cancelled: 'wh-badge-red',
  refunded:  'wh-badge-slate',
}[s] ?? 'wh-badge-slate')

const statusLabel = (s) => ({
  paid: 'Payée', sent: 'Envoyée', overdue: 'En retard',
  draft: 'Brouillon', cancelled: 'Annulée', refunded: 'Remboursée',
}[s] ?? s)

const formatDate  = (d) => d ? new Date(d).toLocaleDateString('fr-FR') : '—'
const formatMoney = (v) => v != null ? Number(v).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €' : '—'
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; gap:16px; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; gap:8px; }
.btn { font-family:var(--font-sans); font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:background var(--dur-base) var(--ease-out); line-height:1.2; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover { background:var(--halo-700); }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover { background:var(--bg-sunken); }
.filter-pill { height:28px; padding:0 12px; border-radius:var(--r-pill); border:1px solid var(--border-subtle); background:var(--bg-canvas); font-size:12px; font-weight:500; color:var(--fg-2); cursor:pointer; transition:all var(--dur-base); }
.filter-pill:hover { background:var(--bg-sunken); color:var(--fg-1); }
.filter-pill-on { background:var(--halo-50); border-color:var(--halo-200); color:var(--halo-700); }
.wh-dt { width:100%; border-collapse:collapse; font-size:14px; }
.wh-dt thead th { text-align:left; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:var(--fg-3); font-weight:600; padding:10px 18px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sunken); }
.wh-dt thead th.num { text-align:right; }
.wh-dt-row td { padding:11px 18px; border-bottom:1px solid var(--border-subtle); color:var(--fg-1); }
.wh-dt-row td.num { text-align:right; }
.wh-dt-row:last-child td { border-bottom:0; }
.wh-dt-row { transition:background var(--dur-fast); cursor:pointer; }
.wh-dt-row:hover { background:var(--bg-sunken); }
.wh-dt-loading { opacity:0.5; pointer-events:none; }
.text-danger { color:var(--danger-fg); font-weight:500; }
.wh-row-btn { width:28px; height:28px; border-radius:var(--r-sm); border:1px solid var(--border-subtle); background:var(--bg-canvas); color:var(--fg-2); cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:all var(--dur-fast); }
.wh-row-btn:hover { background:var(--bg-sunken); color:var(--fg-1); border-color:var(--border-strong); }
.wh-badge { display:inline-flex; align-items:center; gap:5px; padding:2px 8px; border-radius:var(--r-pill); font-size:11px; font-weight:500; }
.wh-badge-dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
.wh-badge-green  { background:var(--success-bg); color:var(--success-fg); }
.wh-badge-blue   { background:var(--halo-50); color:var(--halo-700); }
.wh-badge-amber  { background:var(--warn-bg); color:var(--warn-fg); }
.wh-badge-red    { background:var(--danger-bg); color:var(--danger-fg); }
.wh-badge-slate  { background:var(--bg-sunken); color:var(--fg-2); }
</style>
