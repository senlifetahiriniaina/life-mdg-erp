<template>
  <div :class="['insight-card', `insight-card--${insight.type}`]">
    <div class="insight-header">
      <div class="insight-icon-wrap">
        <i :class="typeIcon" />
      </div>
      <span class="insight-module-badge">{{ insight.module }}</span>
      <button class="insight-dismiss" :title="'Ignorer'" @click.stop="$emit('dismiss')">
        <i class="pi pi-times" />
      </button>
    </div>
    <div class="insight-title">{{ insight.title }}</div>
    <p class="insight-desc">{{ insight.description }}</p>
    <a
      v-if="insight.action_route"
      class="insight-action-btn"
      @click.prevent="navigate"
    >
      {{ insight.action_label }}
      <i class="pi pi-arrow-right" style="font-size: 11px" />
    </a>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'

export interface Insight {
  type:         'alert' | 'opportunity' | 'action' | 'info'
  module:       string
  title:        string
  description:  string
  action_label: string
  action_route: string
  priority:     number
}

const props = defineProps<{
  insight: Insight
}>()

defineEmits<{ dismiss: [] }>()

const typeIcon = computed(() => {
  switch (props.insight.type) {
    case 'alert':       return 'pi pi-exclamation-circle'
    case 'opportunity': return 'pi pi-lightbulb'
    case 'action':      return 'pi pi-bolt'
    case 'info':        return 'pi pi-info-circle'
    default:            return 'pi pi-info-circle'
  }
})

function navigate() {
  // Convert route like 'CRM/Opportunities/Index' to '/crm/opportunities'
  const parts = props.insight.action_route.split('/')
  const module = parts[0].toLowerCase()
  const rest   = parts.slice(1, -1).map((p: string) => p.toLowerCase())
  const href   = ['', module, ...rest].join('/')
  router.visit(href)
}
</script>

<style scoped>
.insight-card {
  padding: 14px 16px;
  border-radius: var(--r-lg);
  border: 1px solid var(--border-subtle);
  background: var(--bg-canvas);
  display: flex;
  flex-direction: column;
  gap: 8px;
  transition: box-shadow var(--dur-fast);
}
.insight-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.06); }

/* Type color bands */
.insight-card--alert       { border-left: 3px solid var(--red-500); }
.insight-card--opportunity { border-left: 3px solid var(--green-500); }
.insight-card--action      { border-left: 3px solid var(--halo-500); }
.insight-card--info        { border-left: 3px solid var(--fg-4); }

.insight-header { display: flex; align-items: center; gap: 8px; }
.insight-icon-wrap {
  width: 24px; height: 24px; border-radius: var(--r-sm);
  display: flex; align-items: center; justify-content: center;
  font-size: 12px;
}
.insight-card--alert       .insight-icon-wrap { background: var(--danger-bg); color: var(--red-500); }
.insight-card--opportunity .insight-icon-wrap { background: var(--success-bg); color: var(--green-500); }
.insight-card--action      .insight-icon-wrap { background: var(--halo-100); color: var(--halo-500); }
.insight-card--info        .insight-icon-wrap { background: var(--bg-sunken); color: var(--fg-3); }

.insight-module-badge {
  font-size: 10px; font-weight: 600; padding: 2px 7px;
  border-radius: var(--r-pill);
  background: var(--bg-sunken); color: var(--fg-2);
  letter-spacing: .03em;
}

.insight-dismiss {
  margin-left: auto;
  width: 20px; height: 20px;
  border: none; background: none; cursor: pointer;
  color: var(--fg-4); border-radius: var(--r-sm);
  display: flex; align-items: center; justify-content: center;
  font-size: 10px; transition: background var(--dur-fast), color var(--dur-fast);
}
.insight-dismiss:hover { background: var(--bg-sunken); color: var(--fg-1); }

.insight-title { font-size: 13px; font-weight: 600; color: var(--fg-1); line-height: 1.3; }
.insight-desc  { margin: 0; font-size: 12px; color: var(--fg-2); line-height: 1.5; }

.insight-action-btn {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 12px; font-weight: 600;
  color: var(--halo-600); cursor: pointer;
  padding: 5px 0; text-decoration: none;
  transition: color var(--dur-fast);
}
.insight-action-btn:hover { color: var(--halo-800); }
</style>
