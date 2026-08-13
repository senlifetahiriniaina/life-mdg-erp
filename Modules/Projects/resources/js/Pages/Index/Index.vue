<template>
  <div class="space-y-6">
    <!-- En-tête -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Gestion des Projets</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          Suivez l'avancement de vos projets clients et internes
        </p>
      </div>
      <div class="flex gap-2">
        <Button
          :icon="viewMode === 'cards' ? 'pi pi-list' : 'pi pi-th-large'"
          severity="secondary"
          outlined
          :label="viewMode === 'cards' ? 'Vue liste' : 'Vue cartes'"
          @click="viewMode = viewMode === 'cards' ? 'list' : 'cards'"
        />
        <Button
          v-if="canCreate"
          icon="pi pi-plus"
          label="Nouveau projet"
          @click="showCreateDialog = true"
        />
      </div>
    </div>

    <!-- Statistiques -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <Card v-for="stat in stats" :key="stat.label">
        <template #content>
          <div class="flex items-center gap-3">
            <div :class="['w-10 h-10 rounded-full flex items-center justify-center', stat.bg]">
              <i :class="['pi', stat.icon, stat.color, 'text-lg']"></i>
            </div>
            <div>
              <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stat.value }}</div>
              <div class="text-xs text-gray-500 dark:text-gray-400">{{ stat.label }}</div>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <!-- Filtres -->
    <Card>
      <template #content>
        <div class="flex flex-wrap gap-3 items-center">
          <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Filtrer :</span>
          <Select
            v-model="filterStatus"
            :options="statusOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Statut"
            class="w-44"
          />
          <Select
            v-model="filterType"
            :options="typeOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Type"
            class="w-44"
          />
          <Select
            v-model="filterOwner"
            :options="ownerOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Responsable"
            class="w-44"
          />
          <Button
            label="Réinitialiser"
            severity="secondary"
            text
            size="small"
            @click="resetFilters"
          />
        </div>
      </template>
    </Card>

    <!-- Vue cartes -->
    <div v-if="viewMode === 'cards'" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
      <Card
        v-for="project in filteredProjects"
        :key="project.id"
        class="hover:shadow-lg transition-shadow cursor-pointer"
        @click="goToProject(project.id)"
      >
        <template #header>
          <div class="px-4 pt-4 flex items-start justify-between">
            <div class="flex-1 min-w-0">
              <h3 class="font-semibold text-gray-900 dark:text-white truncate">{{ project.name }}</h3>
              <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ project.client }}</p>
            </div>
            <Tag
              :value="project.status"
              :severity="statusSeverity(project.status)"
              class="ml-2 shrink-0"
            />
          </div>
        </template>
        <template #content>
          <div class="space-y-3">
            <div class="flex justify-between text-sm">
              <span class="text-gray-500 dark:text-gray-400">Budget</span>
              <span class="font-medium text-gray-900 dark:text-white">{{ formatXOF(project.budget) }}</span>
            </div>
            <div class="flex justify-between text-sm">
              <span class="text-gray-500 dark:text-gray-400">Période</span>
              <span class="text-gray-700 dark:text-gray-300">{{ project.startDate }} → {{ project.endDate }}</span>
            </div>
            <div>
              <div class="flex justify-between text-sm mb-1">
                <span class="text-gray-500 dark:text-gray-400">Avancement</span>
                <span
                  class="font-medium"
                  :class="project.progress >= 75 ? 'text-green-600' : project.progress >= 40 ? 'text-blue-600' : 'text-orange-500'"
                >
                  {{ project.progress }}%
                </span>
              </div>
              <ProgressBar :value="project.progress" :showValue="false" style="height: 6px" />
            </div>
            <div class="flex items-center justify-between pt-1">
              <span class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 px-2 py-0.5 rounded-full">
                {{ project.type }}
              </span>
              <div class="flex -space-x-2">
                <div
                  v-for="(member, idx) in project.team.slice(0, 4)"
                  :key="idx"
                  class="w-7 h-7 rounded-full bg-blue-500 flex items-center justify-center text-white text-xs font-medium border-2 border-white dark:border-gray-800"
                  :title="member"
                >
                  {{ member.charAt(0).toUpperCase() }}
                </div>
                <div
                  v-if="project.team.length > 4"
                  class="w-7 h-7 rounded-full bg-gray-400 flex items-center justify-center text-white text-xs font-medium border-2 border-white dark:border-gray-800"
                >
                  +{{ project.team.length - 4 }}
                </div>
              </div>
            </div>
          </div>
        </template>
      </Card>

      <div
        v-if="filteredProjects.length === 0"
        class="col-span-full flex flex-col items-center justify-center py-16 text-gray-400"
      >
        <i class="pi pi-folder-open text-5xl mb-3"></i>
        <p class="text-lg font-medium">Aucun projet trouvé</p>
        <p class="text-sm">Modifiez vos filtres ou créez un nouveau projet</p>
      </div>
    </div>

    <!-- Vue liste DataTable -->
    <Card v-else>
      <template #content>
        <DataTable
          :value="filteredProjects"
          paginator
          :rows="10"
          dataKey="id"
          class="p-datatable-sm"
          rowHover
          @row-click="(e: any) => goToProject(e.data.id)"
        >
          <Column field="name" header="Projet" sortable>
            <template #body="{ data }">
              <div>
                <div class="font-medium text-gray-900 dark:text-white">{{ data.name }}</div>
                <div class="text-xs text-gray-500">{{ data.client }}</div>
              </div>
            </template>
          </Column>
          <Column field="type" header="Type" sortable />
          <Column field="status" header="Statut" sortable>
            <template #body="{ data }">
              <Tag :value="data.status" :severity="statusSeverity(data.status)" />
            </template>
          </Column>
          <Column field="budget" header="Budget" sortable>
            <template #body="{ data }">{{ formatXOF(data.budget) }}</template>
          </Column>
          <Column field="progress" header="Avancement" sortable>
            <template #body="{ data }">
              <div class="flex items-center gap-2">
                <ProgressBar :value="data.progress" :showValue="false" style="height: 6px; width: 80px" />
                <span class="text-xs">{{ data.progress }}%</span>
              </div>
            </template>
          </Column>
          <Column field="endDate" header="Échéance" sortable />
          <Column field="owner" header="Responsable" sortable />
          <Column header="Actions" style="width: 100px">
            <template #body="{ data }">
              <Button icon="pi pi-eye" rounded text size="small" @click.stop="goToProject(data.id)" />
              <Button icon="pi pi-pencil" rounded text size="small" severity="secondary" @click.stop="editProject(data)" />
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- Dialog créer un projet -->
    <Dialog
      v-model:visible="showCreateDialog"
      header="Nouveau projet"
      modal
      :style="{ width: '640px' }"
      :breakpoints="{ '960px': '90vw' }"
    >
      <div class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Nom du projet *</label>
            <InputText v-model="form.name" placeholder="Ex: Refonte site web" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Client *</label>
            <InputText v-model="form.client" placeholder="Nom du client" />
          </div>
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
          <Textarea v-model="form.description" rows="3" placeholder="Description du projet..." autoResize />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Budget (XOF) *</label>
            <InputText v-model="form.budget" placeholder="Ex: 5000000" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Type de projet</label>
            <Select
              v-model="form.type"
              :options="typeOptions.filter(o => o.value)"
              optionLabel="label"
              optionValue="value"
              placeholder="Sélectionner"
            />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Date de début</label>
            <InputText v-model="form.startDate" type="date" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Date de fin</label>
            <InputText v-model="form.endDate" type="date" />
          </div>
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Responsable</label>
          <Select
            v-model="form.owner"
            :options="ownerOptions.filter(o => o.value)"
            optionLabel="label"
            optionValue="value"
            placeholder="Assigner un responsable"
          />
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" outlined @click="showCreateDialog = false" />
        <Button label="Créer le projet" icon="pi pi-check" @click="createProject" />
      </template>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Card from 'primevue/card'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import ProgressBar from 'primevue/progressbar'

const page = usePage()
const canCreate = computed(() => {
  const auth = (page.props.auth as any)
  return auth?.permissions?.includes('projects.create') || auth?.user?.role === 'admin'
})

const viewMode = ref<'cards' | 'list'>('cards')
const showCreateDialog = ref(false)
const filterStatus = ref<string | null>(null)
const filterType = ref<string | null>(null)
const filterOwner = ref<string | null>(null)

const statusOptions = [
  { label: 'Tous les statuts', value: null },
  { label: 'En cours', value: 'En cours' },
  { label: 'En attente', value: 'En attente' },
  { label: 'En retard', value: 'En retard' },
  { label: 'Terminé', value: 'Terminé' },
  { label: 'Annulé', value: 'Annulé' },
]

const typeOptions = [
  { label: 'Tous les types', value: null },
  { label: 'Développement', value: 'Développement' },
  { label: 'Conseil', value: 'Conseil' },
  { label: 'Infrastructure', value: 'Infrastructure' },
  { label: 'Formation', value: 'Formation' },
  { label: 'Marketing', value: 'Marketing' },
]

const ownerOptions = [
  { label: 'Tous', value: null },
  { label: 'Amara Diallo', value: 'Amara Diallo' },
  { label: 'Fatou Ndiaye', value: 'Fatou Ndiaye' },
  { label: 'Kofi Mensah', value: 'Kofi Mensah' },
  { label: 'Aïssatou Bah', value: 'Aïssatou Bah' },
]

interface Project {
  id: number
  name: string
  client: string
  type: string
  status: string
  budget: number
  startDate: string
  endDate: string
  progress: number
  owner: string
  team: string[]
}

const projects = ref<Project[]>([
  {
    id: 1,
    name: 'Refonte ERP Textile SATG',
    client: 'Société Africaine Textile Guinée',
    type: 'Développement',
    status: 'En cours',
    budget: 18500000,
    startDate: '2026-01-15',
    endDate: '2026-06-30',
    progress: 65,
    owner: 'Amara Diallo',
    team: ['Amara Diallo', 'Fatou Ndiaye', 'Kofi Mensah', 'Bintou Keïta', 'Moussa Touré'],
  },
  {
    id: 2,
    name: 'Application Mobile MoMo Pay',
    client: 'MTN Côte d\'Ivoire',
    type: 'Développement',
    status: 'En retard',
    budget: 9200000,
    startDate: '2026-02-01',
    endDate: '2026-05-15',
    progress: 38,
    owner: 'Fatou Ndiaye',
    team: ['Fatou Ndiaye', 'Adama Coulibaly'],
  },
  {
    id: 3,
    name: 'Audit Sécurité Banque Atlantique',
    client: 'Banque Atlantique Mali',
    type: 'Conseil',
    status: 'Terminé',
    budget: 6750000,
    startDate: '2026-03-01',
    endDate: '2026-04-30',
    progress: 100,
    owner: 'Kofi Mensah',
    team: ['Kofi Mensah', 'Aïssatou Bah'],
  },
  {
    id: 4,
    name: 'Infrastructure Cloud SONATEL',
    client: 'SONATEL Sénégal',
    type: 'Infrastructure',
    status: 'En cours',
    budget: 27000000,
    startDate: '2026-04-01',
    endDate: '2026-09-30',
    progress: 22,
    owner: 'Aïssatou Bah',
    team: ['Aïssatou Bah', 'Moussa Touré', 'Ibrahim Sow'],
  },
  {
    id: 5,
    name: 'Formation SYSCOHADA UEMOA',
    client: 'Ordre des Experts Comptables',
    type: 'Formation',
    status: 'En attente',
    budget: 3200000,
    startDate: '2026-06-01',
    endDate: '2026-07-15',
    progress: 0,
    owner: 'Amara Diallo',
    team: ['Amara Diallo'],
  },
  {
    id: 6,
    name: 'Campagne Marketing Orange Money',
    client: 'Orange Guinée',
    type: 'Marketing',
    status: 'Terminé',
    budget: 4500000,
    startDate: '2026-03-15',
    endDate: '2026-04-15',
    progress: 100,
    owner: 'Fatou Ndiaye',
    team: ['Fatou Ndiaye', 'Kadiatou Bah'],
  },
])

const form = ref({
  name: '',
  client: '',
  description: '',
  budget: '',
  type: null as string | null,
  startDate: '',
  endDate: '',
  owner: null as string | null,
})

const stats = computed(() => {
  const active = projects.value.filter(p => p.status === 'En cours').length
  const late = projects.value.filter(p => p.status === 'En retard').length
  const doneThisMonth = projects.value.filter(p => p.status === 'Terminé').length
  const total = projects.value.length
  const onTimeRate = total > 0 ? Math.round((doneThisMonth / total) * 100) : 0
  return [
    { label: 'Projets actifs', value: active, icon: 'pi-play-circle', color: 'text-blue-600', bg: 'bg-blue-50 dark:bg-blue-900/20' },
    { label: 'En retard', value: late, icon: 'pi-exclamation-triangle', color: 'text-red-500', bg: 'bg-red-50 dark:bg-red-900/20' },
    { label: 'Terminés ce mois', value: doneThisMonth, icon: 'pi-check-circle', color: 'text-green-600', bg: 'bg-green-50 dark:bg-green-900/20' },
    { label: 'Livraison à temps', value: `${onTimeRate}%`, icon: 'pi-chart-line', color: 'text-purple-600', bg: 'bg-purple-50 dark:bg-purple-900/20' },
  ]
})

const filteredProjects = computed(() => {
  return projects.value.filter(p => {
    if (filterStatus.value && p.status !== filterStatus.value) return false
    if (filterType.value && p.type !== filterType.value) return false
    if (filterOwner.value && p.owner !== filterOwner.value) return false
    return true
  })
})

function statusSeverity(status: string): string {
  const map: Record<string, string> = {
    'En cours': 'info',
    'En attente': 'secondary',
    'En retard': 'danger',
    'Terminé': 'success',
    'Annulé': 'contrast',
  }
  return map[status] ?? 'secondary'
}

function formatXOF(amount: number): string {
  return new Intl.NumberFormat('fr-SN', {
    style: 'currency',
    currency: 'XOF',
    maximumFractionDigits: 0,
  }).format(amount)
}

function goToProject(id: number) {
  window.location.href = `/projects/${id}`
}

function editProject(project: Project) {
  form.value = {
    name: project.name,
    client: project.client,
    description: '',
    budget: String(project.budget),
    type: project.type,
    startDate: project.startDate,
    endDate: project.endDate,
    owner: project.owner,
  }
  showCreateDialog.value = true
}

function resetFilters() {
  filterStatus.value = null
  filterType.value = null
  filterOwner.value = null
}

function createProject() {
  projects.value.push({
    id: projects.value.length + 1,
    name: form.value.name,
    client: form.value.client,
    type: form.value.type ?? 'Développement',
    status: 'En attente',
    budget: parseInt(form.value.budget) || 0,
    startDate: form.value.startDate,
    endDate: form.value.endDate,
    progress: 0,
    owner: form.value.owner ?? 'Amara Diallo',
    team: [form.value.owner ?? 'Amara Diallo'],
  })
  showCreateDialog.value = false
  form.value = { name: '', client: '', description: '', budget: '', type: null, startDate: '', endDate: '', owner: null }
}
</script>
