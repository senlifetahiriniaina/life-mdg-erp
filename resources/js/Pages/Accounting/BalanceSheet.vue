<template>
  <AppLayout>
    <Head title="Bilan comptable" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Accounting · Bilan comptable</h1>
        <p class="wh-page-subtitle">Actif / Passif / Capitaux propres au {{ formatDate(data?.as_of) }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-secondary" @click="print"><i class="pi pi-print" style="font-size:13px" /> Imprimer / PDF</button>
        <button class="btn btn-primary" @click="load" :disabled="loading">
          <i class="pi pi-refresh" style="font-size:13px" />
          Actualiser
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="wh-panel" style="padding:12px 16px;margin-bottom:16px;display:flex;flex-wrap:wrap;gap:12px;align-items:center">
      <div style="display:flex;flex-direction:column;gap:4px">
        <label class="wh-label">Au (date de référence)</label>
        <input type="date" v-model="filters.as_of" class="wh-input" style="width:180px" />
      </div>
      <div style="display:flex;flex-direction:column;gap:4px">
        <label class="wh-label">Comparer au (N-1)</label>
        <input type="date" v-model="filters.compare_as_of" class="wh-input" style="width:180px" />
      </div>
      <button class="btn btn-primary" style="align-self:flex-end" @click="load" :disabled="loading">Générer</button>
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
            <span style="float:right;color:var(--halo-blue)">{{ fmt(data.assets.total) }}</span>
          </div>
          <table class="wh-dt" style="font-size:13px">
            <thead>
              <tr>
                <th>Compte</th>
                <th class="num">{{ formatDate(data.as_of) }}</th>
                <th class="num" v-if="data.compare">{{ formatDate(data.compare) }}</th>
                <th class="num" v-if="data.compare">Variation</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in data.assets.items" :key="row.id" class="wh-dt-row">
                <td>
                  <span style="font-size:11px;color:var(--fg-3);font-family:monospace">{{ row.code }}</span>
                  <span style="margin-left:8px">{{ row.name }}</span>
                </td>
                <td class="num" :class="row.balance < 0 ? 'text-danger' : ''">{{ fmt(row.balance) }}</td>
                <td class="num" v-if="data.compare">{{ fmt(row.prev_balance) }}</td>
                <td class="num" v-if="data.compare" :class="varianceClass(row.balance, row.prev_balance)">
                  {{ fmtVariance(row.balance, row.prev_balance) }}
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr style="font-weight:700;background:var(--bg-2)">
                <td>Total Actif</td>
                <td class="num">{{ fmt(data.assets.total) }}</td>
                <td class="num" v-if="data.compare">{{ fmt(data.assets.previous_total) }}</td>
                <td class="num" v-if="data.compare" :class="varianceClass(data.assets.total, data.assets.previous_total)">
                  {{ fmtVariance(data.assets.total, data.assets.previous_total) }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- PASSIF + CAPITAUX -->
        <div>
          <!-- Passif -->
          <div class="wh-panel" style="margin-bottom:16px">
            <div style="padding:14px 16px;border-bottom:1px solid var(--border);font-weight:600;color:var(--fg-1)">
              PASSIF
              <span style="float:right;color:var(--danger)">{{ fmt(data.liabilities.total) }}</span>
            </div>
            <table class="wh-dt" style="font-size:13px">
              <thead>
                <tr>
                  <th>Compte</th>
                  <th class="num">{{ formatDate(data.as_of) }}</th>
                  <th class="num" v-if="data.compare">{{ formatDate(data.compare) }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in data.liabilities.items" :key="row.id" class="wh-dt-row">
                  <td>
                    <span style="font-size:11px;color:var(--fg-3);font-family:monospace">{{ row.code }}</span>
                    <span style="margin-left:8px">{{ row.name }}</span>
                  </td>
                  <td class="num">{{ fmt(row.balance) }}</td>
                  <td class="num" v-if="data.compare">{{ fmt(row.prev_balance) }}</td>
                </tr>
              </tbody>
              <tfoot>
                <tr style="font-weight:700;background:var(--bg-2)">
                  <td>Total Passif</td>
                  <td class="num">{{ fmt(data.liabilities.total) }}</td>
                  <td class="num" v-if="data.compare">{{ fmt(data.liabilities.previous_total) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>

          <!-- Capitaux propres -->
          <div class="wh-panel">
            <div style="padding:14px 16px;border-bottom:1px solid var(--border);font-weight:600;color:var(--fg-1)">
              CAPITAUX PROPRES
              <span style="float:right;color:var(--success)">{{ fmt(data.equity.total) }}</span>
            </div>
            <table class="wh-dt" style="font-size:13px">
              <thead>
                <tr>
                  <th>Compte</th>
                  <th class="num">{{ formatDate(data.as_of) }}</th>
                  <th class="num" v-if="data.compare">{{ formatDate(data.compare) }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in data.equity.items" :key="row.id" class="wh-dt-row">
                  <td>
                    <span style="font-size:11px;color:var(--fg-3);font-family:monospace">{{ row.code }}</span>
                    <span style="margin-left:8px">{{ row.name }}</span>
                  </td>
                  <td class="num">{{ fmt(row.balance) }}</td>
                  <td class="num" v-if="data.compare">{{ fmt(row.prev_balance) }}</td>
                </tr>
              </tbody>
              <tfoot>
                <tr style="font-weight:700;background:var(--bg-2)">
                  <td>Total Capitaux propres</td>
                  <td class="num">{{ fmt(data.equity.total) }}</td>
                  <td class="num" v-if="data.compare">{{ fmt(data.equity.previous_total) }}</td>
                </tr>
                <tr style="font-weight:700;background:var(--halo-blue);color:#fff">
                  <td>Total Passif + Capitaux</td>
                  <td class="num">{{ fmt((data.liabilities.total || 0) + (data.equity.total || 0)) }}</td>
                  <td class="num" v-if="data.compare">
                    {{ fmt((data.liabilities.previous_total || 0) + (data.equity.previous_total || 0)) }}
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="wh-panel" style="padding:40px;text-align:center;color:var(--fg-3)">
      <i class="pi pi-file-edit" style="font-size:32px;display:block;margin-bottom:12px" />
      Sélectionnez une date de référence et cliquez sur Générer.
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

const loading = ref(false)
const data = ref(null)
const today = new Date().toISOString().split('T')[0]
const lastYear = new Date(new Date().getFullYear() - 1, 11, 31).toISOString().split('T')[0]

const filters = reactive({
  as_of: today,
  compare_as_of: lastYear,
})

async function load() {
  loading.value = true
  try {
    const params = { as_of: filters.as_of }
    if (filters.compare_as_of) params.compare_as_of = filters.compare_as_of
    const { data: res } = await axios.get('/api/v1/accounting/reports/balance-sheet', { params })
    data.value = res
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function print() {
  window.print()
}

function fmt(v) {
  if (v === null || v === undefined) return '—'
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(v)
}

function formatDate(d) {
  if (!d) return ''
  return new Intl.DateTimeFormat('fr-FR').format(new Date(d))
}

function fmtVariance(current, previous) {
  if (previous === null || previous === undefined) return '—'
  const v = (current || 0) - (previous || 0)
  return (v >= 0 ? '+' : '') + fmt(v)
}

function varianceClass(current, previous) {
  if (previous === null || previous === undefined) return ''
  return (current || 0) >= (previous || 0) ? 'text-success' : 'text-danger'
}
</script>

<style scoped>
.num { text-align: right; font-variant-numeric: tabular-nums; }
.text-success { color: var(--success); }
.text-danger  { color: var(--danger); }
@media print {
  .page-head .page-actions, .wh-panel:first-child { display: none !important; }
  #balance-sheet-print { display: block; }
  .wh-panel { box-shadow: none; border: 1px solid #ddd; }
}
</style>
