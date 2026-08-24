<template>
  <AppLayout title="CSAT Reports">
    <div class="p-6">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-surface-50 dark:text-surface-50">Rapports CSAT</h1>
          <p class="text-sm text-gray-500 dark:text-surface-400 mt-1">Customer Satisfaction Score — analyse complète</p>
        </div>
        <div class="flex gap-2">
          <InputText v-model="filters.from" type="date" class="text-sm" />
          <InputText v-model="filters.to" type="date" class="text-sm" />
          <Button label="Actualiser" icon="pi pi-refresh" @click="loadReport" :loading="loading" />
        </div>
      </div>

      <AiAssistantPanel v-if="guidance" :guidance="guidance" />

      <!-- Tabs -->
      <div class="flex border-b border-gray-200 dark:border-surface-700 mb-6 gap-1">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors"
          :class="activeTab === tab.key
            ? 'bg-white dark:bg-surface-800 dark:bg-surface-800 border border-b-white border-gray-200 dark:border-surface-700 text-blue-600 -mb-px'
            : 'text-gray-500 hover:text-gray-700 dark:text-surface-100 dark:text-surface-100'"
          @click="activeTab = tab.key"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="flex items-center justify-center py-16">
        <i class="pi pi-spin pi-spinner text-3xl text-gray-400" />
      </div>

      <template v-else>
        <!-- Tab: Vue générale -->
        <div v-show="activeTab === 'overview'">
          <!-- KPI Cards -->
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl border border-gray-200 dark:border-surface-700 p-5 flex flex-col gap-2">
              <span class="text-xs font-semibold text-gray-500 dark:text-surface-400 uppercase tracking-wide">Score CSAT</span>
              <span
                class="text-5xl font-extrabold"
                :class="csatColor(report.score)"
              >{{ report.score }}%</span>
              <span class="text-xs text-gray-400">Réponses ≥ 4 étoiles / total</span>
            </div>
            <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl border border-gray-200 dark:border-surface-700 p-5 flex flex-col gap-2">
              <span class="text-xs font-semibold text-gray-500 dark:text-surface-400 uppercase tracking-wide">Réponses (période)</span>
              <span class="text-4xl font-bold text-gray-800 dark:text-surface-100">{{ totalResponses }}</span>
              <span class="text-xs text-gray-400">Avec note renseignée</span>
            </div>
            <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl border border-gray-200 dark:border-surface-700 p-5 flex flex-col gap-2">
              <span class="text-xs font-semibold text-gray-500 dark:text-surface-400 uppercase tracking-wide">Score moyen</span>
              <span class="text-4xl font-bold text-gray-800 dark:text-surface-100">{{ avgScore }}</span>
              <span class="text-xs text-gray-400">Sur 5 étoiles</span>
            </div>
            <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl border border-gray-200 dark:border-surface-700 p-5 flex flex-col gap-2">
              <span class="text-xs font-semibold text-gray-500 dark:text-surface-400 uppercase tracking-wide">Commentaires</span>
              <span class="text-4xl font-bold text-gray-800 dark:text-surface-100">{{ report.comments?.length ?? 0 }}</span>
              <span class="text-xs text-gray-400">Récents (négatifs d'abord)</span>
            </div>
          </div>

          <!-- Trend Chart -->
          <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl border border-gray-200 dark:border-surface-700 p-5 mb-6">
            <h2 class="text-sm font-semibold text-gray-700 dark:text-surface-100 dark:text-surface-100 mb-4">Évolution semaine par semaine (12 semaines)</h2>
            <VueApexCharts
              v-if="trendSeries.length"
              type="line"
              height="220"
              :options="trendChartOptions"
              :series="trendSeries"
            />
            <div v-else class="text-center text-gray-400 py-8 text-sm">Aucune donnée disponible</div>
          </div>

          <!-- Distribution 1-5 -->
          <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl border border-gray-200 dark:border-surface-700 p-5">
            <h2 class="text-sm font-semibold text-gray-700 dark:text-surface-100 dark:text-surface-100 mb-4">Distribution des scores (1 à 5 étoiles)</h2>
            <VueApexCharts
              v-if="distributionSeries.length"
              type="bar"
              height="200"
              :options="distributionChartOptions"
              :series="distributionSeries"
            />
            <div v-else class="text-center text-gray-400 py-8 text-sm">Aucune donnée disponible</div>
          </div>
        </div>

        <!-- Tab: Par agent -->
        <div v-show="activeTab === 'agents'">
          <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl border border-gray-200 dark:border-surface-700">
            <DataTable :value="report.by_agent" class="text-sm" :rows="20">
              <Column field="agent_name" header="Agent" sortable />
              <Column field="responses" header="Réponses" sortable />
              <Column field="avg_score" header="Score moyen" sortable>
                <template #body="{ data }">
                  <span>{{ data.avg_score.toFixed(2) }} ⭐</span>
                </template>
              </Column>
              <Column field="csat_pct" header="CSAT %" sortable>
                <template #body="{ data }">
                  <Tag
                    :value="`${data.csat_pct}%`"
                    :severity="data.csat_pct >= 80 ? 'success' : data.csat_pct >= 60 ? 'warn' : 'danger'"
                  />
                </template>
              </Column>
            </DataTable>
          </div>
        </div>

        <!-- Tab: Commentaires -->
        <div v-show="activeTab === 'comments'">
          <div v-if="report.comments?.length" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div
              v-for="c in report.comments"
              :key="c.id"
              class="bg-white dark:bg-surface-800 rounded-xl border p-4 flex flex-col gap-2"
              :class="c.score <= 2 ? 'border-red-200 bg-red-50' : c.score >= 4 ? 'border-green-200 bg-green-50 dark:bg-surface-800' : 'border-gray-200 dark:border-surface-700'"
            >
              <div class="flex items-center justify-between">
                <div class="flex gap-1">
                  <span v-for="s in 5" :key="s">
                    <i :class="s <= c.score ? 'pi pi-star-fill text-yellow-400' : 'pi pi-star text-gray-300'" style="font-size:13px" />
                  </span>
                </div>
                <span class="text-xs text-gray-400">#{{ c.ticket_id }}</span>
              </div>
              <p class="text-sm text-gray-700 dark:text-surface-100 italic">"{{ c.comment }}"</p>
              <span class="text-xs text-gray-400">{{ formatDate(c.responded_at) }}</span>
            </div>
          </div>
          <div v-else class="text-center text-gray-400 py-16">Aucun commentaire récent</div>
        </div>

        <!-- Tab: Campagnes -->
        <div v-show="activeTab === 'campaigns'">
          <div class="flex justify-end mb-4">
            <Button label="Nouvelle campagne" icon="pi pi-plus" @click="showCampaignDialog = true" />
          </div>

          <div class="bg-white dark:bg-surface-800 rounded-xl border border-gray-200 dark:border-surface-700">
            <DataTable :value="campaigns" class="text-sm" :rows="20" :loading="loadingCampaigns">
              <Column field="name" header="Nom" sortable />
              <Column field="trigger" header="Déclencheur" />
              <Column field="delay_hours" header="Délai (h)" />
              <Column field="surveys_count" header="Enquêtes" />
              <Column field="active" header="Statut">
                <template #body="{ data }">
                  <Tag :value="data.active ? 'Actif' : 'Inactif'" :severity="data.active ? 'success' : 'secondary'" />
                </template>
              </Column>
            </DataTable>
          </div>
        </div>
      </template>
    </div>

    <!-- Create Campaign Dialog -->
    <Dialog v-model:visible="showCampaignDialog" header="Nouvelle campagne CSAT" modal :style="{ width: '480px' }">
      <div class="flex flex-col gap-4 pt-2">
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-surface-100">Nom *</label>
          <InputText v-model="newCampaign.name" placeholder="Ex: Post-résolution" />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-surface-100">Déclencheur</label>
          <Dropdown
            v-model="newCampaign.trigger"
            :options="triggerOptions"
            option-label="label"
            option-value="value"
            placeholder="Sélectionner"
          />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-surface-100">Délai (heures)</label>
          <InputNumber v-model="newCampaign.delay_hours" :min="0" :max="168" />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-surface-100">Question *</label>
          <Textarea v-model="newCampaign.question_text" rows="3" placeholder="Comment évaluez-vous notre support ?" />
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" @click="showCampaignDialog = false" />
        <Button label="Créer" icon="pi pi-check" @click="createCampaign" :loading="savingCampaign" />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, defineAsyncComponent } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, DataTable, Column, Dialog, Tag, InputText, Textarea, Select as Dropdown, InputNumber } from 'primevue'
import axios from 'axios'
import AiAssistantPanel from '@/Components/AI/AiAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'

const { guidance } = useAiAssistant('Helpdesk', 'csat_surveys')

// Lazy load ApexCharts
const VueApexCharts = defineAsyncComponent(() => import('vue-apexcharts'))

const tabs = [
  { key: 'overview',  label: 'Vue générale' },
  { key: 'agents',    label: 'Par agent' },
  { key: 'comments',  label: 'Commentaires' },
  { key: 'campaigns', label: 'Campagnes' },
]
const activeTab = ref('overview')

const loading         = ref(false)
const loadingCampaigns = ref(false)
const showCampaignDialog = ref(false)
const savingCampaign  = ref(false)

const today = new Date().toISOString().split('T')[0]
const monthStart = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0]

const filters = ref({ from: monthStart, to: today })

const report = ref({
  score: 0,
  trend: [],
  by_agent: [],
  distribution: [],
  comments: [],
})
const campaigns = ref([])

const newCampaign = ref({
  name: '',
  trigger: 'ticket_closed',
  delay_hours: 24,
  question_text: '',
  active: true,
})

const triggerOptions = [
  { label: 'Ticket fermé', value: 'ticket_closed' },
  { label: 'Ticket résolu', value: 'ticket_resolved' },
]

// Computed
const totalResponses = computed(() => {
  return report.value.distribution?.reduce((s, d) => s + (d.count ?? 0), 0) ?? 0
})

const avgScore = computed(() => {
  const dist = report.value.distribution ?? []
  const total = dist.reduce((s, d) => s + d.count, 0)
  if (!total) return '—'
  const sum = dist.reduce((s, d) => s + d.score * d.count, 0)
  return (sum / total).toFixed(1)
})

function csatColor(score) {
  if (score >= 80) return 'text-green-600'
  if (score >= 60) return 'text-orange-500'
  return 'text-red-600'
}

function formatDate(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' })
}

// Charts
const trendSeries = computed(() => {
  const trend = report.value.trend ?? []
  if (!trend.length) return []
  return [{
    name: 'CSAT %',
    data: trend.map(t => t.score),
  }]
})

const trendChartOptions = computed(() => ({
  chart: { toolbar: { show: false }, zoom: { enabled: false } },
  xaxis: { categories: (report.value.trend ?? []).map(t => t.week) },
  yaxis: { min: 0, max: 100, labels: { formatter: (v) => v + '%' } },
  stroke: { curve: 'smooth', width: 2 },
  markers: { size: 4 },
  colors: ['#3B82F6'],
  tooltip: { y: { formatter: (v) => v + '%' } },
}))

const distributionSeries = computed(() => {
  const dist = report.value.distribution ?? []
  if (!dist.length) return []
  return [{
    name: 'Réponses',
    data: dist.map(d => d.count),
  }]
})

const distributionChartOptions = computed(() => ({
  chart: { toolbar: { show: false } },
  plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
  xaxis: { categories: ['⭐', '⭐⭐', '⭐⭐⭐', '⭐⭐⭐⭐', '⭐⭐⭐⭐⭐'] },
  colors: ['#EF4444', '#F97316', '#EAB308', '#22C55E', '#16A34A'],
  dataLabels: { enabled: true },
}))

// API calls
async function loadReport() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/helpdesk/csat/report', {
      params: { from: filters.value.from, to: filters.value.to },
    })
    report.value = data
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function loadCampaigns() {
  loadingCampaigns.value = true
  try {
    const { data } = await axios.get('/api/v1/helpdesk/csat/campaigns')
    campaigns.value = data.data ?? []
  } finally {
    loadingCampaigns.value = false
  }
}

async function createCampaign() {
  savingCampaign.value = true
  try {
    await axios.post('/api/v1/helpdesk/csat/campaigns', newCampaign.value)
    showCampaignDialog.value = false
    newCampaign.value = { name: '', trigger: 'ticket_closed', delay_hours: 24, question_text: '', active: true }
    await loadCampaigns()
  } finally {
    savingCampaign.value = false
  }
}

onMounted(async () => {
  await Promise.all([loadReport(), loadCampaigns()])
})
</script>
