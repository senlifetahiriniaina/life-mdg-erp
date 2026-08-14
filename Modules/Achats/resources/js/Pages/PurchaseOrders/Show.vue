<template>
  <div class="max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">Purchase Order</h1>
        <p class="mt-2 text-surface-600 dark:text-surface-400">{{ po.po_number }}</p>
      </div>
      <Link href="/purchase-orders" class="text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:text-surface-50">
        ← Back to Purchase Orders
      </Link>
    </div>

    <div v-if="loading" class="text-center py-8">
      <p class="text-surface-600 dark:text-surface-400">Loading...</p>
    </div>

    <div v-else-if="po.id" class="space-y-6">
      <!-- Status Bar -->
      <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Current Status</h2>
            <div class="flex items-center gap-2">
              <span
                :class="[
                  'px-4 py-2 rounded-lg text-sm font-medium',
                  statusClasses[po.status] || 'bg-gray-100 dark:bg-surface-700 text-gray-800 dark:text-surface-100'
                ]"
              >
                {{ formatStatus(po.status) }}
              </span>
            </div>
          </div>
          <div class="flex gap-2">
            <button
              v-if="po.status === 'draft'"
              @click="submitForApproval"
              class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm"
            >
              Submit for Approval
            </button>
            <button
              v-if="po.status === 'approved'"
              @click="markAsReceived"
              class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm"
            >
              Mark as Received
            </button>
            <button
              v-if="po.status === 'draft'"
              @click="deletePO"
              class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm"
            >
              Delete
            </button>
          </div>
        </div>
      </div>

      <!-- Order Details -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Order Information</h3>
          <div class="space-y-3">
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">PO Number</p>
              <p class="text-lg font-medium text-surface-900 dark:text-surface-50">{{ po.po_number }}</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Order Date</p>
              <p class="text-base text-surface-900 dark:text-surface-50">{{ formatDate(po.order_date) }}</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Delivery Date</p>
              <p class="text-base text-surface-900 dark:text-surface-50">{{ po.delivery_date ? formatDate(po.delivery_date) : 'Not specified' }}</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Currency</p>
              <p class="text-base text-surface-900 dark:text-surface-50">{{ po.currency }}</p>
            </div>
          </div>
        </div>

        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Supplier Information</h3>
          <div class="space-y-3">
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Supplier</p>
              <p class="text-lg font-medium text-surface-900 dark:text-surface-50">{{ po.supplier?.name || 'Unknown' }}</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Contact Email</p>
              <p class="text-base text-surface-900 dark:text-surface-50">{{ po.supplier?.email || 'N/A' }}</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Contact Phone</p>
              <p class="text-base text-surface-900 dark:text-surface-50">{{ po.supplier?.phone || 'N/A' }}</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Payment Terms</p>
              <p class="text-base text-surface-900 dark:text-surface-50">{{ po.supplier?.payment_terms || '30' }} days</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Line Items -->
      <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Line Items</h3>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-surface-800 dark:bg-surface-800 border-b border-gray-200 dark:border-surface-700">
              <tr>
                <th scope="col" class="px-4 py-3 text-left font-medium text-surface-700 dark:text-surface-300">Description</th>
                <th scope="col" class="px-4 py-3 text-left font-medium text-surface-700 dark:text-surface-300 w-20">Quantity</th>
                <th scope="col" class="px-4 py-3 text-left font-medium text-surface-700 dark:text-surface-300 w-20">Unit</th>
                <th scope="col" class="px-4 py-3 text-left font-medium text-surface-700 dark:text-surface-300 w-24">Unit Price</th>
                <th scope="col" class="px-4 py-3 text-left font-medium text-surface-700 dark:text-surface-300 w-16">Tax %</th>
                <th scope="col" class="px-4 py-3 text-right font-medium text-surface-700 dark:text-surface-300 w-24">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="line in po.lines" :key="line.id" class="border-b border-gray-200 dark:border-surface-700">
                <td class="px-4 py-3">{{ line.description }}</td>
                <td class="px-4 py-3">{{ line.quantity }}</td>
                <td class="px-4 py-3">{{ line.unit }}</td>
                <td class="px-4 py-3">{{ line.unit_price }}</td>
                <td class="px-4 py-3">{{ line.tax_rate }}%</td>
                <td class="px-4 py-3 text-right font-medium">{{ line.line_total }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Totals -->
      <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
        <div class="flex justify-end max-w-md ml-auto space-y-2">
          <div class="flex justify-between w-full text-sm">
            <span class="text-surface-600 dark:text-surface-400">Subtotal:</span>
            <span class="font-medium">{{ subtotal }}</span>
          </div>
          <div class="flex justify-between w-full text-sm">
            <span class="text-surface-600 dark:text-surface-400">Tax:</span>
            <span class="font-medium">{{ taxAmount }}</span>
          </div>
          <div class="flex justify-between w-full text-sm">
            <span class="text-surface-600 dark:text-surface-400">Shipping:</span>
            <span class="font-medium">{{ po.shipping_cost || 0 }}</span>
          </div>
          <div class="border-t border-gray-200 dark:border-surface-700 pt-2 flex justify-between w-full font-semibold text-lg">
            <span>Total:</span>
            <span>{{ po.total }}</span>
          </div>
        </div>
      </div>

      <!-- Notes -->
      <div v-if="po.notes" class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Notes</h3>
        <p class="text-surface-700 dark:text-surface-300 whitespace-pre-wrap">{{ po.notes }}</p>
      </div>
    </div>

    <p v-if="error" class="text-sm text-red-700 dark:text-red-300 mt-4">{{ error }}</p>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useRouteId } from '@/composables/useRouteId'
const routeId = useRouteId()
const po = ref({})
const loading = ref(true)
const error = ref('')

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

const subtotal = computed(() => {
  return po.value.lines?.reduce((sum, line) => sum + (line.line_total || 0), 0) || 0
})

const taxAmount = computed(() => {
  return po.value.lines?.reduce((sum, line) => {
    const tax = (line.line_total || 0) * ((line.tax_rate || 0) / 100)
    return sum + tax
  }, 0) || 0
})

const loadPurchaseOrder = async () => {
  try {
    const response = await fetch(`/api/v1/achats/purchase-orders/${routeId.value}`, {
      headers: {
        'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]').content}`
      }
    })
    if (response.ok) {
      po.value = await response.json()
    } else {
      error.value = 'Failed to load purchase order'
    }
  } catch (err) {
    console.error('Failed to load PO:', err)
    error.value = 'An error occurred while loading the purchase order'
  } finally {
    loading.value = false
  }
}

const submitForApproval = async () => {
  if (!confirm('Submit this purchase order for approval?')) return

  try {
    const response = await fetch(`/api/v1/achats/purchase-orders/${routeId.value}/submit`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]').content}`,
        'Content-Type': 'application/json'
      }
    })
    if (response.ok) {
      loadPurchaseOrder()
    }
  } catch (err) {
    console.error('Failed to submit PO:', err)
    error.value = 'Failed to submit for approval'
  }
}

const markAsReceived = async () => {
  if (!confirm('Mark this purchase order as received?')) return

  try {
    const response = await fetch(`/api/v1/achats/purchase-orders/${routeId.value}`, {
      method: 'PATCH',
      headers: {
        'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]').content}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        status: 'received'
      })
    })
    if (response.ok) {
      loadPurchaseOrder()
    }
  } catch (err) {
    console.error('Failed to mark as received:', err)
    error.value = 'Failed to mark as received'
  }
}

const deletePO = async () => {
  if (!confirm('Are you sure you want to delete this purchase order?')) return

  try {
    const response = await fetch(`/api/v1/achats/purchase-orders/${routeId.value}`, {
      method: 'DELETE',
      headers: {
        'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]').content}`
      }
    })
    if (response.ok) {
      window.location.href = '/purchase-orders'
    }
  } catch (err) {
    console.error('Failed to delete PO:', err)
    error.value = 'Failed to delete purchase order'
  }
}

onMounted(() => {
  loadPurchaseOrder()
})
</script>
