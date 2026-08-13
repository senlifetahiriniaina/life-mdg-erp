<template>
  <div class="space-y-6">
    <!-- En-tête -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Rapport de temps</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          Suivi des heures par projet et collaborateur
        </p>
      </div>
      <div class="flex gap-2">
        <Button
          icon="pi pi-file-export"
          label="Exporter vers facture"
          severity="secondary"
          outlined
          @click="exportToInvoice"
        />
        <Button icon="pi pi-plus" label="Saisir du temps" @click="showLogDialog = true" />
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
            v-model="filterProject"
            :options="projectOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Projet"
            class="w-52"
          />
          <Select
            v-model="filterPerson"
            :options="personOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Collaborateur"
            class="w-48"
          />
          <InputText v-model="filterDateFrom" type="date" class="w-40" :placeholder="'Date début'" />
          <InputText v-model="filterDateTo" type="date" class="w-40" :placeholder="'Date fin'" />
          <Button label="Réinitialiser" severity="secondary" text size="small" @click="resetFilters" />
        </div>
      </template>
    </Card>

    <!-- DataTable des imputations -->
    <Card>
      <template #header>
        <div class="px-4 pt-4 flex items-center justify-between">
          <h2 class="text-base font-semibold text-gray-900 dark:text-white">Imputations de temps</h2>
          <span class="text-sm text-gray-500">{{ filteredEntries.length }} enregistrements</span>
        </div>
      </template>
      <template #content>
        <DataTable
          :value="filteredEntries"
          paginator
          :rows="12"
          dataKey="id"
          class="p-datatable-sm"
          rowHover
          :selection="selectedEntries"
          selectionMode="multiple"
        >
          <Column field="person" header="Collaborateur" sortable>
            <template #body="{ data }">
              <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-full bg-blue-500 flex items-center justify-center text-white text-xs font-medium">
                  {{ data.person.charAt(0) }}
                </div>
                <span class="text-sm">{{ data.person }}</span>
              </div>
            </template>
          </Column>
          <Column field="project" header="Projet" sortable />
          <Column field="task" header="Tâche" sortable />
          <Column field="date" header="Date" sortable />
          <Column field="hours" header="Heures" sortable>
            <template #body="{ data }">
              <span class="font-medium">{{ data.hours }}h</span>
            </template>
          </Column>
          <Column field="billable" header="Type" sortable>
            <template #body="{ data }">
              <Tag
                :value="data.billable ? 'Facturable' : 'Non facturable'"
                :severity="data.billable ? 'success' : 'secondary'"
              />
            </template>
          </Column>
          <Column field="note" header="Note">
            <template #body="{ data }">
              <span class="text-sm text-gray-500 truncate max-w-xs block" :title="data.note">{{ data.note || '—' }}</span>
            </template>
          </Column>
          <Column header="" style="width: 80px">
            <template #body="{ data }">
              <Button icon="pi pi-pencil" rounded text size="small" @click="editEntry(data)" />
              <Button icon="pi pi-trash" rounded text size="small" severity="danger" @click="deleteEntry(data.id)" />
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- Résumé hebdomadaire -->
    <Card>
      <template #header>
        <div class="px-4 pt-4">
          <h2 class="text-base font-semibold text-gray-900 dark:text-white">Résumé hebdomadaire (semaine courante)</h2>
          <p class="text-xs text-gray-500 mt-0.5">Heures saisies par collaborateur et par jour</p>
        </div>
      </template>
      <template #content>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-200 dark:border-gray-700">
                <th scope="col" class="text-left py-2 px-3 font-medium text-gray-600 dark:text-gray-400 w-40">Collaborateur</th>
                <th scope="col"
                  v-for="day in weekDays"
                  :key="day.key"
                  class="text-center py-2 px-3 font-medium text-gray-600 dark:text-gray-400 min-w-[70px]"
                >
                  {{ day.label }}
                </th>
                <th scope="col" class="text-center py-2 px-3 font-semibold text-gray-900 dark:text-white">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in weeklyData"
                :key="row.person"
                class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50"
              >
                <td class="py-2 px-3 font-medium text-gray-800 dark:text-gray-200">{{ row.person }}</td>
                <td
                  v-for="day in weekDays"
                  :key="day.key"
                  class="text-center py-2 px-3"
                >
                  <span
                    v-if="row.hours[day.key] > 0"
                    :class="[
                      'px-2 py-0.5 rounded text-xs font-medium',
                      row.hours[day.key] >= 8 ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                    ]"
                  >
                    {{ row.hours[day.key] }}h
                  </span>
                  <span v-else class="text-gray-300 dark:text-gray-600">—</span>
                </td>
                <td class="text-center py-2 px-3 font-bold text-gray-900 dark:text-white">
                  {{ Object.values(row.hours).reduce((a, b) => a + b, 0) }}h
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="bg-gray-50 dark:bg-gray-800 font-semibold">
                <td class="py-2 px-3 text-gray-700 dark:text-gray-300">Total</td>
                <td
                  v-for="day in weekDays"
                  :key="day.key"
                  class="text-center py-2 px-3 text-gray-900 dark:text-white"
                >
                  {{ weeklyData.reduce((sum, row) => sum + row.hours[day.key], 0) }}h
                </td>
                <td class="text-center py-2 px-3 text-blue-600 dark:text-blue-400">
                  {{ weeklyData.reduce((sum, row) => sum + Object.values(row.hours).reduce((a, b) => a + b, 0), 0) }}h
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </template>
    </Card>

    <!-- Dialog saisie de temps -->
    <Dialog
      v-model:visible="showLogDialog"
      header="Saisir du temps"
      modal
      :style="{ width: '480px' }"
    >
      <div class="space-y-4">
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Projet *</label>
          <Select
            v-model="logForm.project"
            :options="projectOptions.filter(o => o.value)"
            optionLabel="label"
            optionValue="value"
            placeholder="Sélectionner un projet"
          />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Tâche</label>
          <InputText v-model="logForm.task" placeholder="Ex: Développement API REST" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Date *</label>
            <InputText v-model="logForm.date" type="date" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Heures *</label>
            <InputText v-model="logForm.hours" type="number" placeholder="Ex: 3.5" step="0.5" min="0.5" max="24" />
          </div>
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
          <Textarea v-model="logForm.note" rows="2" placeholder="Travaux effectués..." autoResize />
        </div>
        <div class="flex items-center gap-3">
          <ToggleSwitch v-model="logForm.billable" inputId="billable-toggle" />
          <label for="billable-toggle" class="text-sm font-medium text-gray-700 dark:text-gray-300">
            Heures facturables
          </label>
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" outlined @click="showLogDialog = false" />
        <Button label="Enregistrer" icon="pi pi-check" @click="logTime" />
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
import ToggleSwitch from 'primevue/toggleswitch'

const page = usePage()
const roles = computed(() => page.props.auth?.user?.roles?.map(r => r.name) || [])
const isAdmin = computed(() => roles.value.some(r => ['admin', 'super-admin'].includes(r)))
const canManage = computed(() => roles.value.some(r => ['project-manager', 'admin', 'super-admin'].includes(r)))
const canCreate = computed(() => canManage.value)
const canEdit = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)


usePage()

const showLogDialog = ref(false)
const filterProject = ref<string | null>(null)
const filterPerson = ref<string | null>(null)
const filterDateFrom = ref('')
const filterDateTo = ref('')
const selectedEntries = ref([])

const projectOptions = [
  { label: 'Tous les projets', value: null },
  { label: 'Refonte ERP Textile SATG', value: 'Refonte ERP Textile SATG' },
  { label: 'Application Mobile MoMo Pay', value: 'Application Mobile MoMo Pay' },
  { label: 'Infrastructure Cloud SONATEL', value: 'Infrastructure Cloud SONATEL' },
  { label: 'Formation SYSCOHADA UEMOA', value: 'Formation SYSCOHADA UEMOA' },
]

const personOptions = [
  { label: 'Tous', value: null },
  { label: 'Amara Diallo', value: 'Amara Diallo' },
  { label: 'Fatou Ndiaye', value: 'Fatou Ndiaye' },
  { label: 'Kofi Mensah', value: 'Kofi Mensah' },
  { label: 'Bintou Keïta', value: 'Bintou Keïta' },
]

interface TimeEntry {
  id: number
  person: string
  project: string
  task: string
  date: string
  hours: number
  billable: boolean
  note: string
}

const timeEntries = ref<TimeEntry[]>([
  { id: 1, person: 'Amara Diallo', project: 'Refonte ERP Textile SATG', task: 'Réunion de cadrage client', date: '2026-05-19', hours: 2, billable: true, note: 'Présentation maquettes v2' },
  { id: 2, person: 'Fatou Ndiaye', project: 'Refonte ERP Textile SATG', task: 'Développement API REST', date: '2026-05-19', hours: 7, billable: true, note: 'Endpoints factures OHADA' },
  { id: 3, person: 'Kofi Mensah', project: 'Application Mobile MoMo Pay', task: 'Architecture microservices', date: '2026-05-19', hours: 4, billable: true, note: '' },
  { id: 4, person: 'Bintou Keïta', project: 'Refonte ERP Textile SATG', task: 'Migration base de données', date: '2026-05-20', hours: 6, billable: true, note: 'Import données historiques 2022-2025' },
  { id: 5, person: 'Amara Diallo', project: 'Infrastructure Cloud SONATEL', task: 'Suivi de réunion interne', date: '2026-05-20', hours: 1.5, billable: false, note: 'Réunion équipe hebdo' },
  { id: 6, person: 'Fatou Ndiaye', project: 'Application Mobile MoMo Pay', task: 'Intégration API Wave', date: '2026-05-20', hours: 8, billable: true, note: 'Sandbox testé avec succès' },
  { id: 7, person: 'Kofi Mensah', project: 'Refonte ERP Textile SATG', task: 'Review code Fatou', date: '2026-05-21', hours: 2, billable: false, note: '' },
  { id: 8, person: 'Bintou Keïta', project: 'Infrastructure Cloud SONATEL', task: 'Configuration Kubernetes', date: '2026-05-21', hours: 7.5, billable: true, note: 'Cluster staging opérationnel' },
  { id: 9, person: 'Amara Diallo', project: 'Formation SYSCOHADA UEMOA', task: 'Préparation supports', date: '2026-05-22', hours: 3, billable: false, note: 'Slides module 3' },
  { id: 10, person: 'Fatou Ndiaye', project: 'Refonte ERP Textile SATG', task: 'Tests unitaires', date: '2026-05-22', hours: 5, billable: true, note: '78 tests ajoutés' },
])

const logForm = ref({
  project: null as string | null,
  task: '',
  date: '',
  hours: '',
  note: '',
  billable: true,
})

const filteredEntries = computed(() => {
  return timeEntries.value.filter(e => {
    if (filterProject.value && e.project !== filterProject.value) return false
    if (filterPerson.value && e.person !== filterPerson.value) return false
    if (filterDateFrom.value && e.date < filterDateFrom.value) return false
    if (filterDateTo.value && e.date > filterDateTo.value) return false
    return true
  })
})

const stats = computed(() => {
  const totalHours = filteredEntries.value.reduce((sum, e) => sum + e.hours, 0)
  const billableHours = filteredEntries.value.filter(e => e.billable).reduce((sum, e) => sum + e.hours, 0)
  const billableRate = totalHours > 0 ? Math.round((billableHours / totalHours) * 100) : 0
  const overBudgetHours = Math.max(0, totalHours - 160)
  return [
    { label: 'Heures saisies ce mois', value: `${totalHours}h`, icon: 'pi-clock', color: 'text-blue-600', bg: 'bg-blue-50 dark:bg-blue-900/20' },
    { label: 'Heures facturables', value: `${billableHours}h`, icon: 'pi-dollar', color: 'text-green-600', bg: 'bg-green-50 dark:bg-green-900/20' },
    { label: 'Taux facturable', value: `${billableRate}%`, icon: 'pi-percentage', color: 'text-purple-600', bg: 'bg-purple-50 dark:bg-purple-900/20' },
    { label: 'Heures sur-budget', value: `${overBudgetHours}h`, icon: 'pi-exclamation-circle', color: overBudgetHours > 0 ? 'text-red-500' : 'text-gray-400', bg: overBudgetHours > 0 ? 'bg-red-50 dark:bg-red-900/20' : 'bg-gray-50 dark:bg-gray-800' },
  ]
})

const weekDays = [
  { key: 'lun', label: 'Lun 19' },
  { key: 'mar', label: 'Mar 20' },
  { key: 'mer', label: 'Mer 21' },
  { key: 'jeu', label: 'Jeu 22' },
  { key: 'ven', label: 'Ven 23' },
  { key: 'sam', label: 'Sam 24' },
  { key: 'dim', label: 'Dim 25' },
]

const weeklyData = ref([
  { person: 'Amara Diallo', hours: { lun: 2, mar: 1.5, mer: 0, jeu: 3, ven: 4, sam: 0, dim: 0 } },
  { person: 'Fatou Ndiaye', hours: { lun: 7, mar: 8, mer: 2, jeu: 5, ven: 6, sam: 0, dim: 0 } },
  { person: 'Kofi Mensah', hours: { lun: 4, mar: 0, mer: 2, jeu: 0, ven: 5, sam: 0, dim: 0 } },
  { person: 'Bintou Keïta', hours: { lun: 0, mar: 6, mer: 7.5, jeu: 0, ven: 4, sam: 0, dim: 0 } },
])

function resetFilters() {
  filterProject.value = null
  filterPerson.value = null
  filterDateFrom.value = ''
  filterDateTo.value = ''
}

function logTime() {
  timeEntries.value.unshift({
    id: timeEntries.value.length + 1,
    person: 'Amara Diallo',
    project: logForm.value.project ?? '',
    task: logForm.value.task,
    date: logForm.value.date,
    hours: parseFloat(logForm.value.hours) || 0,
    billable: logForm.value.billable,
    note: logForm.value.note,
  })
  showLogDialog.value = false
  logForm.value = { project: null, task: '', date: '', hours: '', note: '', billable: true }
}

function editEntry(entry: TimeEntry) {
  logForm.value = {
    project: entry.project,
    task: entry.task,
    date: entry.date,
    hours: String(entry.hours),
    note: entry.note,
    billable: entry.billable,
  }
  showLogDialog.value = true
}

function deleteEntry(id: number) {
  timeEntries.value = timeEntries.value.filter(e => e.id !== id)
}

function exportToInvoice() {
  alert('Export vers facture en cours de développement — intégration module Accounting à venir.')
}
</script>
