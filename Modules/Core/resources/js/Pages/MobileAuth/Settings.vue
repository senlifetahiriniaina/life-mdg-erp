<script setup lang="ts">
import { ref, onMounted } from 'vue'
import axios from 'axios'

interface SecuritySettings {
  two_factor_enabled: boolean
  biometric_login_enabled: boolean
  auto_lock_enabled: boolean
  auto_lock_timeout: number
  session_timeout: number
  trusted_devices_only: boolean
  suspicious_activity_alerts: boolean
  ip_whitelist_enabled: boolean
}

const settings = ref<SecuritySettings>({
  two_factor_enabled: false,
  biometric_login_enabled: true,
  auto_lock_enabled: true,
  auto_lock_timeout: 5,
  session_timeout: 60,
  trusted_devices_only: false,
  suspicious_activity_alerts: true,
  ip_whitelist_enabled: false,
})

const saving = ref(false)
const saveSuccess = ref(false)

const getTimeoutOptions = () => [
  { label: '2 minutes', value: 2 },
  { label: '5 minutes', value: 5 },
  { label: '10 minutes', value: 10 },
  { label: '15 minutes', value: 15 },
  { label: '30 minutes', value: 30 },
  { label: '1 hour', value: 60 },
]

const loadSettings = async () => {
  try {
    const response = await axios.get('/api/v1/mobile/auth/settings')
    Object.assign(settings.value, response.data.settings)
  } catch (error) {
    console.error('Failed to load settings:', error)
  }
}

const saveSettings = async () => {
  saving.value = true
  saveSuccess.value = false

  try {
    await axios.put('/api/v1/mobile/auth/settings', settings.value)
    saveSuccess.value = true

    // Auto-hide success message after 3 seconds
    setTimeout(() => {
      saveSuccess.value = false
    }, 3000)
  } catch (error) {
    console.error('Failed to save settings:', error)
  } finally {
    saving.value = false
  }
}

const resetPassword = async () => {
  if (!confirm('Are you sure you want to reset your password? You will receive a reset link via email.')) {
    return
  }

  try {
    await axios.post('/api/v1/mobile/auth/reset-password')
    alert('Password reset link sent to your email')
  } catch (error) {
    console.error('Failed to initiate password reset:', error)
  }
}

const enableTwoFactor = async () => {
  try {
    const response = await axios.post('/api/v1/mobile/auth/2fa/setup')
    alert('Two-factor authentication enabled. Save your backup codes in a secure location.')
    settings.value.two_factor_enabled = true
  } catch (error) {
    console.error('Failed to enable 2FA:', error)
  }
}

const disableTwoFactor = async () => {
  if (!confirm('Are you sure? Two-factor authentication provides extra security.')) {
    return
  }

  try {
    await axios.post('/api/v1/mobile/auth/2fa/disable')
    settings.value.two_factor_enabled = false
  } catch (error) {
    console.error('Failed to disable 2FA:', error)
  }
}

onMounted(() => {
  loadSettings()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div>
      <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Security Settings</h2>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Manage your account security and authentication preferences
      </p>
    </div>

    <!-- Success Message -->
    <div
      v-if="saveSuccess"
      class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4"
    >
      <p class="text-sm text-green-800 dark:text-green-300">✓ Settings saved successfully</p>
    </div>

    <!-- Two-Factor Authentication -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
      <div class="p-6">
        <div class="flex items-start justify-between mb-4">
          <div>
            <h3 class="font-semibold text-gray-900 dark:text-white">Two-Factor Authentication</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
              Require a second verification method when logging in
            </p>
          </div>
          <span
            :class="[
              'inline-flex items-center px-3 py-1 rounded-full text-sm font-medium',
              settings.two_factor_enabled
                ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
                : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
            ]"
          >
            {{ settings.two_factor_enabled ? '✓ Enabled' : 'Disabled' }}
          </span>
        </div>

        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
          {{ settings.two_factor_enabled
            ? '2FA is active on your account. You can disable it below if needed.'
            : 'Enabling 2FA adds an extra layer of security to your account.' }}
        </p>

        <div class="flex gap-2">
          <button
            v-if="!settings.two_factor_enabled"
            @click="enableTwoFactor"
            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition"
          >
            Enable 2FA
          </button>
          <button
            v-else
            @click="disableTwoFactor"
            class="px-4 py-2 text-red-600 hover:text-red-700 border border-red-300 hover:bg-red-50 dark:border-red-700 dark:hover:bg-red-900/20 font-medium rounded-lg transition"
          >
            Disable 2FA
          </button>
        </div>
      </div>
    </div>

    <!-- Authentication Methods -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
      <div class="p-6">
        <h3 class="font-semibold text-gray-900 dark:text-white mb-6">Authentication Methods</h3>

        <div class="space-y-4">
          <!-- Biometric Login -->
          <div class="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
            <div>
              <p class="font-medium text-gray-900 dark:text-white">Biometric Login</p>
              <p class="text-sm text-gray-600 dark:text-gray-400">Use fingerprint or face recognition to log in</p>
            </div>
            <button
              @click="settings.biometric_login_enabled = !settings.biometric_login_enabled"
              :class="[
                'relative inline-flex h-6 w-11 items-center rounded-full transition',
                settings.biometric_login_enabled
                  ? 'bg-blue-600'
                  : 'bg-gray-300 dark:bg-gray-600',
              ]"
            >
              <span
                :class="[
                  'inline-block h-4 w-4 transform rounded-full bg-white transition',
                  settings.biometric_login_enabled ? 'translate-x-6' : 'translate-x-1',
                ]"
              ></span>
            </button>
          </div>

          <!-- Trusted Devices Only -->
          <div class="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
            <div>
              <p class="font-medium text-gray-900 dark:text-white">Trusted Devices Only</p>
              <p class="text-sm text-gray-600 dark:text-gray-400">Require verification from new devices</p>
            </div>
            <button
              @click="settings.trusted_devices_only = !settings.trusted_devices_only"
              :class="[
                'relative inline-flex h-6 w-11 items-center rounded-full transition',
                settings.trusted_devices_only
                  ? 'bg-blue-600'
                  : 'bg-gray-300 dark:bg-gray-600',
              ]"
            >
              <span
                :class="[
                  'inline-block h-4 w-4 transform rounded-full bg-white transition',
                  settings.trusted_devices_only ? 'translate-x-6' : 'translate-x-1',
                ]"
              ></span>
            </button>
          </div>

          <!-- IP Whitelist -->
          <div class="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
            <div>
              <p class="font-medium text-gray-900 dark:text-white">IP Whitelist</p>
              <p class="text-sm text-gray-600 dark:text-gray-400">Only allow login from specific IP addresses</p>
            </div>
            <button
              @click="settings.ip_whitelist_enabled = !settings.ip_whitelist_enabled"
              :class="[
                'relative inline-flex h-6 w-11 items-center rounded-full transition',
                settings.ip_whitelist_enabled
                  ? 'bg-blue-600'
                  : 'bg-gray-300 dark:bg-gray-600',
              ]"
            >
              <span
                :class="[
                  'inline-block h-4 w-4 transform rounded-full bg-white transition',
                  settings.ip_whitelist_enabled ? 'translate-x-6' : 'translate-x-1',
                ]"
              ></span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Session Settings -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
      <div class="p-6">
        <h3 class="font-semibold text-gray-900 dark:text-white mb-6">Session Management</h3>

        <div class="space-y-4">
          <!-- Auto-Lock -->
          <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
            <div class="flex items-center justify-between mb-3">
              <div>
                <p class="font-medium text-gray-900 dark:text-white">Auto-Lock</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">Lock app after inactivity</p>
              </div>
              <button
                @click="settings.auto_lock_enabled = !settings.auto_lock_enabled"
                :class="[
                  'relative inline-flex h-6 w-11 items-center rounded-full transition',
                  settings.auto_lock_enabled
                    ? 'bg-blue-600'
                    : 'bg-gray-300 dark:bg-gray-600',
                ]"
              >
                <span
                  :class="[
                    'inline-block h-4 w-4 transform rounded-full bg-white transition',
                    settings.auto_lock_enabled ? 'translate-x-6' : 'translate-x-1',
                  ]"
                ></span>
              </button>
            </div>

            <div v-if="settings.auto_lock_enabled" class="mt-3">
              <label for="lock-after-inactivity" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Lock after inactivity
              </label>
              <select id="lock-after-inactivity"
                v-model.number="settings.auto_lock_timeout"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
              >
                <option v-for="opt in getTimeoutOptions()" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </option>
              </select>
            </div>
          </div>

          <!-- Session Timeout -->
          <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
            <label for="session-timeout" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Session timeout
            </label>
            <select id="session-timeout"
              v-model.number="settings.session_timeout"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
            >
              <option :value="30">30 minutes</option>
              <option :value="60">1 hour</option>
              <option :value="120">2 hours</option>
              <option :value="240">4 hours</option>
              <option :value="480">8 hours</option>
            </select>
            <p class="text-xs text-gray-600 dark:text-gray-400 mt-2">
              You will be automatically logged out after this period of inactivity
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Security Alerts -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
      <div class="p-6">
        <h3 class="font-semibold text-gray-900 dark:text-white mb-6">Notifications</h3>

        <div class="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
          <div>
            <p class="font-medium text-gray-900 dark:text-white">Suspicious Activity Alerts</p>
            <p class="text-sm text-gray-600 dark:text-gray-400">Get notified of unusual login attempts</p>
          </div>
          <button
            @click="settings.suspicious_activity_alerts = !settings.suspicious_activity_alerts"
            :class="[
              'relative inline-flex h-6 w-11 items-center rounded-full transition',
              settings.suspicious_activity_alerts
                ? 'bg-blue-600'
                : 'bg-gray-300 dark:bg-gray-600',
            ]"
          >
            <span
              :class="[
                'inline-block h-4 w-4 transform rounded-full bg-white transition',
                settings.suspicious_activity_alerts ? 'translate-x-6' : 'translate-x-1',
              ]"
            ></span>
          </button>
        </div>
      </div>
    </div>

    <!-- Password Management -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
      <div class="p-6">
        <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Password</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
          Change your password regularly to keep your account secure
        </p>

        <button
          @click="resetPassword"
          class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-white font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition"
        >
          Reset Password
        </button>
      </div>
    </div>

    <!-- Save Button -->
    <div class="flex gap-3">
      <button
        @click="saveSettings"
        :disabled="saving"
        class="px-6 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-medium rounded-lg transition"
      >
        {{ saving ? 'Saving...' : 'Save Changes' }}
      </button>
    </div>

    <!-- Info Message -->
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
      <p class="text-sm text-blue-800 dark:text-blue-300">
        🔒 <strong>Security Tip:</strong> Review these settings regularly and enable 2FA for maximum account protection.
      </p>
    </div>
  </div>
</template>
