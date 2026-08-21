<template>
  <AppLayout>
    <template #header>
      <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50 text-surface-900 dark:text-surface-50">
        {{ sheet ? 'Edit Timesheet' : 'Create Timesheet' }}
      </h1>
    </template>

    <AIAssistantPanel v-if="guidance" :guidance="guidance" />

    <div class="max-w-2xl bg-surface-0 dark:bg-surface-800 rounded-lg shadow-sm p-6">
      <form @submit.prevent="submitForm" class="space-y-6">
        <!-- Period Start -->
        <div>
          <label for="period-start-date" class="block text-sm font-medium text-surface-900 dark:text-surface-50 text-surface-700 dark:text-surface-300 mb-2">Period Start Date *</label>
          <input id="period-start-date"
            v-model="form.period_start"
            type="date"
            required
            class="w-full px-4 py-2 border border-surface-300 dark:border-surface-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          />
        </div>

        <!-- Period End -->
        <div>
          <label for="period-end-date" class="block text-sm font-medium text-surface-900 dark:text-surface-50 text-surface-700 dark:text-surface-300 mb-2">Period End Date *</label>
          <input id="period-end-date"
            v-model="form.period_end"
            type="date"
            required
            class="w-full px-4 py-2 border border-surface-300 dark:border-surface-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          />
          <p v-if="periodDays" class="text-sm text-surface-600 dark:text-surface-400 mt-1">{{ periodDays }} days</p>
        </div>

        <!-- Employee (if admin) -->
        <div v-if="isAdmin">
          <label for="employee" class="block text-sm font-medium text-surface-900 dark:text-surface-50 text-surface-700 dark:text-surface-300 mb-2">Employee</label>
          <select id="employee"
            v-model="form.employee_id"
            required
            class="w-full px-4 py-2 border border-surface-300 dark:border-surface-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          >
            <option value="">Select an employee...</option>
            <option v-for="emp in employees" :key="emp.id" :value="emp.id">
              {{ emp.name }}
            </option>
          </select>
        </div>

        <!-- Period Summary -->
        <div v-if="form.period_start && form.period_end" class="bg-primary-50 dark:bg-primary-900/20 rounded-lg p-4">
          <p class="text-sm font-semibold text-blue-900 mb-2">Period Summary</p>
          <p class="text-sm text-blue-800">
            From {{ formatDate(form.period_start) }} to {{ formatDate(form.period_end) }}
          </p>
        </div>

        <!-- Info Message -->
        <div class="bg-amber-50 rounded-lg p-4 border border-amber-200">
          <p class="text-sm text-amber-800">
            <strong>Note:</strong> After creating this timesheet, you can add individual time entries for each day within this period.
          </p>
        </div>

        <!-- Form Actions -->
        <div class="flex gap-3 pt-6 border-t">
          <button
            type="submit"
            :disabled="loading"
            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 transition"
          >
            {{ sheet ? 'Update' : 'Create' }} Timesheet
          </button>
          <Link
            :href="sheet ? `/timesheets/sheets/${sheet.id}` : '/timesheets/sheets'"
            class="px-6 py-2 bg-gray-200 text-surface-800 dark:text-surface-100 rounded-lg hover:bg-gray-300 transition"
          >
            Cancel
          </Link>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'

const { guidance } = useAiAssistant('Timesheets', 'manage_sheets')

const props = defineProps({
  sheet: Object,
  employees: Array,
})

const page = usePage()
const user = page.props.auth.user
const loading = ref(false)
const employees = ref(props.employees || [])

const isAdmin = computed(() => user.roles?.some(r => r === 'admin'))

const form = reactive({
  period_start: props.sheet?.period_start || '',
  period_end: props.sheet?.period_end || '',
  employee_id: props.sheet?.employee_id || user.id || '',
})

const periodDays = computed(() => {
  if (!form.period_start || !form.period_end) return null
  const start = new Date(form.period_start)
  const end = new Date(form.period_end)
  const days = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1
  return days > 0 ? days : null
})

const formatDate = (date) => {
  return new Date(date).toLocaleDateString()
}

const submitForm = async () => {
  if (new Date(form.period_start) > new Date(form.period_end)) {
    alert('Period start date must be before period end date')
    return
  }

  loading.value = true
  try {
    const data = {
      period_start: form.period_start,
      period_end: form.period_end,
    }
    if (isAdmin.value) {
      data.employee_id = form.employee_id
    }

    if (props.sheet) {
      await axios.put(`/api/v1/timesheets/sheets/${props.sheet.id}`, data)
      router.get(`/timesheets/sheets/${props.sheet.id}`)
    } else {
      const response = await axios.post('/api/v1/timesheets/sheets', data)
      router.get(`/timesheets/sheets/${response.data.id}`)
    }
  } catch (error) {
    console.error('Error saving timesheet:', error)
    alert('Error saving timesheet: ' + (error.response?.data?.message || error.message))
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  if (isAdmin.value && (!employees.value || employees.value.length === 0)) {
    try {
      const response = await axios.get('/api/v1/hr/employees?per_page=999')
      employees.value = response.data.data
    } catch (error) {
      console.error('Error loading employees:', error)
    }
  }
})
</script>
