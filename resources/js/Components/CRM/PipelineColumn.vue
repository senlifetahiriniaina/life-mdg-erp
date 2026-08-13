<template>
  <div class="pipeline-column">
    <div class="column-header">
      <h3 class="column-title">{{ stage }}</h3>
      <span class="deal-count">{{ deals.length }}</span>
    </div>
    <div class="column-deals">
      <div v-if="deals.length" class="space-y-2">
        <div v-for="deal in deals" :key="deal.id" class="deal-card">
          <p class="deal-name">{{ deal.name }}</p>
          <p v-if="deal.value" class="deal-value">{{ formatCurrency(deal.value) }}</p>
        </div>
      </div>
      <p v-else class="empty-state">No deals</p>
    </div>
  </div>
</template>

<script setup lang="ts">
interface Props {
  stage: string
  deals?: Array<{ id: string | number; name: string; value?: number }>
}

withDefaults(defineProps<Props>(), {
  deals: () => [],
})

const formatCurrency = (value: number): string => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(value)
}
</script>

<style scoped>
.pipeline-column {
  min-width: 300px;
  background-color: var(--bg-subtle);
  border: 1px solid var(--border-subtle);
  border-radius: 8px;
  padding: 1rem;
  flex-shrink: 0;
}

.column-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
  padding-bottom: 0.75rem;
  border-bottom: 2px solid var(--border-subtle);
}

.column-title {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 600;
  text-transform: capitalize;
  color: var(--fg-1);
}

.deal-count {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  background-color: var(--halo-500);
  color: white;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 700;
}

.column-deals {
  min-height: 100px;
}

.deal-card {
  background-color: white;
  border: 1px solid var(--border-subtle);
  border-radius: 6px;
  padding: 0.75rem;
  cursor: move;
}

.deal-name {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--fg-1);
}

.deal-value {
  margin: 0.25rem 0 0 0;
  font-size: 0.75rem;
  color: var(--fg-3);
}

.empty-state {
  color: var(--fg-4);
  text-align: center;
  font-size: 0.875rem;
}
</style>
