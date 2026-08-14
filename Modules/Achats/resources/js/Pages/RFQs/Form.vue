<template>
  <div class="max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">
        {{ isEditing ? 'Edit RFQ' : 'New Request for Quotation' }}
      </h1>
      <Link href="/rfqs" class="text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:text-surface-50">
        ← Back to RFQs
      </Link>
    </div>

    <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
      <form @submit.prevent="handleSubmit" class="space-y-6">
        <!-- RFQ Details -->
        <div class="border-b border-gray-200 dark:border-surface-700 pb-6">
          <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">RFQ Details</h2>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label for="rfq-number" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                RFQ Number
              </label>
              <input id="rfq-number"
                v-model="form.rfq_number"
                type="text"
                class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg bg-gray-50 dark:bg-surface-800 dark:bg-surface-800"
                disabled
              />
            </div>

            <div>
              <label for="item-description" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                Item Description *
              </label>
              <input id="item-description"
                v-model="form.item_description"
                type="text"
                required
                class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
                placeholder="Describe the item being quoted" :aria-describedby="errors.item_description ? 'item-description-error' : undefined" :aria-invalid="!!errors.item_description" />
              <p id="item-description-error" role="alert" v-if="errors.item_description" class="mt-1 text-sm text-red-700 dark:text-red-300">{{ errors.item_description }}</p>
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
              <label for="issued-date" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                Issued Date *
              </label>
              <input id="issued-date"
                v-model="form.issued_date"
                type="date"
                required
                class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
              />
            </div>

            <div>
              <label for="response-deadline" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                Response Deadline *
              </label>
              <input id="response-deadline"
                v-model="form.response_deadline"
                type="date"
                required
                class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
              />
            </div>

            <div>
              <label for="expected-quantity" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                Expected Quantity
              </label>
              <input id="expected-quantity"
                v-model.number="form.expected_quantity"
                type="number"
                step="0.01"
                class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
              />
            </div>
          </div>
        </div>

        <!-- Suppliers to Quote -->
        <div class="border-b border-gray-200 dark:border-surface-700 pb-6">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-50">Suppliers to Quote</h2>
            <button
              type="button"
              @click="addSupplier"
              class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm"
            >
              + Add Supplier
            </button>
          </div>

          <div class="space-y-3">
            <div v-for="(supplier, idx) in form.suppliers" :key="idx" class="flex items-center gap-2 p-3 border border-gray-300 dark:border-surface-600 rounded-lg">
              <select
                v-model="supplier.id"
                class="flex-1 px-3 py-2 border border-gray-300 dark:border-surface-600 rounded text-sm focus:ring-blue-500"
              >
                <option value="">Select a supplier</option>
                <option v-for="s in availableSuppliers" :key="s.id" :value="s.id">
                  {{ s.name }}
                </option>
              </select>
              <button aria-label="Fermer"
                type="button"
                @click="removeSupplier(idx)"
                class="text-red-700 dark:text-red-300 hover:text-red-800 font-semibold"
              >✕
  </button>
            </div>
          </div>

          <p v-if="form.suppliers.length === 0" class="py-4 text-center text-surface-500 dark:text-surface-400 text-sm">
            No suppliers selected. Add at least one to issue the RFQ.
          </p>
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

          <p v-if="form.lines.length === 0" class="py-4 text-center text-surface-500 dark:text-surface-400 text-sm">
            No line items yet. Add one to get started.
          </p>
        </div>

        <!-- Notes -->
        <div class="pb-6">
          <label for="special-requirements" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
            Special Requirements
          </label>
          <textarea id="special-requirements"
            v-model="form.notes"
            rows="4"
            class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
            placeholder="Any special requirements or notes for suppliers..."
          ></textarea>
        </div>

        <!-- Actions -->
        <div class="flex gap-4">
          <button
            type="submit"
            :disabled="loading"
            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
          >
            {{ loading ? 'Saving...' : 'Save RFQ' }}
          </button>
          <Link
            href="/rfqs"
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
const availableSuppliers = ref([])

const form = ref({
  rfq_number: '',
  item_description: '',
  currency: 'USD',
  issued_date: new Date().toISOString().split('T')[0],
  response_deadline: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
  expected_quantity: 1,
  suppliers: [],
  lines: [],
  notes: '',
})

const loadSuppliers = async () => {
  try {
    const response = await fetch('/api/v1/achats/suppliers?per_page=999', {
      headers: {
        'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]').content}`
      }
    })
    const data = await response.json()
    availableSuppliers.value = data.data
  } catch (error) {
    console.error('Failed to load suppliers:', error)
  }
}

const loadRFQ = async () => {
  if (!isEditing.value) return

  try {
    const response = await fetch(`/api/v1/achats/rfqs/${routeId.value}`, {
      headers: {
        'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]').content}`
      }
    })
    if (response.ok) {
      const data = await response.json()
      Object.assign(form.value, data)
    }
  } catch (error) {
    console.error('Failed to load RFQ:', error)
    submitError.value = 'Failed to load RFQ data'
  }
}

const addSupplier = () => {
  form.value.suppliers.push({ id: '' })
}

const removeSupplier = (idx) => {
  form.value.suppliers.splice(idx, 1)
}

const addLineItem = () => {
  form.value.lines.push({
    description: '',
    quantity: 1,
    unit: 'ea'
  })
}

const removeLineItem = (idx) => {
  form.value.lines.splice(idx, 1)
}

const handleSubmit = async () => {
  if (form.value.suppliers.length === 0) {
    submitError.value = 'Please add at least one supplier'
    return
  }

  if (form.value.lines.length === 0) {
    submitError.value = 'Please add at least one line item'
    return
  }

  loading.value = true
  submitError.value = ''
  errors.value = {}

  try {
    const url = isEditing.value
      ? `/api/v1/achats/rfqs/${routeId.value}`
      : '/api/v1/achats/rfqs'
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
        submitError.value = data.message || 'Failed to save RFQ'
      }
      return
    }

    window.location.href = '/rfqs'
  } catch (error) {
    console.error('Failed to save RFQ:', error)
    submitError.value = 'An error occurred while saving'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadSuppliers()
  loadRFQ()
})
</script>
