<template>
  <div v-if="error" class="error-alert" role="alert">
    <div class="error-icon">
      <i class="pi pi-exclamation-circle"></i>
    </div>
    <div class="error-content">
      <h3 v-if="title" class="error-title">{{ title }}</h3>
      <p class="error-message">{{ error }}</p>
      <ul v-if="details && details.length" class="error-details">
        <li v-for="(detail, idx) in details" :key="idx">{{ detail }}</li>
      </ul>
    </div>
    <button v-if="closable" class="error-close" @click="$emit('close')" aria-label="Close alert">
      <i class="pi pi-times"></i>
    </button>
  </div>
</template>

<script setup lang="ts">
interface Props {
  error: string
  title?: string
  details?: string[]
  closable?: boolean
}

defineProps<Props>()
defineEmits(['close'])
</script>

<style scoped>
.error-alert {
  display: flex;
  gap: 1rem;
  padding: 1rem;
  margin-bottom: 1rem;
  background-color: var(--danger-bg);
  border: 1px solid var(--danger-bg);
  border-radius: 6px;
  color: var(--danger-fg);
}

.error-icon {
  flex-shrink: 0;
  display: flex;
  align-items: flex-start;
  font-size: 1.25rem;
  color: var(--red-500);
}

.error-content {
  flex: 1;
}

.error-title {
  margin: 0 0 0.5rem 0;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--danger-fg);
}

.error-message {
  margin: 0;
  font-size: 0.875rem;
  color: var(--danger-fg);
}

.error-details {
  margin: 0.5rem 0 0 0;
  padding-left: 1.25rem;
  font-size: 0.75rem;
  color: var(--danger-fg);
}

.error-details li {
  margin: 0.25rem 0;
}

.error-close {
  flex-shrink: 0;
  background: none;
  border: none;
  padding: 0;
  cursor: pointer;
  font-size: 1rem;
  color: var(--red-500);
  opacity: 0.7;
  transition: opacity 0.2s;
}

.error-close:hover {
  opacity: 1;
}
</style>
