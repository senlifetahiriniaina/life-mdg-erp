<template>
  <span class="inline-flex items-center">
    <slot />
    <span
      ref="anchorRef"
      class="ml-1.5 inline-flex items-center justify-center w-4 h-4 rounded-full bg-surface-200 dark:bg-surface-600 text-surface-500 dark:text-surface-300 text-xs font-bold cursor-help hover:bg-primary-100 hover:text-primary-600 dark:hover:bg-primary-900 dark:hover:text-primary-300 transition-colors"
      @mouseenter="show"
      @mouseleave="hide"
      @focus="show"
      @blur="hide"
      tabindex="0"
      :aria-label="text"
    >
      ?
    </span>

    <Teleport to="body">
      <Transition
        enter-active-class="transition-all duration-150"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition-all duration-100"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95"
      >
        <div
          v-if="visible"
          :style="tooltipStyle"
          class="fixed z-50 max-w-xs bg-surface-900 dark:bg-surface-700 text-white text-sm rounded-lg px-3 py-2 shadow-xl pointer-events-none"
          role="tooltip"
        >
          <p class="leading-relaxed">{{ text }}</p>
          <div v-if="link" class="mt-1">
            <a
              :href="link"
              target="_blank"
              rel="noopener"
              class="text-primary-300 hover:underline text-xs pointer-events-auto"
            >
              Learn more →
            </a>
          </div>
          <!-- Arrow -->
          <div
            class="absolute w-2 h-2 bg-surface-900 dark:bg-surface-700 rotate-45"
            :style="arrowStyle"
          />
        </div>
      </Transition>
    </Teleport>
  </span>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'

const props = defineProps<{
  text: string
  link?: string
  placement?: 'top' | 'bottom' | 'left' | 'right'
}>()

const anchorRef = ref<HTMLElement | null>(null)
const visible = ref(false)
const coords = ref({ x: 0, y: 0, width: 0, height: 0 })

function updateCoords() {
  if (!anchorRef.value) return
  const rect = anchorRef.value.getBoundingClientRect()
  coords.value = { x: rect.left, y: rect.top, width: rect.width, height: rect.height }
}

function show() {
  updateCoords()
  visible.value = true
}

function hide() {
  visible.value = false
}

const placement = computed(() => props.placement ?? 'top')

const tooltipStyle = computed(() => {
  const { x, y, width, height } = coords.value
  const offset = 8
  switch (placement.value) {
    case 'bottom': return { top: `${y + height + offset}px`, left: `${x + width / 2}px`, transform: 'translateX(-50%)' }
    case 'left': return { top: `${y + height / 2}px`, left: `${x - offset}px`, transform: 'translate(-100%, -50%)' }
    case 'right': return { top: `${y + height / 2}px`, left: `${x + width + offset}px`, transform: 'translateY(-50%)' }
    default: return { top: `${y - offset}px`, left: `${x + width / 2}px`, transform: 'translate(-50%, -100%)' }
  }
})

const arrowStyle = computed(() => {
  switch (placement.value) {
    case 'bottom': return { top: '-4px', left: '50%', transform: 'translateX(-50%)' }
    case 'left': return { right: '-4px', top: '50%', transform: 'translateY(-50%)' }
    case 'right': return { left: '-4px', top: '50%', transform: 'translateY(-50%)' }
    default: return { bottom: '-4px', left: '50%', transform: 'translateX(-50%)' }
  }
})
</script>
