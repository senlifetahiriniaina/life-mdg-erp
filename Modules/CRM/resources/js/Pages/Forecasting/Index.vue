<template>
  <AppLayout>
    <Head title="Prévisions de Revenus" />

    <div class="space-y-6">
      <!-- Page header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">
            Prévisions de Revenus
          </h1>
          <p class="text-surface-500 text-sm mt-1">
            Analysez vos revenus prévisionnels selon différents scénarios
          </p>
        </div>
        <div class="flex gap-2">
          <Button
            icon="pi pi-plus"
            label="Nouveau scénario"
            outlined
            @click="showNewScenarioDialog = true"
          />
          <Button
            icon="pi pi-refresh"
            label="Actualiser"
            @click="refreshForecasts"
            :loading="loading"
          />
        </div>
      </div>

      <!-- Scenario Tabs -->
      <TabView v-model:activeIndex="activeScenario">
        <TabPanel
          v-for="scenario in scenarios"
          :key="scenario.key"
          :header="scenario.label"
        >
          <div class="space-y-6 pt-2">
            <!-- Scenario Summary -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-5 border border-surface-200 dark:border-surface-700 shadow-sm">
                <p class="text-sm text-surface-500 font-medium">Taux de conversion supposé</p>
                <p class="text-2xl font-bold mt-1" :class="scenario.winRateColor">
                  {{ scenario.winRate }}%
                </p>
              </div>
              <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-5 border border-surface-200 dark:border-surface-700 shadow-sm">
                <p class="text-sm text-surface-500 font-medium">Taille moyenne d'affaire</p>
                <p class="text-2xl font-bold text-surface-900 dark:text-surface-50 mt-1">
                  {{ formatCurrency(scenario.avgDealSize) }}
                </p>
              </div>
              <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-5 border border-surface-200 dark:border-surface-700 shadow-sm">
                <p class="text-sm text-surface-500 font-medium">Revenu total prévu (3 mois)</p>
                <p class="text-2xl font-bold mt-1" :class="scenario.winRateColor">
                  {{ formatCurrency(scenario.months.reduce((a, m) => a + m.revenue, 0)) }}
                </p>
              </div>
            </div>

            <!-- Monthly Forecast Table -->
            <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 overflow-hidden">
              <div class="px-6 py-4 border-b border-surface-200 dark:border-surface-700">
                <h2 class="text-base font-semibold text-surface-900 dark:text-surface-50">
                  Prévisions mensuelles — {{ scenario.label }}
                </h2>
              </div>
              <DataTable :value="scenario.months" class="p-datatable-sm">
                <Column field="month" header="Mois" />
                <Column field="opportunities" header="Opportunités actives">
                  <template #body="{ data }">
                    <span class="font-medium">{{ data.opportunities }}</span>
                  </template>
                </Column>
                <Column field="weighted" header="Valeur pondérée">
                  <template #body="{ data }">
                    {{ formatCurrency(data.weighted) }}
                  </template>
                </Column>
                <Column field="revenue" header="Revenu prévu">
                  <template #body="{ data }">
                    <span class="font-bold" :class="scenario.winRateColor">
                      {{ formatCurrency(data.revenue) }}
                    </span>
                  </template>
                </Column>
                <Column field="confidence" header="Confiance">
                  <template #body="{ data }">
                    <Tag :value="data.confidence + '%'" :severity="confidenceSeverity(data.confidence)" />
                  </template>
                </Column>
              </DataTable>
            </div>

            <!-- Pipeline Waterfall -->
            <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
              <h2 class="text-base font-semibold text-surface-900 dark:text-surface-50 mb-4">
                Entonnoir Pipeline — {{ scenario.label }}
              </h2>
              <div class="space-y-3">
                <div v-for="stage in scenario.pipeline" :key="stage.name" class="flex items-center gap-4">
                  <div class="w-28 text-sm text-surface-600 dark:text-surface-300 text-right flex-shrink-0">
                    {{ stage.name }}
                  </div>
                  <div class="flex-1 bg-surface-100 dark:bg-surface-700 rounded-full h-8 relative overflow-hidden">
                    <div
                      :class="['h-8 rounded-full flex items-center justify-end pr-3 transition-all', stage.color]"
                      :style="{ width: Math.min(stage.pct, 100) + '%' }"
                    >
                      <span class="text-white text-xs font-bold">{{ formatCurrency(stage.value) }}</span>
                    </div>
                  </div>
                  <div class="w-10 text-sm text-surface-500 flex-shrink-0">{{ stage.count }}</div>
                </div>
              </div>
            </div>
          </div>
        </TabPanel>
      </TabView>
    </div>

    <!-- New Scenario Dialog -->
    <Dialog
      v-model:visible="showNewScenarioDialog"
      header="Créer un nouveau scénario"
      modal
      class="w-full max-w-md"
    >
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-surface-700 dark:text-surface-200 mb-1">Nom du scénario</label>
          <InputText v-model="newScenario.label" class="w-full" placeholder="Ex: Expansion Afrique de l'Ouest" />
        </div>
        <div>
          <label class="block text-sm font-medium text-surface-700 dark:text-surface-200 mb-1">Taux de conversion (%)</label>
          <InputNumber v-model="newScenario.winRate" :min="0" :max="100" suffix="%" class="w-full" />
        </div>
        <div>
          <label class="block text-sm font-medium text-surface-700 dark:text-surface-200 mb-1">Taille moyenne d'affaire (XOF)</label>
          <InputNumber v-model="newScenario.avgDealSize" :min="0" class="w-full" />
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" outlined @click="showNewScenarioDialog = false" />
        <Button label="Créer" icon="pi pi-plus" @click="createScenario" />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed} from 'vue'
import { Head, usePage} from '@inertiajs/vue3'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import TabView from 'primevue/tabview'
import TabPanel from 'primevue/tabpanel'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import AppLayout from '@/Layouts/AppLayout.vue'

const page = usePage()
const roles = computed(() => page.props.auth?.user?.roles?.map(r => r.name) || [])
const isAdmin = computed(() => roles.value.some(r => ['admin', 'super-admin'].includes(r)))
const canManage = computed(() => roles.value.some(r => ['crm-manager', 'sales-manager', 'admin', 'super-admin'].includes(r)))
const canCreate = computed(() => canManage.value)
const canEdit = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)


interface PipelineStage {
  name: string
  value: number
  count: number
  pct: number
  color: string
}

interface ForecastMonth {
  month: string
  opportunities: number
  weighted: number
  revenue: number
  confidence: number
}

interface Scenario {
  key: string
  label: string
  winRate: number
  avgDealSize: number
  winRateColor: string
  months: ForecastMonth[]
  pipeline: PipelineStage[]
}

const loading = ref(false)
const activeScenario = ref(1)
const showNewScenarioDialog = ref(false)

const newScenario = ref({ label: '', winRate: 30, avgDealSize: 5000000 })

const scenarios = ref<Scenario[]>([
  {
    key: 'pessimiste',
    label: 'Pessimiste',
    winRate: 18,
    avgDealSize: 4200000,
    winRateColor: 'text-red-600',
    months: [
      { month: 'Juin 2026', opportunities: 24, weighted: 18900000, revenue: 7560000, confidence: 40 },
      { month: 'Juillet 2026', opportunities: 21, weighted: 16500000, revenue: 6300000, confidence: 35 },
      { month: 'Août 2026', opportunities: 19, weighted: 14700000, revenue: 5040000, confidence: 30 },
    ],
    pipeline: [
      { name: 'Qualifiés', value: 62000000, count: 38, pct: 100, color: 'bg-blue-400' },
      { name: 'Proposition', value: 41000000, count: 24, pct: 66, color: 'bg-yellow-400' },
      { name: 'Négociation', value: 22000000, count: 14, pct: 35, color: 'bg-orange-400' },
      { name: 'Gagnés', value: 7560000, count: 5, pct: 12, color: 'bg-red-400' },
    ],
  },
  {
    key: 'realiste',
    label: 'Réaliste',
    winRate: 32,
    avgDealSize: 6500000,
    winRateColor: 'text-blue-600',
    months: [
      { month: 'Juin 2026', opportunities: 24, weighted: 34800000, revenue: 18720000, confidence: 72 },
      { month: 'Juillet 2026', opportunities: 27, weighted: 39200000, revenue: 20800000, confidence: 68 },
      { month: 'Août 2026', opportunities: 25, weighted: 36500000, revenue: 19500000, confidence: 64 },
    ],
    pipeline: [
      { name: 'Qualifiés', value: 78000000, count: 38, pct: 100, color: 'bg-blue-500' },
      { name: 'Proposition', value: 52000000, count: 24, pct: 67, color: 'bg-yellow-500' },
      { name: 'Négociation', value: 31000000, count: 14, pct: 40, color: 'bg-orange-500' },
      { name: 'Gagnés', value: 18720000, count: 8, pct: 24, color: 'bg-blue-600' },
    ],
  },
  {
    key: 'optimiste',
    label: 'Optimiste',
    winRate: 48,
    avgDealSize: 9100000,
    winRateColor: 'text-green-600',
    months: [
      { month: 'Juin 2026', opportunities: 24, weighted: 58800000, revenue: 36400000, confidence: 55 },
      { month: 'Juillet 2026', opportunities: 30, weighted: 74200000, revenue: 45500000, confidence: 50 },
      { month: 'Août 2026', opportunities: 28, weighted: 68900000, revenue: 42700000, confidence: 45 },
    ],
    pipeline: [
      { name: 'Qualifiés', value: 112000000, count: 38, pct: 100, color: 'bg-green-400' },
      { name: 'Proposition', value: 76000000, count: 24, pct: 68, color: 'bg-green-500' },
      { name: 'Négociation', value: 48000000, count: 14, pct: 43, color: 'bg-green-600' },
      { name: 'Gagnés', value: 36400000, count: 12, pct: 32, color: 'bg-green-700' },
    ],
  },
])

const formatCurrency = (value: number): string => {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF', maximumFractionDigits: 0 }).format(value)
}

const confidenceSeverity = (confidence: number): string => {
  if (confidence >= 65) return 'success'
  if (confidence >= 45) return 'warn'
  return 'danger'
}

const refreshForecasts = async () => {
  loading.value = true
  await new Promise(resolve => setTimeout(resolve, 1000))
  loading.value = false
}

const createScenario = () => {
  showNewScenarioDialog.value = false
  // In production: POST /api/v1/crm/forecasting/scenarios
}
</script>
