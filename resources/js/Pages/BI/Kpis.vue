<template>
  <AppLayout>
    <Head title="BI · KPIs" />

    <AIAssistantPanel v-if="guidance" :guidance="guidance" />

    <!-- Header -->
    <div class="page-head">
      <div>
        <h1 class="wh-page-title">BI · Indicateurs clés</h1>
        <p class="wh-page-subtitle">
          {{ kpis.length }} KPI{{ kpis.length !== 1 ? 's' : '' }}
          <span v-if="alertCount" class="alert-badge">
            <i class="pi pi-exclamation-triangle" style="font-size:10px" />
            {{ alertCount }} en alerte
          </span>
        </p>
      </div>
      <div class="page-actions">
        <select v-model="activePeriod" class="wh-select" @change="reloadKpis">
          <option value="today">Aujourd'hui</option>
          <option value="week">Cette semaine</option>
          <option value="month">Ce mois</option>
          <option value="quarter">Ce trimestre</option>
          <option value="year">Cette année</option>
        </select>
        <button class="btn btn-secondary" @click="reloadKpis" :disabled="loading">
          <i :class="loading ? 'pi pi-spin pi-spinner' : 'pi pi-refresh'" style="font-size:13px" />
          Actualiser
        </button>
        <button class="btn btn-primary" @click="showCreateDialog = true">
          <i class="pi pi-plus" style="font-size:13px" />
          Nouveau KPI
        </button>
      </div>
    </div>

    <!-- Category filter tabs -->
    <div class="category-tabs">
      <button
        v-for="cat in categories"
        :key="cat.value"
        class="category-tab"
        :class="{ 'category-tab--active': activeCategory === cat.value }"
        @click="activeCategory = cat.value"
      >
        <i :class="cat.icon" style="font-size:13px" />
        {{ cat.label }}
        <span v-if="cat.count" class="tab-count">{{ cat.count }}</span>
      </button>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="kpi-grid">
      <div v-for="i in 8" :key="i" class="kpi-skeleton" />
    </div>

    <!-- KPI grid -->
    <div v-else class="kpi-grid">
      <div
        v-for="kpi in filteredKpis"
        :key="kpi.id"
        class="kpi-wrapper"
        :class="{ 'kpi-wrapper--alert': isInAlert(kpi) }"
        @click="selectKpi(kpi)"
      >
        <KpiCard
          :label="kpi.name"
          :value="kpi.value"
          :unit="kpi.unit"
          :trend="kpi.trend_percentage"
          :threshold="kpi.threshold"
          :sparkline="kpi.sparkline ?? []"
          :format="kpi.format ?? 'raw'"
        />
        <!-- Category badge -->
        <span class="kpi-category-badge">{{ kpi.category ?? 'Général' }}</span>
      </div>
      <div v-if="!filteredKpis.length" class="kpi-grid-empty">
        <i class="pi pi-gauge" style="font-size:32px;color:var(--fg-4,var(--fg-3))" />
        <p style="font-size:14px;font-weight:500;color:var(--fg-2);margin:0">Aucun KPI dans cette catégorie</p>
      </div>
    </div>

    <!-- Comparison chart -->
    <div v-if="!loading && filteredKpis.length" style="margin-top:28px">
      <div class="section-label" style="margin-bottom:12px">Évolution sur la période</div>
      <div class="wh-panel chart-panel">
        <ChartWidget
          type="bar"
          :series="trendSeries"
          :options="trendChartOptions"
          :height="260"
        />
      </div>
    </div>

    <!-- KPI detail dialog -->
    <Dialog v-model:visible="showDetailDialog" :header="selectedKpi?.name" modal style="width:600px">
      <div v-if="selectedKpi" class="kpi-detail">
        <div class="kpi-detail__stats">
          <div class="kpi-detail__stat">
            <span class="kpi-detail__stat-label">Valeur actuelle</span>
            <span class="kpi-detail__stat-val">
              {{ selectedKpi.value }}
              <span v-if="selectedKpi.unit" style="font-size:16px;font-weight:400;color:var(--fg-3)"> {{ selectedKpi.unit }}</span>
            </span>
          </div>
          <div v-if="selectedKpi.target" class="kpi-detail__stat">
            <span class="kpi-detail__stat-label">Objectif</span>
            <span class="kpi-detail__stat-val">{{ selectedKpi.target }}{{ selectedKpi.unit ? ' ' + selectedKpi.unit : '' }}</span>
          </div>
          <div v-if="selectedKpi.threshold" class="kpi-detail__stat">
            <span class="kpi-detail__stat-label">Seuil d'alerte</span>
            <span class="kpi-detail__stat-val" :style="isInAlert(selectedKpi) ? 'color:var(--danger-fg)' : ''">
              {{ selectedKpi.threshold }}{{ selectedKpi.unit ? ' ' + selectedKpi.unit : '' }}
            </span>
          </div>
          <div v-if="selectedKpi.trend_percentage !== undefined" class="kpi-detail__stat">
            <span class="kpi-detail__stat-label">Tendance</span>
            <span class="kpi-detail__stat-val" :class="selectedKpi.trend_percentage >= 0 ? 'trend-up' : 'trend-down'">
              <i :class="selectedKpi.trend_percentage >= 0 ? 'pi pi-arrow-up' : 'pi pi-arrow-down'" style="font-size:14px" />
              {{ Math.abs(selectedKpi.trend_percentage) }}%
            </span>
          </div>
        </div>

        <!-- Full sparkline chart -->
        <div v-if="selectedKpi.sparkline && selectedKpi.sparkline.length" style="margin-top:16px">
          <ChartWidget
            type="area"
            :series="[{ name: selectedKpi.name, data: selectedKpi.sparkline }]"
            :height="180"
            :options="{
              xaxis: { labels: { show: false } },
              colors: ['#2E5BE8'],
            }"
          />
        </div>

        <!-- Target progress bar -->
        <div v-if="selectedKpi.target && typeof selectedKpi.value === 'number'" class="progress-section">
          <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--fg-3);margin-bottom:6px">
            <span>Progression vers l'objectif</span>
            <span>{{ Math.min(Math.round((selectedKpi.value / selectedKpi.target) * 100), 100) }}%</span>
          </div>
          <div class="progress-bar">
            <div
              class="progress-fill"
              :style="{
                width: Math.min((selectedKpi.value / selectedKpi.target) * 100, 100) + '%',
                background: isInAlert(selectedKpi) ? 'var(--danger-fg)' : 'var(--halo-500)',
              }"
            />
          </div>
          <div style="font-size:11px;color:var(--fg-3);margin-top:3px">Objectif : {{ selectedKpi.target }} {{ selectedKpi.unit }}</div>
        </div>
      </div>
      <template #footer>
        <div style="display:flex;justify-content:flex-end;gap:8px">
          <button class="btn btn-secondary" @click="showDetailDialog = false">Fermer</button>
          <button class="btn btn-secondary">
            <i class="pi pi-pencil" style="font-size:13px" /> Modifier
          </button>
        </div>
      </template>
    </Dialog>

    <!-- Create KPI dialog -->
    <Dialog v-model:visible="showCreateDialog" header="Nouveau KPI" modal style="width:480px">
      <div style="display:flex;flex-direction:column;gap:14px;padding:8px 0">
        <div>
          <label class="wh-label">Nom du KPI</label>
          <InputText v-model="createForm.name" class="w-full" placeholder="ex. Chiffre d'affaires mensuel" />
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div>
            <label class="wh-label">Catégorie</label>
            <Select
              v-model="createForm.category"
              :options="categoryOptions"
              option-label="label"
              option-value="value"
              class="w-full"
            />
          </div>
          <div>
            <label class="wh-label">Format</label>
            <Select
              v-model="createForm.format"
              :options="formatOptions"
              option-label="label"
              option-value="value"
              class="w-full"
            />
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
          <div>
            <label class="wh-label">Unité</label>
            <InputText v-model="createForm.unit" class="w-full" placeholder="€, %, …" />
          </div>
          <div>
            <label class="wh-label">Objectif</label>
            <InputNumber v-model="createForm.target" class="w-full" />
          </div>
          <div>
            <label class="wh-label">Seuil alerte</label>
            <InputNumber v-model="createForm.threshold" class="w-full" />
          </div>
        </div>
      </div>
      <template #footer>
        <div style="display:flex;justify-content:flex-end;gap:8px">
          <button class="btn btn-secondary" @click="showCreateDialog = false">Annuler</button>
          <button class="btn btn-primary" @click="createKpi" :disabled="!createForm.name">
            <i class="pi pi-check" style="font-size:13px" /> Créer
          </button>
        </div>
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import { Dialog, InputText, InputNumber, Select } from 'primevue'
import AppLayout from '@/Layouts/AppLayout.vue'
import KpiCard from '@/Components/BI/KpiCard.vue'
import ChartWidget from '@/Components/BI/ChartWidget.vue'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import axios from 'axios'

const { guidance } = useAiAssistant('BI', 'view_kpis')

const props = defineProps({
  kpis:   { type: Array, default: () => [] },
  period: { type: String, default: 'month' },
})

// ── State ──────────────────────────────────────────────────────────────────────
const loading          = ref(false)
const activePeriod     = ref(props.period)
const activeCategory   = ref('all')
const showDetailDialog = ref(false)
const showCreateDialog = ref(false)
const selectedKpi      = ref(null)
const localKpis        = ref([...props.kpis])

const createForm = reactive({
  name:      '',
  category:  'general',
  format:    'raw',
  unit:      '',
  target:    null,
  threshold: null,
})

const categoryOptions = [
  { label: 'Général',     value: 'general' },
  { label: 'Finance',     value: 'finance' },
  { label: 'Ventes',      value: 'sales' },
  { label: 'RH',          value: 'hr' },
  { label: 'Support',     value: 'support' },
  { label: 'Inventaire',  value: 'inventory' },
]

const formatOptions = [
  { label: 'Brut',      value: 'raw' },
  { label: 'Nombre',    value: 'number' },
  { label: 'Monnaie',   value: 'currency' },
  { label: 'Pourcentage', value: 'percent' },
]

// ── Computed ──────────────────────────────────────────────────────────────────
const alertCount = computed(() =>
  localKpis.value.filter(k => isInAlert(k)).length
)

const categories = computed(() => {
  const counts = {}
  localKpis.value.forEach(k => {
    const c = k.category ?? 'general'
    counts[c] = (counts[c] ?? 0) + 1
  })
  const base = [{ value: 'all', label: 'Tous', icon: 'pi pi-th-large', count: localKpis.value.length }]
  const catMap = {
    general:   { label: 'Général',    icon: 'pi pi-star' },
    finance:   { label: 'Finance',    icon: 'pi pi-euro' },
    sales:     { label: 'Ventes',     icon: 'pi pi-shopping-cart' },
    hr:        { label: 'RH',         icon: 'pi pi-users' },
    support:   { label: 'Support',    icon: 'pi pi-ticket' },
    inventory: { label: 'Inventaire', icon: 'pi pi-box' },
  }
  return [
    ...base,
    ...Object.entries(counts).map(([val, count]) => ({
      value: val,
      label: catMap[val]?.label ?? val,
      icon:  catMap[val]?.icon  ?? 'pi pi-tag',
      count,
    })),
  ]
})

const filteredKpis = computed(() => {
  if (activeCategory.value === 'all') return localKpis.value
  return localKpis.value.filter(k => (k.category ?? 'general') === activeCategory.value)
})

const trendSeries = computed(() => [{
  name: 'Valeur',
  data: filteredKpis.value.map(k => typeof k.value === 'number' ? k.value : 0),
}])

const trendChartOptions = computed(() => ({
  xaxis: {
    categories: filteredKpis.value.map(k => k.name),
    labels: { rotate: -35, style: { fontSize: '11px' } },
  },
  plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
  colors: filteredKpis.value.map(k => isInAlert(k) ? '#dc2626' : '#2E5BE8'),
}))

// ── Methods ───────────────────────────────────────────────────────────────────
function isInAlert(kpi) {
  return kpi.threshold !== undefined && kpi.threshold !== null
    && typeof kpi.value === 'number'
    && kpi.value < kpi.threshold
}

function selectKpi(kpi) {
  selectedKpi.value      = kpi
  showDetailDialog.value = true
}

async function reloadKpis() {
  loading.value = true
  try {
    const res = await axios.get('/api/v1/bi/kpis', { params: { period: activePeriod.value } })
    localKpis.value = res.data?.data ?? []
  } catch (e) {
    console.error('Failed to reload KPIs', e)
  } finally {
    loading.value = false
  }
}

async function createKpi() {
  try {
    const res = await axios.post('/api/v1/bi/kpis', {
      name: createForm.name,
      category: createForm.category,
      unit: createForm.unit,
      target: createForm.target,
      threshold_warning: createForm.threshold,
    })
    if (res.data?.data) localKpis.value.push(res.data.data)
    showCreateDialog.value = false
    Object.assign(createForm, { name: '', category: 'general', format: 'raw', unit: '', target: null, threshold: null })
  } catch (e) {
    console.error('Failed to create KPI', e)
  }
}
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:16px; gap:16px; flex-wrap:wrap; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); display:flex; align-items:center; gap:8px; }
.page-actions { display:flex; gap:8px; align-items:center; }
.section-label { font-size:12px; font-weight:600; color:var(--fg-2); letter-spacing:0.05em; text-transform:uppercase; }

.alert-badge { display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:var(--r-pill); background:var(--danger-bg); color:var(--danger-fg); font-size:11px; font-weight:600; }

/* Category tabs */
.category-tabs { display:flex; gap:4px; margin-bottom:20px; flex-wrap:wrap; }
.category-tab { display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:var(--r-md); border:1px solid var(--border-subtle); background:var(--bg-canvas); color:var(--fg-2); font-size:12px; font-weight:500; cursor:pointer; transition:all var(--dur-fast); }
.category-tab:hover { background:var(--bg-sunken); color:var(--fg-1); }
.category-tab--active { background:var(--halo-500); color:#fff; border-color:var(--halo-500); }
.tab-count { display:inline-flex; align-items:center; justify-content:center; min-width:18px; height:18px; border-radius:99px; background:rgba(255,255,255,0.25); font-size:10px; font-weight:700; padding:0 4px; }
.category-tab:not(.category-tab--active) .tab-count { background:var(--bg-sunken); color:var(--fg-3); }

/* KPI grid */
.kpi-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(220px,1fr)); gap:12px; }
.kpi-skeleton { height:140px; background:var(--bg-sunken); border-radius:var(--r-lg); animation:pulse 1.4s ease-in-out infinite; }
.kpi-wrapper { position:relative; cursor:pointer; border-radius:var(--r-lg); transition:transform var(--dur-fast), box-shadow var(--dur-fast); }
.kpi-wrapper:hover { transform:translateY(-2px); box-shadow:0 4px 16px rgba(0,0,0,0.08); }
.kpi-wrapper--alert { outline:2px solid var(--danger-fg,#dc2626); border-radius:var(--r-lg); }
.kpi-category-badge { position:absolute; top:10px; right:10px; font-size:9px; font-weight:700; color:var(--fg-3); text-transform:uppercase; letter-spacing:0.06em; background:var(--bg-sunken); padding:2px 6px; border-radius:var(--r-pill); }
.kpi-grid-empty { grid-column:1/-1; display:flex; flex-direction:column; align-items:center; gap:10px; padding:48px 20px; color:var(--fg-3); }

/* Chart */
.wh-panel { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); }
.chart-panel { padding:16px 20px; }

/* KPI detail dialog */
.kpi-detail { padding:4px 0; }
.kpi-detail__stats { display:grid; grid-template-columns:repeat(auto-fill,minmax(120px,1fr)); gap:12px; margin-bottom:8px; }
.kpi-detail__stat { background:var(--bg-sunken); border-radius:var(--r-md); padding:12px 14px; }
.kpi-detail__stat-label { display:block; font-size:10px; font-weight:600; color:var(--fg-3); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px; }
.kpi-detail__stat-val { display:block; font-size:22px; font-weight:700; color:var(--fg-1); font-family:var(--font-display); }
.trend-up   { color:var(--success-fg); display:flex; align-items:center; gap:4px; }
.trend-down { color:var(--danger-fg);  display:flex; align-items:center; gap:4px; }

/* Progress */
.progress-section { margin-top:12px; }
.progress-bar { height:6px; background:var(--border-subtle); border-radius:99px; overflow:hidden; }
.progress-fill { height:100%; border-radius:99px; transition:width 0.4s ease; }

/* Form */
.wh-label { display:block; font-size:12px; font-weight:600; color:var(--fg-2); margin-bottom:5px; }
.w-full { width:100%; }
.wh-select { font-family:var(--font-sans); font-size:13px; padding:7px 10px; border:1px solid var(--border-subtle); border-radius:var(--r-md); background:var(--bg-canvas); color:var(--fg-1); cursor:pointer; }

/* Buttons */
.btn { font-family:var(--font-sans); font-weight:500; font-size:13px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:background var(--dur-base); line-height:1.2; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover { background:var(--halo-700); }
.btn-primary:disabled { opacity:0.5; cursor:not-allowed; }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover { background:var(--bg-sunken); }
.btn-secondary:disabled { opacity:0.4; cursor:not-allowed; }

@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.5} }
</style>
