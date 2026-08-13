<template>
  <AppLayout>
    <template #header>
      <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">Timesheets</h1>
    </template>

    <div class="bg-white dark:bg-surface-800 shadow-sm rounded-lg p-6">
      <!-- Stats -->
      <div class="grid grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-4">
          <p class="text-sm text-surface-600 dark:text-surface-400">Total Sheets</p>
          <p class="text-3xl font-bold text-primary-700 dark:text-primary-300">{{ stats.total }}</p>
        </div>
        <div class="bg-gradient-to-br from-amber-50 to-amber-100 rounded-lg p-4">
          <p class="text-sm text-surface-600 dark:text-surface-400">Pending</p>
          <p class="text-3xl font-bold text-amber-600">{{ stats.draft }}</p>
        </div>
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-4">
          <p class="text-sm text-surface-600 dark:text-surface-400">Submitted</p>
          <p class="text-3xl font-bold text-primary-700 dark:text-primary-300">{{ stats.submitted }}</p>
        </div>
        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg p-4">
          <p class="text-sm text-surface-600 dark:text-surface-400">Approved</p>
          <p class="text-3xl font-bold text-green-700 dark:text-green-300">{{ stats.approved }}</p>
        </div>
      </div>

      <!-- Filters -->
      <div class="mb-6 flex gap-4 items-end">
        <div class="flex-1">
          <label for="search" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">Search</label>
          <input id="search"
            v-model="filters.search"
            type="text"
            placeholder="Search by employee name..."
            class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          />
        </div>
        <div class="w-40">
          <label for="status" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">Status</label>
          <select id="status"
            v-model="filters.status"
            class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-2 focus:ring-blue-500"
          >
            <option value="">All</option>
            <option value="draft">Draft</option>
            <option value="submitted">Submitted</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
          </select>
        </div>
        <Link
          href="/timesheets/sheets/create"
          class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
        >
          New Timesheet
        </Link>
      </div>

      <!-- DataTable -->
      <DataTable
        :value="sheets"
        :lazy="true"
        :paginator="true"
        :rows="15"
        :total-records="totalRecords"
        :loading="loading"
        @page="onPage"
        @sort="onSort"
        striped-rows
        responsive-layout="scroll"
        class="w-full"
      >
        <Column field="employee_id" header="Employee" sortable style="width: 20%">
          <template #body="{ data }">
            <span class="font-semibold">{{ data.employee?.name }}</span>
          </template>
        </Column>

        <Column field="period_start" header="Period" sortable style="width: 15%">
          <template #body="{ data }">
            <span class="text-sm">
              {{ formatDate(data.period_start) }} - {{ formatDate(data.period_end) }}
            </span>
          </template>
        </Column>

        <Column field="total_hours" header="Total Hours" sortable style="width: 12%">
          <template #body="{ data }">
            <span class="font-semibold">{{ data.total_hours }}</span>
          </template>
        </Column>

        <Column field="billable_hours" header="Billable Hours" style="width: 12%">
          <template #body="{ data }">
            <span class="text-sm">{{ data.billable_hours }}</span>
          </template>
        </Column>

        <Column field="status" header="Status" sortable style="width: 12%">
          <template #body="{ data }">
            <Tag :value="data.status" :severity="statusSeverity(data.status)" />
          </template>
        </Column>

        <Column field="submitted_at" header="Submitted" style="width: 14%">
          <template #body="{ data }">
            <span class="text-sm">{{ data.submitted_at ? formatDate(data.submitted_at) : '-' }}</span>
          </template>
        </Column>

        <Column header="Actions" style="width: 15%">
          <template #body="{ data }">
            <div class="flex gap-2">
              <Link
                :href="`/timesheets/sheets/${data.id}`"
                class="text-primary-700 dark:text-primary-300 hover:text-blue-800"
                title="View"
              >
                <i class="pi pi-eye text-lg"></i>
              </Link>
              <Link
                v-if="data.status === 'draft'"
                :href="`/timesheets/sheets/${data.id}/edit`"
                class="text-amber-600 hover:text-amber-800"
                title="Edit"
              >
                <i class="pi pi-pencil text-lg"></i>
              </Link>
              <button
                v-if="canApprove && data.status === 'submitted'"
                @click="approveSheet(data.id)"
                class="text-green-700 dark:text-green-300 hover:text-green-800 text-sm font-medium"
                title="Approve"
              >
                Approve
              </button>
            </div>
          </template>
        </Column>
      </DataTable>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'

const page = usePage()
const user = page.props.auth.user

const sheets = ref([])
const loading = ref(false)
const totalRecords = ref(0)
const lazyState = reactive({
  first: 0,
  rows: 15,
  sortField: null,
  sortOrder: null,
})

const filters = reactive({
  search: '',
  status: '',
})

const stats = reactive({
  total: 0,
  draft: 0,
  submitted: 0,
  approved: 0,
})

const canApprove = computed(() => user.roles?.some(r => ['admin', 'manager'].includes(r)))

const formatDate = (date) => {
  return new Date(date).toLocaleDateString()
}

const statusSeverity = (status) => {
  return {
    draft: 'warning',
    submitted: 'info',
    approved: 'success',
    rejected: 'danger',
  }[status] || 'secondary'
}

const loadSheets = async () => {
  loading.value = true
  try {
    const response = await axios.get('/api/v1/timesheets/sheets', {
      params: {
        page: lazyState.first / lazyState.rows + 1,
        per_page: lazyState.rows,
        search: filters.search,
        status: filters.status,
        sort: lazyState.sortField ? (lazyState.sortOrder === -1 ? '-' : '') + lazyState.sortField : null,
      },
    })
    sheets.value = response.data.data
    totalRecords.value = response.data.total

    // Load stats
    const allResponse = await axios.get('/api/v1/timesheets/sheets?per_page=10000')
    const all = allResponse.data.data
    stats.total = all.length
    stats.draft = all.filter(s => s.status === 'draft').length
    stats.submitted = all.filter(s => s.status === 'submitted').length
    stats.approved = all.filter(s => s.status === 'approved').length
  } catch (error) {
    console.error('Error loading sheets:', error)
  } finally {
    loading.value = false
  }
}

const onPage = (event) => {
  lazyState.first = event.first
  lazyState.rows = event.rows
  loadSheets()
}

const onSort = (event) => {
  lazyState.sortField = event.sortField
  lazyState.sortOrder = event.sortOrder
  loadSheets()
}

const approveSheet = async (id) => {
  if (!confirm('Approve this timesheet?')) return
  try {
    await axios.post(`/api/v1/timesheets/sheets/${id}/approve`)
    loadSheets()
  } catch (error) {
    console.error('Error approving sheet:', error)
  }
}

onMounted(() => {
  loadSheets()
})
</script>
