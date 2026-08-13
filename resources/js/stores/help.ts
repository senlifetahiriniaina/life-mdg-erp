import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

const STORAGE_KEY = 'widehalo_help'

function loadState(): Record<string, boolean> {
  try {
    return JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '{}')
  } catch {
    return {}
  }
}

function saveState(state: Record<string, boolean>) {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(state))
}

export const useHelpStore = defineStore('help', () => {
  const dismissed = ref<Record<string, boolean>>(loadState())
  const activeTour = ref<string | null>(null)
  const tourStep = ref(0)

  function isDismissed(key: string): boolean {
    return !!dismissed.value[key]
  }

  function dismiss(key: string) {
    dismissed.value[key] = true
    saveState(dismissed.value)
  }

  function startTour(tourId: string) {
    activeTour.value = tourId
    tourStep.value = 0
  }

  function nextStep(totalSteps: number) {
    if (tourStep.value < totalSteps - 1) {
      tourStep.value++
    } else {
      endTour()
    }
  }

  function prevStep() {
    if (tourStep.value > 0) tourStep.value--
  }

  function endTour() {
    if (activeTour.value) {
      dismiss(`tour:${activeTour.value}`)
    }
    activeTour.value = null
    tourStep.value = 0
  }

  function resetAll() {
    dismissed.value = {}
    saveState({})
  }

  const isTourActive = computed(() => activeTour.value !== null)

  return {
    dismissed,
    activeTour,
    tourStep,
    isTourActive,
    isDismissed,
    dismiss,
    startTour,
    nextStep,
    prevStep,
    endTour,
    resetAll,
  }
})
