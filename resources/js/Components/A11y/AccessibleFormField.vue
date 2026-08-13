<template>
  <div class="accessible-form-field">
    <label :for="fieldId" class="label">
      {{ label }}
      <span v-if="required" class="required" aria-hidden="true">*</span>
      <span v-if="required" class="sr-only">{{ $t ? $t('common.required') : 'required' }}</span>
    </label>
    <slot :attrs="ariaAttrs" />
    <span
      v-if="error"
      :id="`${fieldId}-error`"
      class="error-message"
      role="alert"
      aria-live="polite"
    >
      <span aria-hidden="true">⚠</span> {{ error }}
    </span>
    <span v-if="hint && !error" :id="`${fieldId}-hint`" class="hint">
      {{ hint }}
    </span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useAccessibility } from '@/composables/useAccessibility'

interface Props {
  label: string
  fieldId: string
  error?: string
  hint?: string
  required?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  required: false,
})

const { getAriaAttrs } = useAccessibility()

const ariaAttrs = computed(() =>
  getAriaAttrs(props.fieldId, props.required, !!props.error, props.error)
)
</script>

<style scoped>
.accessible-form-field {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  margin-bottom: 1.25rem;
}

.label {
  font-weight: 500;
  font-size: 0.9375rem;
  color: var(--fg-1, var(--fg-1));
  display: flex;
  gap: 0.25rem;
  align-items: baseline;
}

.required {
  color: var(--red-600, var(--red-500));
  font-weight: 700;
}

.error-message {
  color: var(--red-600, var(--red-500));
  font-size: 0.875rem;
  display: flex;
  align-items: center;
  gap: 0.375rem;
}

.hint {
  color: var(--fg-3, var(--fg-3));
  font-size: 0.875rem;
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
