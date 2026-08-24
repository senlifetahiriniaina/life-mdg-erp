<template>
  <AppLayout>
    <Head :title="`BI · ${dashboard.name}`" />

    <AIAssistantPanel v-if="guidance" :guidance="guidance" />

    <!-- Header -->
    <div class="page-head">
      <div style="display:flex;align-items:center;gap:10px">
        <a href="/bi" class="back-link"><i class="pi pi-arrow-left" style="font-size:13px" /></a>
        <div>
          <h1 class="wh-page-title">{{ dashboard.name }}</h1>
          <p class="wh-page-subtitle">{{ dashboard.description ?? 'Tableau de bord personnalisé' }}</p>
        </div>
      </div>
      <div class="page-actions">
        <button class="btn btn-secondary" @click="refreshWidgets">
          <i class="pi pi-refresh" :class="{ 'pi-spin': refreshing }" style="font-size:13px" />
          Actualiser
        </button>
        <a :href="`/bi/dashboards/${dashboard.id}/builder`" class="btn btn-primary">
          <i class="pi pi-pencil" style="font-size:13px" />
          Modifier
        </a>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="widget-grid">
      <div v-for="i in 4" :key="i" class="widget-skeleton" />
    </div>

    <!-- Empty state -->
    <div v-else-if="!activeWidgets.length" class="wh-empty-state">
      <i class="pi pi-th-large" style="font-size:40px;color:var(--fg-4,var(--fg-3));margin-bottom:14px" />
      <p style="font-size:16px;font-weight:500;color:var(--fg-2);margin:0 0 4px">Dashboard vide</p>
      <p style="font-size:13px;color:var(--fg-3);margin:0 0 20px">
        Ajoutez des widgets via l'éditeur pour visualiser vos données.
      </p>
      <a :href="`/bi/dashboards/${dashboard.id}/builder`" class="btn btn-primary">
        <i class="pi pi-plus" style="font-size:13px" /> Ajouter des widgets
      </a>
    </div>

    <!-- AI Insights section -->
    <div class="insights-section">
      <div class="insights-header">
        <span class="insights-title">✨ Insights IA</span>
        <button class="btn btn-secondary" @click="loadInsights" :disabled="insightsLoading">
          <i class="pi pi-refresh" :class="{ 'pi-spin': insightsLoading }" style="font-size:12px" />
          Actualiser
        </button>
      </div>
      <div class="insights-grid">
        <div v-for="insight in insights" :key="insight.id" class="insight-card" :class="`insight-card--${insight.severity}`">
          <div class="insight-trend">
            <span v-if="insight.trend === 'up'">↑</span>
            <span v-else-if="insight.trend === 'down'">↓</span>
            <span v-else>→</span>
          </div>
          <div class="insight-body">
            <p class="insight-title">{{ insight.title }}</p>
            <p class="insight-text">{{ insight.text }}</p>
          </div>
          <div class="insight-value">{{ insight.value }}</div>
        </div>
      </div>
    </div>

    <!-- Widget grid -->
    <div v-if="!loading && activeWidgets.length > 0" class="widget-grid">
      <div
        v-for="widget in activeWidgets"
        :key="widget.id"
        class="widget-cell"
        :style="widgetStyle(widget)"
      >
        <div class="widget-card">
          <div class="widget-card__head">
            <div style="display:flex;align-items:center;gap:8px">
              <i :class="widgetIcon(widget.type)" style="font-size:14px;color:var(--halo-500)" />
              <span class="widget-card__title">{{ widget.title }}</span>
            </div>
            <div style="display:flex;gap:4px">
              <button
                v-if="widget.description"
                class="wh-icon-btn"
                :title="widget.description"
              >
                <i class="pi pi-info-circle" style="font-size:13px" />
              </button>
            </div>
          </div>

          <div class="widget-card__body">
            <!-- KPI Card widget -->
            <template v-if="widget.type === 'kpi_card'">
              <KpiCard
                :label="widget.config?.label ?? widget.title"
                :value="widget.data?.value ?? 0"
                :unit="widget.config?.unit"
                :trend="widget.data?.trend_percentage"
                :threshold="widget.config?.threshold"
                :sparkline="widget.data?.sparkline ?? []"
                :format="widget.config?.format ?? 'raw'"
              />
            </template>

            <!-- Chart widgets -->
            <template v-else-if="['bar_chart', 'line_chart', 'area_chart', 'pie_chart'].includes(widget.type)">
              <ChartWidget
                :type="chartType(widget.type)"
                :series="widget.data?.series ?? []"
                :options="buildChartOptions(widget)"
                :height="widget.h > 1 ? 280 : 200"
              />
            </template>

            <!-- Gauge / Metric -->
            <template v-else-if="widget.type === 'metric_gauge'">
              <div class="gauge-wrap">
                <ChartWidget
                  type="radialBar"
                  :series="[widget.data?.percentage ?? 0]"
                  :options="gaugeOptions(widget)"
                  :height="200"
                />
                <div class="gauge-label">
                  <span class="gauge-value">{{ widget.data?.value ?? 0 }}</span>
                  <span v-if="widget.config?.unit" class="gauge-unit">{{ widget.config.unit }}</span>
                </div>
              </div>
            </template>

            <!-- Data table widget -->
            <template v-else-if="widget.type === 'data_table'">
              <DataTableWidget
                :columns="widget.config?.columns ?? []"
                :rows="widget.data?.rows ?? []"
                :page-size="widget.config?.pageSize ?? 5"
                :searchable="false"
              />
            </template>

            <!-- Unknown -->
            <template v-else>
              <div style="display:flex;align-items:center;justify-content:center;height:100px;color:var(--fg-3);font-size:13px">
                Widget type inconnu : {{ widget.type }}
              </div>
            </template>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import KpiCard from '@/Components/BI/KpiCard.vue'
import ChartWidget from '@/Components/BI/ChartWidget.vue'
import DataTableWidget from '@/Components/BI/DataTableWidget.vue'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import axios from 'axios'

const { guidance } = useAiAssistant('BI', 'view_dashboard')

const props = defineProps({
  dashboard: { type: Object, required: true },
  widgets:   { type: Array,  default: () => [] },
})

const loading   = ref(false)
const refreshing = ref(false)
const widgetData = ref({})
const insights = ref([])
const insightsLoading = ref(false)

async function loadInsights() {
  insightsLoading.value = true
  try {
    const res = await axios.get('/api/v1/bi/insights')
    insights.value = res.data?.data ?? []
  } catch (e) {
    console.error('Failed to load insights', e)
  } finally {
    insightsLoading.value = false
  }
}

// Load insights on mount
loadInsights()

const activeWidgets = computed(() => {
  return props.widgets.map(w => ({
    ...w,
    data: widgetData.value[w.id] ?? w.data ?? {},
  }))
})

async function refreshWidgets() {
  refreshing.value = true
  try {
    const res = await axios.get(`/api/v1/bi/dashboards/${props.dashboard.id}/widgets`)
    if (res.data?.data) {
      res.data.data.forEach(w => {
        widgetData.value[w.id] = w.data ?? {}
      })
    }
  } catch (e) {
    console.error('Failed to refresh widgets', e)
  } finally {
    refreshing.value = false
  }
}

function widgetStyle(widget) {
  const cols = widget.w ?? 6
  return {
    gridColumn: `span ${Math.min(cols, 12)}`,
  }
}

function widgetIcon(type) {
  const map = {
    kpi_card:     'pi pi-gauge',
    bar_chart:    'pi pi-chart-bar',
    line_chart:   'pi pi-chart-line',
    area_chart:   'pi pi-chart-line',
    pie_chart:    'pi pi-chart-pie',
    data_table:   'pi pi-table',
    metric_gauge: 'pi pi-circle',
  }
  return map[type] ?? 'pi pi-th-large'
}

function chartType(widgetType) {
  const map = {
    bar_chart:  'bar',
    line_chart: 'line',
    area_chart: 'area',
    pie_chart:  'pie',
  }
  return map[widgetType] ?? 'bar'
}

function buildChartOptions(widget) {
  const cfg = widget.config ?? {}
  return {
    xaxis:  cfg.xaxis  ? { categories: cfg.xaxis } : {},
    colors: cfg.colors ?? undefined,
    yaxis:  cfg.yAxisFormatter
      ? { labels: { formatter: v => `${v}${cfg.yAxisFormatter}` } }
      : {},
  }
}

function gaugeOptions(widget) {
  const cfg = widget.config ?? {}
  return {
    chart: { toolbar: { show: false }, fontFamily: 'var(--font-sans)', background: 'transparent' },
    plotOptions: {
      radialBar: {
        hollow:    { size: '60%' },
        dataLabels:{
          name:  { show: false },
          value: { show: true, fontSize: '22px', fontWeight: 700, color: 'var(--fg-1)', formatter: v => `${v}%` },
        },
      },
    },
    colors: cfg.colors ?? ['#2E5BE8'],
    labels: [widget.title],
  }
}
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; gap:16px; flex-wrap:wrap; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:26px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; gap:8px; }
.back-link { display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border:1px solid var(--border-subtle); border-radius:var(--r-md); background:var(--bg-canvas); color:var(--fg-2); text-decoration:none; transition:all var(--dur-fast); }
.back-link:hover { background:var(--bg-sunken); color:var(--fg-1); }
.btn { font-family:var(--font-sans); font-weight:500; font-size:13px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:background var(--dur-base); line-height:1.2; text-decoration:none; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover { background:var(--halo-700); }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover { background:var(--bg-sunken); }
.wh-icon-btn { width:26px; height:26px; border-radius:var(--r-sm); border:none; background:transparent; color:var(--fg-3); cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:all var(--dur-fast); }
.wh-icon-btn:hover { background:var(--bg-sunken); color:var(--fg-1); }

/* 12-column responsive grid */
.widget-grid {
  display: grid;
  grid-template-columns: repeat(12, 1fr);
  gap: 14px;
  align-items: start;
}
@media (max-width:900px) {
  .widget-grid { grid-template-columns: repeat(6, 1fr); }
}
@media (max-width:600px) {
  .widget-grid { grid-template-columns: 1fr; }
  .widget-cell { grid-column: span 1 !important; }
}

.widget-cell { min-width: 0; }

.widget-card {
  background: var(--bg-canvas);
  border: 1px solid var(--border-subtle);
  border-radius: var(--r-lg);
  overflow: hidden;
  height: 100%;
  display: flex;
  flex-direction: column;
}
.widget-card__head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 16px; border-bottom: 1px solid var(--border-subtle);
  background: var(--bg-sunken);
}
.widget-card__title { font-size: 13px; font-weight: 600; color: var(--fg-1); }
.widget-card__body { padding: 14px 16px; flex: 1; }

.widget-skeleton {
  grid-column: span 6;
  height: 220px;
  background: var(--bg-sunken);
  border-radius: var(--r-lg);
  animation: pulse 1.4s ease-in-out infinite;
}

.gauge-wrap { position: relative; display: flex; align-items: center; justify-content: center; }
.gauge-label { position: absolute; bottom: 20px; text-align: center; }
.gauge-value { display:block; font-size:20px; font-weight:700; color:var(--fg-1); font-family:var(--font-display); }
.gauge-unit  { display:block; font-size:11px; color:var(--fg-3); }

.wh-empty-state {
  display:flex; flex-direction:column; align-items:center; justify-content:center;
  padding:80px 20px; background:var(--bg-canvas);
  border:1px solid var(--border-subtle); border-radius:var(--r-lg); text-align:center;
}
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.5} }

/* Insights section */
.insights-section { margin-bottom: 24px; }
.insights-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }
.insights-title { font-size:15px; font-weight:600; color:var(--fg-1); }
.insights-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:12px; }
.insight-card { display:flex; align-items:flex-start; gap:12px; padding:14px 16px; border-radius:var(--r-md); border:1px solid var(--border-subtle); background:var(--bg-canvas); }
.insight-card--success { border-left:3px solid #22c55e; }
.insight-card--warning { border-left:3px solid #f59e0b; }
.insight-card--info    { border-left:3px solid #3b82f6; }
.insight-trend { font-size:20px; font-weight:700; min-width:24px; text-align:center; }
.insight-card--success .insight-trend { color:#22c55e; }
.insight-card--warning .insight-trend { color:#f59e0b; }
.insight-card--info    .insight-trend { color:#3b82f6; }
.insight-body { flex:1; }
.insight-title { font-size:13px; font-weight:600; color:var(--fg-1); margin:0 0 4px; }
.insight-text  { font-size:12px; color:var(--fg-3); margin:0; line-height:1.5; }
.insight-value { font-size:14px; font-weight:700; color:var(--fg-2); white-space:nowrap; }
</style>
