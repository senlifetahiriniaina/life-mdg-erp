<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Prévision de Demande (IA — ForesightAI)</h1>
        <p class="text-surface-500 text-sm mt-1">Anticipez les ruptures et optimisez vos réapprovisionnements</p>
      </div>
      <Button label="Générer les prévisions" icon="pi pi-refresh" :loading="generating" @click="generateForecast" />
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <Card v-for="s in stats" :key="s.label"><template #content>
        <div class="text-2xl font-bold" :class="s.color">{{ s.value }}</div>
        <div class="text-sm text-surface-500 mt-1">{{ s.label }}</div>
      </template></Card>
    </div>

    <Card class="border-l-4 border-orange-400">
      <template #header><div class="px-4 pt-4 flex items-center justify-between">
        <span class="font-semibold text-orange-700">⚠️ Alertes de réapprovisionnement ({{ reorderAlerts.length }})</span>
        <Button label="Générer les bons de commande" icon="pi pi-shopping-cart" size="small" severity="warning" />
      </div></template>
      <template #content>
        <DataTable :value="reorderAlerts" size="small" stripedRows>
          <Column field="product" header="Produit" />
          <Column field="currentStock" header="Stock actuel" />
          <Column field="reorderPoint" header="Point de réappro." />
          <Column field="shortage" header="Manque estimé" />
          <Column field="supplier" header="Fournisseur" />
          <Column header="Action">
            <template #body><Button label="Créer BC" size="small" severity="warning" /></template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <Card>
      <template #header><div class="px-4 pt-4 font-semibold">Prévisions par produit</div></template>
      <template #content>
        <DataTable :value="forecasts" stripedRows responsiveLayout="scroll" selectionMode="single" v-model:selection="selectedProduct" @row-select="showDrawer = true">
          <Column field="product" header="Produit" />
          <Column field="currentStock" header="Stock actuel" />
          <Column field="reorderPoint" header="Pt. réappro." />
          <Column field="forecast30" header="Demande 30j" />
          <Column field="forecast90" header="Demande 90j" />
          <Column field="trend" header="Tendance">
            <template #body="{ data }"><span :class="trendClass(data.trend)">{{ data.trend }}</span></template>
          </Column>
          <Column field="confidence" header="Fiabilité %">
            <template #body="{ data }"><ProgressBar :value="data.confidence" :style="{ height: '8px' }" /></template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <Drawer v-model:visible="showDrawer" position="right" :style="{ width: '500px' }" :header="selectedProduct?.product + ' — Prévisions 12 semaines'">
      <div v-if="selectedProduct">
        <DataTable :value="weeklyForecast" size="small" stripedRows>
          <Column field="week" header="Semaine" />
          <Column field="demand" header="Demande prévue" />
          <Column field="stock" header="Stock projeté">
            <template #body="{ data }"><span :class="data.stock < 0 ? 'text-red-500 font-bold' : ''">{{ data.stock }}</span></template>
          </Column>
          <Column field="alert" header="">
            <template #body="{ data }"><Tag v-if="data.stock < 0" value="Rupture" severity="danger" size="small" /></template>
          </Column>
        </DataTable>
      </div>
    </Drawer>

    <AIAssistantPanel v-if="guidance" :guidance="guidance" />
  </div>
</template>

<script setup>
import { ref, computed} from 'vue'
import { usePage } from '@inertiajs/vue3'
import Card from 'primevue/card'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Drawer from 'primevue/drawer'
import ProgressBar from 'primevue/progressbar'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'

const page = usePage()
const roles = computed(() => page.props.auth?.user?.roles?.map(r => r.name) || [])
const isAdmin = computed(() => roles.value.some(r => ['admin', 'super-admin'].includes(r)))
const canManage = computed(() => roles.value.some(r => ['inventory-manager', 'admin', 'super-admin'].includes(r)))
const canCreate = computed(() => canManage.value)
const canEdit = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)


const { guidance } = useAiAssistant('Inventory', 'low_stock_alert')
const generating = ref(false)
const showDrawer = ref(false)
const selectedProduct = ref(null)

const stats = [
  { label: 'Produits analysés', value: '342', color: 'text-blue-600' },
  { label: 'Ruptures évitées ce mois', value: '18', color: 'text-green-600' },
  { label: 'Précision prévisions', value: '87%', color: 'text-purple-600' },
  { label: 'Valeur réappros suggérés', value: '8.4M XOF', color: 'text-orange-600' },
]

const reorderAlerts = ref([
  { product: 'Écran LCD 24"', currentStock: 12, reorderPoint: 50, shortage: 38, supplier: 'Tech Import SA' },
  { product: 'Câble HDMI 2m', currentStock: 8, reorderPoint: 100, shortage: 92, supplier: 'ElectroDistrib' },
  { product: 'Clavier sans fil', currentStock: 3, reorderPoint: 30, shortage: 27, supplier: 'Tech Import SA' },
])

const forecasts = ref([
  { product: 'Écran LCD 24"', currentStock: 12, reorderPoint: 50, forecast30: 45, forecast90: 132, trend: '↑ Hausse', confidence: 89 },
  { product: 'Câble HDMI 2m', currentStock: 8, reorderPoint: 100, forecast30: 95, forecast90: 280, trend: '↗ Légère hausse', confidence: 76 },
  { product: 'Laptop Pro 15"', currentStock: 24, reorderPoint: 20, forecast30: 18, forecast90: 52, trend: '→ Stable', confidence: 91 },
  { product: 'Souris optique', currentStock: 67, reorderPoint: 40, forecast30: 30, forecast90: 88, trend: '↘ Légère baisse', confidence: 82 },
  { product: 'Clavier sans fil', currentStock: 3, reorderPoint: 30, forecast30: 28, forecast90: 84, trend: '↑ Hausse', confidence: 85 },
  { product: 'Imprimante laser', currentStock: 15, reorderPoint: 10, forecast30: 8, forecast90: 22, trend: '↓ Baisse', confidence: 78 },
  { product: 'Serveur rack 2U', currentStock: 5, reorderPoint: 3, forecast30: 2, forecast90: 7, trend: '→ Stable', confidence: 94 },
  { product: 'Switch 24 ports', currentStock: 9, reorderPoint: 5, forecast30: 4, forecast90: 11, trend: '→ Stable', confidence: 88 },
  { product: 'UPS 1000VA', currentStock: 22, reorderPoint: 15, forecast30: 12, forecast90: 35, trend: '↗ Légère hausse', confidence: 80 },
  { product: 'Câble réseau Cat6 (100m)', currentStock: 45, reorderPoint: 20, forecast30: 15, forecast90: 44, trend: '↘ Légère baisse', confidence: 83 },
])

const weeklyForecast = [
  { week: 'S22', demand: 8, stock: 4 }, { week: 'S23', demand: 9, stock: -5 },
  { week: 'S24', demand: 11, stock: -16 }, { week: 'S25', demand: 7, stock: -23 },
  { week: 'S26', demand: 10, stock: -33 }, { week: 'S27', demand: 12, stock: -45 },
]

const trendClass = (t) => t.includes('Hausse') ? 'text-green-600' : t.includes('baisse') || t.includes('Baisse') ? 'text-red-500' : 'text-surface-500'

const generateForecast = async () => {
  generating.value = true
  await new Promise(r => setTimeout(r, 1500))
  generating.value = false
}
</script>
