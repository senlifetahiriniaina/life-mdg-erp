import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

const DB_NAME = 'widehalo_offline'
const STORE_NAME = 'mutations'

export const useSyncStore = defineStore('sync', () => {
  const pendingMutations = ref([])
  const lastSyncAt = ref(localStorage.getItem('last_sync_at') || null)
  const isSyncing = ref(false)

  const hasPendingMutations = computed(() => pendingMutations.value.length > 0)

  // Queue a mutation for offline storage
  const queueMutation = async (mutation) => {
    const entry = {
      id: crypto.randomUUID(),
      client_timestamp: new Date().toISOString(),
      ...mutation,
    }

    pendingMutations.value.push(entry)
    await persistToIndexedDB(entry)

    // If online, push immediately
    if (navigator.onLine) {
      await pushPendingMutations()
    }
  }

  // Push all pending mutations to server
  const pushPendingMutations = async () => {
    if (isSyncing.value || pendingMutations.value.length === 0) return

    isSyncing.value = true
    try {
      const response = await axios.post('/api/v1/sync/push', {
        mutations: pendingMutations.value,
      })

      if (response.data.applied > 0) {
        const appliedIds = pendingMutations.value
          .slice(0, response.data.applied)
          .map(m => m.id)

        pendingMutations.value = pendingMutations.value.filter(
          m => !appliedIds.includes(m.id)
        )

        await clearAppliedFromIndexedDB(appliedIds)
        lastSyncAt.value = new Date().toISOString()
        localStorage.setItem('last_sync_at', lastSyncAt.value)
      }
    } catch (error) {
      console.error('Sync push failed:', error)
    } finally {
      isSyncing.value = false
    }
  }

  // Pull server changes
  const pullChanges = async () => {
    try {
      const response = await axios.get('/api/v1/sync/pull', {
        params: { last_sync_at: lastSyncAt.value },
      })

      lastSyncAt.value = response.data.timestamp
      localStorage.setItem('last_sync_at', lastSyncAt.value)

      return response.data.changes
    } catch (error) {
      console.error('Sync pull failed:', error)
      return []
    }
  }

  // Load pending mutations from IndexedDB on init
  const loadFromIndexedDB = async () => {
    try {
      const db = await openDB()
      const tx = db.transaction(STORE_NAME, 'readonly')
      const store = tx.objectStore(STORE_NAME)
      const all = await promisify(store.getAll())
      pendingMutations.value = all
    } catch {
      // IndexedDB not available
    }
  }

  const persistToIndexedDB = async (mutation) => {
    try {
      const db = await openDB()
      const tx = db.transaction(STORE_NAME, 'readwrite')
      tx.objectStore(STORE_NAME).put(mutation)
    } catch {
      // Silently fail for persistence issues
    }
  }

  const clearAppliedFromIndexedDB = async (ids) => {
    try {
      const db = await openDB()
      const tx = db.transaction(STORE_NAME, 'readwrite')
      const store = tx.objectStore(STORE_NAME)
      ids.forEach(id => store.delete(id))
    } catch {
      // Silently fail for removal issues
    }
  }

  const openDB = () => new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NAME, 1)
    req.onupgradeneeded = (evt) => {
      evt.target.result.createObjectStore(STORE_NAME, { keyPath: 'id' })
    }
    req.onsuccess = (evt) => resolve(evt.target.result)
    req.onerror = (evt) => reject(evt.target.error)
  })

  const promisify = (req) => new Promise((resolve, reject) => {
    req.onsuccess = () => resolve(req.result)
    req.onerror = () => reject(req.error)
  })

  // Initialize
  loadFromIndexedDB()

  return {
    pendingMutations,
    lastSyncAt,
    isSyncing,
    hasPendingMutations,
    queueMutation,
    pushPendingMutations,
    pullChanges,
  }
})
