<template>
  <AppLayout>
    <Head :title="`${project.name} — Calendrier`" />

    <div class="page-head">
      <div>
        <div style="display:flex;align-items:center;gap:10px">
          <div :style="{ width:'12px', height:'12px', borderRadius:'50%', background: project.color }" />
          <h1 class="wh-page-title">{{ project.name }} · Calendrier</h1>
        </div>
        <p class="wh-page-subtitle">{{ monthLabel }}</p>
      </div>
      <div class="page-actions">
        <a :href="`/projects/${project.id}`" class="btn btn-secondary"><i class="pi pi-list" style="font-size:13px" /> Liste</a>
        <a :href="`/projects/${project.id}/kanban`" class="btn btn-secondary"><i class="pi pi-th-large" style="font-size:13px" /> Kanban</a>
        <a :href="`/projects/${project.id}/gantt`" class="btn btn-secondary"><i class="pi pi-chart-bar" style="font-size:13px" /> Gantt</a>
        <div style="display:flex;gap:4px">
          <button class="btn btn-secondary" @click="prevMonth"><i class="pi pi-chevron-left" /></button>
          <button class="btn btn-secondary" @click="today">Aujourd'hui</button>
          <button class="btn btn-secondary" @click="nextMonth"><i class="pi pi-chevron-right" /></button>
        </div>
      </div>
    </div>

    <div v-if="loading" class="wh-panel" style="padding:40px;text-align:center;color:var(--fg-3)">
      <i class="pi pi-spin pi-spinner" style="font-size:20px" />
    </div>

    <div v-else class="wh-panel cal-grid-wrap">
      <!-- Day headers -->
      <div class="cal-header">
        <div v-for="d in ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim']" :key="d" class="cal-dow">{{ d }}</div>
      </div>
      <!-- Weeks -->
      <div class="cal-body">
        <div v-for="(week, wi) in calendarWeeks" :key="wi" class="cal-week">
          <div
            v-for="day in week" :key="day.date"
            class="cal-day"
            :class="{ 'cal-day-today': day.isToday, 'cal-day-other': !day.inMonth }"
          >
            <div class="cal-day-num">{{ day.dayNum }}</div>
            <div class="cal-events">
              <div
                v-for="ev in day.events" :key="ev.id"
                class="cal-event"
                :style="{ background: ev.color + '22', borderLeft: `3px solid ${ev.color}` }"
                :title="ev.title"
              >
                <i v-if="ev.type === 'milestone'" class="pi pi-flag" style="font-size:9px" />
                {{ ev.title }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

const props = defineProps({ project: Object })

const loading = ref(true)
const events = ref([])
const cursor = ref(new Date())

const monthLabel = computed(() =>
  new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric' }).format(cursor.value)
)

function today() { cursor.value = new Date() }
function prevMonth() {
  const d = new Date(cursor.value); d.setMonth(d.getMonth() - 1); cursor.value = d
}
function nextMonth() {
  const d = new Date(cursor.value); d.setMonth(d.getMonth() + 1); cursor.value = d
}

const calendarWeeks = computed(() => {
  const year  = cursor.value.getFullYear()
  const month = cursor.value.getMonth()
  const first = new Date(year, month, 1)
  const last  = new Date(year, month + 1, 0)

  // Start from Monday
  const start = new Date(first)
  const dow = (start.getDay() + 6) % 7
  start.setDate(start.getDate() - dow)

  const weeks = []
  let current = new Date(start)
  const todayStr = new Date().toISOString().split('T')[0]

  while (current <= last || weeks.length < 6) {
    if (weeks.length >= 6) break
    const week = []
    for (let i = 0; i < 7; i++) {
      const dateStr = current.toISOString().split('T')[0]
      week.push({
        date:    dateStr,
        dayNum:  current.getDate(),
        inMonth: current.getMonth() === month,
        isToday: dateStr === todayStr,
        events:  eventsForDay(dateStr),
      })
      current.setDate(current.getDate() + 1)
    }
    weeks.push(week)
  }
  return weeks
})

function eventsForDay(dateStr) {
  return events.value.filter(ev => {
    if (ev.start <= dateStr && ev.end >= dateStr) return true
    return ev.start === dateStr || ev.end === dateStr
  })
}

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get(`/api/v1/projects/${props.project.id}/views/calendar`)
    events.value = data.events
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.cal-grid-wrap { overflow: hidden; }
.cal-header { display: grid; grid-template-columns: repeat(7, 1fr); border-bottom: 1px solid var(--border); }
.cal-dow { padding: 8px; text-align: center; font-size: 12px; font-weight: 600; color: var(--fg-3); }
.cal-body { display: flex; flex-direction: column; }
.cal-week { display: grid; grid-template-columns: repeat(7, 1fr); border-bottom: 1px solid var(--border); }
.cal-week:last-child { border-bottom: none; }
.cal-day { min-height: 100px; padding: 4px; border-right: 1px solid var(--border); }
.cal-day:last-child { border-right: none; }
.cal-day-other { background: var(--bg-2); }
.cal-day-today .cal-day-num { background: var(--halo-blue); color: #fff; border-radius: 50%; }
.cal-day-num { width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 500; color: var(--fg-2); margin-bottom: 4px; }
.cal-day-other .cal-day-num { color: var(--fg-4); }
.cal-events { display: flex; flex-direction: column; gap: 2px; }
.cal-event { font-size: 11px; padding: 1px 5px; border-radius: 3px; color: var(--fg-1); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: pointer; }
.cal-event:hover { opacity: .8; }
</style>
