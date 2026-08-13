<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Analyses Prédictives — IA</h1>
        <p class="text-surface-500 text-sm mt-1">Prévisions de CA, churn, demande et comportements clients basés sur le machine learning</p>
      </div>
      <div class="flex gap-2">
        <Select v-model="horizon" :options="['30 jours', '90 jours', '6 mois', '12 mois']" size="small" class="w-36" />
        <Button label="Rafraîchir les modèles" icon="pi pi-refresh" :loading="refreshing" @click="refresh" v-if="canManage" />
      </div>
    </div>

    <TabView>
      <TabPanel header="Prévision CA">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
          <Card v-for="s in revenueStats" :key="s.label"><template #content>
            <div class="text-xl font-bold" :class="s.color">{{ s.value }}</div>
            <div class="text-xs text-surface-500 mt-1">{{ s.label }}</div>
          </template></Card>
        </div>
        <Card>
          <template #content>
            <div class="space-y-3">
              <div v-for="month in revenueForecasts" :key="month.period" class="flex items-center gap-3">
                <span class="text-sm w-20">{{ month.period }}</span>
                <div class="flex-1 relative">
                  <ProgressBar :value="Math.round(month.forecast / maxForecast * 100)" :style="{ height: '24px' }" />
                  <span class="absolute left-2 top-1/2 -translate-y-1/2 text-xs font-bold text-white">{{ month.forecast }}M</span>
                </div>
                <span class="text-xs w-24 text-right" :class="month.trend > 0 ? 'text-green-600' : 'text-red-500'">{{ month.trend > 0 ? '+' : '' }}{{ month.trend }}% vs N-1</span>
                <Tag :value="month.confidence" severity="secondary" size="small" />
              </div>
            </div>
          </template>
        </Card>
      </TabPanel>

      <TabPanel header="Prédiction Churn">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <Card>
            <template #header><div class="px-4 pt-4 font-semibold">Clients à risque de désabonnement</div></template>
            <template #content>
              <DataTable :value="churnRisks" stripedRows>
                <Column field="client" header="Client" />
                <Column field="churnProba" header="Risque churn">
                  <template #body="{ data }">
                    <div class="flex items-center gap-2">
                      <ProgressBar :value="data.churnProba" :style="{ height: '8px', width: '80px' }" />
                      <span :class="data.churnProba >= 75 ? 'text-red-600 font-bold' : data.churnProba >= 50 ? 'text-orange-500' : 'text-green-600'">{{ data.churnProba }}%</span>
                    </div>
                  </template>
                </Column>
                <Column field="lastOrder" header="Dernier achat" />
                <Column field="signal" header="Signal principal" />
                <Column header="">
                  <template #body><Button label="Contacter" size="small" text /></template>
                </Column>
              </DataTable>
            </template>
          </Card>

          <Card>
            <template #header><div class="px-4 pt-4 font-semibold">LTV Prédite — Top clients</div></template>
            <template #content>
              <div class="space-y-3">
                <div v-for="client in ltvPredictions" :key="client.name" class="flex items-center justify-between p-2 border-b border-surface-100 last:border-0">
                  <div>
                    <div class="font-medium text-sm">{{ client.name }}</div>
                    <div class="text-xs text-surface-400">{{ client.segment }}</div>
                  </div>
                  <div class="text-right">
                    <div class="font-bold text-blue-600">{{ client.ltv }} M XOF</div>
                    <div class="text-xs text-surface-400">LTV 24 mois · conf. {{ client.conf }}</div>
                  </div>
                </div>
              </div>
            </template>
          </Card>
        </div>
      </TabPanel>

      <TabPanel header="Modèles ML">
        <DataTable :value="models" stripedRows>
          <Column field="name" header="Modèle" />
          <Column field="type" header="Type" />
          <Column field="target" header="Variable cible" />
          <Column field="accuracy" header="Précision">
            <template #body="{ data }"><Tag :value="data.accuracy" :severity="parseFloat(data.accuracy) >= 90 ? 'success' : parseFloat(data.accuracy) >= 80 ? 'warn' : 'danger'" size="small" /></template>
          </Column>
          <Column field="trainingDate" header="Dernier entraînement" />
          <Column field="dataPoints" header="Données d'entraînement" />
          <Column header="">
            <template #body><Button label="Réentraîner" size="small" text /></template>
          </Column>
        </DataTable>
      </TabPanel>
    </TabView>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Card from 'primevue/card'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import ProgressBar from 'primevue/progressbar'
import TabView from 'primevue/tabview'
import TabPanel from 'primevue/tabpanel'
import Select from 'primevue/select'

const page = usePage()
const roles = computed(() => page.props.auth?.user?.roles?.map(r => r.name) || [])
const canManage = computed(() => roles.value.some(r => ['analyst','admin','super-admin'].includes(r)))

const horizon = ref('90 jours')
const refreshing = ref(false)
const refresh = async () => { refreshing.value = true; await new Promise(r => setTimeout(r, 2000)); refreshing.value = false }

const revenueStats = [
  { label: 'Prévision Juin 2026', value: '310M XOF', color: 'text-blue-600' },
  { label: 'Intervalle confiance', value: '±8%', color: 'text-surface-600' },
  { label: 'Précision modèle', value: '91%', color: 'text-green-600' },
  { label: 'vs Budget', value: '+4%', color: 'text-purple-600' },
]

const revenueForecasts = [
  { period: 'Juin 2026', forecast: 310, trend: 9, confidence: '91%' },
  { period: 'Juil. 2026', forecast: 295, trend: 5, confidence: '87%' },
  { period: 'Août 2026', forecast: 280, trend: 2, confidence: '82%' },
  { period: 'Sep. 2026', forecast: 340, trend: 15, confidence: '78%' },
  { period: 'Oct. 2026', forecast: 360, trend: 18, confidence: '74%' },
  { period: 'Nov. 2026', forecast: 420, trend: 22, confidence: '69%' },
]

const maxForecast = computed(() => Math.max(...revenueForecasts.map(m => m.forecast)))

const churnRisks = ref([
  { client: 'Vodafone Ghana', churnProba: 82, lastOrder: 'il y a 45j', signal: 'Inactivité + tickets ouverts' },
  { client: 'BCEAO', churnProba: 71, lastOrder: 'il y a 38j', signal: 'Renouvellement contrat expiré' },
  { client: 'Orange CI', churnProba: 45, lastOrder: 'il y a 22j', signal: 'Baisse volumes -30%' },
  { client: 'Ecobank Sénégal', churnProba: 18, lastOrder: 'il y a 8j', signal: '—' },
])

const ltvPredictions = [
  { name: 'Groupe Sonatel', segment: 'Grand compte', ltv: '245', conf: '88%' },
  { name: 'Ecobank Sénégal', segment: 'Grand compte', ltv: '198', conf: '85%' },
  { name: 'MTN Cameroun', segment: 'Grand compte', ltv: '174', conf: '82%' },
  { name: 'Orange CI', segment: 'Grand compte', ltv: '156', conf: '79%' },
]

const models = ref([
  { name: 'Prévision CA', type: 'Time Series (LSTM)', target: 'Chiffre d\'affaires mensuel', accuracy: '91%', trainingDate: '1 mai 2026', dataPoints: '36 mois' },
  { name: 'Prédiction churn', type: 'Gradient Boosting', target: 'Probabilité désabonnement', accuracy: '87%', trainingDate: '15 mai 2026', dataPoints: '12 400 clients' },
  { name: 'LTV client', type: 'Régression XGBoost', target: 'Valeur vie client 24 mois', accuracy: '84%', trainingDate: '10 mai 2026', dataPoints: '8 200 clients' },
  { name: 'Prévision demande', type: 'Prophet (Facebook)', target: 'Quantité vendue par SKU', accuracy: '89%', trainingDate: '20 mai 2026', dataPoints: '284 SKUs × 24 mois' },
])
</script>
