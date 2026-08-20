<template>
  <div class="max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">Request for Quotation</h1>
        <p class="mt-2 text-surface-600 dark:text-surface-400">{{ rfq.rfq_number }}</p>
      </div>
      <Link href="/rfqs" class="text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:text-surface-50">
        ← Back to RFQs
      </Link>
    </div>

    <div v-if="loading" class="text-center py-8">
      <p class="text-surface-600 dark:text-surface-400">Loading...</p>
    </div>

    <div v-else-if="rfq.id" class="space-y-6">
      <!-- Status and Actions -->
      <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Current Status</h2>
            <div class="flex items-center gap-2">
              <span
                :class="[
                  'px-4 py-2 rounded-lg text-sm font-medium',
                  statusClasses[rfq.status] || 'bg-gray-100 dark:bg-surface-700 text-gray-800 dark:text-surface-100'
                ]"
              >
                {{ formatStatus(rfq.status) }}
              </span>
            </div>
          </div>
          <div class="flex gap-2">
            <button
              v-if="rfq.status === 'draft'"
              @click="issueRFQ"
              class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm"
            >
              Issue RFQ
            </button>
            <button
              v-if="rfq.status === 'sent'"
              @click="goToComparison"
              class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm"
            >
              Compare Quotes
            </button>
          </div>
        </div>
      </div>

      <!-- RFQ Details -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">RFQ Information</h3>
          <div class="space-y-3">
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">RFQ Number</p>
              <p class="text-lg font-medium text-surface-900 dark:text-surface-50">{{ rfq.rfq_number }}</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Item Description</p>
              <p class="text-base text-surface-900 dark:text-surface-50">{{ rfq.description }}</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Currency</p>
              <p class="text-base text-surface-900 dark:text-surface-50">{{ rfq.currency }}</p>
            </div>
          </div>
        </div>

        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Timeline</h3>
          <div class="space-y-3">
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Issued Date</p>
              <p class="text-base text-surface-900 dark:text-surface-50">{{ rfq.issued_date ? formatDate(rfq.issued_date) : 'Not issued yet' }}</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Response Deadline</p>
              <p class="text-base text-surface-900 dark:text-surface-50">{{ formatDate(rfq.deadline_date) }}</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Quotes Received</p>
              <p class="text-3xl font-bold text-primary-700 dark:text-primary-300">{{ quotes.length || 0 }}</p>
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
                <th scope="col" class="px-4 py-3 text-left font-medium text-surface-700 dark:text-surface-300 w-16">Unit</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="line in rfq.lines" :key="line.id" class="border-b border-gray-200 dark:border-surface-700">
                <td class="px-4 py-3">{{ line.description }}</td>
                <td class="px-4 py-3">{{ line.quantity }}</td>
                <td class="px-4 py-3">{{ line.unit }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <p v-if="!rfq.lines || rfq.lines.length === 0" class="py-4 text-center text-surface-500 dark:text-surface-400">
          No line items
        </p>
      </div>

      <!-- Suppliers Issued To -->
      <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Suppliers</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div v-for="supplier in suppliers" :key="supplier.id" class="p-4 border border-gray-200 dark:border-surface-700 rounded-lg">
            <p class="font-medium text-surface-900 dark:text-surface-50">{{ supplier.name }}</p>
            <p class="text-sm text-surface-600 dark:text-surface-400">{{ supplier.email }}</p>
            <p class="text-sm text-surface-600 dark:text-surface-400">{{ supplier.phone }}</p>
          </div>
        </div>

        <p v-if="!suppliers || suppliers.length === 0" class="py-4 text-center text-surface-500 dark:text-surface-400">
          No suppliers added yet
        </p>
      </div>

      <!-- Quotes Received -->
      <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Quotes Received</h3>
        <div v-if="quotes.length === 0" class="text-center py-8 text-surface-500 dark:text-surface-400">
          No quotes received yet
        </div>
        <div v-else class="space-y-4">
          <div v-for="quote in quotes" :key="quote.id" class="p-4 border border-gray-200 dark:border-surface-700 rounded-lg">
            <div class="flex items-start justify-between mb-3">
              <div>
                <p class="font-medium text-surface-900 dark:text-surface-50">{{ quote.supplier?.name || 'Unknown Supplier' }}</p>
                <p class="text-sm text-surface-600 dark:text-surface-400">Received: {{ formatDate(quote.created_at) }}</p>
              </div>
              <span
                :class="[
                  'px-3 py-1 rounded-full text-xs font-medium',
                  quoteStatusClasses[quote.status] || 'bg-gray-100 dark:bg-surface-700 text-gray-800 dark:text-surface-100'
                ]"
              >
                {{ formatQuoteStatus(quote.status) }}
              </span>
            </div>
            <div class="grid grid-cols-3 gap-4 text-sm">
              <div>
                <p class="text-surface-600 dark:text-surface-400">Unit Price</p>
                <p class="font-medium text-surface-900 dark:text-surface-50">{{ quote.unit_price }}</p>
              </div>
              <div>
                <p class="text-surface-600 dark:text-surface-400">Total Price</p>
                <p class="font-medium text-surface-900 dark:text-surface-50">{{ quote.total_price }}</p>
              </div>
              <div>
                <p class="text-surface-600 dark:text-surface-400">Delivery Days</p>
                <p class="font-medium text-surface-900 dark:text-surface-50">{{ quote.delivery_days }}</p>
              </div>
            </div>
            <p v-if="quote.notes" class="mt-3 text-sm text-surface-700 dark:text-surface-300">{{ quote.notes }}</p>
          </div>
        </div>
      </div>

      <!-- Notes -->
      <div v-if="rfq.notes" class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Special Requirements</h3>
        <p class="text-surface-700 dark:text-surface-300 whitespace-pre-wrap">{{ rfq.notes }}</p>
      </div>
    </div>

    <p v-if="error" class="text-sm text-red-700 dark:text-red-300 mt-4">{{ error }}</p>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'
import { useRouteId } from '@/composables/useRouteId'
const routeId = useRouteId()
const rfq = ref({})
const quotes = ref([])
const suppliers = ref([])
const loading = ref(true)
const error = ref('')

// Chantier 19: RFQService only ever sets `draft`/`sent`/`closed`.
const statusClasses = {
  draft: 'bg-surface-100 dark:bg-surface-700 text-surface-900 dark:text-surface-100',
  sent: 'bg-blue-100 text-blue-800',
  closed: 'bg-green-100 text-green-800'
}

// Chantier 19: SupplierQuote::accept()/reject() set `accepted`/`rejected`,
// but RFQService::recordSupplierQuote() always creates a quote with status
// `submitted` (never `pending`, the DB column's unused default) — `pending`
// was dead here.
const quoteStatusClasses = {
  submitted: 'bg-yellow-100 text-yellow-800',
  accepted: 'bg-green-100 text-green-800',
  rejected: 'bg-red-100 text-red-800'
}

const formatStatus = (status) => {
  const statusMap = {
    draft: 'Draft',
    sent: 'Sent',
    closed: 'Closed'
  }
  return statusMap[status] || status
}

const formatQuoteStatus = (status) => {
  const statusMap = {
    submitted: 'Submitted',
    accepted: 'Accepted',
    rejected: 'Rejected'
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

// Chantier 19: RFQ has no `suppliers` relation/attribute at all (only
// `quotes`, each carrying its own `supplier`) — `rfq.value.suppliers` was
// always undefined, so "Suppliers" always showed "No suppliers added yet"
// even after a real issue. Derived from the real quotes instead (unique by
// supplier id), which is exactly who the RFQ was actually issued to.
const loadRFQ = async () => {
  try {
    const { data } = await axios.get(`/api/v1/achats/rfqs/${routeId.value}`)
    rfq.value = data
    quotes.value = data.quotes || []
    const seen = new Set()
    suppliers.value = quotes.value
      .map((q) => q.supplier)
      .filter((s) => s && !seen.has(s.id) && seen.add(s.id))
  } catch (err) {
    console.error('Failed to load RFQ:', err)
    error.value = 'An error occurred while loading the RFQ'
  } finally {
    loading.value = false
  }
}

// Documented gap (Chantier 19): see RFQs/Index.vue's issueRFQ() docblock —
// this button has no supplier-selection UI, so the real endpoint's required
// `supplier_ids` is never sent.
const issueRFQ = async () => {
  if (!confirm('Issue this RFQ to suppliers?')) return

  try {
    await axios.post(`/api/v1/achats/rfqs/${routeId.value}/issue`)
    loadRFQ()
  } catch (err) {
    console.error('Failed to issue RFQ:', err)
    error.value = err.response?.data?.message || 'Failed to issue RFQ — no suppliers selected.'
  }
}

const goToComparison = () => {
  window.location.href = `/rfqs/${routeId.value}/compare`
}

onMounted(() => {
  loadRFQ()
})
</script>
