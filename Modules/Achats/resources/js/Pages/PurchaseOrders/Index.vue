<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">Purchase Orders</h1>
        <p class="mt-2 text-surface-600 dark:text-surface-400">Create and manage purchase orders with suppliers</p>
      </div>
      <div class="flex gap-2">
        <button
          @click="showAiPanel = !showAiPanel"
          class="px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg hover:bg-gray-50 dark:hover:bg-surface-700 text-surface-700 dark:text-surface-300"
          title="Assistant IA"
        >
          <i class="pi pi-sparkles" />
        </button>
        <Link href="/purchase-orders/create" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
          + New Purchase Order
        </Link>
      </div>
    </div>

    <AIAssistantPanel v-if="showAiPanel" :guidance="guidance" @close="showAiPanel = false" />

    <div class="bg-canvas dark:bg-surface-800 rounded-lg shadow">
      <div class="p-6 border-b border-subtle">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div class="relative">
            <input
              v-model="search"
              type="text"
              placeholder="Search by PO number or supplier..."
              class="w-full px-4 py-2 border border-subtle rounded-lg focus:ring-blue-500 focus:border-transparent"
            />
          </div>
          <select
            v-model="filters.status"
            class="px-4 py-2 border border-subtle rounded-lg focus:ring-blue-500"
          >
            <option value="">All Statuses</option>
            <option value="draft">Draft</option>
            <option value="submitted">Submitted</option>
            <option value="approved">Approved</option>
            <option value="received">Received</option>
            <option value="invoiced">Invoiced</option>
            <option value="cancelled">Cancelled</option>
          </select>
          <select
            v-model="filters.currency"
            class="px-4 py-2 border border-subtle rounded-lg focus:ring-blue-500"
          >
            <option value="">All Currencies</option>
            <option value="USD">USD</option>
            <option value="EUR">EUR</option>
            <option value="GBP">GBP</option>
            <option value="JPY">JPY</option>
          </select>
          <button
            @click="loadPurchaseOrders"
            class="px-4 py-2 bg-surface-200 dark:bg-surface-700 text-surface-700 dark:text-surface-300 rounded-lg hover:bg-surface-300"
          >
            Search
          </button>
        </div>
      </div>

      <table class="w-full">
        <thead class="bg-surface-50 dark:bg-surface-800 border-b border-subtle">
          <tr>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">PO Number</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Supplier</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Order Date</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Total</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Currency</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Status</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!loading && purchaseOrders.length === 0" class="border-b border-subtle">
            <td colspan="7" class="px-6 py-8 text-center text-surface-500 dark:text-surface-400">
              No purchase orders found. <Link href="/purchase-orders/create" class="text-primary-700 dark:text-primary-300 hover:underline">Create one now</Link>
            </td>
          </tr>
          <tr v-for="po in purchaseOrders" :key="po.id" class="border-b border-subtle hover:bg-surface-50 dark:hover:bg-surface-700">
            <td class="px-6 py-4 text-sm font-mono font-medium text-surface-900 dark:text-surface-50">{{ po.po_number }}</td>
            <td class="px-6 py-4 text-sm text-surface-600 dark:text-surface-400">{{ po.supplier?.name || 'Unknown' }}</td>
            <td class="px-6 py-4 text-sm text-surface-600 dark:text-surface-400">{{ formatDate(po.order_date) }}</td>
            <td class="px-6 py-4 text-sm font-medium text-surface-900 dark:text-surface-50">{{ po.total }}</td>
            <td class="px-6 py-4 text-sm text-surface-600 dark:text-surface-400">{{ po.currency }}</td>
            <td class="px-6 py-4 text-sm">
              <span
                :class="[
                  'px-3 py-1 rounded-full text-xs font-medium',
                  statusClasses[po.status] || 'bg-surface-100 dark:bg-surface-700 text-surface-900 dark:text-surface-100'
                ]"
              >
                {{ formatStatus(po.status) }}
              </span>
            </td>
            <td class="px-6 py-4 text-sm space-x-2">
              <Link :href="`/purchase-orders/${po.id}`" class="text-primary-700 dark:text-primary-300 hover:underline">
                View
              </Link>
              <Link v-if="po.status === 'draft'" :href="`/purchase-orders/${po.id}/edit`" class="text-primary-700 dark:text-primary-300 hover:underline">
                Edit
              </Link>
              <button
                v-if="po.status === 'draft'"
                @click="submitForApproval(po.id)"
                class="text-green-700 dark:text-green-300 hover:underline"
              >
                Submit
              </button>
              <button
                v-if="po.status === 'draft'"
                @click="deletePurchaseOrder(po.id)"
                class="text-red-700 dark:text-red-300 hover:underline"
              >
                Delete
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="pagination" class="flex items-center justify-between">
      <div class="text-sm text-surface-600 dark:text-surface-400">
        Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} purchase orders
      </div>
      <div class="space-x-2">
        <button
          v-if="pagination.current_page > 1"
          @click="currentPage = pagination.current_page - 1; loadPurchaseOrders()"
          class="px-4 py-2 border border-subtle rounded-lg hover:bg-surface-50 dark:hover:bg-surface-700"
        >
          Previous
        </button>
        <button
          v-if="pagination.current_page < pagination.last_page"
          @click="currentPage = pagination.current_page + 1; loadPurchaseOrders()"
          class="px-4 py-2 border border-subtle rounded-lg hover:bg-surface-50 dark:hover:bg-surface-700"
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
// Chantier 32.13 (layer 13 — AI): the only Achats page that ever called
// useAiAssistant() was SpendAnalytics/Index.vue — every other real screen,
// including this one, had zero contextual guidance despite
// AiContextualAssistantService already carrying real, grounded fr+en
// fallback text for 'create_order' (matches this exact list-and-create
// screen). Same pattern already found and fixed for Strategy at Chantier 30.
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'

const { guidance } = useAiAssistant('Achats', 'create_order')
const showAiPanel = ref(false)

const purchaseOrders = ref([])
const loading = ref(false)
const pagination = ref(null)
const search = ref('')
const currentPage = ref(1)
const filters = ref({
  status: '',
  currency: ''
})

const statusClasses = {
  draft: 'bg-surface-100 dark:bg-surface-700 text-surface-900 dark:text-surface-100',
  submitted: 'bg-yellow-100 text-yellow-800',
  approved: 'bg-blue-100 text-blue-800',
  received: 'bg-purple-100 text-purple-800',
  invoiced: 'bg-green-100 text-green-800',
  cancelled: 'bg-red-100 text-red-800'
}

const formatStatus = (status) => {
  return status.charAt(0).toUpperCase() + status.slice(1)
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

const loadPurchaseOrders = async () => {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      per_page: 15,
      search: search.value,
    }
    // Chantier 19: was reading `filters.status`/`filters.currency` directly
    // on the ref object instead of `filters.value.status`/`.currency` —
    // Ref has no such properties, so both were always undefined and the
    // status/currency dropdowns silently had no effect on the list at all.
    if (filters.value.status) {
      params.status = filters.value.status
    }
    if (filters.value.currency) {
      params.currency = filters.value.currency
    }

    const { data } = await axios.get('/api/v1/achats/purchase-orders', { params })
    purchaseOrders.value = data.data
    pagination.value = data.meta
  } catch (error) {
    console.error('Failed to load purchase orders:', error)
  } finally {
    loading.value = false
  }
}

const submitForApproval = async (id) => {
  if (!confirm('Submit this purchase order for approval?')) return

  try {
    await axios.post(`/api/v1/achats/purchase-orders/${id}/submit`)
    loadPurchaseOrders()
  } catch (error) {
    console.error('Failed to submit PO:', error)
  }
}

const deletePurchaseOrder = async (id) => {
  if (!confirm('Are you sure you want to delete this purchase order?')) return

  try {
    await axios.delete(`/api/v1/achats/purchase-orders/${id}`)
    loadPurchaseOrders()
  } catch (error) {
    console.error('Failed to delete purchase order:', error)
  }
}

onMounted(() => {
  loadPurchaseOrders()
})
</script>
