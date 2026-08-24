<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'

const { guidance } = useAiAssistant('Calendar', 'team_calendar')

const teamMembers  = ref([])
const events       = ref([])
const loading      = ref(true)
const selectedUser = ref(null)
const currentDate  = ref(new Date())
const view         = ref('week') // week | day

const dayNames  = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim']
const hours     = Array.from({ length: 24 }, (_, i) => i)

onMounted(async () => {
  try {
    // Chantier 32.12: same fix as Index.vue's onMounted — GET calendar/events
    // used to require `start`/`end` (always 422'd here, since neither is
    // sent) and always returned a bare array (so `.data ?? []` always fell
    // back to empty regardless). Both fixed backend-side; this call needed
    // no change.
    const [members, evRes] = await Promise.all([
      fetch('/api/v1/hr/employees?per_page=50').then(r => r.json()).catch(() => ({ data: [] })),
      fetch('/api/v1/calendar/events?per_page=500').then(r => r.json()).catch(() => ({ data: [] })),
    ])
    teamMembers.value = members.data ?? []
    events.value = evRes.data ?? []
  } finally {
    loading.value = false
  }
})

const weekDays = computed(() => {
  const d = new Date(currentDate.value)
  const day = (d.getDay() + 6) % 7 // Monday = 0
  const monday = new Date(d)
  monday.setDate(d.getDate() - day)
  return Array.from({ length: 7 }, (_, i) => {
    const date = new Date(monday)
    date.setDate(monday.getDate() + i)
    return date
  })
})

const weekLabel = computed(() => {
  const start = weekDays.value[0]
  const end   = weekDays.value[6]
  return `${start.getDate()} – ${end.getDate()} ${start.toLocaleString('fr-FR', { month: 'long', year: 'numeric' })}`
})

function eventsForMemberDay(memberId, date) {
  const ds = date.toDateString()
  return events.value.filter(e => {
    const match = e.attendees?.some(a => a.user_id === memberId) || e.user_id === memberId
    return match && new Date(e.start_at).toDateString() === ds
  })
}

function eventsForDay(date) {
  const ds = date.toDateString()
  const userId = selectedUser.value ?? null
  return events.value.filter(e => {
    if (userId && e.user_id !== userId && !e.attendees?.some(a => a.user_id === userId)) return false
    return new Date(e.start_at).toDateString() === ds
  })
}

function isToday(date) {
  return date.toDateString() === new Date().toDateString()
}

function prevWeek() {
  const d = new Date(currentDate.value)
  d.setDate(d.getDate() - 7)
  currentDate.value = d
}
function nextWeek() {
  const d = new Date(currentDate.value)
  d.setDate(d.getDate() + 7)
  currentDate.value = d
}

const eventColor = (ev) => {
  const palette = ['#6366f1','#10b981','#f59e0b','#ef4444','#06b6d4','#8b5cf6','#ec4899']
  return palette[(ev.user_id ?? 0) % palette.length]
}

const memberColor = (idx) => {
  const palette = ['#6366f1','#10b981','#f59e0b','#ef4444','#06b6d4','#8b5cf6','#ec4899']
  return palette[idx % palette.length]
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <div class="max-w-7xl mx-auto">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-4">
          <a href="/calendar" class="text-gray-400 hover:text-gray-600">&larr;</a>
          <div>
            <h1 class="text-xl font-semibold text-gray-800">Calendrier équipe</h1>
            <p class="text-sm text-gray-500">Disponibilités et événements de l'équipe</p>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <button @click="prevWeek" class="p-2 rounded-lg hover:bg-gray-200">&larr;</button>
          <span class="text-sm font-medium w-52 text-center">{{ weekLabel }}</span>
          <button @click="nextWeek" class="p-2 rounded-lg hover:bg-gray-200">&rarr;</button>
          <a href="/calendar/events/create"
            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
            + Événement
          </a>
        </div>
      </div>

      <div v-if="loading" class="bg-white rounded-xl border p-8 text-center text-gray-400">
        Chargement de l'équipe...
      </div>

      <div v-else class="flex gap-6">
        <!-- Member list -->
        <div class="w-48 flex-shrink-0">
          <div class="bg-white rounded-xl border p-3">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Membres</h3>
            <button @click="selectedUser = null"
              :class="['w-full text-left px-2 py-1.5 rounded text-sm transition',
                selectedUser === null ? 'bg-indigo-50 text-indigo-700 font-medium' : 'hover:bg-gray-50 text-gray-700']">
              Toute l'équipe
            </button>
            <button v-for="(member, idx) in teamMembers" :key="member.id"
              @click="selectedUser = member.user_id ?? member.id"
              :class="['w-full text-left px-2 py-1.5 rounded text-sm flex items-center gap-2 transition',
                selectedUser === (member.user_id ?? member.id) ? 'bg-indigo-50 text-indigo-700 font-medium' : 'hover:bg-gray-50 text-gray-700']">
              <span class="w-2 h-2 rounded-full flex-shrink-0" :style="{ backgroundColor: memberColor(idx) }"></span>
              <span class="truncate">{{ member.first_name ?? member.name }}</span>
            </button>
            <p v-if="!teamMembers.length" class="text-xs text-gray-400 mt-2">Aucun membre</p>
          </div>
        </div>

        <!-- Week grid -->
        <div class="flex-1 bg-white rounded-xl border overflow-hidden">
          <!-- Day headers -->
          <div class="grid grid-cols-7 border-b bg-gray-50">
            <div v-for="(day, idx) in weekDays" :key="idx"
              :class="['py-3 text-center border-r last:border-r-0',
                isToday(day) ? 'bg-indigo-50' : '']">
              <p class="text-xs text-gray-500">{{ dayNames[idx] }}</p>
              <p :class="['text-lg font-semibold', isToday(day) ? 'text-indigo-600' : 'text-gray-700']">
                {{ day.getDate() }}
              </p>
            </div>
          </div>

          <!-- Events row -->
          <div class="grid grid-cols-7 min-h-48">
            <div v-for="(day, idx) in weekDays" :key="idx"
              :class="['border-r last:border-r-0 p-2 min-h-32', isToday(day) ? 'bg-indigo-50' : '']">
              <div v-for="ev in eventsForDay(day)" :key="ev.id"
                :style="{ backgroundColor: eventColor(ev) }"
                class="text-white text-xs rounded px-2 py-1 mb-1 truncate cursor-pointer hover:opacity-90">
                {{ ev.title || ev.name }}
              </div>
              <p v-if="!eventsForDay(day).length" class="text-xs text-gray-300 text-center mt-4">—</p>
            </div>
          </div>
        </div>

        <!-- AI panel -->
        <div v-if="guidance" class="w-64 flex-shrink-0">
          <AIAssistantPanel :guidance="guidance" />
        </div>
      </div>
    </div>
  </div>
</template>
