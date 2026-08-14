<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">Analyse des Dépenses (AI)</h1>
        <p class="mt-2 text-surface-600 dark:text-surface-400">Visualisez vos dépenses, tendances et anomalies détectées par l'IA</p>
      </div>
      <div class="flex gap-2">
        <button
          @click="showAiPanel = !showAiPanel"
          class="px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg hover:bg-gray-50 dark:hover:bg-surface-700 text-surface-700 dark:text-surface-300"
          title="Assistant IA"
        >
          <i class="pi pi-sparkles" />
        </button>
        <select
          v-model="selectedPeriod"
          class="px-4 py-2 border border-subtle rounded-lg focus:ring-blue-500 text-sm"
        >
          <option value="ytd">Janvier – Mai 2026 (YTD)</option>
          <option value="q1">T1 2026</option>
          <option value="2025">Année 2025</option>
        </select>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="bg-canvas dark:bg-surface-800 rounded-lg shadow p-5">
        <div class="text-sm text-surface-500 dark:text-surface-400">Dépenses totales YTD</div>
        <div class="text-2xl font-bold text-surface-900 dark:text-surface-50 mt-1">{{ formatXOF(stats.totalSpend) }}</div>
        <div class="text-xs text-surface-500 mt-1">+8% vs même période 2025</div>
      </div>
      <div class="bg-canvas dark:bg-surface-800 rounded-lg shadow p-5">
        <div class="text-sm text-surface-500 dark:text-surface-400">Économies vs budget</div>
        <div class="text-2xl font-bold text-green-600 dark:text-green-400 mt-1">{{ formatXOF(stats.savings) }}</div>
        <div class="text-xs text-green-600 mt-1">{{ stats.savingsPercent }}% en dessous du budget</div>
      </div>
      <div class="bg-canvas dark:bg-surface-800 rounded-lg shadow p-5">
        <div class="text-sm text-surface-500 dark:text-surface-400">Fournisseurs actifs</div>
        <div class="text-2xl font-bold text-surface-900 dark:text-surface-50 mt-1">{{ stats.activeSuppliers }}</div>
        <div class="text-xs text-surface-500 mt-1">{{ stats.newSuppliers }} nouveaux ce mois</div>
      </div>
      <div class="bg-canvas dark:bg-surface-800 rounded-lg shadow p-5">
        <div class="text-sm text-surface-500 dark:text-surface-400">Anomalies détectées</div>
        <div class="text-2xl font-bold text-red-600 dark:text-red-400 mt-1">{{ stats.anomalies }}</div>
        <div class="text-xs text-red-600 mt-1">{{ stats.criticalAnomalies }} critiques</div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Spend by Category -->
      <div class="bg-canvas dark:bg-surface-800 rounded-lg shadow p-6">
        <h2 class="text-lg font-bold text-surface-900 dark:text-surface-50 mb-4">Dépenses par catégorie</h2>
        <div class="space-y-4">
          <div
            v-for="cat in spendByCategory"
            :key="cat.name"
            class="space-y-1"
          >
            <div class="flex items-center justify-between text-sm">
              <span class="font-medium text-surface-700 dark:text-surface-300">{{ cat.name }}</span>
              <div class="flex items-center gap-3">
                <span class="text-surface-500 dark:text-surface-400">{{ cat.percent }}%</span>
                <span class="font-medium text-surface-900 dark:text-surface-50">{{ formatXOF(cat.amount) }}</span>
              </div>
            </div>
            <div class="w-full bg-surface-200 dark:bg-surface-700 rounded-full h-2.5">
              <div
                class="h-2.5 rounded-full transition-all duration-500"
                :class="cat.color"
                :style="{ width: cat.percent + '%' }"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Monthly Trends -->
      <div class="bg-canvas dark:bg-surface-800 rounded-lg shadow p-6">
        <h2 class="text-lg font-bold text-surface-900 dark:text-surface-50 mb-4">Tendances mensuelles 2026</h2>
        <table class="w-full">
          <thead>
            <tr class="border-b border-subtle">
              <th scope="col" class="pb-2 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Mois</th>
              <th scope="col" class="pb-2 text-right text-sm font-semibold text-surface-700 dark:text-surface-300">Dépenses (XOF)</th>
              <th scope="col" class="pb-2 text-right text-sm font-semibold text-surface-700 dark:text-surface-300">Budget</th>
              <th scope="col" class="pb-2 text-right text-sm font-semibold text-surface-700 dark:text-surface-300">Écart</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="month in monthlyTrends"
              :key="month.name"
              class="border-b border-subtle hover:bg-surface-50 dark:hover:bg-surface-700"
            >
              <td class="py-2 text-sm font-medium text-surface-900 dark:text-surface-50">{{ month.name }}</td>
              <td class="py-2 text-sm text-right text-surface-600 dark:text-surface-400">{{ formatXOF(month.spend) }}</td>
              <td class="py-2 text-sm text-right text-surface-600 dark:text-surface-400">{{ formatXOF(month.budget) }}</td>
              <td class="py-2 text-sm text-right font-medium" :class="month.spend <= month.budget ? 'text-green-600' : 'text-red-600'">
                {{ month.spend <= month.budget ? '−' : '+' }}{{ formatXOF(Math.abs(month.spend - month.budget)) }}
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="border-t-2 border-surface-300 dark:border-surface-600">
              <td class="pt-3 text-sm font-bold text-surface-900 dark:text-surface-50">Total YTD</td>
              <td class="pt-3 text-sm text-right font-bold text-surface-900 dark:text-surface-50">{{ formatXOF(stats.totalSpend) }}</td>
              <td class="pt-3 text-sm text-right font-bold text-surface-900 dark:text-surface-50">{{ formatXOF(totalBudget) }}</td>
              <td class="pt-3 text-sm text-right font-bold text-green-600">−{{ formatXOF(stats.savings) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- AI Anomalies Section -->
    <div class="bg-canvas dark:bg-surface-800 rounded-lg shadow p-6">
      <div class="flex items-center gap-3 mb-4">
        <i class="pi pi-sparkles text-purple-600 dark:text-purple-400 text-xl" />
        <h2 class="text-lg font-bold text-surface-900 dark:text-surface-50">Anomalies détectées par l'IA</h2>
        <span class="px-2 py-0.5 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 rounded-full text-xs font-medium">
          {{ anomalies.length }} anomalie(s)
        </span>
      </div>
      <div class="space-y-3">
        <div
          v-for="anomaly in anomalies"
          :key="anomaly.id"
          class="flex items-start gap-4 p-4 rounded-lg border"
          :class="anomalySeverityClass(anomaly.severity)"
        >
          <div class="mt-0.5">
            <i :class="anomalySeverityIcon(anomaly.severity)" class="text-lg" />
          </div>
          <div class="flex-1">
            <div class="flex items-center gap-2 mb-1">
              <span class="font-medium text-surface-900 dark:text-surface-50 text-sm">{{ anomaly.supplier }}</span>
              <span
                class="px-2 py-0.5 rounded-full text-xs font-medium"
                :class="anomalyTypeClass(anomaly.type)"
              >
                {{ anomaly.type }}
              </span>
            </div>
            <div class="text-sm text-surface-600 dark:text-surface-400">{{ anomaly.description }}</div>
            <div class="mt-1 flex items-center gap-4 text-xs text-surface-500 dark:text-surface-400">
              <span>Montant: <strong class="text-surface-900 dark:text-surface-50">{{ formatXOF(anomaly.amount) }}</strong></span>
              <span>Détectée le: {{ anomaly.detectedAt }}</span>
            </div>
          </div>
          <div class="flex gap-2">
            <button class="px-3 py-1 text-xs border border-subtle rounded-lg hover:bg-surface-50 dark:hover:bg-surface-700 text-surface-700 dark:text-surface-300">
              Ignorer
            </button>
            <button class="px-3 py-1 text-xs bg-blue-600 text-white rounded-lg hover:bg-blue-700">
              Investiguer
            </button>
          </div>
        </div>
      </div>
    </div>

    <AIAssistantPanel v-if="showAiPanel" :guidance="guidance" @close="showAiPanel = false" />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'

const page = usePage()
const { isAdmin, isElevated, hasAnyRole } = useRoleAccess()
const canManage = computed(() => isElevated.value || hasAnyRole(['purchasing-manager']))
const canCreate = computed(() => canManage.value)
const canEdit = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)


const { guidance } = useAiAssistant('Achats', 'view_dashboard')
const showAiPanel = ref(false)
const selectedPeriod = ref('ytd')

const stats = ref({
  totalSpend: 847500000,
  savings: 68400000,
  savingsPercent: 7.5,
  activeSuppliers: 47,
  newSuppliers: 3,
  anomalies: 5,
  criticalAnomalies: 2
})

const spendByCategory = ref([
  { name: 'Matières premières', amount: 312000000, percent: 37, color: 'bg-blue-500' },
  { name: 'Services informatiques', amount: 186000000, percent: 22, color: 'bg-purple-500' },
  { name: 'Logistique & Transport', amount: 143000000, percent: 17, color: 'bg-green-500' },
  { name: 'Équipements industriels', amount: 110000000, percent: 13, color: 'bg-orange-500' },
  { name: 'Services professionnels', amount: 59000000, percent: 7, color: 'bg-yellow-500' },
  { name: 'Fournitures de bureau', amount: 37500000, percent: 4, color: 'bg-red-400' },
])

const monthlyTrends = ref([
  { name: 'Janvier 2026', spend: 152000000, budget: 165000000 },
  { name: 'Février 2026', spend: 168000000, budget: 170000000 },
  { name: 'Mars 2026', spend: 184000000, budget: 175000000 },
  { name: 'Avril 2026', spend: 171000000, budget: 180000000 },
  { name: 'Mai 2026', spend: 172500000, budget: 178000000 },
])

const totalBudget = computed(() => monthlyTrends.value.reduce((sum, m) => sum + m.budget, 0))

const anomalies = ref([
  {
    id: 1,
    supplier: 'TransAfrique Logistique',
    type: 'Doublon',
    description: 'Deux factures identiques détectées pour la commande TL-2026-0312 : même montant, même date, deux références différentes.',
    amount: 8900000,
    detectedAt: '21 mai 2026',
    severity: 'critical'
  },
  {
    id: 2,
    supplier: 'Lomé Industries SA',
    type: 'Prix hors marché',
    description: 'Prix unitaire 43% supérieur au prix moyen de marché pour la catégorie Matières premières (réf. LIS-2026-048).',
    amount: 22500000,
    detectedAt: '20 mai 2026',
    severity: 'critical'
  },
  {
    id: 3,
    supplier: 'Dakar Supplies SARL',
    type: 'Fréquence inhabituelle',
    description: '14 commandes passées en 3 jours pour le même article — fréquence 5x supérieure à la normale.',
    amount: 4750000,
    detectedAt: '19 mai 2026',
    severity: 'warning'
  },
  {
    id: 4,
    supplier: 'Abidjan Tech Services',
    type: 'Dépassement budget',
    description: 'Catégorie Services informatiques à 109% du budget mensuel, dépassement de 12 200 000 XOF.',
    amount: 12200000,
    detectedAt: '18 mai 2026',
    severity: 'warning'
  },
  {
    id: 5,
    supplier: 'Accra Industrial Group',
    type: 'Délai de paiement',
    description: 'Facture AIG-2026-201 en retard de 32 jours — pénalités de retard contractuelles applicables.',
    amount: 31000000,
    detectedAt: '15 mai 2026',
    severity: 'info'
  },
])

const formatXOF = (amount) => {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF', minimumFractionDigits: 0 }).format(amount)
}

const anomalySeverityClass = (severity) => {
  const classes = {
    critical: 'border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20',
    warning: 'border-yellow-200 dark:border-yellow-800 bg-yellow-50 dark:bg-yellow-900/20',
    info: 'border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20',
  }
  return classes[severity] || 'border-subtle'
}

const anomalySeverityIcon = (severity) => {
  const icons = {
    critical: 'pi pi-exclamation-circle text-red-600 dark:text-red-400',
    warning: 'pi pi-exclamation-triangle text-yellow-600 dark:text-yellow-400',
    info: 'pi pi-info-circle text-blue-600 dark:text-blue-400',
  }
  return icons[severity] || 'pi pi-info-circle text-surface-500'
}

const anomalyTypeClass = (type) => {
  const classes = {
    'Doublon': 'bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300',
    'Prix hors marché': 'bg-orange-100 dark:bg-orange-900 text-orange-700 dark:text-orange-300',
    'Fréquence inhabituelle': 'bg-yellow-100 dark:bg-yellow-900 text-yellow-700 dark:text-yellow-300',
    'Dépassement budget': 'bg-purple-100 dark:bg-purple-900 text-purple-700 dark:text-purple-300',
    'Délai de paiement': 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300',
  }
  return classes[type] || 'bg-surface-100 dark:bg-surface-700 text-surface-700 dark:text-surface-300'
}
</script>
