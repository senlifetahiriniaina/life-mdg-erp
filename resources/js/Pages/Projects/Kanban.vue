<template>
  <AppLayout>
    <Head :title="`${project.name} — Kanban`" />

    <div class="page-head">
      <div>
        <div style="display:flex;align-items:center;gap:10px">
          <div :style="{ width:'12px', height:'12px', borderRadius:'50%', background: project.color }" />
          <h1 class="wh-page-title">{{ project.name }} · Kanban</h1>
        </div>
        <p class="wh-page-subtitle">{{ totalTasks }} tâche{{ totalTasks !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions">
        <a :href="`/projects/${project.id}`" class="btn btn-secondary"><i class="pi pi-list" style="font-size:13px" /> Liste</a>
        <a :href="`/projects/${project.id}/calendar`" class="btn btn-secondary"><i class="pi pi-calendar" style="font-size:13px" /> Calendrier</a>
        <a :href="`/projects/${project.id}/gantt`" class="btn btn-secondary"><i class="pi pi-chart-bar" style="font-size:13px" /> Gantt</a>
      </div>
    </div>

    <div v-if="loading" class="wh-panel" style="padding:40px;text-align:center;color:var(--fg-3)">
      <i class="pi pi-spin pi-spinner" style="font-size:20px" />
    </div>

    <div v-else class="kanban-board">
      <div
        v-for="col in columns" :key="col.status"
        class="kanban-col"
        @dragover.prevent
        @drop="onDrop($event, col.status)"
      >
        <div class="kanban-col-header">
          <span class="kanban-status-dot" :style="{ background: statusColor(col.status) }" />
          <span class="kanban-col-title">{{ col.label }}</span>
          <span class="kanban-col-count">{{ col.count }}</span>
        </div>

        <div class="kanban-col-body">
          <div
            v-for="task in col.tasks" :key="task.id"
            class="kanban-card"
            draggable="true"
            @dragstart="onDragStart($event, task, col.status)"
          >
            <div class="kanban-card-title">{{ task.title }}</div>

            <div v-if="task.tags?.length" style="display:flex;flex-wrap:wrap;gap:4px;margin-top:6px">
              <span v-for="tag in task.tags" :key="tag" class="kanban-tag">{{ tag }}</span>
            </div>

            <div class="kanban-card-meta">
              <span v-if="task.due_date" :class="['kanban-date', isOverdue(task) ? 'overdue' : '']">
                <i class="pi pi-calendar" style="font-size:10px" />
                {{ formatDate(task.due_date) }}
              </span>
              <span class="spacer" />
              <span :class="['kanban-priority', `priority-${task.priority}`]">{{ task.priority }}</span>
              <div v-if="task.assignee" class="kanban-avatar" :title="task.assignee.name">
                {{ initials(task.assignee.name) }}
              </div>
            </div>

            <div v-if="task.estimated_hours > 0" class="kanban-progress">
              <div class="kanban-progress-bar" :style="{ width: progressPct(task) + '%' }" />
            </div>
          </div>

          <div v-if="col.tasks.length === 0" class="kanban-empty">
            Aucune tâche
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

const props = defineProps({ project: Object })

const loading = ref(true)
const columns = ref([])

const totalTasks = computed(() => columns.value.reduce((sum, c) => sum + c.count, 0))

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get(`/api/v1/projects/${props.project.id}/views/kanban`)
    columns.value = data.columns
  } finally {
    loading.value = false
  }
}

let dragTask = null
let dragFrom = null

function onDragStart(e, task, status) {
  dragTask = task
  dragFrom = status
  e.dataTransfer.effectAllowed = 'move'
}

async function onDrop(e, targetStatus) {
  if (!dragTask || dragFrom === targetStatus) return
  try {
    await axios.put(`/api/v1/tasks/${dragTask.id}`, { status: targetStatus })
    await load()
  } catch (err) {
    console.error(err)
  }
}

function statusColor(s) {
  const map = { todo: '#94a3b8', in_progress: '#3b82f6', review: '#f59e0b', done: '#22c55e', cancelled: '#ef4444' }
  return map[s] || '#94a3b8'
}

function isOverdue(task) {
  return task.due_date && task.status !== 'done' && new Date(task.due_date) < new Date()
}

function formatDate(d) {
  return new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: 'short' }).format(new Date(d))
}

function initials(name) {
  return name?.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase() || '?'
}

function progressPct(task) {
  if (!task.estimated_hours) return 0
  return Math.min(100, Math.round(task.logged_hours / task.estimated_hours * 100))
}

let echoChannel = null

onMounted(() => {
  load()
  if (window.Echo && props.project?.id) {
    echoChannel = window.Echo.private(`project.${props.project.id}`)
      .listen('.TaskUpdated', () => {
        load()
      })
  }
})

onUnmounted(() => {
  if (echoChannel && props.project?.id) {
    window.Echo?.leaveChannel(`private-project.${props.project.id}`)
  }
})
</script>

<style scoped>
.kanban-board {
  display: flex;
  gap: 12px;
  overflow-x: auto;
  align-items: flex-start;
  padding-bottom: 16px;
}
.kanban-col {
  flex: 0 0 280px;
  background: var(--bg-2);
  border-radius: 8px;
  border: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  max-height: calc(100vh - 220px);
}
.kanban-col-header {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  border-bottom: 1px solid var(--border);
  position: sticky;
  top: 0;
  background: var(--bg-2);
  border-radius: 8px 8px 0 0;
}
.kanban-status-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.kanban-col-title { font-weight: 600; font-size: 13px; flex: 1; color: var(--fg-1); }
.kanban-col-count { font-size: 12px; color: var(--fg-3); background: var(--bg-3); padding: 1px 7px; border-radius: 10px; }
.kanban-col-body { padding: 8px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px; flex: 1; }
.kanban-card {
  background: var(--bg-1);
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 10px 12px;
  cursor: grab;
  transition: box-shadow .15s;
}
.kanban-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.08); }
.kanban-card-title { font-size: 13px; font-weight: 500; color: var(--fg-1); line-height: 1.4; }
.kanban-tag { font-size: 10px; background: var(--halo-blue-10); color: var(--halo-blue); padding: 1px 6px; border-radius: 4px; }
.kanban-card-meta { display: flex; align-items: center; gap: 6px; margin-top: 8px; }
.kanban-date { font-size: 11px; color: var(--fg-3); display: flex; align-items: center; gap: 3px; }
.kanban-date.overdue { color: var(--danger); }
.spacer { flex: 1; }
.kanban-priority { font-size: 10px; padding: 1px 5px; border-radius: 4px; text-transform: capitalize; }
.priority-urgent { background: var(--red-50);    color: var(--red-600); }
.priority-high   { background: var(--yellow-50); color: var(--yellow-600); }
.priority-medium { background: var(--halo-50);   color: var(--halo-700); }
.priority-low    { background: var(--slate-100); color: var(--slate-600); }
.kanban-avatar { width: 22px; height: 22px; border-radius: 50%; background: var(--halo-blue); color: #fff; font-size: 9px; font-weight: 700; display: flex; align-items: center; justify-content: center; }
.kanban-progress { height: 3px; background: var(--bg-3); border-radius: 2px; margin-top: 8px; overflow: hidden; }
.kanban-progress-bar { height: 100%; background: var(--halo-blue); border-radius: 2px; transition: width .3s; }
.kanban-empty { text-align: center; color: var(--fg-4); font-size: 12px; padding: 20px 0; }
</style>
