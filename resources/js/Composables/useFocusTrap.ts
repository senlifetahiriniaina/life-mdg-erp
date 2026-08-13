import { onMounted, onUnmounted, ref, type Ref } from 'vue'

/**
 * Focus trap composable for modals and dialogs
 * Ensures keyboard focus stays within the dialog while it's open
 */
export function useFocusTrap(containerRef: Ref<HTMLElement | null>) {
  const previousActiveElement = ref<HTMLElement | null>(null)

  const getFocusableElements = (): HTMLElement[] => {
    if (!containerRef.value) return []

    return Array.from(
      containerRef.value.querySelectorAll(
        'button, a, input, select, textarea, [tabindex]:not([tabindex="-1"])'
      )
    ) as HTMLElement[]
  }

  const handleKeyDown = (event: KeyboardEvent) => {
    if (event.key !== 'Tab') return

    const focusables = getFocusableElements()
    if (focusables.length === 0) return

    const firstFocusable = focusables[0]
    const lastFocusable = focusables[focusables.length - 1]
    const activeElement = document.activeElement

    if (event.shiftKey) {
      // Shift + Tab
      if (activeElement === firstFocusable) {
        event.preventDefault()
        lastFocusable.focus()
      }
    } else {
      // Tab
      if (activeElement === lastFocusable) {
        event.preventDefault()
        firstFocusable.focus()
      }
    }
  }

  onMounted(() => {
    previousActiveElement.value = document.activeElement as HTMLElement
    const focusables = getFocusableElements()
    if (focusables.length > 0) {
      focusables[0].focus()
    }

    if (containerRef.value) {
      containerRef.value.addEventListener('keydown', handleKeyDown)
    }
  })

  onUnmounted(() => {
    if (containerRef.value) {
      containerRef.value.removeEventListener('keydown', handleKeyDown)
    }
    previousActiveElement.value?.focus()
  })

  return {
    getFocusableElements,
  }
}
