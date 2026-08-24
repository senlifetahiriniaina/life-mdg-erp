<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">Purchase Receipts</h1>
        <p class="mt-2 text-surface-600 dark:text-surface-400">Track and manage received goods</p>
      </div>
      <div class="flex gap-2">
        <button
          @click="showAiPanel = !showAiPanel"
          class="px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg hover:bg-gray-50 dark:hover:bg-surface-700 text-surface-700 dark:text-surface-300"
          title="Assistant IA"
        >
          <i class="pi pi-sparkles" />
        </button>
        <Link href="/purchase-receipts/create" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
          + New Receipt
        </Link>
      </div>
    </div>

    <AIAssistantPanel v-if="showAiPanel" :guidance="guidance" @close="showAiPanel = false" />

    <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow">
      <div class="p-6 border-b border-gray-200 dark:border-surface-700">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div class="relative">
            <input
              v-model="search"
              type="text"
              placeholder="Search by receipt or PO number..."
              class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
            />
          </div>
          <select
            v-model="filters.status"
            class="px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500"
          >
            <option value="">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="partial">Partial</option>
            <option value="completed">Completed</option>
            <option value="issues">Issues</option>
          </select>
          <select
            v-model="filters.has_issues"
            class="px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500"
          >
            <option value="">All Receipts</option>
            <option value="1">With Issues</option>
            <option value="0">No Issues</option>
          </select>
          <button
            @click="loadReceipts"
            class="px-4 py-2 bg-gray-200 text-surface-700 dark:text-surface-300 rounded-lg hover:bg-gray-300"
          >
            Search
          </button>
        </div>
      </div>

      <table class="w-full">
        <thead class="bg-gray-50 dark:bg-surface-800 dark:bg-surface-800 border-b border-gray-200 dark:border-surface-700">
          <tr>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Receipt #</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">PO Number</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Supplier</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Receipt Date</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Items Received</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Quality Issues</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Status</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!loading && receipts.length === 0" class="border-b border-gray-200 dark:border-surface-700">
            <td colspan="8" class="px-6 py-8 text-center text-surface-500 dark:text-surface-400">
              No receipts found. <Link href="/purchase-receipts/create" class="text-primary-700 dark:text-primary-300 hover:underline">Create one now</Link>
            </td>
          </tr>
          <tr v-for="receipt in receipts" :key="receipt.id" class="border-b border-gray-200 dark:border-surface-700 hover:bg-gray-50 dark:bg-surface-800 dark:bg-surface-800">
            <td class="px-6 py-4 text-sm font-mono font-medium text-surface-900 dark:text-surface-50">{{ receipt.receipt_number }}</td>
            <td class="px-6 py-4 text-sm font-mono text-surface-600 dark:text-surface-400">{{ receipt.purchase_order?.po_number || 'N/A' }}</td>
            <td class="px-6 py-4 text-sm text-surface-600 dark:text-surface-400">{{ receipt.purchase_order?.supplier?.name || 'Unknown' }}</td>
            <td class="px-6 py-4 text-sm text-surface-600 dark:text-surface-400">{{ formatDate(receipt.receipt_date) }}</td>
            <td class="px-6 py-4 text-sm text-surface-600 dark:text-surface-400">{{ receipt.lines_count || 0 }}</td>
            <td class="px-6 py-4 text-sm">
              <span
                v-if="receipt.has_quality_issues"
                class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800"
              >
                {{ receipt.quality_issues_count || 0 }}
              </span>
              <span v-else class="text-surface-500 dark:text-surface-400">None</span>
            </td>
            <td class="px-6 py-4 text-sm">
              <span
                :class="[
                  'px-3 py-1 rounded-full text-xs font-medium',
                  statusClasses[receipt.status] || 'bg-gray-100 dark:bg-surface-700 text-gray-800 dark:text-surface-100'
                ]"
              >
                {{ formatStatus(receipt.status) }}
              </span>
            </td>
            <td class="px-6 py-4 text-sm space-x-2">
              <Link :href="`/purchase-receipts/${receipt.id}`" class="text-primary-700 dark:text-primary-300 hover:underline">
                View
              </Link>
              <Link
                v-if="receipt.status !== 'completed'"
                :href="`/purchase-receipts/${receipt.id}/edit`"
                class="text-primary-700 dark:text-primary-300 hover:underline"
              >
                Edit
              </Link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="pagination" class="flex items-center justify-between">
      <div class="text-sm text-surface-600 dark:text-surface-400">
        Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} receipts
      </div>
      <div class="space-x-2">
        <button
          v-if="pagination.current_page > 1"
          @click="currentPage = pagination.current_page - 1; loadReceipts()"
          class="px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg hover:bg-gray-50 dark:bg-surface-800 dark:bg-surface-800"
        >
          Previous
        </button>
        <button
          v-if="pagination.current_page < pagination.last_page"
          @click="currentPage = pagination.current_page + 1; loadReceipts()"
          class="px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg hover:bg-gray-50 dark:bg-surface-800 dark:bg-surface-800"
        >
          Next
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'
// Chantier 32.13 (layer 13 — AI): 'receive_goods' — an exact fit, real
// grounded fr/en guidance already existed in AiContextualAssistantService
// but was never surfaced on this page.
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'

const { guidance } = useAiAssistant('Achats', 'receive_goods')
const showAiPanel = ref(false)

const receipts = ref([])
const loading = ref(false)
const pagination = ref(null)
const search = ref('')
const currentPage = ref(1)
const filters = ref({
  status: '',
  has_issues: ''
})

const statusClasses = {
  pending: 'bg-yellow-100 text-yellow-800',
  partial: 'bg-blue-100 text-blue-800',
  completed: 'bg-green-100 text-green-800',
  issues: 'bg-red-100 text-red-800'
}

const formatStatus = (status) => {
  const statusMap = {
    pending: 'Pending',
    partial: 'Partial',
    completed: 'Completed',
    issues: 'Issues'
  }
  return statusMap[status] || status
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

const loadReceipts = async () => {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      per_page: 15,
      search: search.value,
    }
    // Chantier 19: was reading `filters.status`/`filters.has_issues` on the
    // ref object itself instead of `filters.value.*` — always undefined, so
    // neither dropdown ever actually filtered the list.
    if (filters.value.status) {
      params.status = filters.value.status
    }
    if (filters.value.has_issues) {
      params.has_issues = filters.value.has_issues
    }

    const { data } = await axios.get('/api/v1/achats/purchase-receipts', { params })
    receipts.value = data.data
    pagination.value = data.meta
  } catch (error) {
    console.error('Failed to load receipts:', error)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadReceipts()
})
</script>
