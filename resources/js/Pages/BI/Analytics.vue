<template>
  <AppLayout>
    <Head title="BI · Analytics" />

    <GuidedTour tour-id="bi-analytics" :steps="biTourSteps" />

    <AIAssistantPanel v-if="guidance" :guidance="guidance" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Analytics</h1>
        <p class="wh-page-subtitle">Business intelligence overview · last {{ months }} months</p>
      </div>
      <div style="display:flex;gap:8px;align-items:center">
        <select class="wh-select" v-model="months" @change="load">
          <option :value="3">3 months</option>
          <option :value="6">6 months</option>
          <option :value="12">12 months</option>
        </select>
        <button class="btn btn-secondary" @click="load"><i class="pi pi-refresh" style="font-size:13px" /> Refresh</button>
      </div>
    </div>

    <!-- KPI snapshot -->
    <div class="kpi-grid" style="margin-bottom:16px" v-if="data">
      <div class="wh-kpi" v-for="kpi in kpis" :key="kpi.label">
        <div class="wh-kpi-label">{{ kpi.label }}</div>
        <div class="wh-kpi-num font-display" :style="kpi.danger ? 'color:var(--danger-fg)' : ''">{{ kpi.value }}</div>
      </div>
    </div>

    <div class="chart-grid" v-if="data">
      <!-- Revenue chart -->
      <div class="wh-panel chart-panel">
        <div class="chart-head">
          <span class="chart-title">Revenue (paid invoices)</span>
        </div>
        <VueApexCharts
          type="area"
          height="220"
          :options="revenueOptions"
          :series="revenueSeries"
        />
      </div>

      <!-- Tickets by status -->
      <div class="wh-panel chart-panel">
        <div class="chart-head">
          <span class="chart-title">Tickets by status</span>
        </div>
        <VueApexCharts
          type="donut"
          height="220"
          :options="ticketOptions"
          :series="ticketSeries"
        />
      </div>

      <!-- Leads funnel -->
      <div class="wh-panel chart-panel">
        <div class="chart-head">
          <span class="chart-title">Lead pipeline</span>
        </div>
        <VueApexCharts
          type="bar"
          height="220"
          :options="leadOptions"
          :series="leadSeries"
        />
      </div>

      <!-- Top products -->
      <div class="wh-panel chart-panel">
        <div class="chart-head">
          <span class="chart-title">Top products by revenue</span>
        </div>
        <VueApexCharts
          type="bar"
          height="220"
          :options="productOptions"
          :series="productSeries"
        />
      </div>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="chart-grid">
      <div v-for="i in 4" :key="i" class="wh-panel chart-panel skeleton" />
    </div>

    <div v-if="error" style="color:var(--danger-fg);padding:24px">Failed to load analytics: {{ error }}</div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, defineAsyncComponent } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import GuidedTour from '@/Components/UI/GuidedTour.vue'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import { useHelpStore } from '@/stores/help'

const { guidance } = useAiAssistant('BI', 'view_analytics')

// Lazy load ApexCharts - splits 556KB into separate chunk
const VueApexCharts = defineAsyncComponent(() => import('vue-apexcharts'))
import axios from 'axios'

const help = useHelpStore()

const biTourSteps = [
  { tag: 'BI', icon: 'pi pi-chart-line', title: 'KPI Snapshot',       description: 'The top row shows five live KPIs pulled in real time: open tickets, overdue invoices, headcount, new leads, and low-stock items. Red values require attention.' },
  { tag: 'BI', icon: 'pi pi-chart-bar',  title: 'Revenue Chart',       description: 'The area chart tracks monthly revenue from paid invoices. Use the month selector (3/6/12 M) to zoom in or out on the trend.' },
  { tag: 'BI', icon: 'pi pi-chart-pie',  title: 'Ticket Donut',        description: 'The donut shows the distribution of helpdesk tickets by status. A large "open" slice signals a support backlog.' },
  { tag: 'BI', icon: 'pi pi-filter',     title: 'Lead Pipeline',       description: 'The bar chart maps leads across pipeline stages (new → converted). Compare stage heights to spot where deals are stalling.' },
  { tag: 'BI', icon: 'pi pi-star',       title: 'Top Products',        description: 'The horizontal bar shows your top 5 products by revenue from POS sales. Use this to inform purchasing and promotions.' },
]

interface AnalyticsData {
  kpi_snapshot: Array<{ label: string; value: string; trend: string; change: string }>
  revenue: { data: number[]; labels: string[] }
  tickets: Record<string, number>
  leads: { data: number[]; labels: string[] }
  top_products: Array<{ name: string; revenue: number }>
}

const months  = ref(6)
const data    = ref<AnalyticsData | null>(null)
const loading = ref(false)
const error   = ref<string | null>(null)

async function load() {
  loading.value = true
  error.value   = null
  try {
    const res = await axios.get('/api/v1/bi/analytics/summary', { params: { months: months.value } })
    data.value = res.data
  } catch (e: any) {
    error.value = e.response?.data?.message ?? e.message
  } finally {
    loading.value = false
  }
}

onMounted(load)

// ── KPI snapshot ──────────────────────────────────────────────────────
const kpis = computed(() => {
  const snapshot = data.value?.kpi_snapshot ?? []
  return snapshot.map((kpi: { label: string; value: string; trend: string; change: string }) => ({
    label:  kpi.label,
    value:  kpi.value,
    danger: kpi.trend === 'up' && ['Open Tickets', 'Overdue Invoices', 'Low Stock Items'].includes(kpi.label),
    change: kpi.change,
    trend:  kpi.trend,
  }))
})

// ── Chart commons ─────────────────────────────────────────────────────
const baseChart = {
  chart:  { toolbar: { show: false }, fontFamily: 'var(--font-sans)', background: 'transparent' },
  theme:  { mode: document.documentElement.classList.contains('dark') ? 'dark' : 'light' },
  colors: ['#2E5BE8', '#1F9D55', '#C99403', '#D6442C', '#6B7488'],
  grid:   { borderColor: 'var(--border-subtle)', strokeDashArray: 3 },
}

// Revenue
const revenueSeries = computed(() => [{
  name: 'Revenue',
  data: data.value?.revenue?.data ?? [],
}])
const revenueOptions = computed(() => ({
  ...baseChart,
  xaxis: { categories: data.value?.revenue?.labels ?? [], labels: { style: { colors: 'var(--fg-3)', fontSize: '11px' } } },
  yaxis: { labels: { formatter: (v: number) => `$${(v / 1000).toFixed(0)}k`, style: { colors: 'var(--fg-3)' } } },
  fill:  { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
  stroke:{ curve: 'smooth', width: 2 },
  dataLabels: { enabled: false },
  tooltip: { y: { formatter: (v: number) => `$${v.toLocaleString()}` } },
}))

// Tickets
const ticketSeries = computed(() => Object.values(data.value?.tickets ?? {}))
const ticketOptions = computed(() => ({
  ...baseChart,
  labels: Object.keys(data.value?.tickets ?? {}).map(s => s.replace('_', ' ')),
  legend: { position: 'bottom', fontSize: '12px' },
  dataLabels: { enabled: true, formatter: (v: number) => `${v.toFixed(0)}%` },
}))

// Leads
const leadSeries = computed(() => [{ name: 'Leads', data: data.value?.leads?.data ?? [] }])
const leadOptions = computed(() => ({
  ...baseChart,
  xaxis: { categories: data.value?.leads?.labels ?? [], labels: { style: { colors: 'var(--fg-3)', fontSize: '11px' } } },
  yaxis: { labels: { style: { colors: 'var(--fg-3)' } } },
  plotOptions: { bar: { borderRadius: 4, horizontal: false } },
  dataLabels: { enabled: false },
}))

// Top products
const productSeries = computed(() => [{
  name: 'Revenue',
  data: (data.value?.top_products ?? []).map((p) => p.revenue),
}])
const productOptions = computed(() => ({
  ...baseChart,
  xaxis: {
    categories: (data.value?.top_products ?? []).map((p) => p.name),
    labels: { style: { colors: 'var(--fg-3)', fontSize: '11px' } },
  },
  yaxis: { labels: { formatter: (v: number) => `$${(v / 1000).toFixed(1)}k`, style: { colors: 'var(--fg-3)' } } },
  plotOptions: { bar: { borderRadius: 4, horizontal: true } },
  dataLabels: { enabled: false },
}))
</script>

<style scoped>
.page-head   { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:20px; gap:16px; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.btn { font-family:var(--font-sans); font-weight:500; font-size:13px; padding:7px 12px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:background var(--dur-base); line-height:1.2; }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover { background:var(--bg-sunken); }
.wh-select { font-family:var(--font-sans); font-size:13px; padding:7px 10px; border:1px solid var(--border-subtle); border-radius:var(--r-md); background:var(--bg-canvas); color:var(--fg-1); cursor:pointer; }
.kpi-grid   { display:grid; grid-template-columns:repeat(5,1fr); gap:12px; }
@media (max-width:900px) { .kpi-grid { grid-template-columns:repeat(3,1fr); } }
.wh-kpi     { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); padding:16px 20px; }
.wh-kpi-label { font-size:11px; font-weight:600; color:var(--fg-3); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px; }
.wh-kpi-num   { font-size:28px; font-weight:700; color:var(--fg-1); line-height:1; }
.chart-grid  { display:grid; grid-template-columns:repeat(2,1fr); gap:16px; }
@media (max-width:900px) { .chart-grid { grid-template-columns:1fr; } }
.chart-panel { padding:16px 20px; }
.chart-head  { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }
.chart-title { font-size:13px; font-weight:600; color:var(--fg-1); }
.skeleton    { min-height:280px; background:var(--bg-sunken); border-radius:var(--r-lg); animation:pulse 1.5s infinite; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.5} }
</style>
