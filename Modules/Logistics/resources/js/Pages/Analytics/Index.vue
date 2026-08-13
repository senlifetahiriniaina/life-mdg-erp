<template>
  <div class="space-y-6">
    <!-- En-tête -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Analytique Logistique</h1>
        <p class="text-gray-500 dark:text-gray-400 mt-1">Tableau de bord des performances de livraison</p>
      </div>
      <div class="flex gap-2">
        <Select v-model="periodeSelectionnee" :options="periodes" optionLabel="label" optionValue="value" class="w-44" />
        <Button label="Exporter" icon="pi pi-download" severity="secondary" outlined />
      </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <Card v-for="kpi in kpis" :key="kpi.label">
        <template #content>
          <div class="flex flex-col gap-2">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ kpi.label }}</span>
            <span class="text-3xl font-bold" :class="kpi.color">{{ kpi.valeur }}</span>
            <div class="flex items-center gap-1 text-sm">
              <i :class="kpi.tendance === 'up' ? 'pi pi-arrow-up text-green-500' : 'pi pi-arrow-down text-red-500'"></i>
              <span :class="kpi.tendance === 'up' ? 'text-green-600' : 'text-red-600'">{{ kpi.variation }}</span>
              <span class="text-gray-400">vs mois dernier</span>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <!-- Performance par transporteur -->
    <Card>
      <template #title>
        <div class="flex items-center gap-2">
          <i class="pi pi-truck text-blue-600"></i>
          <span>Performance par Transporteur</span>
        </div>
      </template>
      <template #content>
        <DataTable :value="performanceTransporteurs" stripedRows responsiveLayout="scroll">
          <Column field="transporteur" header="Transporteur" sortable />
          <Column field="livraisons" header="Livraisons" sortable>
            <template #body="{ data }">
              <span class="font-semibold">{{ data.livraisons.toLocaleString('fr-FR') }}</span>
            </template>
          </Column>
          <Column field="aTempsPct" header="À Temps %" sortable>
            <template #body="{ data }">
              <div class="flex items-center gap-2">
                <ProgressBar :value="data.aTempsPct" class="w-20 h-2" :showValue="false" />
                <span
                  :class="data.aTempsPct >= 90 ? 'text-green-600' : data.aTempsPct >= 75 ? 'text-yellow-600' : 'text-red-600'"
                  class="font-semibold text-sm"
                >{{ data.aTempsPct }}%</span>
              </div>
            </template>
          </Column>
          <Column field="coutMoyen" header="Coût Moyen (XOF)" sortable>
            <template #body="{ data }">
              <span class="font-semibold text-gray-800 dark:text-gray-200">{{ data.coutMoyen.toLocaleString('fr-FR') }} XOF</span>
            </template>
          </Column>
          <Column field="incidents" header="Incidents" sortable>
            <template #body="{ data }">
              <Tag
                :value="String(data.incidents)"
                :severity="data.incidents === 0 ? 'success' : data.incidents <= 3 ? 'warn' : 'danger'"
              />
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- Tendance mensuelle + Répartition géographique -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <!-- Tendance mensuelle -->
      <Card>
        <template #title>
          <div class="flex items-center gap-2">
            <i class="pi pi-chart-bar text-indigo-600"></i>
            <span>Livraisons par Mois</span>
          </div>
        </template>
        <template #content>
          <div class="space-y-3">
            <div v-for="mois in tendanceMensuelle" :key="mois.nom" class="flex items-center gap-3">
              <span class="text-sm text-gray-600 dark:text-gray-400 w-12 shrink-0">{{ mois.nom }}</span>
              <div class="flex-1 bg-gray-100 dark:bg-gray-700 rounded-full h-7 relative overflow-hidden">
                <div
                  class="h-full rounded-full bg-blue-500 flex items-center justify-end pr-2 transition-all duration-500"
                  :style="{ width: (mois.livraisons / maxLivraisons * 100) + '%' }"
                >
                  <span class="text-xs text-white font-semibold">{{ mois.livraisons }}</span>
                </div>
              </div>
              <span
                class="text-xs w-14 text-right shrink-0 font-medium"
                :class="mois.variation >= 0 ? 'text-green-600' : 'text-red-500'"
              >
                {{ mois.variation >= 0 ? '+' : '' }}{{ mois.variation }}%
              </span>
            </div>
          </div>
        </template>
      </Card>

      <!-- Répartition géographique -->
      <Card>
        <template #title>
          <div class="flex items-center gap-2">
            <i class="pi pi-map text-green-600"></i>
            <span>Répartition Géographique</span>
          </div>
        </template>
        <template #content>
          <div class="space-y-4">
            <div v-for="region in repartitionGeo" :key="region.nom" class="flex flex-col gap-1">
              <div class="flex justify-between text-sm">
                <span class="font-medium text-gray-700 dark:text-gray-300">{{ region.nom }}</span>
                <div class="flex gap-4 text-gray-500 dark:text-gray-400">
                  <span>{{ region.pct }}% des livraisons</span>
                  <span>
                    Délai moy. :
                    <strong :class="region.delaiMoyen <= 2 ? 'text-green-600' : region.delaiMoyen <= 4 ? 'text-yellow-600' : 'text-red-600'">
                      {{ region.delaiMoyen }}j
                    </strong>
                  </span>
                </div>
              </div>
              <ProgressBar
                :value="region.pct"
                class="h-2"
                :showValue="false"
              />
            </div>
          </div>
        </template>
      </Card>
    </div>

    <!-- Répartition des coûts par mode (ProgressBars) -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <Card>
        <template #title>
          <div class="flex items-center gap-2">
            <i class="pi pi-wallet text-orange-600"></i>
            <span>Répartition des coûts par mode — Ce mois</span>
          </div>
        </template>
        <template #content>
          <div class="mb-4 rounded-xl bg-orange-50 dark:bg-orange-950 p-4 text-center">
            <p class="text-sm text-orange-600 dark:text-orange-400">Total transport ce mois</p>
            <p class="text-3xl font-bold text-orange-700 dark:text-orange-300 mt-1">14 820 000 XOF</p>
          </div>
          <div class="space-y-4">
            <div v-for="mode in repartitionCoutsMode" :key="mode.type">
              <div class="flex items-center justify-between mb-1">
                <div class="flex items-center gap-2">
                  <i :class="mode.icon" class="text-sm text-gray-500"></i>
                  <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ mode.type }}</span>
                </div>
                <div class="flex items-center gap-3">
                  <span class="text-sm font-bold text-gray-800 dark:text-gray-200">{{ mode.amount }}</span>
                  <span class="text-xs text-gray-500 w-8 text-right">{{ mode.pct }}%</span>
                </div>
              </div>
              <ProgressBar :value="mode.pct" class="h-3" :showValue="false" />
            </div>
          </div>
        </template>
      </Card>

      <!-- Tendance mensuelle coûts -->
      <Card>
        <template #title>
          <div class="flex items-center gap-2">
            <i class="pi pi-chart-line text-purple-600"></i>
            <span>Évolution mensuelle des coûts — 6 mois</span>
          </div>
        </template>
        <template #content>
          <div class="space-y-4">
            <div v-for="month in coutsMensuels" :key="month.mois">
              <div class="flex items-center justify-between mb-1">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300 w-32">{{ month.mois }}</span>
                <span class="text-sm font-bold text-gray-800 dark:text-gray-200">{{ month.cout }}</span>
                <span class="text-xs w-14 text-right" :class="month.delta >= 0 ? 'text-red-500' : 'text-green-500'">
                  <i :class="month.delta >= 0 ? 'pi pi-arrow-up' : 'pi pi-arrow-down'" class="text-xs mr-0.5"></i>
                  {{ Math.abs(month.delta) }}%
                </span>
              </div>
              <ProgressBar :value="month.pct" class="h-4" :showValue="false" />
            </div>
          </div>
        </template>
      </Card>
    </div>

    <!-- Top destinations -->
    <Card>
      <template #title>
        <div class="flex items-center gap-2">
          <i class="pi pi-map-marker text-red-500"></i>
          <span>Top destinations — Ce mois</span>
        </div>
      </template>
      <template #content>
        <DataTable :value="topDestinations" size="small" stripedRows>
          <Column field="rank" header="#" style="width:50px">
            <template #body="{ data }">
              <span class="text-sm font-bold text-gray-500">{{ data.rank }}</span>
            </template>
          </Column>
          <Column field="destination" header="Destination" style="min-width:140px">
            <template #body="{ data }">
              <div class="flex items-center gap-1">
                <i class="pi pi-map-marker text-xs text-gray-400"></i>
                <span class="font-semibold text-gray-900 dark:text-white">{{ data.destination }}</span>
              </div>
            </template>
          </Column>
          <Column field="volume" header="Volume (expéd.)" style="width:130px">
            <template #body="{ data }">
              <span class="font-semibold text-blue-600">{{ data.volume }}</span>
            </template>
          </Column>
          <Column field="coutTotal" header="Coût total (XOF)" style="width:180px">
            <template #body="{ data }">
              <span class="font-semibold text-gray-800 dark:text-gray-200">{{ data.coutTotal.toLocaleString('fr-FR') }} XOF</span>
            </template>
          </Column>
          <Column field="delaiMoyen" header="Délai moyen" style="width:120px">
            <template #body="{ data }">
              <span class="text-sm" :class="parseFloat(data.delaiMoyen) <= 2 ? 'text-green-600' : parseFloat(data.delaiMoyen) <= 5 ? 'text-yellow-600' : 'text-red-600'">{{ data.delaiMoyen }}</span>
            </template>
          </Column>
          <Column field="otif" header="OTIF %" style="width:130px">
            <template #body="{ data }">
              <div class="flex items-center gap-2">
                <ProgressBar :value="data.otif" class="flex-1 h-2" :showValue="false" />
                <span class="text-sm font-semibold" :class="data.otif >= 90 ? 'text-green-600' : 'text-yellow-600'">{{ data.otif }}%</span>
              </div>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Card from 'primevue/card'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Select from 'primevue/select'
import ProgressBar from 'primevue/progressbar'

const page = usePage()
const roles = computed(() => page.props.auth?.user?.roles?.map(r => r.name) || [])
const isAdmin = computed(() => roles.value.some(r => ['admin', 'super-admin'].includes(r)))
const canManage = computed(() => roles.value.some(r => ['logistics-manager', 'admin', 'super-admin'].includes(r)))
const canCreate = computed(() => canManage.value)
const canEdit = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)


const page = usePage()
const user = computed(() => page.props.auth?.user)

const periodeSelectionnee = ref('mois')
const periodes = ref([
  { label: 'Ce mois', value: 'mois' },
  { label: 'Trimestre', value: 'trimestre' },
  { label: 'Année', value: 'annee' },
])

const kpis = ref([
  { label: 'Coût/kg moyen', valeur: '1 240 XOF', color: 'text-blue-600', tendance: 'down', variation: '-8%' },
  { label: 'OTIF (On-Time In-Full)', valeur: '87%', color: 'text-green-600', tendance: 'up', variation: '+3%' },
  { label: 'km parcourus ce mois', valeur: '48 320 km', color: 'text-purple-600', tendance: 'up', variation: '+5%' },
  { label: 'CO₂ émis ce mois', valeur: '4,7 t', color: 'text-teal-600', tendance: 'down', variation: '-12%' },
])

const performanceTransporteurs = ref([
  { transporteur: 'DHL Sénégal', livraisons: 1240, aTempsPct: 95, coutMoyen: 12500, incidents: 2 },
  { transporteur: 'Colissimo Afrique', livraisons: 890, aTempsPct: 88, coutMoyen: 7800, incidents: 5 },
  { transporteur: 'Express Mali', livraisons: 560, aTempsPct: 78, coutMoyen: 6200, incidents: 8 },
  { transporteur: 'Wave Delivery', livraisons: 430, aTempsPct: 91, coutMoyen: 5500, incidents: 3 },
  { transporteur: 'Rapid Cargo CI', livraisons: 320, aTempsPct: 82, coutMoyen: 9100, incidents: 4 },
])

const tendanceMensuelle = ref([
  { nom: 'Jan', livraisons: 820, variation: 0 },
  { nom: 'Fév', livraisons: 940, variation: 14.6 },
  { nom: 'Mar', livraisons: 1100, variation: 17.0 },
  { nom: 'Avr', livraisons: 980, variation: -10.9 },
  { nom: 'Mai', livraisons: 1250, variation: 27.6 },
  { nom: 'Jun', livraisons: 1180, variation: -5.6 },
])

const maxLivraisons = computed(() => Math.max(...tendanceMensuelle.value.map(m => m.livraisons)))

const repartitionGeo = ref([
  { nom: 'Dakar & banlieue', pct: 42, delaiMoyen: 1 },
  { nom: 'Thiès & Saint-Louis', pct: 22, delaiMoyen: 2 },
  { nom: 'Ziguinchor & Casamance', pct: 15, delaiMoyen: 4 },
  { nom: 'Tambacounda & Est', pct: 12, delaiMoyen: 5 },
  { nom: 'International UEMOA', pct: 9, delaiMoyen: 7 },
])

const repartitionCouts = ref([
  { categorie: 'Carburant', pct: 38, montant: 4200000, couleur: 'bg-blue-500' },
  { categorie: 'Salaires', pct: 32, montant: 3540000, couleur: 'bg-indigo-500' },
  { categorie: 'Maintenance', pct: 18, montant: 1990000, couleur: 'bg-yellow-500' },
  { categorie: 'Assurance', pct: 12, montant: 1325000, couleur: 'bg-green-500' },
])

const coutTotal = computed(() =>
  repartitionCouts.value.reduce((sum, c) => sum + c.montant, 0)
)

const repartitionCoutsMode = ref([
  { type: 'Routier', icon: 'pi pi-truck', pct: 54, amount: '7 980 000 XOF' },
  { type: 'Maritime', icon: 'pi pi-star', pct: 28, amount: '4 150 000 XOF' },
  { type: 'Aérien', icon: 'pi pi-send', pct: 13, amount: '1 930 000 XOF' },
  { type: 'Express', icon: 'pi pi-bolt', pct: 5, amount: '760 000 XOF' },
])

const coutsMensuels = ref([
  { mois: 'Décembre 2025', cout: '11,2 M XOF', pct: 70, delta: 0 },
  { mois: 'Janvier 2026', cout: '12,8 M XOF', pct: 80, delta: 14 },
  { mois: 'Février 2026', cout: '11,9 M XOF', pct: 74, delta: -7 },
  { mois: 'Mars 2026', cout: '13,4 M XOF', pct: 84, delta: 13 },
  { mois: 'Avril 2026', cout: '16,1 M XOF', pct: 100, delta: 20 },
  { mois: 'Mai 2026', cout: '14,8 M XOF', pct: 92, delta: -8 },
])

const topDestinations = ref([
  { rank: 1, destination: 'Abidjan', volume: 87, coutTotal: 4200000, delaiMoyen: '3,2 jours', otif: 91 },
  { rank: 2, destination: 'Lomé', volume: 54, coutTotal: 2850000, delaiMoyen: '4,0 jours', otif: 88 },
  { rank: 3, destination: 'Douala', volume: 41, coutTotal: 3100000, delaiMoyen: '8,5 jours', otif: 80 },
  { rank: 4, destination: 'Bamako', volume: 38, coutTotal: 1780000, delaiMoyen: '2,8 jours', otif: 93 },
  { rank: 5, destination: 'Cotonou', volume: 32, coutTotal: 1450000, delaiMoyen: '3,7 jours', otif: 87 },
  { rank: 6, destination: 'Accra', volume: 28, coutTotal: 980000, delaiMoyen: '1,9 jours', otif: 97 },
  { rank: 7, destination: 'Lagos', volume: 21, coutTotal: 1640000, delaiMoyen: '5,2 jours', otif: 79 },
])
</script>
