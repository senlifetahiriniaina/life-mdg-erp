<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'

const { guidance } = useAiAssistant('Calendar', 'view_calendar')

const events        = ref([])
const calendars     = ref([])
const loading       = ref(true)
const currentView   = ref('month') // month | week | day | agenda
const currentDate   = ref(new Date())
const showCreateModal = ref(false)
const selectedDate  = ref(null)

const monthNames = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre']
const dayNames   = ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim']

onMounted(async () => {
  try {
    // Chantier 32.12: `events` used to be fetched with no start/end params
    // at all — the backend used to require both (a guaranteed 422 on every
    // real load, silently swallowed by the .catch() below). The backend
    // now defaults to a broad window when they're omitted, so this call is
    // left as-is; both endpoints now correctly respond `{data: [...]}`
    // (previously bare arrays, which `.data ?? []` always resolved to an
    // empty list for regardless of real content).
    const [ev, cal] = await Promise.all([
      fetch('/api/v1/calendar/events?per_page=200').then(r => r.json()).catch(() => ({ data: [] })),
      fetch('/api/v1/calendar/calendars').then(r => r.json()).catch(() => ({ data: [] })),
    ])
    events.value   = ev.data   ?? []
    calendars.value = cal.data ?? []
  } finally {
    loading.value = false
  }
})

const currentMonthLabel = computed(() => {
  return `${monthNames[currentDate.value.getMonth()]} ${currentDate.value.getFullYear()}`
})

const calendarDays = computed(() => {
  const year  = currentDate.value.getFullYear()
  const month = currentDate.value.getMonth()
  const first = new Date(year, month, 1)
  const last  = new Date(year, month + 1, 0)
  // Start from Monday
  const startDay = (first.getDay() + 6) % 7
  const days = []
  // Fill leading empty cells
  for (let i = 0; i < startDay; i++) {
    const d = new Date(year, month, -startDay + i + 1)
    days.push({ date: d, current: false })
  }
  for (let d = 1; d <= last.getDate(); d++) {
    days.push({ date: new Date(year, month, d), current: true })
  }
  // Fill trailing cells to complete last row
  const remaining = 42 - days.length
  for (let i = 1; i <= remaining; i++) {
    days.push({ date: new Date(year, month + 1, i), current: false })
  }
  return days
})

function eventsForDay(date) {
  const ds = date.toDateString()
  return events.value.filter(e => new Date(e.start_at).toDateString() === ds)
}

function isToday(date) {
  return date.toDateString() === new Date().toDateString()
}

function prevMonth() {
  currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() - 1, 1)
}
function nextMonth() {
  currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + 1, 1)
}
function goToday() {
  currentDate.value = new Date()
}

function onDayClick(day) {
  selectedDate.value = day.date
  showCreateModal.value = true
}

function eventColor(ev) {
  const colors = { work: '#6366f1', personal: '#10b981', holiday: '#f59e0b', deadline: '#ef4444' }
  return colors[ev.type] ?? '#6366f1'
}
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="bg-white border-b px-6 py-4 flex items-center justify-between">
      <div class="flex items-center gap-4">
        <h1 class="text-xl font-semibold text-gray-800">Calendrier</h1>
        <div class="flex items-center gap-2 bg-gray-100 rounded-lg p-1">
          <button v-for="v in ['month','week','day','agenda']" :key="v"
            @click="currentView = v"
            :class="['px-3 py-1 rounded text-sm font-medium transition', currentView === v ? 'bg-white shadow text-indigo-600' : 'text-gray-500 hover:text-gray-700']">
            {{ { month: 'Mois', week: 'Semaine', day: 'Jour', agenda: 'Agenda' }[v] }}
          </button>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <button @click="goToday" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Aujourd'hui</button>
        <div class="flex items-center gap-1">
          <button @click="prevMonth" class="p-1.5 rounded-lg hover:bg-gray-100">&larr;</button>
          <span class="text-sm font-medium w-40 text-center">{{ currentMonthLabel }}</span>
          <button @click="nextMonth" class="p-1.5 rounded-lg hover:bg-gray-100">&rarr;</button>
        </div>
        <a href="/calendar/events/create"
          class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
          + Événement
        </a>
      </div>
    </div>

    <div class="flex gap-6 p-6">
      <!-- Sidebar calendars -->
      <div class="w-56 flex-shrink-0">
        <div class="bg-white rounded-xl border p-4">
          <h3 class="text-sm font-semibold text-gray-600 mb-3">Mes calendriers</h3>
          <div v-if="loading" class="space-y-2">
            <div v-for="i in 3" :key="i" class="h-6 bg-gray-200 rounded animate-pulse"></div>
          </div>
          <ul v-else class="space-y-1">
            <li v-for="cal in calendars" :key="cal.id" class="flex items-center gap-2 text-sm text-gray-700 py-1">
              <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: cal.color || 'var(--halo-500)' }"></span>
              {{ cal.name }}
            </li>
            <li v-if="!calendars.length" class="text-xs text-gray-400">Aucun calendrier</li>
          </ul>
          <a href="/calendar/integrations"
            class="mt-4 block text-xs text-indigo-600 hover:underline">
            + Synchroniser Google/Outlook
          </a>
        </div>
      </div>

      <!-- Calendar grid -->
      <div class="flex-1">
        <div v-if="loading" class="bg-white rounded-xl border p-8 text-center text-gray-400">
          Chargement...
        </div>
        <div v-else class="bg-white rounded-xl border overflow-hidden">
          <!-- Day headers -->
          <div class="grid grid-cols-7 border-b bg-gray-50">
            <div v-for="day in dayNames" :key="day"
              class="py-2 text-center text-xs font-semibold text-gray-500">
              {{ day }}
            </div>
          </div>
          <!-- Days grid -->
          <div class="grid grid-cols-7">
            <div v-for="(day, idx) in calendarDays" :key="idx"
              @click="onDayClick(day)"
              :class="['min-h-24 border-b border-r p-1 cursor-pointer hover:bg-gray-50 transition',
                !day.current ? 'bg-gray-50' : '',
                isToday(day.date) ? 'bg-indigo-50' : '']" role="button" tabindex="0" @keydown.enter.prevent="onDayClick(day)">
              <!-- Day number -->
              <span :class="['inline-flex items-center justify-center w-6 h-6 text-xs font-medium rounded-full mb-1',
                isToday(day.date) ? 'bg-indigo-600 text-white' : day.current ? 'text-gray-700' : 'text-gray-400']">
                {{ day.date.getDate() }}
              </span>
              <!-- Events -->
              <div class="space-y-0.5">
                <div v-for="ev in eventsForDay(day.date).slice(0, 3)" :key="ev.id"
                  :style="{ backgroundColor: eventColor(ev) }"
                  class="text-white text-xs rounded px-1 py-0.5 truncate">
                  {{ ev.title || ev.name }}
                </div>
                <div v-if="eventsForDay(day.date).length > 3"
                  class="text-xs text-gray-400">
                  +{{ eventsForDay(day.date).length - 3 }} autres
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- AI Panel -->
      <div v-if="guidance" class="w-72 flex-shrink-0">
        <AIAssistantPanel :guidance="guidance" />
      </div>
    </div>

    <!-- Create event modal placeholder -->
    <div v-if="showCreateModal"
      class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl p-6 w-96 shadow-xl">
        <h2 class="text-lg font-semibold mb-4">Nouvel événement</h2>
        <p class="text-sm text-gray-500 mb-4">
          Date : {{ selectedDate?.toLocaleDateString('fr-FR') }}
        </p>
        <div class="flex justify-end gap-3">
          <button @click="showCreateModal = false"
            class="px-4 py-2 text-sm border rounded-lg hover:bg-gray-50">Annuler</button>
          <a :href="`/calendar/events/create?date=${selectedDate?.toISOString().split('T')[0]}`"
            class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
            Créer
          </a>
        </div>
      </div>
    </div>
  </div>
</template>
