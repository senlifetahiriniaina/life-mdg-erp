<template>
  <AppLayout>
    <Head :title="$t('helpdesk.sla.title')" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">{{ $t('helpdesk.sla.title') }}</h1>
        <p class="wh-page-subtitle">{{ $t('helpdesk.sla.subtitle') }}</p>
      </div>
    </div>

    <AiAssistantPanel v-if="guidance" :guidance="guidance" />

    <!-- Tabs -->
    <div class="tabs">
      <button class="tab" :class="{ 'tab-active': activeTab === 'sla' }" @click="activeTab = 'sla'">
        <i class="pi pi-clock" style="font-size:13px" />
        {{ $t('helpdesk.sla.policies') }}
        <span class="tab-badge">{{ slaPolicies.length }}</span>
      </button>
      <button class="tab" :class="{ 'tab-active': activeTab === 'rules' }" @click="activeTab = 'rules'">
        <i class="pi pi-sitemap" style="font-size:13px" />
        {{ $t('helpdesk.escalation.rules') }}
        <span class="tab-badge">{{ escalationRules.length }}</span>
      </button>
    </div>

    <!-- SLA Policies Tab -->
    <div v-if="activeTab === 'sla'" class="wh-panel">
      <div class="panel-toolbar">
        <span class="panel-count">{{ slaPolicies.length }} {{ $t('helpdesk.sla.policies') }}</span>
        <button class="btn btn-primary" @click="openCreateSla">
          <i class="pi pi-plus" style="font-size:12px" />
          {{ $t('helpdesk.sla.new_policy') }}
        </button>
      </div>
      <table class="wh-dt">
        <thead>
          <tr>
            <th>{{ $t('common.name') }}</th>
            <th>{{ $t('helpdesk.sla.priority_level') }}</th>
            <th>{{ $t('helpdesk.sla.first_response') }}</th>
            <th>{{ $t('helpdesk.sla.resolution') }}</th>
            <th>{{ $t('helpdesk.sla.business_hours') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in slaPolicies" :key="p.id" class="wh-dt-row">
            <td>
              <div style="display:flex;align-items:center;gap:8px">
                {{ p.name }}
                <span v-if="p.is_default" class="wh-badge wh-badge-blue">{{ $t('helpdesk.sla.default') }}</span>
              </div>
            </td>
            <td>
              <span :class="['wh-badge', priorityBadge(p.priority)]">{{ p.priority }}</span>
            </td>
            <td style="font-variant-numeric:tabular-nums">{{ p.first_response_hours }}h</td>
            <td style="font-variant-numeric:tabular-nums">{{ p.resolution_hours }}h</td>
            <td>
              <span v-if="p.business_hours_only" class="wh-badge wh-badge-green">{{ $t('common.yes') }}</span>
              <span v-else class="wh-badge wh-badge-slate">{{ $t('common.no') }}</span>
            </td>
            <td>
              <div style="display:flex;gap:6px">
                <button class="wh-row-btn" @click="editSla(p)"><i class="pi pi-pencil" style="font-size:12px" /></button>
                <button class="wh-row-btn wh-row-btn-danger" @click="deleteSla(p.id)"><i class="pi pi-trash" style="font-size:12px" /></button>
              </div>
            </td>
          </tr>
          <tr v-if="slaPolicies.length === 0">
            <td colspan="6" class="empty-cell">{{ $t('common.no_results') }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Escalation Rules Tab -->
    <div v-if="activeTab === 'rules'" class="wh-panel">
      <div class="panel-toolbar">
        <span class="panel-count">{{ escalationRules.length }} {{ $t('helpdesk.escalation.rules') }}</span>
        <button class="btn btn-primary" @click="openCreateRule">
          <i class="pi pi-plus" style="font-size:12px" />
          {{ $t('helpdesk.escalation.new_rule') }}
        </button>
      </div>
      <table class="wh-dt">
        <thead>
          <tr>
            <th>{{ $t('common.name') }}</th>
            <th>{{ $t('helpdesk.escalation.trigger') }}</th>
            <th>{{ $t('helpdesk.escalation.trigger_hours') }}</th>
            <th>{{ $t('helpdesk.escalation.action') }}</th>
            <th>{{ $t('common.status') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in escalationRules" :key="r.id" class="wh-dt-row">
            <td style="font-weight:500">{{ r.name }}</td>
            <td>
              <span class="wh-badge wh-badge-slate">{{ r.trigger_type }}</span>
            </td>
            <td style="font-variant-numeric:tabular-nums">{{ r.trigger_hours }}h</td>
            <td>
              <span :class="['wh-badge', actionBadge(r.action_type)]">{{ r.action_type }}</span>
            </td>
            <td>
              <span v-if="r.is_active" class="wh-badge wh-badge-green">{{ $t('common.active') }}</span>
              <span v-else class="wh-badge wh-badge-slate">{{ $t('common.inactive') }}</span>
            </td>
            <td>
              <div style="display:flex;gap:6px">
                <button class="wh-row-btn" @click="editRule(r)"><i class="pi pi-pencil" style="font-size:12px" /></button>
                <button class="wh-row-btn wh-row-btn-danger" @click="deleteRule(r.id)"><i class="pi pi-trash" style="font-size:12px" /></button>
              </div>
            </td>
          </tr>
          <tr v-if="escalationRules.length === 0">
            <td colspan="6" class="empty-cell">{{ $t('common.no_results') }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- SLA Policy Modal -->
    <Dialog v-model:visible="showSlaModal" modal :header="editingSla ? $t('helpdesk.sla.edit_policy') : $t('helpdesk.sla.new_policy')" :style="{ width: '480px' }">
      <form @submit.prevent="saveSla" style="display:flex;flex-direction:column;gap:14px;padding-top:4px">
        <div class="form-field">
          <label class="form-label">{{ $t('common.name') }} *</label>
          <InputText v-model="slaForm.name" class="w-full" required />
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-field">
            <label class="form-label">{{ $t('helpdesk.sla.priority_level') }} *</label>
            <Select v-model="slaForm.priority" :options="priorityOptions" option-label="label" option-value="value" class="w-full" />
          </div>
          <div class="form-field">
            <label class="form-label">{{ $t('helpdesk.sla.first_response') }} (h) *</label>
            <InputNumber v-model="slaForm.first_response_hours" :min="0" :max-fraction-digits="2" class="w-full" />
          </div>
        </div>
        <div class="form-field">
          <label class="form-label">{{ $t('helpdesk.sla.resolution') }} (h) *</label>
          <InputNumber v-model="slaForm.resolution_hours" :min="0" :max-fraction-digits="2" class="w-full" />
        </div>
        <div style="display:flex;gap:20px">
          <label class="checkbox-label">
            <input type="checkbox" v-model="slaForm.business_hours_only" />
            {{ $t('helpdesk.sla.business_hours') }}
          </label>
          <label class="checkbox-label">
            <input type="checkbox" v-model="slaForm.is_default" />
            {{ $t('helpdesk.sla.default') }}
          </label>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;border-top:1px solid var(--border-subtle);padding-top:12px">
          <button type="button" class="btn btn-secondary" @click="showSlaModal = false">{{ $t('common.cancel') }}</button>
          <button type="submit" class="btn btn-primary" :disabled="slaSubmitting">{{ $t('common.save') }}</button>
        </div>
      </form>
    </Dialog>

    <!-- Escalation Rule Modal -->
    <Dialog v-model:visible="showRuleModal" modal :header="editingRule ? $t('helpdesk.escalation.edit_rule') : $t('helpdesk.escalation.new_rule')" :style="{ width: '520px' }">
      <form @submit.prevent="saveRule" style="display:flex;flex-direction:column;gap:14px;padding-top:4px">
        <div class="form-field">
          <label class="form-label">{{ $t('common.name') }} *</label>
          <InputText v-model="ruleForm.name" class="w-full" required />
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-field">
            <label class="form-label">{{ $t('helpdesk.escalation.trigger') }} *</label>
            <Select v-model="ruleForm.trigger_type" :options="triggerOptions" option-label="label" option-value="value" class="w-full" />
          </div>
          <div class="form-field">
            <label class="form-label">{{ $t('helpdesk.escalation.trigger_hours') }} *</label>
            <InputNumber v-model="ruleForm.trigger_hours" :min="0" :max-fraction-digits="2" class="w-full" />
          </div>
        </div>
        <div class="form-field">
          <label class="form-label">{{ $t('helpdesk.escalation.action') }} *</label>
          <Select v-model="ruleForm.action_type" :options="actionOptions" option-label="label" option-value="value" class="w-full" />
        </div>
        <div class="form-field">
          <label class="form-label">{{ $t('helpdesk.escalation.action_config') }} (JSON) *</label>
          <Textarea v-model="ruleForm.action_config_str" rows="3" class="w-full" placeholder='{"priority":"high"}' />
        </div>
        <label class="checkbox-label">
          <input type="checkbox" v-model="ruleForm.is_active" />
          {{ $t('common.active') }}
        </label>
        <div style="display:flex;justify-content:flex-end;gap:8px;border-top:1px solid var(--border-subtle);padding-top:12px">
          <button type="button" class="btn btn-secondary" @click="showRuleModal = false">{{ $t('common.cancel') }}</button>
          <button type="submit" class="btn btn-primary" :disabled="ruleSubmitting">{{ $t('common.save') }}</button>
        </div>
      </form>
    </Dialog>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import AppLayout from '@/Layouts/AppLayout.vue'
import AiAssistantPanel from '@/Components/AI/AiAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'

const { t } = useI18n()
const { guidance } = useAiAssistant('Helpdesk', 'escalation_config')

// Chantier 32.21: this admin config page's 4 mutating fetch() calls
// (save/delete SLA policy, save/delete escalation rule) sent no CSRF token
// at all — every real save/delete on this page 419'd in a real browser.
function getCsrf() {
  return document.querySelector('meta[name="csrf-token"]')?.content ?? ''
}

const props = defineProps({
  slaPolicies:     { type: Array, default: () => [] },
  escalationRules: { type: Array, default: () => [] },
})

const slaPolicies     = ref([...props.slaPolicies])
const escalationRules = ref([...props.escalationRules])
const activeTab       = ref('sla')

// SLA Modal
const showSlaModal  = ref(false)
const editingSla    = ref(null)
const slaSubmitting = ref(false)
const slaForm = ref({ name: '', priority: 'medium', first_response_hours: 4, resolution_hours: 24, business_hours_only: false, is_default: false })

// Rule Modal
const showRuleModal  = ref(false)
const editingRule    = ref(null)
const ruleSubmitting = ref(false)
const ruleForm = ref({ name: '', trigger_type: 'first_response_overdue', trigger_hours: 4, action_type: 'notify', action_config_str: '{}', is_active: true })

const priorityOptions = [
  { label: t('helpdesk.priority_levels.low'),      value: 'low' },
  { label: t('helpdesk.priority_levels.medium'),   value: 'medium' },
  { label: t('helpdesk.priority_levels.high'),     value: 'high' },
  { label: t('helpdesk.priority_levels.urgent'),   value: 'critical' },
]

const triggerOptions = [
  { label: t('helpdesk.escalation.trigger_first_response'), value: 'first_response_overdue' },
  { label: t('helpdesk.escalation.trigger_resolution'),     value: 'resolution_overdue' },
  { label: t('helpdesk.escalation.trigger_no_activity'),    value: 'no_activity' },
  { label: t('helpdesk.escalation.trigger_custom'),         value: 'custom' },
]

const actionOptions = [
  { label: t('helpdesk.escalation.action_reassign'),        value: 'reassign' },
  { label: t('helpdesk.escalation.action_notify'),          value: 'notify' },
  { label: t('helpdesk.escalation.action_change_priority'), value: 'change_priority' },
  { label: t('helpdesk.escalation.action_add_tag'),         value: 'add_tag' },
]

function priorityBadge(p) {
  return { low: 'wh-badge-slate', medium: 'wh-badge-blue', high: 'wh-badge-amber', critical: 'wh-badge-red' }[p] ?? 'wh-badge-slate'
}

function actionBadge(a) {
  return { reassign: 'wh-badge-amber', notify: 'wh-badge-blue', change_priority: 'wh-badge-red', add_tag: 'wh-badge-green' }[a] ?? 'wh-badge-slate'
}

function openCreateSla() {
  editingSla.value = null
  slaForm.value = { name: '', priority: 'medium', first_response_hours: 4, resolution_hours: 24, business_hours_only: false, is_default: false }
  showSlaModal.value = true
}

function editSla(p) {
  editingSla.value = p
  slaForm.value = { ...p }
  showSlaModal.value = true
}

async function saveSla() {
  slaSubmitting.value = true
  try {
    const method = editingSla.value ? 'PUT' : 'POST'
    const url = editingSla.value ? `/api/v1/helpdesk/sla-policies/${editingSla.value.id}` : '/api/v1/helpdesk/sla-policies'
    const res = await fetch(url, {
      method,
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
      body: JSON.stringify(slaForm.value),
    })
    if (res.ok) {
      const data = await res.json()
      if (editingSla.value) {
        const idx = slaPolicies.value.findIndex(p => p.id === data.id)
        if (idx !== -1) slaPolicies.value[idx] = data
      } else {
        slaPolicies.value.push(data)
      }
      showSlaModal.value = false
    }
  } finally {
    slaSubmitting.value = false
  }
}

async function deleteSla(id) {
  if (!confirm(t('common.confirm_delete'))) return
  const res = await fetch(`/api/v1/helpdesk/sla-policies/${id}`, { method: 'DELETE', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrf() } })
  if (res.status === 204) {
    slaPolicies.value = slaPolicies.value.filter(p => p.id !== id)
  }
}

function openCreateRule() {
  editingRule.value = null
  ruleForm.value = { name: '', trigger_type: 'first_response_overdue', trigger_hours: 4, action_type: 'notify', action_config_str: '{}', is_active: true }
  showRuleModal.value = true
}

function editRule(r) {
  editingRule.value = r
  ruleForm.value = { ...r, action_config_str: JSON.stringify(r.action_config ?? {}) }
  showRuleModal.value = true
}

async function saveRule() {
  ruleSubmitting.value = true
  try {
    let actionConfig = {}
    try { actionConfig = JSON.parse(ruleForm.value.action_config_str || '{}') } catch { /* invalid JSON */ }

    const payload = { ...ruleForm.value, action_config: actionConfig }
    delete payload.action_config_str

    const method = editingRule.value ? 'PUT' : 'POST'
    const url = editingRule.value ? `/api/v1/helpdesk/escalation-rules/${editingRule.value.id}` : '/api/v1/helpdesk/escalation-rules'
    const res = await fetch(url, {
      method,
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
      body: JSON.stringify(payload),
    })
    if (res.ok) {
      const data = await res.json()
      if (editingRule.value) {
        const idx = escalationRules.value.findIndex(r => r.id === data.id)
        if (idx !== -1) escalationRules.value[idx] = data
      } else {
        escalationRules.value.push(data)
      }
      showRuleModal.value = false
    }
  } finally {
    ruleSubmitting.value = false
  }
}

async function deleteRule(id) {
  if (!confirm(t('common.confirm_delete'))) return
  const res = await fetch(`/api/v1/helpdesk/escalation-rules/${id}`, { method: 'DELETE', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrf() } })
  if (res.status === 204) {
    escalationRules.value = escalationRules.value.filter(r => r.id !== id)
  }
}
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; gap:16px; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }

.tabs { display:flex; gap:4px; margin-bottom:16px; border-bottom:1px solid var(--border-subtle); }
.tab { padding:9px 16px; border:none; background:none; cursor:pointer; font-size:13px; font-weight:500; color:var(--fg-2); border-bottom:2px solid transparent; margin-bottom:-1px; display:inline-flex;align-items:center;gap:6px; }
.tab:hover { color:var(--fg-1); }
.tab-active { color:var(--halo-600); border-bottom-color:var(--halo-500); }
.tab-badge { background:var(--bg-sunken);color:var(--fg-2);border-radius:var(--r-pill);padding:1px 7px;font-size:11px; }

.wh-panel { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); overflow:hidden; }
.panel-toolbar { display:flex; align-items:center; justify-content:space-between; padding:12px 16px; border-bottom:1px solid var(--border-subtle); }
.panel-count { font-size:13px; color:var(--fg-3); }

.wh-dt { width:100%; border-collapse:collapse; font-size:14px; }
.wh-dt thead th { text-align:left; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:var(--fg-3); font-weight:600; padding:10px 18px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sunken); }
.wh-dt-row td { padding:11px 18px; border-bottom:1px solid var(--border-subtle); }
.wh-dt-row:last-child td { border-bottom:0; }
.wh-dt-row:hover { background:var(--bg-sunken); }
.empty-cell { text-align:center; padding:40px; color:var(--fg-3); font-size:13px; }

.btn { font-weight:500; font-size:13px; padding:7px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex;align-items:center;gap:6px; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover:not(:disabled) { background:var(--halo-700); }
.btn-primary:disabled { opacity:0.5; cursor:not-allowed; }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover { background:var(--bg-sunken); }

.wh-row-btn { width:28px;height:28px;border-radius:var(--r-sm);border:1px solid var(--border-subtle);background:var(--bg-canvas);color:var(--fg-2);cursor:pointer;display:inline-flex;align-items:center;justify-content:center; }
.wh-row-btn:hover { background:var(--bg-sunken);color:var(--fg-1); }
.wh-row-btn-danger:hover { border-color:var(--danger-fg);color:var(--danger-fg); }

.wh-badge { display:inline-flex;align-items:center;gap:5px;padding:2px 8px;border-radius:var(--r-pill);font-size:11px;font-weight:500; }
.wh-badge-green  { background:var(--success-bg);color:var(--success-fg); }
.wh-badge-blue   { background:var(--halo-50);color:var(--halo-700); }
.wh-badge-amber  { background:var(--warn-bg);color:var(--warn-fg); }
.wh-badge-red    { background:var(--danger-bg);color:var(--danger-fg); }
.wh-badge-slate  { background:var(--bg-sunken);color:var(--fg-2); }

.form-field { display:flex;flex-direction:column;gap:5px; }
.form-label { font-size:12px;font-weight:500;color:var(--fg-2); }
.checkbox-label { display:flex;align-items:center;gap:8px;font-size:13px;color:var(--fg-1);cursor:pointer; }
.w-full { width:100%; }
</style>
