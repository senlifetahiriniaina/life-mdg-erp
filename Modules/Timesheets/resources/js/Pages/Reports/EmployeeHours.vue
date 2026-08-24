<template>
  <AppLayout>
    <template #header>
      <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">Employee Hours Report</h1>
    </template>

    <AIAssistantPanel v-if="guidance" :guidance="guidance" />

    <div class="space-y-6">
      <!-- Filters -->
      <div class="bg-white dark:bg-surface-800 rounded-lg shadow-sm p-4 flex gap-4 items-end">
        <div class="flex-1">
          <label for="from-date" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">From Date</label>
          <input id="from-date"
            v-model="filters.from_date"
            type="date"
            @change="loadReport"
            class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          />
        </div>
        <div class="flex-1">
          <label for="to-date" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">To Date</label>
          <input id="to-date"
            v-model="filters.to_date"
            type="date"
            @change="loadReport"
            class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          />
        </div>
        <div class="flex-1">
          <label for="employee" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">Employee</label>
          <select id="employee"
            v-model="filters.employee_id"
            @change="loadReport"
            class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          >
            <option value="">All Employees</option>
            <option v-for="emp in employees" :key="emp.id" :value="emp.id">
              {{ emp.name }}
            </option>
          </select>
        </div>
        <div>
          <button
            @click="exportReport"
            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition"
          >
            Export
          </button>
        </div>
      </div>

      <!-- Summary Stats -->
      <div class="grid grid-cols-4 gap-4">
        <div class="bg-white dark:bg-surface-800 rounded-lg shadow-sm p-6">
          <p class="text-sm text-surface-600 dark:text-surface-400">Total Hours</p>
          <p class="text-4xl font-bold text-primary-700 dark:text-primary-300 mt-2">{{ stats.totalHours }}</p>
        </div>
        <div class="bg-white dark:bg-surface-800 rounded-lg shadow-sm p-6">
          <p class="text-sm text-surface-600 dark:text-surface-400">Billable Hours</p>
          <p class="text-4xl font-bold text-green-700 dark:text-green-300 mt-2">{{ stats.billableHours }}</p>
        </div>
        <div class="bg-white dark:bg-surface-800 rounded-lg shadow-sm p-6">
          <p class="text-sm text-surface-600 dark:text-surface-400">Non-Billable</p>
          <p class="text-4xl font-bold text-amber-600 mt-2">{{ stats.nonBillableHours }}</p>
        </div>
        <div class="bg-white dark:bg-surface-800 rounded-lg shadow-sm p-6">
          <p class="text-sm text-surface-600 dark:text-surface-400">Billable %</p>
          <p class="text-4xl font-bold text-violet-700 dark:text-violet-300 mt-2">{{ billablePercent }}%</p>
        </div>
      </div>

      <!-- Hours by Employee -->
      <div class="bg-white dark:bg-surface-800 rounded-lg shadow-sm p-6">
        <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Hours by Employee</h2>
        <DataTable
          :value="employeeData"
          striped-rows
          :paginator="true"
          :rows="10"
          class="w-full"
        >
          <Column field="name" header="Employee">
            <template #body="{ data }">
              <span class="font-semibold">{{ data.name }}</span>
            </template>
          </Column>
          <Column field="total_hours" header="Total Hours" sortable>
            <template #body="{ data }">
              <span class="font-semibold">{{ data.total_hours }}</span>
            </template>
          </Column>
          <Column field="billable_hours" header="Billable Hours">
            <template #body="{ data }">
              {{ data.billable_hours }}
            </template>
          </Column>
          <Column field="non_billable_hours" header="Non-Billable">
            <template #body="{ data }">
              {{ data.non_billable_hours }}
            </template>
          </Column>
          <Column field="billable_amount" header="Billable Amount">
            <template #body="{ data }">
              <span class="font-semibold">${{ data.billable_amount }}</span>
            </template>
          </Column>
          <Column field="avg_hourly_rate" header="Avg Rate">
            <template #body="{ data }">
              ${{ data.avg_hourly_rate }}/hr
            </template>
          </Column>
        </DataTable>
      </div>

      <!-- Hours by Project -->
      <div class="bg-white dark:bg-surface-800 rounded-lg shadow-sm p-6">
        <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Hours by Project</h2>
        <DataTable
          :value="projectData"
          striped-rows
          :paginator="true"
          :rows="10"
          class="w-full"
        >
          <Column field="project_name" header="Project">
            <template #body="{ data }">
              <span class="font-semibold">{{ data.project_name || 'General' }}</span>
            </template>
          </Column>
          <Column field="total_hours" header="Total Hours" sortable>
            <template #body="{ data }">
              {{ data.total_hours }}
            </template>
          </Column>
          <Column field="billable_hours" header="Billable Hours">
            <template #body="{ data }">
              {{ data.billable_hours }}
            </template>
          </Column>
          <Column field="employee_count" header="Employees">
            <template #body="{ data }">
              {{ data.employee_count }}
            </template>
          </Column>
          <Column field="billable_amount" header="Billable Amount">
            <template #body="{ data }">
              <span class="font-semibold">${{ data.billable_amount }}</span>
            </template>
          </Column>
        </DataTable>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'

const { guidance } = useAiAssistant('Timesheets', 'view_reports')

const filters = reactive({
  from_date: new Date(new Date().setDate(new Date().getDate() - 30)).toISOString().split('T')[0],
  to_date: new Date().toISOString().split('T')[0],
  employee_id: '',
})

const stats = reactive({
  totalHours: 0,
  billableHours: 0,
  nonBillableHours: 0,
})

const employees = ref([])
const employeeData = ref([])
const projectData = ref([])

const billablePercent = computed(() => {
  if (stats.totalHours === 0) return 0
  return Math.round((stats.billableHours / stats.totalHours) * 100)
})

const loadReport = async () => {
  try {
    const response = await axios.get('/api/v1/timesheets/reports/employee-hours', {
      params: {
        from_date: filters.from_date,
        to_date: filters.to_date,
        employee_id: filters.employee_id,
      },
    })
    const data = response.data
    stats.totalHours = data.total_hours || 0
    stats.billableHours = data.billable_hours || 0
    stats.nonBillableHours = data.non_billable_hours || 0
    employeeData.value = data.by_employee || []
    projectData.value = data.by_project || []
  } catch (error) {
    console.error('Error loading report:', error)
  }
}

const exportReport = () => {
  alert('Export functionality not yet implemented')
}

onMounted(async () => {
  try {
    const response = await axios.get('/api/v1/hr/employees?per_page=999')
    employees.value = response.data.data
  } catch (error) {
    console.error('Error loading employees:', error)
  }
  loadReport()
})
</script>
