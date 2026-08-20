<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">Requests for Quotation</h1>
        <p class="mt-2 text-surface-600 dark:text-surface-400">Send RFQs to suppliers and compare quotes</p>
      </div>
      <Link href="/rfqs/create" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
        + New RFQ
      </Link>
    </div>

    <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow">
      <div class="p-6 border-b border-gray-200 dark:border-surface-700">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div class="relative">
            <input
              v-model="search"
              type="text"
              placeholder="Search by RFQ number..."
              class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
            />
          </div>
          <select
            v-model="filters.status"
            class="px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500"
          >
            <option value="">All Statuses</option>
            <option value="draft">Draft</option>
            <option value="sent">Sent</option>
            <option value="closed">Closed</option>
          </select>
          <div></div>
          <button
            @click="loadRFQs"
            class="px-4 py-2 bg-gray-200 text-surface-700 dark:text-surface-300 rounded-lg hover:bg-gray-300"
          >
            Search
          </button>
        </div>
      </div>

      <table class="w-full">
        <thead class="bg-gray-50 dark:bg-surface-800 dark:bg-surface-800 border-b border-gray-200 dark:border-surface-700">
          <tr>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">RFQ Number</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Item Description</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Issued Date</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Response Deadline</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Quotes Received</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Status</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!loading && rfqs.length === 0" class="border-b border-gray-200 dark:border-surface-700">
            <td colspan="7" class="px-6 py-8 text-center text-surface-500 dark:text-surface-400">
              No RFQs found. <Link href="/rfqs/create" class="text-primary-700 dark:text-primary-300 hover:underline">Create one now</Link>
            </td>
          </tr>
          <tr v-for="rfq in rfqs" :key="rfq.id" class="border-b border-gray-200 dark:border-surface-700 hover:bg-gray-50 dark:bg-surface-800 dark:bg-surface-800">
            <td class="px-6 py-4 text-sm font-mono font-medium text-surface-900 dark:text-surface-50">{{ rfq.rfq_number }}</td>
            <td class="px-6 py-4 text-sm text-surface-600 dark:text-surface-400">{{ rfq.description }}</td>
            <td class="px-6 py-4 text-sm text-surface-600 dark:text-surface-400">{{ formatDate(rfq.issued_date) }}</td>
            <td class="px-6 py-4 text-sm text-surface-600 dark:text-surface-400">{{ formatDate(rfq.deadline_date) }}</td>
            <td class="px-6 py-4 text-sm">
              <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                {{ rfq.quotes_count || 0 }}
              </span>
            </td>
            <td class="px-6 py-4 text-sm">
              <span
                :class="[
                  'px-3 py-1 rounded-full text-xs font-medium',
                  statusClasses[rfq.status] || 'bg-gray-100 dark:bg-surface-700 text-gray-800 dark:text-surface-100'
                ]"
              >
                {{ formatStatus(rfq.status) }}
              </span>
            </td>
            <td class="px-6 py-4 text-sm space-x-2">
              <Link :href="`/rfqs/${rfq.id}`" class="text-primary-700 dark:text-primary-300 hover:underline">
                View
              </Link>
              <Link v-if="rfq.status === 'draft'" :href="`/rfqs/${rfq.id}/edit`" class="text-primary-700 dark:text-primary-300 hover:underline">
                Edit
              </Link>
              <button
                v-if="rfq.status === 'draft'"
                @click="issueRFQ(rfq.id)"
                class="text-green-700 dark:text-green-300 hover:underline"
              >
                Issue
              </button>
              <button
                v-if="rfq.status === 'sent'"
                @click="evaluateQuotes(rfq.id)"
                class="text-violet-700 dark:text-violet-300 hover:underline"
              >
                Compare
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="pagination" class="flex items-center justify-between">
      <div class="text-sm text-surface-600 dark:text-surface-400">
        Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} RFQs
      </div>
      <div class="space-x-2">
        <button
          v-if="pagination.current_page > 1"
          @click="currentPage = pagination.current_page - 1; loadRFQs()"
          class="px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg hover:bg-gray-50 dark:bg-surface-800 dark:bg-surface-800"
        >
          Previous
        </button>
        <button
          v-if="pagination.current_page < pagination.last_page"
          @click="currentPage = pagination.current_page + 1; loadRFQs()"
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

const rfqs = ref([])
const loading = ref(false)
const pagination = ref(null)
const search = ref('')
const currentPage = ref(1)
const filters = ref({
  status: ''
})

// Chantier 19: RFQService only ever sets `draft`/`sent`/`closed` (issueRFQ()/
// closeRfq()) — `issued`/`quotes_received`/`evaluated` were never real
// values, so the "Compare Quotes" action could never appear after issuing
// a real RFQ (its status becomes `sent`, which matched none of these).
const statusClasses = {
  draft: 'bg-surface-100 dark:bg-surface-700 text-surface-900 dark:text-surface-100',
  sent: 'bg-blue-100 text-blue-800',
  closed: 'bg-green-100 text-green-800'
}

const formatStatus = (status) => {
  const statusMap = {
    draft: 'Draft',
    sent: 'Sent',
    closed: 'Closed'
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

const loadRFQs = async () => {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      per_page: 15,
      search: search.value,
    }
    // Chantier 19: was reading `filters.status` on the ref object itself
    // instead of `filters.value.status` — always undefined, so the status
    // dropdown silently had no effect on the list.
    if (filters.value.status) {
      params.status = filters.value.status
    }

    const { data } = await axios.get('/api/v1/achats/rfqs', { params })
    rfqs.value = data.data
    pagination.value = data.meta
  } catch (error) {
    console.error('Failed to load RFQs:', error)
  } finally {
    loading.value = false
  }
}

// Documented gap (Chantier 19): RFQController::issue() requires a real
// `supplier_ids` array, but neither this list page nor RFQs/Show.vue's own
// "Issue RFQ" button collects one — RFQs/Form.vue's create-time supplier
// picker is deliberately not wired to this endpoint either (see that
// controller's own docblock). Every click here will 422 until a supplier
// picker is built for this action — surfacing the failure rather than
// inventing that UI here.
const issueRFQ = async (id) => {
  if (!confirm('Issue this RFQ to suppliers?')) return

  try {
    await axios.post(`/api/v1/achats/rfqs/${id}/issue`)
    loadRFQs()
  } catch (error) {
    console.error('Failed to issue RFQ:', error)
    alert(error.response?.data?.message || 'Failed to issue RFQ — no suppliers selected.')
  }
}

const evaluateQuotes = async (id) => {
  window.location.href = `/rfqs/${id}/compare`
}

onMounted(() => {
  loadRFQs()
})
</script>
