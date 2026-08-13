<template>
  <AppLayout>
    <Head :title="$t('hr.leave_requests')" />

    <div class="space-y-6">
      <!-- Page header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50 text-surface-900 dark:text-surface-50">
            {{ $t('hr.leave_requests') }}
          </h1>
          <p class="text-surface-500 text-sm mt-1">
            {{ total }} {{ $t('hr.leave_requests').toLowerCase() }}
          </p>
        </div>
        <Button
          icon="pi pi-plus"
          :label="$t('common.new') + ' Request'"
          @click="showCreateDialog = true"
        />
      </div>

      <!-- Filters -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-4">
        <div class="flex flex-wrap gap-3">
          <Select
            v-model="filters.employee_id"
            :options="employees"
            option-label="full_name"
            option-value="id"
            placeholder="All Employees"
            show-clear
            filter
            class="w-56"
            @change="loadLeaves"
          />
          <Select
            v-model="filters.status"
            :options="statusOptions"
            option-label="label"
            option-value="value"
            placeholder="All Statuses"
            show-clear
            class="w-44"
            @change="loadLeaves"
          />
          <Select
            v-model="filters.year"
            :options="yearOptions"
            option-label="label"
            option-value="value"
            placeholder="Year"
            show-clear
            class="w-32"
            @change="loadLeaves"
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

      <!-- DataTable -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700">
        <DataTable
          :value="leaves"
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
          <Column :header="$t('hr.employee')" style="min-width: 180px">
            <template #body="{ data }">
              <div v-if="data.employee" class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center flex-shrink-0">
                  <span class="text-xs font-semibold text-primary-700 dark:text-primary-300">
                    {{ data.employee.full_name.charAt(0) }}
                  </span>
                </div>
                <span class="font-medium text-surface-900 dark:text-surface-50">{{ data.employee.full_name }}</span>
              </div>
            </template>
          </Column>
          <Column header="Leave Type" style="min-width: 140px">
            <template #body="{ data }">
              <span v-if="data.leave_type">{{ data.leave_type.name }}</span>
            </template>
          </Column>
          <Column header="Start Date" style="min-width: 120px">
            <template #body="{ data }">{{ formatDate(data.start_date) }}</template>
          </Column>
          <Column header="End Date" style="min-width: 120px">
            <template #body="{ data }">{{ formatDate(data.end_date) }}</template>
          </Column>
          <Column header="Days" style="min-width: 80px">
            <template #body="{ data }">
              <span class="font-semibold">{{ data.days }}</span>
            </template>
          </Column>
          <Column :header="$t('hr.fields.status')" style="min-width: 120px">
            <template #body="{ data }">
              <Tag
                :value="$t('hr.leave_statuses.' + data.status)"
                :severity="statusSeverity(data.status)"
              />
            </template>
          </Column>
          <Column :header="$t('common.actions')" style="min-width: 160px; text-align: right">
            <template #body="{ data }">
              <div class="flex gap-1 justify-end">
                <template v-if="data.status === 'pending'">
                  <Button
                    icon="pi pi-check"
                    size="small"
                    text
                    rounded
                    severity="success"
                    :label="$t('hr.leave_statuses.approved')"
                    @click="confirmApprove(data)"
                  />
                  <Button
                    icon="pi pi-times"
                    size="small"
                    text
                    rounded
                    severity="danger"
                    :label="$t('hr.leave_statuses.rejected')"
                    @click="openRejectDialog(data)"
                  />
                </template>
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
              <i class="pi pi-calendar text-4xl mb-3 block" />
              <p>{{ $t('common.no_records') }}</p>
            </div>
          </template>
        </DataTable>
      </div>
    </div>

    <!-- Create Leave Dialog -->
    <Dialog v-model:visible="showCreateDialog" modal :header="$t('common.new') + ' Leave Request'" style="width: 520px">
      <form @submit.prevent="submitLeave" class="space-y-4">
        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium text-surface-900 dark:text-surface-50">{{ $t('hr.employee') }} <span class="text-error-600">*</span></label>
          <Select
            v-model="leaveForm.employee_id"
            :options="employees"
            option-label="full_name"
            option-value="id"
            filter
            :placeholder="'Select ' + $t('hr.employee')"
            :invalid="!!leaveErrors.employee_id"
          />
          <small v-if="leaveErrors.employee_id" class="text-error-600">{{ leaveErrors.employee_id }}</small>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium text-surface-900 dark:text-surface-50">Leave Type <span class="text-error-600">*</span></label>
          <Select
            v-model="leaveForm.leave_type_id"
            :options="leaveTypes"
            option-label="name"
            option-value="id"
            placeholder="Select leave type"
            :invalid="!!leaveErrors.leave_type_id"
          />
          <small v-if="leaveErrors.leave_type_id" class="text-error-600">{{ leaveErrors.leave_type_id }}</small>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-surface-900 dark:text-surface-50">Start Date <span class="text-error-600">*</span></label>
            <DatePicker v-model="leaveForm.start_date" date-format="yy-mm-dd" :invalid="!!leaveErrors.start_date" />
            <small v-if="leaveErrors.start_date" class="text-error-600">{{ leaveErrors.start_date }}</small>
          </div>
          <div class="flex flex-col gap-2">
            <label class="text-sm font-medium text-surface-900 dark:text-surface-50">End Date <span class="text-error-600">*</span></label>
            <DatePicker v-model="leaveForm.end_date" date-format="yy-mm-dd" :invalid="!!leaveErrors.end_date" />
            <small v-if="leaveErrors.end_date" class="text-error-600">{{ leaveErrors.end_date }}</small>
          </div>
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium text-surface-900 dark:text-surface-50">Reason</label>
          <Textarea v-model="leaveForm.reason" rows="3" auto-resize />
        </div>
        <div class="flex justify-end gap-3 pt-2">
          <Button type="button" :label="$t('common.cancel')" severity="secondary" outlined @click="showCreateDialog = false" />
          <Button type="submit" :label="$t('common.save')" icon="pi pi-check" :loading="savingLeave" />
        </div>
      </form>
    </Dialog>

    <!-- Reject Dialog -->
    <Dialog v-model:visible="showRejectDialog" modal header="Reject Leave Request" style="width: 420px">
      <div class="space-y-4">
        <p class="text-sm text-surface-600 dark:text-surface-300">Please provide a reason for rejection.</p>
        <div class="flex flex-col gap-2">
          <label class="text-sm font-medium text-surface-900 dark:text-surface-50">Rejection Reason <span class="text-error-600">*</span></label>
          <Textarea v-model="rejectReason" rows="3" auto-resize :invalid="!rejectReason" />
        </div>
        <div class="flex justify-end gap-3">
          <Button :label="$t('common.cancel')" severity="secondary" outlined @click="showRejectDialog = false" />
          <Button label="Reject" icon="pi pi-times" severity="danger" :loading="rejecting" @click="submitReject" />
        </div>
      </div>
    </Dialog>

    <ConfirmDialog />
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { useConfirm } from 'primevue/useconfirm'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import Button from 'primevue/button'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import DatePicker from 'primevue/datepicker'
import Textarea from 'primevue/textarea'
import ConfirmDialog from 'primevue/confirmdialog'

const { t } = useI18n()
const confirm = useConfirm()

const leaves = ref([])
const employees = ref([])
const leaveTypes = ref([])
const loading = ref(false)
const total = ref(0)
const page = ref(1)
const perPage = ref(25)

const showCreateDialog = ref(false)
const showRejectDialog = ref(false)
const savingLeave = ref(false)
const rejecting = ref(false)
const rejectReason = ref('')
const selectedLeave = ref(null)

const filters = reactive({
  employee_id: null,
  status: null,
  year: null,
})

const leaveForm = reactive({
  employee_id: null,
  leave_type_id: null,
  start_date: null,
  end_date: null,
  reason: '',
})

const leaveErrors = reactive({})

const currentYear = new Date().getFullYear()
const yearOptions = Array.from({ length: 5 }, (_, i) => ({
  label: String(currentYear - i),
  value: currentYear - i,
}))

const statusOptions = [
  { label: t('hr.leave_statuses.pending'), value: 'pending' },
  { label: t('hr.leave_statuses.approved'), value: 'approved' },
  { label: t('hr.leave_statuses.rejected'), value: 'rejected' },
]

const statusSeverity = (status) => {
  const map = { pending: 'warn', approved: 'success', rejected: 'danger' }
  return map[status] ?? 'secondary'
}

const formatDate = (date) => (date ? new Date(date).toLocaleDateString() : '—')

const formatDateForApi = (val) => {
  if (!val) return null
  if (typeof val === 'string') return val
  const d = new Date(val)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

const loadLeaves = async () => {
  loading.value = true
  try {
    const params = { page: page.value, per_page: perPage.value, ...filters }
    Object.keys(params).forEach((k) => (params[k] == null || params[k] === '') && delete params[k])
    const { data } = await axios.get('/api/v1/hr/leaves', { params })
    leaves.value = data.data
    total.value = data.total
  } finally {
    loading.value = false
  }
}

const clearFilters = () => {
  filters.employee_id = null
  filters.status = null
  filters.year = null
  loadLeaves()
}

const onPage = (event) => {
  page.value = event.page + 1
  perPage.value = event.rows
  loadLeaves()
}

const confirmApprove = (leave) => {
  confirm.require({
    message: `Approve leave request for ${leave.employee?.full_name}?`,
    header: 'Approve Leave',
    icon: 'pi pi-check-circle',
    accept: async () => {
      await axios.post(`/api/v1/hr/leaves/${leave.id}/approve`)
      loadLeaves()
    },
  })
}

const openRejectDialog = (leave) => {
  selectedLeave.value = leave
  rejectReason.value = ''
  showRejectDialog.value = true
}

const submitReject = async () => {
  if (!rejectReason.value) return
  rejecting.value = true
  try {
    await axios.post(`/api/v1/hr/leaves/${selectedLeave.value.id}/reject`, {
      rejection_reason: rejectReason.value,
    })
    showRejectDialog.value = false
    loadLeaves()
  } finally {
    rejecting.value = false
  }
}

const confirmDelete = (leave) => {
  confirm.require({
    message: 'Delete this leave request?',
    header: t('common.delete'),
    icon: 'pi pi-exclamation-triangle',
    acceptSeverity: 'danger',
    accept: async () => {
      await axios.delete(`/api/v1/hr/leaves/${leave.id}`)
      loadLeaves()
    },
  })
}

const submitLeave = async () => {
  savingLeave.value = true
  Object.keys(leaveErrors).forEach((k) => delete leaveErrors[k])
  try {
    await axios.post('/api/v1/hr/leaves', {
      ...leaveForm,
      start_date: formatDateForApi(leaveForm.start_date),
      end_date: formatDateForApi(leaveForm.end_date),
    })
    showCreateDialog.value = false
    Object.assign(leaveForm, { employee_id: null, leave_type_id: null, start_date: null, end_date: null, reason: '' })
    loadLeaves()
  } catch (e) {
    if (e.response?.status === 422) {
      Object.assign(leaveErrors, e.response.data.errors)
    }
  } finally {
    savingLeave.value = false
  }
}

onMounted(async () => {
  loadLeaves()
  const [empRes, ltRes] = await Promise.all([
    axios.get('/api/v1/hr/employees', { params: { per_page: 200, status: 'active' } }),
    axios.get('/api/v1/hr/leave-types', { params: { per_page: 100 } }),
  ])
  employees.value = empRes.data.data
  leaveTypes.value = ltRes.data.data
})
</script>
