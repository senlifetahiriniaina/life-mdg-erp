<template>
  <div class="wh-page-header">
    <div class="wh-page-header-left">
      <Link v-if="backRoute" :href="backRoute" class="wh-back-btn" :aria-label="$t('common.back')">
        <i class="pi pi-arrow-left" />
      </Link>
      <div>
        <p v-if="subtitle" class="wh-page-subtitle">{{ subtitle }}</p>
        <h1 class="wh-page-title">{{ title }}</h1>
      </div>
    </div>
    <div class="wh-page-header-actions">
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

defineProps<{
  title: string
  subtitle?: string
  backRoute?: string
}>()
</script>

<style scoped>
.wh-page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; gap: 1rem; flex-wrap: wrap; }
.wh-page-header-left { display: flex; align-items: center; gap: 0.75rem; }
.wh-page-title { font-size: 1.5rem; font-weight: 700; color: var(--fg-1); margin: 0; }
.wh-page-subtitle { font-size: 0.75rem; color: var(--fg-3); margin: 0 0 2px; }
.wh-back-btn { display: flex; align-items: center; justify-content: center; width: 2rem; height: 2rem; border-radius: 8px; color: var(--fg-2); transition: background var(--transition-fast); }
.wh-back-btn:hover { background: var(--bg-2, var(--bg-subtle)); }
.wh-page-header-actions { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
</style>
