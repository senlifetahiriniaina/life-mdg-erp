<template>
  <Teleport to="body">
    <Transition name="wh-modal">
      <div
        v-if="modelValue"
        class="wh-modal-backdrop"
        role="presentation"
        @click.self="closeOnBackdrop && close()"
        @keydown.esc="close()"
      >
        <div
          ref="trapRef"
          role="dialog"
          :aria-modal="true"
          :aria-labelledby="titleId"
          :aria-describedby="descId"
          class="wh-modal-dialog"
          :class="[`wh-modal-${size}`]"
        >
          <!-- Header -->
          <header class="wh-modal-header">
            <h2 :id="titleId" class="wh-modal-title">{{ title }}</h2>
            <button
              class="wh-modal-close"
              :aria-label="closeLabel"
              @click="close"
            >
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                <path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              </svg>
            </button>
          </header>

          <!-- Description (sr-only if only for screen readers) -->
          <p v-if="description" :id="descId" class="wh-modal-desc">{{ description }}</p>

          <!-- Body -->
          <div class="wh-modal-body">
            <slot />
          </div>

          <!-- Footer (actions) -->
          <footer v-if="$slots.footer" class="wh-modal-footer">
            <slot name="footer" />
          </footer>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, watch } from 'vue'
import { useFocusTrap } from '@/composables/useFocusTrap'

interface Props {
  modelValue: boolean
  title: string
  description?: string
  size?: 'sm' | 'md' | 'lg' | 'xl'
  closeOnBackdrop?: boolean
  closeLabel?: string
}

const props = withDefaults(defineProps<Props>(), {
  size: 'md',
  closeOnBackdrop: true,
  closeLabel: 'Fermer',
})

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  close: []
}>()

const { trapRef, activate, deactivate } = useFocusTrap()

const uid = Math.random().toString(36).slice(2, 8)
const titleId = computed(() => `modal-title-${uid}`)
const descId = computed(() => `modal-desc-${uid}`)

const close = () => {
  emit('update:modelValue', false)
  emit('close')
}

watch(
  () => props.modelValue,
  (open) => {
    if (open) {
      document.body.style.overflow = 'hidden'
      // Wait for DOM to render before activating trap
      requestAnimationFrame(activate)
    } else {
      document.body.style.overflow = ''
      deactivate()
    }
  },
)
</script>

<style scoped>
.wh-modal-backdrop {
  position: fixed;
  inset: 0;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: var(--z-modal, 1000);
  padding: 1rem;
}

.wh-modal-dialog {
  background: var(--bg-card, #fff);
  border-radius: var(--radius-lg, 12px);
  box-shadow: var(--shadow-xl, 0 20px 60px rgba(0,0,0,.18));
  display: flex;
  flex-direction: column;
  max-height: calc(100dvh - 2rem);
  width: 100%;
  overflow: hidden;
}

.wh-modal-sm  { max-width: 400px; }
.wh-modal-md  { max-width: 560px; }
.wh-modal-lg  { max-width: 768px; }
.wh-modal-xl  { max-width: 1024px; }

.wh-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.25rem 1.5rem 0;
  flex-shrink: 0;
}

.wh-modal-title {
  font-family: var(--font-display, inherit);
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--fg-1, var(--fg-1));
  margin: 0;
}

.wh-modal-close {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border: none;
  background: transparent;
  border-radius: var(--radius-sm, 6px);
  cursor: pointer;
  color: var(--fg-3, var(--fg-3));
  transition: background-color var(--dur-fast, 150ms) ease, color var(--dur-fast, 150ms) ease;
  flex-shrink: 0;
}

.wh-modal-close:hover {
  background-color: var(--bg-subtle, #F0F2F7);
  color: var(--fg-1, var(--fg-1));
}

.wh-modal-close:focus-visible {
  outline: 3px solid var(--halo-500, var(--halo-500));
  outline-offset: 2px;
}

.wh-modal-desc {
  padding: 0.25rem 1.5rem 0;
  font-size: 0.875rem;
  color: var(--fg-3, var(--fg-3));
  margin: 0;
}

.wh-modal-body {
  padding: 1.25rem 1.5rem;
  overflow-y: auto;
  flex: 1;
}

.wh-modal-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--border-subtle, #E4E8F2);
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  flex-shrink: 0;
}

/* Entry/exit transition */
.wh-modal-enter-active,
.wh-modal-leave-active {
  transition: opacity var(--dur-base, 200ms) ease;
}
.wh-modal-enter-active .wh-modal-dialog,
.wh-modal-leave-active .wh-modal-dialog {
  transition: transform var(--dur-base, 200ms) ease, opacity var(--dur-base, 200ms) ease;
}
.wh-modal-enter-from,
.wh-modal-leave-to {
  opacity: 0;
}
.wh-modal-enter-from .wh-modal-dialog,
.wh-modal-leave-to .wh-modal-dialog {
  transform: translateY(12px) scale(0.97);
  opacity: 0;
}

/* Responsive */
@media (max-width: 640px) {
  .wh-modal-backdrop {
    align-items: flex-end;
    padding: 0;
  }
  .wh-modal-dialog {
    border-bottom-left-radius: 0;
    border-bottom-right-radius: 0;
    max-height: 90dvh;
  }
}
</style>
