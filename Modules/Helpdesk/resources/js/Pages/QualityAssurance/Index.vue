<template>
  <AppLayout>
    <Head title="Helpdesk · Performance & Qualité" />
    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Performance & Qualité</h1>
        <p class="wh-page-subtitle">Métriques agent, tendances et benchmarking d'équipe</p>
      </div>
    </div>

    <!-- Agent metrics -->
    <div class="wh-panel" style="padding:18px;margin-bottom:20px">
      <h3 class="section-title">Métriques agent</h3>
      <div class="filter-row">
        <input v-model.number="metricsForm.agent_id" type="number" placeholder="ID agent" class="wh-input" style="width:130px" />
        <input v-model="metricsForm.start_date" type="date" class="wh-input" />
        <input v-model="metricsForm.end_date" type="date" class="wh-input" />
        <button class="btn btn-primary" :disabled="!canLoadMetrics || loadingMetrics" @click="loadMetrics">
          <i v-if="loadingMetrics" class="pi pi-spin pi-spinner" style="font-size:12px" /> Charger
        </button>
      </div>
      <p v-if="metricsError" class="empty-msg">{{ metricsError }}</p>
      <table v-else-if="metrics.length" class="wh-dt">
        <thead>
          <tr>
            <th>Date</th>
            <th>Tickets traités</th>
            <th>Satisfaction moy.</th>
            <th>Taux FCR</th>
            <th>Taux escalade</th>
            <th>Score global</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="m in metrics" :key="m.date" class="wh-dt-row">
            <td>{{ m.date }}</td>
            <td>{{ m.tickets_handled }}</td>
            <td>{{ m.avg_satisfaction ?? '—' }}</td>
            <td>{{ m.first_contact_resolution_rate !== null ? pct(m.first_contact_resolution_rate) : '—' }}</td>
            <td>{{ m.escalation_rate !== null ? pct(m.escalation_rate) : '—' }}</td>
            <td>{{ m.overall_performance ?? '—' }}</td>
          </tr>
        </tbody>
      </table>
      <p v-else-if="metricsLoaded" class="empty-msg">Aucune métrique pour cette période.</p>
    </div>

    <!-- Agent performance trends -->
    <div class="wh-panel" style="padding:18px;margin-bottom:20px">
      <h3 class="section-title">Tendances de performance</h3>
      <div class="filter-row">
        <input v-model.number="trendsForm.agent_id" type="number" placeholder="ID agent" class="wh-input" style="width:130px" />
        <select v-model="trendsForm.period_type" class="wh-input" style="width:150px">
          <option value="monthly">Mensuel</option>
          <option value="weekly">Hebdomadaire</option>
          <option value="quarterly">Trimestriel</option>
        </select>
        <button class="btn btn-primary" :disabled="!trendsForm.agent_id || loadingTrends" @click="loadTrends">
          <i v-if="loadingTrends" class="pi pi-spin pi-spinner" style="font-size:12px" /> Charger
        </button>
      </div>
      <div v-if="trends.length" style="display:flex;flex-direction:column;gap:8px">
        <div v-for="(t, i) in trends" :key="i" class="trend-row">
          <span style="font-size:12px;color:var(--fg-3)">{{ t.period?.[0] }} → {{ t.period?.[1] }}</span>
          <span :class="['badge', trendBadge(t.direction)]">{{ t.direction ?? '—' }}</span>
        </div>
      </div>
      <p v-else-if="trendsLoaded" class="empty-msg">Aucune tendance disponible pour cet agent.</p>
    </div>

    <!-- Team benchmarking -->
    <div class="wh-panel" style="padding:18px">
      <h3 class="section-title">Benchmarking d'équipe</h3>
      <div class="filter-row">
        <input v-model.number="teamId" type="number" placeholder="ID équipe" class="wh-input" style="width:130px" />
        <button class="btn btn-primary" :disabled="!teamId || loadingBenchmark" @click="loadBenchmark">
          <i v-if="loadingBenchmark" class="pi pi-spin pi-spinner" style="font-size:12px" /> Charger
        </button>
      </div>
      <div v-if="benchmark" class="benchmark-grid">
        <div class="benchmark-stat">
          <span class="benchmark-label">Taille équipe</span>
          <span class="benchmark-value">{{ benchmark.team_size }}</span>
        </div>
        <div class="benchmark-stat">
          <span class="benchmark-label">Satisfaction moy.</span>
          <span class="benchmark-value">{{ benchmark.metrics?.avg_satisfaction ?? '—' }}</span>
        </div>
        <div class="benchmark-stat">
          <span class="benchmark-label">Taux FCR</span>
          <span class="benchmark-value">{{ benchmark.metrics?.first_contact_resolution_rate !== undefined ? pct(benchmark.metrics.first_contact_resolution_rate) : '—' }}</span>
        </div>
        <div class="benchmark-stat">
          <span class="benchmark-label">NPS</span>
          <span class="benchmark-value">{{ benchmark.metrics?.nps_score ?? '—' }}</span>
        </div>
        <div class="benchmark-stat" style="grid-column:1/-1">
          <span class="benchmark-label">Tendance</span>
          <span :class="['badge', trendBadge(benchmark.trend)]">{{ benchmark.trend ?? '—' }}</span>
        </div>
      </div>
      <p v-else-if="benchmarkLoaded" class="empty-msg">Aucune donnée de benchmarking pour cette équipe.</p>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

const pct = (v) => `${Math.round(v * 100)}%`
const trendBadge = (d) => ({ improving: 'badge-green', declining: 'badge-red', stable: 'badge-gray', up: 'badge-green', down: 'badge-red' }[d] ?? 'badge-gray')

// ── Agent metrics ────────────────────────────────────────────────────────
const metricsForm = reactive({ agent_id: null, start_date: '', end_date: '' })
const canLoadMetrics = computed(() => metricsForm.agent_id && metricsForm.start_date && metricsForm.end_date)
const metrics = ref([])
const metricsLoaded = ref(false)
const metricsError = ref('')
const loadingMetrics = ref(false)

async function loadMetrics() {
  loadingMetrics.value = true
  metricsError.value = ''
  metricsLoaded.value = false
  try {
    const { data } = await axios.get('/api/v1/helpdesk/cs-ai/agents/metrics', { params: metricsForm })
    metrics.value = data.metrics ?? []
  } catch (err) {
    metrics.value = []
    metricsError.value = err.response?.status === 404 ? 'Aucune métrique trouvée pour cette période.' : "Échec du chargement."
  } finally {
    loadingMetrics.value = false
    metricsLoaded.value = true
  }
}

// ── Agent trends ─────────────────────────────────────────────────────────
const trendsForm = reactive({ agent_id: null, period_type: 'monthly' })
const trends = ref([])
const trendsLoaded = ref(false)
const loadingTrends = ref(false)

async function loadTrends() {
  loadingTrends.value = true
  trendsLoaded.value = false
  try {
    const { data } = await axios.get('/api/v1/helpdesk/cs-ai/agents/trends', { params: trendsForm })
    trends.value = data.trends ?? []
  } finally {
    loadingTrends.value = false
    trendsLoaded.value = true
  }
}

// ── Team benchmarking ────────────────────────────────────────────────────
const teamId = ref(null)
const benchmark = ref(null)
const benchmarkLoaded = ref(false)
const loadingBenchmark = ref(false)

async function loadBenchmark() {
  loadingBenchmark.value = true
  benchmarkLoaded.value = false
  benchmark.value = null
  try {
    const { data } = await axios.get('/api/v1/helpdesk/cs-ai/team-benchmarking', { params: { team_id: teamId.value } })
    benchmark.value = data
  } catch {
    benchmark.value = null
  } finally {
    loadingBenchmark.value = false
    benchmarkLoaded.value = true
  }
}
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:20px; gap:16px; flex-wrap:wrap; }
.wh-page-title { margin:0; font-size:22px; font-weight:700; color:var(--fg-1); }
.wh-page-subtitle { margin:3px 0 0; font-size:13px; color:var(--fg-3); }
.wh-panel { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); }
.section-title { font-size:14px; font-weight:600; color:var(--fg-1); margin:0 0 12px; }
.filter-row { display:flex; gap:8px; align-items:center; margin-bottom:14px; flex-wrap:wrap; }
.wh-input { padding:7px 10px; border-radius:var(--r-md); border:1px solid var(--border-subtle); background:var(--bg-canvas); color:var(--fg-1); font-size:14px; outline:none; }
.wh-dt { width:100%; border-collapse:collapse; font-size:13px; }
.wh-dt thead th { text-align:left; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:var(--fg-3); font-weight:600; padding:8px 12px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sunken); }
.wh-dt-row td { padding:10px 12px; border-bottom:1px solid var(--border-subtle); }
.wh-dt-row:last-child td { border-bottom:0; }
.empty-msg { color:var(--fg-3); font-size:13px; margin:0; }
.btn { font-weight:500; font-size:13px; padding:7px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:disabled { opacity:.6; cursor:not-allowed; }
.badge { padding:2px 7px; border-radius:4px; font-size:11px; }
.badge-green { background:var(--green-50,#f0fdf4); color:var(--green-600,#16a34a); }
.badge-red { background:var(--danger-bg,#fef2f2); color:var(--danger-fg,#dc2626); }
.badge-gray { background:var(--slate-100,#f1f5f9); color:var(--slate-600,#475569); }
.trend-row { display:flex; align-items:center; justify-content:space-between; padding:8px 12px; background:var(--bg-sunken); border-radius:var(--r-md); }
.benchmark-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:12px; }
.benchmark-stat { background:var(--bg-sunken); border-radius:var(--r-md); padding:12px 14px; }
.benchmark-label { display:block; font-size:11px; color:var(--fg-3); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px; }
.benchmark-value { font-size:18px; font-weight:700; color:var(--fg-1); }
</style>
