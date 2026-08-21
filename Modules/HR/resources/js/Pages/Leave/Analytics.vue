<template>
  <AppLayout>
    <Head title="Leave Analytics" />

    <!-- Chantier 32.17 (HR deep 14-layer audit): this real, routed page
         never called useAiAssistant() at all before this fix. -->
    <AIAssistantPanel v-if="showAiPanel" :guidance="guidance" @close="showAiPanel = false" />

    <div class="space-y-6">
      <!-- Page header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">
            Leave Analytics
          </h1>
          <p class="text-surface-500 text-sm mt-1">
            Track leave balances and trends
          </p>
        </div>
        <Button
          icon="pi pi-download"
          label="Export Report"
          @click="exportReport"
        />
      </div>

      <!-- Filters -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-4 flex items-center gap-4 flex-wrap">
        <Select
          v-model="selectedDepartment"
          :options="departments"
          option-label="name"
          option-value="id"
          placeholder="All Departments"
          show-clear
          class="w-56"
          @change="loadAnalytics"
        />
        <Select
          v-model="selectedYear"
          :options="availableYears"
          placeholder="Year"
          class="w-40"
          @change="loadAnalytics"
        />
      </div>

      <!-- Key Metrics -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-surface-500 text-sm">Total Leaves Taken</p>
              <p class="text-3xl font-bold text-surface-900 dark:text-surface-50 mt-2">
                {{ metrics.totalTaken }}
              </p>
            </div>
            <i class="pi pi-calendar text-2xl text-orange-500 opacity-50" />
          </div>
        </div>

        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-surface-500 text-sm">Average Balance</p>
              <p class="text-3xl font-bold text-surface-900 dark:text-surface-50 mt-2">
                {{ metrics.avgBalance.toFixed(1) }}
              </p>
            </div>
            <i class="pi pi-chart-bar text-2xl text-green-500 opacity-50" />
          </div>
        </div>

        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-surface-500 text-sm">Pending Requests</p>
              <p class="text-3xl font-bold text-surface-900 dark:text-surface-50 mt-2">
                {{ metrics.pendingRequests }}
              </p>
            </div>
            <i class="pi pi-hourglass text-2xl text-yellow-500 opacity-50" />
          </div>
        </div>

        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-surface-500 text-sm">Approval Rate</p>
              <p class="text-3xl font-bold text-surface-900 dark:text-surface-50 mt-2">
                {{ metrics.approvalRate }}%
              </p>
            </div>
            <i class="pi pi-check-circle text-2xl text-blue-500 opacity-50" />
          </div>
        </div>
      </div>

      <!-- Charts Section -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Leave Type Distribution -->
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
          <h3 class="font-semibold text-surface-900 dark:text-surface-50 mb-4">Leave Type Distribution</h3>
          <div class="space-y-3">
            <div v-for="type in leaveTypeStats" :key="type.name" class="flex items-center justify-between">
              <div class="flex items-center gap-2 flex-1">
                <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: type.color }" />
                <span class="text-sm text-surface-600 dark:text-surface-300">{{ type.name }}</span>
              </div>
              <div class="flex items-center gap-2">
                <div class="bg-surface-100 dark:bg-surface-700 rounded px-2 py-1">
                  <span class="text-sm font-medium text-surface-900 dark:text-surface-50">{{ type.count }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Monthly Trend -->
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
          <h3 class="font-semibold text-surface-900 dark:text-surface-50 mb-4">Monthly Trend</h3>
          <div class="space-y-2">
            <div v-for="(month, index) in monthlyTrend" :key="index" class="flex items-center gap-3">
              <span class="text-xs text-surface-500 w-10">{{ month.name }}</span>
              <div class="flex-1 bg-surface-100 dark:bg-surface-700 rounded-full h-2 overflow-hidden">
                <div
                  class="bg-gradient-to-r from-primary-400 to-primary-600 h-full"
                  :style="{ width: (month.count / 10) * 100 + '%' }"
                />
              </div>
              <span class="text-xs font-medium text-surface-600 dark:text-surface-300 w-6 text-right">
                {{ month.count }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Employee Leave Balances -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
        <h3 class="font-semibold text-surface-900 dark:text-surface-50 mb-4">Employee Leave Balances</h3>
        <div class="space-y-3">
          <div v-for="employee in leaveBalances" :key="employee.id" class="flex items-center justify-between p-3 bg-surface-50 dark:bg-surface-700/30 rounded-lg">
            <div class="flex items-center gap-3 flex-1">
              <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center">
                <span class="text-xs font-bold text-primary-700 dark:text-primary-300">
                  {{ employee.name.charAt(0) }}
                </span>
              </div>
              <div>
                <p class="font-medium text-surface-900 dark:text-surface-50">{{ employee.name }}</p>
                <p class="text-xs text-surface-500">{{ employee.department }}</p>
              </div>
            </div>
            <div class="flex gap-4">
              <div class="text-right">
                <p class="text-xs text-surface-500">Taken</p>
                <p class="font-medium text-surface-900 dark:text-surface-50">{{ employee.taken }}</p>
              </div>
              <div class="text-right">
                <p class="text-xs text-surface-500">Remaining</p>
                <Tag
                  :value="`${employee.remaining}`"
                  :severity="employee.remaining > 5 ? 'success' : employee.remaining > 2 ? 'warning' : 'danger'"
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, reactive, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import Button from 'primevue/button'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import AppLayout from '@/Layouts/AppLayout.vue'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'

interface Department {
  id: number
  name: string
}

interface EmployeeLeaveBalance {
  id: number
  name: string
  department: string
  taken: number
  remaining: number
}

interface Metrics {
  totalTaken: number
  avgBalance: number
  pendingRequests: number
  approvalRate: number
}

// Chantier 32.17 (HR deep 14-layer audit): see the AIAssistantPanel comment
// in the template — this page never called useAiAssistant() at all before.
const showAiPanel = ref(true)
const { guidance } = useAiAssistant('HR', 'view_leave_analytics')

const selectedDepartment = ref<number | null>(null)
const selectedYear = ref(new Date().getFullYear())
const departments = ref<Department[]>([])
const leaveBalances = ref<EmployeeLeaveBalance[]>([])
const leaveTypeStatsData = ref<{ name: string; count: number }[]>([])
const monthlyTrendData = ref<{ month: number; count: number }[]>([])

const TYPE_COLORS = ['#3b82f6', '#ef4444', '#f59e0b', '#ec4899', '#10b981', '#8b5cf6']
const MONTH_NAMES = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

const availableYears = computed(() => {
  const years = []
  const currentYear = new Date().getFullYear()
  for (let i = currentYear - 2; i <= currentYear + 1; i++) {
    years.push(i)
  }
  return years
})

const metrics = reactive<Metrics>({
  totalTaken: 0,
  avgBalance: 0,
  pendingRequests: 0,
  approvalRate: 0,
})

const leaveTypeStats = computed(() =>
  leaveTypeStatsData.value.map((type, index) => ({
    name: type.name,
    count: type.count,
    color: TYPE_COLORS[index % TYPE_COLORS.length],
  }))
)

const monthlyTrend = computed(() =>
  monthlyTrendData.value.length
    ? monthlyTrendData.value.map(m => ({ name: MONTH_NAMES[m.month - 1], count: m.count }))
    : MONTH_NAMES.map(name => ({ name, count: 0 }))
)

const loadAnalytics = async () => {
  try {
    const params = new URLSearchParams()
    params.set('year', String(selectedYear.value))
    if (selectedDepartment.value) {
      params.set('department_id', String(selectedDepartment.value))
    }

    const response = await fetch(`/api/v1/hr/leave-analytics?${params}`, {
      headers: { Accept: 'application/json' },
    })
    const data = await response.json()

    metrics.totalTaken = data.total_taken || 0
    metrics.avgBalance = data.avg_balance || 0
    metrics.pendingRequests = data.pending_requests || 0
    metrics.approvalRate = data.approval_rate || 0

    leaveBalances.value = (data.employee_balances || []).map((e: any) => ({
      id: e.employee_id,
      name: e.name,
      department: e.department,
      taken: e.taken,
      remaining: e.remaining,
    }))
    leaveTypeStatsData.value = data.leave_type_stats || []
    monthlyTrendData.value = data.monthly_trend || []
  } catch (error) {
    console.error('Failed to load analytics:', error)
  }
}

const exportReport = () => {
  const csv = generateCSV()
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = `leave-analytics-${selectedYear.value}.csv`
  link.click()
}

const generateCSV = (): string => {
  const headers = ['Employee', 'Department', 'Taken', 'Remaining', 'Balance %']
  const rows = leaveBalances.value.map(emp => {
    const total = emp.taken + emp.remaining
    const percentage = total > 0 ? ((emp.remaining / total) * 100).toFixed(1) : '0'
    return [emp.name, emp.department, emp.taken, emp.remaining, percentage]
  })
  return [headers, ...rows].map(row => row.map(cell => `"${cell}"`).join(',')).join('\n')
}

onMounted(() => {
  Promise.all([
    fetch('/api/v1/hr/departments', {
      headers: { Accept: 'application/json' },
    }).then(r => r.json()).then(d => {
      departments.value = d.data || []
    }),
    loadAnalytics(),
  ]).catch(error => {
    console.error('Failed to load initial data:', error)
  })
})
</script>
