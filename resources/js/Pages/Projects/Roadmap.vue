<template>
  <AppLayout>
    <Head title="Roadmap produit" />

    <AIAssistantPanel v-if="guidance" :guidance="guidance" />

    <div class="space-y-4">
      <div class="flex items-center justify-between" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:8px">
        <div>
          <h1 class="wh-page-title">Roadmap</h1>
          <p class="wh-page-subtitle">Vision à 12 mois par projet</p>
        </div>
        <div style="display:flex;gap:8px;align-items:center">
          <Dropdown
            v-model="selectedProject"
            :options="projectOptions"
            optionLabel="name"
            optionValue="id"
            placeholder="Tous les projets"
            class="w-48"
            style="min-width:192px"
            showClear
            @change="onProjectChange"
          />
        </div>
      </div>

      <!-- Timeline header (mois) -->
      <div class="wh-panel" style="overflow-x:auto;padding:0">
        <div style="min-width:max-content">
          <!-- Month headers -->
          <div style="display:flex;border-bottom:1px solid var(--surface-200)">
            <div style="width:192px;flex-shrink:0;padding:10px 12px;font-size:13px;font-weight:600;color:var(--fg-3);border-right:1px solid var(--surface-200)">
              Épique / Sprint
            </div>
            <div
              v-for="month in months"
              :key="month.key"
              style="flex:1;min-width:80px;padding:8px;text-align:center;font-size:11px;font-weight:600;color:var(--fg-3);border-right:1px solid var(--surface-200)"
              :style="month.isCurrentMonth ? { background: 'var(--primary-50, #eef2ff)', color: 'var(--primary-600, #4f46e5)' } : {}"
            >
              {{ month.label }}
              <div v-if="month.isCurrentMonth" style="width:4px;height:4px;border-radius:50%;background:var(--primary-500,#6366f1);margin:2px auto 0" />
            </div>
          </div>

          <!-- Loading state -->
          <div v-if="loading" style="padding:40px;text-align:center;color:var(--fg-3)">
            <i class="pi pi-spin pi-spinner" style="font-size:20px" />
          </div>

          <!-- Epic rows -->
          <template v-else>
            <div
              v-for="epic in filteredEpics"
              :key="`epic-${epic.id}`"
              style="display:flex;align-items:center;border-bottom:1px solid var(--surface-100);min-height:44px"
              class="roadmap-row"
            >
              <div style="width:192px;flex-shrink:0;padding:8px 12px;font-size:13px;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;border-right:1px solid var(--surface-200);display:flex;align-items:center;gap:8px">
                <span :style="{ backgroundColor: epic.color, width:'12px', height:'12px', borderRadius:'2px', flexShrink: 0 }" />
                {{ epic.title }}
              </div>
              <!-- Month cells + epic bar -->
              <div style="flex:1;position:relative;display:flex;min-height:44px;align-items:center">
                <div
                  v-for="month in months"
                  :key="month.key"
                  style="flex:1;min-width:80px;border-right:1px solid var(--surface-100);height:44px"
                  :style="month.isCurrentMonth ? { background: 'rgba(99,102,241,0.04)' } : {}"
                />
                <!-- Epic bar (absolute) -->
                <div
                  v-if="epicBarStyle(epic)"
                  class="epic-bar"
                  :style="{ ...epicBarStyle(epic), backgroundColor: epic.color }"
                  :title="`${epic.title} — ${epic.start_date ?? '?'} → ${epic.end_date ?? '?'}`"
                >
                  {{ epic.title }}
                </div>
              </div>
            </div>

            <!-- Sprint rows -->
            <div
              v-for="sprint in filteredSprints"
              :key="`sprint-${sprint.id}`"
              style="display:flex;align-items:center;border-bottom:1px solid var(--surface-100);min-height:36px"
              class="roadmap-row"
            >
              <div style="width:192px;flex-shrink:0;padding:6px 12px;font-size:12px;color:var(--fg-3);border-right:1px solid var(--surface-200);display:flex;align-items:center;gap:6px">
                <span style="display:inline-block;width:8px;height:2px;background:#60a5fa;border-radius:1px" />
                {{ sprint.name }}
                <Tag :value="sprint.status" :severity="sprintSeverity(sprint.status)" style="font-size:10px;padding:1px 5px" />
              </div>
              <div style="flex:1;position:relative;display:flex;min-height:36px;align-items:center">
                <div
                  v-for="month in months"
                  :key="month.key"
                  style="flex:1;min-width:80px;border-right:1px solid var(--surface-100);height:36px"
                />
                <!-- Sprint bar -->
                <div
                  v-if="sprintBarStyle(sprint)"
                  class="sprint-bar"
                  :style="sprintBarStyle(sprint)!"
                  :title="`${sprint.name}`"
                />
              </div>
            </div>

            <!-- Empty state -->
            <div v-if="!filteredEpics.length && !filteredSprints.length" style="padding:48px;text-align:center;color:var(--fg-3)">
              <p style="font-size:40px;margin-bottom:8px">🗺️</p>
              <p>Aucun épique planifié. Créez des épiques avec des dates pour les voir ici.</p>
            </div>
          </template>
        </div>
      </div>

      <!-- Legend -->
      <div style="display:flex;gap:16px;font-size:12px;color:var(--fg-3);flex-wrap:wrap">
        <span style="display:flex;align-items:center;gap:4px">
          <span style="width:12px;height:12px;border-radius:2px;background:#6366f1;display:inline-block" />
          Épique
        </span>
        <span style="display:flex;align-items:center;gap:4px">
          <span style="width:12px;height:4px;background:#60a5fa;border-radius:2px;display:inline-block" />
          Sprint
        </span>
        <span style="display:flex;align-items:center;gap:4px">
          ◆ Jalon
        </span>
        <span style="display:flex;align-items:center;gap:4px">
          <span style="width:4px;height:4px;border-radius:50%;background:#6366f1;display:inline-block" />
          Mois courant
        </span>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import { Select as Dropdown, Tag } from 'primevue'
import axios from 'axios'

interface EpicItem {
  id: number
  project_id: number
  title: string
  color: string
  start_date: string | null
  end_date: string | null
  status: string
}

interface SprintItem {
  id: number
  project_id: number
  name: string
  start_date: string | null
  end_date: string | null
  status: string
}

interface ProjectOption {
  id: number
  name: string
}

const props = defineProps<{ projects?: ProjectOption[] }>()
const { guidance } = useAiAssistant('Projects', 'view_roadmap')

const selectedProject = ref<number | null>(null)
const epics           = ref<EpicItem[]>([])
const sprints         = ref<SprintItem[]>([])
const loading         = ref(true)

const projectOptions = computed((): ProjectOption[] => props.projects ?? [])

const today = new Date()

const months = computed(() => {
  const result: { key: string; label: string; date: Date; isCurrentMonth: boolean }[] = []
  for (let i = 0; i < 12; i++) {
    const d = new Date(today.getFullYear(), today.getMonth() + i, 1)
    result.push({
      key:            `${d.getFullYear()}-${d.getMonth() + 1}`,
      label:          d.toLocaleDateString('fr', { month: 'short', year: '2-digit' }),
      date:           d,
      isCurrentMonth: d.getFullYear() === today.getFullYear() && d.getMonth() === today.getMonth(),
    })
  }
  return result
})

const filteredEpics = computed((): EpicItem[] => {
  if (selectedProject.value == null) return epics.value
  return epics.value.filter(e => e.project_id === selectedProject.value)
})

const filteredSprints = computed((): SprintItem[] => {
  if (selectedProject.value == null) return sprints.value
  return sprints.value.filter(s => s.project_id === selectedProject.value)
})

const firstDate = computed((): Date => months.value[0].date)
const lastDate  = computed((): Date => {
  const last = months.value[11].date
  return new Date(last.getFullYear(), last.getMonth() + 1, 0) // last day of last month
})

function positionBar(startStr: string | null, endStr: string | null): Record<string, string> | null {
  if (!startStr || !endStr) return null
  const start  = new Date(startStr)
  const end    = new Date(endStr)
  const first  = firstDate.value
  const last   = lastDate.value
  const totalMs = last.getTime() - first.getTime()
  if (totalMs <= 0) return null
  const leftMs  = Math.max(0, start.getTime() - first.getTime())
  const widthMs = Math.min(end.getTime(), last.getTime()) - Math.max(start.getTime(), first.getTime())
  if (widthMs <= 0) return null
  return {
    left:  `${(leftMs  / totalMs) * 100}%`,
    width: `${(widthMs / totalMs) * 100}%`,
  }
}

function epicBarStyle(epic: EpicItem): Record<string, string> | null {
  return positionBar(epic.start_date, epic.end_date)
}

function sprintBarStyle(sprint: SprintItem): Record<string, string> | null {
  return positionBar(sprint.start_date, sprint.end_date)
}

function sprintSeverity(status: string): string {
  return { planning: 'secondary', active: 'success', completed: 'contrast' }[status] ?? 'secondary'
}

async function fetchData(): Promise<void> {
  loading.value = true
  try {
    const params = selectedProject.value != null ? { project_id: selectedProject.value } : {}
    const [er, sr] = await Promise.all([
      axios.get('/api/v1/projects/epics', { params }),
      selectedProject.value != null
        ? axios.get(`/api/v1/projects/${selectedProject.value}/sprints`)
        : Promise.resolve({ data: { data: [] } }),
    ])
    epics.value   = er.data?.data ?? []
    sprints.value = sr.data?.data ?? []
  } catch {
    epics.value   = []
    sprints.value = []
  } finally {
    loading.value = false
  }
}

function onProjectChange(): void {
  fetchData()
}

onMounted(fetchData)
</script>

<style scoped>
.roadmap-row:hover { background: var(--surface-50, #f8fafc); }

.epic-bar {
  position: absolute;
  top: 8px;
  height: 24px;
  border-radius: 12px;
  color: #fff;
  font-size: 11px;
  font-weight: 600;
  display: flex;
  align-items: center;
  padding: 0 10px;
  opacity: 0.92;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  cursor: default;
  transition: opacity .15s;
  z-index: 2;
}
.epic-bar:hover { opacity: 1; }

.sprint-bar {
  position: absolute;
  top: 14px;
  height: 8px;
  border-radius: 4px;
  background: #60a5fa;
  z-index: 2;
}
</style>
