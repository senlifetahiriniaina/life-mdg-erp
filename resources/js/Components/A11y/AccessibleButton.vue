<template>
  <button
    :type="type"
    :disabled="disabled"
    :aria-label="ariaLabel"
    :aria-pressed="isToggle ? isPressed : undefined"
    :aria-expanded="isDropdown ? isExpanded : undefined"
    :aria-haspopup="isDropdown ? 'menu' : false"
    class="accessible-button"
    :class="[variant, size, { 'is-loading': loading }]"
  >
    <slot />
    <span v-if="loading" class="sr-only">{{ $t('common.loading') }}</span>
  </button>
</template>

<script setup lang="ts">
interface Props {
  type?: 'button' | 'submit' | 'reset'
  disabled?: boolean
  variant?: 'primary' | 'secondary' | 'danger' | 'ghost'
  size?: 'sm' | 'md' | 'lg'
  ariaLabel?: string
  isToggle?: boolean
  isPressed?: boolean
  isDropdown?: boolean
  isExpanded?: boolean
  loading?: boolean
}

withDefaults(defineProps<Props>(), {
  type: 'button',
  disabled: false,
  variant: 'primary',
  size: 'md',
  isToggle: false,
  isPressed: false,
  isDropdown: false,
  isExpanded: false,
  loading: false,
})
</script>

<style scoped>
.accessible-button {
  font-family: inherit;
  padding: 0.5rem 1rem;
  border: 2px solid transparent;
  border-radius: var(--radius-md, 6px);
  cursor: pointer;
  transition: background-color var(--dur-fast, 150ms) ease, box-shadow var(--dur-fast, 150ms) ease;
  font-weight: 500;
  min-height: 44px; /* WCAG 2.5.5 minimum touch target */
  min-width: 44px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  text-decoration: none;
  line-height: 1;
}

.accessible-button:focus-visible {
  outline: 3px solid var(--halo-500, var(--halo-500));
  outline-offset: 2px;
}

.accessible-button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Primary — uses halo brand tokens */
.accessible-button.primary {
  background-color: var(--halo-500, var(--halo-500));
  color: #fff;
}
.accessible-button.primary:hover:not(:disabled) {
  background-color: var(--halo-600, var(--halo-600));
}
.accessible-button.primary:active:not(:disabled) {
  background-color: var(--halo-700, var(--halo-700));
}

/* Secondary — neutral surface */
.accessible-button.secondary {
  background-color: var(--bg-subtle, #F0F2F7);
  color: var(--fg-1, var(--fg-1));
  border-color: var(--border-default, #D0D4DE);
}
.accessible-button.secondary:hover:not(:disabled) {
  background-color: var(--bg-muted, #E4E8F2);
}

/* Danger — uses red semantic tokens */
.accessible-button.danger {
  background-color: var(--red-600, var(--red-500));
  color: #fff;
}
.accessible-button.danger:hover:not(:disabled) {
  background-color: var(--red-700, #B33620);
}

/* Ghost — transparent, brand text */
.accessible-button.ghost {
  background-color: transparent;
  color: var(--halo-600, var(--halo-600));
}
.accessible-button.ghost:hover:not(:disabled) {
  background-color: var(--halo-50, var(--halo-50));
}

/* Sizes */
.accessible-button.sm {
  padding: 0.375rem 0.75rem;
  font-size: 0.875rem;
  min-height: 36px;
}
.accessible-button.lg {
  padding: 0.75rem 1.5rem;
  font-size: 1.125rem;
  min-height: 52px;
}

.accessible-button.is-loading {
  opacity: 0.6;
  pointer-events: none;
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border-width: 0;
}
</style>
