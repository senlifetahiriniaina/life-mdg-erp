<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50 text-surface-900 dark:text-surface-50">Tracking Projects</h1>
        <Button
          label="New Project"
          icon="pi pi-plus"
          class="p-button-primary"
          @click="navigateTo('/timesheets/projects/new')"
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
              @click="viewProject(project.id)"
            />
            <Button
              label="Edit"
              icon="pi pi-pencil"
              class="p-button-text p-button-sm"
              @click="editProject(project.id)"
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
        @click="navigateTo('/timesheets/projects/new')"
      />
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useTimesheetsStore } from '../../stores/timesheetsStore'
import AppLayout from '@/Layouts/AppLayout.vue'
import Card from 'primevue/card'
import Badge from 'primevue/badge'
import Button from 'primevue/button'
import ProgressBar from 'primevue/progressbar'

const router = useRouter()
const store = useTimesheetsStore()

const projects = computed(() => store.projects)

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
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

const navigateTo = (path: string) => {
  router.push(path)
}

const viewProject = (id: number) => {
  router.push(`/timesheets/projects/${id}`)
}

const editProject = (id: number) => {
  router.push(`/timesheets/projects/${id}/edit`)
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
