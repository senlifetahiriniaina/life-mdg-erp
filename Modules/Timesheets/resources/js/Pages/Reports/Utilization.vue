<template>
  <AppLayout>
    <template #header>
      <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">Utilization Report</h1>
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
          <label for="department" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">Department</label>
          <select id="department"
            v-model="filters.department"
            @change="loadReport"
            class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          >
            <option value="">All Departments</option>
            <option v-for="dept in departments" :key="dept" :value="dept">
              {{ dept }}
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
          <p class="text-sm text-surface-600 dark:text-surface-400">Avg Utilization</p>
          <p class="text-4xl font-bold text-primary-700 dark:text-primary-300 mt-2">{{ stats.avgUtilization }}%</p>
        </div>
        <div class="bg-white dark:bg-surface-800 rounded-lg shadow-sm p-6">
          <p class="text-sm text-surface-600 dark:text-surface-400">High Utilization</p>
          <p class="text-4xl font-bold text-green-700 dark:text-green-300 mt-2">{{ stats.highUtilization }}</p>
        </div>
        <div class="bg-white dark:bg-surface-800 rounded-lg shadow-sm p-6">
          <p class="text-sm text-surface-600 dark:text-surface-400">Under Utilized</p>
          <p class="text-4xl font-bold text-amber-600 mt-2">{{ stats.underUtilized }}</p>
        </div>
        <div class="bg-white dark:bg-surface-800 rounded-lg shadow-sm p-6">
          <p class="text-sm text-surface-600 dark:text-surface-400">Employees</p>
          <p class="text-4xl font-bold text-violet-700 dark:text-violet-300 mt-2">{{ employeeCount }}</p>
        </div>
      </div>

      <!-- Utilization by Employee -->
      <div class="bg-white dark:bg-surface-800 rounded-lg shadow-sm p-6">
        <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Utilization by Employee</h2>
        <div class="space-y-3">
          <div
            v-for="emp in employeeData"
            :key="emp.id"
            class="border rounded-lg p-4"
          >
            <div class="flex items-center justify-between mb-2">
              <div>
                <p class="font-semibold">{{ emp.name }}</p>
                <p class="text-sm text-surface-600 dark:text-surface-400">{{ emp.department }}</p>
              </div>
              <div class="text-right">
                <p class="text-2xl font-bold" :class="utilizationColor(emp.utilization)">
                  {{ emp.utilization }}%
                </p>
                <p class="text-sm text-surface-600 dark:text-surface-400">{{ emp.billable_hours }} / {{ emp.total_hours }} hours</p>
              </div>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
              <div
                class="h-2 rounded-full transition"
                :style="{ width: emp.utilization + '%' }"
                :class="utilizationBarColor(emp.utilization)"
              ></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Utilization Distribution -->
      <div class="grid grid-cols-2 gap-6">
        <!-- By Range -->
        <div class="bg-white dark:bg-surface-800 rounded-lg shadow-sm p-6">
          <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Utilization Distribution</h2>
          <div class="space-y-3">
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400 mb-1">High (80-100%)</p>
              <div class="w-full bg-gray-200 rounded-full h-2">
                <div class="bg-green-600 h-2 rounded-full" :style="{ width: rangePercent('high') }"></div>
              </div>
              <p class="text-xs text-surface-500 dark:text-surface-400 mt-1">{{ utilizationRanges.high }} employees</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400 mb-1">Medium (60-80%)</p>
              <div class="w-full bg-gray-200 rounded-full h-2">
                <div class="bg-blue-600 h-2 rounded-full" :style="{ width: rangePercent('medium') }"></div>
              </div>
              <p class="text-xs text-surface-500 dark:text-surface-400 mt-1">{{ utilizationRanges.medium }} employees</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400 mb-1">Low (40-60%)</p>
              <div class="w-full bg-gray-200 rounded-full h-2">
                <div class="bg-amber-600 h-2 rounded-full" :style="{ width: rangePercent('low') }"></div>
              </div>
              <p class="text-xs text-surface-500 dark:text-surface-400 mt-1">{{ utilizationRanges.low }} employees</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400 mb-1">Very Low (0-40%)</p>
              <div class="w-full bg-gray-200 rounded-full h-2">
                <div class="bg-red-600 h-2 rounded-full" :style="{ width: rangePercent('veryLow') }"></div>
              </div>
              <p class="text-xs text-surface-500 dark:text-surface-400 mt-1">{{ utilizationRanges.veryLow }} employees</p>
            </div>
          </div>
        </div>

        <!-- By Department -->
        <div class="bg-white dark:bg-surface-800 rounded-lg shadow-sm p-6">
          <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Average Utilization by Department</h2>
          <div class="space-y-3">
            <div
              v-for="dept in departmentData"
              :key="dept.department"
              class="flex items-center justify-between p-3 bg-gray-50 dark:bg-surface-800 rounded-lg"
            >
              <span class="font-semibold">{{ dept.department }}</span>
              <div class="text-right">
                <span class="text-lg font-bold" :class="utilizationColor(dept.avg_utilization)">
                  {{ dept.avg_utilization }}%
                </span>
                <p class="text-xs text-surface-600 dark:text-surface-400">{{ dept.employee_count }} employees</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'

const filters = reactive({
  from_date: new Date(new Date().setDate(new Date().getDate() - 30)).toISOString().split('T')[0],
  to_date: new Date().toISOString().split('T')[0],
  department: '',
})

const stats = reactive({
  avgUtilization: 0,
  highUtilization: 0,
  underUtilized: 0,
})

const utilizationRanges = reactive({
  high: 0,
  medium: 0,
  low: 0,
  veryLow: 0,
})

const departments = ref([])
const employeeData = ref([])
const departmentData = ref([])

const employeeCount = computed(() => employeeData.value.length)

const utilizationColor = (util) => {
  if (util >= 80) return 'text-green-700 dark:text-green-300'
  if (util >= 60) return 'text-primary-700 dark:text-primary-300'
  if (util >= 40) return 'text-amber-600'
  return 'text-red-700 dark:text-red-300'
}

const utilizationBarColor = (util) => {
  if (util >= 80) return 'bg-green-600'
  if (util >= 60) return 'bg-blue-600'
  if (util >= 40) return 'bg-amber-600'
  return 'bg-red-600'
}

const rangePercent = (range) => {
  const total = utilizationRanges.high + utilizationRanges.medium + utilizationRanges.low + utilizationRanges.veryLow
  const count = utilizationRanges[range]
  return total === 0 ? '0%' : Math.round((count / total) * 100) + '%'
}

const loadReport = async () => {
  try {
    const response = await axios.get('/api/v1/timesheets/reports/utilization', {
      params: {
        from_date: filters.from_date,
        to_date: filters.to_date,
        department: filters.department,
      },
    })
    const data = response.data
    stats.avgUtilization = data.avg_utilization || 0
    stats.highUtilization = data.high_utilization || 0
    stats.underUtilized = data.under_utilized || 0
    utilizationRanges.high = data.utilization_ranges?.high || 0
    utilizationRanges.medium = data.utilization_ranges?.medium || 0
    utilizationRanges.low = data.utilization_ranges?.low || 0
    utilizationRanges.veryLow = data.utilization_ranges?.['very_low'] || 0
    employeeData.value = data.by_employee || []
    departmentData.value = data.by_department || []

    // Extract unique departments
    const depts = new Set()
    employeeData.value.forEach(emp => {
      if (emp.department) depts.add(emp.department)
    })
    departments.value = Array.from(depts)
  } catch (error) {
    console.error('Error loading report:', error)
  }
}

const exportReport = () => {
  alert('Export functionality not yet implemented')
}

onMounted(() => {
  loadReport()
})
</script>
