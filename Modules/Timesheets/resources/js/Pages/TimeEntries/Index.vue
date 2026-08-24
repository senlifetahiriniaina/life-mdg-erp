<template>
  <AppLayout>
    <template #header>
      <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">Time Entries</h1>
    </template>

    <AIAssistantPanel v-if="guidance" :guidance="guidance" />

    <div class="bg-white dark:bg-surface-800 shadow-sm rounded-lg p-6">
      <!-- Stats -->
      <div class="grid grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-4">
          <p class="text-sm text-surface-600 dark:text-surface-400">Total Entries</p>
          <p class="text-3xl font-bold text-primary-700 dark:text-primary-300">{{ stats.total }}</p>
        </div>
        <div class="bg-gradient-to-br from-cyan-50 to-cyan-100 rounded-lg p-4">
          <p class="text-sm text-surface-600 dark:text-surface-400">Total Hours</p>
          <p class="text-3xl font-bold text-cyan-600">{{ stats.hours }}</p>
        </div>
        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg p-4">
          <p class="text-sm text-surface-600 dark:text-surface-400">Billable Hours</p>
          <p class="text-3xl font-bold text-green-700 dark:text-green-300">{{ stats.billableHours }}</p>
        </div>
        <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg p-4">
          <p class="text-sm text-surface-600 dark:text-surface-400">Billable Amount</p>
          <p class="text-3xl font-bold text-violet-700 dark:text-violet-300">${{ stats.billableAmount }}</p>
        </div>
      </div>

      <!-- Filters -->
      <div class="mb-6 flex gap-4 items-end">
        <div class="flex-1">
          <label for="search" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">Search</label>
          <input id="search"
            v-model="filters.search"
            type="text"
            placeholder="Search by task or project..."
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
          </select>
        </div>
        <div class="w-40">
          <label for="billable" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">Billable</label>
          <select id="billable"
            v-model="filters.billable"
            class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-2 focus:ring-blue-500"
          >
            <option value="">All</option>
            <option value="true">Billable</option>
            <option value="false">Non-Billable</option>
          </select>
        </div>
        <button
          @click="createNew"
          class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
        >
          New Entry
        </button>
      </div>

      <!-- DataTable -->
      <DataTable
        :value="entries"
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
        <Column field="entry_date" header="Date" sortable style="width: 12%">
          <template #body="{ data }">
            {{ formatDate(data.entry_date) }}
          </template>
        </Column>

        <Column field="description" header="Task" sortable style="width: 25%">
          <template #body="{ data }">
            <span class="font-semibold">{{ data.description }}</span>
          </template>
        </Column>

        <Column field="project_id" header="Project" style="width: 15%">
          <template #body="{ data }">
            <span class="text-sm">{{ data.project?.name || 'General' }}</span>
          </template>
        </Column>

        <Column field="hours_worked" header="Hours" sortable style="width: 10%">
          <template #body="{ data }">
            <span class="font-semibold">{{ data.hours_worked }}</span>
          </template>
        </Column>

        <Column field="billable" header="Billable" style="width: 10%">
          <template #body="{ data }">
            <Badge :value="data.billable ? 'Yes' : 'No'" :severity="data.billable ? 'success' : 'secondary'" />
          </template>
        </Column>

        <Column field="hourly_rate" header="Rate" style="width: 10%">
          <template #body="{ data }">
            <span class="text-sm">${{ data.hourly_rate || '-' }}</span>
          </template>
        </Column>

        <Column field="status" header="Status" sortable style="width: 10%">
          <template #body="{ data }">
            <Tag :value="data.status" :severity="statusSeverity(data.status)" />
          </template>
        </Column>

        <Column header="Actions" style="width: 8%">
          <template #body="{ data }">
            <div class="flex gap-2">
              <Link
                v-if="canEdit(data)"
                :href="`/timesheets/entries/${data.id}/edit`"
                class="text-amber-600 hover:text-amber-800"
                title="Edit"
              >
                <i class="pi pi-pencil text-lg"></i>
              </Link>
              <button
                v-if="canDelete(data)"
                @click="deleteEntry(data.id)"
                class="text-red-700 dark:text-red-300 hover:text-red-800"
                title="Delete"
              >
                <i class="pi pi-trash text-lg"></i>
              </button>
            </div>
          </template>
        </Column>
      </DataTable>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Badge from 'primevue/badge'
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'

const { guidance } = useAiAssistant('Timesheets', 'view_entries')

const entries = ref([])
const loading = ref(false)
const totalRecords = ref(0)
const lazyState = reactive({
  first: 0,
  rows: 15,
  sortField: null,
  sortOrder: null,
})

// Chantier 19 (Lot 2): allows Dashboard.vue's "Pending Approvals" quick
// action to deep-link here pre-filtered to submitted entries (this closure
// route has no Inertia controller/props to pass a query param through, so
// the initial value is read directly off the URL).
const initialParams = new URLSearchParams(window.location.search)

const filters = reactive({
  search: '',
  status: initialParams.get('status') || '',
  billable: '',
})

const stats = reactive({
  total: 0,
  hours: 0,
  billableHours: 0,
  billableAmount: 0,
})

const formatDate = (date) => {
  return new Date(date).toLocaleDateString()
}

const statusSeverity = (status) => {
  return {
    draft: 'warning',
    submitted: 'info',
    approved: 'success',
  }[status] || 'secondary'
}

// Chantier 19 (Lot 2): these compared user.id (a users.id, shared via
// Inertia's auth.user prop) against entry.employee_id (an hr_employees.id)
// — the same ID-space mismatch bug pattern already fixed on the backend
// (TimesheetEntryPolicy, TimesheetEntryController::index()). Since the
// backend already scopes the entries a non-elevated caller can even see to
// their own (see loadEntries()/GET timesheets/entries), every entry
// visible here to a non-elevated user already IS their own — so the edit
// button never needs an ownership check on the frontend, only the status
// gate the real TimesheetEntryPolicy::update()/delete() also enforce.
const canEdit = (entry) => {
  return entry.status === 'draft'
}

const canDelete = (entry) => {
  return entry.status === 'draft'
}

const loadEntries = async () => {
  loading.value = true
  try {
    const response = await axios.get('/api/v1/timesheets/entries', {
      params: {
        page: lazyState.first / lazyState.rows + 1,
        per_page: lazyState.rows,
        search: filters.search,
        status: filters.status,
        billable: filters.billable === '' ? undefined : filters.billable === 'true',
        sort: lazyState.sortField ? (lazyState.sortOrder === -1 ? '-' : '') + lazyState.sortField : null,
      },
    })
    entries.value = response.data.data
    // Chantier 19 (Lot 2): index() returns a Resource collection wrapping
    // a paginator — pagination metadata lands under top-level `meta`
    // (Laravel's default), not a flat `total` key. The pager silently
    // never rendered a real count, the same bug pattern already fixed for
    // Inventory's Warehouses/Index.vue.
    totalRecords.value = response.data.meta?.total ?? 0

    // Load stats
    const allResponse = await axios.get('/api/v1/timesheets/entries?per_page=10000')
    const all = allResponse.data.data
    stats.total = all.length
    stats.hours = all.reduce((sum, e) => sum + parseFloat(e.hours_worked || 0), 0).toFixed(2)
    const billable = all.filter(e => e.billable)
    stats.billableHours = billable.reduce((sum, e) => sum + parseFloat(e.hours_worked || 0), 0).toFixed(2)
    stats.billableAmount = billable.reduce((sum, e) => sum + parseFloat(e.billable_amount || 0), 0).toFixed(2)
  } catch (error) {
    console.error('Error loading entries:', error)
  } finally {
    loading.value = false
  }
}

const onPage = (event) => {
  lazyState.first = event.first
  lazyState.rows = event.rows
  loadEntries()
}

const onSort = (event) => {
  lazyState.sortField = event.sortField
  lazyState.sortOrder = event.sortOrder
  loadEntries()
}

const createNew = () => {
  router.get('/timesheets/entries/create')
}

const deleteEntry = async (id) => {
  if (!confirm('Delete this time entry?')) return
  try {
    await axios.delete(`/api/v1/timesheets/entries/${id}`)
    loadEntries()
  } catch (error) {
    console.error('Error deleting entry:', error)
  }
}

onMounted(() => {
  loadEntries()
})
</script>
