<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-all duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-all duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="help.activeTour === tourId"
        class="fixed inset-0 z-[9999] pointer-events-none"
        aria-live="polite"
      >
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/50 pointer-events-auto" @click.self="help.endTour()" />

        <!-- Tour card -->
        <div
          class="absolute bg-white dark:bg-surface-800 rounded-2xl shadow-2xl w-96 pointer-events-auto"
          :style="cardStyle"
        >
          <!-- Progress bar -->
          <div class="h-1 bg-surface-100 dark:bg-surface-700 rounded-t-2xl overflow-hidden">
            <div
              class="h-full bg-primary-500 transition-all duration-300"
              :style="{ width: `${((help.tourStep + 1) / steps.length) * 100}%` }"
            />
          </div>

          <div class="p-6">
            <!-- Step indicator -->
            <div class="flex items-center justify-between mb-4">
              <span class="text-xs font-semibold text-surface-400 uppercase tracking-wider">
                {{ steps[help.tourStep]?.tag ?? `Step ${help.tourStep + 1} of ${steps.length}` }}
              </span>
              <button
                class="text-surface-400 hover:text-surface-600 dark:hover:text-surface-200 transition-colors"
                @click="help.endTour()"
                aria-label="Close tour"
              >
                <i class="pi pi-times text-sm" />
              </button>
            </div>

            <!-- Step icon + title -->
            <div class="flex items-start gap-4 mb-3">
              <div
                v-if="currentStep?.icon"
                class="w-10 h-10 rounded-xl bg-primary-50 dark:bg-primary-900/30 flex items-center justify-center flex-shrink-0"
              >
                <i :class="`${currentStep.icon} text-primary-600 dark:text-primary-400 text-lg`" />
              </div>
              <div>
                <h3 class="text-lg font-bold text-surface-900 dark:text-surface-50 leading-tight">
                  {{ currentStep?.title }}
                </h3>
              </div>
            </div>

            <p class="text-surface-600 dark:text-surface-300 text-sm leading-relaxed mb-6">
              {{ currentStep?.description }}
            </p>

            <!-- Step dots -->
            <div class="flex items-center justify-center gap-1.5 mb-6">
              <button
                v-for="(_, i) in steps"
                :key="i"
                :class="[
                  'rounded-full transition-all duration-200',
                  i === help.tourStep
                    ? 'w-6 h-2 bg-primary-500'
                    : 'w-2 h-2 bg-surface-200 dark:bg-surface-600 hover:bg-surface-300',
                ]"
                @click="help.tourStep = i"
                :aria-label="`Go to step ${i + 1}`"
              />
            </div>

            <!-- Navigation -->
            <div class="flex items-center gap-3">
              <button
                v-if="help.tourStep > 0"
                class="flex-1 py-2 px-4 rounded-lg border border-surface-200 dark:border-surface-600 text-sm font-medium text-surface-700 dark:text-surface-200 hover:bg-surface-50 dark:hover:bg-surface-700 transition-colors"
                @click="help.prevStep()"
              >
                ← {{ $t('common.back') }}
              </button>
              <button
                class="flex-1 py-2 px-4 rounded-lg bg-primary-500 hover:bg-primary-600 text-white text-sm font-semibold transition-colors"
                @click="help.nextStep(steps.length)"
              >
                {{ help.tourStep === steps.length - 1 ? $t('help.finish') : $t('help.next') }} →
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useHelpStore } from '@/stores/help'

interface TourStep {
  title: string
  description: string
  icon?: string
  tag?: string
  targetSelector?: string
}

const props = defineProps<{
  tourId: string
  steps: TourStep[]
  position?: 'center' | 'bottom-right'
}>()

const help = useHelpStore()

const currentStep = computed(() => props.steps[help.tourStep])

const cardStyle = computed(() => {
  if (props.position === 'bottom-right') {
    return { bottom: '32px', right: '32px' }
  }
  return { top: '50%', left: '50%', transform: 'translate(-50%, -50%)' }
})
</script>
