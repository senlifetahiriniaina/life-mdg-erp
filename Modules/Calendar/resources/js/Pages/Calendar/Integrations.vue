<script setup>
import { ref, onMounted } from 'vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'

const { guidance } = useAiAssistant('Calendar', 'calendar_integrations')

const integrations = ref([
  {
    id: 'google',
    name: 'Google Calendar',
    icon: '📅',
    description: 'Synchronisez vos événements Google Calendar en temps réel.',
    color: '#4285f4',
    connected: false,
    last_sync: null,
    calendars_synced: 0,
  },
  {
    id: 'outlook',
    name: 'Microsoft Outlook',
    icon: '📆',
    description: 'Connectez votre calendrier Outlook / Microsoft 365.',
    color: '#0078d4',
    connected: false,
    last_sync: null,
    calendars_synced: 0,
  },
  {
    id: 'apple',
    name: 'Apple Calendar (iCloud)',
    icon: '🍎',
    description: 'Synchronisation bidirectionnelle avec iCloud CalDAV.',
    color: '#000000',
    connected: false,
    last_sync: null,
    calendars_synced: 0,
  },
])

const syncing  = ref(null)
const loading  = ref(true)

onMounted(async () => {
  try {
    const res  = await fetch('/api/v1/calendar/sync/status')
    if (res.ok) {
      const data = await res.json()
      const statuses = data.data ?? {}
      integrations.value = integrations.value.map(i => ({
        ...i,
        ...(statuses[i.id] ?? {}),
      }))
    }
  } catch {
    // Use defaults
  } finally {
    loading.value = false
  }
})

async function connectIntegration(id) {
  const res = await fetch(`/api/v1/calendar/sync/${id}/connect`, { method: 'POST' })
  if (res.ok) {
    const data = await res.json()
    if (data.auth_url) {
      window.location.href = data.auth_url
    }
  }
}

async function disconnectIntegration(id) {
  await fetch(`/api/v1/calendar/sync/${id}/disconnect`, { method: 'DELETE' })
  const integration = integrations.value.find(i => i.id === id)
  if (integration) {
    integration.connected = false
    integration.last_sync = null
    integration.calendars_synced = 0
  }
}

async function syncNow(id) {
  syncing.value = id
  try {
    await fetch(`/api/v1/calendar/sync/${id}/sync`, { method: 'POST' })
    const integration = integrations.value.find(i => i.id === id)
    if (integration) {
      integration.last_sync = new Date().toISOString()
    }
  } finally {
    syncing.value = null
  }
}

function formatLastSync(dt) {
  if (!dt) return 'Jamais'
  return new Date(dt).toLocaleString('fr-FR', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <div class="max-w-3xl mx-auto">
      <div class="flex items-center gap-4 mb-6">
        <a href="/calendar" class="text-gray-400 hover:text-gray-600">&larr;</a>
        <div>
          <h1 class="text-xl font-semibold text-gray-800">Intégrations calendrier</h1>
          <p class="text-sm text-gray-500">Synchronisation bidirectionnelle avec vos applications calendrier</p>
        </div>
      </div>

      <div v-if="loading" class="space-y-4">
        <div v-for="i in 3" :key="i" class="h-28 bg-white rounded-xl border animate-pulse"></div>
      </div>

      <div v-else class="space-y-4">
        <div v-for="integ in integrations" :key="integ.id"
          class="bg-white rounded-xl border p-5 flex items-center justify-between">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl"
              :style="{ backgroundColor: integ.color + '15' }">
              {{ integ.icon }}
            </div>
            <div>
              <h3 class="font-semibold text-gray-800">{{ integ.name }}</h3>
              <p class="text-sm text-gray-500">{{ integ.description }}</p>
              <div class="flex items-center gap-3 mt-1">
                <span :class="['inline-flex items-center gap-1 text-xs font-medium',
                  integ.connected ? 'text-green-600' : 'text-gray-400']">
                  <span class="w-1.5 h-1.5 rounded-full"
                    :class="integ.connected ? 'bg-green-500' : 'bg-gray-300'"></span>
                  {{ integ.connected ? 'Connecté' : 'Non connecté' }}
                </span>
                <span v-if="integ.connected" class="text-xs text-gray-400">
                  Dernière sync : {{ formatLastSync(integ.last_sync) }}
                </span>
                <span v-if="integ.calendars_synced" class="text-xs text-gray-400">
                  • {{ integ.calendars_synced }} calendrier(s)
                </span>
              </div>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <button v-if="integ.connected"
              @click="syncNow(integ.id)"
              :disabled="syncing === integ.id"
              class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50">
              {{ syncing === integ.id ? 'Sync...' : '↻ Sync' }}
            </button>
            <button v-if="integ.connected"
              @click="disconnectIntegration(integ.id)"
              class="px-3 py-1.5 text-sm text-red-600 border border-red-200 rounded-lg hover:bg-red-50">
              Déconnecter
            </button>
            <button v-else
              @click="connectIntegration(integ.id)"
              :style="{ backgroundColor: integ.color }"
              class="px-4 py-2 text-sm text-white font-medium rounded-lg hover:opacity-90">
              Connecter
            </button>
          </div>
        </div>
      </div>

      <!-- Import iCal -->
      <div class="mt-6 bg-white rounded-xl border p-5">
        <h3 class="font-semibold text-gray-800 mb-1">Importer un fichier iCal (.ics)</h3>
        <p class="text-sm text-gray-500 mb-3">Importez des événements depuis un fichier .ics exporté d'une autre application.</p>
        <label class="inline-flex items-center gap-2 cursor-pointer px-4 py-2 border-2 border-dashed border-gray-300 rounded-lg text-sm text-gray-600 hover:border-indigo-400 hover:text-indigo-600">
          <span>Choisir un fichier .ics</span>
          <input type="file" accept=".ics" class="hidden"
            @change="e => alert('Import via /api/v1/calendar/import — coming soon')" />
        </label>
      </div>

      <!-- AI panel -->
      <div v-if="guidance" class="mt-6">
        <AIAssistantPanel :guidance="guidance" />
      </div>
    </div>
  </div>
</template>
