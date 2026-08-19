<template>
  <AppLayout>
    <Head title="Compte de résultat" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Accounting · Compte de résultat SYSCOHADA / Madagascar</h1>
        <p class="wh-page-subtitle">Chiffre d'affaires → marge → résultat d'exploitation → financier → net</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-secondary" @click="print"><i class="pi pi-print" style="font-size:13px" /> Imprimer / PDF</button>
        <button class="btn btn-primary" @click="load" :disabled="loading">
          <i :class="['pi', loading ? 'pi-spin pi-spinner' : 'pi-refresh']" style="font-size:13px" />
          Générer
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="wh-panel" style="padding:12px 16px;margin-bottom:16px;display:flex;flex-wrap:wrap;gap:12px;align-items:center">
      <div style="display:flex;flex-direction:column;gap:4px">
        <label class="wh-label">Période (AAAA-MM)</label>
        <input type="month" v-model="filters.period" class="wh-input" style="width:180px" />
      </div>
      <button class="btn btn-primary" style="align-self:flex-end" @click="load" :disabled="loading">Générer</button>
    </div>

    <div v-if="loading" class="wh-panel" style="padding:40px;text-align:center;color:var(--fg-3)">
      <i class="pi pi-spin pi-spinner" style="font-size:20px" /> Calcul en cours…
    </div>

    <div v-else-if="data" id="is-print">
      <!-- Cascade summary -->
      <div class="wh-kpi-grid" style="margin-bottom:16px">
        <div class="wh-kpi">
          <div class="wh-kpi-label">Chiffre d'affaires</div>
          <div class="wh-kpi-num font-display" style="color:var(--success)">{{ fmt(data.totaux.chiffre_affaires) }}</div>
        </div>
        <div class="wh-kpi">
          <div class="wh-kpi-label">Marge brute</div>
          <div class="wh-kpi-num font-display">{{ fmt(data.totaux.marge_brute) }}</div>
        </div>
        <div class="wh-kpi">
          <div class="wh-kpi-label">Résultat d'exploitation</div>
          <div class="wh-kpi-num font-display" :style="{ color: data.totaux.resultat_exploitation >= 0 ? 'var(--success)' : 'var(--danger)' }">
            {{ fmt(data.totaux.resultat_exploitation) }}
          </div>
        </div>
        <div class="wh-kpi">
          <div class="wh-kpi-label">Résultat financier</div>
          <div class="wh-kpi-num font-display" :style="{ color: data.totaux.resultat_financier >= 0 ? 'var(--success)' : 'var(--danger)' }">
            {{ fmt(data.totaux.resultat_financier) }}
          </div>
        </div>
        <div class="wh-kpi">
          <div class="wh-kpi-label">Résultat net</div>
          <div class="wh-kpi-num font-display" :style="{ color: data.totaux.resultat_net >= 0 ? 'var(--success)' : 'var(--danger)' }">
            {{ fmt(data.totaux.resultat_net) }}
          </div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <!-- PRODUITS -->
        <div class="wh-panel">
          <div style="padding:14px 16px;border-bottom:1px solid var(--border);font-weight:600;color:var(--success)">
            PRODUITS (Classe 7)
            <span style="float:right">{{ fmt(data.totaux.total_produits) }}</span>
          </div>
          <table class="wh-dt" style="font-size:13px">
            <thead>
              <tr>
                <th>Compte</th>
                <th class="num">Période</th>
                <th class="num">N-1</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in data.produits" :key="row.code" class="wh-dt-row">
                <td>
                  <span style="font-size:11px;color:var(--fg-3);font-family:monospace">{{ row.code }}</span>
                  <span style="margin-left:8px">{{ row.label_fr }}</span>
                </td>
                <td class="num" style="color:var(--success)">{{ fmt(row.current) }}</td>
                <td class="num">{{ fmt(row.previous) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr style="font-weight:700;background:var(--bg-2)">
                <td>Total produits</td>
                <td class="num">{{ fmt(data.totaux.total_produits) }}</td>
                <td class="num">{{ fmt(data.totaux.total_produits_n1) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- CHARGES -->
        <div class="wh-panel">
          <div style="padding:14px 16px;border-bottom:1px solid var(--border);font-weight:600;color:var(--danger)">
            CHARGES (Classe 6)
            <span style="float:right">{{ fmt(data.totaux.total_charges) }}</span>
          </div>
          <table class="wh-dt" style="font-size:13px">
            <thead>
              <tr>
                <th>Compte</th>
                <th class="num">Période</th>
                <th class="num">N-1</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in data.charges" :key="row.code" class="wh-dt-row">
                <td>
                  <span style="font-size:11px;color:var(--fg-3);font-family:monospace">{{ row.code }}</span>
                  <span style="margin-left:8px">{{ row.label_fr }}</span>
                </td>
                <td class="num" style="color:var(--danger)">{{ fmt(row.current) }}</td>
                <td class="num">{{ fmt(row.previous) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr style="font-weight:700;background:var(--bg-2)">
                <td>Total charges</td>
                <td class="num">{{ fmt(data.totaux.total_charges) }}</td>
                <td class="num">{{ fmt(data.totaux.total_charges_n1) }}</td>
              </tr>
              <tr style="font-weight:700;" :style="{ background: data.totaux.resultat_net >= 0 ? 'var(--success)' : 'var(--danger)', color: '#fff' }">
                <td>Résultat net</td>
                <td class="num">{{ fmt(data.totaux.resultat_net) }}</td>
                <td class="num">{{ fmt(data.totaux.resultat_net_n1) }}</td>
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
import { ref, reactive, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

const loading = ref(false)
const data = ref(null)

const filters = reactive({
  period: new Date().toISOString().slice(0, 7),
})

async function load() {
  loading.value = true
  try {
    const { data: res } = await axios.post('/api/v1/accounting/financial-reports/ohada/income-statement', {
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

function fmt(v) {
  if (v === null || v === undefined) return '—'
  return new Intl.NumberFormat('fr-MG', { maximumFractionDigits: 0 }).format(v) + ' Ar'
}

onMounted(load)
</script>

<style scoped>
.num { text-align: right; font-variant-numeric: tabular-nums; }
@media print {
  .page-head .page-actions, .wh-panel:first-child { display: none !important; }
}
</style>
