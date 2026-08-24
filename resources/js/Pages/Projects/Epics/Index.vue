<template>
  <AppLayout>
    <Head title="Épiques" />

    <AIAssistantPanel v-if="guidance" :guidance="guidance" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Épiques</h1>
        <p class="wh-page-subtitle">Swimlanes par épique — story points &amp; progression</p>
      </div>
      <div class="page-actions">
        <Button icon="pi pi-plus" label="Nouvelle épique" @click="openCreateEpic" />
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="wh-panel" style="padding:40px;text-align:center;color:var(--fg-3)">
      <i class="pi pi-spin pi-spinner" style="font-size:20px" />
    </div>

    <!-- Empty state -->
    <div v-else-if="!epics.length" class="wh-panel" style="padding:48px;text-align:center;color:var(--fg-3)">
      <p style="font-size:40px;margin-bottom:8px">🗂️</p>
      <p>Aucune épique. Créez votre première épique pour organiser les stories.</p>
    </div>

    <!-- Swimlanes -->
    <div v-else class="space-y-4">
      <div
        v-for="epic in epics"
        :key="epic.id"
        class="wh-panel"
        :style="{ borderLeft: `4px solid ${epic.color}` }"
      >
        <!-- Epic header -->
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;flex-wrap:wrap">
          <span
            class="w-4 h-4 rounded-sm"
            :style="{ backgroundColor: epic.color, width:'16px', height:'16px', flexShrink:0 }"
          />
          <span style="font-weight:600;font-size:15px;flex:1">{{ epic.title }}</span>
          <Tag :value="epic.status" :severity="statusSeverity(epic.status)" />
          <Badge :value="`${epic.total_story_points ?? 0} pts`" severity="info" />
          <span style="font-size:12px;color:var(--fg-3)">
            {{ epic.start_date ? formatDate(epic.start_date) : '—' }}
            →
            {{ epic.end_date   ? formatDate(epic.end_date)   : '—' }}
          </span>
          <!-- Color picker -->
          <input
            type="color"
            :value="epic.color"
            class="color-swatch"
            title="Changer la couleur"
            @change="(e: Event) => updateEpicColor(epic, (e.target as HTMLInputElement).value)"
          />
          <Button
            icon="pi pi-pencil"
            text
            rounded
            size="small"
            @click="editEpic(epic)"
          />
        </div>

        <!-- Progress bar -->
        <div style="height:6px;background:var(--surface-200);border-radius:4px;margin-bottom:12px;overflow:hidden">
          <div
            :style="{ width: `${epic.progress_pct ?? 0}%`, background: epic.color, height: '100%', transition: 'width .3s' }"
          />
        </div>
        <p style="font-size:12px;color:var(--fg-3);margin-bottom:12px">
          {{ epic.done_count ?? 0 }} / {{ epic.task_count ?? 0 }} tâches complétées
          ({{ epic.progress_pct ?? 0 }}%)
        </p>

        <!-- Tasks list -->
        <DataTable
          :value="epic.tasks ?? []"
          :rows="10"
          size="small"
          :showGridlines="false"
          class="p-datatable-sm"
        >
          <template #empty>
            <span style="color:var(--fg-3);font-size:13px">Aucune tâche dans cette épique.</span>
          </template>
          <Column field="title" header="Tâche" style="min-width:200px" />
          <Column field="status" header="Statut">
            <template #body="{ data }">
              <Tag :value="data.status" :severity="taskStatusSeverity(data.status)" />
            </template>
          </Column>
          <Column field="story_points" header="Points">
            <template #body="{ data }">
              <Badge v-if="data.story_points" :value="String(data.story_points)" severity="secondary" />
              <span v-else style="color:var(--fg-3)">—</span>
            </template>
          </Column>
        </DataTable>

        <!-- Add story button -->
        <div style="margin-top:12px">
          <Button
            icon="pi pi-plus"
            label="Ajouter story"
            text
            size="small"
            :style="{ color: epic.color }"
            @click="openAddStory(epic)"
          />
        </div>
      </div>
    </div>

    <!-- Dialog: Create / Edit Epic -->
    <Dialog
      v-model:visible="epicDialogVisible"
      :header="editingEpic ? 'Modifier l\'épique' : 'Nouvelle épique'"
      :style="{ width: '500px' }"
      modal
    >
      <div class="space-y-3" style="display:flex;flex-direction:column;gap:12px">
        <div>
          <label class="block text-sm font-medium mb-1">Titre *</label>
          <InputText v-model="epicForm.title" class="w-full" placeholder="Nom de l'épique" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Description</label>
          <Textarea v-model="epicForm.description" class="w-full" rows="3" placeholder="Description optionnelle" />
        </div>
        <div style="display:flex;gap:12px">
          <div style="flex:1">
            <label class="block text-sm font-medium mb-1">Statut</label>
            <Dropdown
              v-model="epicForm.status"
              :options="epicStatuses"
              optionLabel="label"
              optionValue="value"
              class="w-full"
            />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Couleur</label>
            <input type="color" v-model="epicForm.color" class="color-swatch" style="width:40px;height:40px" />
          </div>
        </div>
        <div style="display:flex;gap:12px">
          <div style="flex:1">
            <label class="block text-sm font-medium mb-1">Date début</label>
            <InputText v-model="epicForm.start_date" type="date" class="w-full" />
          </div>
          <div style="flex:1">
            <label class="block text-sm font-medium mb-1">Date fin</label>
            <InputText v-model="epicForm.end_date" type="date" class="w-full" />
          </div>
        </div>
      </div>

      <template #footer>
        <Button label="Annuler" text @click="epicDialogVisible = false" />
        <Button label="Enregistrer" icon="pi pi-check" :loading="saving" @click="saveEpic" />
      </template>
    </Dialog>

    <!-- Dialog: Add story to epic -->
    <Dialog
      v-model:visible="storyDialogVisible"
      header="Ajouter une story"
      :style="{ width: '480px' }"
      modal
    >
      <div style="display:flex;flex-direction:column;gap:12px">
        <div>
          <label class="block text-sm font-medium mb-1">Titre *</label>
          <InputText v-model="storyForm.title" class="w-full" placeholder="Titre de la story" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Story Points</label>
          <InputNumber v-model="storyForm.story_points" :min="0" :max="100" class="w-full" />
        </div>
      </div>

      <template #footer>
        <Button label="Annuler" text @click="storyDialogVisible = false" />
        <Button label="Ajouter" icon="pi pi-plus" :loading="savingStory" @click="addStory" />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import { Button, DataTable, Column, Dialog, Tag, InputText, Textarea, Select as Dropdown, InputNumber, Badge } from 'primevue'
import axios from 'axios'

interface EpicTask {
  id: number
  title: string
  status: string
  story_points: number | null
}

interface EpicItem {
  id: number
  project_id: number
  title: string
  description: string | null
  color: string
  status: string
  start_date: string | null
  end_date: string | null
  progress_pct: number
  task_count: number
  done_count: number
  total_story_points: number
  done_story_points: number
  tasks?: EpicTask[]
}

const props = defineProps<{ projectId?: number | string }>()
const { guidance } = useAiAssistant('Projects', 'view_epics')

const loading  = ref(true)
const saving   = ref(false)
const savingStory = ref(false)
const epics    = ref<EpicItem[]>([])

const epicDialogVisible  = ref(false)
const storyDialogVisible = ref(false)
const editingEpic        = ref<EpicItem | null>(null)
const activeEpicForStory = ref<EpicItem | null>(null)

const epicForm = ref({
  title: '',
  description: '',
  color: '#6366f1',
  status: 'open',
  start_date: '',
  end_date: '',
})

const storyForm = ref({
  title: '',
  story_points: null as number | null,
})

const epicStatuses = [
  { label: 'Ouvert',      value: 'open'        },
  { label: 'En cours',    value: 'in_progress' },
  { label: 'Terminé',     value: 'done'        },
]

function statusSeverity(status: string): string {
  return { open: 'info', in_progress: 'warn', done: 'success' }[status] ?? 'secondary'
}

function taskStatusSeverity(status: string): string {
  return { done: 'success', in_progress: 'warn', cancelled: 'danger' }[status] ?? 'secondary'
}

function formatDate(d: string): string {
  return new Date(d).toLocaleDateString('fr', { day: '2-digit', month: 'short', year: 'numeric' })
}

async function fetchEpics(): Promise<void> {
  loading.value = true
  try {
    const pid = props.projectId
    const url = pid ? `/api/v1/projects/${pid}/epics` : '/api/v1/projects/epics'
    const res = await axios.get(url)
    epics.value = res.data?.data ?? []
  } catch { epics.value = [] } finally { loading.value = false }
}

function openCreateEpic(): void {
  editingEpic.value = null
  epicForm.value = { title: '', description: '', color: '#6366f1', status: 'open', start_date: '', end_date: '' }
  epicDialogVisible.value = true
}

function editEpic(epic: EpicItem): void {
  editingEpic.value = epic
  epicForm.value = {
    title:       epic.title,
    description: epic.description ?? '',
    color:       epic.color,
    status:      epic.status,
    start_date:  epic.start_date ?? '',
    end_date:    epic.end_date ?? '',
  }
  epicDialogVisible.value = true
}

async function saveEpic(): Promise<void> {
  if (!epicForm.value.title.trim()) return
  saving.value = true
  try {
    const pid = props.projectId
    if (!pid) return
    if (editingEpic.value) {
      await axios.put(`/api/v1/projects/${pid}/epics/${editingEpic.value.id}`, epicForm.value)
    } else {
      await axios.post(`/api/v1/projects/${pid}/epics`, epicForm.value)
    }
    epicDialogVisible.value = false
    await fetchEpics()
  } finally { saving.value = false }
}

async function updateEpicColor(epic: EpicItem, color: string): Promise<void> {
  const pid = props.projectId
  if (!pid) return
  await axios.put(`/api/v1/projects/${pid}/epics/${epic.id}`, { color })
  await fetchEpics()
}

function openAddStory(epic: EpicItem): void {
  activeEpicForStory.value = epic
  storyForm.value = { title: '', story_points: null }
  storyDialogVisible.value = true
}

async function addStory(): Promise<void> {
  if (!storyForm.value.title.trim() || !activeEpicForStory.value) return
  savingStory.value = true
  try {
    const pid = props.projectId
    if (!pid) return
    await axios.post(`/api/v1/projects/${pid}/tasks`, {
      title:        storyForm.value.title,
      type:         'story',
      story_points: storyForm.value.story_points,
      epic_id:      activeEpicForStory.value.id,
    })
    storyDialogVisible.value = false
    await fetchEpics()
  } finally { savingStory.value = false }
}

onMounted(fetchEpics)
</script>

<style scoped>
.color-swatch {
  cursor: pointer;
  border-radius: 4px;
  border: 1px solid var(--surface-300, #d1d5db);
  padding: 0;
}
.space-y-4 > * + * { margin-top: 16px; }
</style>
