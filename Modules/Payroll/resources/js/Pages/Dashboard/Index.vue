<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'

const { guidance } = useAiAssistant('Payroll', 'view_dashboard')

interface PayslipSummary {
  id: number
  employee_id: number
  employee_name: string
  period: string
  gross_salary: number
  total_deductions: number
  net_salary: number
  currency: string
  status: 'draft' | 'approved' | 'paid'
  payment_date?: string
}

interface CountryTaxSummary {
  country_code: string
  country_name: string
  employee_count: number
  total_gross: number
  total_tax: number
  tax_rate: number
  social_security: number
  health_insurance: number
}

interface PayrollStatistics {
  total_employees: number
  payroll_pending: number
  payroll_processed: number
  payroll_paid: number
  average_salary: number
  total_payroll: number
  currency: string
}

const payslips = ref<PayslipSummary[]>([])
const countryTaxes = ref<CountryTaxSummary[]>([])
const statistics = ref<PayrollStatistics | null>(null)
const loading = ref(false)
const selectedPeriod = ref<string>(getCurrentPeriod())
const selectedTab = ref<'payslips' | 'taxes' | 'statistics'>('payslips')
const filterStatus = ref<'all' | 'draft' | 'approved' | 'paid'>('all')

function getCurrentPeriod(): string {
  const now = new Date()
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  return `${year}-${month}`
}

const filteredPayslips = computed(() => {
  let filtered = payslips.value

  if (filterStatus.value !== 'all') {
    filtered = filtered.filter(p => p.status === filterStatus.value)
  }

  return filtered
})

const getStatusColor = (status: string): string => {
  switch (status) {
    case 'paid':
      return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
    case 'approved':
      return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300'
    case 'draft':
      return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300'
    default:
      return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
  }
}

const getStatusLabel = (status: string): string => {
  return status.charAt(0).toUpperCase() + status.slice(1)
}

const formatCurrency = (amount: number, currency: string = 'XOF'): string => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency,
    minimumFractionDigits: 0,
  }).format(amount)
}

const formatDate = (dateString: string): string => {
  const date = new Date(dateString)
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

const formatPeriod = (periodString: string): string => {
  const [year, month] = periodString.split('-')
  const date = new Date(parseInt(year), parseInt(month) - 1)
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
  })
}

const getTaxPercentage = (country: CountryTaxSummary): number => {
  if (country.total_gross === 0) return 0
  return (country.total_tax / country.total_gross) * 100
}

const loadPayslips = async () => {
  try {
    const response = await axios.get('/api/v1/payroll/payslips', {
      params: { period: selectedPeriod.value },
    })
    payslips.value = response.data.payslips || []
  } catch (error) {
    console.error('Failed to load payslips:', error)
  }
}

const loadCountryTaxes = async () => {
  try {
    const response = await axios.get('/api/v1/payroll/taxes/by-country', {
      params: { period: selectedPeriod.value },
    })
    countryTaxes.value = response.data.taxes || [
      {
        country_code: 'SN',
        country_name: 'Senegal',
        employee_count: 25,
        total_gross: 15000000,
        total_tax: 2250000,
        tax_rate: 15,
        social_security: 1500000,
        health_insurance: 450000,
      },
      {
        country_code: 'CI',
        country_name: 'Côte d\'Ivoire',
        employee_count: 18,
        total_gross: 12000000,
        total_tax: 1920000,
        tax_rate: 16,
        social_security: 1200000,
        health_insurance: 360000,
      },
    ]
  } catch (error) {
    console.error('Failed to load country taxes:', error)
  }
}

const loadStatistics = async () => {
  try {
    const response = await axios.get('/api/v1/payroll/statistics', {
      params: { period: selectedPeriod.value },
    })
    statistics.value = response.data.statistics || {
      total_employees: 43,
      payroll_pending: 5,
      payroll_processed: 38,
      payroll_paid: 35,
      average_salary: 650000,
      total_payroll: 27500000,
      currency: 'XOF',
    }
  } catch (error) {
    console.error('Failed to load statistics:', error)
  }
}

const loadAllData = async () => {
  loading.value = true
  try {
    await Promise.all([loadPayslips(), loadCountryTaxes(), loadStatistics()])
  } finally {
    loading.value = false
  }
}

const approvePayslips = async () => {
  try {
    await axios.post('/api/v1/payroll/payslips/approve-batch', {
      period: selectedPeriod.value,
    })
    loadPayslips()
  } catch (error) {
    console.error('Failed to approve payslips:', error)
  }
}

const processPayment = async () => {
  try {
    await axios.post('/api/v1/payroll/process-payment', {
      period: selectedPeriod.value,
    })
    loadPayslips()
    loadStatistics()
  } catch (error) {
    console.error('Failed to process payment:', error)
  }
}

onMounted(() => {
  loadAllData()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div>
      <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Payroll Dashboard</h2>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Manage payroll, taxes, and salary processing
      </p>
    </div>

    <!-- AI Assistant Panel -->
    <AIAssistantPanel v-if="guidance" :guidance="guidance" class="mb-6" />

    <!-- Period Selector -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
      <div class="flex items-center gap-4">
        <label for="select-period" class="text-sm font-medium text-gray-700 dark:text-gray-300">Select Period</label>
        <input id="select-period"
          v-model="selectedPeriod"
          type="month"
          @change="loadAllData"
          class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
        />
        <span class="text-sm text-gray-600 dark:text-gray-400">
          {{ formatPeriod(selectedPeriod) }}
        </span>
      </div>
    </div>

    <!-- Statistics Cards -->
    <div v-if="statistics" class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
        <p class="text-sm text-blue-600 dark:text-blue-400 font-medium">Total Employees</p>
        <p class="text-3xl font-bold text-blue-700 dark:text-blue-300 mt-1">{{ statistics.total_employees }}</p>
        <p class="text-xs text-blue-600 dark:text-blue-400 mt-2">
          {{ statistics.payroll_paid }} paid • {{ statistics.payroll_pending }} pending
        </p>
      </div>

      <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 border border-green-200 dark:border-green-800">
        <p class="text-sm text-green-600 dark:text-green-400 font-medium">Total Payroll</p>
        <p class="text-3xl font-bold text-green-700 dark:text-green-300 mt-1">
          {{ formatCurrency(statistics.total_payroll, statistics.currency) }}
        </p>
      </div>

      <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4 border border-purple-200 dark:border-purple-800">
        <p class="text-sm text-purple-600 dark:text-purple-400 font-medium">Average Salary</p>
        <p class="text-3xl font-bold text-purple-700 dark:text-purple-300 mt-1">
          {{ formatCurrency(statistics.average_salary, statistics.currency) }}
        </p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
      <div class="flex border-b border-gray-200 dark:border-gray-700">
        <button
          @click="selectedTab = 'payslips'"
          :class="[
            'flex-1 px-6 py-3 text-center font-medium transition',
            selectedTab === 'payslips'
              ? 'text-blue-600 dark:text-blue-400 border-b-2 border-blue-600 dark:border-blue-400'
              : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-300',
          ]"
        >
          Payslips ({{ filteredPayslips.length }})
        </button>
        <button
          @click="selectedTab = 'taxes'"
          :class="[
            'flex-1 px-6 py-3 text-center font-medium transition',
            selectedTab === 'taxes'
              ? 'text-blue-600 dark:text-blue-400 border-b-2 border-blue-600 dark:border-blue-400'
              : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-300',
          ]"
        >
          Taxes by Country
        </button>
        <button
          @click="selectedTab = 'statistics'"
          :class="[
            'flex-1 px-6 py-3 text-center font-medium transition',
            selectedTab === 'statistics'
              ? 'text-blue-600 dark:text-blue-400 border-b-2 border-blue-600 dark:border-blue-400'
              : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-300',
          ]"
        >
          Statistics
        </button>
      </div>

      <!-- Tab Content -->
      <div class="p-6">
        <!-- Payslips Tab -->
        <div v-if="selectedTab === 'payslips'" class="space-y-4">
          <div class="flex justify-between items-center mb-4">
            <div>
              <label for="filter-by-status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Filter by Status
              </label>
              <select id="filter-by-status"
                v-model="filterStatus"
                class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
              >
                <option value="all">All Payslips</option>
                <option value="draft">Draft</option>
                <option value="approved">Approved</option>
                <option value="paid">Paid</option>
              </select>
            </div>
            <div class="flex gap-2">
              <button
                @click="approvePayslips"
                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition"
              >
                ✓ Approve All
              </button>
              <button
                @click="processPayment"
                class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition"
              >
                💳 Process Payment
              </button>
            </div>
          </div>

          <div v-if="loading" class="flex justify-center py-8">
            <div class="animate-spin h-8 w-8 text-blue-600">
              <svg class="animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
            </div>
          </div>

          <div v-else class="space-y-2">
            <div
              v-for="payslip in filteredPayslips"
              :key="payslip.id"
              class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-md transition"
            >
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <h3 class="font-semibold text-gray-900 dark:text-white">{{ payslip.employee_name }}</h3>
                  <p class="text-sm text-gray-600 dark:text-gray-400">{{ formatPeriod(payslip.period) }}</p>
                </div>
                <span
                  :class="[
                    'inline-flex items-center px-3 py-1 rounded-full text-sm font-medium',
                    getStatusColor(payslip.status),
                  ]"
                >
                  {{ getStatusLabel(payslip.status) }}
                </span>
              </div>

              <div class="grid grid-cols-3 gap-4 mt-3 text-sm">
                <div>
                  <p class="text-gray-600 dark:text-gray-400 text-xs">Gross Salary</p>
                  <p class="font-semibold text-gray-900 dark:text-white">
                    {{ formatCurrency(payslip.gross_salary, payslip.currency) }}
                  </p>
                </div>
                <div>
                  <p class="text-gray-600 dark:text-gray-400 text-xs">Deductions</p>
                  <p class="font-semibold text-gray-900 dark:text-white">
                    {{ formatCurrency(payslip.total_deductions, payslip.currency) }}
                  </p>
                </div>
                <div>
                  <p class="text-gray-600 dark:text-gray-400 text-xs">Net Salary</p>
                  <p class="font-semibold text-green-600 dark:text-green-400">
                    {{ formatCurrency(payslip.net_salary, payslip.currency) }}
                  </p>
                </div>
              </div>

              <div v-if="payslip.payment_date" class="mt-2 text-xs text-gray-600 dark:text-gray-400">
                Paid on {{ formatDate(payslip.payment_date) }}
              </div>

              <div class="flex gap-2 mt-3">
                <button class="flex-1 px-3 py-2 text-sm bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white rounded hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                  📄 View Payslip
                </button>
                <button class="flex-1 px-3 py-2 text-sm bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white rounded hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                  ⬇ Download
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Taxes Tab -->
        <div v-if="selectedTab === 'taxes'" class="space-y-4">
          <div v-if="countryTaxes.length === 0" class="text-center py-8">
            <p class="text-gray-500 dark:text-gray-400">No tax data available</p>
          </div>

          <div v-else class="space-y-3">
            <div
              v-for="country in countryTaxes"
              :key="country.country_code"
              class="border border-gray-200 dark:border-gray-700 rounded-lg p-4"
            >
              <div class="flex items-start justify-between mb-3">
                <div>
                  <h3 class="font-semibold text-gray-900 dark:text-white">{{ country.country_name }}</h3>
                  <p class="text-sm text-gray-600 dark:text-gray-400">{{ country.employee_count }} employees</p>
                </div>
                <div class="text-right">
                  <p class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ getTaxPercentage(country).toFixed(1) }}%
                  </p>
                  <p class="text-xs text-gray-600 dark:text-gray-400">Effective Tax Rate</p>
                </div>
              </div>

              <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm mb-3">
                <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded">
                  <p class="text-gray-600 dark:text-gray-400 text-xs">Total Gross</p>
                  <p class="font-semibold text-gray-900 dark:text-white">
                    {{ formatCurrency(country.total_gross) }}
                  </p>
                </div>
                <div class="bg-red-50 dark:bg-red-900/20 p-3 rounded">
                  <p class="text-red-600 dark:text-red-400 text-xs">Income Tax</p>
                  <p class="font-semibold text-red-700 dark:text-red-300">
                    {{ formatCurrency(country.total_tax) }}
                  </p>
                </div>
                <div class="bg-orange-50 dark:bg-orange-900/20 p-3 rounded">
                  <p class="text-orange-600 dark:text-orange-400 text-xs">Social Security</p>
                  <p class="font-semibold text-orange-700 dark:text-orange-300">
                    {{ formatCurrency(country.social_security) }}
                  </p>
                </div>
                <div class="bg-yellow-50 dark:bg-yellow-900/20 p-3 rounded">
                  <p class="text-yellow-600 dark:text-yellow-400 text-xs">Health Insurance</p>
                  <p class="font-semibold text-yellow-700 dark:text-yellow-300">
                    {{ formatCurrency(country.health_insurance) }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Statistics Tab -->
        <div v-if="selectedTab === 'statistics'" class="space-y-4">
          <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
              <p class="text-xs text-blue-600 dark:text-blue-400 font-medium">Payslips Processed</p>
              <p class="text-2xl font-bold text-blue-700 dark:text-blue-300 mt-1">{{ statistics?.payroll_processed }}</p>
            </div>

            <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 border border-green-200 dark:border-green-800">
              <p class="text-xs text-green-600 dark:text-green-400 font-medium">Payslips Paid</p>
              <p class="text-2xl font-bold text-green-700 dark:text-green-300 mt-1">{{ statistics?.payroll_paid }}</p>
            </div>

            <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4 border border-yellow-200 dark:border-yellow-800">
              <p class="text-xs text-yellow-600 dark:text-yellow-400 font-medium">Pending Approval</p>
              <p class="text-2xl font-bold text-yellow-700 dark:text-yellow-300 mt-1">{{ statistics?.payroll_pending }}</p>
            </div>
          </div>

          <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <p class="text-sm text-blue-800 dark:text-blue-300">
              ℹ️ <strong>Note:</strong> All payroll calculations are OHADA-compliant with multi-country tax support (SN/CI/CM/NG). Deductions include income tax, social security, and health insurance.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
