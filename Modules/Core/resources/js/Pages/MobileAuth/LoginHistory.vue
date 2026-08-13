<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'

interface LoginRecord {
  id: number
  user_id: number
  device_name: string
  platform: 'ios' | 'android' | 'web'
  ip_address: string
  user_agent: string
  success: boolean
  failure_reason?: string
  biometric_used: boolean
  location?: string
  latitude?: number
  longitude?: number
  created_at: string
}

const loginHistory = ref<LoginRecord[]>([])
const loading = ref(false)
const filterSuccess = ref<'all' | 'success' | 'failed'>('all')
const filterPlatform = ref<'all' | 'ios' | 'android' | 'web'>('all')
const limit = ref(50)

const filteredHistory = computed(() => {
  let filtered = loginHistory.value

  if (filterSuccess.value !== 'all') {
    const isSuccess = filterSuccess.value === 'success'
    filtered = filtered.filter(h => h.success === isSuccess)
  }

  if (filterPlatform.value !== 'all') {
    filtered = filtered.filter(h => h.platform === filterPlatform.value)
  }

  return filtered.slice(0, limit.value)
})

const getStatusIcon = (success: boolean): string => {
  return success ? '✅' : '❌'
}

const getStatusColor = (success: boolean): string => {
  return success ? 'text-green-600' : 'text-red-600'
}

const getPlatformLabel = (platform: string): string => {
  switch (platform) {
    case 'ios':
      return 'iOS'
    case 'android':
      return 'Android'
    case 'web':
      return 'Web'
    default:
      return platform
  }
}

const getAuthMethodBadge = (biometric: boolean, success: boolean): string => {
  if (!success) return 'Failed'
  return biometric ? 'Biometric' : 'Password'
}

const getAuthMethodColor = (biometric: boolean, success: boolean): string => {
  if (!success) return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300'
  return biometric ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300' : 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300'
}

const formatDateTime = (dateString: string): string => {
  const date = new Date(dateString)
  return date.toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  })
}

const formatTimeAgo = (dateString: string): string => {
  const date = new Date(dateString)
  const now = new Date()
  const diffTime = Math.abs(now.getTime() - date.getTime())
  const diffSecs = Math.floor(diffTime / 1000)
  const diffMins = Math.floor(diffTime / (1000 * 60))
  const diffHours = Math.floor(diffTime / (1000 * 60 * 60))
  const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24))

  if (diffSecs < 60) return 'Just now'
  if (diffMins < 60) return `${diffMins}m ago`
  if (diffHours < 24) return `${diffHours}h ago`
  if (diffDays < 7) return `${diffDays}d ago`

  return date.toLocaleDateString()
}

const loadHistory = async () => {
  loading.value = true
  try {
    const response = await axios.get('/api/v1/mobile/auth/history', {
      params: { limit: 100 },
    })
    loginHistory.value = response.data.logins || []
  } catch (error) {
    console.error('Failed to load login history:', error)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadHistory()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div>
      <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Login History</h2>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Review your recent login activity
      </p>
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Status Filter -->
        <div>
          <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Status
          </label>
          <select id="status"
            v-model="filterSuccess"
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
          >
            <option value="all">All Attempts</option>
            <option value="success">Successful</option>
            <option value="failed">Failed</option>
          </select>
        </div>

        <!-- Platform Filter -->
        <div>
          <label for="platform" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Platform
          </label>
          <select id="platform"
            v-model="filterPlatform"
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
          >
            <option value="all">All Platforms</option>
            <option value="ios">iOS</option>
            <option value="android">Android</option>
            <option value="web">Web</option>
          </select>
        </div>

        <!-- Limit -->
        <div>
          <label for="show" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Show
          </label>
          <select id="show"
            v-model.number="limit"
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
          >
            <option :value="10">Last 10</option>
            <option :value="25">Last 25</option>
            <option :value="50">Last 50</option>
            <option :value="100">Last 100</option>
          </select>
        </div>
      </div>
    </div>

    <!-- History Table -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
      <div v-if="loading" class="flex justify-center py-8">
        <div class="animate-spin h-8 w-8 text-blue-600">
          <svg class="animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
        </div>
      </div>

      <div v-else-if="filteredHistory.length === 0" class="text-center py-8">
        <p class="text-gray-500 dark:text-gray-400">No login records found</p>
      </div>

      <table v-else class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-700">
          <tr>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
              Time
            </th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
              Status
            </th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
              Device
            </th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
              Auth Method
            </th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
              IP Address
            </th>
          </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
          <tr
            v-for="record in filteredHistory"
            :key="record.id"
            class="hover:bg-gray-50 dark:hover:bg-gray-700 transition"
          >
            <!-- Time -->
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm font-medium text-gray-900 dark:text-white">
                {{ formatTimeAgo(record.created_at) }}
              </div>
              <div class="text-xs text-gray-500 dark:text-gray-400">
                {{ formatDateTime(record.created_at) }}
              </div>
            </td>

            <!-- Status -->
            <td class="px-6 py-4 whitespace-nowrap">
              <span :class="['text-lg', getStatusColor(record.success)]">
                {{ getStatusIcon(record.success) }}
              </span>
              <span :class="['ml-2 text-sm font-medium', getStatusColor(record.success)]">
                {{ record.success ? 'Success' : 'Failed' }}
              </span>
              <div v-if="record.failure_reason" class="text-xs text-red-600 dark:text-red-400 mt-1">
                {{ record.failure_reason }}
              </div>
            </td>

            <!-- Device -->
            <td class="px-6 py-4">
              <div class="text-sm font-medium text-gray-900 dark:text-white">
                {{ record.device_name }}
              </div>
              <div class="text-xs text-gray-500 dark:text-gray-400">
                {{ getPlatformLabel(record.platform) }}
              </div>
            </td>

            <!-- Auth Method -->
            <td class="px-6 py-4 whitespace-nowrap">
              <span :class="['inline-flex items-center px-2 py-1 rounded-full text-xs font-medium', getAuthMethodColor(record.biometric_used, record.success)]">
                {{ record.biometric_used ? '🔐' : '🔑' }}
                {{ getAuthMethodBadge(record.biometric_used, record.success) }}
              </span>
            </td>

            <!-- IP Address -->
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
              {{ record.ip_address }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Security Info -->
    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
      <p class="text-sm text-yellow-800 dark:text-yellow-300">
        🔒 <strong>Security Tip:</strong> Review this list regularly. If you see suspicious login attempts, revoke the device immediately from your device list.
      </p>
    </div>
  </div>
</template>
