<template>
  <AppLayout>
    <Head :title="$t('hr.departments')" />

    <div class="space-y-6">
      <!-- Page header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50 text-surface-900 dark:text-surface-50">
            {{ $t('hr.departments') }}
          </h1>
          <p class="text-surface-500 text-sm mt-1">{{ total }} {{ $t('hr.departments').toLowerCase() }}</p>
        </div>
        <Button
          icon="pi pi-plus"
          :label="$t('common.new') + ' ' + $t('hr.departments').slice(0, -1)"
          @click="openCreateDialog"
        />
      </div>

      <!-- Search & filter row -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-4">
        <div class="flex flex-wrap gap-3">
          <InputText
            v-model="search"
            :placeholder="$t('common.search') + '...'"
            class="w-64"
            @input="debounceLoad"
          />
          <Button
            icon="pi pi-times"
            :label="$t('common.clear')"
            severity="secondary"
            outlined
            @click="search = ''; loadDepartments()"
          />
        </div>
      </div>

      <!-- List -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700">
        <DataTable
          :value="departments"
          :loading="loading"
          lazy
          :total-records="total"
          :rows="perPage"
          paginator
          :rows-per-page-options="[10, 25, 50]"
          @page="onPage"
          row-hover
          class="rounded-xl overflow-hidden"
        >
          <Column field="name" header="Name" sortable style="min-width: 180px">
            <template #body="{ data }">
              <p class="font-medium text-surface-900 dark:text-surface-50">{{ data.name }}</p>
            </template>
          </Column>
          <Column field="code" header="Code" style="min-width: 100px">
            <template #body="{ data }">
              <code class="bg-surface-100 dark:bg-surface-700 px-2 py-0.5 rounded text-xs">{{ data.code }}</code>
            </template>
          </Column>
          <Column header="Manager" style="min-width: 160px">
            <template #body="{ data }">
              <span v-if="data.manager">{{ data.manager.full_name }}</span>
              <span v-else class="text-surface-300 text-sm">—</span>
            </template>
          </Column>
          <Column header="Employees" style="min-width: 110px; text-align: right">
            <template #body="{ data }">
              <span class="font-semibold">{{ data.employees_count ?? 0 }}</span>
            </template>
          </Column>
          <Column header="Status" style="min-width: 100px">
            <template #body="{ data }">
              <Tag :value="data.status === 'active' ? 'Active' : 'Inactive'" :severity="data.status === 'active' ? 'success' : 'secondary'" />
            </template>
          </Column>
          <Column :header="$t('common.actions')" style="min-width: 120px; text-align: right">
            <template #body="{ data }">
              <div class="flex gap-1 justify-end">
                <Button icon="pi pi-pencil" size="small" text rounded @click="openEditDialog(data)" />
                <Button icon="pi pi-trash" size="small" text rounded severity="danger" @click="confirmDelete(data)" />
              </div>
            </template>
          </Column>
          <template #empty>
            <div class="text-center py-12 text-surface-400">
              <i class="pi pi-building text-4xl mb-3 block" />
              <p>{{ $t('common.no_records') }}</p>
            </div>
          </template>
        </DataTable>
      </div>
    </div>

    <!-- Create/Edit Dialog -->
    <Dialog v-model:visible="showDialog" modal :header="editingDept ? 'Edit Department' : 'New Department'" style="width: 520px">
      <form @submit.prevent="submitForm" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-surface-900 dark:text-surface-50">Name <span class="text-red-500">*</span></label>
            <InputText v-model="form.name" :invalid="!!errors.name" />
            <small v-if="errors.name" class="text-red-500">{{ errors.name }}</small>
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-surface-900 dark:text-surface-50">Code <span class="text-red-500">*</span></label>
            <InputText v-model="form.code" :invalid="!!errors.code" />
            <small v-if="errors.code" class="text-red-500">{{ errors.code }}</small>
          </div>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium text-surface-900 dark:text-surface-50">Manager</label>
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
          <label class="text-sm font-medium text-surface-900 dark:text-surface-50">Description</label>
          <Textarea v-model="form.description" rows="2" auto-resize />
        </div>
        <div class="flex items-center gap-3">
          <ToggleSwitch :model-value="form.status === 'active'" @update:model-value="v => form.status = v ? 'active' : 'inactive'" input-id="status" />
          <label for="status" class="text-sm font-medium text-surface-900 dark:text-surface-50 cursor-pointer">Active</label>
        </div>
        <div class="flex justify-end gap-3 pt-2">
          <Button type="button" :label="$t('common.cancel')" severity="secondary" outlined @click="showDialog = false" />
          <Button type="submit" :label="$t('common.save')" icon="pi pi-check" :loading="saving" />
        </div>
      </form>
    </Dialog>

    <ConfirmDialog />
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
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
import Dialog from 'primevue/dialog'
import Textarea from 'primevue/textarea'
import ToggleSwitch from 'primevue/toggleswitch'
import ConfirmDialog from 'primevue/confirmdialog'

const { t } = useI18n()
const confirm = useConfirm()

const departments = ref([])
const managers = ref([])
const loading = ref(false)
const total = ref(0)
const page = ref(1)
const perPage = ref(25)
const search = ref('')

const showDialog = ref(false)
const saving = ref(false)
const editingDept = ref(null)

const form = reactive({
  name: '',
  code: '',
  manager_id: null,
  description: '',
  status: 'active',
})

const errors = reactive({})

const loadDepartments = async () => {
  loading.value = true
  try {
    const params = { page: page.value, per_page: perPage.value }
    if (search.value) params.search = search.value
    const { data } = await axios.get('/api/v1/hr/departments', { params })
    departments.value = data.data
    total.value = data.total
  } finally {
    loading.value = false
  }
}

const debounceLoad = useDebounceFn(loadDepartments, 400)

const onPage = (event) => {
  page.value = event.page + 1
  perPage.value = event.rows
  loadDepartments()
}

const openCreateDialog = () => {
  editingDept.value = null
  Object.assign(form, { name: '', code: '', manager_id: null, description: '', status: 'active' })
  Object.keys(errors).forEach((k) => delete errors[k])
  showDialog.value = true
}

const openEditDialog = (dept) => {
  editingDept.value = dept
  Object.assign(form, {
    name: dept.name,
    code: dept.code,
    manager_id: dept.manager?.id ?? null,
    description: dept.description ?? '',
    status: dept.status ?? 'active',
  })
  Object.keys(errors).forEach((k) => delete errors[k])
  showDialog.value = true
}

const submitForm = async () => {
  saving.value = true
  Object.keys(errors).forEach((k) => delete errors[k])
  try {
    if (editingDept.value) {
      await axios.put(`/api/v1/hr/departments/${editingDept.value.id}`, form)
    } else {
      await axios.post('/api/v1/hr/departments', form)
    }
    showDialog.value = false
    loadDepartments()
  } catch (e) {
    if (e.response?.status === 422) {
      Object.assign(errors, e.response.data.errors)
    }
  } finally {
    saving.value = false
  }
}

const confirmDelete = (dept) => {
  confirm.require({
    message: `Delete department "${dept.name}"?`,
    header: t('common.delete'),
    icon: 'pi pi-exclamation-triangle',
    acceptSeverity: 'danger',
    accept: async () => {
      await axios.delete(`/api/v1/hr/departments/${dept.id}`)
      loadDepartments()
    },
  })
}

onMounted(async () => {
  loadDepartments()
  const { data } = await axios.get('/api/v1/hr/employees', { params: { per_page: 200, status: 'active' } })
  managers.value = data.data
})
</script>
