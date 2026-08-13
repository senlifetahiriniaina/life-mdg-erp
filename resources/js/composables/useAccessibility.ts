import { ref } from 'vue'

/**
 * Composable for accessibility features (ARIA, focus management, etc.)
 */
export function useAccessibility() {
  /**
   * Generate ARIA attributes for form fields
   */
  const getAriaAttrs = (fieldName: string, isRequired = false, isInvalid = false, error?: string) => ({
    'aria-required': isRequired,
    'aria-invalid': isInvalid,
    'aria-describedby': error ? `${fieldName}-error` : undefined,
  })

  /**
   * Set focus on first error field
   */
  const focusFirstError = (containerRef: HTMLElement | null) => {
    if (!containerRef) return
    const firstInvalid = containerRef.querySelector('[aria-invalid="true"]') as HTMLElement
    if (firstInvalid) {
      firstInvalid.focus()
    }
  }

  /**
   * Announce message to screen readers
   */
  const announce = (message: string, priority: 'polite' | 'assertive' = 'polite') => {
    const announcer = document.createElement('div')
    announcer.setAttribute('role', 'status')
    announcer.setAttribute('aria-live', priority)
    announcer.setAttribute('aria-atomic', 'true')
    announcer.className = 'sr-only'
    announcer.textContent = message
    document.body.appendChild(announcer)

    setTimeout(() => announcer.remove(), 3000)
  }

  /**
   * Handle keyboard navigation in lists
   */
  const handleListKeydown = (event: KeyboardEvent, items: HTMLElement[]) => {
    const currentIndex = items.findIndex(item => item === document.activeElement)
    let nextIndex = currentIndex

    switch (event.key) {
      case 'ArrowDown':
      case 'ArrowRight':
        nextIndex = Math.min(currentIndex + 1, items.length - 1)
        break
      case 'ArrowUp':
      case 'ArrowLeft':
        nextIndex = Math.max(currentIndex - 1, 0)
        break
      case 'Home':
        nextIndex = 0
        break
      case 'End':
        nextIndex = items.length - 1
        break
      default:
        return
    }

    if (nextIndex !== currentIndex) {
      event.preventDefault()
      items[nextIndex]?.focus()
    }
  }

  return {
    getAriaAttrs,
    focusFirstError,
    announce,
    handleListKeydown,
  }
}
