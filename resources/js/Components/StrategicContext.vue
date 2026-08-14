<script setup>
import { computed } from 'vue'
import { useRoleAccess } from '@/composables/useRoleAccess'
import ProgressBar from 'primevue/progressbar'
import Tag from 'primevue/tag'

const props = defineProps({
  linkableType: String,
  linkableId: Number,
  objective: Object,
  contributionValue: [String, Number],
  showHierarchy: { type: Boolean, default: false },
  showProgress: { type: Boolean, default: true },
  size: { type: String, default: 'md' },
  clickable: { type: Boolean, default: true },
})

const emit = defineEmits(['click:objective'])

const { isAdmin, isElevated } = useRoleAccess()

const canViewContext = computed(() => isAdmin.value || isElevated.value)

const getHealthIcon = (progress) => {
  if (progress >= 80) return 'pi-check-circle text-green-600'
  if (progress >= 50) return 'pi-exclamation-circle text-orange-600'
  return 'pi-times-circle text-red-600'
}

const handleClick = () => {
  if (props.clickable && props.objective) {
    emit('click:objective', props.objective)
  }
}
</script>

<template>
  <div
    v-if="canViewContext && objective"
    class="strategic-context"
    :class="[`size-${size}`, { 'cursor-pointer hover:bg-slate-100': clickable }]"
    :style="{ borderLeftColor: objective.pillar?.color || 'var(--halo-500)' }"
    @click="handleClick"
  >
    <!-- Header: Icon + Pillar + Title -->
    <div class="flex items-center gap-3">
      <div class="flex-shrink-0">
        <i
          class="pi pi-target text-xl"
          :style="{ color: objective.pillar?.color || 'var(--halo-500)' }"
        />
      </div>

      <div class="flex-grow">
        <div class="flex items-center gap-2">
          <Tag
            v-if="objective.pillar"
            :value="objective.pillar.name"
            :style="{
              backgroundColor: (objective.pillar.color || 'var(--halo-500)') + '20',
              color: objective.pillar.color || 'var(--halo-500)',
            }"
            class="text-xs"
          />
          <span class="text-xs text-gray-500">{{ objective.progress || 0 }}% realise</span>
        </div>
        <p class="text-sm font-semibold mt-1">{{ objective.title }}</p>
      </div>

      <!-- Status indicator -->
      <div class="flex-shrink-0">
        <i :class="['pi text-lg', getHealthIcon(objective.progress || 0)]" />
      </div>
    </div>

    <!-- Progress bar -->
    <ProgressBar
      v-if="showProgress"
      :value="objective.progress || 0"
      :show-value="false"
      class="mt-3"
      :style="{ height: '6px' }"
    />

    <!-- Contribution value (optional) -->
    <div v-if="contributionValue" class="mt-3 text-xs text-green-700 font-medium">
      ✓ Contribution: {{ contributionValue }}
    </div>
  </div>
</template>

<style scoped>
.strategic-context {
  padding: 12px 16px;
  border-left: 4px solid var(--halo-500);
  background-color: var(--bg-subtle);
  border-radius: 4px;
  transition: background-color 0.2s;
}

.strategic-context:hover {
  background-color: var(--bg-subtle);
}

.size-sm {
  font-size: 0.875rem;
  padding: 8px 12px;
}

.size-lg {
  font-size: 1rem;
  padding: 16px 20px;
}
</style>
