<template>
  <AppLayout>
    <Head :title="project.name" />

    <AIAssistantPanel v-if="guidance" :guidance="guidance" />

    <div class="page-head">
      <div style="display:flex;align-items:center;gap:12px">
        <a href="/projects" class="btn btn-icon"><i class="pi pi-arrow-left" style="font-size:14px" /></a>
        <div>
          <h1 class="wh-page-title">{{ project.name }}</h1>
          <div style="display:flex;gap:6px;margin-top:6px">
            <span :class="['wh-badge', statusClass(project.status)]">
              <span class="wh-badge-dot" />{{ statusLabel(project.status) }}
            </span>
          </div>
        </div>
      </div>
      <ExportButton :project-id="project.id" />
    </div>

    <!-- Project info -->
    <div class="wh-panel" style="margin-bottom:16px">
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:0;border-bottom:1px solid var(--border-subtle)">
        <div v-for="info in infoFields" :key="info.label" class="info-cell">
          <p class="info-label">{{ info.label }}</p>
          <p class="info-value">{{ info.value }}</p>
        </div>
        <div class="info-cell">
          <p class="info-label">Progression</p>
          <div style="display:flex;align-items:center;gap:8px;margin-top:4px">
            <div class="progress-track"><div class="progress-fill" :style="{ width: budgetProgress + '%' }" /></div>
            <span style="font-size:12px;color:var(--fg-3);width:30px;text-align:right">{{ budgetProgress }}%</span>
          </div>
        </div>
      </div>
      <div v-if="project.description" style="padding:20px">
        <p style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;color:var(--fg-3);margin:0 0 8px">Description</p>
        <p style="font-size:14px;color:var(--fg-1);margin:0;line-height:1.6">{{ project.description }}</p>
      </div>
    </div>

    <!-- Milestones -->
    <div v-if="project.milestones?.length" class="wh-panel" style="margin-bottom:16px">
      <div class="wh-panel-head"><h3>Jalons</h3></div>
      <div style="padding:16px 18px;display:flex;flex-wrap:wrap;gap:8px">
        <div
          v-for="m in project.milestones"
          :key="m.id"
          class="milestone-tag"
          :class="m.is_reached ? 'milestone-reached' : ''"
        >
          <i :class="m.is_reached ? 'pi pi-check-circle' : 'pi pi-circle'" style="font-size:12px" />
          <span>{{ m.name }}</span>
          <span style="opacity:0.7;font-size:11px">{{ formatDate(m.due_date) }}</span>
        </div>
      </div>
    </div>

    <!-- Team & Time Tracking -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
      <TeamPanel :project-id="project.id" />
      <TimeTracker :project-id="project.id" :tasks="project.tasks" />
    </div>

    <!-- Tasks table -->
    <div class="wh-panel">
      <div class="wh-panel-head"><h3>Tâches</h3></div>
      <table class="wh-dt">
        <thead>
          <tr>
            <th>Titre</th>
            <th>Statut</th>
            <th>Priorité</th>
            <th>Assigné à</th>
            <th>Échéance</th>
            <th class="num">Heures</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="task in project.tasks" :key="task.id" class="wh-dt-row">
            <td style="font-weight:500;color:var(--fg-1)">{{ task.title }}</td>
            <td>
              <span :class="['wh-badge', taskStatusClass(task.status)]">
                <span class="wh-badge-dot" />{{ taskStatusLabel(task.status) }}
              </span>
            </td>
            <td><span :class="['wh-badge', priorityClass(task.priority)]">{{ priorityLabel(task.priority) }}</span></td>
            <td style="color:var(--fg-2)">{{ task.assignee?.name ?? '—' }}</td>
            <td :class="isOverdue(task.due_date) && task.status !== 'done' ? 'text-danger' : ''" style="color:var(--fg-2)">
              {{ formatDate(task.due_date) }}
            </td>
            <td class="num" style="color:var(--fg-3)">{{ task.logged_hours ?? 0 }} / {{ task.estimated_hours ?? 0 }}h</td>
          </tr>
          <tr v-if="!project.tasks?.length">
            <td colspan="6" style="text-align:center;color:var(--fg-3);padding:32px 18px">Aucune tâche.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import TeamPanel from '@/Components/Projects/TeamPanel.vue'
import TimeTracker from '@/Components/Projects/TimeTracker.vue'
import ExportButton from '@/Components/Projects/ExportButton.vue'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'

const props = defineProps({
  project: { type: Object, required: true },
})

const { guidance } = useAiAssistant('Projects', 'view_project')

const today = new Date()

const budgetProgress = computed(() => {
  // Chantier 32.17 (14-layer deep audit): Project has no spent_budget
  // column anywhere in the backend (Modules\Projects\Models\Project's
  // $fillable never had it, confirmed via Schema::getColumnListing —
  // spending is tracked separately by ProjectBudgetService's EVM
  // endpoints, not exposed on this page's props) — spent_budget was
  // therefore always undefined here, so this always rendered "NaN%" for
  // any project with a non-zero budget. Default to 0 rather than
  // silently NaN, honestly reflecting that no actual-spend figure
  // reaches this page yet.
  const spent = Number(props.project.spent_budget) || 0
  if (!props.project.budget || props.project.budget == 0) return 0
  return Math.min(100, Math.round((spent / props.project.budget) * 100))
})

const infoFields = computed(() => [
  { label: 'Responsable',  value: props.project.owner?.name ?? '—' },
  { label: 'Début',        value: formatDate(props.project.start_date) },
  { label: 'Fin',          value: formatDate(props.project.end_date) },
  { label: 'Budget',       value: formatMoney(props.project.budget) },
  { label: 'Dépensé',      value: formatMoney(props.project.spent_budget) },
])

const statusClass   = (s) => ({ planning:'wh-badge-slate', active:'wh-badge-blue', on_hold:'wh-badge-amber', completed:'wh-badge-green', cancelled:'wh-badge-red' }[s] ?? 'wh-badge-slate')
const statusLabel   = (s) => ({ planning:'Planifié', active:'Actif', on_hold:'En pause', completed:'Terminé', cancelled:'Annulé' }[s] ?? s)
const priorityClass = (p) => ({ low:'wh-badge-slate', medium:'wh-badge-blue', high:'wh-badge-amber', critical:'wh-badge-red' }[p] ?? 'wh-badge-slate')
const priorityLabel = (p) => ({ low:'Faible', medium:'Moyenne', high:'Haute', critical:'Critique' }[p] ?? p)
const taskStatusClass = (s) => ({ todo:'wh-badge-slate', in_progress:'wh-badge-blue', review:'wh-badge-amber', done:'wh-badge-green' }[s] ?? 'wh-badge-slate')
const taskStatusLabel = (s) => ({ todo:'À faire', in_progress:'En cours', review:'En revue', done:'Terminée' }[s] ?? s)
const isOverdue = (date) => date && new Date(date) < today
const formatDate = (date) => date ? new Date(date).toLocaleDateString('fr-FR') : '—'
const formatMoney = (v) => v != null ? Number(v).toLocaleString('fr-FR', { minimumFractionDigits: 0 }) + ' €' : '—'
</script>

<style scoped>
.page-head { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:24px; gap:16px; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:22px; font-weight:600; letter-spacing:-0.02em; color:var(--fg-1); }
.btn-icon { background:var(--bg-canvas); color:var(--fg-2); border:1px solid var(--border-subtle); border-radius:var(--r-md); width:34px; height:34px; padding:0; display:inline-flex; align-items:center; justify-content:center; cursor:pointer; text-decoration:none; flex-shrink:0; }
.info-cell { padding:14px 18px; border-right:1px solid var(--border-subtle); }
.info-cell:last-child { border-right:0; }
.info-label { font-size:11px; color:var(--fg-3); text-transform:uppercase; letter-spacing:0.06em; margin:0 0 4px; }
.info-value { font-size:13px; font-weight:500; color:var(--fg-1); margin:0; }
.wh-panel-head { display:flex; align-items:center; justify-content:space-between; padding:14px 18px; border-bottom:1px solid var(--border-subtle); }
.wh-panel-head h3 { margin:0; font-size:14px; font-weight:600; color:var(--fg-1); }
.progress-track { flex:1; height:6px; border-radius:99px; background:var(--bg-sunken); overflow:hidden; }
.progress-fill { height:100%; border-radius:99px; background:var(--halo-500); transition:width 0.3s; }
.milestone-tag { display:inline-flex; align-items:center; gap:6px; padding:5px 10px; border-radius:var(--r-pill); border:1px solid var(--border-subtle); background:var(--bg-sunken); font-size:12px; color:var(--fg-2); }
.milestone-reached { border-color:var(--success-fg,#065F46); background:var(--success-bg,#D1FAE5); color:var(--success-fg,#065F46); }
.wh-dt { width:100%; border-collapse:collapse; font-size:14px; }
.wh-dt thead th { text-align:left; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:var(--fg-3); font-weight:600; padding:10px 18px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sunken); }
.wh-dt thead th.num { text-align:right; }
.wh-dt-row td { padding:11px 18px; border-bottom:1px solid var(--border-subtle); }
.wh-dt-row td.num { text-align:right; }
.wh-dt-row:last-child td { border-bottom:0; }
.wh-dt-row { cursor:pointer; transition:background var(--dur-fast); }
.wh-dt-row:hover { background:var(--bg-sunken); }
.text-danger { color:var(--danger-fg); font-weight:500; }
.wh-badge { display:inline-flex; align-items:center; gap:5px; padding:2px 8px; border-radius:var(--r-pill); font-size:11px; font-weight:500; }
.wh-badge-dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
.wh-badge-green { background:var(--success-bg); color:var(--success-fg); }
.wh-badge-blue { background:var(--halo-50); color:var(--halo-700); }
.wh-badge-amber { background:var(--warn-bg); color:var(--warn-fg); }
.wh-badge-red { background:var(--danger-bg); color:var(--danger-fg); }
.wh-badge-slate { background:var(--bg-sunken); color:var(--fg-2); }
</style>
