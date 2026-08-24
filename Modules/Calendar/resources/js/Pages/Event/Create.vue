<script setup>
import { ref, onMounted } from 'vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'

const { guidance } = useAiAssistant('Calendar', 'create_event')

const loading   = ref(false)
const errors    = ref({})
const calendars = ref([])

const form = ref({
  title:       '',
  description: '',
  calendar_id: '',
  start_at:    new URLSearchParams(window.location.search).get('date')
               ? `${new URLSearchParams(window.location.search).get('date')}T09:00`
               : '',
  end_at:      '',
  all_day:     false,
  location:    '',
  url:         '',
  color:       '#6366f1',
  recurrence:  'none',
  attendees:   '',
  reminder_minutes: 30,
})

const eventTypes = [
  { value: 'meeting',   label: 'Réunion' },
  { value: 'task',      label: 'Tâche' },
  { value: 'deadline',  label: 'Échéance' },
  { value: 'reminder',  label: 'Rappel' },
  { value: 'holiday',   label: 'Congé / Férié' },
]

onMounted(async () => {
  const res = await fetch('/api/v1/calendar/calendars').catch(() => ({ ok: false }))
  if (res.ok) {
    const data = await res.json()
    calendars.value = data.data ?? []
    if (calendars.value.length) {
      form.value.calendar_id = calendars.value[0].id
    }
  }
})

async function submit() {
  loading.value = true
  errors.value  = {}
  try {
    const payload = { ...form.value }
    // Chantier 32.12: `attendees` was sent as a bare array of email
    // strings (['a@x.com', 'b@x.com']) — the real endpoint validates
    // `attendees.*.email`, an array of OBJECTS, so this 422'd
    // ("attendees.0.email is required") on every real submission with a
    // participant filled in, confirmed empirically. Fixed to send the
    // shape the backend actually validates.
    if (payload.attendees) {
      payload.attendees = payload.attendees.split(',').map(e => e.trim()).filter(Boolean)
        .map(email => ({ email }))
    } else {
      delete payload.attendees
    }
    // Chantier 32.12: `reminder_minutes` (the dropdown's own v-model) was
    // never translated into the `reminders` array the backend actually
    // reads (`reminders.*.minutes_before`/`.method`) — the field existed
    // on the form and was sent as a flat scalar the backend simply
    // ignores (unknown key), so picking a reminder delay has silently
    // never created a real reminder since this page was built.
    if (payload.reminder_minutes && Number(payload.reminder_minutes) > 0) {
      payload.reminders = [{ minutes_before: Number(payload.reminder_minutes), method: 'popup' }]
    }
    delete payload.reminder_minutes
    // `recurrence` has no matching backend field (the real column is
    // `recurrence_rule`, a raw RRULE string) and this page never exposes a
    // control to set it — dropped rather than sent as an ignored key.
    delete payload.recurrence
    const res = await fetch('/api/v1/calendar/events', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(payload),
    })
    const data = await res.json()
    if (!res.ok) {
      errors.value = data.errors ?? { general: data.message ?? 'Erreur' }
    } else {
      window.location.href = `/calendar`
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
        <a href="/calendar" class="text-gray-400 hover:text-gray-600">&larr;</a>
        <h1 class="text-xl font-semibold text-gray-800">Créer un événement</h1>
      </div>

      <div class="flex gap-6">
        <div class="flex-1 bg-white rounded-xl border p-6 space-y-5">
          <!-- Title -->
          <div>
            <label for="titre" class="block text-sm font-medium text-gray-700 mb-1">Titre *</label>
            <input id="titre" v-model="form.title" type="text" placeholder="Nom de l'événement"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent" :aria-describedby="errors.title ? 'titre-error' : undefined" :aria-invalid="!!errors.title" />
            <p id="titre-error" role="alert" v-if="errors.title" class="text-red-500 text-xs mt-1">{{ errors.title }}</p>
          </div>

          <!-- Calendar -->
          <div>
            <label for="calendrier" class="block text-sm font-medium text-gray-700 mb-1">Calendrier</label>
            <select id="calendrier" v-model="form.calendar_id"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
              <option v-for="cal in calendars" :key="cal.id" :value="cal.id">{{ cal.name }}</option>
            </select>
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
                class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
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
            <label for="participants-emails-s-par-s-par-virgule" class="block text-sm font-medium text-gray-700 mb-1">Participants (emails, séparés par virgule)</label>
            <input id="participants-emails-s-par-s-par-virgule" v-model="form.attendees" type="text" placeholder="user@example.com, autre@example.com"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500" />
          </div>

          <!-- Reminder -->
          <div>
            <label for="rappel-minutes-avant" class="block text-sm font-medium text-gray-700 mb-1">Rappel (minutes avant)</label>
            <select id="rappel-minutes-avant" v-model="form.reminder_minutes"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500">
              <option :value="0">Aucun</option>
              <option :value="5">5 minutes</option>
              <option :value="15">15 minutes</option>
              <option :value="30">30 minutes</option>
              <option :value="60">1 heure</option>
              <option :value="1440">1 jour</option>
            </select>
          </div>

          <!-- Color -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Couleur</label>
            <div class="flex gap-2">
              <button v-for="color in ['var(--halo-500)','var(--green-500)','var(--amber-400)','var(--red-500)','var(--halo-500)','var(--halo-500)','var(--red-400)']"
                :key="color"
                @click="form.color = color"
                :style="{ backgroundColor: color }"
                :class="['w-7 h-7 rounded-full border-2 transition', form.color === color ? 'border-gray-800 scale-110' : 'border-transparent']">
              </button>
            </div>
          </div>

          <p v-if="errors.general" class="text-red-500 text-sm">{{ errors.general }}</p>

          <div class="flex gap-3 pt-2">
            <button @click="submit" :disabled="loading"
              class="flex-1 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">
              {{ loading ? 'Enregistrement...' : 'Créer l\'événement' }}
            </button>
            <a href="/calendar"
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
