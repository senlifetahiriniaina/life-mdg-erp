<template>
  <AppLayout>
    <Head :title="isEdit ? $t('common.edit') + ' ' + $t('hr.employee') : $t('common.new') + ' ' + $t('hr.employee')" />

    <!-- Chantier 32.17 (HR deep 14-layer audit): this real, routed page
         never called useAiAssistant() at all before this fix. Reuses the
         already-registered 'create_employee' action for both create and
         edit — same form, same real guidance. -->
    <AIAssistantPanel v-if="showAiPanel" :guidance="guidance" @close="showAiPanel = false" />

    <div class="max-w-4xl mx-auto space-y-6">
      <!-- Page header -->
      <div class="flex items-center gap-4">
        <Button
          icon="pi pi-arrow-left"
          text
          rounded
          severity="secondary"
          @click="router.visit(route('hr.employees.index'))"
        />
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">
            {{ isEdit ? $t('common.edit') + ' ' + $t('hr.employee') : $t('common.new') + ' ' + $t('hr.employee') }}
          </h1>
        </div>
      </div>

      <form @submit.prevent="submit" class="space-y-6">
        <!-- Personal Information -->
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
          <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-5">Personal Information</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="flex flex-col gap-2">
              <label class="text-sm font-medium text-surface-700 dark:text-surface-200">
                {{ $t('hr.fields.first_name') }} <span class="text-error-600">*</span>
              </label>
              <InputText v-model="form.first_name" :invalid="!!errors.first_name" />
              <small v-if="errors.first_name" class="text-error-600">{{ errors.first_name }}</small>
            </div>
            <div class="flex flex-col gap-2">
              <label class="text-sm font-medium text-surface-700 dark:text-surface-200">
                {{ $t('hr.fields.last_name') }} <span class="text-red-500">*</span>
              </label>
              <InputText v-model="form.last_name" :invalid="!!errors.last_name" />
              <small v-if="errors.last_name" class="text-red-500">{{ errors.last_name }}</small>
            </div>
            <div class="flex flex-col gap-2">
              <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Email <span class="text-red-500">*</span></label>
              <InputText v-model="form.email" type="email" :invalid="!!errors.email" />
              <small v-if="errors.email" class="text-red-500">{{ errors.email }}</small>
            </div>
            <div class="flex flex-col gap-2">
              <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Phone</label>
              <InputText v-model="form.phone" />
            </div>
            <div class="flex flex-col gap-2">
              <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Gender</label>
              <Select
                v-model="form.gender"
                :options="genderOptions"
                option-label="label"
                option-value="value"
                show-clear
                placeholder="Select gender"
              />
            </div>
            <div class="flex flex-col gap-2">
              <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Date of Birth</label>
              <DatePicker v-model="form.date_of_birth" date-format="yy-mm-dd" show-button-bar />
            </div>
          </div>
        </div>

        <!-- Employment Information -->
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
          <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-5">Employment Information</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="flex flex-col gap-2">
              <label class="text-sm font-medium text-surface-700 dark:text-surface-200">
                {{ $t('hr.fields.employee_number') }} <span class="text-red-500">*</span>
              </label>
              <InputText v-model="form.employee_number" :invalid="!!errors.employee_number" />
              <small v-if="errors.employee_number" class="text-red-500">{{ errors.employee_number }}</small>
            </div>
            <div class="flex flex-col gap-2">
              <label class="text-sm font-medium text-surface-700 dark:text-surface-200">
                {{ $t('hr.fields.hire_date') }} <span class="text-red-500">*</span>
              </label>
              <DatePicker v-model="form.hire_date" date-format="yy-mm-dd" show-button-bar :invalid="!!errors.hire_date" />
              <small v-if="errors.hire_date" class="text-red-500">{{ errors.hire_date }}</small>
            </div>
            <div class="flex flex-col gap-2">
              <label class="text-sm font-medium text-surface-700 dark:text-surface-200">{{ $t('hr.fields.department') }}</label>
              <Select
                v-model="form.department_id"
                :options="departments"
                option-label="name"
                option-value="id"
                show-clear
                :placeholder="'Select ' + $t('hr.fields.department')"
                @change="onDepartmentChange"
              />
            </div>
            <div class="flex flex-col gap-2">
              <label class="text-sm font-medium text-surface-700 dark:text-surface-200">{{ $t('hr.fields.job_position') }}</label>
              <Select
                v-model="form.job_position_id"
                :options="filteredPositions"
                option-label="title"
                option-value="id"
                show-clear
                :placeholder="'Select ' + $t('hr.fields.job_position')"
                :disabled="!form.department_id"
              />
            </div>
            <div class="flex flex-col gap-2">
              <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Manager</label>
              <Select
                v-model="form.manager_id"
                :options="managers"
                option-label="full_name"
                option-value="id"
                show-clear
                placeholder="Select manager"
                filter
              />
            </div>
            <div class="flex flex-col gap-2">
              <label class="text-sm font-medium text-surface-700 dark:text-surface-200">{{ $t('hr.fields.employment_type') }}</label>
              <Select
                v-model="form.employment_type"
                :options="employmentTypeOptions"
                option-label="label"
                option-value="value"
                show-clear
                :placeholder="'Select ' + $t('hr.fields.employment_type')"
              />
            </div>
            <div class="flex flex-col gap-2">
              <label class="text-sm font-medium text-surface-700 dark:text-surface-200">{{ $t('hr.fields.status') }}</label>
              <Select
                v-model="form.status"
                :options="statusOptions"
                option-label="label"
                option-value="value"
                show-clear
                :placeholder="'Select ' + $t('hr.fields.status')"
              />
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3">
          <Button
            type="button"
            :label="$t('common.cancel')"
            severity="secondary"
            outlined
            @click="router.visit(route('hr.employees.index'))"
          />
          <Button
            type="submit"
            :label="$t('common.save')"
            icon="pi pi-check"
            :loading="saving"
          />
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'

const props = defineProps({
  employee: { type: Object, default: null },
})

const { t } = useI18n()

const isEdit = computed(() => !!props.employee)

// Chantier 32.17 (HR deep 14-layer audit): see the AIAssistantPanel comment
// in the template — this page never called useAiAssistant() at all before.
const showAiPanel = ref(true)
const { guidance } = useAiAssistant('HR', 'create_employee')

const form = reactive({
  first_name: props.employee?.first_name ?? '',
  last_name: props.employee?.last_name ?? '',
  email: props.employee?.email ?? '',
  phone: props.employee?.phone ?? '',
  gender: props.employee?.gender ?? null,
  date_of_birth: props.employee?.date_of_birth ?? null,
  employee_number: props.employee?.employee_number ?? '',
  hire_date: props.employee?.hire_date ?? null,
  department_id: props.employee?.department?.id ?? null,
  job_position_id: props.employee?.job_position?.id ?? null,
  manager_id: props.employee?.manager?.id ?? null,
  employment_type: props.employee?.employment_type ?? null,
  status: props.employee?.status ?? 'active',
})

const errors = reactive({})
const saving = ref(false)

const departments = ref([])
const jobPositions = ref([])
const managers = ref([])

const filteredPositions = computed(() => {
  if (!form.department_id) return []
  return jobPositions.value.filter((p) => p.department_id === form.department_id)
})

const genderOptions = [
  { label: 'Male', value: 'male' },
  { label: 'Female', value: 'female' },
  { label: 'Other', value: 'other' },
]

const statusOptions = [
  { label: t('hr.statuses.active'), value: 'active' },
  { label: t('hr.statuses.on_probation'), value: 'on_probation' },
  { label: t('hr.statuses.terminated'), value: 'terminated' },
]

const employmentTypeOptions = [
  { label: t('hr.employment_types.full_time'), value: 'full_time' },
  { label: t('hr.employment_types.part_time'), value: 'part_time' },
  { label: t('hr.employment_types.contract'), value: 'contract' },
]

const onDepartmentChange = () => {
  form.job_position_id = null
}

const formatDateForApi = (val) => {
  if (!val) return null
  if (typeof val === 'string') return val
  const d = new Date(val)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

const submit = async () => {
  saving.value = true
  Object.keys(errors).forEach((k) => delete errors[k])

  const payload = {
    ...form,
    hire_date: formatDateForApi(form.hire_date),
    date_of_birth: formatDateForApi(form.date_of_birth),
  }

  try {
    if (isEdit.value) {
      await axios.put(`/api/v1/hr/employees/${props.employee.id}`, payload)
    } else {
      await axios.post('/api/v1/hr/employees', payload)
    }
    router.visit(route('hr.employees.index'))
  } catch (e) {
    if (e.response?.status === 422) {
      Object.assign(errors, e.response.data.errors)
    }
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  const [deptRes, posRes, empRes] = await Promise.all([
    axios.get('/api/v1/hr/departments', { params: { is_active: true, per_page: 200 } }),
    axios.get('/api/v1/hr/job-positions', { params: { is_active: true, per_page: 200 } }),
    axios.get('/api/v1/hr/employees', { params: { status: 'active', per_page: 200 } }),
  ])
  departments.value = deptRes.data.data
  jobPositions.value = posRes.data.data
  managers.value = empRes.data.data
})
</script>
