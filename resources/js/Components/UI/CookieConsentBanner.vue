<template>
  <Teleport to="body">
    <div
      v-if="!dismissed"
      class="fixed bottom-0 left-0 right-0 bg-canvas border-t border-subtle shadow-lg z-50"
    >
      <div class="max-w-6xl mx-auto px-4 py-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
          <div class="flex-1">
            <h3 class="text-sm font-semibold text-fg-1 mb-2">
              {{ $t('cookie_consent.title') }}
            </h3>
            <p class="text-xs text-fg-2 mb-4">
              {{ $t('cookie_consent.description') }}
              <a href="/privacy" class="text-link hover:underline">
                {{ $t('cookie_consent.learn_more') }}
              </a>
            </p>
            <div class="space-y-2">
              <label class="flex items-center text-xs text-fg-2">
                <input
                  v-model="consent.cookies"
                  type="checkbox"
                  class="mr-2"
                  disabled
                  checked
                />
                <span>{{ $t('cookie_consent.essential') }}</span>
                <span class="text-fg-4 ml-1">({{ $t('cookie_consent.required') }})</span>
              </label>
              <label class="flex items-center text-xs text-fg-2">
                <input
                  v-model="consent.marketing"
                  type="checkbox"
                  class="mr-2"
                />
                <span>{{ $t('cookie_consent.marketing') }}</span>
              </label>
              <label class="flex items-center text-xs text-fg-2">
                <input
                  v-model="consent.analytics"
                  type="checkbox"
                  class="mr-2"
                />
                <span>{{ $t('cookie_consent.analytics') }}</span>
              </label>
            </div>
          </div>
          <div class="flex gap-2 sm:flex-col lg:flex-row">
            <button
              @click="decline"
              class="px-4 py-2 text-sm font-medium text-fg-2 bg-sunken rounded-md hover:bg-slate-200 dark:hover:bg-slate-700 transition whitespace-nowrap"
            >
              {{ $t('cookie_consent.decline') }}
            </button>
            <button
              @click="accept"
              class="px-4 py-2 text-sm font-medium text-white bg-halo-600 rounded-md hover:bg-halo-700 transition whitespace-nowrap"
            >
              {{ $t('cookie_consent.accept_all') }}
            </button>
            <button
              @click="acceptSelected"
              class="px-4 py-2 text-sm font-medium text-fg-2 bg-slate-200 dark:bg-slate-700 rounded-md hover:bg-slate-300 dark:hover:bg-slate-600 transition whitespace-nowrap"
            >
              {{ $t('cookie_consent.save') }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useApi } from '@/composables/useApi'
import { useI18n } from 'vue-i18n'

const { $t } = useI18n()
const api = useApi()

const dismissed = ref(false)
const consent = ref({
  cookies: true, // Always required
  marketing: false,
  analytics: false,
})

onMounted(() => {
  const stored = localStorage.getItem('cookie-consent')
  if (stored) {
    dismissed.value = true
  }
})

const accept = async () => {
  const consents = [
    { consent_type: 'cookies', granted: true },
    { consent_type: 'marketing', granted: true },
    { consent_type: 'analytics', granted: true },
  ]

  try {
    await api.post('/auth/consent/bulk', { consents })
    localStorage.setItem('cookie-consent', JSON.stringify(consents))
    dismissed.value = true
  } catch (error) {
    console.error('Failed to record consent:', error)
  }
}

const decline = async () => {
  const consents = [
    { consent_type: 'cookies', granted: true },
    { consent_type: 'marketing', granted: false },
    { consent_type: 'analytics', granted: false },
  ]

  try {
    await api.post('/auth/consent/bulk', { consents })
    localStorage.setItem('cookie-consent', JSON.stringify(consents))
    dismissed.value = true
  } catch (error) {
    console.error('Failed to record consent:', error)
  }
}

const acceptSelected = async () => {
  const consents = [
    { consent_type: 'cookies', granted: true },
    { consent_type: 'marketing', granted: consent.value.marketing },
    { consent_type: 'analytics', granted: consent.value.analytics },
  ]

  try {
    await api.post('/auth/consent/bulk', { consents })
    localStorage.setItem('cookie-consent', JSON.stringify(consents))
    dismissed.value = true
  } catch (error) {
    console.error('Failed to record consent:', error)
  }
}
</script>
