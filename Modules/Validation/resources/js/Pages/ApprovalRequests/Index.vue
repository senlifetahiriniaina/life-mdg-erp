<template>
  <AppLayout>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">Pending Approvals</h1>
        <p class="mt-2 text-surface-600 dark:text-surface-400">Review and approve pending requests</p>
      </div>
    </div>

    <!-- Summary Cards (computed from the current page's results) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
        <div class="text-sm font-medium text-surface-600 dark:text-surface-400">On this page</div>
        <div class="mt-2 text-3xl font-bold text-surface-900 dark:text-surface-50">{{ pagination?.total ?? approvals.length }}</div>
      </div>
      <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
        <div class="text-sm font-medium text-surface-600 dark:text-surface-400">Awaiting Your Decision</div>
        <div class="mt-2 text-3xl font-bold text-primary-700 dark:text-primary-300">{{ awaitingMyActionCount }}</div>
      </div>
      <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
        <div class="text-sm font-medium text-surface-600 dark:text-surface-400">Approved</div>
        <div class="mt-2 text-3xl font-bold text-green-700 dark:text-green-300">{{ approvedCount }}</div>
      </div>
    </div>

    <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow">
      <div class="p-6 border-b border-gray-200 dark:border-surface-700">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div class="relative">
            <input
              v-model="search"
              type="text"
              placeholder="Search by request number or requester..."
              class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
            />
          </div>
          <select
            v-model="filters.status"
            class="px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500"
          >
            <option value="">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
            <option value="delegated">Delegated</option>
          </select>
          <select
            v-model="filters.module"
            class="px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500"
          >
            <option value="">All Modules</option>
            <option value="Achats">Purchase Orders</option>
            <option value="Accounting">Invoices</option>
            <option value="HR">HR Requests</option>
          </select>
          <button
            @click="loadApprovals"
            class="px-4 py-2 bg-gray-200 text-surface-700 dark:text-surface-300 rounded-lg hover:bg-gray-300"
          >
            Search
          </button>
        </div>
      </div>

      <table class="w-full">
        <thead class="bg-gray-50 dark:bg-surface-800 dark:bg-surface-800 border-b border-gray-200 dark:border-surface-700">
          <tr>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Request</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Module</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Requester</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Progress</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Status</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Submitted</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!loading && filteredApprovals.length === 0" class="border-b border-gray-200 dark:border-surface-700">
            <td colspan="7" class="px-6 py-8 text-center text-surface-500 dark:text-surface-400">
              No pending approvals.
            </td>
          </tr>
          <tr v-for="approval in filteredApprovals" :key="approval.id" class="border-b border-gray-200 dark:border-surface-700 hover:bg-gray-50 dark:bg-surface-800 dark:bg-surface-800">
            <td class="px-6 py-4 text-sm font-mono font-medium text-surface-900 dark:text-surface-50">{{ approval.approvable_type }} #{{ approval.approvable_id }}</td>
            <td class="px-6 py-4 text-sm text-surface-600 dark:text-surface-400">{{ approval.workflow?.module_name }}</td>
            <td class="px-6 py-4 text-sm text-surface-600 dark:text-surface-400">{{ approval.requester?.name }}</td>
            <td class="px-6 py-4 text-sm font-medium text-surface-900 dark:text-surface-50">Level {{ approval.current_level }}/{{ approval.total_levels }}</td>
            <td class="px-6 py-4 text-sm">
              <span
                :class="[
                  'px-3 py-1 rounded-full text-xs font-medium',
                  statusClasses[approval.status] || 'bg-gray-100 dark:bg-surface-700 text-gray-800 dark:text-surface-100'
                ]"
              >
                {{ formatStatus(approval.status) }}
              </span>
            </td>
            <td class="px-6 py-4 text-sm text-surface-600 dark:text-surface-400">{{ formatDate(approval.created_at) }}</td>
            <td class="px-6 py-4 text-sm space-x-2">
              <Link :href="`/approval-requests/${approval.id}`" class="text-primary-700 dark:text-primary-300 hover:underline">
                Review
              </Link>
              <button
                v-if="approval.status === 'pending' && approval.awaiting_my_action"
                @click="approveRequest(approval.id)"
                class="text-green-700 dark:text-green-300 hover:underline"
              >
                Approve
              </button>
              <button
                v-if="approval.status === 'pending' && approval.awaiting_my_action"
                @click="rejectRequest(approval.id)"
                class="text-red-700 dark:text-red-300 hover:underline"
              >
                Reject
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="pagination" class="flex items-center justify-between">
      <div class="text-sm text-surface-600 dark:text-surface-400">
        Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} approvals
      </div>
      <div class="space-x-2">
        <button
          v-if="pagination.current_page > 1"
          @click="currentPage = pagination.current_page - 1; loadApprovals()"
          class="px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg hover:bg-gray-50 dark:bg-surface-800 dark:bg-surface-800"
        >
          Previous
        </button>
        <button
          v-if="pagination.current_page < pagination.last_page"
          @click="currentPage = pagination.current_page + 1; loadApprovals()"
          class="px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg hover:bg-gray-50 dark:bg-surface-800 dark:bg-surface-800"
        >
          Next
        </button>
      </div>
    </div>
  </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

const approvals = ref([])
const loading = ref(false)
const pagination = ref(null)
const search = ref('')
const currentPage = ref(1)
const filters = ref({
  status: '',
  module: ''
})

const statusClasses = {
  pending: 'bg-yellow-100 text-yellow-800',
  approved: 'bg-green-100 text-green-800',
  rejected: 'bg-red-100 text-red-800',
  delegated: 'bg-blue-100 text-blue-800'
}

// The backend doesn't support free-text search, so it's applied client-side
// over the currently loaded page rather than left as a decorative no-op input.
const filteredApprovals = computed(() => {
  if (!search.value) return approvals.value
  const q = search.value.toLowerCase()
  return approvals.value.filter(a =>
    `${a.approvable_type} ${a.approvable_id}`.toLowerCase().includes(q) ||
    (a.requester?.name ?? '').toLowerCase().includes(q)
  )
})

const awaitingMyActionCount = computed(() => approvals.value.filter(a => a.awaiting_my_action).length)
const approvedCount = computed(() => approvals.value.filter(a => a.status === 'approved').length)

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

const loadApprovals = async () => {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/validation/approval-requests', {
      params: {
        page: currentPage.value,
        status: filters.value.status || undefined,
        module: filters.value.module || undefined,
      },
    })
    approvals.value = data.data
    pagination.value = { from: data.from, to: data.to, total: data.total, current_page: data.current_page, last_page: data.last_page }
  } catch (error) {
    console.error('Failed to load approvals:', error)
  } finally {
    loading.value = false
  }
}

const approveRequest = async (id) => {
  if (!confirm('Approve this request?')) return

  try {
    await axios.post(`/api/v1/validation/approval-requests/${id}/approve`, { comments: '' })
    await loadApprovals()
  } catch (error) {
    console.error('Failed to approve request:', error)
  }
}

const rejectRequest = async (id) => {
  const reason = prompt('Please provide a reason for rejection:')
  if (!reason) return

  try {
    await axios.post(`/api/v1/validation/approval-requests/${id}/reject`, { reason })
    await loadApprovals()
  } catch (error) {
    console.error('Failed to reject request:', error)
  }
}

onMounted(() => {
  loadApprovals()
})
</script>
