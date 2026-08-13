<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'

interface Device {
  id: number
  device_name: string
  device_model: string
  platform: 'ios' | 'android' | 'web'
  os_version: string
  is_active: boolean
  registered_at: string
  last_login_at?: string
  ip_address?: string
  device_id?: string
}

const devices = ref<Device[]>([])
const loading = ref(false)
const sortBy = ref<'last_login' | 'registered_at'>('last_login')
const filterPlatform = ref<'all' | 'ios' | 'android' | 'web'>('all')

const filteredDevices = computed(() => {
  let filtered = devices.value

  if (filterPlatform.value !== 'all') {
    filtered = filtered.filter(d => d.platform === filterPlatform.value)
  }

  if (sortBy.value === 'last_login') {
    filtered.sort((a, b) => {
      const aDate = a.last_login_at ? new Date(a.last_login_at).getTime() : 0
      const bDate = b.last_login_at ? new Date(b.last_login_at).getTime() : 0
      return bDate - aDate
    })
  } else {
    filtered.sort((a, b) => {
      const aDate = new Date(a.registered_at).getTime()
      const bDate = new Date(b.registered_at).getTime()
      return bDate - aDate
    })
  }

  return filtered
})

const getDeviceIcon = (platform: string): string => {
  switch (platform) {
    case 'ios':
      return '🍎'
    case 'android':
      return '🤖'
    case 'web':
      return '💻'
    default:
      return '📱'
  }
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

const getStatusColor = (isActive: boolean): string => {
  return isActive ? 'text-green-600' : 'text-gray-400'
}

const formatDate = (dateString: string): string => {
  const date = new Date(dateString)
  const now = new Date()
  const diffTime = Math.abs(now.getTime() - date.getTime())
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))

  if (diffDays === 0) {
    const diffHours = Math.ceil(diffTime / (1000 * 60 * 60))
    return `${diffHours}h ago`
  }
  if (diffDays === 1) return 'Yesterday'
  if (diffDays < 7) return `${diffDays} days ago`

  return date.toLocaleDateString()
}

const loadDevices = async () => {
  loading.value = true
  try {
    const response = await axios.get('/api/v1/mobile/auth/devices')
    devices.value = response.data.devices || []
  } catch (error) {
    console.error('Failed to load devices:', error)
  } finally {
    loading.value = false
  }
}

const revokeDevice = async (deviceId: number) => {
  if (!confirm('Are you sure you want to revoke this device? You will need to log in again on that device.')) {
    return
  }

  try {
    await axios.delete(`/api/v1/mobile/auth/devices/${deviceId}`)
    devices.value = devices.value.filter(d => d.id !== deviceId)
  } catch (error) {
    console.error('Failed to revoke device:', error)
  }
}

onMounted(() => {
  loadDevices()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div>
      <h2 class="text-2xl font-bold text-gray-900 dark:text-white">My Devices</h2>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Manage your connected devices and sessions
      </p>
    </div>

    <!-- Filters and Sorting -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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

        <!-- Sort By -->
        <div>
          <label for="sort-by" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Sort By
          </label>
          <select id="sort-by"
            v-model="sortBy"
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
          >
            <option value="last_login">Last Login</option>
            <option value="registered_at">Registration Date</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Devices List -->
    <div class="space-y-3">
      <div v-if="loading" class="flex justify-center py-8">
        <div class="animate-spin h-8 w-8 text-blue-600">
          <svg class="animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
        </div>
      </div>

      <div v-else-if="filteredDevices.length === 0" class="text-center py-8">
        <p class="text-gray-500 dark:text-gray-400">No devices found</p>
      </div>

      <div
        v-for="device in filteredDevices"
        :key="device.id"
        class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 hover:shadow-lg transition"
      >
        <div class="flex items-start justify-between">
          <div class="flex-1">
            <!-- Device Header -->
            <div class="flex items-center space-x-3 mb-2">
              <span class="text-2xl">{{ getDeviceIcon(device.platform) }}</span>
              <div>
                <h3 class="font-semibold text-gray-900 dark:text-white">
                  {{ device.device_name || device.device_model }}
                </h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                  {{ getPlatformLabel(device.platform) }} • {{ device.os_version }}
                </p>
              </div>
            </div>

            <!-- Device Details -->
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mt-3 text-sm">
              <div>
                <p class="text-gray-500 dark:text-gray-400">Registered</p>
                <p class="text-gray-900 dark:text-white font-medium">
                  {{ formatDate(device.registered_at) }}
                </p>
              </div>

              <div v-if="device.last_login_at">
                <p class="text-gray-500 dark:text-gray-400">Last Login</p>
                <p class="text-gray-900 dark:text-white font-medium">
                  {{ formatDate(device.last_login_at) }}
                </p>
              </div>

              <div v-if="device.ip_address">
                <p class="text-gray-500 dark:text-gray-400">IP Address</p>
                <p class="text-gray-900 dark:text-white font-medium">{{ device.ip_address }}</p>
              </div>

              <div>
                <p class="text-gray-500 dark:text-gray-400">Status</p>
                <p :class="['font-medium', getStatusColor(device.is_active)]">
                  {{ device.is_active ? 'Active' : 'Inactive' }}
                </p>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <button
            @click="revokeDevice(device.id)"
            class="ml-4 px-3 py-1 text-sm font-medium text-red-600 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 rounded transition"
          >
            Revoke
          </button>
        </div>
      </div>
    </div>

    <!-- Info Message -->
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
      <p class="text-sm text-blue-800 dark:text-blue-300">
        💡 <strong>Tip:</strong> Revoking a device will log you out from that device. You can log in again anytime.
      </p>
    </div>
  </div>
</template>
