<template>
  <span :class="['wh-badge', colorClass]">{{ label }}</span>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ status: string }>()

const colorMap: Record<string, string> = {
  active: 'wh-badge-success', open: 'wh-badge-success', approved: 'wh-badge-success', won: 'wh-badge-success',
  pending: 'wh-badge-warning', in_progress: 'wh-badge-warning', review: 'wh-badge-warning',
  closed: 'wh-badge-neutral', cancelled: 'wh-badge-neutral', lost: 'wh-badge-neutral', inactive: 'wh-badge-neutral',
  draft: 'wh-badge-neutral',
  overdue: 'wh-badge-danger', critical: 'wh-badge-danger', rejected: 'wh-badge-danger',
}

const colorClass = computed(() => colorMap[props.status] ?? 'wh-badge-neutral')
const label = computed(() => props.status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()))
</script>
