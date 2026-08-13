<template>
  <AppLayout>
    <Head title="Projets" />

    <GuidedTour tour-id="projects-index" :steps="projectsTourSteps" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Projets</h1>
        <p class="wh-page-subtitle">{{ projects.total }} projet{{ projects.total !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-secondary"><i class="pi pi-download" style="font-size:13px" /> Exporter</button>
        <button class="btn btn-primary"><i class="pi pi-plus" style="font-size:13px" /> Nouveau projet</button>
      </div>
    </div>

    <!-- KPI row -->
    <div class="wh-kpi-grid" style="margin-bottom:16px">
      <div class="wh-kpi" v-for="s in stats" :key="s.label">
        <div class="wh-kpi-label">{{ s.label }}</div>
        <div class="wh-kpi-num font-display">{{ s.value }}</div>
      </div>
    </div>

    <!-- Filter pills -->
    <div style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap">
      <button
        v-for="f in filterOptions" :key="f.value"
        :class="['filter-pill', activeFilter === f.value ? 'filter-pill-on' : '']"
        @click="activeFilter = f.value"
      >{{ f.label }}</button>
    </div>

    <!-- Table -->
    <div class="wh-panel" style="position:relative">
      <table class="wh-dt">
        <thead>
          <tr>
            <th>Projet</th>
            <th>Statut</th>
            <th>Priorité</th>
            <th>Progression</th>
            <th>Échéance</th>
            <th class="num">Budget / Dépensé</th>
            <th style="width:60px"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in filteredProjects" :key="p.id" class="wh-dt-row">
            <td>
              <div style="font-weight:500;color:var(--fg-1)">{{ p.name }}</div>
              <div v-if="p.description" style="font-size:12px;color:var(--fg-3);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:280px">{{ p.description }}</div>
            </td>
            <td>
              <span :class="['wh-badge', projectStatusClass(p.status)]">
                <span class="wh-badge-dot" />{{ statusLabel(p.status) }}
              </span>
            </td>
            <td>
              <span :class="['wh-badge', priorityClass(p.priority)]">{{ priorityLabel(p.priority) }}</span>
            </td>
            <td style="width:180px">
              <div style="display:flex;align-items:center;gap:8px">
                <div class="progress-track">
                  <div class="progress-fill" :style="{ width: progress(p) + '%' }" />
                </div>
                <span style="font-size:12px;color:var(--fg-3);font-variant-numeric:tabular-nums;width:30px;text-align:right">{{ progress(p) }}%</span>
              </div>
            </td>
            <td :class="isOverdue(p) ? 'text-danger' : ''" style="font-variant-numeric:tabular-nums">{{ formatDate(p.end_date) }}</td>
            <td class="num" style="font-variant-numeric:tabular-nums;color:var(--fg-2)">
              {{ formatMoney(p.spent_budget) }} / {{ formatMoney(p.budget) }}
            </td>
            <td>
              <button class="wh-row-btn" title="Voir"><i class="pi pi-eye" style="font-size:13px" /></button>
            </td>
          </tr>
          <tr v-if="filteredProjects.length === 0">
            <td colspan="7" style="text-align:center;padding:48px;color:var(--fg-3);font-size:14px">Aucun projet trouvé.</td>
          </tr>
        </tbody>
      </table>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-top:1px solid var(--border-subtle)">
        <span style="font-size:13px;color:var(--fg-3)">{{ projects.total }} résultat{{ projects.total !== 1 ? 's' : '' }}</span>
        <Paginator :rows="projects.per_page" :total-records="projects.total" :first="(projects.current_page - 1) * projects.per_page" />
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import Paginator from 'primevue/paginator'
import AppLayout from '@/Layouts/AppLayout.vue'
import GuidedTour from '@/Components/UI/GuidedTour.vue'
import { useHelpStore } from '@/stores/help'

const help = useHelpStore()

const projectsTourSteps = [
  { tag: 'Projects', icon: 'pi pi-folder',      title: 'Project List',     description: 'Every project your team is working on appears here. Click a project to see its tasks, timeline, and budget consumption.' },
  { tag: 'Projects', icon: 'pi pi-th-large',    title: 'Kanban Board',     description: 'Switch to the Kanban view to drag tasks between stages (To Do → In Progress → Review → Done). Each column shows the WIP count.' },
  { tag: 'Projects', icon: 'pi pi-chart-bar',   title: 'Gantt Chart',      description: 'The Gantt view shows task dependencies and milestones on a timeline. Hover a bar to see the assigned team member and deadline.' },
  { tag: 'Projects', icon: 'pi pi-clock',       title: 'Time Tracking',    description: 'Team members log hours against tasks. The project header shows total logged vs estimated hours — over 100% signals scope creep.' },
  { tag: 'Projects', icon: 'pi pi-dollar',      title: 'Budget Control',   description: 'Each project has a budget. As timesheets and expenses are logged, the budget burn rate updates in real time with a RAG status indicator.' },
]

const props = defineProps({
  projects: { type: Object, required: true },
})

const activeFilter = ref('all')
const today = new Date()

const filterOptions = [
  { label: 'Tous',       value: 'all' },
  { label: 'Actifs',     value: 'active' },
  { label: 'Planifiés',  value: 'planning' },
  { label: 'En pause',   value: 'on_hold' },
  { label: 'Terminés',   value: 'completed' },
]

const stats = computed(() => [
  { label: 'Total',     value: props.projects.total },
  { label: 'Actifs',    value: props.projects.data.filter(p => p.status === 'active').length },
  { label: 'Terminés',  value: props.projects.data.filter(p => p.status === 'completed').length },
  { label: 'En retard', value: props.projects.data.filter(p => isOverdue(p)).length },
])

const filteredProjects = computed(() => {
  const data = props.projects.data ?? []
  if (activeFilter.value === 'all') return data
  return data.filter(p => p.status === activeFilter.value)
})

const isOverdue = (p) =>
  p.end_date && new Date(p.end_date) < today && p.status !== 'completed' && p.status !== 'cancelled'

const progress = (p) => {
  if (!p.budget || p.budget == 0) return 0
  return Math.min(100, Math.round((p.spent_budget / p.budget) * 100))
}

const projectStatusClass = (s) => ({
  planning:  'wh-badge-slate',
  active:    'wh-badge-blue',
  on_hold:   'wh-badge-amber',
  completed: 'wh-badge-green',
  cancelled: 'wh-badge-red',
}[s] ?? 'wh-badge-slate')

const statusLabel = (s) => ({
  planning: 'Planifié', active: 'Actif', on_hold: 'En pause', completed: 'Terminé', cancelled: 'Annulé',
}[s] ?? s)

const priorityClass = (p) => ({
  low:      'wh-badge-slate',
  medium:   'wh-badge-blue',
  high:     'wh-badge-amber',
  critical: 'wh-badge-red',
}[p] ?? 'wh-badge-slate')

const priorityLabel = (p) => ({
  low: 'Faible', medium: 'Moyenne', high: 'Haute', critical: 'Critique',
}[p] ?? p)

const formatDate  = (d) => d ? new Date(d).toLocaleDateString('fr-FR') : '—'
const formatMoney = (v) => v != null ? Number(v).toLocaleString('fr-FR', { minimumFractionDigits: 0 }) + ' €' : '—'
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; gap:16px; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; gap:8px; }
.btn { font-family:var(--font-sans); font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:background var(--dur-base) var(--ease-out); line-height:1.2; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover { background:var(--halo-700); }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover { background:var(--bg-sunken); }
.filter-pill { height:28px; padding:0 12px; border-radius:var(--r-pill); border:1px solid var(--border-subtle); background:var(--bg-canvas); font-size:12px; font-weight:500; color:var(--fg-2); cursor:pointer; transition:all var(--dur-base); }
.filter-pill:hover { background:var(--bg-sunken); color:var(--fg-1); }
.filter-pill-on { background:var(--halo-50); border-color:var(--halo-200); color:var(--halo-700); }
.wh-dt { width:100%; border-collapse:collapse; font-size:14px; }
.wh-dt thead th { text-align:left; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:var(--fg-3); font-weight:600; padding:10px 18px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sunken); }
.wh-dt thead th.num { text-align:right; }
.wh-dt-row td { padding:11px 18px; border-bottom:1px solid var(--border-subtle); }
.wh-dt-row td.num { text-align:right; }
.wh-dt-row:last-child td { border-bottom:0; }
.wh-dt-row { cursor:pointer; transition:background var(--dur-fast); }
.wh-dt-row:hover { background:var(--bg-sunken); }
.text-danger { color:var(--danger-fg); font-weight:500; }
.progress-track { flex:1; height:6px; border-radius:var(--r-pill); background:var(--bg-sunken); overflow:hidden; }
.progress-fill  { height:100%; border-radius:var(--r-pill); background:var(--halo-500); transition:width 0.3s; }
.wh-row-btn { width:28px; height:28px; border-radius:var(--r-sm); border:1px solid var(--border-subtle); background:var(--bg-canvas); color:var(--fg-2); cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:all var(--dur-fast); }
.wh-row-btn:hover { background:var(--bg-sunken); color:var(--fg-1); border-color:var(--border-strong); }
.wh-badge { display:inline-flex; align-items:center; gap:5px; padding:2px 8px; border-radius:var(--r-pill); font-size:11px; font-weight:500; }
.wh-badge-dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
.wh-badge-green  { background:var(--success-bg); color:var(--success-fg); }
.wh-badge-blue   { background:var(--halo-50); color:var(--halo-700); }
.wh-badge-amber  { background:var(--warn-bg); color:var(--warn-fg); }
.wh-badge-red    { background:var(--danger-bg); color:var(--danger-fg); }
.wh-badge-slate  { background:var(--bg-sunken); color:var(--fg-2); }
</style>
