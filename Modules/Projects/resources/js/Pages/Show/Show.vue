<template>
  <div v-if="canView" class="space-y-6">
    <!-- En-tête projet -->
    <div class="flex items-start justify-between">
      <div class="flex items-center gap-4">
        <Button icon="pi pi-arrow-left" severity="secondary" text rounded @click="() => window.history.back()" />
        <div>
          <div class="flex items-center gap-2">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ project.name }}</h1>
            <Tag :value="project.status" :severity="statusSeverity(project.status)" />
          </div>
          <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
            Client : {{ project.client }} &nbsp;|&nbsp; Responsable : {{ project.owner }}
          </p>
        </div>
      </div>
      <div class="flex gap-2">
        <Button v-if="canEdit" icon="pi pi-pencil" label="Modifier" severity="secondary" outlined />
        <Button icon="pi pi-share-alt" label="Partager" severity="secondary" outlined />
      </div>
    </div>

    <!-- KPI rapides en-tête -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <Card>
        <template #content>
          <div class="text-center">
            <div class="text-xs text-gray-500 mb-1">Progression globale</div>
            <div class="text-3xl font-bold text-blue-600">{{ project.progress }}%</div>
            <ProgressBar :value="project.progress" :showValue="false" style="height: 6px; margin-top: 8px" />
          </div>
        </template>
      </Card>
      <Card>
        <template #content>
          <div class="text-center">
            <div class="text-xs text-gray-500 mb-1">Budget prévu</div>
            <div class="text-xl font-bold text-gray-900 dark:text-white">{{ formatXOF(project.budget) }}</div>
            <div class="text-xs text-orange-500 mt-1">Consommé : {{ formatXOF(project.spent) }}</div>
          </div>
        </template>
      </Card>
      <Card>
        <template #content>
          <div class="text-center">
            <div class="text-xs text-gray-500 mb-1">Période</div>
            <div class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ project.startDate }}</div>
            <div class="text-xs text-gray-400">au {{ project.endDate }}</div>
          </div>
        </template>
      </Card>
      <Card>
        <template #content>
          <div class="text-center">
            <div class="text-xs text-gray-500 mb-1">Jours restants</div>
            <div class="text-3xl font-bold" :class="project.daysLeft < 10 ? 'text-red-500' : 'text-green-600'">
              {{ project.daysLeft }}
            </div>
            <div class="text-xs text-gray-400 mt-1">jours</div>
          </div>
        </template>
      </Card>
    </div>

    <!-- Onglets -->
    <Card>
      <template #content>
        <!-- Tab navigation -->
        <div class="flex gap-0 border-b border-gray-200 dark:border-gray-700 mb-6">
          <button
            v-for="tab in tabs"
            :key="tab.key"
            :class="[
              'px-5 py-3 text-sm font-medium border-b-2 transition-colors',
              activeTab === tab.key
                ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300',
            ]"
            @click="activeTab = tab.key"
          >
            <i :class="['pi', tab.icon, 'mr-1.5']"></i>{{ tab.label }}
          </button>
        </div>

        <!-- Vue d'ensemble -->
        <div v-if="activeTab === 'overview'" class="space-y-6">
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div
              v-for="kpi in overviewKpis"
              :key="kpi.label"
              class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 text-center"
            >
              <div class="text-2xl font-bold" :class="kpi.color">{{ kpi.value }}</div>
              <div class="text-xs text-gray-500 mt-1">{{ kpi.label }}</div>
              <Tag v-if="kpi.tag" :value="kpi.tag" :severity="kpi.tagSeverity" class="mt-2 text-xs" />
            </div>
          </div>

          <div>
            <h3 class="font-semibold text-gray-900 dark:text-white mb-3">Description du projet</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">{{ project.description }}</p>
          </div>

          <div>
            <h3 class="font-semibold text-gray-900 dark:text-white mb-3">Risques identifiés</h3>
            <div class="space-y-2">
              <div
                v-for="risk in project.risks"
                :key="risk.id"
                class="flex items-start gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-800"
              >
                <Tag :value="risk.level" :severity="riskSeverity(risk.level)" class="shrink-0" />
                <div>
                  <div class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ risk.title }}</div>
                  <div class="text-xs text-gray-500 mt-0.5">{{ risk.mitigation }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Tâches Kanban -->
        <div v-if="activeTab === 'tasks'">
          <div class="flex justify-between items-center mb-4">
            <h3 class="font-semibold text-gray-900 dark:text-white">Tableau Kanban</h3>
            <Button icon="pi pi-plus" label="Ajouter une tâche" size="small" @click="showTaskDialog = true" />
          </div>
          <div class="flex gap-4 overflow-x-auto pb-4">
            <div
              v-for="col in kanbanColumns"
              :key="col.key"
              class="flex-shrink-0 w-64 bg-gray-50 dark:bg-gray-800 rounded-lg p-3"
            >
              <div class="flex items-center justify-between mb-3">
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ col.label }}</span>
                <span class="text-xs bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-400 rounded-full px-2 py-0.5">
                  {{ tasksByColumn(col.key).length }}
                </span>
              </div>
              <div class="space-y-2">
                <div
                  v-for="task in tasksByColumn(col.key)"
                  :key="task.id"
                  class="bg-white dark:bg-gray-700 rounded-lg p-3 shadow-sm hover:shadow-md transition-shadow cursor-pointer"
                >
                  <div class="flex items-start justify-between gap-1 mb-2">
                    <span class="text-sm font-medium text-gray-900 dark:text-white leading-tight">{{ task.title }}</span>
                    <Tag :value="task.priority" :severity="prioritySeverity(task.priority)" class="text-xs shrink-0" />
                  </div>
                  <div class="flex items-center justify-between">
                    <div
                      class="w-6 h-6 rounded-full bg-blue-500 flex items-center justify-center text-white text-xs font-medium"
                      :title="task.assignee"
                    >
                      {{ task.assignee.charAt(0) }}
                    </div>
                    <span class="text-xs text-gray-400">{{ task.dueDate }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Équipe -->
        <div v-if="activeTab === 'team'">
          <div class="flex justify-between items-center mb-4">
            <h3 class="font-semibold text-gray-900 dark:text-white">Membres de l'équipe</h3>
            <Button icon="pi pi-user-plus" label="Ajouter un membre" size="small" severity="secondary" outlined />
          </div>
          <DataTable :value="project.team" dataKey="id" class="p-datatable-sm">
            <Column header="Collaborateur">
              <template #body="{ data }">
                <div class="flex items-center gap-2">
                  <div class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center text-white text-sm font-medium">
                    {{ data.name.charAt(0) }}
                  </div>
                  <div>
                    <div class="font-medium text-sm text-gray-900 dark:text-white">{{ data.name }}</div>
                    <div class="text-xs text-gray-500">{{ data.email }}</div>
                  </div>
                </div>
              </template>
            </Column>
            <Column field="role" header="Rôle" />
            <Column field="allocation" header="Allocation">
              <template #body="{ data }">
                <div class="flex items-center gap-2">
                  <ProgressBar :value="data.allocation" :showValue="false" style="height: 6px; width: 60px" />
                  <span class="text-xs">{{ data.allocation }}%</span>
                </div>
              </template>
            </Column>
            <Column field="tasksCount" header="Tâches" />
            <Column field="workload" header="Charge">
              <template #body="{ data }">
                <Tag
                  :value="data.workload"
                  :severity="data.workload === 'Surchargé' ? 'danger' : data.workload === 'Normal' ? 'success' : 'secondary'"
                />
              </template>
            </Column>
          </DataTable>
        </div>

        <!-- Documents -->
        <div v-if="activeTab === 'documents'">
          <div class="flex justify-between items-center mb-4">
            <h3 class="font-semibold text-gray-900 dark:text-white">Documents du projet</h3>
            <Button icon="pi pi-upload" label="Téléverser un fichier" size="small" />
          </div>
          <div class="space-y-2">
            <div
              v-for="doc in project.documents"
              :key="doc.id"
              class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
            >
              <div class="flex items-center gap-3">
                <i :class="['pi', docIcon(doc.type), 'text-blue-500 text-xl']"></i>
                <div>
                  <div class="text-sm font-medium text-gray-900 dark:text-white">{{ doc.name }}</div>
                  <div class="text-xs text-gray-500">{{ doc.size }} &middot; Ajouté par {{ doc.author }} le {{ doc.date }}</div>
                </div>
              </div>
              <div class="flex gap-1">
                <Button icon="pi pi-eye" rounded text size="small" />
                <Button icon="pi pi-download" rounded text size="small" severity="secondary" />
              </div>
            </div>
          </div>
        </div>
      </template>
    </Card>

    <!-- Dialog ajouter une tâche -->
    <Dialog v-model:visible="showTaskDialog" header="Nouvelle tâche" modal :style="{ width: '480px' }">
      <div class="space-y-4">
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Titre de la tâche *</label>
          <InputText v-model="taskForm.title" placeholder="Ex: Mise en place de la base de données" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Priorité</label>
            <Select
              v-model="taskForm.priority"
              :options="['Critique', 'Haute', 'Normale', 'Basse']"
              placeholder="Sélectionner"
            />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Date d'échéance</label>
            <InputText v-model="taskForm.dueDate" type="date" />
          </div>
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Assigné à</label>
          <Select
            v-model="taskForm.assignee"
            :options="project.team.map(m => m.name)"
            placeholder="Choisir un collaborateur"
          />
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" outlined @click="showTaskDialog = false" />
        <Button label="Créer la tâche" icon="pi pi-check" @click="addTask" />
      </template>
    </Dialog>

    <!-- AI Assistant -->
    <AiAssistantPanel v-if="guidance" :guidance="guidance" />
  </div>
  <div v-else class="flex items-center justify-center h-48">
    <Message severity="warn">Vous n'avez pas accès à cette section.</Message>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import Card from 'primevue/card'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import InputText from 'primevue/inputtext'
import ProgressBar from 'primevue/progressbar'
import StrategicContext from '@/Components/StrategicContext.vue'
import { useStrategicLink } from '@/composables/useStrategicLink'
import AiAssistantPanel from '@/Components/AI/AiAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import { useRbac } from '@/composables/useRbac'

const { guidance } = useAiAssistant('Projects', 'view_project')
const { isAdmin, canManage, canView } = useRbac('Projects')

const { objective } = useStrategicLink('Projects/Project', project?.id)

const activeTab = ref('overview')
const showTaskDialog = ref(false)

const tabs = [
  { key: 'overview', label: 'Vue d\'ensemble', icon: 'pi-home' },
  { key: 'tasks', label: 'Tâches', icon: 'pi-check-square' },
  { key: 'team', label: 'Équipe', icon: 'pi-users' },
  { key: 'documents', label: 'Documents', icon: 'pi-folder' },
]

const kanbanColumns = [
  { key: 'backlog', label: 'Backlog' },
  { key: 'todo', label: 'À faire' },
  { key: 'inprogress', label: 'En cours' },
  { key: 'review', label: 'En révision' },
  { key: 'done', label: 'Terminé' },
]

interface Task {
  id: number
  title: string
  column: string
  priority: string
  assignee: string
  dueDate: string
}

const tasks = ref<Task[]>([
  { id: 1, title: 'Analyse des besoins métier', column: 'done', priority: 'Haute', assignee: 'Amara Diallo', dueDate: '2026-02-15' },
  { id: 2, title: 'Conception de l\'architecture', column: 'done', priority: 'Haute', assignee: 'Kofi Mensah', dueDate: '2026-03-01' },
  { id: 3, title: 'Mise en place BDD PostgreSQL', column: 'inprogress', priority: 'Haute', assignee: 'Fatou Ndiaye', dueDate: '2026-05-30' },
  { id: 4, title: 'Développement API REST', column: 'inprogress', priority: 'Critique', assignee: 'Bintou Keïta', dueDate: '2026-06-15' },
  { id: 5, title: 'Interface utilisateur Vue 3', column: 'todo', priority: 'Normale', assignee: 'Moussa Touré', dueDate: '2026-06-20' },
  { id: 6, title: 'Intégration Mobile Money', column: 'todo', priority: 'Haute', assignee: 'Fatou Ndiaye', dueDate: '2026-06-25' },
  { id: 7, title: 'Tests unitaires et d\'intégration', column: 'backlog', priority: 'Normale', assignee: 'Kofi Mensah', dueDate: '2026-07-01' },
  { id: 8, title: 'Documentation technique', column: 'backlog', priority: 'Basse', assignee: 'Amara Diallo', dueDate: '2026-07-10' },
  { id: 9, title: 'Revue de sécurité OWASP', column: 'review', priority: 'Critique', assignee: 'Aïssatou Bah', dueDate: '2026-05-28' },
])

const taskForm = ref({ title: '', priority: 'Normale', dueDate: '', assignee: '' })

interface TeamMember {
  id: number
  name: string
  email: string
  role: string
  allocation: number
  tasksCount: number
  workload: string
}

const project = ref({
  id: 1,
  name: 'Refonte ERP Textile SATG',
  client: 'Société Africaine Textile Guinée',
  status: 'En cours',
  progress: 65,
  budget: 18500000,
  spent: 12025000,
  startDate: '15 Jan 2026',
  endDate: '30 Juin 2026',
  daysLeft: 37,
  owner: 'Amara Diallo',
  description: 'Refonte complète du système ERP de la SATG pour moderniser la gestion de la production textile, de la comptabilité SYSCOHADA et de la chaîne d\'approvisionnement. Le projet inclut l\'intégration des paiements Mobile Money (Orange Money, Wave) et une interface mobile-first adaptée aux opérateurs terrain.',
  risks: [
    { id: 1, level: 'Élevé', title: 'Retard de fourniture des données historiques par le client', mitigation: 'Relance hebdomadaire, migration partielle autorisée' },
    { id: 2, level: 'Moyen', title: 'Dépendance API Mobile Money en cours de certification', mitigation: 'Développement en mode sandbox, bascule production J-14' },
    { id: 3, level: 'Faible', title: 'Formation utilisateurs insuffisante', mitigation: 'Sessions de formation planifiées semaine 22' },
  ],
  team: [
    { id: 1, name: 'Amara Diallo', email: 'a.diallo@widehalo.com', role: 'Chef de projet', allocation: 80, tasksCount: 4, workload: 'Normal' },
    { id: 2, name: 'Fatou Ndiaye', email: 'f.ndiaye@widehalo.com', role: 'Développeur Senior', allocation: 100, tasksCount: 6, workload: 'Surchargé' },
    { id: 3, name: 'Kofi Mensah', email: 'k.mensah@widehalo.com', role: 'Architecte', allocation: 50, tasksCount: 3, workload: 'Disponible' },
    { id: 4, name: 'Bintou Keïta', email: 'b.keita@widehalo.com', role: 'Développeur Backend', allocation: 100, tasksCount: 5, workload: 'Normal' },
    { id: 5, name: 'Moussa Touré', email: 'm.toure@widehalo.com', role: 'Développeur Frontend', allocation: 80, tasksCount: 3, workload: 'Normal' },
  ] as TeamMember[],
  documents: [
    { id: 1, name: 'Cahier des charges SATG v2.1.pdf', type: 'pdf', size: '2.4 Mo', author: 'Amara Diallo', date: '15 Jan 2026' },
    { id: 2, name: 'Architecture technique.docx', type: 'doc', size: '840 Ko', author: 'Kofi Mensah', date: '05 Fév 2026' },
    { id: 3, name: 'Maquettes UI Figma.fig', type: 'fig', size: '18 Mo', author: 'Moussa Touré', date: '12 Fév 2026' },
    { id: 4, name: 'Planning Gantt Mai 2026.xlsx', type: 'xls', size: '320 Ko', author: 'Amara Diallo', date: '01 Mai 2026' },
  ],
})

const overviewKpis = computed(() => [
  { label: 'Budget consommé', value: formatXOF(project.value.spent), color: 'text-orange-500', tag: `${Math.round((project.value.spent / project.value.budget) * 100)}% utilisé`, tagSeverity: 'warn' },
  { label: 'Tâches terminées', value: `${tasks.value.filter(t => t.column === 'done').length}/${tasks.value.length}`, color: 'text-blue-600', tag: null, tagSeverity: 'info' },
  { label: 'Jours restants', value: project.value.daysLeft, color: project.value.daysLeft < 10 ? 'text-red-500' : 'text-green-600', tag: project.value.daysLeft < 10 ? 'Urgent' : 'Dans les temps', tagSeverity: project.value.daysLeft < 10 ? 'danger' : 'success' },
  { label: 'Risques identifiés', value: project.value.risks.length, color: 'text-yellow-600', tag: project.value.risks.filter(r => r.level === 'Élevé').length + ' élevés', tagSeverity: 'warn' },
])

function tasksByColumn(col: string): Task[] {
  return tasks.value.filter(t => t.column === col)
}

function statusSeverity(status: string): string {
  const map: Record<string, string> = {
    'En cours': 'info',
    'En attente': 'secondary',
    'En retard': 'danger',
    'Terminé': 'success',
  }
  return map[status] ?? 'secondary'
}

function prioritySeverity(priority: string): string {
  const map: Record<string, string> = {
    'Critique': 'danger',
    'Haute': 'warn',
    'Normale': 'info',
    'Basse': 'secondary',
  }
  return map[priority] ?? 'secondary'
}

function riskSeverity(level: string): string {
  const map: Record<string, string> = { 'Élevé': 'danger', 'Moyen': 'warn', 'Faible': 'success' }
  return map[level] ?? 'secondary'
}

function docIcon(type: string): string {
  const map: Record<string, string> = {
    pdf: 'pi-file-pdf',
    doc: 'pi-file-word',
    xls: 'pi-file-excel',
    fig: 'pi-palette',
  }
  return map[type] ?? 'pi-file'
}

function formatXOF(amount: number): string {
  return new Intl.NumberFormat('fr-SN', {
    style: 'currency',
    currency: 'XOF',
    maximumFractionDigits: 0,
  }).format(amount)
}

function addTask() {
  tasks.value.push({
    id: tasks.value.length + 1,
    title: taskForm.value.title,
    column: 'backlog',
    priority: taskForm.value.priority,
    assignee: taskForm.value.assignee,
    dueDate: taskForm.value.dueDate,
  })
  showTaskDialog.value = false
  taskForm.value = { title: '', priority: 'Normale', dueDate: '', assignee: '' }
}
</script>
