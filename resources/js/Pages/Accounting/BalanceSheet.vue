<template>
  <AppLayout>
    <Head title="Bilan comptable" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Accounting · Bilan SYSCOHADA / Madagascar</h1>
        <p class="wh-page-subtitle">Actif immobilisé / Stocks / Créances / Trésorerie — période {{ data?.period }}</p>
      </div>
      <div class="page-actions">
        <Link href="/accounting/financial-simulations" class="btn btn-secondary"><i class="pi pi-chart-line" style="font-size:13px" /> Simulation financière</Link>
        <button class="btn btn-secondary" @click="print"><i class="pi pi-print" style="font-size:13px" /> Imprimer / PDF</button>
        <button class="btn btn-secondary" @click="exportFile('pdf')" :disabled="exporting">
          <i class="pi pi-file-pdf" style="font-size:13px" /> Exporter PDF
        </button>
        <button class="btn btn-secondary" @click="exportFile('excel')" :disabled="exporting">
          <i class="pi pi-file-excel" style="font-size:13px" /> Exporter Excel
        </button>
        <button class="btn btn-primary" @click="load" :disabled="loading">
          <i class="pi pi-refresh" style="font-size:13px" />
          Actualiser
        </button>
      </div>
    </div>

    <AIAssistantPanel v-if="guidance" :guidance="guidance" />

    <!-- Filters -->
    <div class="wh-panel" style="padding:12px 16px;margin-bottom:16px;display:flex;flex-wrap:wrap;gap:12px;align-items:center">
      <div style="display:flex;flex-direction:column;gap:4px">
        <label class="wh-label">Période (AAAA-MM)</label>
        <input type="month" v-model="filters.period" class="wh-input" style="width:180px" />
      </div>
      <button class="btn btn-primary" style="align-self:flex-end" @click="load" :disabled="loading">Générer</button>
      <span v-if="data" class="wh-badge" :class="data.equilibre ? 'badge-success' : 'badge-danger'" style="align-self:flex-end">
        {{ data.equilibre ? 'Bilan équilibré' : 'Déséquilibre détecté' }}
      </span>
    </div>

    <div v-if="loading" class="wh-panel" style="padding:40px;text-align:center;color:var(--fg-3)">
      <i class="pi pi-spin pi-spinner" style="font-size:20px" /> Calcul en cours…
    </div>

    <div v-else-if="data" id="balance-sheet-print">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">

        <!-- ACTIF -->
        <div class="wh-panel">
          <div style="padding:14px 16px;border-bottom:1px solid var(--border);font-weight:600;color:var(--fg-1)">
            ACTIF
            <span style="float:right;color:var(--halo-blue)">{{ fmt(data.totaux.total_actif) }}</span>
          </div>
          <div v-for="(section, key) in data.actif" :key="key" style="border-bottom:1px solid var(--border)">
            <div style="padding:10px 16px;font-weight:600;font-size:13px;background:var(--bg-2)">
              {{ section.label_fr }}
              <span style="float:right">{{ fmt(section.total) }}</span>
            </div>
            <table v-if="section.accounts?.length" class="wh-dt" style="font-size:12px">
              <tbody>
                <tr v-for="acc in section.accounts" :key="acc.account_code" class="wh-dt-row">
                  <td><span style="font-family:monospace;color:var(--fg-3)">{{ acc.account_code }}</span></td>
                  <td class="num">{{ fmt(acc.montant) }}</td>
                </tr>
              </tbody>
            </table>
            <p v-else style="padding:6px 16px;font-size:12px;color:var(--fg-3)">Aucun mouvement</p>
          </div>
        </div>

        <!-- PASSIF -->
        <div class="wh-panel">
          <div style="padding:14px 16px;border-bottom:1px solid var(--border);font-weight:600;color:var(--fg-1)">
            PASSIF
            <span style="float:right;color:var(--danger)">{{ fmt(data.totaux.total_passif) }}</span>
          </div>
          <div v-for="(section, key) in data.passif" :key="key" style="border-bottom:1px solid var(--border)">
            <div style="padding:10px 16px;font-weight:600;font-size:13px;background:var(--bg-2)">
              {{ section.label_fr }}
              <span style="float:right">{{ fmt(section.total) }}</span>
            </div>
            <table v-if="section.accounts?.length" class="wh-dt" style="font-size:12px">
              <tbody>
                <tr v-for="acc in section.accounts" :key="acc.account_code" class="wh-dt-row">
                  <td>
                    <span style="font-family:monospace;color:var(--fg-3)">{{ acc.account_code }}</span>
                    <span v-if="acc.label_fr" style="margin-left:6px;font-style:italic">{{ acc.label_fr }}</span>
                  </td>
                  <td class="num">{{ fmt(acc.montant) }}</td>
                </tr>
              </tbody>
            </table>
            <p v-else style="padding:6px 16px;font-size:12px;color:var(--fg-3)">Aucun mouvement</p>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="wh-panel" style="padding:40px;text-align:center;color:var(--fg-3)">
      <i class="pi pi-file-edit" style="font-size:32px;display:block;margin-bottom:12px" />
      Sélectionnez une période et cliquez sur Générer.
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'

const { guidance } = useAiAssistant('Accounting', 'view_balance_sheet')

const loading = ref(false)
const exporting = ref(false)
const data = ref(null)

const filters = reactive({
  period: new Date().toISOString().slice(0, 7),
})

async function load() {
  loading.value = true
  try {
    const { data: res } = await axios.post('/api/v1/accounting/financial-reports/ohada/balance-sheet', {
      period: filters.period,
    })
    data.value = res.data
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function print() {
  window.print()
}

// Chantier 29: real PDF/Excel export — the endpoint returns a binary file,
// so a plain fetch()-to-blob download is used here rather than axios (same
// pattern already established by Invoices/Index.vue's export button; a GET
// request needs no CSRF header either way).
async function exportFile(format) {
  exporting.value = true
  try {
    const accept = format === 'pdf'
      ? 'application/pdf'
      : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    const url = `/api/v1/accounting/financial-reports/balance-sheet/export/${format}?period=${encodeURIComponent(filters.period)}`
    const res = await fetch(url, { headers: { Accept: accept } })
    if (!res.ok) throw new Error('Export failed')
    const blob = await res.blob()
    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob)
    a.download = `bilan-syscohada-${filters.period}.${format === 'pdf' ? 'pdf' : 'xlsx'}`
    a.click()
  } catch (e) {
    console.error(e)
  } finally {
    exporting.value = false
  }
}

function fmt(v) {
  if (v === null || v === undefined) return '—'
  return new Intl.NumberFormat('fr-MG', { maximumFractionDigits: 0 }).format(v) + ' Ar'
}

onMounted(load)
</script>

<style scoped>
.num { text-align: right; font-variant-numeric: tabular-nums; }
.badge-success { background: var(--success); color: #fff; padding: 4px 10px; border-radius: 4px; font-size: 12px; }
.badge-danger { background: var(--danger); color: #fff; padding: 4px 10px; border-radius: 4px; font-size: 12px; }
@media print {
  .page-head .page-actions, .wh-panel:first-child { display: none !important; }
  #balance-sheet-print { display: block; }
  .wh-panel { box-shadow: none; border: 1px solid #ddd; }
}
</style>
