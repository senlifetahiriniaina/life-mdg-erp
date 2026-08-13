<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'

const { guidance } = useAiAssistant('Calendar', 'view_calendar')
const events = ref([])
const calendars = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    const [ev, cal] = await Promise.all([
      fetch('/api/v1/calendar/events?per_page=50').then(r => r.json()).catch(() => ({ data: [] })),
      fetch('/api/v1/calendar/calendars').then(r => r.json()).catch(() => ({ data: [] })),
    ])
    events.value = ev.data ?? []
    calendars.value = cal.data ?? []
  } finally { loading.value = false }
})

const todayEvents = computed(() => {
  const today = new Date().toDateString()
  return events.value.filter(e => new Date(e.start_at).toDateString() === today)
})

const upcomingEvents = computed(() =>
  events.value.filter(e => new Date(e.start_at) >= new Date())
    .sort((a,b) => new Date(a.start_at) - new Date(b.start_at)).slice(0,10)
)

const fmt = (dt, opts) => new Date(dt).toLocaleString('fr-FR', opts)
const sourceIcon = (s) => ({ google:'🔵', outlook:'🟦', apple:'🍎', internal:'🗓' }[s] ?? '🗓')
</script>

<template>
  <div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-900">Calendrier</h1>
    </div>
    <AIAssistantPanel v-if="guidance" :guidance="guidance" />
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
      <div class="bg-white rounded-lg shadow p-4">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Mes calendriers</h2>
        <ul class="space-y-2">
          <li v-for="cal in calendars" :key="cal.id" class="flex items-center gap-2 text-sm">
            <span :style="`background:${cal.color}`" class="w-3 h-3 rounded-full flex-shrink-0" />
            <span class="truncate">{{ cal.name }}</span>
            <span class="text-xs text-gray-400 ml-auto">{{ sourceIcon(cal.source) }}</span>
          </li>
          <li v-if="!calendars.length" class="text-gray-400 text-xs">Aucun calendrier</li>
        </ul>
      </div>
      <div class="lg:col-span-3 space-y-4">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
          <h2 class="text-sm font-semibold text-blue-800 mb-2">
            Aujourd'hui · {{ fmt(new Date(), {weekday:'long',day:'numeric',month:'long'}) }}
          </h2>
          <div v-if="loading" class="text-sm text-gray-400">Chargement…</div>
          <ul v-else class="space-y-1">
            <li v-for="e in todayEvents" :key="e.id" class="flex items-center gap-3 text-sm">
              <span class="text-gray-500 w-12 text-right text-xs">{{ fmt(e.start_at,{hour:'2-digit',minute:'2-digit'}) }}</span>
              <span class="border-l-2 pl-2 truncate" :style="`border-color:${e.color??'var(--halo-500)'}`">{{ e.title }}</span>
            </li>
            <li v-if="!todayEvents.length" class="text-gray-400 text-sm">Aucun événement aujourd'hui</li>
          </ul>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
          <h2 class="text-lg font-semibold mb-3">Prochains événements</h2>
          <ul class="divide-y">
            <li v-for="e in upcomingEvents" :key="e.id" class="py-2 flex items-start gap-3">
              <div class="text-center w-10 flex-shrink-0">
                <p class="text-xs text-gray-400">{{ fmt(e.start_at,{month:'short'}) }}</p>
                <p class="text-lg font-bold leading-none">{{ new Date(e.start_at).getDate() }}</p>
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium truncate">{{ e.title }}</p>
                <p class="text-xs text-gray-500">
                  {{ fmt(e.start_at,{hour:'2-digit',minute:'2-digit'}) }}
                  <span v-if="e.location"> · 📍 {{ e.location }}</span>
                  <span v-if="e.module_type" class="ml-1 px-1.5 py-0.5 bg-gray-100 rounded text-gray-600">{{ e.module_type }}</span>
                </p>
              </div>
            </li>
            <li v-if="!upcomingEvents.length" class="py-6 text-center text-gray-400 text-sm">Aucun événement à venir</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>
