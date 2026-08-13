<template>
  <Transition name="slide-down">
    <div
      v-if="!isOnline || isSyncing"
      :class="[
        'flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium',
        isSyncing ? 'bg-halo-100 text-halo-700' : 'bg-amber-100 text-amber-700'
      ]"
    >
      <span
        :class="[
          'w-2 h-2 rounded-full',
          isSyncing ? 'bg-halo-500 animate-pulse' : 'bg-amber-500'
        ]"
      />
      <span>
        {{ isSyncing ? $t('offline.syncing') : $t('offline.banner').split('.')[0] }}
      </span>
    </div>
  </Transition>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useOnline } from '@vueuse/core'
import { useSyncStore } from '@/stores/sync'

const isOnline = useOnline()
const syncStore = useSyncStore()
const isSyncing = ref(false)

// When coming back online, trigger sync
let unwatch
onMounted(() => {
  unwatch = isOnline.value
  window.addEventListener('online', handleOnline)
})

onUnmounted(() => {
  window.removeEventListener('online', handleOnline)
})

const handleOnline = async () => {
  if (syncStore.hasPendingMutations) {
    isSyncing.value = true
    try {
      await syncStore.pushPendingMutations()
    } finally {
      isSyncing.value = false
    }
  }
}
</script>

<style scoped>
.slide-down-enter-active, .slide-down-leave-active {
  transition: all 0.3s ease;
}
.slide-down-enter-from, .slide-down-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}
</style>
