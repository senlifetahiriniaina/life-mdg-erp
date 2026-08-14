<template>
  <div class="max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">
        {{ isEditing ? 'Edit Purchase Order' : 'New Purchase Order' }}
      </h1>
      <Link href="/purchase-orders" class="text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:text-surface-50">
        ← Back to Purchase Orders
      </Link>
    </div>

    <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
      <form @submit.prevent="handleSubmit" class="space-y-6">
        <!-- Order Details -->
        <div class="border-b border-gray-200 dark:border-surface-700 pb-6">
          <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Order Details</h2>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label for="po-number" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                PO Number
              </label>
              <input id="po-number"
                v-model="form.po_number"
                type="text"
                class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg bg-gray-50 dark:bg-surface-800 dark:bg-surface-800"
                disabled
              />
            </div>

            <div>
              <label for="supplier" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                Supplier *
              </label>
              <select id="supplier"
                v-model="form.supplier_id"
                required
                class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
               :aria-describedby="errors.supplier_id ? 'supplier-error' : undefined" :aria-invalid="!!errors.supplier_id">
                <option value="">Select a supplier</option>
                <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                  {{ supplier.name }}
                </option>
              </select>
              <p id="supplier-error" role="alert" v-if="errors.supplier_id" class="mt-1 text-sm text-red-700 dark:text-red-300">{{ errors.supplier_id }}</p>
            </div>

            <div>
              <label for="currency" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                Currency
              </label>
              <select id="currency"
                v-model="form.currency"
                class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
              >
                <option value="USD">USD</option>
                <option value="EUR">EUR</option>
                <option value="GBP">GBP</option>
                <option value="JPY">JPY</option>
              </select>
            </div>

            <div>
              <label for="order-date" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                Order Date *
              </label>
              <input id="order-date"
                v-model="form.order_date"
                type="date"
                required
                class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
              />
            </div>

            <div>
              <label for="delivery-date" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                Delivery Date
              </label>
              <input id="delivery-date"
                v-model="form.delivery_date"
                type="date"
                class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
              />
            </div>

            <div>
              <label for="shipping-cost" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                Shipping Cost
              </label>
              <input id="shipping-cost"
                v-model.number="form.shipping_cost"
                type="number"
                step="0.01"
                class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
                placeholder="0.00"
              />
            </div>
          </div>
        </div>

        <!-- Line Items -->
        <div class="border-b border-gray-200 dark:border-surface-700 pb-6">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-50">Line Items</h2>
            <button
              type="button"
              @click="addLineItem"
              class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm"
            >
              + Add Line
            </button>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-gray-50 dark:bg-surface-800 dark:bg-surface-800 border-b border-gray-200 dark:border-surface-700">
                <tr>
                  <th scope="col" class="px-4 py-2 text-left font-medium text-surface-700 dark:text-surface-300">Description</th>
                  <th scope="col" class="px-4 py-2 text-left font-medium text-surface-700 dark:text-surface-300 w-20">Quantity</th>
                  <th scope="col" class="px-4 py-2 text-left font-medium text-surface-700 dark:text-surface-300 w-20">Unit</th>
                  <th scope="col" class="px-4 py-2 text-left font-medium text-surface-700 dark:text-surface-300 w-24">Unit Price</th>
                  <th scope="col" class="px-4 py-2 text-left font-medium text-surface-700 dark:text-surface-300 w-20">Tax %</th>
                  <th scope="col" class="px-4 py-2 text-right font-medium text-surface-700 dark:text-surface-300 w-24">Total</th>
                  <th scope="col" class="px-4 py-2 text-center font-medium text-surface-700 dark:text-surface-300 w-12">Action</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(line, idx) in form.lines" :key="idx" class="border-b border-gray-200 dark:border-surface-700">
                  <td class="px-4 py-2">
                    <input
                      v-model="line.description"
                      type="text"
                      placeholder="Item description"
                      class="w-full px-2 py-1 border border-gray-300 dark:border-surface-600 rounded text-sm"
                    />
                  </td>
                  <td class="px-4 py-2">
                    <input
                      v-model.number="line.quantity"
                      type="number"
                      step="0.01"
                      class="w-full px-2 py-1 border border-gray-300 dark:border-surface-600 rounded text-sm"
                      @change="calculateLineTotal(idx)"
                    />
                  </td>
                  <td class="px-4 py-2">
                    <input
                      v-model="line.unit"
                      type="text"
                      placeholder="Unit"
                      class="w-full px-2 py-1 border border-gray-300 dark:border-surface-600 rounded text-sm"
                    />
                  </td>
                  <td class="px-4 py-2">
                    <input
                      v-model.number="line.unit_price"
                      type="number"
                      step="0.01"
                      class="w-full px-2 py-1 border border-gray-300 dark:border-surface-600 rounded text-sm"
                      @change="calculateLineTotal(idx)"
                    />
                  </td>
                  <td class="px-4 py-2">
                    <input
                      v-model.number="line.tax_rate"
                      type="number"
                      step="0.01"
                      class="w-full px-2 py-1 border border-gray-300 dark:border-surface-600 rounded text-sm"
                      @change="calculateLineTotal(idx)"
                    />
                  </td>
                  <td class="px-4 py-2 text-right font-medium">
                    {{ line.line_total || 0 }}
                  </td>
                  <td class="px-4 py-2 text-center">
                    <button aria-label="Fermer"
                      type="button"
                      @click="removeLineItem(idx)"
                      class="text-red-700 dark:text-red-300 hover:text-red-800"
                    >✕
  </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="form.lines.length === 0" class="py-8 text-center text-surface-500 dark:text-surface-400">
            No line items yet. Add one to get started.
          </div>
        </div>

        <!-- Totals -->
        <div class="border-b border-gray-200 dark:border-surface-700 pb-6">
          <div class="flex justify-end max-w-md ml-auto space-y-2">
            <div class="flex justify-between w-full text-sm">
              <span class="text-surface-600 dark:text-surface-400">Subtotal:</span>
              <span class="font-medium">{{ subtotal }}</span>
            </div>
            <div class="flex justify-between w-full text-sm">
              <span class="text-surface-600 dark:text-surface-400">Tax Amount:</span>
              <span class="font-medium">{{ taxAmount }}</span>
            </div>
            <div class="flex justify-between w-full text-sm">
              <span class="text-surface-600 dark:text-surface-400">Shipping:</span>
              <span class="font-medium">{{ form.shipping_cost || 0 }}</span>
            </div>
            <div class="border-t border-gray-200 dark:border-surface-700 pt-2 flex justify-between w-full font-semibold">
              <span>Total:</span>
              <span>{{ total }}</span>
            </div>
          </div>
        </div>

        <!-- Notes -->
        <div class="pb-6">
          <label for="notes" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
            Notes
          </label>
          <textarea id="notes"
            v-model="form.notes"
            rows="4"
            class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
            placeholder="Additional notes or special instructions..."
          ></textarea>
        </div>

        <!-- Actions -->
        <div class="flex gap-4">
          <button
            type="submit"
            :disabled="loading"
            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
          >
            {{ loading ? 'Saving...' : 'Save Purchase Order' }}
          </button>
          <Link
            href="/purchase-orders"
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
import { ref, onMounted, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useRouteId } from '@/composables/useRouteId'
const routeId = useRouteId()
const isEditing = computed(() => !!routeId.value)
const loading = ref(false)
const submitError = ref('')
const errors = ref({})
const suppliers = ref([])

const form = ref({
  po_number: '',
  supplier_id: '',
  currency: 'USD',
  order_date: new Date().toISOString().split('T')[0],
  delivery_date: '',
  shipping_cost: 0,
  lines: [],
  notes: '',
})

const subtotal = computed(() => {
  return form.value.lines.reduce((sum, line) => sum + (line.line_total || 0), 0)
})

const taxAmount = computed(() => {
  return form.value.lines.reduce((sum, line) => {
    const tax = (line.line_total || 0) * ((line.tax_rate || 0) / 100)
    return sum + tax
  }, 0)
})

const total = computed(() => {
  return subtotal.value + taxAmount.value + (form.value.shipping_cost || 0)
})

const loadSuppliers = async () => {
  try {
    const response = await fetch('/api/v1/achats/suppliers?per_page=999', {
      headers: {
        'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]').content}`
      }
    })
    const data = await response.json()
    suppliers.value = data.data
  } catch (error) {
    console.error('Failed to load suppliers:', error)
  }
}

const loadPurchaseOrder = async () => {
  if (!isEditing.value) return

  try {
    const response = await fetch(`/api/v1/achats/purchase-orders/${routeId.value}`, {
      headers: {
        'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]').content}`
      }
    })
    if (response.ok) {
      const data = await response.json()
      Object.assign(form.value, data)
    }
  } catch (error) {
    console.error('Failed to load PO:', error)
    submitError.value = 'Failed to load purchase order data'
  }
}

const addLineItem = () => {
  form.value.lines.push({
    description: '',
    quantity: 1,
    unit: 'ea',
    unit_price: 0,
    tax_rate: 0,
    line_total: 0
  })
}

const removeLineItem = (idx) => {
  form.value.lines.splice(idx, 1)
}

const calculateLineTotal = (idx) => {
  const line = form.value.lines[idx]
  line.line_total = (line.quantity || 0) * (line.unit_price || 0)
}

const handleSubmit = async () => {
  if (form.value.lines.length === 0) {
    submitError.value = 'Please add at least one line item'
    return
  }

  loading.value = true
  submitError.value = ''
  errors.value = {}

  try {
    const url = isEditing.value
      ? `/api/v1/achats/purchase-orders/${routeId.value}`
      : '/api/v1/achats/purchase-orders'
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
        submitError.value = data.message || 'Failed to save purchase order'
      }
      return
    }

    window.location.href = '/purchase-orders'
  } catch (error) {
    console.error('Failed to save PO:', error)
    submitError.value = 'An error occurred while saving'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadSuppliers()
  loadPurchaseOrder()
})
</script>
