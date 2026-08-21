<script setup>
import { ref, onMounted } from 'vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'

// Chantier 32.12: `Event/Show.vue`'s own "Modifier" button has always
// linked to `/calendar/events/{id}/edit` — a route that never existed
// anywhere in this module (confirmed via grep of routes/web.php), a dead
// link/404 on every click since the Show page was built. The real
// `PUT /api/v1/calendar/events/{event}` endpoint is fully functional
// (updateEvent()) — only the page + route were missing. Built as a
// self-contained page rather than a dual-mode Create/Edit component, same
// separation Show.vue/Create.vue already use.
const { guidance } = useAiAssistant('Calendar', 'create_event')

const eventId = window.location.pathname.split('/').slice(-2, -1)[0]

const loading      = ref(false)
const loadingEvent = ref(true)
const notFound     = ref(false)
const errors       = ref({})

const form = ref({
  title:            '',
  description:      '',
  start_at:         '',
  end_at:           '',
  all_day:          false,
  location:         '',
  color:            '#6366f1',
  attendees:        '',
  reminder_minutes: 0,
})

function toDatetimeLocal(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

function toDateOnly(iso) {
  if (!iso) return ''
  return iso.slice(0, 10)
}

onMounted(async () => {
  try {
    const res = await fetch(`/api/v1/calendar/events/${eventId}`)
    if (!res.ok) {
      notFound.value = true
      return
    }
    const data  = await res.json()
    const event = data.data ?? data
    if (!event || !event.id) {
      notFound.value = true
      return
    }

    form.value.title       = event.title ?? ''
    form.value.description = event.description ?? ''
    form.value.all_day     = !!event.all_day
    form.value.start_at    = event.all_day ? toDateOnly(event.start_at) : toDatetimeLocal(event.start_at)
    form.value.end_at      = event.all_day ? toDateOnly(event.end_at) : toDatetimeLocal(event.end_at)
    form.value.location    = event.location ?? ''
    form.value.color       = event.color || '#6366f1'
    form.value.attendees   = (event.attendees ?? []).map(a => a.email).filter(Boolean).join(', ')
    form.value.reminder_minutes = event.reminders?.[0]?.minutes_before ?? 0
  } catch {
    notFound.value = true
  } finally {
    loadingEvent.value = false
  }
})

async function submit() {
  loading.value = true
  errors.value  = {}
  try {
    const payload = { ...form.value }

    if (payload.attendees) {
      payload.attendees = payload.attendees.split(',').map(e => e.trim()).filter(Boolean)
        .map(email => ({ email }))
    } else {
      delete payload.attendees
    }

    if (payload.reminder_minutes && Number(payload.reminder_minutes) > 0) {
      payload.reminders = [{ minutes_before: Number(payload.reminder_minutes), method: 'popup' }]
    } else {
      payload.reminders = []
    }
    delete payload.reminder_minutes

    const res = await fetch(`/api/v1/calendar/events/${eventId}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(payload),
    })
    const data = await res.json()
    if (!res.ok) {
      errors.value = data.errors ?? { general: data.message ?? 'Erreur' }
    } else {
      window.location.href = `/calendar/events/${eventId}`
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <div class="max-w-3xl mx-auto">
      <div class="flex items-center gap-4 mb-6">
        <a :href="`/calendar/events/${eventId}`" class="text-gray-400 hover:text-gray-600">&larr;</a>
        <h1 class="text-xl font-semibold text-gray-800">Modifier l'événement</h1>
      </div>

      <div v-if="loadingEvent" class="bg-white rounded-xl border p-8 text-center text-gray-400">
        Chargement...
      </div>

      <div v-else-if="notFound" class="bg-white rounded-xl border p-8 text-center text-gray-500">
        Événement introuvable.
      </div>

      <div v-else class="flex gap-6">
        <div class="flex-1 bg-white rounded-xl border p-6 space-y-5">
          <!-- Title -->
          <div>
            <label for="titre" class="block text-sm font-medium text-gray-700 mb-1">Titre *</label>
            <input id="titre" v-model="form.title" type="text" placeholder="Nom de l'événement"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent" :aria-describedby="errors.title ? 'titre-error' : undefined" :aria-invalid="!!errors.title" />
            <p id="titre-error" role="alert" v-if="errors.title" class="text-red-500 text-xs mt-1">{{ errors.title }}</p>
          </div>

          <!-- All day toggle -->
          <div class="flex items-center gap-2">
            <input v-model="form.all_day" type="checkbox" id="all_day" class="rounded" />
            <label for="all_day" class="text-sm text-gray-700">Toute la journée</label>
          </div>

          <!-- Start / End -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label for="d-but" class="block text-sm font-medium text-gray-700 mb-1">Début *</label>
              <input id="d-but" v-model="form.start_at"
                :type="form.all_day ? 'date' : 'datetime-local'"
                class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" :aria-describedby="errors.start_at ? 'd-but-error' : undefined" :aria-invalid="!!errors.start_at" />
              <p id="d-but-error" role="alert" v-if="errors.start_at" class="text-red-500 text-xs mt-1">{{ errors.start_at }}</p>
            </div>
            <div>
              <label for="fin" class="block text-sm font-medium text-gray-700 mb-1">Fin</label>
              <input id="fin" v-model="form.end_at"
                :type="form.all_day ? 'date' : 'datetime-local'"
                class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" :aria-describedby="errors.end_at ? 'fin-error' : undefined" :aria-invalid="!!errors.end_at" />
              <p id="fin-error" role="alert" v-if="errors.end_at" class="text-red-500 text-xs mt-1">{{ errors.end_at }}</p>
            </div>
          </div>

          <!-- Description -->
          <div>
            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea id="description" v-model="form.description" rows="3" placeholder="Détails de l'événement..."
              class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
          </div>

          <!-- Location -->
          <div>
            <label for="lieu" class="block text-sm font-medium text-gray-700 mb-1">Lieu</label>
            <input id="lieu" v-model="form.location" type="text" placeholder="Lieu ou lien visio"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
          </div>

          <!-- Attendees -->
          <div>
            <label for="participants" class="block text-sm font-medium text-gray-700 mb-1">Participants (emails, séparés par virgule)</label>
            <input id="participants" v-model="form.attendees" type="text" placeholder="user@example.com, autre@example.com"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
          </div>

          <!-- Reminder -->
          <div>
            <label for="rappel" class="block text-sm font-medium text-gray-700 mb-1">Rappel (minutes avant)</label>
            <select id="rappel" v-model="form.reminder_minutes"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
              <option :value="0">Aucun</option>
              <option :value="5">5 minutes</option>
              <option :value="15">15 minutes</option>
              <option :value="30">30 minutes</option>
              <option :value="60">1 heure</option>
              <option :value="1440">1 jour</option>
            </select>
          </div>

          <p v-if="errors.general" class="text-red-500 text-sm">{{ errors.general }}</p>

          <div class="flex gap-3 pt-2">
            <button @click="submit" :disabled="loading"
              class="flex-1 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
              {{ loading ? 'Enregistrement...' : 'Enregistrer' }}
            </button>
            <a :href="`/calendar/events/${eventId}`"
              class="px-6 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50 text-center">
              Annuler
            </a>
          </div>
        </div>

        <!-- AI panel -->
        <div v-if="guidance" class="w-72 flex-shrink-0">
          <AIAssistantPanel :guidance="guidance" />
        </div>
      </div>
    </div>
  </div>
</template>
