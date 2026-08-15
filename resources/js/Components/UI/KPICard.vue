<template>
  <div class="wh-kpi" style="position: relative">
    <div class="wh-kpi-label">{{ label }}</div>
    <div class="wh-kpi-num font-display">{{ formattedValue }}</div>
    <div v-if="change !== undefined" :class="['wh-kpi-delta', change > 0 ? 'up' : change < 0 ? 'down' : '']">
      <i v-if="change !== 0" :class="['pi', change > 0 ? 'pi-arrow-up' : 'pi-arrow-down']" style="font-size: 10px" />
      <span>{{ deltaLabel }}</span>
    </div>

    <!-- Loading overlay -->
    <Transition name="fade">
      <div v-if="loading" style="position:absolute;inset:0;background:rgba(247,248,251,0.7);border-radius:var(--r-lg);display:flex;align-items:center;justify-content:center">
        <i class="pi pi-spin pi-spinner" style="color:var(--halo-500);font-size:18px" />
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { n } = useI18n()

const props = withDefaults(defineProps<{
  label:     string
  value:     number | string
  change?:   number
  format?:   'number' | 'currency' | 'percent' | 'raw'
  currency?: string
  loading?:  boolean
  // legacy props kept for backward compat
  title?:    string
  trend?:    number
  icon?:     string
  color?:    string
  iconBg?:   string
  iconColor?: string
}>(), {
  format:   'raw',
  currency: 'USD',
  loading:  false,
})

const formattedValue = computed(() => {
  const v = props.value
  if (typeof v === 'string') return v
  switch (props.format) {
    case 'currency': return n(v, { key: 'currency', currency: props.currency })
    case 'percent':  return `${v}%`
    case 'number':   return n(v, 'decimal')
    default:         return v.toLocaleString()
  }
})

const deltaLabel = computed(() => {
  if (props.change === undefined) return ''
  const abs = Math.abs(props.change)
  return `${abs}%`
})
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 150ms; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
