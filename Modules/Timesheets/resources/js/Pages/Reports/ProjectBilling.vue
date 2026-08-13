<template>
  <AppLayout>
    <template #header>
      <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">Project Billing Report</h1>
    </template>

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
          <label for="project" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">Project</label>
          <select id="project"
            v-model="filters.project_id"
            @change="loadReport"
            class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          >
            <option value="">All Projects</option>
            <option v-for="project in projects" :key="project.id" :value="project.id">
              {{ project.name }}
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
          <p class="text-sm text-surface-600 dark:text-surface-400">Total Billable Hours</p>
          <p class="text-4xl font-bold text-primary-700 dark:text-primary-300 mt-2">{{ stats.billableHours }}</p>
        </div>
        <div class="bg-white dark:bg-surface-800 rounded-lg shadow-sm p-6">
          <p class="text-sm text-surface-600 dark:text-surface-400">Total Billable Amount</p>
          <p class="text-4xl font-bold text-green-700 dark:text-green-300 mt-2">${{ stats.billableAmount }}</p>
        </div>
        <div class="bg-white dark:bg-surface-800 rounded-lg shadow-sm p-6">
          <p class="text-sm text-surface-600 dark:text-surface-400">Avg Hourly Rate</p>
          <p class="text-4xl font-bold text-violet-700 dark:text-violet-300 mt-2">${{ stats.avgRate }}/hr</p>
        </div>
        <div class="bg-white dark:bg-surface-800 rounded-lg shadow-sm p-6">
          <p class="text-sm text-surface-600 dark:text-surface-400">Projects</p>
          <p class="text-4xl font-bold text-amber-600 mt-2">{{ projectCount }}</p>
        </div>
      </div>

      <!-- Billing by Project -->
      <div class="bg-white dark:bg-surface-800 rounded-lg shadow-sm p-6">
        <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Billing by Project</h2>
        <DataTable
          :value="projectData"
          striped-rows
          :paginator="true"
          :rows="10"
          class="w-full"
        >
          <Column field="project_name" header="Project" sortable>
            <template #body="{ data }">
              <span class="font-semibold">{{ data.project_name || 'General' }}</span>
            </template>
          </Column>
          <Column field="billable_hours" header="Billable Hours">
            <template #body="{ data }">
              {{ data.billable_hours }}
            </template>
          </Column>
          <Column field="avg_hourly_rate" header="Avg Rate">
            <template #body="{ data }">
              ${{ data.avg_hourly_rate }}/hr
            </template>
          </Column>
          <Column field="billable_amount" header="Total Amount" sortable>
            <template #body="{ data }">
              <span class="font-bold text-green-700 dark:text-green-300">${{ data.billable_amount }}</span>
            </template>
          </Column>
          <Column field="employee_count" header="Employees">
            <template #body="{ data }">
              {{ data.employee_count }}
            </template>
          </Column>
          <Column field="entry_count" header="Entries">
            <template #body="{ data }">
              {{ data.entry_count }}
            </template>
          </Column>
        </DataTable>
      </div>

      <!-- Billing by Employee per Project -->
      <div class="bg-white dark:bg-surface-800 rounded-lg shadow-sm p-6">
        <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Billing by Employee & Project</h2>
        <DataTable
          :value="employeeProjectData"
          striped-rows
          :paginator="true"
          :rows="15"
          class="w-full"
        >
          <Column field="project_name" header="Project">
            <template #body="{ data }">
              {{ data.project_name || 'General' }}
            </template>
          </Column>
          <Column field="employee_name" header="Employee">
            <template #body="{ data }">
              <span class="font-semibold">{{ data.employee_name }}</span>
            </template>
          </Column>
          <Column field="billable_hours" header="Hours">
            <template #body="{ data }">
              {{ data.billable_hours }}
            </template>
          </Column>
          <Column field="hourly_rate" header="Rate">
            <template #body="{ data }">
              ${{ data.hourly_rate }}/hr
            </template>
          </Column>
          <Column field="amount" header="Amount">
            <template #body="{ data }">
              <span class="font-semibold">${{ data.amount }}</span>
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

const filters = reactive({
  from_date: new Date(new Date().setDate(new Date().getDate() - 30)).toISOString().split('T')[0],
  to_date: new Date().toISOString().split('T')[0],
  project_id: '',
})

const stats = reactive({
  billableHours: 0,
  billableAmount: 0,
  avgRate: 0,
})

const projects = ref([])
const projectData = ref([])
const employeeProjectData = ref([])

const projectCount = computed(() => projectData.value.length)

const loadReport = async () => {
  try {
    const response = await axios.get('/api/v1/timesheets/reports/project-billing', {
      params: {
        from_date: filters.from_date,
        to_date: filters.to_date,
        project_id: filters.project_id,
      },
    })
    const data = response.data
    stats.billableHours = data.total_billable_hours || 0
    stats.billableAmount = (data.total_billable_amount || 0).toFixed(2)
    stats.avgRate = (data.avg_hourly_rate || 0).toFixed(2)
    projectData.value = data.by_project || []
    employeeProjectData.value = data.by_employee_project || []
  } catch (error) {
    console.error('Error loading report:', error)
  }
}

const exportReport = () => {
  alert('Export functionality not yet implemented')
}

onMounted(async () => {
  try {
    const response = await axios.get('/api/v1/projects/projects?per_page=999')
    projects.value = response.data.data
  } catch (error) {
    console.error('Error loading projects:', error)
  }
  loadReport()
})
</script>
