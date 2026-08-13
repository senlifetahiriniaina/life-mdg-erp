<template>
  <AppLayout>
    <template #header>
      <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">
        {{ entry ? 'Edit Time Entry' : 'New Time Entry' }}
      </h1>
    </template>

    <div class="max-w-2xl bg-white dark:bg-surface-800 rounded-lg shadow-sm p-6">
      <form @submit.prevent="submitForm" class="space-y-6">
        <!-- Work Date -->
        <div>
          <label for="date" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">Date *</label>
          <input id="date"
            v-model="form.work_date"
            type="date"
            required
            class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          />
        </div>

        <!-- Task Description -->
        <div>
          <label for="task-description" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">Task Description *</label>
          <textarea id="task-description"
            v-model="form.task_description"
            required
            rows="3"
            placeholder="Describe the work performed..."
            class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          />
        </div>

        <!-- Project -->
        <div>
          <label for="project" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">Project</label>
          <select id="project"
            v-model="form.project_id"
            class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          >
            <option value="">No Project</option>
            <option v-for="project in projects" :key="project.id" :value="project.id">
              {{ project.name }}
            </option>
          </select>
        </div>

        <!-- Hours -->
        <div>
          <label for="hours" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">Hours *</label>
          <input id="hours"
            v-model.number="form.hours"
            type="number"
            required
            step="0.25"
            min="0.25"
            max="24"
            class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          />
          <p class="text-sm text-surface-600 dark:text-surface-400 mt-1">0.25 hour = 15 minutes</p>
        </div>

        <!-- Billable -->
        <div>
          <label class="flex items-center gap-2">
            <input
              v-model="form.billable"
              type="checkbox"
              class="rounded border-gray-300 dark:border-surface-600 text-primary-700 dark:text-primary-300 focus:ring-blue-500"
            />
            <span class="text-sm font-medium text-surface-700 dark:text-surface-300">Mark as Billable</span>
          </label>
        </div>

        <!-- Rate -->
        <div v-if="form.billable">
          <label for="hourly-rate" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">Hourly Rate</label>
          <input id="hourly-rate"
            v-model.number="form.rate"
            type="number"
            step="0.01"
            min="0"
            placeholder="Billable rate per hour"
            class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          />
          <p v-if="form.hours && form.rate" class="text-sm text-surface-600 dark:text-surface-400 mt-1">
            Total: ${{ (form.hours * form.rate).toFixed(2) }}
          </p>
        </div>

        <!-- Notes -->
        <div>
          <label for="notes" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">Notes</label>
          <textarea id="notes"
            v-model="form.notes"
            rows="3"
            placeholder="Additional notes (optional)..."
            class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          />
        </div>

        <!-- Form Actions -->
        <div class="flex gap-3 pt-6 border-t">
          <button
            type="submit"
            :disabled="loading"
            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 transition"
          >
            {{ entry ? 'Update' : 'Create' }} Entry
          </button>
          <Link
            :href="entry ? '/timesheets/entries' : '/timesheets/entries'"
            class="px-6 py-2 bg-gray-200 text-gray-800 dark:text-surface-100 rounded-lg hover:bg-gray-300 transition"
          >
            Cancel
          </Link>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  entry: Object,
  projects: Array,
})

const loading = ref(false)
const projects = ref(props.projects || [])

const form = reactive({
  work_date: props.entry?.work_date || new Date().toISOString().split('T')[0],
  task_description: props.entry?.task_description || '',
  project_id: props.entry?.project_id || '',
  hours: props.entry?.hours || 1,
  billable: props.entry?.billable ?? true,
  rate: props.entry?.rate || null,
  notes: props.entry?.notes || '',
})

const submitForm = async () => {
  loading.value = true
  try {
    const data = {
      work_date: form.work_date,
      task_description: form.task_description,
      project_id: form.project_id || null,
      hours: form.hours,
      billable: form.billable,
      rate: form.rate,
      notes: form.notes,
    }

    if (props.entry) {
      await axios.put(`/api/v1/timesheets/entries/${props.entry.id}`, data)
      router.get('/timesheets/entries')
    } else {
      await axios.post('/api/v1/timesheets/entries', data)
      router.get('/timesheets/entries')
    }
  } catch (error) {
    console.error('Error saving entry:', error)
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  if (!projects.value || projects.value.length === 0) {
    try {
      const response = await axios.get('/api/v1/projects/projects?per_page=999')
      projects.value = response.data.data
    } catch (error) {
      console.error('Error loading projects:', error)
    }
  }
})
</script>
