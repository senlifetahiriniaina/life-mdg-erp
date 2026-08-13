<template>
  <Transition
    enter-active-class="transition-all duration-300"
    enter-from-class="opacity-0 -translate-y-2"
    enter-to-class="opacity-100 translate-y-0"
    leave-active-class="transition-all duration-200"
    leave-from-class="opacity-100 translate-y-0"
    leave-to-class="opacity-0 -translate-y-2"
  >
    <div
      v-if="!help.isDismissed(dismissKey)"
      class="rounded-xl border border-primary-200 dark:border-primary-800 bg-gradient-to-r from-primary-50 to-blue-50 dark:from-primary-900/20 dark:to-blue-900/20 p-5"
    >
      <div class="flex items-start gap-4">
        <div class="w-10 h-10 rounded-xl bg-primary-500 flex items-center justify-center flex-shrink-0">
          <i :class="`${icon} text-white text-lg`" />
        </div>

        <div class="flex-1 min-w-0">
          <h3 class="font-semibold text-surface-900 dark:text-surface-50 mb-1">{{ title }}</h3>
          <p class="text-sm text-surface-600 dark:text-surface-300 leading-relaxed">{{ description }}</p>

          <div v-if="actions?.length" class="flex flex-wrap gap-2 mt-3">
            <button
              v-for="action in actions"
              :key="action.label"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors"
              :class="action.primary
                ? 'bg-primary-500 hover:bg-primary-600 text-white'
                : 'bg-white dark:bg-surface-700 hover:bg-surface-50 dark:hover:bg-surface-600 text-surface-700 dark:text-surface-200 border border-surface-200 dark:border-surface-600'"
              @click="action.onClick"
            >
              <i v-if="action.icon" :class="`${action.icon} text-xs`" />
              {{ action.label }}
            </button>
          </div>
        </div>

        <button
          class="text-surface-400 hover:text-surface-600 dark:hover:text-surface-200 transition-colors flex-shrink-0"
          @click="help.dismiss(dismissKey)"
          aria-label="Dismiss"
        >
          <i class="pi pi-times" />
        </button>
      </div>
    </div>
  </Transition>
</template>

<script setup lang="ts">
import { useHelpStore } from '@/stores/help'

interface BannerAction {
  label: string
  icon?: string
  primary?: boolean
  onClick: () => void
}

defineProps<{
  dismissKey: string
  icon?: string
  title: string
  description: string
  actions?: BannerAction[]
}>()

const help = useHelpStore()
</script>
