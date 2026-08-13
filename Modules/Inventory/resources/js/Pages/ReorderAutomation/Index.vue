<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'

interface ReorderRecommendation {
  product_id: number
  product_name: string
  sku: string
  current_stock: number
  reorder_point: number
  stock_below_point: boolean
  recommended_qty: number
  preferred_supplier: string
  estimated_cost: number
  lead_time_days: number
  seasonal_factor: number
  safety_stock: number
  eoq: number
  next_reorder_date: string
}

interface SeasonalFactor {
  name: string
  period: string
  factor: number
  active: boolean
}

const recommendations = ref<ReorderRecommendation[]>([])
const seasonalFactors = ref<SeasonalFactor[]>([])
const loading = ref(false)
const selectedTab = ref<'recommendations' | 'seasonal' | 'settings'>('recommendations')
const filterByStatus = ref<'all' | 'urgent' | 'scheduled'>('all')
const autoReorderEnabled = ref(true)

const urgentReorders = computed(() => {
  return recommendations.value.filter(r => r.stock_below_point)
})

const scheduledReorders = computed(() => {
  return recommendations.value.filter(r => !r.stock_below_point)
})

const filteredRecommendations = computed(() => {
  let filtered = recommendations.value

  if (filterByStatus.value === 'urgent') {
    filtered = filtered.filter(r => r.stock_below_point)
  } else if (filterByStatus.value === 'scheduled') {
    filtered = filtered.filter(r => !r.stock_below_point)
  }

  return filtered.sort((a, b) => {
    const aDate = new Date(a.next_reorder_date).getTime()
    const bDate = new Date(b.next_reorder_date).getTime()
    return aDate - bDate
  })
})

const totalRecommendedCost = computed(() => {
  return recommendations.value.reduce((sum, r) => sum + r.estimated_cost, 0)
})

const getStatusBadge = (stock: number, reorderPoint: number): string => {
  return stock <= reorderPoint ? 'Urgent' : 'Scheduled'
}

const getStatusColor = (stock: number, reorderPoint: number): string => {
  if (stock <= reorderPoint * 0.5) return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300'
  if (stock <= reorderPoint) return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300'
  return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
}

const formatCurrency = (amount: number): string => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'XOF',
    minimumFractionDigits: 0,
  }).format(amount)
}

const formatDate = (dateString: string): string => {
  const date = new Date(dateString)
  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
}

const getSeasonalIcon = (factor: number): string => {
  if (factor > 1.5) return '📈'
  if (factor > 1.1) return '↗️'
  if (factor < 0.9) return '📉'
  return '➡️'
}

const approveReorder = async (productId: number, qty: number) => {
  try {
    await axios.post('/api/v1/inventory/reorder/approve', {
      product_id: productId,
      quantity: qty,
    })
    // Reload recommendations after approval
    loadRecommendations()
  } catch (error) {
    console.error('Failed to approve reorder:', error)
  }
}

const loadRecommendations = async () => {
  loading.value = true
  try {
    const response = await axios.get('/api/v1/inventory/reorder/recommendations')
    recommendations.value = response.data.recommendations || []
  } catch (error) {
    console.error('Failed to load recommendations:', error)
  } finally {
    loading.value = false
  }
}

const loadSeasonalFactors = async () => {
  try {
    const response = await axios.get('/api/v1/inventory/reorder/seasonal-factors')
    seasonalFactors.value = response.data.factors || [
      {
        name: 'Ramadan',
        period: 'March 28 - April 27',
        factor: 1.8,
        active: true,
      },
      {
        name: 'School Year',
        period: 'September 1 - July 30',
        factor: 1.5,
        active: true,
      },
      {
        name: 'Harvest Season',
        period: 'November 1 - January 31',
        factor: 1.3,
        active: false,
      },
    ]
  } catch (error) {
    console.error('Failed to load seasonal factors:', error)
  }
}

const toggleAutoReorder = async (enabled: boolean) => {
  try {
    await axios.put('/api/v1/inventory/reorder/settings', {
      auto_reorder_enabled: enabled,
    })
    autoReorderEnabled.value = enabled
  } catch (error) {
    console.error('Failed to update auto-reorder setting:', error)
  }
}

onMounted(() => {
  loadRecommendations()
  loadSeasonalFactors()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div>
      <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Reorder Automation</h2>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Manage inventory reorder points and seasonal adjustments
      </p>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4 border border-red-200 dark:border-red-800">
        <p class="text-sm text-red-600 dark:text-red-400 font-medium">Urgent Reorders</p>
        <p class="text-3xl font-bold text-red-700 dark:text-red-300 mt-1">{{ urgentReorders.length }}</p>
      </div>

      <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
        <p class="text-sm text-blue-600 dark:text-blue-400 font-medium">Scheduled Reorders</p>
        <p class="text-3xl font-bold text-blue-700 dark:text-blue-300 mt-1">{{ scheduledReorders.length }}</p>
      </div>

      <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 border border-green-200 dark:border-green-800">
        <p class="text-sm text-green-600 dark:text-green-400 font-medium">Total Cost</p>
        <p class="text-2xl font-bold text-green-700 dark:text-green-300 mt-1">
          {{ formatCurrency(totalRecommendedCost) }}
        </p>
      </div>

      <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4 border border-purple-200 dark:border-purple-800">
        <p class="text-sm text-purple-600 dark:text-purple-400 font-medium">Auto Reorder</p>
        <p class="text-lg font-bold text-purple-700 dark:text-purple-300 mt-1">
          {{ autoReorderEnabled ? '✅ Enabled' : '⏸ Disabled' }}
        </p>
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
          Recommendations
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
          Seasonal Factors
        </button>
        <button
          @click="selectedTab = 'settings'"
          :class="[
            'flex-1 px-6 py-3 text-center font-medium transition',
            selectedTab === 'settings'
              ? 'text-blue-600 dark:text-blue-400 border-b-2 border-blue-600 dark:border-blue-400'
              : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-300',
          ]"
        >
          Settings
        </button>
      </div>

      <!-- Tab Content -->
      <div class="p-6">
        <!-- Recommendations Tab -->
        <div v-if="selectedTab === 'recommendations'" class="space-y-4">
          <div class="flex justify-between items-center mb-4">
            <div>
              <label for="filter-by-status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Filter by Status
              </label>
              <select id="filter-by-status"
                v-model="filterByStatus"
                class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
              >
                <option value="all">All Items</option>
                <option value="urgent">Urgent Only</option>
                <option value="scheduled">Scheduled Only</option>
              </select>
            </div>
          </div>

          <div v-if="loading" class="flex justify-center py-8">
            <div class="animate-spin h-8 w-8 text-blue-600">
              <svg class="animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
            </div>
          </div>

          <div v-else-if="filteredRecommendations.length === 0" class="text-center py-8">
            <p class="text-gray-500 dark:text-gray-400">No reorder recommendations</p>
          </div>

          <div v-else class="space-y-3">
            <div
              v-for="rec in filteredRecommendations"
              :key="rec.product_id"
              class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-md transition"
            >
              <div class="flex items-start justify-between mb-3">
                <div class="flex-1">
                  <h3 class="font-semibold text-gray-900 dark:text-white">{{ rec.product_name }}</h3>
                  <p class="text-sm text-gray-600 dark:text-gray-400">SKU: {{ rec.sku }}</p>
                </div>
                <span
                  :class="[
                    'inline-flex items-center px-3 py-1 rounded-full text-sm font-medium',
                    getStatusColor(rec.current_stock, rec.reorder_point),
                  ]"
                >
                  {{ getStatusBadge(rec.current_stock, rec.reorder_point) }}
                </span>
              </div>

              <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-3 text-sm">
                <div>
                  <p class="text-gray-600 dark:text-gray-400 text-xs">Current Stock</p>
                  <p class="font-semibold text-gray-900 dark:text-white">{{ rec.current_stock }}</p>
                </div>
                <div>
                  <p class="text-gray-600 dark:text-gray-400 text-xs">Reorder Point</p>
                  <p class="font-semibold text-gray-900 dark:text-white">{{ rec.reorder_point }}</p>
                </div>
                <div>
                  <p class="text-gray-600 dark:text-gray-400 text-xs">Recommended Qty</p>
                  <p class="font-semibold text-gray-900 dark:text-white">{{ rec.recommended_qty }}</p>
                </div>
                <div>
                  <p class="text-gray-600 dark:text-gray-400 text-xs">Est. Cost</p>
                  <p class="font-semibold text-gray-900 dark:text-white">
                    {{ formatCurrency(rec.estimated_cost) }}
                  </p>
                </div>
              </div>

              <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4 text-sm">
                <div>
                  <p class="text-gray-600 dark:text-gray-400 text-xs">Supplier</p>
                  <p class="font-semibold text-gray-900 dark:text-white">{{ rec.preferred_supplier }}</p>
                </div>
                <div>
                  <p class="text-gray-600 dark:text-gray-400 text-xs">Lead Time</p>
                  <p class="font-semibold text-gray-900 dark:text-white">{{ rec.lead_time_days }} days</p>
                </div>
                <div>
                  <p class="text-gray-600 dark:text-gray-400 text-xs">Seasonal Factor</p>
                  <p class="font-semibold text-gray-900 dark:text-white">{{ rec.seasonal_factor }}x</p>
                </div>
                <div>
                  <p class="text-gray-600 dark:text-gray-400 text-xs">Next Reorder</p>
                  <p class="font-semibold text-gray-900 dark:text-white">{{ formatDate(rec.next_reorder_date) }}</p>
                </div>
              </div>

              <div class="flex gap-2">
                <button
                  @click="approveReorder(rec.product_id, rec.recommended_qty)"
                  class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition"
                >
                  ✓ Approve Reorder
                </button>
                <button
                  class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                >
                  📋 View Details
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Seasonal Factors Tab -->
        <div v-if="selectedTab === 'seasonal'" class="space-y-4">
          <div v-if="seasonalFactors.length === 0" class="text-center py-8">
            <p class="text-gray-500 dark:text-gray-400">No seasonal factors configured</p>
          </div>

          <div v-else class="space-y-3">
            <div
              v-for="(factor, idx) in seasonalFactors"
              :key="idx"
              class="border border-gray-200 dark:border-gray-700 rounded-lg p-4"
            >
              <div class="flex items-start justify-between mb-2">
                <div class="flex-1">
                  <h3 class="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                    {{ getSeasonalIcon(factor.factor) }}
                    {{ factor.name }}
                  </h3>
                  <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ factor.period }}</p>
                </div>
                <span
                  :class="[
                    'inline-flex items-center px-3 py-1 rounded-full text-sm font-medium',
                    factor.active
                      ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
                      : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                  ]"
                >
                  {{ factor.active ? '✓ Active' : 'Inactive' }}
                </span>
              </div>

              <div class="bg-gray-100 dark:bg-gray-700 rounded p-3 text-center">
                <p class="text-xs text-gray-600 dark:text-gray-400">Demand Multiplier</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ factor.factor }}x</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Settings Tab -->
        <div v-if="selectedTab === 'settings'" class="space-y-4">
          <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="font-semibold text-gray-900 dark:text-white">Auto-generate Purchase Orders</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                  Automatically create POs when stock falls below reorder point
                </p>
              </div>
              <button
                @click="toggleAutoReorder(!autoReorderEnabled)"
                :class="[
                  'relative inline-flex h-6 w-11 items-center rounded-full transition',
                  autoReorderEnabled
                    ? 'bg-blue-600'
                    : 'bg-gray-300 dark:bg-gray-600',
                ]"
              >
                <span
                  :class="[
                    'inline-block h-4 w-4 transform rounded-full bg-white transition',
                    autoReorderEnabled ? 'translate-x-6' : 'translate-x-1',
                  ]"
                ></span>
              </button>
            </div>
          </div>

          <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <p class="text-sm text-blue-800 dark:text-blue-300">
              💡 <strong>Tip:</strong> Seasonal factors are applied to recommended quantities during peak periods like Ramadan and school year to prevent stockouts.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
