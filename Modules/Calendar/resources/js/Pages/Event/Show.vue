<script setup>
import { ref, onMounted } from 'vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'

const { guidance } = useAiAssistant('Calendar', 'view_event')

const event     = ref(null)
const loading   = ref(true)
const deleting  = ref(false)
const showDelete = ref(false)

// Get event ID from URL
const eventId = window.location.pathname.split('/').at(-1)

onMounted(async () => {
  try {
    const res  = await fetch(`/api/v1/calendar/events/${eventId}`)
    const data = await res.json()
    event.value = data.data ?? data
  } catch {
    event.value = null
  } finally {
    loading.value = false
  }
})

function formatDate(dt) {
  if (!dt) return '—'
  return new Date(dt).toLocaleString('fr-FR', {
    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

function formatDateShort(dt) {
  if (!dt) return '—'
  return new Date(dt).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' })
}

async function deleteEvent() {
  deleting.value = true
  try {
    await fetch(`/api/v1/calendar/events/${eventId}`, { method: 'DELETE' })
    window.location.href = '/calendar'
  } finally {
    deleting.value = false
    showDelete.value = false
  }
}

const statusColors = {
  confirmed: 'bg-green-100 text-green-700',
  tentative: 'bg-yellow-100 text-yellow-700',
  cancelled: 'bg-red-100 text-red-700',
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <div class="max-w-3xl mx-auto">
      <div class="flex items-center gap-4 mb-6">
        <a href="/calendar" class="text-gray-400 hover:text-gray-600">&larr;</a>
        <h1 class="text-xl font-semibold text-gray-800">Détail événement</h1>
      </div>

      <div v-if="loading" class="bg-white rounded-xl border p-8 text-center text-gray-400">
        Chargement...
      </div>

      <div v-else-if="!event" class="bg-white rounded-xl border p-8 text-center text-gray-500">
        Événement introuvable.
      </div>

      <div v-else class="flex gap-6">
        <div class="flex-1 bg-white rounded-xl border overflow-hidden">
          <!-- Color banner -->
          <div class="h-2" :style="{ backgroundColor: event.color || 'var(--halo-500)' }"></div>

          <div class="p-6 space-y-5">
            <!-- Title & status -->
            <div class="flex items-start justify-between">
              <div>
                <h2 class="text-2xl font-bold text-gray-800">{{ event.title || event.name }}</h2>
                <p v-if="event.calendar_name" class="text-sm text-gray-500 mt-1">{{ event.calendar_name }}</p>
              </div>
              <span v-if="event.status"
                :class="['px-3 py-1 rounded-full text-xs font-semibold', statusColors[event.status] ?? 'bg-gray-100 text-gray-600']">
                {{ event.status }}
              </span>
            </div>

            <!-- Dates -->
            <div class="grid grid-cols-2 gap-4 py-4 border-t border-b">
              <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Début</p>
                <p class="text-sm font-medium text-gray-700">{{ formatDate(event.start_at) }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Fin</p>
                <p class="text-sm font-medium text-gray-700">{{ formatDate(event.end_at) }}</p>
              </div>
            </div>

            <!-- Location -->
            <div v-if="event.location">
              <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Lieu</p>
              <p class="text-sm text-gray-700">{{ event.location }}</p>
            </div>

            <!-- Description -->
            <div v-if="event.description">
              <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Description</p>
              <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ event.description }}</p>
            </div>

            <!-- Attendees -->
            <div v-if="event.attendees?.length">
              <p class="text-xs text-gray-400 uppercase tracking-wide mb-2">Participants</p>
              <div class="flex flex-wrap gap-2">
                <span v-for="att in event.attendees" :key="att.id ?? att.email"
                  class="px-3 py-1 bg-gray-100 rounded-full text-xs text-gray-600">
                  {{ att.name ?? att.email ?? att }}
                </span>
              </div>
            </div>

            <!-- URL -->
            <div v-if="event.url">
              <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Lien</p>
              <a :href="event.url" target="_blank" class="text-sm text-indigo-600 hover:underline break-all">{{ event.url }}</a>
            </div>

            <!-- Actions -->
            <div class="flex gap-3 pt-2 border-t">
              <a :href="`/calendar/events/${eventId}/edit`"
                class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                Modifier
              </a>
              <button @click="showDelete = true"
                class="px-5 py-2 bg-red-50 text-red-600 text-sm font-medium rounded-lg hover:bg-red-100 border border-red-200">
                Supprimer
              </button>
              <a href="/calendar"
                class="px-5 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                Retour
              </a>
            </div>
          </div>
        </div>

        <!-- AI panel -->
        <div v-if="guidance" class="w-72 flex-shrink-0">
          <AIAssistantPanel :guidance="guidance" />
        </div>
      </div>
    </div>

    <!-- Delete confirmation modal -->
    <div v-if="showDelete"
      class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl p-6 w-96 shadow-xl">
        <h2 class="text-lg font-semibold mb-2">Supprimer l'événement ?</h2>
        <p class="text-sm text-gray-500 mb-5">Cette action est irréversible.</p>
        <div class="flex justify-end gap-3">
          <button @click="showDelete = false"
            class="px-4 py-2 text-sm border rounded-lg hover:bg-gray-50">Annuler</button>
          <button @click="deleteEvent" :disabled="deleting"
            class="px-4 py-2 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50">
            {{ deleting ? 'Suppression...' : 'Supprimer' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
