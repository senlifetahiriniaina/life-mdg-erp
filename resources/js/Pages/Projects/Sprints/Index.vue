<template>
  <AppLayout>
    <Head title="Sprint Planning" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Sprint Planning</h1>
        <p class="wh-page-subtitle">Velocity, burndown &amp; backlog</p>
      </div>
      <div class="page-actions">
        <Button icon="pi pi-plus" label="Nouveau sprint" @click="openCreateSprint" />
      </div>
    </div>

    <div v-if="loading" class="wh-panel" style="padding:40px;text-align:center;color:var(--fg-3)">
      <i class="pi pi-spin pi-spinner" style="font-size:20px" />
    </div>

    <div v-else class="sprint-layout">
      <!-- Active sprint header -->
      <div v-if="activeSprint" class="wh-panel" style="border-left:4px solid #6366f1;margin-bottom:16px">
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:8px">
          <Tag value="ACTIF" severity="success" />
          <span style="font-weight:700;font-size:16px">{{ activeSprint.name }}</span>
          <span style="color:var(--fg-3);font-size:13px">
            {{ activeSprint.start_date ? formatDate(activeSprint.start_date) : '—' }}
            →
            {{ activeSprint.end_date ? formatDate(activeSprint.end_date) : '—' }}
          </span>
          <Badge :value="`${activeSprint.completed_points}/${activeSprint.total_points} pts`" severity="info" />
        </div>
        <p v-if="activeSprint.goal" style="color:var(--fg-3);font-size:13px;margin-bottom:8px">
          🎯 {{ activeSprint.goal }}
        </p>
        <!-- Progress bar -->
        <div style="height:8px;background:var(--surface-200);border-radius:4px;overflow:hidden">
          <div
            :style="{
              width: `${activeSprint.progress_pct ?? 0}%`,
              background: '#6366f1',
              height: '100%',
              transition: 'width .3s'
            }"
          />
        </div>
        <div style="display:flex;gap:8px;margin-top:10px">
          <Button
            label="Terminer le sprint"
            icon="pi pi-flag"
            severity="warning"
            size="small"
            @click="completeSprint(activeSprint)"
          />
          <Button
            label="Burndown"
            icon="pi pi-chart-line"
            text
            size="small"
            @click="openBurndown(activeSprint)"
          />
        </div>
      </div>

      <!-- Burndown chart -->
      <div v-if="burndownData" class="wh-panel" style="margin-bottom:16px">
        <h2 style="font-weight:600;margin-bottom:12px">Burndown — {{ burndownData.sprint_name }}</h2>
        <apexchart
          type="area"
          height="280"
          :options="burndownChartOptions"
          :series="burndownSeries"
        />
      </div>

      <!-- Sprint list -->
      <div class="wh-panel" style="margin-bottom:16px">
        <h2 style="font-weight:600;margin-bottom:12px">Tous les sprints</h2>
        <DataTable :value="sprints" :rows="20" size="small">
          <template #empty><span style="color:var(--fg-3)">Aucun sprint.</span></template>
          <Column field="name" header="Nom" />
          <Column field="status" header="Statut">
            <template #body="{ data }">
              <Tag :value="data.status" :severity="sprintStatusSeverity(data.status)" />
            </template>
          </Column>
          <Column header="Points">
            <template #body="{ data }">
              <span>{{ data.completed_points }}/{{ data.total_points }}</span>
            </template>
          </Column>
          <Column field="start_date" header="Début" />
          <Column field="end_date" header="Fin" />
          <Column header="Actions">
            <template #body="{ data }">
              <div style="display:flex;gap:4px">
                <Button
                  v-if="data.status === 'planning'"
                  label="Démarrer"
                  icon="pi pi-play"
                  size="small"
                  @click="startSprint(data)"
                />
                <Button
                  icon="pi pi-pencil"
                  text
                  rounded
                  size="small"
                  @click="editSprint(data)"
                />
                <Button
                  icon="pi pi-trash"
                  text
                  rounded
                  size="small"
                  severity="danger"
                  @click="deleteSprint(data)"
                />
              </div>
            </template>
          </Column>
        </DataTable>
      </div>

      <!-- Backlog -->
      <div class="wh-panel">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
          <h2 style="font-weight:600">Backlog <Badge :value="String(backlog.length)" severity="secondary" /></h2>
          <Button
            label="✨ Planifier le sprint IA"
            icon="pi pi-sparkles"
            size="small"
            severity="help"
            @click="aiPlanSprint"
            :loading="aiPlanning"
          />
        </div>
        <DataTable :value="backlog" :rows="50" size="small" selectionMode="multiple" v-model:selection="selectedBacklog">
          <template #empty><span style="color:var(--fg-3)">Le backlog est vide.</span></template>
          <Column selectionMode="multiple" style="width:40px" />
          <Column field="title" header="Tâche" />
          <Column field="status" header="Statut">
            <template #body="{ data }">
              <Tag :value="data.status" severity="secondary" />
            </template>
          </Column>
          <Column field="story_points" header="Points">
            <template #body="{ data }">
              <Badge v-if="data.story_points" :value="String(data.story_points)" severity="info" />
              <span v-else style="color:var(--fg-3)">—</span>
            </template>
          </Column>
          <Column header="Ajouter au sprint">
            <template #body="{ data }">
              <Dropdown
                :options="planningOrActiveSprints"
                optionLabel="name"
                optionValue="id"
                placeholder="Sprint…"
                class="w-full"
                style="min-width:120px"
                @change="(e: any) => assignToSprint(data, e.value)"
              />
            </template>
          </Column>
        </DataTable>
      </div>
    </div>

    <!-- Dialog: Create / Edit Sprint -->
    <Dialog
      v-model:visible="sprintDialogVisible"
      :header="editingSprint ? 'Modifier le sprint' : 'Nouveau sprint'"
      :style="{ width: '480px' }"
      modal
    >
      <div style="display:flex;flex-direction:column;gap:12px">
        <div>
          <label class="block text-sm font-medium mb-1">Nom *</label>
          <InputText v-model="sprintForm.name" class="w-full" placeholder="Sprint 1" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Objectif du sprint</label>
          <Textarea v-model="sprintForm.goal" class="w-full" rows="2" placeholder="Objectif principal…" />
        </div>
        <div style="display:flex;gap:12px">
          <div style="flex:1">
            <label class="block text-sm font-medium mb-1">Début</label>
            <InputText v-model="sprintForm.start_date" type="date" class="w-full" />
          </div>
          <div style="flex:1">
            <label class="block text-sm font-medium mb-1">Fin</label>
            <InputText v-model="sprintForm.end_date" type="date" class="w-full" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Capacité (points)</label>
          <InputNumber v-model="sprintForm.capacity_points" :min="0" :max="127" class="w-full" />
        </div>
      </div>

      <template #footer>
        <Button label="Annuler" text @click="sprintDialogVisible = false" />
        <Button label="Enregistrer" icon="pi pi-check" :loading="saving" @click="saveSprint" />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, DataTable, Column, Dialog, Tag, InputText, Textarea, Dropdown, InputNumber, Badge } from 'primevue'
import axios from 'axios'

interface SprintItem {
  id: number
  project_id: number
  name: string
  goal: string | null
  start_date: string | null
  end_date: string | null
  status: string
  capacity_points: number | null
  total_points: number
  completed_points: number
  task_count: number
  progress_pct: number
}

interface BacklogTask {
  id: number
  title: string
  status: string
  story_points: number | null
}

interface BurndownData {
  sprint_id: number
  sprint_name: string
  total_points: number
  ideal: { date: string; points: number }[]
  actual: { date: string; points: number }[]
}

const props = defineProps<{ projectId?: number | string }>()

const loading    = ref(true)
const saving     = ref(false)
const aiPlanning = ref(false)
const sprints    = ref<SprintItem[]>([])
const backlog    = ref<BacklogTask[]>([])
const burndownData = ref<BurndownData | null>(null)

const selectedBacklog   = ref<BacklogTask[]>([])
const sprintDialogVisible = ref(false)
const editingSprint     = ref<SprintItem | null>(null)

const sprintForm = ref({
  name: '',
  goal: '',
  start_date: '',
  end_date: '',
  capacity_points: null as number | null,
})

const activeSprint = computed((): SprintItem | undefined => sprints.value.find(s => s.status === 'active'))
const planningOrActiveSprints = computed(() => sprints.value.filter(s => ['planning', 'active'].includes(s.status)))

function sprintStatusSeverity(status: string): string {
  return { planning: 'secondary', active: 'success', completed: 'contrast' }[status] ?? 'secondary'
}

function formatDate(d: string): string {
  return new Date(d).toLocaleDateString('fr', { day: '2-digit', month: 'short' })
}

const burndownChartOptions = computed(() => ({
  chart:  { toolbar: { show: false } },
  stroke: { curve: 'smooth', width: 2 },
  fill:   { type: 'gradient', gradient: { opacityFrom: 0.3, opacityTo: 0 } },
  xaxis:  { categories: burndownData.value?.ideal.map(d => d.date) ?? [] },
  yaxis:  { title: { text: 'Points restants' } },
  colors: ['#94a3b8', '#6366f1'],
  legend: { labels: { colors: '#64748b' } },
  tooltip: { x: { format: 'dd/MM/yy' } },
}))

const burndownSeries = computed(() => [
  { name: 'Idéal',  data: burndownData.value?.ideal.map(d => d.points) ?? [] },
  { name: 'Réel',   data: burndownData.value?.actual.map(d => d.points) ?? [] },
])

const pid = computed(() => props.projectId)

async function fetchSprints(): Promise<void> {
  if (!pid.value) return
  loading.value = true
  try {
    const [sr, br] = await Promise.all([
      axios.get(`/api/v1/projects/${pid.value}/sprints`),
      axios.get(`/api/v1/projects/${pid.value}/backlog`),
    ])
    sprints.value = sr.data?.data ?? []
    backlog.value = br.data?.data ?? []
  } catch { sprints.value = []; backlog.value = [] } finally { loading.value = false }
}

async function startSprint(sprint: SprintItem): Promise<void> {
  await axios.post(`/api/v1/projects/${pid.value}/sprints/${sprint.id}/start`)
  await fetchSprints()
}

async function completeSprint(sprint: SprintItem): Promise<void> {
  await axios.post(`/api/v1/projects/${pid.value}/sprints/${sprint.id}/complete`)
  await fetchSprints()
}

async function openBurndown(sprint: SprintItem): Promise<void> {
  try {
    const res = await axios.get(`/api/v1/projects/${pid.value}/sprints/${sprint.id}/burndown`)
    burndownData.value = res.data
  } catch { burndownData.value = null }
}

function openCreateSprint(): void {
  editingSprint.value = null
  sprintForm.value = { name: '', goal: '', start_date: '', end_date: '', capacity_points: null }
  sprintDialogVisible.value = true
}

function editSprint(sprint: SprintItem): void {
  editingSprint.value = sprint
  sprintForm.value = {
    name:            sprint.name,
    goal:            sprint.goal ?? '',
    start_date:      sprint.start_date ?? '',
    end_date:        sprint.end_date ?? '',
    capacity_points: sprint.capacity_points,
  }
  sprintDialogVisible.value = true
}

async function saveSprint(): Promise<void> {
  if (!sprintForm.value.name.trim() || !pid.value) return
  saving.value = true
  try {
    if (editingSprint.value) {
      await axios.put(`/api/v1/projects/${pid.value}/sprints/${editingSprint.value.id}`, sprintForm.value)
    } else {
      await axios.post(`/api/v1/projects/${pid.value}/sprints`, sprintForm.value)
    }
    sprintDialogVisible.value = false
    await fetchSprints()
  } finally { saving.value = false }
}

async function deleteSprint(sprint: SprintItem): Promise<void> {
  if (!confirm(`Supprimer le sprint "${sprint.name}" ?`)) return
  await axios.delete(`/api/v1/projects/${pid.value}/sprints/${sprint.id}`)
  await fetchSprints()
}

async function assignToSprint(task: BacklogTask, sprintId: number): Promise<void> {
  await axios.put(`/api/v1/tasks/${task.id}`, { sprint_id: sprintId })
  await fetchSprints()
}

async function aiPlanSprint(): Promise<void> {
  aiPlanning.value = true
  try {
    const res = await axios.post('/api/v1/projects/ai/estimate-task', {
      project_id:      pid.value,
      context:         'Sprint planning',
      backlog_task_ids: backlog.value.map(t => t.id),
    })
    alert(res.data?.suggestion ?? 'Aucune suggestion IA disponible pour le moment.')
  } catch {
    alert('Service IA indisponible.')
  } finally { aiPlanning.value = false }
}

onMounted(fetchSprints)
</script>
