<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">Analytics Dashboard</h1>
        <p class="mt-2 text-surface-600 dark:text-surface-400">Real-time metrics and performance insights</p>
      </div>
      <div class="flex gap-2">
        <select
          v-model="dateRange"
          class="px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg text-sm focus:ring-blue-500"
        >
          <option value="7d">Last 7 days</option>
          <option value="30d">Last 30 days</option>
          <option value="90d">Last 90 days</option>
          <option value="1y">Last year</option>
        </select>
        <button
          @click="refreshData"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm"
        >
          Refresh
        </button>
      </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow border-b border-gray-200 dark:border-surface-700">
      <div class="flex gap-0">
        <button
          v-for="tab in tabs"
          :key="tab"
          @click="activeTab = tab"
          :class="[
            'flex-1 px-4 py-3 text-sm font-medium border-b-2 transition',
            activeTab === tab
              ? 'border-blue-600 text-primary-700 dark:text-primary-300'
              : 'border-transparent text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:text-surface-50'
          ]"
        >
          {{ tab }}
        </button>
      </div>
    </div>

    <!-- Procurement Analytics -->
    <div v-if="activeTab === 'Procurement'" class="space-y-6">
      <!-- KPI Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <p class="text-sm text-surface-600 dark:text-surface-400 mb-2">Total Spend</p>
          <p class="text-3xl font-bold text-surface-900 dark:text-surface-50">{{ formatCurrency(procurementMetrics.totalSpend) }}</p>
          <p class="text-sm text-green-700 dark:text-green-300 mt-2">↑ 12% from last period</p>
        </div>
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <p class="text-sm text-surface-600 dark:text-surface-400 mb-2">Active Suppliers</p>
          <p class="text-3xl font-bold text-primary-700 dark:text-primary-300">{{ procurementMetrics.activeSuppliers }}</p>
          <p class="text-sm text-surface-600 dark:text-surface-400 mt-2">{{ procurementMetrics.newSuppliersThisMonth }} new this month</p>
        </div>
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <p class="text-sm text-surface-600 dark:text-surface-400 mb-2">Avg PO Value</p>
          <p class="text-3xl font-bold text-surface-900 dark:text-surface-50">{{ formatCurrency(procurementMetrics.avgPOValue) }}</p>
          <p class="text-sm text-surface-600 dark:text-surface-400 mt-2">Across {{ procurementMetrics.totalPOs }} orders</p>
        </div>
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <p class="text-sm text-surface-600 dark:text-surface-400 mb-2">On-Time Delivery</p>
          <p class="text-3xl font-bold text-green-700 dark:text-green-300">{{ procurementMetrics.onTimeDelivery }}%</p>
          <p class="text-sm text-surface-600 dark:text-surface-400 mt-2">{{ procurementMetrics.lateDeliveries }} orders late</p>
        </div>
      </div>

      <!-- Charts -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Spend by Supplier -->
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Top 10 Suppliers by Spend</h3>
          <div class="space-y-3">
            <div v-for="supplier in procurementMetrics.topSuppliers" :key="supplier.id" class="flex items-center gap-3">
              <div class="flex-1">
                <p class="text-sm font-medium text-surface-900 dark:text-surface-50">{{ supplier.name }}</p>
                <div class="mt-1 bg-gray-200 rounded-full h-2">
                  <div
                    class="bg-blue-600 h-2 rounded-full"
                    :style="{ width: (supplier.spend / procurementMetrics.topSuppliers[0].spend * 100) + '%' }"
                  ></div>
                </div>
              </div>
              <p class="text-sm font-medium text-surface-900 dark:text-surface-50 w-20 text-right">{{ formatCurrency(supplier.spend) }}</p>
            </div>
          </div>
        </div>

        <!-- Monthly Spend Trend -->
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Spend Trend</h3>
          <div class="h-64 flex items-end gap-2">
            <div
              v-for="(month, idx) in procurementMetrics.monthlyTrend"
              :key="idx"
              class="flex-1 flex flex-col items-center gap-1"
            >
              <div
                class="w-full bg-primary-50 dark:bg-primary-900/200 rounded-t transition hover:bg-blue-600"
                :style="{ height: (month.amount / 100000 * 100) + '%', minHeight: '20px' }"
              ></div>
              <p class="text-xs text-surface-600 dark:text-surface-400">{{ month.month }}</p>
            </div>
          </div>
        </div>

        <!-- PO Status Distribution -->
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">PO Status Distribution</h3>
          <div class="space-y-3">
            <div v-for="status in procurementMetrics.poStatus" :key="status.status" class="flex items-center justify-between">
              <span class="text-sm text-surface-600 dark:text-surface-400">{{ status.label }}</span>
              <div class="flex items-center gap-2">
                <div class="w-32 bg-gray-200 rounded-full h-2">
                  <div
                    :class="`h-2 rounded-full ${status.color}`"
                    :style="{ width: (status.count / procurementMetrics.totalPOs * 100) + '%' }"
                  ></div>
                </div>
                <span class="text-sm font-medium text-surface-900 dark:text-surface-50 w-12">{{ status.count }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Cost Variance -->
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Budget vs Actual</h3>
          <div class="space-y-4">
            <div>
              <div class="flex justify-between mb-1">
                <span class="text-sm font-medium text-surface-900 dark:text-surface-50">Q1 2026</span>
                <span class="text-sm font-medium text-surface-900 dark:text-surface-50">92%</span>
              </div>
              <div class="w-full bg-gray-200 rounded-full h-2">
                <div class="bg-green-50 dark:bg-green-900/200 h-2 rounded-full" style="width: 92%"></div>
              </div>
            </div>
            <div>
              <div class="flex justify-between mb-1">
                <span class="text-sm font-medium text-surface-900 dark:text-surface-50">Q2 2026</span>
                <span class="text-sm font-medium text-red-700 dark:text-red-300">115%</span>
              </div>
              <div class="w-full bg-gray-200 rounded-full h-2">
                <div class="bg-red-50 dark:bg-red-900/200 h-2 rounded-full" style="width: 100%"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Approval Analytics -->
    <div v-if="activeTab === 'Approvals'" class="space-y-6">
      <!-- KPI Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <p class="text-sm text-surface-600 dark:text-surface-400 mb-2">Pending Approvals</p>
          <p class="text-3xl font-bold text-yellow-700 dark:text-yellow-300">{{ approvalMetrics.pending }}</p>
          <p class="text-sm text-red-700 dark:text-red-300 mt-2">{{ approvalMetrics.overdue }} overdue</p>
        </div>
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <p class="text-sm text-surface-600 dark:text-surface-400 mb-2">Avg Approval Time</p>
          <p class="text-3xl font-bold text-surface-900 dark:text-surface-50">{{ approvalMetrics.avgApprovalTime }}h</p>
          <p class="text-sm text-surface-600 dark:text-surface-400 mt-2">{{ approvalMetrics.slaCompliance }}% SLA met</p>
        </div>
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <p class="text-sm text-surface-600 dark:text-surface-400 mb-2">Approval Rate</p>
          <p class="text-3xl font-bold text-green-700 dark:text-green-300">{{ approvalMetrics.approvalRate }}%</p>
          <p class="text-sm text-surface-600 dark:text-surface-400 mt-2">{{ approvalMetrics.rejectionRate }}% rejected</p>
        </div>
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <p class="text-sm text-surface-600 dark:text-surface-400 mb-2">Bottleneck</p>
          <p class="text-lg font-semibold text-surface-900 dark:text-surface-50">{{ approvalMetrics.bottleneck.role }}</p>
          <p class="text-sm text-surface-600 dark:text-surface-400 mt-2">{{ approvalMetrics.bottleneck.pending }} pending</p>
        </div>
      </div>

      <!-- Charts -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Approval Time by Role -->
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Avg Approval Time by Role</h3>
          <div class="space-y-3">
            <div v-for="role in approvalMetrics.timeByRole" :key="role.role" class="flex items-center gap-3">
              <p class="text-sm font-medium text-surface-900 dark:text-surface-50 w-24">{{ role.role }}</p>
              <div class="flex-1 bg-gray-200 rounded-full h-2">
                <div
                  class="bg-primary-50 dark:bg-primary-900/200 h-2 rounded-full"
                  :style="{ width: (role.hours / 48 * 100) + '%' }"
                ></div>
              </div>
              <p class="text-sm text-surface-600 dark:text-surface-400 w-12 text-right">{{ role.hours }}h</p>
            </div>
          </div>
        </div>

        <!-- Approval Status -->
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Request Status</h3>
          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-sm text-surface-600 dark:text-surface-400">Approved</span>
              <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                {{ approvalMetrics.approved }}
              </span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-sm text-surface-600 dark:text-surface-400">Pending</span>
              <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                {{ approvalMetrics.pending }}
              </span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-sm text-surface-600 dark:text-surface-400">Rejected</span>
              <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                {{ approvalMetrics.rejected }}
              </span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-sm text-surface-600 dark:text-surface-400">Delegated</span>
              <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                {{ approvalMetrics.delegated }}
              </span>
            </div>
          </div>
        </div>

        <!-- SLA Compliance -->
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">SLA Compliance Trend</h3>
          <div class="h-64 flex items-end gap-2">
            <div
              v-for="(week, idx) in approvalMetrics.slaWeekly"
              :key="idx"
              class="flex-1 flex flex-col items-center gap-1"
            >
              <div
                :class="week.compliance >= 90 ? 'bg-green-50 dark:bg-green-900/200' : week.compliance >= 70 ? 'bg-yellow-50 dark:bg-yellow-900/200' : 'bg-red-50 dark:bg-red-900/200'"
                class="w-full rounded-t transition hover:opacity-80"
                :style="{ height: (week.compliance / 100 * 100) + '%', minHeight: '20px' }"
              ></div>
              <p class="text-xs text-surface-600 dark:text-surface-400">W{{ week.week }}</p>
            </div>
          </div>
        </div>

        <!-- Top Approvers -->
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Top Approvers (Speed)</h3>
          <div class="space-y-3">
            <div v-for="approver in approvalMetrics.topApprovers" :key="approver.name" class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-surface-900 dark:text-surface-50">{{ approver.name }}</p>
                <p class="text-xs text-surface-600 dark:text-surface-400">{{ approver.completed }} completed</p>
              </div>
              <span class="text-sm font-medium text-surface-900 dark:text-surface-50">{{ approver.avgTime }}h</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Quality Analytics -->
    <div v-if="activeTab === 'Quality'" class="space-y-6">
      <!-- KPI Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <p class="text-sm text-surface-600 dark:text-surface-400 mb-2">Total Receipts</p>
          <p class="text-3xl font-bold text-surface-900 dark:text-surface-50">{{ qualityMetrics.totalReceipts }}</p>
          <p class="text-sm text-surface-600 dark:text-surface-400 mt-2">{{ qualityMetrics.thisMonth }} this month</p>
        </div>
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <p class="text-sm text-surface-600 dark:text-surface-400 mb-2">Issues Reported</p>
          <p class="text-3xl font-bold text-red-700 dark:text-red-300">{{ qualityMetrics.totalIssues }}</p>
          <p class="text-sm text-green-700 dark:text-green-300 mt-2">{{ qualityMetrics.resolvedIssues }} resolved</p>
        </div>
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <p class="text-sm text-surface-600 dark:text-surface-400 mb-2">Defect Rate</p>
          <p class="text-3xl font-bold text-surface-900 dark:text-surface-50">{{ qualityMetrics.defectRate }}%</p>
          <p class="text-sm text-surface-600 dark:text-surface-400 mt-2">↓ 3% improvement</p>
        </div>
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <p class="text-sm text-surface-600 dark:text-surface-400 mb-2">Avg Quality Score</p>
          <p class="text-3xl font-bold text-primary-700 dark:text-primary-300">{{ qualityMetrics.avgQualityScore }}/100</p>
          <p class="text-sm text-surface-600 dark:text-surface-400 mt-2">{{ qualityMetrics.excellentSuppliers }} excellent</p>
        </div>
      </div>

      <!-- Charts -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Defects by Supplier -->
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Quality Score by Supplier</h3>
          <div class="space-y-3">
            <div v-for="supplier in qualityMetrics.supplierQuality" :key="supplier.id" class="flex items-center justify-between">
              <p class="text-sm font-medium text-surface-900 dark:text-surface-50">{{ supplier.name }}</p>
              <div class="flex items-center gap-2">
                <div class="w-24 bg-gray-200 rounded-full h-2">
                  <div
                    :class="supplier.score >= 90 ? 'bg-green-50 dark:bg-green-900/200' : supplier.score >= 70 ? 'bg-yellow-50 dark:bg-yellow-900/200' : 'bg-red-50 dark:bg-red-900/200'"
                    class="h-2 rounded-full"
                    :style="{ width: (supplier.score / 100 * 100) + '%' }"
                  ></div>
                </div>
                <span class="text-sm font-medium text-surface-900 dark:text-surface-50 w-12 text-right">{{ supplier.score }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Issue Type Distribution -->
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Issues by Type</h3>
          <div class="space-y-3">
            <div v-for="issue in qualityMetrics.issueTypes" :key="issue.type" class="flex items-center justify-between">
              <span class="text-sm text-surface-600 dark:text-surface-400">{{ issue.type }}</span>
              <div class="flex items-center gap-2">
                <div class="w-32 bg-gray-200 rounded-full h-2">
                  <div
                    class="bg-red-50 dark:bg-red-900/200 h-2 rounded-full"
                    :style="{ width: (issue.count / qualityMetrics.totalIssues * 100) + '%' }"
                  ></div>
                </div>
                <span class="text-sm font-medium text-surface-900 dark:text-surface-50 w-8">{{ issue.count }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const activeTab = ref('Procurement')
const dateRange = ref('30d')
const tabs = ref(['Procurement', 'Approvals', 'Quality'])

const procurementMetrics = ref({
  totalSpend: 1250000,
  activeSuppliers: 48,
  newSuppliersThisMonth: 3,
  avgPOValue: 15500,
  totalPOs: 285,
  onTimeDelivery: 92,
  lateDeliveries: 12,
  topSuppliers: [
    { id: 1, name: 'Tech Supplies Inc', spend: 125000 },
    { id: 2, name: 'Office Pro', spend: 95000 },
    { id: 3, name: 'Industrial Parts Co', spend: 78000 },
    { id: 4, name: 'Global Logistics', spend: 62000 },
    { id: 5, name: 'Premium Materials', spend: 55000 }
  ],
  monthlyTrend: [
    { month: 'Jan', amount: 85000 },
    { month: 'Feb', amount: 92000 },
    { month: 'Mar', amount: 87000 },
    { month: 'Apr', amount: 105000 },
    { month: 'May', amount: 98000 }
  ],
  poStatus: [
    { status: 'draft', label: 'Draft', count: 15, color: 'bg-gray-500' },
    { status: 'approved', label: 'Approved', count: 220, color: 'bg-green-50 dark:bg-green-900/200' },
    { status: 'received', label: 'Received', count: 45, color: 'bg-primary-50 dark:bg-primary-900/200' },
    { status: 'invoiced', label: 'Invoiced', count: 5, color: 'bg-violet-50 dark:bg-violet-900/200' }
  ]
})

const approvalMetrics = ref({
  pending: 23,
  overdue: 4,
  approved: 156,
  rejected: 8,
  delegated: 12,
  avgApprovalTime: 18,
  slaCompliance: 87,
  approvalRate: 95,
  rejectionRate: 5,
  bottleneck: { role: 'CFO', pending: 8 },
  timeByRole: [
    { role: 'Manager', hours: 6 },
    { role: 'Director', hours: 12 },
    { role: 'CFO', hours: 24 },
    { role: 'CEO', hours: 48 }
  ],
  slaWeekly: [
    { week: 1, compliance: 92 },
    { week: 2, compliance: 88 },
    { week: 3, compliance: 85 },
    { week: 4, compliance: 90 }
  ],
  topApprovers: [
    { name: 'Sarah Johnson', completed: 28, avgTime: 4 },
    { name: 'Mike Chen', completed: 24, avgTime: 6 },
    { name: 'Lisa Rodriguez', completed: 20, avgTime: 8 }
  ]
})

const qualityMetrics = ref({
  totalReceipts: 285,
  thisMonth: 45,
  totalIssues: 18,
  resolvedIssues: 15,
  defectRate: 6.3,
  avgQualityScore: 87,
  excellentSuppliers: 32,
  supplierQuality: [
    { id: 1, name: 'Tech Supplies Inc', score: 95 },
    { id: 2, name: 'Office Pro', score: 88 },
    { id: 3, name: 'Industrial Parts Co', score: 82 },
    { id: 4, name: 'Global Logistics', score: 76 },
    { id: 5, name: 'Premium Materials', score: 91 }
  ],
  issueTypes: [
    { type: 'Damage', count: 8 },
    { type: 'Defect', count: 6 },
    { type: 'Shortfall', count: 3 },
    { type: 'Other', count: 1 }
  ]
})

const formatCurrency = (value) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 0
  }).format(value)
}

const refreshData = async () => {
  // Simulate data refresh
  console.log('Refreshing analytics for:', dateRange.value)
}

onMounted(() => {
  // Load analytics data based on dateRange
})
</script>
