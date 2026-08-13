<template>
  <AppLayout :title="`${project.name} — Gantt`">
    <!-- Toolbar -->
    <div class="flex items-center justify-between px-6 py-3 border-b border-gray-200 dark:border-surface-700 bg-white dark:bg-surface-800 dark:bg-surface-800">
      <div class="flex items-center gap-3">
        <div :style="{ width: '12px', height: '12px', borderRadius: '50%', background: project.color }" />
        <h1 class="text-lg font-semibold text-gray-800 dark:text-surface-100">{{ project.name }} · Gantt</h1>
        <span class="text-xs text-surface-400 dark:text-surface-500">{{ tasks.length }} tâche{{ tasks.length !== 1 ? 's' : '' }}</span>
      </div>
      <div class="flex items-center gap-2">
        <!-- Zoom buttons -->
        <div class="flex gap-1 border border-gray-200 dark:border-surface-700 rounded-lg overflow-hidden">
          <button
            v-for="z in zoomLevels"
            :key="z.key"
            class="px-3 py-1.5 text-xs font-medium transition-colors"
            :class="zoom === z.key ? 'bg-blue-600 text-white' : 'bg-white dark:bg-surface-800 text-surface-600 dark:text-surface-400 hover:bg-gray-50 dark:bg-surface-800 dark:bg-surface-800'"
            @click="zoom = z.key"
          >{{ z.label }}</button>
        </div>
        <!-- Today button -->
        <button
          class="px-3 py-1.5 text-xs font-medium border border-gray-200 dark:border-surface-700 rounded-lg bg-white dark:bg-surface-800 text-surface-600 dark:text-surface-400 hover:bg-gray-50 dark:bg-surface-800 dark:bg-surface-800"
          @click="scrollToToday"
        >Aujourd'hui</button>
        <!-- Export button -->
        <button
          class="px-3 py-1.5 text-xs font-medium border border-gray-200 dark:border-surface-700 rounded-lg bg-white dark:bg-surface-800 text-surface-600 dark:text-surface-400 hover:bg-gray-50 dark:bg-surface-800 dark:bg-surface-800"
          @click="exportCsv"
        ><i class="pi pi-download mr-1" />Exporter</button>
        <!-- Nav links -->
        <a :href="`/projects/${project.id}`" class="px-3 py-1.5 text-xs border border-gray-200 dark:border-surface-700 rounded-lg bg-white dark:bg-surface-800 text-surface-600 dark:text-surface-400 hover:bg-gray-50 dark:bg-surface-800 dark:bg-surface-800"><i class="pi pi-list mr-1" />Liste</a>
        <a :href="`/projects/${project.id}/kanban`" class="px-3 py-1.5 text-xs border border-gray-200 dark:border-surface-700 rounded-lg bg-white dark:bg-surface-800 text-surface-600 dark:text-surface-400 hover:bg-gray-50 dark:bg-surface-800 dark:bg-surface-800"><i class="pi pi-th-large mr-1" />Kanban</a>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-24">
      <i class="pi pi-spin pi-spinner text-3xl text-surface-400 dark:text-surface-500" />
    </div>

    <!-- Gantt Layout -->
    <div v-else class="flex h-[calc(100vh-120px)] overflow-hidden">

      <!-- Left: Task list -->
      <div class="w-72 flex-shrink-0 border-r border-gray-200 dark:border-surface-700 flex flex-col">
        <!-- Left header -->
        <div class="flex border-b border-gray-200 dark:border-surface-700 bg-gray-50 dark:bg-surface-800 dark:bg-surface-800" style="height:48px">
          <div class="flex-1 px-3 flex items-center text-xs font-semibold text-surface-500 dark:text-surface-400 uppercase tracking-wide">Tâche</div>
          <div class="w-20 px-2 flex items-center text-xs font-semibold text-surface-500 dark:text-surface-400 uppercase tracking-wide">Début</div>
          <div class="w-14 px-2 flex items-center text-xs font-semibold text-surface-500 dark:text-surface-400 uppercase tracking-wide">Durée</div>
        </div>
        <!-- Task rows (left) -->
        <div class="flex-1 overflow-y-auto" ref="leftScrollRef" @scroll="syncScroll($event, 'left')">
          <div
            v-for="task in tasks"
            :key="task.id"
            class="flex items-center border-b border-gray-100 cursor-pointer hover:bg-gray-50 dark:bg-surface-800 dark:bg-surface-800 transition-colors"
            style="height:36px"
            @click="openTask(task)"
          >
            <div
              class="flex-1 px-3 flex items-center gap-2 text-sm text-surface-700 dark:text-surface-300 overflow-hidden"
              :style="{ paddingLeft: (12 + task.level * 16) + 'px' }"
            >
              <span
                class="w-2 h-2 rounded-full flex-shrink-0"
                :class="priorityColor(task.priority)"
              />
              <span class="truncate">{{ task.title }}</span>
              <span v-if="task.is_critical" class="ml-1 w-1.5 h-1.5 rounded-full bg-red-50 dark:bg-red-900/200 flex-shrink-0" title="Chemin critique" />
            </div>
            <div class="w-20 px-2 text-xs text-surface-400 dark:text-surface-500 tabular-nums">
              {{ task.start_date ? formatShortDate(task.start_date) : '—' }}
            </div>
            <div class="w-14 px-2 text-xs text-surface-400 dark:text-surface-500 tabular-nums text-right">
              {{ taskDuration(task) }}j
            </div>
          </div>

          <!-- Milestone rows -->
          <div
            v-for="m in milestones"
            :key="`ms-${m.id}`"
            class="flex items-center border-b border-gray-100"
            style="height:36px"
          >
            <div class="flex-1 px-3 flex items-center gap-2 text-xs text-amber-600 italic overflow-hidden">
              <i class="pi pi-flag text-amber-500" style="font-size:10px" />
              <span class="truncate">{{ m.title ?? m.name }}</span>
            </div>
            <div class="w-20 px-2 text-xs text-surface-400 dark:text-surface-500">{{ m.end ?? m.due_date }}</div>
            <div class="w-14" />
          </div>
        </div>
      </div>

      <!-- Right: Timeline -->
      <div class="flex-1 overflow-x-auto overflow-y-hidden flex flex-col" ref="timelineRef" @scroll="syncScroll($event, 'timeline')">
        <div :style="{ width: totalWidth + 'px' }" class="flex flex-col flex-1">

          <!-- Header: months + days -->
          <div class="flex flex-col border-b border-gray-200 dark:border-surface-700 bg-gray-50 dark:bg-surface-800 dark:bg-surface-800 flex-shrink-0" style="height:48px">
            <!-- Month row -->
            <div class="flex" style="height:20px">
              <div
                v-for="month in monthHeaders"
                :key="month.label"
                class="border-r border-gray-200 dark:border-surface-700 flex items-center px-2 text-xs font-semibold text-surface-500 dark:text-surface-400"
                :style="{ width: month.width + 'px', flex: 'none' }"
              >{{ month.label }}</div>
            </div>
            <!-- Day row -->
            <div class="flex" style="height:28px">
              <div
                v-for="day in timelineDays"
                :key="day.dateStr"
                class="border-r border-gray-200 dark:border-surface-700 flex items-center justify-center flex-shrink-0"
                :class="{
                  'bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300 font-bold': day.isToday,
                  'bg-gray-100 dark:bg-surface-700 text-surface-400 dark:text-surface-500': day.isWeekend && !day.isToday,
                  'text-surface-400 dark:text-surface-500': !day.isToday && !day.isWeekend,
                }"
                :style="{ width: dayWidth + 'px', fontSize: '10px' }"
              >{{ day.label }}</div>
            </div>
          </div>

          <!-- Rows with bars -->
          <div class="flex-1 overflow-y-auto relative" ref="rightScrollRef" @scroll="syncScroll($event, 'right')">
            <!-- Background grid -->
            <div class="absolute inset-0 pointer-events-none flex">
              <div
                v-for="day in timelineDays"
                :key="`bg-${day.dateStr}`"
                class="border-r border-gray-100 flex-shrink-0 h-full"
                :class="{ 'bg-gray-50 dark:bg-surface-800': day.isWeekend, 'bg-primary-50 dark:bg-primary-900/20/40': day.isToday }"
                :style="{ width: dayWidth + 'px' }"
              />
            </div>

            <!-- Today vertical line -->
            <div
              v-if="todayOffset >= 0"
              class="absolute top-0 bottom-0 w-px bg-blue-400 z-10 pointer-events-none"
              :style="{ left: todayOffset + 'px' }"
            />

            <!-- Task bar rows -->
            <div
              v-for="task in tasks"
              :key="`row-${task.id}`"
              class="relative border-b border-gray-100"
              style="height:36px"
            >
              <!-- Task bar -->
              <div
                v-if="task.start_date && task.due_date"
                class="absolute top-2 bottom-2 rounded flex items-center overflow-hidden cursor-pointer hover:opacity-90 transition-opacity"
                :class="task.is_critical ? 'bg-red-50 dark:bg-red-900/200' : 'bg-primary-50 dark:bg-primary-900/200'"
                :style="barStyle(task)"
                :title="task.title"
                @click="openTask(task)"
              >
                <div
                  class="h-full opacity-30"
                  :class="task.is_critical ? 'bg-red-800' : 'bg-blue-800'"
                  :style="{ width: task.progress + '%' }"
                />
                <span class="absolute left-2 text-white text-xs font-medium truncate max-w-full pr-2">
                  {{ task.title }}
                </span>
              </div>
            </div>

            <!-- Milestone rows -->
            <div
              v-for="m in milestones"
              :key="`ms-row-${m.id}`"
              class="relative border-b border-gray-100"
              style="height:36px"
            >
              <div
                v-if="m.end ?? m.due_date"
                class="absolute w-4 h-4 bg-amber-400 rotate-45 z-10 cursor-pointer"
                :style="milestoneStyle(m)"
                :title="m.title ?? m.name"
              />
            </div>

            <!-- Dependency SVG arrows -->
            <svg
              class="absolute inset-0 pointer-events-none z-20"
              :width="totalWidth"
              :height="svgHeight"
              overflow="visible"
            >
              <defs>
                <marker id="arrowhead" markerWidth="6" markerHeight="6" refX="3" refY="3" orient="auto">
                  <polygon points="0 0, 6 3, 0 6" fill="#94a3b8" />
                </marker>
              </defs>
              <polyline
                v-for="arrow in dependencyArrows"
                :key="arrow.key"
                :points="arrow.points"
                fill="none"
                stroke="#94a3b8"
                stroke-width="1.5"
                stroke-dasharray="4 2"
                marker-end="url(#arrowhead)"
              />
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- Task Detail Dialog -->
    <Dialog v-model:visible="showTaskDialog" :header="selectedTask?.title" modal :style="{ width: '520px' }">
      <div v-if="selectedTask" class="flex flex-col gap-4 pt-2">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="text-xs font-medium text-surface-500 dark:text-surface-400 uppercase tracking-wide">Statut</label>
            <p class="mt-1 text-sm font-medium text-gray-800 dark:text-surface-100">{{ selectedTask.status }}</p>
          </div>
          <div>
            <label class="text-xs font-medium text-surface-500 dark:text-surface-400 uppercase tracking-wide">Priorité</label>
            <p class="mt-1 text-sm font-medium text-gray-800 dark:text-surface-100">{{ selectedTask.priority }}</p>
          </div>
          <div>
            <label class="text-xs font-medium text-surface-500 dark:text-surface-400 uppercase tracking-wide">Début</label>
            <p class="mt-1 text-sm text-surface-700 dark:text-surface-300">{{ selectedTask.start_date ?? '—' }}</p>
          </div>
          <div>
            <label class="text-xs font-medium text-surface-500 dark:text-surface-400 uppercase tracking-wide">Fin</label>
            <p class="mt-1 text-sm text-surface-700 dark:text-surface-300">{{ selectedTask.due_date ?? '—' }}</p>
          </div>
          <div>
            <label class="text-xs font-medium text-surface-500 dark:text-surface-400 uppercase tracking-wide">Assigné</label>
            <p class="mt-1 text-sm text-surface-700 dark:text-surface-300">{{ selectedTask.assignee ?? '—' }}</p>
          </div>
          <div>
            <label class="text-xs font-medium text-surface-500 dark:text-surface-400 uppercase tracking-wide">Chemin critique</label>
            <p class="mt-1">
              <span v-if="selectedTask.is_critical" class="inline-flex items-center gap-1 text-xs font-medium text-red-700 dark:text-red-300 bg-red-50 dark:bg-red-900/20 px-2 py-0.5 rounded-full">
                <span class="w-1.5 h-1.5 rounded-full bg-red-50 dark:bg-red-900/200" /> Critique
              </span>
              <span v-else class="text-sm text-surface-400 dark:text-surface-500">Non</span>
            </p>
          </div>
        </div>

        <div class="border-t pt-4">
          <label class="text-xs font-medium text-surface-500 dark:text-surface-400 uppercase tracking-wide">Ajouter une dépendance</label>
          <div class="flex gap-2 mt-2">
            <Dropdown
              v-model="newDepTaskId"
              :options="otherTasks"
              option-label="title"
              option-value="id"
              placeholder="Sélectionner la tâche prédécesseur"
              class="flex-1 text-sm"
              :filter="true"
            />
            <Button
              label="Ajouter"
              icon="pi pi-link"
              size="small"
              @click="addDependency"
              :loading="addingDep"
              :disabled="!newDepTaskId"
            />
          </div>
        </div>
      </div>
    </Dialog>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, Dialog, Dropdown } from 'primevue'
import axios from 'axios'

const props = defineProps({
  project: { type: Object, required: true },
})

// State
const loading    = ref(true)
const tasks      = ref([])
const milestones = ref([])
const deps       = ref([])
const criticalPath = ref([])

const zoom = ref('month')
const zoomLevels = [
  { key: 'day',   label: 'Jour',    px: 30 },
  { key: 'week',  label: 'Semaine', px: 15 },
  { key: 'month', label: 'Mois',    px: 6  },
]

const showTaskDialog = ref(false)
const selectedTask   = ref(null)
const newDepTaskId   = ref(null)
const addingDep      = ref(false)

// Scroll refs
const leftScrollRef  = ref(null)
const rightScrollRef = ref(null)
const timelineRef    = ref(null)
let syncing = false

// Computed: day width
const dayWidth = computed(() => {
  const z = zoomLevels.find(l => l.key === zoom.value)
  return z ? z.px : 6
})

// Compute timeline date range
const timelineDays = computed(() => {
  const all = tasks.value.filter(t => t.start_date && t.due_date)
  if (!all.length) {
    return buildDays(
      new Date(new Date().getFullYear(), new Date().getMonth(), 1),
      new Date(new Date().getFullYear(), new Date().getMonth() + 2, 0),
    )
  }
  const starts = all.map(t => new Date(t.start_date))
  const ends   = all.map(t => new Date(t.due_date))
  const min = new Date(Math.min(...starts))
  const max = new Date(Math.max(...ends))
  min.setDate(min.getDate() - 7)
  max.setDate(max.getDate() + 14)
  return buildDays(min, max)
})

function buildDays(from, to) {
  const days = []
  const todayStr = new Date().toISOString().split('T')[0]
  const cur = new Date(from)
  while (cur <= to) {
    const dateStr = cur.toISOString().split('T')[0]
    const dow = cur.getDay()
    days.push({
      label:     String(cur.getDate()),
      dateStr,
      isToday:   dateStr === todayStr,
      isWeekend: dow === 0 || dow === 6,
      month:     cur.getMonth(),
      year:      cur.getFullYear(),
      monthLabel: cur.toLocaleString('fr-FR', { month: 'short', year: 'numeric' }),
    })
    cur.setDate(cur.getDate() + 1)
  }
  return days
}

const totalWidth = computed(() => timelineDays.value.length * dayWidth.value)
const svgHeight  = computed(() => (tasks.value.length + milestones.value.length) * 36)

const monthHeaders = computed(() => {
  const headers = []
  let current = null
  let width = 0
  for (const day of timelineDays.value) {
    const key = `${day.year}-${day.month}`
    if (current !== key) {
      if (current !== null) headers.push({ label: headers.length === 0 ? '' : timelineDays.value.find(d => `${d.year}-${d.month}` === current)?.monthLabel ?? '', width })
      current = key
      width = dayWidth.value
    } else {
      width += dayWidth.value
    }
  }
  if (current !== null) {
    const d = timelineDays.value.find(d => `${d.year}-${d.month}` === current)
    headers.push({ label: d?.monthLabel ?? '', width })
  }
  return headers
})

// Today offset
const todayOffset = computed(() => {
  const todayStr = new Date().toISOString().split('T')[0]
  const idx = timelineDays.value.findIndex(d => d.dateStr === todayStr)
  return idx >= 0 ? idx * dayWidth.value + dayWidth.value / 2 : -1
})

function dayIndex(dateStr) {
  return timelineDays.value.findIndex(d => d.dateStr === dateStr)
}

function barStyle(task) {
  const si = dayIndex(task.start_date)
  const ei = dayIndex(task.due_date)
  if (si < 0 && ei < 0) return { display: 'none' }
  const startI = si >= 0 ? si : 0
  const endI   = ei >= 0 ? ei : timelineDays.value.length - 1
  return {
    left:  `${startI * dayWidth.value}px`,
    width: `${Math.max((endI - startI + 1) * dayWidth.value, dayWidth.value)}px`,
  }
}

function milestoneStyle(m) {
  const dateStr = m.end ?? m.due_date
  const idx = dayIndex(dateStr)
  if (idx < 0) return { display: 'none' }
  return {
    left:   `${idx * dayWidth.value - 8}px`,
    top:    '10px',
  }
}

// Dependency arrows
const dependencyArrows = computed(() => {
  const arrows = []
  const taskIndexMap = {}
  tasks.value.forEach((t, i) => { taskIndexMap[t.id] = i })

  for (const dep of deps.value) {
    const fromIdx = taskIndexMap[dep.depends_on_task_id]
    const toIdx   = taskIndexMap[dep.task_id]
    if (fromIdx === undefined || toIdx === undefined) continue

    const fromTask = tasks.value[fromIdx]
    const toTask   = tasks.value[toIdx]
    if (!fromTask?.due_date || !toTask?.start_date) continue

    const x1 = (dayIndex(fromTask.due_date) + 1) * dayWidth.value
    const y1 = fromIdx * 36 + 18
    const x2 = dayIndex(toTask.start_date) * dayWidth.value
    const y2 = toIdx * 36 + 18

    arrows.push({
      key:    `${dep.task_id}-${dep.depends_on_task_id}`,
      points: `${x1},${y1} ${x1 + 8},${y1} ${x1 + 8},${y2} ${x2},${y2}`,
    })
  }
  return arrows
})

// Helpers
function priorityColor(priority) {
  const map = { urgent: 'bg-red-50 dark:bg-red-900/200', high: 'bg-yellow-50 dark:bg-yellow-900/200', medium: 'bg-blue-400', low: 'bg-gray-400' }
  return map[priority] ?? 'bg-gray-400'
}

function formatShortDate(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' })
}

function taskDuration(task) {
  if (!task.start_date || !task.due_date) return '—'
  const s = new Date(task.start_date)
  const e = new Date(task.due_date)
  return Math.max(1, Math.round((e - s) / 86400000) + 1)
}

const otherTasks = computed(() => {
  if (!selectedTask.value) return tasks.value
  return tasks.value.filter(t => t.id !== selectedTask.value.id)
})

// Interactions
function openTask(task) {
  selectedTask.value = task
  newDepTaskId.value = null
  showTaskDialog.value = true
}

async function addDependency() {
  if (!selectedTask.value || !newDepTaskId.value) return
  addingDep.value = true
  try {
    await axios.post(`/api/v1/projects/tasks/${selectedTask.value.id}/dependencies`, {
      depends_on_task_id: newDepTaskId.value,
      type: 'FS',
      lag_days: 0,
    })
    newDepTaskId.value = null
    await loadGantt()
  } finally {
    addingDep.value = false
  }
}

function scrollToToday() {
  if (todayOffset.value >= 0 && timelineRef.value) {
    timelineRef.value.scrollLeft = Math.max(0, todayOffset.value - timelineRef.value.clientWidth / 2)
  }
}

function exportCsv() {
  const rows = [['ID', 'Titre', 'Début', 'Fin', 'Statut', 'Priorité', 'Assigné', 'Critique']]
  for (const t of tasks.value) {
    rows.push([t.id, t.title, t.start_date ?? '', t.due_date ?? '', t.status, t.priority, t.assignee ?? '', t.is_critical ? 'Oui' : 'Non'])
  }
  const csv = rows.map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv' })
  const url  = URL.createObjectURL(blob)
  const a    = document.createElement('a')
  a.href = url
  a.download = `${props.project.name}-gantt.csv`
  a.click()
  URL.revokeObjectURL(url)
}

// Synchronized vertical scrolling
function syncScroll(event, source) {
  if (syncing) return
  syncing = true
  const scrollTop = event.target.scrollTop
  if (source !== 'left' && leftScrollRef.value)   leftScrollRef.value.scrollTop  = scrollTop
  if (source !== 'right' && rightScrollRef.value) rightScrollRef.value.scrollTop = scrollTop
  nextTick(() => { syncing = false })
}

// Data loading
async function loadGantt() {
  loading.value = true
  try {
    const [ganttRes, viewRes] = await Promise.all([
      axios.get(`/api/v1/projects/${props.project.id}/gantt`),
      axios.get(`/api/v1/projects/${props.project.id}/views/gantt`),
    ])
    tasks.value      = ganttRes.data.tasks ?? []
    deps.value       = ganttRes.data.dependencies ?? []
    criticalPath.value = ganttRes.data.critical_path ?? []
    milestones.value = viewRes.data.milestones ?? []
  } catch (e) {
    console.error('Gantt load error:', e)
  } finally {
    loading.value = false
    await nextTick()
    scrollToToday()
  }
}

onMounted(loadGantt)
</script>
