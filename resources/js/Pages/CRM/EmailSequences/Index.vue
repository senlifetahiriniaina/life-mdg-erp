<template>
  <AppLayout>
    <Head :title="$t('crm.email_sequences.title')" />

    <!-- Page header -->
    <div class="page-head">
      <div>
        <h1 class="wh-page-title">{{ $t('crm.email_sequences.title') }}</h1>
        <p class="wh-page-subtitle">{{ pagination.total }} {{ $t('crm.email_sequences.title').toLowerCase() }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="openCreateModal">
          <i class="pi pi-plus" style="font-size:13px" />
          {{ $t('crm.email_sequences.new') }}
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="wh-panel" style="margin-bottom:16px;overflow:visible">
      <div style="padding:12px 16px;display:flex;flex-wrap:wrap;gap:10px;align-items:center">
        <Select
          v-model="filters.status"
          :options="statusOptions"
          option-label="label"
          option-value="value"
          :placeholder="$t('common.status')"
          show-clear
          style="width:180px"
          @change="fetchSequences(1)"
        />
      </div>
    </div>

    <!-- Data table -->
    <div class="wh-panel" style="position:relative">
      <table class="wh-dt" :class="{ 'wh-dt-loading': loading }">
        <thead>
          <tr>
            <th>{{ $t('common.name') }}</th>
            <th>{{ $t('crm.email_sequences.trigger') }}</th>
            <th>{{ $t('common.status') }}</th>
            <th>{{ $t('crm.email_sequences.steps') }}</th>
            <th>{{ $t('crm.email_sequences.enrollments') }}</th>
            <th style="width:140px">{{ $t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="seq in sequences" :key="seq.id" class="wh-dt-row">
            <td>
              <span style="font-weight:500;color:var(--fg-1)">{{ seq.name }}</span>
              <div v-if="seq.description" style="font-size:12px;color:var(--fg-3);margin-top:2px">{{ seq.description }}</div>
            </td>
            <td style="color:var(--fg-2)">{{ formatTrigger(seq.trigger_event) }}</td>
            <td>
              <span :class="['wh-badge', statusClass(seq.status)]">
                <span class="wh-badge-dot" />
                {{ $t('crm.email_sequences.status_' + seq.status) }}
              </span>
            </td>
            <td style="color:var(--fg-2)">{{ seq.steps_count }}</td>
            <td style="color:var(--fg-2)">{{ seq.enrollments_count }}</td>
            <td>
              <div style="display:flex;gap:4px">
                <button class="wh-row-btn" :title="$t('common.edit')" @click="editSequence(seq)">
                  <i class="pi pi-pencil" style="font-size:13px" />
                </button>
                <button
                  class="wh-row-btn"
                  :title="seq.status === 'active' ? $t('crm.email_sequences.pause') : $t('crm.email_sequences.resume')"
                  @click="toggleStatus(seq)"
                >
                  <i :class="['pi', seq.status === 'active' ? 'pi-pause' : 'pi-play']" style="font-size:13px" />
                </button>
                <button class="wh-row-btn" :title="$t('crm.email_sequences.enroll')" @click="openEnrollModal(seq)">
                  <i class="pi pi-user-plus" style="font-size:13px" />
                </button>
                <button class="wh-row-btn wh-row-btn-danger" :title="$t('common.delete')" @click="confirmDelete(seq)">
                  <i class="pi pi-trash" style="font-size:13px" />
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!loading && sequences.length === 0">
            <td colspan="6" style="text-align:center;padding:48px 18px;color:var(--fg-3);font-size:14px">
              {{ $t('common.no_results') }}
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="loading" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(247,248,251,0.6)">
        <i class="pi pi-spin pi-spinner" style="font-size:20px;color:var(--halo-500)" />
      </div>

      <!-- Pagination -->
      <div
        v-if="pagination.total > pagination.per_page"
        style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-top:1px solid var(--border-subtle)"
      >
        <span style="font-size:13px;color:var(--fg-3)">
          {{ $t('common.of') }} {{ pagination.total }}
        </span>
        <Paginator
          :rows="pagination.per_page"
          :total-records="pagination.total"
          :first="(pagination.current_page - 1) * pagination.per_page"
          @page="(e) => fetchSequences(e.page + 1)"
        />
      </div>
    </div>

    <!-- Create/Edit Dialog -->
    <Dialog
      v-model:visible="showModal"
      :header="editingSeq ? $t('common.edit') : $t('crm.email_sequences.new')"
      modal
      style="width:600px"
    >
      <div style="display:flex;flex-direction:column;gap:14px;padding-top:4px">
        <div>
          <label class="wh-label">{{ $t('common.name') }} *</label>
          <input v-model="form.name" class="wh-input" :placeholder="$t('common.name')" />
        </div>
        <div>
          <label class="wh-label">{{ $t('common.description') }}</label>
          <textarea v-model="form.description" class="wh-input" rows="2" />
        </div>
        <div>
          <label class="wh-label">{{ $t('crm.email_sequences.trigger') }}</label>
          <Select
            v-model="form.trigger_event"
            :options="triggerOptions"
            option-label="label"
            option-value="value"
            style="width:100%"
          />
        </div>
        <div>
          <label class="wh-label">{{ $t('common.status') }}</label>
          <Select
            v-model="form.status"
            :options="statusOptions"
            option-label="label"
            option-value="value"
            style="width:100%"
          />
        </div>

        <div v-if="!editingSeq">
          <label class="wh-label">{{ $t('crm.email_sequences.steps') }}</label>
          <div v-for="(step, idx) in form.steps" :key="idx" style="border:1px solid var(--border-subtle);border-radius:var(--r-md);padding:12px;margin-bottom:8px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
              <span style="font-size:13px;font-weight:600;color:var(--fg-2)">Step {{ idx + 1 }}</span>
              <button v-if="form.steps.length > 1" class="wh-row-btn wh-row-btn-danger" @click="removeStep(idx)">
                <i class="pi pi-times" style="font-size:11px" />
              </button>
            </div>
            <input v-model="step.subject" class="wh-input" :placeholder="$t('crm.email_sequences.subject')" style="margin-bottom:6px" />
            <input v-model.number="step.delay_days" type="number" min="0" class="wh-input" :placeholder="$t('crm.email_sequences.delay_days')" style="margin-bottom:6px" />
            <textarea v-model="step.body_html" class="wh-input" rows="3" :placeholder="$t('crm.email_sequences.body_html')" />
          </div>
          <button class="btn btn-secondary" style="margin-top:4px" @click="addStep">
            <i class="pi pi-plus" style="font-size:12px" />
            {{ $t('crm.email_sequences.add_step') }}
          </button>
        </div>

        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px">
          <button class="btn btn-secondary" @click="showModal = false">{{ $t('common.cancel') }}</button>
          <button class="btn btn-primary" :disabled="saving" @click="saveSequence">
            <i v-if="saving" class="pi pi-spin pi-spinner" style="font-size:13px" />
            {{ $t('common.save') }}
          </button>
        </div>
      </div>
    </Dialog>

    <!-- Enroll Contacts Dialog -->
    <Dialog
      v-model:visible="showEnrollModal"
      :header="$t('crm.email_sequences.enroll')"
      modal
      style="width:480px"
    >
      <div style="display:flex;flex-direction:column;gap:14px;padding-top:4px">
        <div>
          <label class="wh-label">{{ $t('common.contact') }} *</label>
          <input
            v-model="enrollContactId"
            type="number"
            class="wh-input"
            :placeholder="$t('crm.email_sequences.contact_id_placeholder')"
          />
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end">
          <button class="btn btn-secondary" @click="showEnrollModal = false">{{ $t('common.cancel') }}</button>
          <button class="btn btn-primary" :disabled="enrolling" @click="enrollContact">
            <i v-if="enrolling" class="pi pi-spin pi-spinner" style="font-size:13px" />
            {{ $t('crm.email_sequences.enroll') }}
          </button>
        </div>
      </div>
    </Dialog>

    <ConfirmDialog />
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import { useConfirm } from 'primevue/useconfirm'
import Select from 'primevue/select'
import Dialog from 'primevue/dialog'
import Paginator from 'primevue/paginator'
import ConfirmDialog from 'primevue/confirmdialog'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useI18n } from 'vue-i18n'

const confirm = useConfirm()
const { t } = useI18n()

const sequences = ref([])
const loading = ref(false)
const showModal = ref(false)
const showEnrollModal = ref(false)
const editingSeq = ref(null)
const saving = ref(false)
const enrolling = ref(false)
const enrollContactId = ref('')
const enrollingSeq = ref(null)

const pagination = reactive({ current_page: 1, per_page: 25, total: 0, last_page: 1 })
const filters = reactive({ status: null })

const defaultForm = () => ({
  name: '',
  description: '',
  status: 'draft',
  trigger_event: 'manual',
  steps: [{ step_order: 1, delay_days: 0, subject: '', body_html: '' }],
})
const form = reactive(defaultForm())

const statusOptions = computed(() => [
  { label: t('crm.email_sequences.status_draft'),  value: 'draft' },
  { label: t('crm.email_sequences.status_active'), value: 'active' },
  { label: t('crm.email_sequences.status_paused'), value: 'paused' },
])

const triggerOptions = computed(() => [
  { label: t('crm.email_sequences.trigger_manual'),          value: 'manual' },
  { label: t('crm.email_sequences.trigger_contact_created'), value: 'contact_created' },
  { label: t('crm.email_sequences.trigger_lead_created'),    value: 'lead_created' },
])

const statusClass = (status) => {
  switch (status) {
    case 'active': return 'wh-badge-green'
    case 'paused': return 'wh-badge-yellow'
    default:       return 'wh-badge-slate'
  }
}

const formatTrigger = (trigger) => {
  return triggerOptions.value.find(o => o.value === trigger)?.label ?? trigger
}

const fetchSequences = async (page = 1) => {
  loading.value = true
  try {
    const params = new URLSearchParams({ page: String(page) })
    if (filters.status) params.set('status', filters.status)
    const res = await fetch(`/api/v1/crm/email-sequences?${params}`, { headers: { Accept: 'application/json' } })
    const data = await res.json()
    sequences.value = data.data
    pagination.current_page = data.current_page
    pagination.total = data.total
    pagination.last_page = data.last_page
  } finally { loading.value = false }
}

const openCreateModal = () => {
  editingSeq.value = null
  Object.assign(form, defaultForm())
  form.steps = [{ step_order: 1, delay_days: 0, subject: '', body_html: '' }]
  showModal.value = true
}

const editSequence = (seq) => {
  editingSeq.value = seq
  Object.assign(form, { name: seq.name, description: seq.description ?? '', status: seq.status, trigger_event: seq.trigger_event, steps: [] })
  showModal.value = true
}

const addStep = () => {
  form.steps.push({ step_order: form.steps.length + 1, delay_days: 1, subject: '', body_html: '' })
}

const removeStep = (idx) => {
  form.steps.splice(idx, 1)
  form.steps.forEach((s, i) => { s.step_order = i + 1 })
}

const saveSequence = async () => {
  if (!form.name) return
  saving.value = true
  try {
    const url = editingSeq.value ? `/api/v1/crm/email-sequences/${editingSeq.value.id}` : '/api/v1/crm/email-sequences'
    const method = editingSeq.value ? 'PUT' : 'POST'
    const body = editingSeq.value
      ? { name: form.name, description: form.description, status: form.status, trigger_event: form.trigger_event }
      : { ...form }
    await fetch(url, { method, headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
    showModal.value = false
    fetchSequences(pagination.current_page)
  } finally { saving.value = false }
}

const toggleStatus = async (seq) => {
  const newStatus = seq.status === 'active' ? 'paused' : 'active'
  await fetch(`/api/v1/crm/email-sequences/${seq.id}`, {
    method: 'PUT',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({ status: newStatus }),
  })
  fetchSequences(pagination.current_page)
}

const openEnrollModal = (seq) => {
  enrollingSeq.value = seq
  enrollContactId.value = ''
  showEnrollModal.value = true
}

const enrollContact = async () => {
  if (!enrollContactId.value || !enrollingSeq.value) return
  enrolling.value = true
  try {
    await fetch(`/api/v1/crm/email-sequences/${enrollingSeq.value.id}/enroll`, {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ contact_id: Number(enrollContactId.value) }),
    })
    showEnrollModal.value = false
    fetchSequences(pagination.current_page)
  } finally { enrolling.value = false }
}

const confirmDelete = (seq) => {
  confirm.require({
    message: `${t('common.delete')} "${seq.name}" ?`,
    header: t('common.delete'),
    icon: 'pi pi-exclamation-triangle',
    rejectClass: 'p-button-text',
    acceptClass: 'p-button-danger',
    accept: async () => {
      await fetch(`/api/v1/crm/email-sequences/${seq.id}`, { method: 'DELETE', headers: { Accept: 'application/json' } })
      fetchSequences(pagination.current_page)
    },
  })
}

onMounted(() => fetchSequences())
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; gap:16px; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; gap:8px; }
.btn { font-family:var(--font-sans); font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:background var(--dur-base) var(--ease-out), border-color var(--dur-base); line-height:1.2; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover { background:var(--halo-700); }
.btn-primary:disabled { opacity:0.6; cursor:not-allowed; }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover { background:var(--bg-sunken); }
.wh-dt { width:100%; border-collapse:collapse; font-size:14px; }
.wh-dt thead th { text-align:left; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:var(--fg-3); font-weight:600; padding:10px 18px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sunken); }
.wh-dt-row td { padding:11px 18px; border-bottom:1px solid var(--border-subtle); color:var(--fg-1); }
.wh-dt-row:last-child td { border-bottom:0; }
.wh-dt-row { transition:background var(--dur-fast); }
.wh-dt-row:hover { background:var(--bg-sunken); }
.wh-dt-loading { opacity:0.5; pointer-events:none; }
.wh-row-btn { width:28px; height:28px; border-radius:var(--r-sm); border:1px solid var(--border-subtle); background:var(--bg-canvas); color:var(--fg-2); cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:all var(--dur-fast); }
.wh-row-btn:hover { background:var(--bg-sunken); color:var(--fg-1); border-color:var(--border-strong); }
.wh-row-btn-danger:hover { background:var(--danger-bg); color:var(--danger-fg); border-color:var(--red-500); }
.wh-badge { display:inline-flex; align-items:center; gap:5px; padding:2px 8px; border-radius:var(--r-pill); font-size:11px; font-weight:500; }
.wh-badge-dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
.wh-badge-green  { background:var(--success-bg); color:var(--success-fg); }
.wh-badge-yellow { background:#fef9c3; color:#854d0e; }
.wh-badge-slate  { background:var(--bg-sunken); color:var(--fg-2); }
.wh-panel { background:var(--bg-canvas); border-radius:var(--r-lg); border:1px solid var(--border-subtle); }
.wh-label { display:block; font-size:12px; font-weight:600; color:var(--fg-2); margin-bottom:4px; text-transform:uppercase; letter-spacing:0.04em; }
.wh-input { width:100%; padding:8px 12px; border-radius:var(--r-md); border:1px solid var(--border-subtle); background:var(--bg-sunken); font-family:var(--font-sans); font-size:13px; color:var(--fg-1); outline:none; box-sizing:border-box; }
.wh-input:focus { border-color:var(--halo-500); background:var(--bg-canvas); box-shadow:0 0 0 3px rgba(46,91,232,0.12); }
</style>
