<template>
  <AppLayout>
    <Head :title="$t('helpdesk.tickets.title')" />

    <GuidedTour tour-id="helpdesk-tickets" :steps="helpdeskTourSteps" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">{{ $t('helpdesk.tickets.title') }}</h1>
        <p class="wh-page-subtitle">{{ tickets.total }} ticket{{ tickets.total !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-secondary"><i class="pi pi-download" style="font-size:13px" /> {{ $t('common.export') }}</button>
        <button class="btn btn-primary" @click="showCreateModal = true"><i class="pi pi-plus" style="font-size:13px" /> {{ $t('helpdesk.tickets.new') }}</button>
      </div>
    </div>

    <!-- KPI row -->
    <div class="wh-kpi-grid" style="margin-bottom:16px">
      <div class="wh-kpi" v-for="s in stats" :key="s.label">
        <div class="wh-kpi-label">{{ s.label }}</div>
        <div class="wh-kpi-num font-display">{{ s.value }}</div>
      </div>
    </div>

    <!-- Filter pills -->
    <div style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap">
      <button
        v-for="f in filterOptions" :key="f.value"
        :class="['filter-pill', activeFilter === f.value ? 'filter-pill-on' : '']"
        @click="activeFilter = f.value"
      >{{ f.label }}</button>
    </div>

    <!-- Table -->
    <div class="wh-panel" style="position:relative">
      <table class="wh-dt">
        <thead>
          <tr>
            <th style="width:4px;padding:0"></th>
            <th>{{ $t('helpdesk.subject') }}</th>
            <th>{{ $t('common.status') }}</th>
            <th>{{ $t('common.priority') }}</th>
            <th>{{ $t('helpdesk.channel') }}</th>
            <th>{{ $t('helpdesk.team') }}</th>
            <th>{{ $t('common.created_at') }}</th>
            <th style="width:60px"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="t in filteredTickets" :key="t.id" class="wh-dt-row" style="cursor:pointer" @click="goToTicket(t.id)">
            <td class="priority-bar-cell">
              <div class="priority-bar" :class="`priority-bar-${t.priority}`" />
            </td>
            <td>
              <div style="font-weight:500;color:var(--fg-1)">{{ t.subject }}</div>
              <div style="font-family:var(--font-mono);font-size:11px;color:var(--fg-3)">#{{ t.id }}</div>
            </td>
            <td>
              <span :class="['wh-badge', ticketStatusClass(t.status)]">
                <span class="wh-badge-dot" />{{ statusLabel(t.status) }}
              </span>
            </td>
            <td>
              <span :class="['wh-badge', priorityClass(t.priority)]">{{ priorityLabel(t.priority) }}</span>
            </td>
            <td style="color:var(--fg-2)">
              <span style="display:inline-flex;align-items:center;gap:5px">
                <i :class="channelIcon(t.channel)" style="font-size:12px" />
                {{ channelLabel(t.channel) }}
              </span>
            </td>
            <td style="color:var(--fg-2)">{{ t.team?.name ?? '—' }}</td>
            <td style="color:var(--fg-2);font-variant-numeric:tabular-nums">{{ formatDate(t.created_at) }}</td>
            <td>
              <button class="wh-row-btn" title="Voir" @click.stop="goToTicket(t.id)"><i class="pi pi-eye" style="font-size:13px" /></button>
            </td>
          </tr>
          <tr v-if="filteredTickets.length === 0">
            <td colspan="8" style="text-align:center;padding:48px;color:var(--fg-3);font-size:14px">{{ $t('common.no_results') }}</td>
          </tr>
        </tbody>
      </table>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-top:1px solid var(--border-subtle)">
        <span style="font-size:13px;color:var(--fg-3)">{{ tickets.total }} résultat{{ tickets.total !== 1 ? 's' : '' }}</span>
        <Paginator :rows="tickets.per_page" :total-records="tickets.total" :first="(tickets.current_page - 1) * tickets.per_page" />
      </div>
    </div>

    <!-- Create Ticket Modal -->
    <Dialog v-model:visible="showCreateModal" modal :header="$t('helpdesk.tickets.new')" :style="{ width: '520px' }" role="dialog" aria-modal="true" :aria-label="$t('helpdesk.tickets.new')" @hide="resetCreateForm">
      <form @submit.prevent="onCreateSubmit" style="display:flex;flex-direction:column;gap:14px;padding-top:4px">
        <div class="form-field">
          <label class="form-label">{{ $t('helpdesk.subject') }} <span style="color:var(--danger-fg)">*</span></label>
          <InputText
            v-model="subject"
            v-bind="subjectAttrs"
            :class="{ 'p-invalid': createErrors.subject }"
            placeholder="Décrivez le problème en une ligne"
            :maxlength="255"
            :aria-required="true"
            :aria-invalid="!!createErrors.subject"
            aria-describedby="error-subject"
          />
          <small v-if="createErrors.subject" class="form-error" role="alert" id="error-subject">{{ createErrors.subject }}</small>
        </div>

        <div class="form-field">
          <label class="form-label">{{ $t('common.description') }}</label>
          <Textarea
            v-model="description"
            v-bind="descriptionAttrs"
            rows="4"
            placeholder="Détails, étapes pour reproduire…"
            style="resize:vertical"
          />
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div class="form-field">
            <label class="form-label">{{ $t('common.priority') }} <span style="color:var(--danger-fg)">*</span></label>
            <Select
              v-model="priority"
              v-bind="priorityAttrs"
              :options="priorityOptions"
              option-label="label"
              option-value="value"
              :class="{ 'p-invalid': createErrors.priority }"
              class="w-full"
            />
            <small v-if="createErrors.priority" class="form-error">{{ createErrors.priority }}</small>
          </div>

          <div class="form-field">
            <label class="form-label">{{ $t('helpdesk.channel') }}</label>
            <Select
              v-model="channel"
              v-bind="channelAttrs"
              :options="channelOptions"
              option-label="label"
              option-value="value"
              :placeholder="t('helpdesk.channel')"
              show-clear
              class="w-full"
            />
          </div>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:8px;padding-top:4px;border-top:1px solid var(--border-subtle)">
          <button type="button" class="btn btn-secondary" @click="showCreateModal = false">{{ $t('common.cancel') }}</button>
          <button type="submit" class="btn btn-primary" :disabled="createSubmitting">
            <i v-if="createSubmitting" class="pi pi-spin pi-spinner" style="font-size:13px" />
            {{ $t('helpdesk.tickets.new') }}
          </button>
        </div>
      </form>
    </Dialog>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import { Head, router } from '@inertiajs/vue3'
import Paginator from 'primevue/paginator'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import AppLayout from '@/Layouts/AppLayout.vue'
import GuidedTour from '@/Components/UI/GuidedTour.vue'
import { useHelpStore } from '@/stores/help'
import { useHelpdeskChannel } from '@/composables/useEcho'
import { useI18n } from 'vue-i18n'

const help = useHelpStore()
const { t } = useI18n()

const helpdeskTourSteps = [
  { tag: 'Helpdesk', icon: 'pi pi-ticket',     title: 'Ticket Queue',       description: 'All support requests arrive here from email, WhatsApp and web forms. Tickets are sorted by priority and SLA deadline by default.' },
  { tag: 'Helpdesk', icon: 'pi pi-sliders-h',  title: 'Priority Colour Bar', description: 'The coloured bar on the left of each row shows priority at a glance: red = urgent, orange = high, blue = medium, grey = low.' },
  { tag: 'Helpdesk', icon: 'pi pi-clock',      title: 'SLA Deadlines',       description: 'The SLA column shows how much time remains before a response is overdue. Tickets turning red require immediate attention.' },
  { tag: 'Helpdesk', icon: 'pi pi-user-plus',  title: 'Auto-Assignment',     description: 'The AI automatically assigns incoming tickets to the most appropriate agent based on topic, workload and past resolution history.' },
  { tag: 'Helpdesk', icon: 'pi pi-book',       title: 'Knowledge Base',      description: 'Link a ticket to a KB article to suggest a self-service answer — this trains the AI chatbot for future similar questions.' },
]

const props = defineProps({
  tickets: { type: Object, required: true },
})

// Local mutable copy of the ticket list so Echo events can patch it without
// requiring a full Inertia reload.
const localTickets = ref([...(props.tickets.data ?? [])])

useHelpdeskChannel(
  (data) => {
    // Prepend new ticket if it matches the current filter (or filter is 'all')
    const alreadyExists = localTickets.value.some(t => t.id === data.id)
    if (!alreadyExists) {
      localTickets.value.unshift(data)
    }
  },
  (data) => {
    // Patch the updated ticket in-place
    const idx = localTickets.value.findIndex(t => t.id === data.id)
    if (idx !== -1) {
      localTickets.value[idx] = { ...localTickets.value[idx], ...data }
    }
  },
)

const activeFilter = ref('all')

const filterOptions = computed(() => [
  { label: t('common.all'),                      value: 'all' },
  { label: t('helpdesk.status.open'),            value: 'open' },
  { label: t('helpdesk.status.in_progress'),     value: 'in_progress' },
  { label: t('helpdesk.status.resolved'),        value: 'resolved' },
  { label: t('helpdesk.status.closed'),          value: 'closed' },
])

const priorityOptions = computed(() => [
  { label: t('helpdesk.priority_levels.low'),    value: 'low' },
  { label: t('helpdesk.priority_levels.medium'), value: 'medium' },
  { label: t('helpdesk.priority_levels.high'),   value: 'high' },
  { label: t('helpdesk.priority_levels.urgent'), value: 'urgent' },
])

const channelOptions = [
  { label: 'Email',    value: 'email' },
  { label: 'Web',      value: 'web' },
  { label: 'WhatsApp', value: 'whatsapp' },
]

const stats = computed(() => [
  { label: t('helpdesk.status.open'),        value: localTickets.value.filter(t => t.status === 'open').length },
  { label: t('helpdesk.status.in_progress'), value: localTickets.value.filter(t => t.status === 'in_progress').length },
  { label: t('helpdesk.status.resolved'),    value: localTickets.value.filter(t => t.status === 'resolved').length },
  { label: t('helpdesk.status.closed'),      value: localTickets.value.filter(t => t.status === 'closed').length },
])

const filteredTickets = computed(() => {
  if (activeFilter.value === 'all') return localTickets.value
  return localTickets.value.filter(t => t.status === activeFilter.value)
})

const ticketStatusClass = (s) => ({
  open:        'wh-badge-red',
  in_progress: 'wh-badge-amber',
  resolved:    'wh-badge-green',
  closed:      'wh-badge-slate',
}[s] ?? 'wh-badge-slate')

const statusLabel = (s) => ({
  open:        t('helpdesk.status.open'),
  in_progress: t('helpdesk.status.in_progress'),
  resolved:    t('helpdesk.status.resolved'),
  closed:      t('helpdesk.status.closed'),
}[s] ?? s)

const priorityClass = (p) => ({
  low:      'wh-badge-slate',
  medium:   'wh-badge-blue',
  high:     'wh-badge-amber',
  critical: 'wh-badge-red',
}[p] ?? 'wh-badge-slate')

const priorityLabel = (p) => ({
  low:      t('helpdesk.priority_levels.low'),
  medium:   t('helpdesk.priority_levels.medium'),
  high:     t('helpdesk.priority_levels.high'),
  critical: t('helpdesk.priority_levels.urgent'),
}[p] ?? p)

const channelIcon = (c) => ({
  email:    'pi pi-envelope',
  web:      'pi pi-globe',
  whatsapp: 'pi pi-whatsapp',
}[c] ?? 'pi pi-question-circle')

const channelLabel = (c) => ({
  email: 'Email', web: 'Web', whatsapp: 'WhatsApp',
}[c] ?? c)

const formatDate = (d) => d ? new Date(d).toLocaleDateString('fr-FR') : '—'

const goToTicket = (id) => router.visit(`/helpdesk/tickets/${id}`)

// --- Create Ticket Form ---

const showCreateModal = ref(false)
const createSubmitting = ref(false)

const ticketSchema = toTypedSchema(z.object({
  subject:     z.string().min(1, t('common.required')).max(255),
  description: z.string().optional(),
  priority:    z.enum(['low', 'medium', 'high', 'urgent'], { errorMap: () => ({ message: t('common.required') }) }),
  channel:     z.enum(['email', 'web', 'whatsapp']).optional().nullable(),
}))

const { handleSubmit: handleCreateSubmit, defineField: defineCreateField, errors: createErrors, resetForm: resetCreateForm } = useForm({
  validationSchema: ticketSchema,
  initialValues: {
    subject: '',
    description: '',
    priority: 'medium',
    channel: null,
  },
})

const [subject, subjectAttrs]         = defineCreateField('subject')
const [description, descriptionAttrs] = defineCreateField('description')
const [priority, priorityAttrs]       = defineCreateField('priority')
const [channel, channelAttrs]         = defineCreateField('channel')

const onCreateSubmit = handleCreateSubmit(async (values) => {
  createSubmitting.value = true
  try {
    const payload = { ...values }
    if (!payload.description) delete payload.description
    if (!payload.channel) delete payload.channel
    const response = await fetch('/api/v1/helpdesk/tickets', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(payload),
    })
    if (response.ok) {
      showCreateModal.value = false
      resetCreateForm()
    }
  } finally {
    createSubmitting.value = false
  }
})
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; gap:16px; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; gap:8px; }
.btn { font-family:var(--font-sans); font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:background var(--dur-base) var(--ease-out); line-height:1.2; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover:not(:disabled) { background:var(--halo-700); }
.btn-primary:disabled { opacity:0.6; cursor:not-allowed; }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover { background:var(--bg-sunken); }
.filter-pill { height:28px; padding:0 12px; border-radius:var(--r-pill); border:1px solid var(--border-subtle); background:var(--bg-canvas); font-size:12px; font-weight:500; color:var(--fg-2); cursor:pointer; transition:all var(--dur-base); }
.filter-pill:hover { background:var(--bg-sunken); color:var(--fg-1); }
.filter-pill-on { background:var(--halo-50); border-color:var(--halo-200); color:var(--halo-700); }
.wh-dt { width:100%; border-collapse:collapse; font-size:14px; }
.wh-dt thead th { text-align:left; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:var(--fg-3); font-weight:600; padding:10px 18px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sunken); }
.wh-dt-row td { padding:11px 18px; border-bottom:1px solid var(--border-subtle); }
.wh-dt-row:last-child td { border-bottom:0; }
.wh-dt-row { cursor:pointer; transition:background var(--dur-fast); }
.wh-dt-row:hover { background:var(--bg-sunken); }
.priority-bar-cell { padding:0 !important; width:4px; }
.priority-bar { width:4px; height:100%; min-height:44px; border-radius:0; }
.priority-bar-low      { background:var(--fg-4); }
.priority-bar-medium   { background:var(--halo-500); }
.priority-bar-high     { background:var(--warn-fg); }
.priority-bar-critical { background:var(--danger-fg); }
.wh-row-btn { width:28px; height:28px; border-radius:var(--r-sm); border:1px solid var(--border-subtle); background:var(--bg-canvas); color:var(--fg-2); cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:all var(--dur-fast); }
.wh-row-btn:hover { background:var(--bg-sunken); color:var(--fg-1); border-color:var(--border-strong); }
.wh-badge { display:inline-flex; align-items:center; gap:5px; padding:2px 8px; border-radius:var(--r-pill); font-size:11px; font-weight:500; }
.wh-badge-dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
.wh-badge-green  { background:var(--success-bg); color:var(--success-fg); }
.wh-badge-blue   { background:var(--halo-50); color:var(--halo-700); }
.wh-badge-amber  { background:var(--warn-bg); color:var(--warn-fg); }
.wh-badge-red    { background:var(--danger-bg); color:var(--danger-fg); }
.wh-badge-slate  { background:var(--bg-sunken); color:var(--fg-2); }
.form-field { display:flex; flex-direction:column; gap:5px; }
.form-label { font-size:12px; font-weight:500; color:var(--fg-2); }
.form-error { font-size:11px; color:var(--danger-fg,#991B1B); }
</style>
