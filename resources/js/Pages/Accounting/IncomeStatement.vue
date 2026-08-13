<template>
  <AppLayout>
    <Head title="Compte de résultat" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Accounting · Compte de résultat</h1>
        <p class="wh-page-subtitle">Produits et charges sur la période</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-secondary" @click="window.print()"><i class="pi pi-print" style="font-size:13px" /> Imprimer / PDF</button>
        <button class="btn btn-primary" @click="load" :disabled="loading">
          <i :class="['pi', loading ? 'pi-spin pi-spinner' : 'pi-refresh']" style="font-size:13px" />
          Générer
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="wh-panel" style="padding:12px 16px;margin-bottom:16px;display:flex;flex-wrap:wrap;gap:12px;align-items:center">
      <div style="display:flex;gap:8px;align-items:center">
        <div style="display:flex;flex-direction:column;gap:4px">
          <label class="wh-label">Du</label>
          <input type="date" v-model="filters.from" class="wh-input" style="width:150px" />
        </div>
        <span style="align-self:flex-end;padding-bottom:6px;color:var(--fg-3)">→</span>
        <div style="display:flex;flex-direction:column;gap:4px">
          <label class="wh-label">Au</label>
          <input type="date" v-model="filters.to" class="wh-input" style="width:150px" />
        </div>
      </div>
      <div style="display:flex;gap:8px;align-items:center;padding-left:16px;border-left:1px solid var(--border)">
        <div style="display:flex;flex-direction:column;gap:4px">
          <label class="wh-label">Comparer du</label>
          <input type="date" v-model="filters.compare_from" class="wh-input" style="width:150px" />
        </div>
        <span style="align-self:flex-end;padding-bottom:6px;color:var(--fg-3)">→</span>
        <div style="display:flex;flex-direction:column;gap:4px">
          <label class="wh-label">Au</label>
          <input type="date" v-model="filters.compare_to" class="wh-input" style="width:150px" />
        </div>
      </div>
    </div>

    <div v-if="loading" class="wh-panel" style="padding:40px;text-align:center;color:var(--fg-3)">
      <i class="pi pi-spin pi-spinner" style="font-size:20px" /> Calcul en cours…
    </div>

    <div v-else-if="data" id="is-print">
      <!-- KPI summary -->
      <div class="wh-kpi-grid" style="margin-bottom:16px">
        <div class="wh-kpi">
          <div class="wh-kpi-label">Chiffre d'affaires</div>
          <div class="wh-kpi-num font-display" style="color:var(--success)">{{ fmt(data.revenue.total) }}</div>
        </div>
        <div class="wh-kpi">
          <div class="wh-kpi-label">Charges totales</div>
          <div class="wh-kpi-num font-display" style="color:var(--danger)">{{ fmt(data.expenses.total) }}</div>
        </div>
        <div class="wh-kpi">
          <div class="wh-kpi-label">Résultat net</div>
          <div class="wh-kpi-num font-display" :style="{ color: data.net_income >= 0 ? 'var(--success)' : 'var(--danger)' }">
            {{ fmt(data.net_income) }}
          </div>
        </div>
        <div class="wh-kpi" v-if="data.variance !== null">
          <div class="wh-kpi-label">Variation vs N-1</div>
          <div class="wh-kpi-num font-display" :style="{ color: data.variance >= 0 ? 'var(--success)' : 'var(--danger)' }">
            {{ (data.variance >= 0 ? '+' : '') + fmt(data.variance) }}
          </div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <!-- PRODUITS -->
        <div class="wh-panel">
          <div style="padding:14px 16px;border-bottom:1px solid var(--border);font-weight:600;color:var(--success)">
            PRODUITS (Revenus)
            <span style="float:right">{{ fmt(data.revenue.total) }}</span>
          </div>
          <table class="wh-dt" style="font-size:13px">
            <thead>
              <tr>
                <th>Compte</th>
                <th class="num">Période</th>
                <th class="num" v-if="data.compare_from">N-1</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in data.revenue.items" :key="row.id" class="wh-dt-row">
                <td>
                  <span style="font-size:11px;color:var(--fg-3);font-family:monospace">{{ row.code }}</span>
                  <span style="margin-left:8px">{{ row.name }}</span>
                </td>
                <td class="num" style="color:var(--success)">{{ fmt(row.balance) }}</td>
                <td class="num" v-if="data.compare_from">{{ fmt(row.prev_balance) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr style="font-weight:700;background:var(--bg-2)">
                <td>Total produits</td>
                <td class="num">{{ fmt(data.revenue.total) }}</td>
                <td class="num" v-if="data.compare_from">{{ fmt(data.revenue.previous_total) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- CHARGES -->
        <div class="wh-panel">
          <div style="padding:14px 16px;border-bottom:1px solid var(--border);font-weight:600;color:var(--danger)">
            CHARGES
            <span style="float:right">{{ fmt(data.expenses.total) }}</span>
          </div>
          <table class="wh-dt" style="font-size:13px">
            <thead>
              <tr>
                <th>Compte</th>
                <th class="num">Période</th>
                <th class="num" v-if="data.compare_from">N-1</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in data.expenses.items" :key="row.id" class="wh-dt-row">
                <td>
                  <span style="font-size:11px;color:var(--fg-3);font-family:monospace">{{ row.code }}</span>
                  <span style="margin-left:8px">{{ row.name }}</span>
                </td>
                <td class="num" style="color:var(--danger)">{{ fmt(row.balance) }}</td>
                <td class="num" v-if="data.compare_from">{{ fmt(row.prev_balance) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr style="font-weight:700;background:var(--bg-2)">
                <td>Total charges</td>
                <td class="num">{{ fmt(data.expenses.total) }}</td>
                <td class="num" v-if="data.compare_from">{{ fmt(data.expenses.previous_total) }}</td>
              </tr>
              <tr style="font-weight:700;" :style="{ background: data.net_income >= 0 ? 'var(--success)' : 'var(--danger)', color: '#fff' }">
                <td>Résultat net</td>
                <td class="num">{{ fmt(data.net_income) }}</td>
                <td class="num" v-if="data.compare_from">{{ fmt(data.net_income_previous) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>

    <div v-else class="wh-panel" style="padding:40px;text-align:center;color:var(--fg-3)">
      <i class="pi pi-chart-bar" style="font-size:32px;display:block;margin-bottom:12px" />
      Sélectionnez une période et cliquez sur Générer.
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

const now = new Date()
const filters = reactive({
  from: new Date(now.getFullYear(), 0, 1).toISOString().split('T')[0],
  to:   now.toISOString().split('T')[0],
  compare_from: new Date(now.getFullYear() - 1, 0, 1).toISOString().split('T')[0],
  compare_to:   new Date(now.getFullYear() - 1, 11, 31).toISOString().split('T')[0],
})

async function load() {
  loading.value = true
  try {
    const { data: res } = await axios.get('/api/v1/accounting/reports/income-statement', { params: filters })
    data.value = res
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function fmt(v) {
  if (v === null || v === undefined) return '—'
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(v)
}
</script>

<style scoped>
.num { text-align: right; font-variant-numeric: tabular-nums; }
@media print {
  .page-head .page-actions, .wh-panel:first-child { display: none !important; }
}
</style>
