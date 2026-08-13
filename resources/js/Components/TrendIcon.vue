<template>
  <span :class="`trend-icon trend-${trend}`">
    <i v-if="trend === 'up'" class="pi pi-arrow-up" />
    <i v-else-if="trend === 'down'" class="pi pi-arrow-down" />
    <i v-else class="pi pi-minus" />
  </span>
</template>

<script setup lang="ts">
interface Props {
  trend?: 'up' | 'down' | 'neutral'
}

withDefaults(defineProps<Props>(), {
  trend: 'neutral',
})
</script>

<style scoped>
.trend-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 4px;
}

.trend-up {
  color: var(--green-500);
  background-color: var(--success-bg);
}

.trend-down {
  color: var(--red-500);
  background-color: var(--danger-bg);
}

.trend-neutral {
  color: var(--fg-3);
  background-color: var(--bg-subtle);
}
</style>
