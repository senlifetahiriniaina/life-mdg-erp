<template>
  <div class="max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">Purchase Receipt</h1>
        <p class="mt-2 text-surface-600 dark:text-surface-400">{{ receipt.receipt_number }}</p>
      </div>
      <Link href="/purchase-receipts" class="text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:text-surface-50">
        ← Back to Receipts
      </Link>
    </div>

    <div v-if="loading" class="text-center py-8">
      <p class="text-surface-600 dark:text-surface-400">Loading...</p>
    </div>

    <div v-else-if="receipt.id" class="space-y-6">
      <!-- Status Bar -->
      <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Receipt Status</h2>
            <div class="flex items-center gap-2">
              <span
                :class="[
                  'px-4 py-2 rounded-lg text-sm font-medium',
                  statusClasses[receipt.status] || 'bg-gray-100 dark:bg-surface-700 text-gray-800 dark:text-surface-100'
                ]"
              >
                {{ formatStatus(receipt.status) }}
              </span>
              <span v-if="receipt.has_quality_issues" class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                ⚠ {{ receipt.quality_issues_count }} Issues
              </span>
            </div>
          </div>
          <div v-if="receipt.status !== 'completed'" class="flex gap-2">
            <Link
              :href="`/purchase-receipts/${receipt.id}/edit`"
              class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm"
            >
              Edit Receipt
            </Link>
          </div>
        </div>
      </div>

      <!-- Receipt Details -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Receipt Information</h3>
          <div class="space-y-3">
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Receipt Number</p>
              <p class="text-lg font-medium text-surface-900 dark:text-surface-50">{{ receipt.receipt_number }}</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Receipt Date</p>
              <p class="text-base text-surface-900 dark:text-surface-50">{{ formatDate(receipt.receipt_date) }}</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">PO Number</p>
              <p class="text-base font-mono text-surface-900 dark:text-surface-50">{{ receipt.purchase_order?.po_number }}</p>
            </div>
          </div>
        </div>

        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Supplier Information</h3>
          <div class="space-y-3">
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Supplier</p>
              <p class="text-lg font-medium text-surface-900 dark:text-surface-50">{{ receipt.purchase_order?.supplier?.name }}</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Email</p>
              <p class="text-base text-surface-900 dark:text-surface-50">{{ receipt.purchase_order?.supplier?.email }}</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Phone</p>
              <p class="text-base text-surface-900 dark:text-surface-50">{{ receipt.purchase_order?.supplier?.phone || 'N/A' }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Received Items -->
      <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Received Items</h3>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-surface-800 dark:bg-surface-800 border-b border-gray-200 dark:border-surface-700">
              <tr>
                <th scope="col" class="px-4 py-3 text-left font-medium text-surface-700 dark:text-surface-300">Description</th>
                <th scope="col" class="px-4 py-3 text-center font-medium text-surface-700 dark:text-surface-300 w-20">Ordered</th>
                <th scope="col" class="px-4 py-3 text-center font-medium text-surface-700 dark:text-surface-300 w-20">Received</th>
                <th scope="col" class="px-4 py-3 text-center font-medium text-surface-700 dark:text-surface-300 w-20">Variance</th>
                <th scope="col" class="px-4 py-3 text-center font-medium text-surface-700 dark:text-surface-300 w-24">Quality</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="line in receipt.lines" :key="line.id" class="border-b border-gray-200 dark:border-surface-700">
                <td class="px-4 py-3">{{ line.po_line?.description }}</td>
                <td class="px-4 py-3 text-center">{{ line.po_line?.quantity }}</td>
                <td class="px-4 py-3 text-center font-medium">{{ line.quantity_received }}</td>
                <td class="px-4 py-3 text-center">
                  <span
                    :class="[
                      'px-2 py-1 rounded text-xs font-medium',
                      !line.variance ? 'bg-green-100 text-green-800' :
                      line.variance < 0 ? 'bg-yellow-100 text-yellow-800' :
                      'bg-red-100 text-red-800'
                    ]"
                  >
                    {{ line.variance > 0 ? '+' : '' }}{{ line.variance }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <span
                    :class="[
                      'px-2 py-1 rounded text-xs font-medium',
                      line.quality_status === 'acceptable' ? 'bg-green-100 text-green-800' :
                      line.quality_status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                      'bg-red-100 text-red-800'
                    ]"
                  >
                    {{ formatQualityStatus(line.quality_status) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Quality Issues -->
      <div v-if="receipt.quality_issues && receipt.quality_issues.length > 0" class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6 border-l-4 border-red-500">
        <h3 class="text-lg font-semibold text-red-900 mb-4">⚠ Quality Issues Reported</h3>
        <div class="space-y-4">
          <div v-for="issue in receipt.quality_issues" :key="issue.id" class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 rounded-lg">
            <div class="flex items-start justify-between mb-2">
              <div>
                <p class="font-medium text-surface-900 dark:text-surface-50">{{ issue.po_line?.description }}</p>
                <p class="text-sm text-surface-600 dark:text-surface-400 mt-1">Type: <span class="font-medium">{{ formatIssueType(issue.issue_type) }}</span></p>
              </div>
              <span
                :class="[
                  'px-3 py-1 rounded-full text-xs font-medium',
                  issue.status === 'open' ? 'bg-red-100 text-red-800' :
                  issue.status === 'investigating' ? 'bg-yellow-100 text-yellow-800' :
                  'bg-green-100 text-green-800'
                ]"
              >
                {{ formatIssueStatus(issue.status) }}
              </span>
            </div>
            <p class="text-sm text-surface-700 dark:text-surface-300 mt-3">{{ issue.description }}</p>
          </div>
        </div>
      </div>

      <!-- Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <p class="text-sm text-surface-600 dark:text-surface-400 mb-2">Items Received</p>
          <p class="text-3xl font-bold text-surface-900 dark:text-surface-50">{{ receipt.lines?.length || 0 }}</p>
        </div>
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <p class="text-sm text-surface-600 dark:text-surface-400 mb-2">Variance Count</p>
          <p class="text-3xl font-bold text-yellow-700 dark:text-yellow-300">{{ varianceCount }}</p>
        </div>
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <p class="text-sm text-surface-600 dark:text-surface-400 mb-2">Quality Issues</p>
          <p class="text-3xl font-bold text-red-700 dark:text-red-300">{{ receipt.quality_issues_count || 0 }}</p>
        </div>
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <p class="text-sm text-surface-600 dark:text-surface-400 mb-2">Acceptable Items</p>
          <p class="text-3xl font-bold text-green-700 dark:text-green-300">{{ acceptableCount }}</p>
        </div>
      </div>

      <!-- Notes -->
      <div v-if="receipt.notes" class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Notes</h3>
        <p class="text-surface-700 dark:text-surface-300 whitespace-pre-wrap">{{ receipt.notes }}</p>
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
const receipt = ref({})
const loading = ref(true)
const error = ref('')

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

const formatQualityStatus = (status) => {
  const statusMap = {
    acceptable: 'Acceptable',
    damaged: 'Damaged',
    defective: 'Defective',
    pending: 'Pending'
  }
  return statusMap[status] || status
}

const formatIssueType = (type) => {
  const typeMap = {
    damage: 'Damage',
    defect: 'Defect',
    shortfall: 'Shortfall',
    other: 'Other'
  }
  return typeMap[type] || type
}

const formatIssueStatus = (status) => {
  const statusMap = {
    open: 'Open',
    investigating: 'Investigating',
    resolved: 'Resolved'
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

const varianceCount = computed(() => {
  return receipt.value.lines?.filter(l => l.variance !== 0).length || 0
})

const acceptableCount = computed(() => {
  return receipt.value.lines?.filter(l => l.quality_status === 'acceptable').length || 0
})

const loadReceipt = async () => {
  try {
    const response = await fetch(`/api/v1/achats/purchase-receipts/${routeId.value}`, {
      headers: {
        'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]').content}`
      }
    })
    if (response.ok) {
      receipt.value = await response.json()
    } else {
      error.value = 'Failed to load receipt'
    }
  } catch (err) {
    console.error('Failed to load receipt:', err)
    error.value = 'An error occurred while loading the receipt'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadReceipt()
})
</script>
