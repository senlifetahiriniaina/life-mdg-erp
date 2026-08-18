<template>
  <div class="max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">Compare Quotes</h1>
        <p class="mt-2 text-surface-600 dark:text-surface-400">{{ rfq.rfq_number }}</p>
      </div>
      <Link :href="`/rfqs/${rfq.id}`" class="text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:text-surface-50">
        ← Back to RFQ
      </Link>
    </div>

    <div v-if="loading" class="text-center py-8">
      <p class="text-surface-600 dark:text-surface-400">Loading quotes...</p>
    </div>

    <div v-else-if="quotes.length === 0" class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-8 text-center">
      <p class="text-surface-600 dark:text-surface-400">No quotes received yet</p>
    </div>

    <div v-else class="space-y-6">
      <!-- Quote Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="quote in quotes"
          :key="quote.id"
          :class="[
            'p-4 rounded-lg border-2 cursor-pointer transition',
            selectedQuote?.id === quote.id ? 'border-blue-500 bg-primary-50 dark:bg-primary-900/20' : 'border-gray-200 dark:border-surface-700 hover:border-gray-300 dark:border-surface-600'
          ]"
          @click="selectedQuote = quote"
         role="button" tabindex="0" @keydown.enter.prevent="selectedQuote = quote">
          <p class="font-semibold text-surface-900 dark:text-surface-50">{{ quote.supplier?.name }}</p>
          <p class="text-sm text-surface-600 dark:text-surface-400 mb-2">{{ quote.supplier?.email }}</p>
          <div class="space-y-2">
            <div class="flex justify-between">
              <span class="text-sm text-surface-600 dark:text-surface-400">Unit Price:</span>
              <span class="font-medium">{{ quote.unit_price }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-sm text-surface-600 dark:text-surface-400">Total:</span>
              <span class="text-lg font-bold text-primary-700 dark:text-primary-300">{{ quote.total_price }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-sm text-surface-600 dark:text-surface-400">Delivery:</span>
              <span class="font-medium">{{ quote.delivery_days }} days</span>
            </div>
          </div>
          <span
            :class="[
              'inline-block mt-3 px-3 py-1 rounded-full text-xs font-medium',
              quoteStatusClasses[quote.status] || 'bg-gray-100 dark:bg-surface-700 text-gray-800 dark:text-surface-100'
            ]"
          >
            {{ formatQuoteStatus(quote.status) }}
          </span>
        </div>
      </div>

      <!-- Comparison Table -->
      <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50 dark:bg-surface-800 dark:bg-surface-800 border-b border-gray-200 dark:border-surface-700">
            <tr>
              <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300 min-w-48">Criteria</th>
              <th scope="col" v-for="quote in quotes" :key="quote.id" class="px-6 py-3 text-center text-sm font-semibold text-surface-700 dark:text-surface-300 min-w-40">
                <p class="font-medium">{{ quote.supplier?.name }}</p>
                <p class="text-xs text-surface-600 dark:text-surface-400">{{ quote.supplier?.code }}</p>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr class="border-b border-gray-200 dark:border-surface-700">
              <td class="px-6 py-4 text-sm font-medium text-surface-700 dark:text-surface-300">Unit Price</td>
              <td v-for="quote in quotes" :key="quote.id" class="px-6 py-4 text-center text-sm">
                <p class="font-semibold text-surface-900 dark:text-surface-50">{{ quote.unit_price }}</p>
              </td>
            </tr>
            <tr class="border-b border-gray-200 dark:border-surface-700 bg-primary-50 dark:bg-primary-900/20">
              <td class="px-6 py-4 text-sm font-medium text-surface-700 dark:text-surface-300">Total Price</td>
              <td v-for="quote in quotes" :key="quote.id" class="px-6 py-4 text-center text-sm">
                <p class="text-lg font-bold text-primary-700 dark:text-primary-300">{{ quote.total_price }}</p>
              </td>
            </tr>
            <tr class="border-b border-gray-200 dark:border-surface-700">
              <td class="px-6 py-4 text-sm font-medium text-surface-700 dark:text-surface-300">Delivery Days</td>
              <td v-for="quote in quotes" :key="quote.id" class="px-6 py-4 text-center text-sm">
                <p class="font-semibold text-surface-900 dark:text-surface-50">{{ quote.delivery_days }}</p>
              </td>
            </tr>
            <tr class="border-b border-gray-200 dark:border-surface-700">
              <td class="px-6 py-4 text-sm font-medium text-surface-700 dark:text-surface-300">Payment Terms</td>
              <td v-for="quote in quotes" :key="quote.id" class="px-6 py-4 text-center text-sm text-surface-600 dark:text-surface-400">
                {{ quote.payment_terms || 'Standard' }}
              </td>
            </tr>
            <tr class="border-b border-gray-200 dark:border-surface-700">
              <td class="px-6 py-4 text-sm font-medium text-surface-700 dark:text-surface-300">Warranty</td>
              <td v-for="quote in quotes" :key="quote.id" class="px-6 py-4 text-center text-sm text-surface-600 dark:text-surface-400">
                {{ quote.warranty || 'Not specified' }}
              </td>
            </tr>
            <tr class="border-b border-gray-200 dark:border-surface-700">
              <td class="px-6 py-4 text-sm font-medium text-surface-700 dark:text-surface-300">Status</td>
              <td v-for="quote in quotes" :key="quote.id" class="px-6 py-4 text-center">
                <span
                  :class="[
                    'inline-block px-3 py-1 rounded-full text-xs font-medium',
                    quoteStatusClasses[quote.status] || 'bg-gray-100 dark:bg-surface-700 text-gray-800 dark:text-surface-100'
                  ]"
                >
                  {{ formatQuoteStatus(quote.status) }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Selected Quote Details -->
      <div v-if="selectedQuote" class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">{{ selectedQuote.supplier?.name }} - Detailed Quote</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
          <div>
            <p class="text-sm text-surface-600 dark:text-surface-400 mb-1">Supplier Contact</p>
            <p class="text-base font-medium text-surface-900 dark:text-surface-50">{{ selectedQuote.supplier?.contact_person || 'N/A' }}</p>
            <p class="text-sm text-surface-600 dark:text-surface-400">{{ selectedQuote.supplier?.email }}</p>
          </div>
          <div>
            <p class="text-sm text-surface-600 dark:text-surface-400 mb-1">Quote Date</p>
            <p class="text-base font-medium text-surface-900 dark:text-surface-50">{{ formatDate(selectedQuote.created_at) }}</p>
          </div>
          <div>
            <p class="text-sm text-surface-600 dark:text-surface-400 mb-1">Currency</p>
            <p class="text-base font-medium text-surface-900 dark:text-surface-50">{{ selectedQuote.currency || 'USD' }}</p>
          </div>
        </div>

        <div v-if="selectedQuote.notes" class="bg-gray-50 dark:bg-surface-800 dark:bg-surface-800 p-4 rounded-lg mb-6">
          <p class="text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">Notes from Supplier</p>
          <p class="text-sm text-surface-700 dark:text-surface-300">{{ selectedQuote.notes }}</p>
        </div>

        <!-- Action Buttons -->
        <div class="flex gap-3">
          <button
            @click="acceptQuote(selectedQuote.id)"
            :disabled="selectedQuote.status !== 'pending' || actioning"
            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50"
          >
            {{ actioning ? 'Processing...' : 'Accept Quote' }}
          </button>
          <button
            @click="rejectQuote(selectedQuote.id)"
            :disabled="selectedQuote.status !== 'pending' || actioning"
            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50"
          >
            {{ actioning ? 'Processing...' : 'Reject Quote' }}
          </button>
        </div>
      </div>

      <!-- Evaluation Summary -->
      <div class="bg-primary-50 dark:bg-primary-900/20 border border-blue-200 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Evaluation Summary</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div>
            <p class="text-sm text-surface-600 dark:text-surface-400 mb-1">Lowest Price</p>
            <p class="text-lg font-semibold text-surface-900 dark:text-surface-50">
              {{ lowestPriceQuote?.supplier?.name || 'N/A' }}
            </p>
            <p class="text-sm text-surface-600 dark:text-surface-400">{{ lowestPrice }}</p>
          </div>
          <div>
            <p class="text-sm text-surface-600 dark:text-surface-400 mb-1">Fastest Delivery</p>
            <p class="text-lg font-semibold text-surface-900 dark:text-surface-50">
              {{ fastestDeliveryQuote?.supplier?.name || 'N/A' }}
            </p>
            <p class="text-sm text-surface-600 dark:text-surface-400">{{ fastestDelivery }} days</p>
          </div>
          <div>
            <p class="text-sm text-surface-600 dark:text-surface-400 mb-1">Total Quotes</p>
            <p class="text-lg font-semibold text-surface-900 dark:text-surface-50">{{ quotes.length }}</p>
            <p class="text-sm text-surface-600 dark:text-surface-400">{{ pendingQuotesCount }} pending, {{ acceptedQuotesCount }} accepted</p>
          </div>
        </div>
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
const rfq = ref({})
const quotes = ref([])
const selectedQuote = ref(null)
const loading = ref(true)
const actioning = ref(false)
const error = ref('')

const quoteStatusClasses = {
  pending: 'bg-yellow-100 text-yellow-800',
  accepted: 'bg-green-100 text-green-800',
  rejected: 'bg-red-100 text-red-800'
}

const formatQuoteStatus = (status) => {
  const statusMap = {
    pending: 'Pending',
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

const lowestPriceQuote = computed(() => {
  return quotes.value.reduce((lowest, current) => {
    const currentPrice = parseFloat(current.total_price) || 0
    const lowestPrice = parseFloat(lowest?.total_price) || Infinity
    return currentPrice < lowestPrice ? current : lowest
  }, null)
})

const lowestPrice = computed(() => {
  return lowestPriceQuote.value?.total_price || 'N/A'
})

const fastestDeliveryQuote = computed(() => {
  return quotes.value.reduce((fastest, current) => {
    const currentDays = current.delivery_days || Infinity
    const fastestDays = fastest?.delivery_days || Infinity
    return currentDays < fastestDays ? current : fastest
  }, null)
})

const fastestDelivery = computed(() => {
  return fastestDeliveryQuote.value?.delivery_days || 'N/A'
})

const pendingQuotesCount = computed(() => {
  return quotes.value.filter(q => q.status === 'pending').length
})

const acceptedQuotesCount = computed(() => {
  return quotes.value.filter(q => q.status === 'accepted').length
})

const loadRFQAndQuotes = async () => {
  try {
    const response = await fetch(`/api/v1/achats/rfqs/${routeId.value}`, {
      headers: {
        'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]').content}`
      }
    })
    if (response.ok) {
      const data = await response.json()
      rfq.value = data
      quotes.value = data.quotes || []
      if (quotes.value.length > 0) {
        selectedQuote.value = quotes.value[0]
      }
    } else {
      error.value = 'Failed to load RFQ and quotes'
    }
  } catch (err) {
    console.error('Failed to load RFQ:', err)
    error.value = 'An error occurred while loading the RFQ'
  } finally {
    loading.value = false
  }
}

const acceptQuote = async (quoteId) => {
  if (!confirm('Accept this quote? This will create a purchase order.')) return

  actioning.value = true
  try {
    // Chantier 10: this used to call a fictional per-RFQ
    // `rfqs/{id}/accept-quote` endpoint that doesn't exist anywhere in
    // routes/api.php. The real, already-working equivalent is
    // SupplierQuoteController::accept() -> RFQService::selectWinningQuote().
    const response = await fetch(`/api/v1/achats/supplier-quotes/${quoteId}/accept`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]').content}`,
        'Content-Type': 'application/json'
      }
    })
    if (response.ok) {
      loadRFQAndQuotes()
    } else {
      error.value = 'Failed to accept quote'
    }
  } catch (err) {
    console.error('Failed to accept quote:', err)
    error.value = 'An error occurred while accepting the quote'
  } finally {
    actioning.value = false
  }
}

const rejectQuote = async (quoteId) => {
  if (!confirm('Reject this quote?')) return

  actioning.value = true
  try {
    // Chantier 10: same fix as acceptQuote() above — real equivalent is
    // SupplierQuoteController::reject() -> RFQService::rejectQuote().
    const response = await fetch(`/api/v1/achats/supplier-quotes/${quoteId}/reject`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]').content}`,
        'Content-Type': 'application/json'
      }
    })
    if (response.ok) {
      loadRFQAndQuotes()
    } else {
      error.value = 'Failed to reject quote'
    }
  } catch (err) {
    console.error('Failed to reject quote:', err)
    error.value = 'An error occurred while rejecting the quote'
  } finally {
    actioning.value = false
  }
}

onMounted(() => {
  loadRFQAndQuotes()
})
</script>
