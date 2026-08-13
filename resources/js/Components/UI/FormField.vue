<template>
  <div class="wh-form-field" :class="{ 'wh-form-field--error': !!error }">
    <label v-if="label" :for="fieldId" class="wh-label">
      {{ label }}<span v-if="required" class="wh-required" aria-hidden="true"> *</span>
    </label>
    <slot :id="fieldId" :aria-describedby="error ? `${fieldId}-error` : undefined" :aria-invalid="!!error" :aria-required="required" />
    <p v-if="error" :id="`${fieldId}-error`" class="wh-field-error" role="alert">{{ error }}</p>
    <p v-else-if="hint" class="wh-field-hint">{{ hint }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
const props = defineProps<{ label?: string; error?: string; hint?: string; required?: boolean; name?: string }>()
const fieldId = computed(() => props.name ?? `field-${Math.random().toString(36).slice(2, 7)}`)
</script>

<style scoped>
.wh-form-field { display: flex; flex-direction: column; gap: 4px; }
.wh-label { font-size: 0.8125rem; font-weight: 500; color: var(--fg-2); }
.wh-required { color: var(--color-error, var(--red-500)); margin-left: 2px; }
.wh-field-error { font-size: 0.75rem; color: var(--color-error, var(--red-500)); margin: 0; }
.wh-field-hint { font-size: 0.75rem; color: var(--fg-3); margin: 0; }
</style>
