import { ref, onUnmounted } from 'vue'

const FOCUSABLE_SELECTORS = [
  'a[href]',
  'area[href]',
  'input:not([disabled]):not([type="hidden"])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  'button:not([disabled])',
  '[tabindex]:not([tabindex="-1"])',
  'details > summary',
].join(', ')

/**
 * Trap keyboard focus inside a container (WCAG 2.1.2).
 * Returns activate/deactivate/trapRef to attach to the modal root element.
 */
export function useFocusTrap() {
  const trapRef = ref<HTMLElement | null>(null)
  let previouslyFocused: HTMLElement | null = null

  const getFocusable = (): HTMLElement[] => {
    if (!trapRef.value) return []
    return Array.from(trapRef.value.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTORS)).filter(
      el => !el.closest('[aria-hidden="true"]'),
    )
  }

  const handleKeydown = (event: KeyboardEvent) => {
    if (event.key !== 'Tab') return
    const focusable = getFocusable()
    if (!focusable.length) { event.preventDefault(); return }

    const first = focusable[0]
    const last = focusable[focusable.length - 1]

    if (event.shiftKey) {
      if (document.activeElement === first) { event.preventDefault(); last.focus() }
    } else {
      if (document.activeElement === last) { event.preventDefault(); first.focus() }
    }
  }

  const activate = () => {
    previouslyFocused = document.activeElement as HTMLElement
    const focusable = getFocusable()
    if (focusable.length) focusable[0].focus()
    document.addEventListener('keydown', handleKeydown)
  }

  const deactivate = () => {
    document.removeEventListener('keydown', handleKeydown)
    previouslyFocused?.focus()
    previouslyFocused = null
  }

  onUnmounted(deactivate)

  return { trapRef, activate, deactivate }
}
