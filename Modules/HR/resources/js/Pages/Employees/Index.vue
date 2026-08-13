<template>
  <AppLayout>
    <Head title="Employees" />

    <div class="space-y-6">
      <!-- Page header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50 text-surface-900 dark:text-surface-50">
            {{ $t('hr.employees') }}
          </h1>
          <p class="text-surface-500 text-sm mt-1">
            {{ total }} {{ $t('hr.employees').toLowerCase() }} {{ $t('common.total') }}
          </p>
        </div>
        <Button
          icon="pi pi-plus"
          :label="$t('common.new') + ' ' + $t('hr.employee')"
          @click="router.visit(route('hr.employees.create'))"
        />
      </div>

      <!-- Filters -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-4">
        <div class="flex flex-wrap gap-3">
          <InputText
            v-model="filters.search"
            :placeholder="$t('common.search') + '...'"
            class="w-64"
            @input="debounceLoad"
          />
          <Select
            v-model="filters.department_id"
            :options="departments"
            option-label="name"
            option-value="id"
            :placeholder="$t('hr.fields.department')"
            show-clear
            class="w-48"
            @change="loadEmployees"
          />
          <Select
            v-model="filters.status"
            :options="statusOptions"
            option-label="label"
            option-value="value"
            :placeholder="$t('hr.fields.status')"
            show-clear
            class="w-48"
            @change="loadEmployees"
          />
          <Select
            v-model="filters.employment_type"
            :options="employmentTypeOptions"
            option-label="label"
            option-value="value"
            :placeholder="$t('hr.fields.employment_type')"
            show-clear
            class="w-48"
            @change="loadEmployees"
          />
          <Button
            icon="pi pi-times"
            :label="$t('common.clear')"
            severity="secondary"
            outlined
            @click="clearFilters"
          />
        </div>
      </div>

      <!-- DataTable with Virtual Scrolling -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 overflow-hidden">
        <DataTable
          :value="employees"
          :loading="loading"
          row-hover
          class="rounded-xl"
          scroll-height="600px"
          scroll-direction="vertical"
        >
          <Column field="employee_number" :header="$t('hr.fields.employee_number')" sortable style="min-width: 130px" />
          <Column :header="$t('hr.fields.first_name') + ' / ' + $t('hr.fields.last_name')" style="min-width: 180px">
            <template #body="{ data }">
              <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center flex-shrink-0">
                  <span class="text-xs font-semibold text-primary-700 dark:text-primary-300">
                    {{ data.full_name.charAt(0) }}
                  </span>
                </div>
                <div>
                  <p class="font-medium text-surface-900 dark:text-surface-50 leading-tight">{{ data.full_name }}</p>
                  <p class="text-xs text-surface-400">{{ data.email }}</p>
                </div>
              </div>
            </template>
          </Column>
          <Column :header="$t('hr.fields.department')" style="min-width: 140px">
            <template #body="{ data }">
              <span v-if="data.department" class="text-sm">{{ data.department.name }}</span>
              <span v-else class="text-surface-300 text-sm">—</span>
            </template>
          </Column>
          <Column :header="$t('hr.fields.job_position')" style="min-width: 160px">
            <template #body="{ data }">
              <span v-if="data.job_position" class="text-sm">{{ data.job_position.title }}</span>
              <span v-else class="text-surface-300 text-sm">—</span>
            </template>
          </Column>
          <Column :header="$t('hr.fields.employment_type')" style="min-width: 140px">
            <template #body="{ data }">
              <Tag :value="$t('hr.employment_types.' + data.employment_type)" :severity="employmentTypeSeverity(data.employment_type)" />
            </template>
          </Column>
          <Column :header="$t('hr.fields.status')" style="min-width: 120px">
            <template #body="{ data }">
              <Tag :value="$t('hr.statuses.' + data.status)" :severity="statusSeverity(data.status)" />
            </template>
          </Column>
          <Column :header="$t('hr.fields.hire_date')" style="min-width: 120px">
            <template #body="{ data }">
              {{ data.hire_date ? formatDate(data.hire_date) : '—' }}
            </template>
          </Column>
          <Column :header="$t('common.actions')" style="min-width: 120px; text-align: right">
            <template #body="{ data }">
              <div class="flex gap-1 justify-end">
                <Button
                  icon="pi pi-eye"
                  size="small"
                  text
                  rounded
                  severity="secondary"
                  @click="router.visit(route('hr.employees.show', data.id))"
                />
                <Button
                  icon="pi pi-pencil"
                  size="small"
                  text
                  rounded
                  @click="router.visit(route('hr.employees.edit', data.id))"
                />
                <Button
                  icon="pi pi-trash"
                  size="small"
                  text
                  rounded
                  severity="danger"
                  @click="confirmDelete(data)"
                />
              </div>
            </template>
          </Column>
          <template #empty>
            <div class="text-center py-12 text-surface-400">
              <i class="pi pi-users text-4xl mb-3 block" />
              <p>{{ $t('common.no_records') }}</p>
            </div>
          </template>
        </DataTable>
      </div>
    </div>

    <!-- Delete confirmation dialog -->
    <ConfirmDialog />
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { useConfirm } from 'primevue/useconfirm'
import { useDebounceFn } from '@vueuse/core'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import ConfirmDialog from 'primevue/confirmdialog'

const { t } = useI18n()
const confirm = useConfirm()

const employees = ref([])
const departments = ref([])
const loading = ref(false)
const total = ref(0)
const page = ref(1)
const perPage = ref(500)  // Load up to 500 records at once for virtual scrolling

const filters = reactive({
  search: '',
  department_id: null,
  status: null,
  employment_type: null,
})

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

const statusSeverity = (status) => {
  const map = { active: 'success', on_probation: 'warn', terminated: 'danger' }
  return map[status] ?? 'secondary'
}

const employmentTypeSeverity = (type) => {
  const map = { full_time: 'info', part_time: 'secondary', contract: 'warn' }
  return map[type] ?? 'secondary'
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString()
}

const loadEmployees = async () => {
  loading.value = true
  try {
    const params = { page: page.value, per_page: perPage.value, ...filters }
    Object.keys(params).forEach((k) => (params[k] == null || params[k] === '') && delete params[k])
    const { data } = await axios.get('/api/v1/hr/employees', { params })
    employees.value = data.data
    total.value = data.total
  } finally {
    loading.value = false
  }
}

const loadDepartments = async () => {
  const { data } = await axios.get('/api/v1/hr/departments', { params: { is_active: true, per_page: 100 } })
  departments.value = data.data
}

const debounceLoad = useDebounceFn(loadEmployees, 400)

const clearFilters = () => {
  filters.search = ''
  filters.department_id = null
  filters.status = null
  filters.employment_type = null
  page.value = 1
  loadEmployees()
}

const confirmDelete = (employee) => {
  confirm.require({
    message: `${t('common.confirm_delete')} ${employee.full_name}?`,
    header: t('common.delete'),
    icon: 'pi pi-exclamation-triangle',
    acceptSeverity: 'danger',
    accept: async () => {
      await axios.delete(`/api/v1/hr/employees/${employee.id}`)
      loadEmployees()
    },
  })
}

onMounted(() => {
  loadEmployees()
  loadDepartments()
})
</script>
