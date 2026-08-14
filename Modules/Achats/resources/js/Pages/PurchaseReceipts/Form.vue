<template>
  <div class="max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">
        {{ isEditing ? 'Edit Purchase Receipt' : 'Record Purchase Receipt' }}
      </h1>
      <Link href="/purchase-receipts" class="text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:text-surface-50">
        ← Back to Receipts
      </Link>
    </div>

    <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
      <form @submit.prevent="handleSubmit" class="space-y-6">
        <!-- Receipt Details -->
        <div class="border-b border-gray-200 dark:border-surface-700 pb-6">
          <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Receipt Details</h2>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label for="receipt-number" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                Receipt Number
              </label>
              <input id="receipt-number"
                v-model="form.receipt_number"
                type="text"
                class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg bg-gray-50 dark:bg-surface-800 dark:bg-surface-800"
                disabled
              />
            </div>

            <div>
              <label for="purchase-order" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                Purchase Order *
              </label>
              <select id="purchase-order"
                v-model="form.purchase_order_id"
                required
                class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
                @change="loadPODetails"
               :aria-describedby="errors.purchase_order_id ? 'purchase-order-error' : undefined" :aria-invalid="!!errors.purchase_order_id">
                <option value="">Select a purchase order</option>
                <option v-for="po in purchaseOrders" :key="po.id" :value="po.id">
                  {{ po.po_number }} - {{ po.supplier?.name }}
                </option>
              </select>
              <p id="purchase-order-error" role="alert" v-if="errors.purchase_order_id" class="mt-1 text-sm text-red-700 dark:text-red-300">{{ errors.purchase_order_id }}</p>
            </div>

            <div>
              <label for="receipt-date" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                Receipt Date *
              </label>
              <input id="receipt-date"
                v-model="form.receipt_date"
                type="date"
                required
                class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
              />
            </div>
          </div>
        </div>

        <!-- PO Summary -->
        <div v-if="selectedPO" class="bg-primary-50 dark:bg-primary-900/20 border border-blue-200 rounded-lg p-4">
          <h3 class="font-semibold text-surface-900 dark:text-surface-50 mb-3">Original PO Details</h3>
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
            <div>
              <p class="text-surface-600 dark:text-surface-400">PO Total</p>
              <p class="font-medium text-surface-900 dark:text-surface-50">{{ selectedPO.total }}</p>
            </div>
            <div>
              <p class="text-surface-600 dark:text-surface-400">Supplier</p>
              <p class="font-medium text-surface-900 dark:text-surface-50">{{ selectedPO.supplier?.name }}</p>
            </div>
            <div>
              <p class="text-surface-600 dark:text-surface-400">Delivery Date</p>
              <p class="font-medium text-surface-900 dark:text-surface-50">{{ formatDate(selectedPO.delivery_date) }}</p>
            </div>
            <div>
              <p class="text-surface-600 dark:text-surface-400">Currency</p>
              <p class="font-medium text-surface-900 dark:text-surface-50">{{ selectedPO.currency }}</p>
            </div>
          </div>
        </div>

        <!-- Received Items -->
        <div class="border-b border-gray-200 dark:border-surface-700 pb-6">
          <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Received Items</h2>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-gray-50 dark:bg-surface-800 dark:bg-surface-800 border-b border-gray-200 dark:border-surface-700">
                <tr>
                  <th scope="col" class="px-4 py-2 text-left font-medium text-surface-700 dark:text-surface-300">Description</th>
                  <th scope="col" class="px-4 py-2 text-left font-medium text-surface-700 dark:text-surface-300 w-20">Ordered</th>
                  <th scope="col" class="px-4 py-2 text-left font-medium text-surface-700 dark:text-surface-300 w-20">Received *</th>
                  <th scope="col" class="px-4 py-2 text-left font-medium text-surface-700 dark:text-surface-300 w-20">Unit</th>
                  <th scope="col" class="px-4 py-2 text-left font-medium text-surface-700 dark:text-surface-300 w-24">Variance</th>
                  <th scope="col" class="px-4 py-2 text-left font-medium text-surface-700 dark:text-surface-300 w-20">Quality</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(line, idx) in form.lines" :key="idx" class="border-b border-gray-200 dark:border-surface-700">
                  <td class="px-4 py-2">{{ line.po_line?.description || 'Unknown' }}</td>
                  <td class="px-4 py-2 text-center">{{ line.po_line?.quantity || 0 }}</td>
                  <td class="px-4 py-2">
                    <input
                      v-model.number="line.quantity_received"
                      type="number"
                      step="0.01"
                      class="w-full px-2 py-1 border border-gray-300 dark:border-surface-600 rounded text-sm"
                      @change="calculateVariance(idx)"
                    />
                  </td>
                  <td class="px-4 py-2 text-center">{{ line.po_line?.unit }}</td>
                  <td class="px-4 py-2">
                    <span
                      :class="[
                        'px-2 py-1 rounded text-xs font-medium',
                        !line.variance ? 'bg-green-100 text-green-800' :
                        line.variance < 0 ? 'bg-yellow-100 text-yellow-800' :
                        'bg-red-100 text-red-800'
                      ]"
                    >
                      {{ line.variance || 0 }}
                    </span>
                  </td>
                  <td class="px-4 py-2">
                    <select
                      v-model="line.quality_status"
                      class="px-2 py-1 border border-gray-300 dark:border-surface-600 rounded text-sm focus:ring-blue-500"
                    >
                      <option value="acceptable">Acceptable</option>
                      <option value="damaged">Damaged</option>
                      <option value="defective">Defective</option>
                      <option value="pending">Pending</option>
                    </select>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <p v-if="form.lines.length === 0" class="py-4 text-center text-surface-500 dark:text-surface-400">
            Select a purchase order to load its line items.
          </p>
        </div>

        <!-- Quality Issues -->
        <div class="border-b border-gray-200 dark:border-surface-700 pb-6">
          <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Quality Issues</h2>
          <div class="space-y-4">
            <div v-for="(issue, idx) in form.quality_issues" :key="idx" class="p-4 border border-gray-300 dark:border-surface-600 rounded-lg">
              <div class="flex items-start justify-between mb-3">
                <h3 class="font-medium text-surface-900 dark:text-surface-50">Issue {{ idx + 1 }}</h3>
                <button
                  type="button"
                  @click="removeQualityIssue(idx)"
                  class="text-red-700 dark:text-red-300 hover:text-red-800"
                >
                  Remove
                </button>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label for="line-item" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                    Line Item
                  </label>
                  <select id="line-item"
                    v-model="issue.po_line_id"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-surface-600 rounded-lg text-sm focus:ring-blue-500"
                  >
                    <option value="">Select line item</option>
                    <option v-for="line in form.lines" :key="line.po_line?.id" :value="line.po_line?.id">
                      {{ line.po_line?.description }}
                    </option>
                  </select>
                </div>
                <div>
                  <label for="issue-type" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                    Issue Type
                  </label>
                  <select id="issue-type"
                    v-model="issue.issue_type"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-surface-600 rounded-lg text-sm focus:ring-blue-500"
                  >
                    <option value="">Select type</option>
                    <option value="damage">Damage</option>
                    <option value="defect">Defect</option>
                    <option value="shortfall">Shortfall</option>
                    <option value="other">Other</option>
                  </select>
                </div>
              </div>
              <div class="mt-3">
                <label for="description" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                  Description
                </label>
                <textarea id="description"
                  v-model="issue.description"
                  rows="2"
                  placeholder="Describe the issue..."
                  class="w-full px-3 py-2 border border-gray-300 dark:border-surface-600 rounded-lg text-sm focus:ring-blue-500"
                ></textarea>
              </div>
            </div>
          </div>

          <button
            type="button"
            @click="addQualityIssue"
            class="mt-4 px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 text-sm"
          >
            + Add Quality Issue
          </button>
        </div>

        <!-- Notes -->
        <div class="pb-6">
          <label for="notes" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
            Notes
          </label>
          <textarea id="notes"
            v-model="form.notes"
            rows="3"
            placeholder="Additional notes about this receipt..."
            class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
          ></textarea>
        </div>

        <!-- Actions -->
        <div class="flex gap-4">
          <button
            type="submit"
            :disabled="loading"
            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
          >
            {{ loading ? 'Saving...' : 'Save Receipt' }}
          </button>
          <Link
            href="/purchase-receipts"
            class="px-6 py-2 border border-gray-300 dark:border-surface-600 text-surface-700 dark:text-surface-300 rounded-lg hover:bg-gray-50 dark:bg-surface-800 dark:bg-surface-800"
          >
            Cancel
          </Link>
        </div>

        <p v-if="submitError" class="text-sm text-red-700 dark:text-red-300 mt-4">{{ submitError }}</p>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useRouteId } from '@/composables/useRouteId'
const routeId = useRouteId()
const isEditing = ref(!!routeId.value)
const loading = ref(false)
const submitError = ref('')
const errors = ref({})
const purchaseOrders = ref([])
const selectedPO = ref(null)

const form = ref({
  receipt_number: '',
  purchase_order_id: '',
  receipt_date: new Date().toISOString().split('T')[0],
  lines: [],
  quality_issues: [],
  notes: ''
})

const formatDate = (date) => {
  if (!date) return 'N/A'
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

const loadPurchaseOrders = async () => {
  try {
    const response = await fetch('/api/v1/achats/purchase-orders?status=approved&per_page=999', {
      headers: {
        'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]').content}`
      }
    })
    const data = await response.json()
    purchaseOrders.value = data.data
  } catch (error) {
    console.error('Failed to load POs:', error)
  }
}

const loadPODetails = async () => {
  if (!form.value.purchase_order_id) {
    form.value.lines = []
    selectedPO.value = null
    return
  }

  try {
    const response = await fetch(`/api/v1/achats/purchase-orders/${form.value.purchase_order_id}`, {
      headers: {
        'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]').content}`
      }
    })
    const po = await response.json()
    selectedPO.value = po
    form.value.lines = po.lines.map(line => ({
      po_line_id: line.id,
      po_line: line,
      quantity_received: 0,
      variance: 0,
      quality_status: 'acceptable'
    }))
  } catch (error) {
    console.error('Failed to load PO details:', error)
    submitError.value = 'Failed to load PO details'
  }
}

const calculateVariance = (idx) => {
  const line = form.value.lines[idx]
  const ordered = line.po_line?.quantity || 0
  const received = line.quantity_received || 0
  line.variance = received - ordered
}

const addQualityIssue = () => {
  form.value.quality_issues.push({
    po_line_id: '',
    issue_type: '',
    description: ''
  })
}

const removeQualityIssue = (idx) => {
  form.value.quality_issues.splice(idx, 1)
}

const handleSubmit = async () => {
  if (!form.value.purchase_order_id) {
    submitError.value = 'Please select a purchase order'
    return
  }

  if (form.value.lines.length === 0) {
    submitError.value = 'No line items to receive'
    return
  }

  loading.value = true
  submitError.value = ''
  errors.value = {}

  try {
    const url = isEditing.value
      ? `/api/v1/achats/purchase-receipts/${routeId.value}`
      : '/api/v1/achats/purchase-receipts'
    const method = isEditing.value ? 'PATCH' : 'POST'

    const response = await fetch(url, {
      method,
      headers: {
        'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]').content}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(form.value)
    })

    if (!response.ok) {
      const data = await response.json()
      if (data.errors) {
        errors.value = data.errors
      } else {
        submitError.value = data.message || 'Failed to save receipt'
      }
      return
    }

    window.location.href = '/purchase-receipts'
  } catch (error) {
    console.error('Failed to save receipt:', error)
    submitError.value = 'An error occurred while saving'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadPurchaseOrders()
})
</script>
