<template>
  <div
    :class="['mod-card', enabled ? 'mod-card--enabled' : 'mod-card--disabled']"
    :title="enabled ? undefined : 'Module non activé'"
    @click="navigate"
  >
    <div class="mod-card-header">
      <div class="mod-card-icon" :style="{ background: color }">
        <i :class="icon" />
      </div>
      <span :class="['mod-card-badge', enabled ? 'badge-on' : 'badge-off']">
        {{ enabled ? 'Actif' : 'Inactif' }}
      </span>
    </div>
    <div class="mod-card-label">{{ label }}</div>
    <div v-if="kpi !== undefined && enabled" class="mod-card-kpi">
      <span class="mod-card-kpi-val">{{ formattedKpi }}</span>
      <span class="mod-card-kpi-label">{{ kpiLabel }}</span>
    </div>
    <div v-else-if="!enabled" class="mod-card-kpi">
      <span class="mod-card-kpi-label" style="color:var(--fg-4)">Non disponible</span>
    </div>
    <div class="mod-card-arrow">
      <i class="pi pi-arrow-right" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps<{
  module:    string
  label:     string
  icon:      string
  kpi?:      number | string
  kpiLabel?: string
  route:     string
  color:     string
  enabled:   boolean
}>()

const formattedKpi = computed(() => {
  if (props.kpi === undefined) return '—'
  if (typeof props.kpi === 'string') return props.kpi
  return props.kpi.toLocaleString('fr-FR')
})

function navigate() {
  if (!props.enabled) return
  router.visit(props.route)
}
</script>

<style scoped>
.mod-card {
  position: relative;
  padding: 16px;
  border-radius: var(--r-lg);
  background: var(--bg-canvas);
  border: 1px solid var(--border-subtle);
  cursor: pointer;
  transition: box-shadow var(--dur-fast), border-color var(--dur-fast), background var(--dur-fast);
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.mod-card--enabled:hover { box-shadow: 0 4px 12px rgba(0,0,0,.08); border-color: var(--halo-200); }
.mod-card--disabled { opacity: .55; cursor: not-allowed; }

.mod-card-header { display: flex; align-items: center; justify-content: space-between; }
.mod-card-icon {
  width: 36px; height: 36px; border-radius: var(--r-sm);
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-size: 15px;
}

.mod-card-badge {
  font-size: 10px; font-weight: 600; padding: 2px 7px;
  border-radius: var(--r-pill); letter-spacing: .03em;
}
.badge-on  { background: var(--success-bg); color: var(--success-fg); }
.badge-off { background: var(--bg-sunken); color: var(--fg-3); }

.mod-card-label { font-size: 14px; font-weight: 600; color: var(--fg-1); }

.mod-card-kpi { display: flex; align-items: baseline; gap: 6px; }
.mod-card-kpi-val { font-size: 22px; font-weight: 700; color: var(--fg-1); font-variant-numeric: tabular-nums; font-family: var(--font-display); }
.mod-card-kpi-label { font-size: 11px; color: var(--fg-3); }

.mod-card-arrow {
  position: absolute; bottom: 14px; right: 14px;
  font-size: 12px; color: var(--fg-4);
  transition: color var(--dur-fast), transform var(--dur-fast);
}
.mod-card--enabled:hover .mod-card-arrow { color: var(--halo-500); transform: translateX(2px); }
</style>
