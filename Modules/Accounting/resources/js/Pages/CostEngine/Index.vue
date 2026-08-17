<template>
  <AppLayout>
    <Head title="Moteur de coûts" />

    <div class="wh-page">
      <div class="wh-page-header">
        <div>
          <h1 class="wh-page-title">Moteur de coûts (CAPEX/OPEX/FINEX/RISKEX)</h1>
          <p class="wh-page-subtitle">Répartition des coûts par catégorie, comparaison aux benchmarks, anomalies</p>
        </div>
      </div>

      <div class="wh-filters">
        <input v-model="period" type="month" class="wh-input" @change="loadSummary" />
        <button class="wh-btn wh-btn-secondary" @click="loadSummary">Actualiser</button>
      </div>

      <div v-if="loading" class="wh-loading-state"><i class="pi pi-spin pi-spinner" /> Chargement...</div>

      <template v-else>
        <div class="wh-kpi-row" v-if="summary">
          <div class="wh-kpi"><div class="wh-kpi-label">CAPEX</div><div class="wh-kpi-value">{{ fmt(summary.CAPEX?.total) }}</div><div class="wh-kpi-sub">{{ summary.structure_pct?.CAPEX }}%</div></div>
          <div class="wh-kpi"><div class="wh-kpi-label">OPEX</div><div class="wh-kpi-value">{{ fmt(summary.OPEX?.total) }}</div><div class="wh-kpi-sub">{{ summary.structure_pct?.OPEX }}%</div></div>
          <div class="wh-kpi"><div class="wh-kpi-label">FINEX</div><div class="wh-kpi-value">{{ fmt(summary.FINEX?.total) }}</div><div class="wh-kpi-sub">{{ summary.structure_pct?.FINEX }}%</div></div>
          <div class="wh-kpi"><div class="wh-kpi-label">RISKEX</div><div class="wh-kpi-value">{{ fmt(summary.RISKEX?.total) }}</div><div class="wh-kpi-sub">{{ summary.structure_pct?.RISKEX }}%</div></div>
          <div class="wh-kpi"><div class="wh-kpi-label">Total</div><div class="wh-kpi-value">{{ fmt(summary.total) }}</div></div>
        </div>

        <h2 class="wh-section-title">Benchmarks sectoriels</h2>
        <div class="wh-filters">
          <input v-model="industry" class="wh-input" placeholder="Secteur (ex: manufacturing)" @change="loadBenchmarks" />
          <button class="wh-btn wh-btn-secondary" @click="loadBenchmarks">Charger</button>
        </div>
        <table class="wh-table" v-if="benchmark">
          <thead><tr><th>Catégorie</th><th>Benchmark %</th></tr></thead>
          <tbody>
            <tr v-for="(pct, cat) in benchmark.benchmark?.benchmark ?? {}" :key="cat">
              <td>{{ cat }}</td><td>{{ pct }}%</td>
            </tr>
          </tbody>
        </table>

        <h2 class="wh-section-title">Anomalies détectées ({{ anomalies.length }})</h2>
        <table class="wh-table">
          <thead><tr><th>Sévérité</th><th>Entité</th><th>Catégorie</th><th>Message</th></tr></thead>
          <tbody>
            <tr v-for="(a, i) in anomalies" :key="i">
              <td><span :class="['wh-badge', a.severity === 'high' ? 'wh-badge-red' : 'wh-badge-slate']">{{ a.severity }}</span></td>
              <td>{{ a.entity_name ?? `${a.entity_type} #${a.entity_id}` }}</td>
              <td>{{ a.category }}</td>
              <td>{{ a.message }}</td>
            </tr>
            <tr v-if="anomalies.length === 0"><td colspan="4" class="wh-empty-state">Aucune anomalie détectée pour cette période.</td></tr>
          </tbody>
        </table>
      </template>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

const period = ref(new Date().toISOString().slice(0, 7))
const industry = ref('manufacturing')
const loading = ref(true)
const summary = ref(null)
const benchmark = ref(null)
const anomalies = ref([])

const loadSummary = async () => {
  loading.value = true
  try {
    const [s, a] = await Promise.all([
      axios.get('/api/v1/accounting/cost-engine/summary', { params: { period: period.value } }),
      axios.get('/api/v1/accounting/cost-engine/anomalies', { params: { period: period.value } }),
    ])
    summary.value = s.data
    anomalies.value = a.data.anomalies ?? []
  } catch (e) {
    console.error(e)
  } finally { loading.value = false }
}

const loadBenchmarks = async () => {
  try {
    const { data } = await axios.get('/api/v1/accounting/cost-engine/benchmarks', { params: { industry: industry.value } })
    benchmark.value = data
  } catch (e) { console.error(e) }
}

onMounted(() => { loadSummary(); loadBenchmarks() })

const fmt = (n) => new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n ?? 0)
</script>

<style scoped>
.wh-page { max-width: 1100px; margin: 0 auto; padding: 24px; }
.wh-page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; }
.wh-page-title { font-size: 20px; font-weight: 700; margin: 0; }
.wh-page-subtitle { font-size: 13px; color: #6B7280; margin-top: 2px; }
.wh-filters { display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; align-items: center; }
.wh-input { padding: 7px 10px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 13px; }
.wh-section-title { font-size: 15px; font-weight: 600; margin: 24px 0 12px; }
.wh-kpi-row { display: flex; gap: 16px; margin-bottom: 16px; flex-wrap: wrap; }
.wh-kpi { flex: 1; min-width: 140px; padding: 14px 16px; border: 1px solid #E5E7EB; border-radius: 8px; background: #fff; }
.wh-kpi-label { font-size: 11px; text-transform: uppercase; color: #6B7280; margin-bottom: 4px; }
.wh-kpi-value { font-size: 18px; font-weight: 700; }
.wh-kpi-sub { font-size: 12px; color: #6B7280; }
.wh-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 8px; }
.wh-table th { text-align: left; font-size: 11px; text-transform: uppercase; color: #6B7280; padding: 10px 12px; border-bottom: 1px solid #E5E7EB; background: #F9FAFB; }
.wh-table td { padding: 10px 12px; border-bottom: 1px solid #E5E7EB; }
.wh-badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.wh-badge-red { background: #FEE2E2; color: #991B1B; }
.wh-badge-slate { background: #F3F4F6; color: #6B7280; }
.wh-btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px; border-radius: 7px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; }
.wh-btn-secondary { background: #F3F4F6; color: #374151; border: 1px solid #E5E7EB; }
.wh-loading-state, .wh-empty-state { text-align: center; padding: 40px; color: #9CA3AF; font-size: 13px; }
</style>
