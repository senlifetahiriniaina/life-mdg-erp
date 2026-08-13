<template>
  <div class="wh-panel">
    <div class="panel-head">
      <h3>Time Tracker</h3>
      <span class="today-label">Today: <strong>{{ todayHours }}h logged</strong></span>
    </div>

    <!-- Timer Section -->
    <div class="timer-section">
      <div class="timer-display">{{ timerDisplay }}</div>
      <div class="timer-controls">
        <select v-model="activeTaskId" class="task-select">
          <option :value="null">— No task —</option>
          <option v-for="task in tasks" :key="task.id" :value="task.id">{{ task.title }}</option>
        </select>
        <button v-if="!activeLog" class="btn btn-success" @click="startTimer">
          <i class="pi pi-play" /> Start
        </button>
        <button v-else class="btn btn-danger" @click="stopTimer">
          <i class="pi pi-stop" /> Stop
        </button>
      </div>
    </div>

    <!-- Manual Log Form -->
    <div class="manual-section">
      <div class="manual-title">Log Time Manually</div>
      <div class="manual-form">
        <div class="field">
          <label class="field-label">Task</label>
          <select v-model.number="manual.task_id" class="field-input">
            <option :value="null">— No task —</option>
            <option v-for="task in tasks" :key="task.id" :value="task.id">{{ task.title }}</option>
          </select>
        </div>
        <div class="field-row">
          <div class="field">
            <label class="field-label">Start</label>
            <input v-model="manual.started_at" type="datetime-local" class="field-input" />
          </div>
          <div class="field">
            <label class="field-label">End</label>
            <input v-model="manual.ended_at" type="datetime-local" class="field-input" />
          </div>
        </div>
        <div class="field">
          <label class="field-label">Description</label>
          <input v-model="manual.description" type="text" class="field-input" placeholder="What did you work on?" />
        </div>
        <div class="field-row align-center">
          <label class="checkbox-label">
            <input v-model="manual.billable" type="checkbox" class="checkbox" />
            Billable
          </label>
          <div class="field" style="flex:1">
            <label class="field-label">Hourly Rate</label>
            <input v-model.number="manual.hourly_rate" type="number" class="field-input" placeholder="0.00" />
          </div>
        </div>
        <button class="btn btn-primary" :disabled="!manual.started_at" @click="logManual">
          Log Time
        </button>
      </div>
    </div>

    <!-- Today's Logs -->
    <div v-if="todayLogs.length" class="logs-section">
      <div class="logs-title">Today's Logs</div>
      <div v-for="log in todayLogs" :key="log.id" class="log-item">
        <div class="log-time">{{ formatTime(log.started_at) }} – {{ log.ended_at ? formatTime(log.ended_at) : 'running' }}</div>
        <div class="log-desc">{{ log.description || log.task?.title || 'No description' }}</div>
        <div class="log-duration">{{ formatDuration(log.duration_minutes) }}</div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

interface Task { id: number; title: string }
interface Log {
  id: number
  started_at: string
  ended_at: string | null
  duration_minutes: number | null
  description: string | null
  billable: boolean
  task?: { id: number; title: string }
}

const props = defineProps<{
  projectId: number
  tasks?: Task[]
}>()

const tasks = ref<Task[]>(props.tasks ?? [])
const activeLog = ref<Log | null>(null)
const activeTaskId = ref<number | null>(null)
const todayLogs = ref<Log[]>([])
const elapsed = ref(0)
let timerInterval: ReturnType<typeof setInterval> | null = null

const manual = reactive({
  task_id: null as number | null,
  started_at: '',
  ended_at: '',
  description: '',
  billable: false,
  hourly_rate: null as number | null,
})

const timerDisplay = computed(() => {
  const s = elapsed.value
  const h = Math.floor(s / 3600).toString().padStart(2, '0')
  const m = Math.floor((s % 3600) / 60).toString().padStart(2, '0')
  const sec = (s % 60).toString().padStart(2, '0')
  return `${h}:${m}:${sec}`
})

const todayHours = computed(() => {
  const mins = todayLogs.value
    .filter((l) => l.ended_at)
    .reduce((acc, l) => acc + (l.duration_minutes ?? 0), 0)
  return (mins / 60).toFixed(1)
})

function formatTime(iso: string) {
  return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

function formatDuration(mins: number | null) {
  if (!mins) return '0m'
  const h = Math.floor(mins / 60)
  const m = mins % 60
  return h > 0 ? `${h}h ${m}m` : `${m}m`
}

async function fetchTodayLogs() {
  const today = new Date().toISOString().split('T')[0]
  const { data } = await axios.get(`/api/v1/projects/${props.projectId}/time-logs`)
  todayLogs.value = (data.data ?? []).filter((l: Log) =>
    l.started_at?.startsWith(today)
  )
}

function startElapsed() {
  if (!activeLog.value) return
  const start = new Date(activeLog.value.started_at).getTime()
  timerInterval = setInterval(() => {
    elapsed.value = Math.floor((Date.now() - start) / 1000)
  }, 1000)
}

async function startTimer() {
  const { data } = await axios.post(`/api/v1/projects/${props.projectId}/time-logs`, {
    started_at: new Date().toISOString(),
    task_id: activeTaskId.value,
  })
  activeLog.value = data
  elapsed.value = 0
  startElapsed()
}

async function stopTimer() {
  if (!activeLog.value) return
  await axios.post(`/api/v1/projects/time-logs/${activeLog.value.id}/stop`)
  activeLog.value = null
  elapsed.value = 0
  if (timerInterval) clearInterval(timerInterval)
  await fetchTodayLogs()
}

async function logManual() {
  await axios.post(`/api/v1/projects/${props.projectId}/time-logs`, {
    task_id: manual.task_id,
    started_at: manual.started_at,
    ended_at: manual.ended_at || undefined,
    description: manual.description || undefined,
    billable: manual.billable,
    hourly_rate: manual.hourly_rate || undefined,
  })
  manual.started_at = ''
  manual.ended_at = ''
  manual.description = ''
  manual.billable = false
  manual.hourly_rate = null
  await fetchTodayLogs()
}

onMounted(() => fetchTodayLogs())
onUnmounted(() => { if (timerInterval) clearInterval(timerInterval) })
</script>

<style scoped>
.panel-head { display:flex; align-items:center; justify-content:space-between; padding:14px 18px; border-bottom:1px solid var(--border-subtle); }
.panel-head h3 { margin:0; font-size:14px; font-weight:600; color:var(--fg-1); }
.today-label { font-size:12px; color:var(--fg-3); }
.today-label strong { color:var(--fg-1); }

.timer-section { padding:24px 18px; border-bottom:1px solid var(--border-subtle); display:flex; flex-direction:column; align-items:center; gap:16px; }
.timer-display { font-size:42px; font-weight:700; font-variant-numeric:tabular-nums; color:var(--fg-1); letter-spacing:0.02em; font-family:var(--font-mono,'monospace'); }
.timer-controls { display:flex; gap:10px; align-items:center; }
.task-select { padding:8px 12px; border:1px solid var(--border-subtle); border-radius:var(--r-md); font-size:13px; background:var(--bg-canvas); color:var(--fg-1); min-width:200px; }

.manual-section { padding:18px; border-bottom:1px solid var(--border-subtle); }
.manual-title { font-size:12px; font-weight:600; color:var(--fg-2); text-transform:uppercase; letter-spacing:0.06em; margin-bottom:14px; }
.manual-form { display:flex; flex-direction:column; gap:12px; }
.field-row { display:flex; gap:12px; }
.field-row.align-center { align-items:center; }
.field { display:flex; flex-direction:column; flex:1; }
.field-label { font-size:11px; font-weight:500; color:var(--fg-2); margin-bottom:4px; }
.field-input { padding:7px 10px; border:1px solid var(--border-subtle); border-radius:var(--r-md); font-size:13px; background:var(--bg-canvas); color:var(--fg-1); }
.checkbox-label { display:flex; align-items:center; gap:6px; font-size:13px; color:var(--fg-1); cursor:pointer; white-space:nowrap; }
.checkbox { width:15px; height:15px; }

.logs-section { padding:14px 18px; }
.logs-title { font-size:12px; font-weight:600; color:var(--fg-2); text-transform:uppercase; letter-spacing:0.06em; margin-bottom:10px; }
.log-item { display:flex; align-items:center; gap:12px; padding:8px 0; border-bottom:1px solid var(--border-subtle); font-size:13px; }
.log-item:last-child { border-bottom:0; }
.log-time { color:var(--fg-3); font-size:11px; min-width:130px; }
.log-desc { flex:1; color:var(--fg-1); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.log-duration { font-weight:600; color:var(--fg-1); font-size:12px; min-width:50px; text-align:right; }

.btn { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:var(--r-md); font-size:13px; font-weight:500; cursor:pointer; border:none; transition:background var(--dur-fast); }
.btn:disabled { opacity:0.5; cursor:not-allowed; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover:not(:disabled) { background:var(--halo-600); }
.btn-success { background:var(--green-500); color:#fff; }
.btn-success:hover { background:var(--green-600, #047857); }
.btn-danger { background:var(--red-500); color:#fff; }
.btn-danger:hover { background:var(--red-700, var(--danger-fg)); }
</style>
