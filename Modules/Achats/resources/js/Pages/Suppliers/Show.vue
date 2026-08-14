<template>
  <div class="max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">Supplier Details</h1>
        <p class="mt-2 text-surface-600 dark:text-surface-400">{{ supplier.code }}</p>
      </div>
      <Link href="/suppliers" class="text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:text-surface-50">
        ← Back to Suppliers
      </Link>
    </div>

    <div v-if="loading" class="text-center py-8">
      <p class="text-surface-600 dark:text-surface-400">Loading...</p>
    </div>

    <div v-else-if="supplier.id" class="space-y-6">
      <!-- Status and Actions -->
      <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Status</h2>
            <div class="flex items-center gap-2">
              <span
                :class="[
                  'px-4 py-2 rounded-lg text-sm font-medium',
                  supplier.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 dark:bg-surface-700 text-gray-800 dark:text-surface-100'
                ]"
              >
                {{ supplier.is_active ? 'Active' : 'Inactive' }}
              </span>
            </div>
          </div>
          <div class="flex gap-2">
            <Link
              :href="`/suppliers/${supplier.id}/edit`"
              class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm"
            >
              Edit Supplier
            </Link>
            <button
              @click="deleteSupplier"
              class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm"
            >
              Delete
            </button>
          </div>
        </div>
      </div>

      <!-- Performance Metrics -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <p class="text-sm text-surface-600 dark:text-surface-400 mb-2">Total Orders</p>
          <p class="text-3xl font-bold text-surface-900 dark:text-surface-50">{{ metrics.total_orders || 0 }}</p>
        </div>
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <p class="text-sm text-surface-600 dark:text-surface-400 mb-2">Total Spent</p>
          <p class="text-3xl font-bold text-surface-900 dark:text-surface-50">{{ metrics.total_spent || 0 }}</p>
        </div>
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <p class="text-sm text-surface-600 dark:text-surface-400 mb-2">On-Time Delivery</p>
          <p class="text-3xl font-bold text-green-700 dark:text-green-300">{{ metrics.on_time_delivery || 0 }}%</p>
        </div>
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <p class="text-sm text-surface-600 dark:text-surface-400 mb-2">Quality Score</p>
          <p class="text-3xl font-bold text-primary-700 dark:text-primary-300">{{ metrics.quality_score || 0 }}/100</p>
        </div>
      </div>

      <!-- Basic Information -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Company Information</h3>
          <div class="space-y-3">
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Supplier Name</p>
              <p class="text-lg font-medium text-surface-900 dark:text-surface-50">{{ supplier.name }}</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Code</p>
              <p class="text-base font-mono text-surface-900 dark:text-surface-50">{{ supplier.code }}</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Email</p>
              <p class="text-base text-surface-900 dark:text-surface-50">{{ supplier.email }}</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Phone</p>
              <p class="text-base text-surface-900 dark:text-surface-50">{{ supplier.phone || 'N/A' }}</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Default Currency</p>
              <p class="text-base text-surface-900 dark:text-surface-50">{{ supplier.currency || 'USD' }}</p>
            </div>
          </div>
        </div>

        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Contact Information</h3>
          <div class="space-y-3">
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Contact Person</p>
              <p class="text-base text-surface-900 dark:text-surface-50">{{ supplier.contact_person || 'N/A' }}</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Contact Email</p>
              <p class="text-base text-surface-900 dark:text-surface-50">{{ supplier.contact_email || 'N/A' }}</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Contact Phone</p>
              <p class="text-base text-surface-900 dark:text-surface-50">{{ supplier.contact_phone || 'N/A' }}</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Contact Mobile</p>
              <p class="text-base text-surface-900 dark:text-surface-50">{{ supplier.contact_mobile || 'N/A' }}</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Payment Terms</p>
              <p class="text-base text-surface-900 dark:text-surface-50">{{ supplier.payment_terms || '30' }} days</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Address Information -->
      <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Address</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <p class="text-sm text-surface-600 dark:text-surface-400">Street Address</p>
            <p class="text-base text-surface-900 dark:text-surface-50">{{ supplier.street_address || 'N/A' }}</p>
          </div>
          <div>
            <p class="text-sm text-surface-600 dark:text-surface-400">City</p>
            <p class="text-base text-surface-900 dark:text-surface-50">{{ supplier.city || 'N/A' }}</p>
          </div>
          <div>
            <p class="text-sm text-surface-600 dark:text-surface-400">State/Province</p>
            <p class="text-base text-surface-900 dark:text-surface-50">{{ supplier.state || 'N/A' }}</p>
          </div>
          <div>
            <p class="text-sm text-surface-600 dark:text-surface-400">Postal Code</p>
            <p class="text-base text-surface-900 dark:text-surface-50">{{ supplier.postal_code || 'N/A' }}</p>
          </div>
          <div>
            <p class="text-sm text-surface-600 dark:text-surface-400">Country</p>
            <p class="text-base text-surface-900 dark:text-surface-50">{{ supplier.country || 'N/A' }}</p>
          </div>
          <div>
            <p class="text-sm text-surface-600 dark:text-surface-400">Tax ID / VAT</p>
            <p class="text-base text-surface-900 dark:text-surface-50">{{ supplier.tax_id || 'N/A' }}</p>
          </div>
        </div>
      </div>

      <!-- Recent Orders -->
      <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Recent Purchase Orders</h3>
        <div v-if="recentOrders.length === 0" class="text-center py-6 text-surface-500 dark:text-surface-400">
          No purchase orders yet
        </div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-surface-800 dark:bg-surface-800 border-b border-gray-200 dark:border-surface-700">
              <tr>
                <th scope="col" class="px-4 py-2 text-left font-medium text-surface-700 dark:text-surface-300">PO Number</th>
                <th scope="col" class="px-4 py-2 text-left font-medium text-surface-700 dark:text-surface-300">Order Date</th>
                <th scope="col" class="px-4 py-2 text-left font-medium text-surface-700 dark:text-surface-300">Total</th>
                <th scope="col" class="px-4 py-2 text-left font-medium text-surface-700 dark:text-surface-300">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="order in recentOrders" :key="order.id" class="border-b border-gray-200 dark:border-surface-700">
                <td class="px-4 py-2 font-mono">{{ order.po_number }}</td>
                <td class="px-4 py-2">{{ formatDate(order.order_date) }}</td>
                <td class="px-4 py-2">{{ order.total }}</td>
                <td class="px-4 py-2">
                  <span
                    :class="[
                      'px-3 py-1 rounded-full text-xs font-medium',
                      statusClasses[order.status] || 'bg-gray-100 dark:bg-surface-700 text-gray-800 dark:text-surface-100'
                    ]"
                  >
                    {{ formatStatus(order.status) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <p v-if="error" class="text-sm text-red-700 dark:text-red-300 mt-4">{{ error }}</p>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useRouteId } from '@/composables/useRouteId'
const routeId = useRouteId()
const supplier = ref({})
const metrics = ref({})
const recentOrders = ref([])
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

const loadSupplier = async () => {
  try {
    const response = await fetch(`/api/v1/achats/suppliers/${routeId.value}`, {
      headers: {
        'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]').content}`
      }
    })
    if (response.ok) {
      supplier.value = await response.json()
    } else {
      error.value = 'Failed to load supplier'
    }
  } catch (err) {
    console.error('Failed to load supplier:', err)
    error.value = 'An error occurred while loading the supplier'
  } finally {
    loading.value = false
  }
}

const loadMetrics = async () => {
  try {
    const response = await fetch(`/api/v1/achats/suppliers/${routeId.value}/performance`, {
      headers: {
        'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]').content}`
      }
    })
    if (response.ok) {
      const data = await response.json()
      metrics.value = data.metrics || {}
    }
  } catch (err) {
    console.error('Failed to load metrics:', err)
  }
}

const loadRecentOrders = async () => {
  try {
    const response = await fetch(`/api/v1/achats/suppliers/${routeId.value}/quotes`, {
      headers: {
        'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]').content}`
      }
    })
    if (response.ok) {
      const data = await response.json()
      recentOrders.value = (data.data || []).slice(0, 5)
    }
  } catch (err) {
    console.error('Failed to load recent orders:', err)
  }
}

const deleteSupplier = async () => {
  if (!confirm('Are you sure you want to delete this supplier?')) return

  try {
    const response = await fetch(`/api/v1/achats/suppliers/${routeId.value}`, {
      method: 'DELETE',
      headers: {
        'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]').content}`
      }
    })
    if (response.ok) {
      window.location.href = '/suppliers'
    }
  } catch (err) {
    console.error('Failed to delete supplier:', err)
    error.value = 'Failed to delete supplier'
  }
}

onMounted(() => {
  loadSupplier()
  loadMetrics()
  loadRecentOrders()
})
</script>
