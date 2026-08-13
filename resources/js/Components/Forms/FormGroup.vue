<template>
  <div class="form-group" :class="{ 'form-group-error': hasError, 'form-group-disabled': disabled }">
    <label v-if="label" :for="fieldId" class="form-label">
      {{ label }}
      <span v-if="required" class="form-required" aria-label="required">*</span>
    </label>

    <div class="form-input-wrapper">
      <slot />
    </div>

    <p v-if="hint && !hasError" class="form-hint">{{ hint }}</p>
    <p v-if="hasError && error" class="form-error" role="alert">
      <i class="pi pi-exclamation-circle"></i>
      {{ error }}
    </p>

    <div v-if="character && characterLimit" class="form-counter">
      <span :class="{ 'counter-warning': character > characterLimit * 0.8 }">
        {{ character }} / {{ characterLimit }}
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  fieldId: string
  label?: string
  hint?: string
  error?: string
  required?: boolean
  disabled?: boolean
  character?: number
  characterLimit?: number
}

const props = defineProps<Props>()

const hasError = computed(() => !!props.error)
</script>

<style scoped>
.form-group {
  margin-bottom: 1.5rem;
  display: flex;
  flex-direction: column;
}

.form-group-disabled {
  opacity: 0.6;
  pointer-events: none;
}

.form-label {
  display: block;
  margin-bottom: 0.5rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--fg-1);
  cursor: pointer;
}

.form-required {
  color: var(--red-500);
  margin-left: 0.25rem;
}

.form-input-wrapper {
  position: relative;
}

.form-hint {
  margin: 0.5rem 0 0 0;
  font-size: 0.75rem;
  color: var(--fg-3);
}

.form-error {
  margin: 0.5rem 0 0 0;
  padding: 0.5rem;
  font-size: 0.875rem;
  color: var(--red-500);
  background-color: var(--danger-bg);
  border-left: 3px solid var(--red-500);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.form-error i {
  flex-shrink: 0;
}

.form-counter {
  margin-top: 0.5rem;
  font-size: 0.75rem;
  color: var(--fg-3);
  text-align: right;
}

.counter-warning {
  color: var(--yellow-500);
  font-weight: 500;
}

@media (max-width: 640px) {
  .form-group {
    margin-bottom: 1rem;
  }

  .form-label {
    font-size: 0.9375rem;
  }
}
</style>
