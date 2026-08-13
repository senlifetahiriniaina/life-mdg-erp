<template>
  <div class="flex items-center gap-2">
    <span class="text-xs text-fg-3">{{ simplified ? 'Simplifié' : 'Expert' }}</span>
    <button
      @click="toggle"
      :class="['relative inline-flex h-5 w-9 items-center rounded-full transition-colors', simplified ? 'bg-halo-600' : 'bg-slate-300']"
    >
      <span :class="['inline-block h-3 w-3 transform rounded-full bg-canvas shadow transition-transform', simplified ? 'translate-x-5' : 'translate-x-1']" />
    </button>
    <span class="text-xs font-medium text-halo-600">✨ IA</span>
  </div>
</template>

<script setup lang="ts">
import { ref, provide } from 'vue'

const simplified = ref(true)
provide('simplifiedMode', simplified)

function toggle() {
  simplified.value = !simplified.value
  localStorage.setItem('wh_simplified_mode', simplified.value ? '1' : '0')
}
</script>
