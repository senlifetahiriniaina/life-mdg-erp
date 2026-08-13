<template>
  <button class="wh-pill" :title="meta.label" role="status" aria-live="polite">
    <span
      class="pill-dot"
      :style="{ background: meta.dot }"
      :class="{ 'animate-pulse': isSyncing }"
    />
    <span class="cap">{{ meta.cap }}</span>
  </button>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useOnline } from '@vueuse/core'
import { useSyncStore } from '@/stores/sync'

const isOnline = useOnline()
const syncStore = useSyncStore()
const isSyncing = ref(false)

const meta = computed(() => {
  if (isSyncing.value) return { dot: 'var(--amber-400)', cap: 'Sync…',     label: 'Synchronisation en cours…' }
  if (!isOnline.value) return { dot: 'var(--red-500)',   cap: 'Hors-ligne', label: 'Hors-ligne · modifications en file' }
  return                       { dot: 'var(--green-500)', cap: 'En ligne',   label: 'En ligne · synchronisé' }
})

const handleOnline = async () => {
  if (syncStore.hasPendingMutations) {
    isSyncing.value = true
    try { await syncStore.pushPendingMutations() }
    finally { isSyncing.value = false }
  }
}

onMounted(() => window.addEventListener('online', handleOnline))
onUnmounted(() => window.removeEventListener('online', handleOnline))
</script>
