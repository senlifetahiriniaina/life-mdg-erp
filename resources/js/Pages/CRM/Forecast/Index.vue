<template>
  <AppLayout>
    <Head title="AI Sales Forecasting" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">AI Sales Forecasting</h1>
        <p class="wh-page-subtitle">Prévisions de ventes alimentées par l'IA</p>
      </div>
      <div class="page-actions">
        <select v-model="selectedPeriod" class="wh-filter-input" style="width:140px">
          <option v-for="p in periodOptions" :key="p" :value="p">{{ p }}</option>
        </select>
        <button class="btn btn-secondary" :disabled="generating" @click="generate">
          <i class="pi pi-refresh" :class="{ 'pi-spin': generating }" style="font-size:13px" />
          {{ generating ? 'Génération…' : 'Générer' }}
        </button>
        <button class="btn btn-secondary" @click="aiAnalyse">
          ✨ Analyse IA
        </button>
      </div>
    </div>

    <!-- KPI cards -->
    <div class="wh-kpi-row" style="margin-bottom:20px">
      <div class="wh-kpi-card">
        <div class="wh-kpi-label">Pipeline Total</div>
        <div class="wh-kpi-value">{{ fmtCurrency(currentForecast?.pipeline_total) }}</div>
      </div>
      <div class="wh-kpi-card">
        <div class="wh-kpi-label">Commit</div>
        <div class="wh-kpi-value" style="color:#3b82f6">{{ fmtCurrency(currentForecast?.commit_amount) }}</div>
      </div>
      <div class="wh-kpi-card">
        <div class="wh-kpi-label">Best Case</div>
        <div class="wh-kpi-value" style="color:#22c55e">{{ fmtCurrency(currentForecast?.best_case) }}</div>
      </div>
      <div class="wh-kpi-card" style="border:2px solid #7c3aed20">
        <div class="wh-kpi-label" style="display:flex;align-items:center;gap:6px">
          Prédiction IA
          <span style="background:#7c3aed;color:white;font-size:10px;padding:1px 6px;border-radius:10px;font-weight:600">IA</span>
        </div>
        <div class="wh-kpi-value" style="color:#7c3aed">{{ fmtCurrency(currentForecast?.ai_prediction) }}</div>
        <div v-if="currentForecast?.confidence_pct" style="font-size:12px;color:var(--fg-4);margin-top:2px">
          Confiance : {{ currentForecast.confidence_pct }}%
        </div>
      </div>
    </div>

    <!-- ApexCharts bar chart -->
    <div class="wh-panel" style="margin-bottom:20px">
      <div style="padding:12px 16px;border-bottom:1px solid var(--border-1)">
        <span style="font-weight:600;font-size:15px">Prévisions par trimestre</span>
      </div>
      <div style="padding:16px">
        <apexchart
          v-if="chartSeries.length"
          type="bar"
          height="280"
          :options="chartOptions"
          :series="chartSeries"
        />
        <div v-else style="text-align:center;padding:40px;color:var(--fg-4)">
          Aucune donnée — cliquez sur Générer pour créer une prévision.
        </div>
      </div>
    </div>

    <!-- Table par commercial -->
    <div class="wh-panel">
      <div style="padding:12px 16px;border-bottom:1px solid var(--border-1)">
        <span style="font-weight:600;font-size:15px">Prévisions par commercial</span>
      </div>
      <DataTable :value="forecasts" :loading="loading" striped-rows style="font-size:13px">
        <Column field="period" header="Période" style="width:100px" />
        <Column header="Pipeline" style="width:140px;text-align:right">
          <template #body="{ data }">{{ fmtCurrency(data.pipeline_total) }}</template>
        </Column>
        <Column header="Forecast" style="width:140px;text-align:right">
          <template #body="{ data }">{{ fmtCurrency(data.forecast_amount) }}</template>
        </Column>
        <Column header="Commit" style="width:140px;text-align:right">
          <template #body="{ data }">{{ fmtCurrency(data.commit_amount) }}</template>
        </Column>
        <Column header="Best Case" style="width:140px;text-align:right">
          <template #body="{ data }">{{ fmtCurrency(data.best_case) }}</template>
        </Column>
        <Column header="Prédiction IA" style="width:150px;text-align:right">
          <template #body="{ data }">
            <span style="color:#7c3aed;font-weight:600">{{ fmtCurrency(data.ai_prediction) }}</span>
          </template>
        </Column>
        <Column header="Confiance" style="width:100px">
          <template #body="{ data }">
            <div style="display:flex;align-items:center;gap:4px">
              <div style="flex:1;background:var(--surface-2);border-radius:4px;height:6px;overflow:hidden">
                <div :style="{ width: (data.confidence_pct || 0) + '%', height: '100%', background: '#7c3aed', borderRadius: '4px' }" />
              </div>
              <span style="font-size:11px">{{ data.confidence_pct }}%</span>
            </div>
          </template>
        </Column>
        <Column header="Généré le" style="width:130px">
          <template #body="{ data }">{{ fmtDate(data.generated_at) }}</template>
        </Column>
      </DataTable>
    </div>

    <!-- AI Analysis dialog -->
    <Dialog v-model:visible="showAiDialog" header="✨ Analyse IA du forecast" :style="{ width: '520px' }" modal>
      <div v-if="aiAnswer" style="white-space:pre-wrap;font-size:14px;line-height:1.6">{{ aiAnswer }}</div>
      <div v-else style="color:var(--fg-4)">Analyse en cours…</div>
      <template #footer>
        <Button label="Fermer" @click="showAiDialog = false" />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, DataTable, Column, Dialog } from 'primevue'
import axios from 'axios'

const forecasts = ref([])
const loading = ref(false)
const generating = ref(false)
const showAiDialog = ref(false)
const aiAnswer = ref(null)

// Default to current quarter
const now = new Date()
const currentQuarter = `Q${Math.ceil((now.getMonth() + 1) / 3)}`
const currentYear = now.getFullYear()
const selectedPeriod = ref(`${currentYear}-${currentQuarter}`)

const periodOptions = computed(() => {
  const opts = []
  for (let y = currentYear - 1; y <= currentYear + 1; y++) {
    for (let q = 1; q <= 4; q++) opts.push(`${y}-Q${q}`)
  }
  return opts
})

const currentForecast = computed(() =>
  forecasts.value.find(f => f.period === selectedPeriod.value) ?? forecasts.value[0] ?? null
)

const chartCategories = computed(() => forecasts.value.map(f => f.period).reverse())

const chartSeries = computed(() => {
  if (!forecasts.value.length) return []
  const rev = [...forecasts.value].reverse()
  return [
    { name: 'Pipeline', data: rev.map(f => parseFloat(f.pipeline_total) || 0) },
    { name: 'Commit', data: rev.map(f => parseFloat(f.commit_amount) || 0) },
    { name: 'Best Case', data: rev.map(f => parseFloat(f.best_case) || 0) },
    { name: 'Prédiction IA', data: rev.map(f => parseFloat(f.ai_prediction) || 0) },
  ]
})

const chartOptions = computed(() => ({
  chart: { toolbar: { show: false }, background: 'transparent' },
  colors: ['#6366f1', '#3b82f6', '#22c55e', '#7c3aed'],
  plotOptions: { bar: { columnWidth: '60%', borderRadius: 4 } },
  xaxis: { categories: chartCategories.value },
  yaxis: { labels: { formatter: v => fmtCurrencyShort(v) } },
  legend: { position: 'top' },
  dataLabels: { enabled: false },
  theme: { mode: document.documentElement.classList.contains('dark') ? 'dark' : 'light' },
}))

function fmtCurrency(val) {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(val || 0)
}

function fmtCurrencyShort(val) {
  if (val >= 1000000) return `${(val / 1000000).toFixed(1)}M€`
  if (val >= 1000) return `${(val / 1000).toFixed(0)}k€`
  return `${val}€`
}

function fmtDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/crm/forecasts')
    forecasts.value = data.data || []
  } finally {
    loading.value = false
  }
}

async function generate() {
  generating.value = true
  try {
    await axios.post('/api/v1/crm/forecasts/generate', { period: selectedPeriod.value })
    await load()
  } finally {
    generating.value = false
  }
}

async function aiAnalyse() {
  showAiDialog.value = true
  aiAnswer.value = null
  try {
    const context = currentForecast.value
      ? `Période: ${currentForecast.value.period}, Pipeline: ${currentForecast.value.pipeline_total}€, Commit: ${currentForecast.value.commit_amount}€, Prédiction IA: ${currentForecast.value.ai_prediction}€, Confiance: ${currentForecast.value.confidence_pct}%.`
      : 'Aucune donnée de forecast disponible.'
    const { data } = await axios.post('/api/v1/ai/ask', {
      question: `Analyse ces prévisions de ventes et donne des recommandations pour améliorer la performance commerciale. ${context}`,
      module: 'CRM',
    })
    aiAnswer.value = data.answer ?? data.reply ?? JSON.stringify(data)
  } catch {
    aiAnswer.value = 'Erreur lors de la consultation IA. Veuillez réessayer.'
  }
}

onMounted(load)
</script>
