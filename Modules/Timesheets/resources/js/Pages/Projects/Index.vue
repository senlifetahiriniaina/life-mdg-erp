<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50 text-surface-900 dark:text-surface-50">Tracking Projects</h1>
        <Button
          label="New Project"
          icon="pi pi-plus"
          class="p-button-primary"
          @click="openCreate"
        />
      </div>
    </template>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <Card
        v-for="project in projects"
        :key="project.id"
        class="bg-surface-0 dark:bg-surface-800"
      >
        <template #header>
          <div class="flex items-center justify-between p-4">
            <div>
              <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50">{{ project.name }}</h3>
              <p class="text-sm text-surface-600 dark:text-surface-400">{{ project.code }}</p>
            </div>
            <Badge :value="project.status" :severity="getStatusSeverity(project.status)" />
          </div>
        </template>
        <template #content>
          <div class="space-y-4">
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400 mb-2">Budget Usage</p>
              <ProgressBar :value="project.percentage_used" show-value />
              <p class="text-xs text-surface-600 dark:text-surface-400 mt-1">
                {{ project.hours_tracked }} / {{ project.budget_hours }} hours
              </p>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <p class="text-xs text-surface-600 dark:text-surface-400">Start Date</p>
                <p class="text-sm font-medium text-surface-900 dark:text-surface-50">{{ formatDate(project.start_date) }}</p>
              </div>
              <div>
                <p class="text-xs text-surface-600 dark:text-surface-400">End Date</p>
                <p class="text-sm font-medium text-surface-900 dark:text-surface-50">{{ formatDate(project.end_date) }}</p>
              </div>
            </div>
            <div v-if="project.description" class="border-t pt-4">
              <p class="text-sm text-surface-700 dark:text-surface-300">{{ project.description }}</p>
            </div>
          </div>
        </template>
        <template #footer>
          <div class="flex gap-2">
            <Button
              label="View Details"
              class="p-button-text p-button-sm flex-1"
              @click="viewProject(project)"
            />
            <Button
              label="Edit"
              icon="pi pi-pencil"
              class="p-button-text p-button-sm"
              @click="openEdit(project)"
            />
            <Button
              icon="pi pi-trash"
              class="p-button-text p-button-danger p-button-sm"
              @click="deleteProject(project.id)"
            />
          </div>
        </template>
      </Card>
    </div>

    <div v-if="projects.length === 0" class="text-center py-12">
      <p class="text-surface-500 dark:text-surface-400 mb-4">No tracking projects yet</p>
      <Button
        label="Create Your First Project"
        icon="pi pi-plus"
        class="p-button-primary"
        @click="openCreate"
      />
    </div>

    <!-- Create/Edit Modal -->
    <!--
      Chantier 19 (Lot 2): the "New Project"/"View Details"/"Edit" buttons
      used to navigate to /timesheets/projects/new, /{id}, /{id}/edit —
      none of which have ever had a web route or a Vue page anywhere in
      this module, a guaranteed 404 dead end on every click. Rewired onto
      an in-page modal reusing the already-real store methods
      (addProject/updateProjectInStore/deleteProjectFromStore), matching
      the modal-based list+CRUD pattern already used elsewhere in this
      session (e.g. Inventory's Categories/Index.vue) rather than building
      3 new pages + routes for a feature that never needed them.
    -->
    <Dialog v-model:visible="showForm" :header="editingProject ? 'Edit Project' : 'New Project'" modal class="w-full max-w-lg">
      <form class="space-y-4" @submit.prevent="submitForm">
        <div>
          <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Name *</label>
          <InputText v-model="form.name" class="w-full" required />
        </div>
        <div>
          <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Code *</label>
          <InputText v-model="form.code" class="w-full" required />
        </div>
        <div>
          <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Description</label>
          <Textarea v-model="form.description" class="w-full" rows="3" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Budget Hours *</label>
            <InputNumber v-model="form.budget_hours" class="w-full" :min="1" required />
          </div>
          <div v-if="editingProject">
            <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Status</label>
            <Dropdown v-model="form.status" :options="statusOptions" option-label="label" option-value="value" class="w-full" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Start Date *</label>
            <input v-model="form.start_date" type="date" required class="w-full px-3 py-2 border border-gray-300 dark:border-surface-600 rounded-lg" />
          </div>
          <div>
            <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">End Date *</label>
            <input v-model="form.end_date" type="date" required class="w-full px-3 py-2 border border-gray-300 dark:border-surface-600 rounded-lg" />
          </div>
        </div>
        <p v-if="formError" class="text-sm text-red-700 dark:text-red-300">{{ formError }}</p>
        <div class="flex justify-end gap-2 pt-2">
          <Button label="Cancel" class="p-button-text" type="button" @click="showForm = false" />
          <Button :label="editingProject ? 'Save' : 'Create'" type="submit" :loading="saving" />
        </div>
      </form>
    </Dialog>

    <!-- View Details Modal -->
    <Dialog v-model:visible="showDetails" header="Project Details" modal class="w-full max-w-lg">
      <div v-if="viewingProject" class="space-y-3">
        <div>
          <p class="text-xs text-surface-500 dark:text-surface-400">Name</p>
          <p class="font-semibold">{{ viewingProject.name }}</p>
        </div>
        <div>
          <p class="text-xs text-surface-500 dark:text-surface-400">Code</p>
          <p>{{ viewingProject.code }}</p>
        </div>
        <div v-if="viewingProject.description">
          <p class="text-xs text-surface-500 dark:text-surface-400">Description</p>
          <p>{{ viewingProject.description }}</p>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <p class="text-xs text-surface-500 dark:text-surface-400">Start Date</p>
            <p>{{ formatDate(viewingProject.start_date) }}</p>
          </div>
          <div>
            <p class="text-xs text-surface-500 dark:text-surface-400">End Date</p>
            <p>{{ formatDate(viewingProject.end_date) }}</p>
          </div>
        </div>
        <div>
          <p class="text-xs text-surface-500 dark:text-surface-400 mb-1">Budget Usage</p>
          <ProgressBar :value="viewingProject.percentage_used" show-value />
          <p class="text-xs text-surface-600 dark:text-surface-400 mt-1">
            {{ viewingProject.hours_tracked }} / {{ viewingProject.budget_hours }} hours
          </p>
        </div>
      </div>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useTimesheetsStore } from '../../stores/timesheetsStore'
import AppLayout from '@/Layouts/AppLayout.vue'
import Card from 'primevue/card'
import Badge from 'primevue/badge'
import Button from 'primevue/button'
import ProgressBar from 'primevue/progressbar'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import Dropdown from 'primevue/dropdown'

const store = useTimesheetsStore()

const projects = computed(() => store.projects)

const statusOptions = [
  { label: 'Active', value: 'active' },
  { label: 'Paused', value: 'paused' },
  { label: 'Completed', value: 'completed' },
  { label: 'Archived', value: 'archived' },
]

const getStatusSeverity = (status: string) => {
  const severities: Record<string, string> = {
    active: 'success',
    paused: 'warning',
    completed: 'info',
    archived: 'secondary',
  }
  return severities[status] || 'info'
}

const formatDate = (date: string) => {
  if (!date) return '—'
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

const showForm = ref(false)
const showDetails = ref(false)
const editingProject = ref<any>(null)
const viewingProject = ref<any>(null)
const saving = ref(false)
const formError = ref('')

const emptyForm = () => ({
  name: '',
  code: '',
  description: '',
  budget_hours: 40,
  status: 'active',
  start_date: new Date().toISOString().split('T')[0],
  end_date: new Date().toISOString().split('T')[0],
})

const form = reactive(emptyForm())

const openCreate = () => {
  editingProject.value = null
  Object.assign(form, emptyForm())
  formError.value = ''
  showForm.value = true
}

const openEdit = (project: any) => {
  editingProject.value = project
  Object.assign(form, {
    name: project.name,
    code: project.code,
    description: project.description || '',
    budget_hours: project.budget_hours,
    status: project.status,
    start_date: project.start_date ? project.start_date.substring(0, 10) : '',
    end_date: project.end_date ? project.end_date.substring(0, 10) : '',
  })
  formError.value = ''
  showForm.value = true
}

const viewProject = (project: any) => {
  viewingProject.value = project
  showDetails.value = true
}

const submitForm = async () => {
  saving.value = true
  formError.value = ''
  try {
    if (editingProject.value) {
      await store.updateProjectInStore(editingProject.value.id, { ...form })
    } else {
      await store.addProject({ ...form })
    }
    showForm.value = false
  } catch (error: any) {
    formError.value = error?.response?.data?.message || 'Error saving project'
  } finally {
    saving.value = false
  }
}

const deleteProject = async (id: number) => {
  if (confirm('Are you sure you want to delete this project?')) {
    await store.deleteProjectFromStore(id)
  }
}

onMounted(async () => {
  await store.loadProjects()
})
</script>
