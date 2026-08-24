<template>
  <AppLayout>
    <Head title="BI · Analyses Prédictives" />

    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Analyses Prédictives — IA</h1>
          <p class="text-surface-500 text-sm mt-1">Prévisions de chiffre d'affaires, modèles de machine learning et anomalies détectées</p>
        </div>
        <div class="flex gap-2">
          <Select v-model="horizonMonths" :options="horizonOptions" option-label="label" option-value="value" size="small" class="w-36" @change="loadRevenueTrend" />
          <Button label="Rafraîchir" icon="pi pi-refresh" :loading="loading" @click="loadAll" />
        </div>
      </div>

      <Tabs value="0">
        <TabList>
          <Tab value="0">Prévision CA</Tab>
          <Tab value="1">Anomalies détectées</Tab>
          <Tab value="2">Modèles ML</Tab>
        </TabList>
        <TabPanels>
        <TabPanel value="0">
          <div v-if="revenueTrend" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
            <Card><template #content>
              <div class="text-xl font-bold text-blue-600">{{ nextForecastValue }} XOF</div>
              <div class="text-xs text-surface-500 mt-1">Prochain mois prévu</div>
            </template></Card>
            <Card><template #content>
              <div class="text-xl font-bold" :class="trendColor">{{ revenueTrend.trend ?? '—' }}</div>
              <div class="text-xs text-surface-500 mt-1">Tendance</div>
            </template></Card>
            <Card><template #content>
              <div class="text-xl font-bold text-surface-600">{{ revenueTrend.months?.length ?? 0 }}</div>
              <div class="text-xs text-surface-500 mt-1">Mois analysés</div>
            </template></Card>
            <Card><template #content>
              <div class="text-xl font-bold text-purple-600">{{ revenueTrend.forecast?.length ?? 0 }}</div>
              <div class="text-xs text-surface-500 mt-1">Mois projetés</div>
            </template></Card>
          </div>

          <Card v-if="revenueTrend">
            <template #content>
              <div class="space-y-3">
                <div v-for="month in revenueTrend.months" :key="month.month" class="flex items-center gap-3">
                  <span class="text-sm w-20">{{ month.month }}</span>
                  <div class="flex-1 relative">
                    <ProgressBar :value="progressPercent(month.revenue)" :style="{ height: '24px' }" />
                    <span class="absolute left-2 top-1/2 -translate-y-1/2 text-xs font-bold text-white">{{ formatXof(month.revenue) }}</span>
                  </div>
                  <span class="text-xs w-24 text-right" :class="month.growth_rate > 0 ? 'text-green-600' : month.growth_rate < 0 ? 'text-red-500' : 'text-surface-400'">
                    {{ month.growth_rate > 0 ? '+' : '' }}{{ (month.growth_rate * 100).toFixed(1) }}%
                  </span>
                </div>
                <div v-for="month in revenueTrend.forecast" :key="`f-${month.month}`" class="flex items-center gap-3 opacity-60">
                  <span class="text-sm w-20">{{ month.month }}</span>
                  <div class="flex-1 relative">
                    <ProgressBar :value="progressPercent(month.forecast_value)" :style="{ height: '24px' }" />
                    <span class="absolute left-2 top-1/2 -translate-y-1/2 text-xs font-bold text-white">{{ formatXof(month.forecast_value) }}</span>
                  </div>
                  <Tag value="Projeté" severity="secondary" size="small" />
                </div>
              </div>
            </template>
          </Card>
          <div v-else-if="!loading" class="text-center py-12 text-surface-400">Aucune donnée de revenu disponible.</div>
        </TabPanel>

        <TabPanel value="1">
          <Card>
            <template #header><div class="px-4 pt-4 font-semibold">Anomalies sur les métriques suivies</div></template>
            <template #content>
              <DataTable :value="anomalies" stripedRows :loading="loading">
                <Column field="entity_type" header="Entité" />
                <Column field="metric_name" header="Métrique" />
                <Column field="anomaly_date" header="Date" />
                <Column header="Écart">
                  <template #body="{ data }">
                    <span :class="Math.abs(data.deviation_percent) >= 30 ? 'text-red-600 font-bold' : 'text-orange-500'">
                      {{ data.deviation_percent > 0 ? '+' : '' }}{{ data.deviation_percent }}%
                    </span>
                  </template>
                </Column>
                <Column field="severity" header="Sévérité">
                  <template #body="{ data }">
                    <Tag :value="data.severity" :severity="severityTag(data.severity)" size="small" />
                  </template>
                </Column>
                <Column field="status" header="Statut" />
                <Column header="">
                  <template #body="{ data }">
                    <Button
                      v-if="data.status === 'new'"
                      label="Acquitter"
                      size="small"
                      text
                      :loading="acknowledgingId === data.id"
                      @click="acknowledge(data)"
                    />
                  </template>
                </Column>
              </DataTable>
              <p v-if="!loading && !anomalies.length" class="text-center text-surface-400 py-6">Aucune anomalie détectée.</p>
            </template>
          </Card>
        </TabPanel>

        <TabPanel value="2">
          <DataTable :value="models" stripedRows :loading="loading">
            <Column field="name" header="Modèle" />
            <Column field="model_type" header="Type" />
            <Column field="entity_type" header="Entité cible" />
            <Column header="Précision">
              <template #body="{ data }">
                <Tag
                  v-if="data.accuracy_score !== null"
                  :value="`${(data.accuracy_score * 100).toFixed(0)}%`"
                  :severity="data.accuracy_score >= 0.9 ? 'success' : data.accuracy_score >= 0.8 ? 'warn' : 'danger'"
                  size="small"
                />
                <span v-else class="text-surface-400">—</span>
              </template>
            </Column>
            <Column header="Dernier entraînement">
              <template #body="{ data }">{{ data.last_trained_at ? formatDate(data.last_trained_at) : 'Jamais' }}</template>
            </Column>
            <Column field="forecast_horizon_days" header="Horizon (j)" />
            <Column header="">
              <template #body="{ data }">
                <Button
                  label="Générer les prévisions"
                  size="small"
                  text
                  :loading="generatingId === data.id"
                  v-if="canManage"
                  @click="generateForecasts(data)"
                />
              </template>
            </Column>
          </DataTable>
          <p v-if="!loading && !models.length" class="text-center text-surface-400 py-6">Aucun modèle prédictif configuré.</p>
        </TabPanel>
        </TabPanels>
      </Tabs>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import Card from 'primevue/card'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import ProgressBar from 'primevue/progressbar'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import Select from 'primevue/select'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useRoleAccess } from '@/composables/useRoleAccess'

const { isElevated } = useRoleAccess()
const canManage = computed(() => isElevated.value)

const loading = ref(false)
const models = ref([])
const anomalies = ref([])
const revenueTrend = ref(null)
const acknowledgingId = ref(null)
const generatingId = ref(null)

const horizonOptions = [
  { label: '3 mois', value: 3 },
  { label: '6 mois', value: 6 },
  { label: '12 mois', value: 12 },
  { label: '24 mois', value: 24 },
]
const horizonMonths = ref(12)

async function loadModels() {
  const { data } = await axios.get('/api/v1/bi/predictive-models')
  models.value = data?.data ?? []
}

async function loadAnomalies() {
  const { data } = await axios.get('/api/v1/bi/anomalies')
  anomalies.value = data?.data ?? []
}

async function loadRevenueTrend() {
  const { data } = await axios.get('/api/v1/bi/analytics/revenue-trend', { params: { months: horizonMonths.value } })
  revenueTrend.value = data
}

async function loadAll() {
  loading.value = true
  try {
    await Promise.all([loadModels(), loadAnomalies(), loadRevenueTrend()])
  } finally {
    loading.value = false
  }
}

onMounted(loadAll)

const maxRevenue = computed(() => {
  const values = [
    ...(revenueTrend.value?.months ?? []).map(m => m.revenue),
    ...(revenueTrend.value?.forecast ?? []).map(m => m.forecast_value),
  ]
  return values.length ? Math.max(...values) : 1
})

function progressPercent(value) {
  return maxRevenue.value > 0 ? Math.round((value / maxRevenue.value) * 100) : 0
}

const nextForecastValue = computed(() => {
  const first = revenueTrend.value?.forecast?.[0]
  return first ? formatXof(first.forecast_value) : '—'
})

const trendColor = computed(() => {
  const t = revenueTrend.value?.trend
  if (t === 'up' || t === 'increasing') return 'text-green-600'
  if (t === 'down' || t === 'decreasing') return 'text-red-500'
  return 'text-surface-600'
})

function formatXof(value) {
  if (value === null || value === undefined) return '—'
  return new Intl.NumberFormat('fr-FR').format(Math.round(value))
}

function formatDate(value) {
  return value ? new Date(value).toLocaleDateString('fr-FR', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'
}

function severityTag(severity) {
  return { low: 'info', medium: 'secondary', high: 'warn', critical: 'danger' }[severity] ?? 'secondary'
}

async function acknowledge(anomaly) {
  acknowledgingId.value = anomaly.id
  try {
    await axios.post(`/api/v1/bi/anomalies/${anomaly.id}/acknowledge`)
    anomaly.status = 'acknowledged'
  } finally {
    acknowledgingId.value = null
  }
}

async function generateForecasts(model) {
  generatingId.value = model.id
  try {
    await axios.post(`/api/v1/bi/predictive-models/${model.id}/generate`, {
      days: model.forecast_horizon_days || 30,
    })
  } finally {
    generatingId.value = null
  }
}
</script>
