<template>
  <AppLayout>
    <template #header>
      <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">My Timesheets</h1>
    </template>

    <AIAssistantPanel v-if="guidance" :guidance="guidance" />

    <div class="bg-white dark:bg-surface-800 shadow-sm rounded-lg p-6">
      <!-- Stats -->
      <div class="grid grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-4">
          <p class="text-sm text-surface-600 dark:text-surface-400">Total</p>
          <p class="text-3xl font-bold text-primary-700 dark:text-primary-300">{{ stats.total }}</p>
        </div>
        <div class="bg-gradient-to-br from-amber-50 to-amber-100 rounded-lg p-4">
          <p class="text-sm text-surface-600 dark:text-surface-400">Draft</p>
          <p class="text-3xl font-bold text-amber-600">{{ stats.draft }}</p>
        </div>
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-4">
          <p class="text-sm text-surface-600 dark:text-surface-400">Pending Review</p>
          <p class="text-3xl font-bold text-primary-700 dark:text-primary-300">{{ stats.submitted }}</p>
        </div>
        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg p-4">
          <p class="text-sm text-surface-600 dark:text-surface-400">Approved</p>
          <p class="text-3xl font-bold text-green-700 dark:text-green-300">{{ stats.approved }}</p>
        </div>
      </div>

      <!-- Actions -->
      <div class="mb-6">
        <button
          @click="createNew"
          class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
        >
          New Timesheet
        </button>
      </div>

      <!-- Timesheets List -->
      <div v-if="sheets.length === 0" class="text-center py-12 text-surface-500 dark:text-surface-400">
        <p class="text-lg">No timesheets yet</p>
        <button
          @click="createNew"
          class="mt-4 text-primary-700 dark:text-primary-300 hover:text-blue-800 font-semibold"
        >
          Create your first timesheet
        </button>
      </div>

      <div v-else class="space-y-4">
        <div
          v-for="sheet in sheets"
          :key="sheet.id"
          class="border rounded-lg p-4 hover:bg-gray-50 dark:bg-surface-800 transition"
        >
          <div class="flex items-center justify-between">
            <div class="flex-1">
              <div class="flex items-center gap-3 mb-2">
                <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50">
                  {{ formatDate(sheet.period_start) }} - {{ formatDate(sheet.period_end) }}
                </h3>
                <Tag :value="sheet.status" :severity="statusSeverity(sheet.status)" />
              </div>
              <p class="text-sm text-surface-600 dark:text-surface-400">
                {{ sheet.total_hours }} total hours • {{ sheet.billable_hours }} billable hours
              </p>
              <div v-if="sheet.submitted_at" class="text-xs text-surface-500 dark:text-surface-400 mt-1">
                Submitted on {{ formatDate(sheet.submitted_at) }}
              </div>
              <div v-if="sheet.approved_at" class="text-xs text-green-700 dark:text-green-300 mt-1">
                Approved on {{ formatDate(sheet.approved_at) }}
              </div>
              <div v-if="sheet.rejected_reason" class="text-xs text-red-700 dark:text-red-300 mt-1">
                Rejected: {{ sheet.rejected_reason }}
              </div>
            </div>

            <div class="flex gap-2">
              <Link
                :href="`/timesheets/sheets/${sheet.id}`"
                class="px-4 py-2 bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300 rounded-lg hover:bg-blue-100 transition"
              >
                View
              </Link>
              <Link
                v-if="sheet.status === 'draft'"
                :href="`/timesheets/sheets/${sheet.id}/edit`"
                class="px-4 py-2 bg-amber-50 text-amber-600 rounded-lg hover:bg-amber-100 transition"
              >
                Edit
              </Link>
              <button
                v-if="sheet.status === 'draft'"
                @click="submitSheet(sheet.id)"
                class="px-4 py-2 bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300 rounded-lg hover:bg-green-100 transition"
              >
                Submit
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Tag from 'primevue/tag'
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'

const { guidance } = useAiAssistant('Timesheets', 'manage_sheets')

const sheets = ref([])
const loading = ref(false)

const stats = reactive({
  total: 0,
  draft: 0,
  submitted: 0,
  approved: 0,
})

const formatDate = (date) => {
  return new Date(date).toLocaleDateString()
}

const statusSeverity = (status) => {
  return {
    draft: 'warning',
    submitted: 'info',
    approved: 'success',
    rejected: 'danger',
  }[status] || 'secondary'
}

const loadSheets = async () => {
  loading.value = true
  try {
    const response = await axios.get('/api/v1/timesheets/sheets/my-sheets', {
      params: { per_page: 100 }
    })
    sheets.value = response.data.data

    stats.total = sheets.value.length
    stats.draft = sheets.value.filter(s => s.status === 'draft').length
    stats.submitted = sheets.value.filter(s => s.status === 'submitted').length
    stats.approved = sheets.value.filter(s => s.status === 'approved').length
  } catch (error) {
    console.error('Error loading sheets:', error)
  } finally {
    loading.value = false
  }
}

const createNew = () => {
  router.get('/timesheets/sheets/create')
}

const submitSheet = async (id) => {
  if (!confirm('Submit this timesheet for approval?')) return
  try {
    await axios.post(`/api/v1/timesheets/sheets/${id}/submit`)
    loadSheets()
  } catch (error) {
    console.error('Error submitting sheet:', error)
  }
}

onMounted(() => {
  loadSheets()
})
</script>
