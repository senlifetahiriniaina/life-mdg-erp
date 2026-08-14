<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">Risque Fournisseurs</h1>
        <p class="mt-2 text-surface-600 dark:text-surface-400">Scoring de risque et évaluation continue de votre portefeuille fournisseurs</p>
      </div>
      <div class="flex gap-2">
        <button
          @click="showAiPanel = !showAiPanel"
          class="px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg hover:bg-gray-50 dark:hover:bg-surface-700 text-surface-700 dark:text-surface-300"
          title="Assistant IA"
        >
          <i class="pi pi-sparkles" />
        </button>
        <button
          @click="triggerBulkAssessment"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
        >
          <i class="pi pi-refresh mr-1" />Réévaluer tout
        </button>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="bg-canvas dark:bg-surface-800 rounded-lg shadow p-5">
        <div class="text-sm text-surface-500 dark:text-surface-400">Fournisseurs à risque élevé</div>
        <div class="text-3xl font-bold text-red-600 dark:text-red-400 mt-1">{{ stats.highRisk }}</div>
        <div class="text-xs text-red-600 mt-1">Score &lt; 40 — Action requise</div>
      </div>
      <div class="bg-canvas dark:bg-surface-800 rounded-lg shadow p-5">
        <div class="text-sm text-surface-500 dark:text-surface-400">Alertes actives</div>
        <div class="text-3xl font-bold text-orange-600 dark:text-orange-400 mt-1">{{ stats.activeAlerts }}</div>
        <div class="text-xs text-orange-600 mt-1">{{ stats.criticalAlerts }} critiques</div>
      </div>
      <div class="bg-canvas dark:bg-surface-800 rounded-lg shadow p-5">
        <div class="text-sm text-surface-500 dark:text-surface-400">Réévaluations dues</div>
        <div class="text-3xl font-bold text-yellow-600 dark:text-yellow-400 mt-1">{{ stats.dueReassessments }}</div>
        <div class="text-xs text-yellow-600 mt-1">Dernière éval. &gt; 30 jours</div>
      </div>
      <div class="bg-canvas dark:bg-surface-800 rounded-lg shadow p-5">
        <div class="text-sm text-surface-500 dark:text-surface-400">Score moyen portefeuille</div>
        <div class="text-3xl font-bold text-surface-900 dark:text-surface-50 mt-1">{{ stats.avgScore }}/100</div>
        <div class="text-xs text-green-600 mt-1">+4 pts vs trimestre précédent</div>
      </div>
    </div>

    <!-- Risk Table -->
    <div class="bg-canvas dark:bg-surface-800 rounded-lg shadow">
      <div class="p-6 border-b border-subtle">
        <div class="flex items-center gap-4">
          <input
            v-model="search"
            type="text"
            placeholder="Rechercher un fournisseur..."
            class="flex-1 px-4 py-2 border border-subtle rounded-lg focus:ring-blue-500 focus:border-transparent"
          />
          <select
            v-model="filterRisk"
            class="px-4 py-2 border border-subtle rounded-lg focus:ring-blue-500"
          >
            <option value="">Tous les niveaux</option>
            <option value="high">Risque élevé (&lt;40)</option>
            <option value="medium">Risque moyen (40–70)</option>
            <option value="low">Risque faible (&gt;70)</option>
          </select>
        </div>
      </div>

      <table class="w-full">
        <thead class="bg-surface-50 dark:bg-surface-800 border-b border-subtle">
          <tr>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Fournisseur</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Score risque</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Dimensions</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Dernière évaluation</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="supplier in filteredSuppliers"
            :key="supplier.id"
            class="border-b border-subtle hover:bg-surface-50 dark:hover:bg-surface-700 cursor-pointer"
            @click="selectSupplier(supplier)"
          >
            <td class="px-6 py-4">
              <div class="font-medium text-surface-900 dark:text-surface-50 text-sm">{{ supplier.name }}</div>
              <div class="text-xs text-surface-500 dark:text-surface-400">{{ supplier.category }} • {{ supplier.country }}</div>
            </td>
            <td class="px-6 py-4">
              <div class="flex items-center gap-3">
                <div class="flex-1 bg-surface-200 dark:bg-surface-700 rounded-full h-2 w-20">
                  <div
                    class="h-2 rounded-full transition-all duration-500"
                    :class="riskBarColor(supplier.score)"
                    :style="{ width: supplier.score + '%' }"
                  />
                </div>
                <span
                  class="px-2.5 py-1 rounded-full text-xs font-bold min-w-[70px] text-center"
                  :class="riskBadgeClass(supplier.score)"
                >
                  {{ supplier.score }}/100
                </span>
              </div>
            </td>
            <td class="px-6 py-4">
              <div class="flex flex-wrap gap-1">
                <span
                  v-for="dim in supplier.dimensions"
                  :key="dim.name"
                  class="px-2 py-0.5 rounded text-xs font-medium"
                  :class="dimensionClass(dim.score)"
                  :title="`${dim.name}: ${dim.score}/100`"
                >
                  {{ dim.name }}
                </span>
              </div>
            </td>
            <td class="px-6 py-4 text-sm text-surface-600 dark:text-surface-400">
              <span :class="isOverdue(supplier.lastAssessment) ? 'text-red-600 font-medium' : ''">
                {{ supplier.lastAssessment }}
              </span>
              <span v-if="isOverdue(supplier.lastAssessment)" class="block text-xs text-red-500">Réévaluation requise</span>
            </td>
            <td class="px-6 py-4 text-sm" @click.stop>
              <button
                @click="assessSupplier(supplier)"
                :disabled="assessing === supplier.id"
                class="px-3 py-1.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-xs font-medium disabled:opacity-50"
              >
                {{ assessing === supplier.id ? 'Évaluation...' : 'Évaluer' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Risk Detail Drawer -->
    <div
      v-if="selectedSupplier"
      class="fixed inset-y-0 right-0 w-96 bg-canvas dark:bg-surface-800 shadow-2xl z-40 overflow-y-auto"
    >
      <div class="p-6">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-xl font-bold text-surface-900 dark:text-surface-50">Profil de Risque</h2>
          <button
            @click="selectedSupplier = null"
            class="text-surface-500 hover:text-surface-700 dark:hover:text-surface-300"
          >
            <i class="pi pi-times text-lg" />
          </button>
        </div>

        <!-- Supplier Header -->
        <div class="flex items-center gap-3 mb-6">
          <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
            <span class="text-blue-600 dark:text-blue-300 font-bold text-lg">{{ selectedSupplier.name.charAt(0) }}</span>
          </div>
          <div>
            <div class="font-bold text-surface-900 dark:text-surface-50">{{ selectedSupplier.name }}</div>
            <div class="text-sm text-surface-500 dark:text-surface-400">{{ selectedSupplier.category }} • {{ selectedSupplier.country }}</div>
          </div>
        </div>

        <!-- Overall Score -->
        <div class="mb-6 p-4 rounded-xl" :class="riskPanelBg(selectedSupplier.score)">
          <div class="text-sm font-medium text-surface-600 dark:text-surface-400 mb-1">Score de risque global</div>
          <div class="text-4xl font-bold" :class="riskScoreColor(selectedSupplier.score)">
            {{ selectedSupplier.score }}<span class="text-lg">/100</span>
          </div>
          <div class="text-sm mt-1" :class="riskScoreColor(selectedSupplier.score)">
            {{ riskLevel(selectedSupplier.score) }}
          </div>
        </div>

        <!-- Dimension Breakdown -->
        <div class="mb-6">
          <h3 class="text-sm font-semibold text-surface-700 dark:text-surface-300 mb-3">Détail par dimension</h3>
          <div class="space-y-3">
            <div v-for="dim in selectedSupplier.dimensions" :key="dim.name">
              <div class="flex items-center justify-between text-sm mb-1">
                <span class="text-surface-600 dark:text-surface-400">{{ dim.name }}</span>
                <span class="font-medium" :class="riskScoreColor(dim.score)">{{ dim.score }}/100</span>
              </div>
              <div class="w-full bg-surface-200 dark:bg-surface-700 rounded-full h-1.5">
                <div
                  class="h-1.5 rounded-full"
                  :class="riskBarColor(dim.score)"
                  :style="{ width: dim.score + '%' }"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Alerts -->
        <div v-if="selectedSupplier.alerts && selectedSupplier.alerts.length" class="mb-6">
          <h3 class="text-sm font-semibold text-surface-700 dark:text-surface-300 mb-3">Alertes actives</h3>
          <div class="space-y-2">
            <div
              v-for="alert in selectedSupplier.alerts"
              :key="alert"
              class="flex items-start gap-2 text-sm p-2 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg"
            >
              <i class="pi pi-exclamation-triangle text-yellow-600 mt-0.5 text-xs flex-shrink-0" />
              <span class="text-surface-700 dark:text-surface-300">{{ alert }}</span>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex gap-3">
          <button
            @click="assessSupplier(selectedSupplier)"
            :disabled="assessing === selectedSupplier.id"
            class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium disabled:opacity-50"
          >
            {{ assessing === selectedSupplier.id ? 'Évaluation en cours...' : 'Lancer une évaluation' }}
          </button>
          <button class="px-4 py-2 border border-subtle rounded-lg hover:bg-surface-50 dark:hover:bg-surface-700 text-surface-700 dark:text-surface-300 text-sm">
            Rapport
          </button>
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
const selectedSupplier = ref(null)
const search = ref('')
const filterRisk = ref('')
const assessing = ref(null)

const stats = ref({
  highRisk: 2,
  activeAlerts: 7,
  criticalAlerts: 3,
  dueReassessments: 4,
  avgScore: 68
})

const suppliers = ref([
  {
    id: 1,
    name: 'Nairobi Consulting Ltd',
    category: 'Services professionnels',
    country: 'Kenya',
    score: 88,
    lastAssessment: '10 mai 2026',
    dimensions: [
      { name: 'Financier', score: 90 },
      { name: 'Livraison', score: 95 },
      { name: 'Qualité', score: 92 },
      { name: 'Géopolitique', score: 75 },
    ],
    alerts: []
  },
  {
    id: 2,
    name: 'Abidjan Tech Services',
    category: 'Services informatiques',
    country: 'Côte d\'Ivoire',
    score: 82,
    lastAssessment: '15 mai 2026',
    dimensions: [
      { name: 'Financier', score: 85 },
      { name: 'Livraison', score: 88 },
      { name: 'Qualité', score: 90 },
      { name: 'Géopolitique', score: 65 },
    ],
    alerts: ['Concentration de risque: 38% du CA sur un seul client']
  },
  {
    id: 3,
    name: 'Dakar Supplies SARL',
    category: 'Matières premières',
    country: 'Sénégal',
    score: 74,
    lastAssessment: '01 mai 2026',
    dimensions: [
      { name: 'Financier', score: 78 },
      { name: 'Livraison', score: 82 },
      { name: 'Qualité', score: 72 },
      { name: 'Géopolitique', score: 64 },
    ],
    alerts: ['Retard moyen en hausse: +2 jours vs T1 2026']
  },
  {
    id: 4,
    name: 'Accra Industrial Group',
    category: 'Équipements industriels',
    country: 'Ghana',
    score: 67,
    lastAssessment: '20 avril 2026',
    dimensions: [
      { name: 'Financier', score: 60 },
      { name: 'Livraison', score: 72 },
      { name: 'Qualité', score: 75 },
      { name: 'Géopolitique', score: 61 },
    ],
    alerts: ['Ratio endettement élevé signalé au dernier audit', 'Délai de paiement moyen: 52 jours']
  },
  {
    id: 5,
    name: 'TransAfrique Logistique',
    category: 'Logistique & Transport',
    country: 'Cameroun',
    score: 55,
    lastAssessment: '05 avril 2026',
    dimensions: [
      { name: 'Financier', score: 58 },
      { name: 'Livraison', score: 48 },
      { name: 'Qualité', score: 62 },
      { name: 'Géopolitique', score: 52 },
    ],
    alerts: ['3 incidents de livraison non conformes en avril', 'Doublon de facturation détecté (en investigation)']
  },
  {
    id: 6,
    name: 'BureauPlus Bamako',
    category: 'Fournitures de bureau',
    country: 'Mali',
    score: 48,
    lastAssessment: '10 mars 2026',
    dimensions: [
      { name: 'Financier', score: 42 },
      { name: 'Livraison', score: 55 },
      { name: 'Qualité', score: 50 },
      { name: 'Géopolitique', score: 45 },
    ],
    alerts: ['Situation financière fragile — dernier bilan déficitaire', 'Pays classé zone de risque géopolitique moyen']
  },
  {
    id: 7,
    name: 'Lomé Industries SA',
    category: 'Matières premières',
    country: 'Togo',
    score: 28,
    lastAssessment: '15 mars 2026',
    dimensions: [
      { name: 'Financier', score: 22 },
      { name: 'Livraison', score: 30 },
      { name: 'Qualité', score: 35 },
      { name: 'Géopolitique', score: 25 },
    ],
    alerts: [
      'Fournisseur suspendu — procédure de redressement judiciaire en cours',
      'Qualité non conforme sur 4 livraisons consécutives',
      'Risque géopolitique élevé — instabilité régionale signalée'
    ]
  },
  {
    id: 8,
    name: 'Casablanca Import Export',
    category: 'Matières premières',
    country: 'Maroc',
    score: 79,
    lastAssessment: '18 mai 2026',
    dimensions: [
      { name: 'Financier', score: 82 },
      { name: 'Livraison', score: 80 },
      { name: 'Qualité', score: 84 },
      { name: 'Géopolitique', score: 70 },
    ],
    alerts: []
  },
])

const filteredSuppliers = computed(() => {
  return suppliers.value.filter(s => {
    const matchSearch = !search.value ||
      s.name.toLowerCase().includes(search.value.toLowerCase()) ||
      s.category.toLowerCase().includes(search.value.toLowerCase())
    const matchRisk = !filterRisk.value ||
      (filterRisk.value === 'high' && s.score < 40) ||
      (filterRisk.value === 'medium' && s.score >= 40 && s.score <= 70) ||
      (filterRisk.value === 'low' && s.score > 70)
    return matchSearch && matchRisk
  })
})

const riskBarColor = (score) => {
  if (score > 70) return 'bg-green-500'
  if (score >= 40) return 'bg-orange-500'
  return 'bg-red-500'
}

const riskBadgeClass = (score) => {
  if (score > 70) return 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200'
  if (score >= 40) return 'bg-orange-100 dark:bg-orange-900 text-orange-800 dark:text-orange-200'
  return 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200'
}

const riskPanelBg = (score) => {
  if (score > 70) return 'bg-green-50 dark:bg-green-900/20'
  if (score >= 40) return 'bg-orange-50 dark:bg-orange-900/20'
  return 'bg-red-50 dark:bg-red-900/20'
}

const riskScoreColor = (score) => {
  if (score > 70) return 'text-green-700 dark:text-green-300'
  if (score >= 40) return 'text-orange-700 dark:text-orange-300'
  return 'text-red-700 dark:text-red-300'
}

const riskLevel = (score) => {
  if (score > 70) return 'Risque faible — fournisseur fiable'
  if (score >= 40) return 'Risque modéré — surveillance recommandée'
  return 'Risque élevé — action immédiate requise'
}

const dimensionClass = (score) => {
  if (score > 70) return 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300'
  if (score >= 40) return 'bg-orange-100 dark:bg-orange-900 text-orange-700 dark:text-orange-300'
  return 'bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300'
}

const isOverdue = (dateStr) => {
  // Flag as overdue if assessment is older than 30 days
  const parts = dateStr.split(' ')
  const months = { 'janvier': 0, 'février': 1, 'mars': 2, 'avril': 3, 'mai': 4, 'juin': 5 }
  const day = parseInt(parts[0])
  const month = months[parts[1].toLowerCase()] ?? 0
  const year = parseInt(parts[2])
  const assessDate = new Date(year, month, day)
  const today = new Date(2026, 4, 23) // reference date per project
  const diffDays = Math.floor((today - assessDate) / (1000 * 60 * 60 * 24))
  return diffDays > 30
}

const selectSupplier = (supplier) => {
  selectedSupplier.value = supplier
}

const assessSupplier = async (supplier) => {
  assessing.value = supplier.id
  try {
    await fetch('/api/v1/achats/supplier-risk/assess', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]').content}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ supplier_id: supplier.id })
    })
    // Simulate score update after assessment
    setTimeout(() => {
      supplier.lastAssessment = '23 mai 2026'
      assessing.value = null
      alert(`Évaluation de ${supplier.name} lancée. Le score sera mis à jour dans quelques instants.`)
    }, 1500)
  } catch (error) {
    console.error('Erreur évaluation fournisseur:', error)
    assessing.value = null
  }
}

const triggerBulkAssessment = async () => {
  if (!confirm('Lancer une réévaluation de l\'ensemble du portefeuille fournisseurs ?')) return
  alert('Réévaluation en masse lancée. Vous serez notifié à la fin du processus.')
}
</script>
