<script setup lang="ts">
import { ref, onMounted, computed, reactive } from 'vue'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

interface LowStockItem {
  product_id: number
  product_name: string
  quantity: number
  reorder_level: number
  warehouse_id: number
}

interface Suggestion {
  product_id: number
  action: 'reorder' | 'watch' | 'ok'
  suggested_qty: number
  urgency: 'urgent' | 'soon' | 'normal'
  reason: string
}

interface SeasonalFactor {
  id: number
  product_id: number | null
  category_id: number | null
  period_type: string
  period_index: number
  factor: number
  notes: string | null
  product?: { name: string } | null
  category?: { name: string } | null
}

const lowStockItems = ref<LowStockItem[]>([])
const suggestions = ref<Suggestion[]>([])
const suggestionsLoading = ref(false)
const suggestionsError = ref<string | null>(null)

const seasonalFactors = ref<SeasonalFactor[]>([])
const seasonalLoading = ref(false)
const seasonalError = ref<string | null>(null)

const selectedTab = ref<'recommendations' | 'seasonal'>('recommendations')

const suggestionsByProduct = computed(() => {
  const map = new Map<number, Suggestion>()
  suggestions.value.forEach(s => map.set(s.product_id, s))
  return map
})

const rows = computed(() => {
  return lowStockItems.value.map(item => ({
    ...item,
    suggestion: suggestionsByProduct.value.get(item.product_id) ?? null,
  }))
})

const urgentCount = computed(() => suggestions.value.filter(s => s.urgency === 'urgent').length)
const soonCount = computed(() => suggestions.value.filter(s => s.urgency === 'soon').length)

const getUrgencyColor = (urgency?: string): string => {
  if (urgency === 'urgent') return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300'
  if (urgency === 'soon') return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300'
  return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
}

const loadSuggestions = async () => {
  suggestionsLoading.value = true
  suggestionsError.value = null
  try {
    const { data } = await axios.get('/api/v1/inventory/low-stock')
    lowStockItems.value = data.low_stock_items ?? []

    if (lowStockItems.value.length) {
      const { data: suggestData } = await axios.post('/api/v1/inventory/ai/suggest-reorder', {
        stock: lowStockItems.value,
      })
      suggestions.value = suggestData.suggestions ?? []
    } else {
      suggestions.value = []
    }
  } catch (error) {
    suggestionsError.value = 'Impossible de charger les suggestions de réapprovisionnement.'
  } finally {
    suggestionsLoading.value = false
  }
}

const loadSeasonalFactors = async () => {
  seasonalLoading.value = true
  seasonalError.value = null
  try {
    const { data } = await axios.get('/api/v1/inventory/seasonal-factors', { params: { per_page: 50 } })
    seasonalFactors.value = data.data ?? []
  } catch (error) {
    seasonalError.value = 'Impossible de charger les facteurs saisonniers.'
  } finally {
    seasonalLoading.value = false
  }
}

const newFactor = reactive({
  product_id: null as number | null,
  period_type: 'monthly',
  period_index: 1,
  factor: 1,
  notes: '',
})
const savingFactor = ref(false)
const factorErrors = reactive<Record<string, string>>({})

const saveFactor = async () => {
  savingFactor.value = true
  Object.keys(factorErrors).forEach(k => delete factorErrors[k])
  try {
    await axios.post('/api/v1/inventory/seasonal-factors', newFactor)
    newFactor.product_id = null
    newFactor.period_index = 1
    newFactor.factor = 1
    newFactor.notes = ''
    await loadSeasonalFactors()
  } catch (e: any) {
    if (e.response?.data?.errors) {
      Object.assign(factorErrors, e.response.data.errors)
    }
  } finally {
    savingFactor.value = false
  }
}

onMounted(() => {
  loadSuggestions()
  loadSeasonalFactors()
})
</script>

<template>
  <AppLayout>
  <div class="space-y-6">
    <!-- Header -->
    <div>
      <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Réapprovisionnement automatique</h2>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Suggestions de réapprovisionnement générées par IA et facteurs saisonniers
      </p>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4 border border-red-200 dark:border-red-800">
        <p class="text-sm text-red-600 dark:text-red-400 font-medium">Urgent</p>
        <p class="text-3xl font-bold text-red-700 dark:text-red-300 mt-1">{{ urgentCount }}</p>
      </div>
      <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4 border border-yellow-200 dark:border-yellow-800">
        <p class="text-sm text-yellow-600 dark:text-yellow-400 font-medium">À prévoir bientôt</p>
        <p class="text-3xl font-bold text-yellow-700 dark:text-yellow-300 mt-1">{{ soonCount }}</p>
      </div>
      <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
        <p class="text-sm text-blue-600 dark:text-blue-400 font-medium">Produits en stock bas</p>
        <p class="text-3xl font-bold text-blue-700 dark:text-blue-300 mt-1">{{ lowStockItems.length }}</p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
      <div class="flex border-b border-gray-200 dark:border-gray-700">
        <button
          @click="selectedTab = 'recommendations'"
          :class="[
            'flex-1 px-6 py-3 text-center font-medium transition',
            selectedTab === 'recommendations'
              ? 'text-blue-600 dark:text-blue-400 border-b-2 border-blue-600 dark:border-blue-400'
              : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-300',
          ]"
        >
          Suggestions IA
        </button>
        <button
          @click="selectedTab = 'seasonal'"
          :class="[
            'flex-1 px-6 py-3 text-center font-medium transition',
            selectedTab === 'seasonal'
              ? 'text-blue-600 dark:text-blue-400 border-b-2 border-blue-600 dark:border-blue-400'
              : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-300',
          ]"
        >
          Facteurs saisonniers
        </button>
      </div>

      <!-- Tab Content -->
      <div class="p-6">
        <!-- Recommendations Tab -->
        <div v-if="selectedTab === 'recommendations'" class="space-y-4">
          <div v-if="suggestionsLoading" class="text-center py-8 text-gray-400">Chargement…</div>
          <div v-else-if="suggestionsError" class="text-center py-8 text-red-600">{{ suggestionsError }}</div>
          <div v-else-if="rows.length === 0" class="text-center py-8">
            <p class="text-gray-500 dark:text-gray-400">Aucun produit en stock bas</p>
          </div>
          <div v-else class="space-y-3">
            <div
              v-for="row in rows"
              :key="row.product_id"
              class="border border-gray-200 dark:border-gray-700 rounded-lg p-4"
            >
              <div class="flex items-start justify-between mb-3">
                <div class="flex-1">
                  <h3 class="font-semibold text-gray-900 dark:text-white">{{ row.product_name }}</h3>
                  <p class="text-sm text-gray-600 dark:text-gray-400">
                    Stock actuel : {{ row.quantity }} · Seuil : {{ row.reorder_level }}
                  </p>
                </div>
                <span
                  v-if="row.suggestion"
                  :class="['inline-flex items-center px-3 py-1 rounded-full text-sm font-medium', getUrgencyColor(row.suggestion.urgency)]"
                >
                  {{ row.suggestion.urgency }}
                </span>
              </div>

              <div v-if="row.suggestion" class="text-sm space-y-1">
                <p><strong>Quantité suggérée :</strong> {{ row.suggestion.suggested_qty }}</p>
                <p class="text-gray-600 dark:text-gray-400">{{ row.suggestion.reason }}</p>
              </div>
              <p v-else class="text-sm text-gray-400">Aucune suggestion IA disponible pour ce produit.</p>
            </div>
          </div>
        </div>

        <!-- Seasonal Factors Tab -->
        <div v-if="selectedTab === 'seasonal'" class="space-y-4">
          <form class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 grid grid-cols-2 md:grid-cols-5 gap-3 items-end" @submit.prevent="saveFactor">
            <div>
              <label class="block text-xs text-gray-500 mb-1">ID Produit</label>
              <input v-model.number="newFactor.product_id" type="number" class="w-full border rounded px-2 py-1 text-sm" />
              <small v-if="factorErrors.product_id" class="text-red-500">{{ factorErrors.product_id }}</small>
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">Période</label>
              <select v-model="newFactor.period_type" class="w-full border rounded px-2 py-1 text-sm">
                <option value="monthly">Mensuelle</option>
                <option value="quarterly">Trimestrielle</option>
                <option value="yearly">Annuelle</option>
              </select>
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">Index (1-12)</label>
              <input v-model.number="newFactor.period_index" type="number" min="1" max="12" class="w-full border rounded px-2 py-1 text-sm" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">Facteur</label>
              <input v-model.number="newFactor.factor" type="number" step="0.1" min="0.1" max="10" class="w-full border rounded px-2 py-1 text-sm" />
            </div>
            <button type="submit" :disabled="savingFactor" class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition">
              Ajouter
            </button>
          </form>

          <div v-if="seasonalLoading" class="text-center py-8 text-gray-400">Chargement…</div>
          <div v-else-if="seasonalError" class="text-center py-8 text-red-600">{{ seasonalError }}</div>
          <div v-else-if="seasonalFactors.length === 0" class="text-center py-8">
            <p class="text-gray-500 dark:text-gray-400">Aucun facteur saisonnier configuré</p>
          </div>
          <div v-else class="space-y-3">
            <div
              v-for="factor in seasonalFactors"
              :key="factor.id"
              class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 flex items-center justify-between"
            >
              <div>
                <h3 class="font-semibold text-gray-900 dark:text-white">
                  {{ factor.product?.name ?? factor.category?.name ?? `Produit #${factor.product_id}` }}
                </h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                  {{ factor.period_type }} · période {{ factor.period_index }}
                </p>
              </div>
              <div class="bg-gray-100 dark:bg-gray-700 rounded px-3 py-2 text-center">
                <p class="text-xs text-gray-600 dark:text-gray-400">Multiplicateur</p>
                <p class="text-xl font-bold text-gray-900 dark:text-white">{{ factor.factor }}x</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  </AppLayout>
</template>
