<template>
  <div class="responsive-grid" :style="gridStyles">
    <slot />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useResponsive } from '@/composables/useResponsive'

interface Props {
  cols?: number | { xs: number; sm: number; md: number; lg: number; xl: number }
  gap?: string | number
  mobileStack?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  cols: 12,
  gap: '1rem',
  mobileStack: true,
})

const { isMobile } = useResponsive()

const getColsForBreakpoint = (): number => {
  if (typeof props.cols === 'number') return props.cols

  if (isMobile.value) return props.cols.xs
  return props.cols.md || 12
}

const gridStyles = computed(() => {
  const cols = getColsForBreakpoint()
  const gapValue = typeof props.gap === 'number' ? `${props.gap}px` : props.gap

  return {
    display: 'grid',
    gridTemplateColumns: `repeat(${cols}, 1fr)`,
    gap: gapValue,
  }
})
</script>

<style scoped>
.responsive-grid {
  width: 100%;
}

@media (max-width: 640px) {
  .responsive-grid {
    grid-auto-flow: row;
  }
}
</style>
