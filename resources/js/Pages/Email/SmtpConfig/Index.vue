<template>
  <AppLayout>
    <Head title="SMTP Configuration" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Email · SMTP Configuration</h1>
        <p class="wh-page-subtitle">Manage outgoing mail server settings</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="openCreate">
          <i class="pi pi-plus" /> New SMTP Config
        </button>
      </div>
    </div>

    <div class="wh-panel">
      <div v-if="loading" style="padding:40px;text-align:center">
        <i class="pi pi-spin pi-spinner" style="font-size:24px;color:var(--fg-3)" />
      </div>
      <table v-else class="wh-dt">
        <thead>
          <tr>
            <th>Name</th>
            <th>Host</th>
            <th>From</th>
            <th>Status</th>
            <th>Last test</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="cfg in configs" :key="cfg.id" class="wh-dt-row">
            <td>
              <p style="font-weight:500;margin:0">{{ cfg.name }}</p>
              <div style="display:flex;gap:4px;margin-top:3px">
                <span v-if="cfg.is_default" class="wh-badge badge-gold">Default</span>
                <span :class="cfg.is_active ? 'badge-green' : 'badge-gray'" class="wh-badge">
                  {{ cfg.is_active ? $t('common.active') : $t('common.inactive') }}
                </span>
              </div>
            </td>
            <td style="font-family:monospace;font-size:13px">{{ cfg.host }}:{{ cfg.port }} ({{ cfg.encryption }})</td>
            <td style="font-size:13px">
              <p style="margin:0">{{ cfg.from_name }}</p>
              <p style="color:var(--fg-3);margin:2px 0 0;font-size:12px">&lt;{{ cfg.from_email }}&gt;</p>
            </td>
            <td>
              <span v-if="cfg.test_result" :class="cfg.test_result.success ? 'badge-green' : 'badge-red'" class="wh-badge">
                <i :class="cfg.test_result.success ? 'pi pi-check' : 'pi pi-times'" />
                {{ cfg.test_result.success ? 'OK' : 'Failed' }}
              </span>
              <span v-else class="wh-badge badge-gray">Not tested</span>
            </td>
            <td style="font-size:12px;color:var(--fg-3)">{{ formatDate(cfg.last_tested_at) }}</td>
            <td>
              <div class="row-actions">
                <button
                  class="btn-sm btn-outline"
                  :disabled="testingId === cfg.id"
                  @click="testConnection(cfg)"
                  title="Test connection"
                >
                  <i :class="testingId === cfg.id ? 'pi pi-spin pi-spinner' : 'pi pi-wifi'" />
                  Test
                </button>
                <button v-if="!cfg.is_default" class="btn-sm btn-outline" @click="setDefault(cfg)" title="Set as default">
                  <i class="pi pi-star" />
                </button>
                <button class="btn-sm btn-outline" @click="promptSendTest(cfg)" title="Send test email">
                  <i class="pi pi-send" />
                </button>
                <button class="btn-sm btn-outline" @click="openEdit(cfg)" title="Edit">
                  <i class="pi pi-pencil" />
                </button>
                <button class="btn-sm btn-danger" @click="deleteConfig(cfg)" title="Delete">
                  <i class="pi pi-trash" />
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="configs.length === 0">
            <td colspan="6" style="text-align:center;padding:32px;color:var(--fg-3)">No SMTP configs yet</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
      <div class="modal">
        <div class="modal-header">
          <span>{{ editingConfig ? 'Edit SMTP Config' : 'New SMTP Config' }}</span>
          <button @click="closeModal"><i class="pi pi-times" /></button>
        </div>
        <div class="modal-body">
          <div class="form-grid">
            <div class="form-group full">
              <label>Name *</label>
              <input v-model="form.name" class="wh-input" placeholder="e.g. SendGrid Production" />
            </div>
            <div class="form-group">
              <label>Host *</label>
              <input v-model="form.host" class="wh-input" placeholder="smtp.sendgrid.net" />
            </div>
            <div class="form-group">
              <label>Port *</label>
              <input v-model.number="form.port" class="wh-input" type="number" placeholder="587" />
            </div>
            <div class="form-group full">
              <label>Encryption *</label>
              <div class="enc-selector">
                <button v-for="enc in ['tls','ssl','none']" :key="enc"
                  :class="['enc-btn', form.encryption === enc ? 'active' : '']"
                  @click="form.encryption = enc"
                >{{ enc.toUpperCase() }}</button>
              </div>
            </div>
            <div class="form-group">
              <label>Username *</label>
              <input v-model="form.username" class="wh-input" autocomplete="off" />
            </div>
            <div class="form-group">
              <label>Password *</label>
              <input v-model="form.password" class="wh-input" type="password" autocomplete="new-password" />
            </div>
            <div class="form-group">
              <label>From name *</label>
              <input v-model="form.from_name" class="wh-input" placeholder="WideHalo ERP" />
            </div>
            <div class="form-group">
              <label>From email *</label>
              <input v-model="form.from_email" class="wh-input" type="email" placeholder="noreply@example.com" />
            </div>
          </div>

          <!-- Test result -->
          <div v-if="modalTestResult" class="test-result-box" :class="modalTestResult.success ? 'success' : 'error'">
            <i :class="modalTestResult.success ? 'pi pi-check-circle' : 'pi pi-times-circle'" />
            {{ modalTestResult.success ? `Connected in ${modalTestResult.latency_ms}ms` : modalTestResult.error }}
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-ghost" @click="testModalConfig" :disabled="modalTesting">
            <i :class="modalTesting ? 'pi pi-spin pi-spinner' : 'pi pi-wifi'" />
            {{ modalTesting ? 'Testing…' : 'Test connection' }}
          </button>
          <div style="flex:1" />
          <button class="btn btn-ghost" @click="closeModal">{{ $t('common.cancel') }}</button>
          <button class="btn btn-primary" :disabled="submitting" @click="submitForm">
            {{ submitting ? 'Saving…' : (editingConfig ? 'Update' : 'Create') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Send test email modal -->
    <div v-if="showTestModal" class="modal-overlay" @click.self="showTestModal = false">
      <div class="modal modal-sm">
        <div class="modal-header">
          <span>Send test email via {{ testTargetConfig?.name }}</span>
          <button @click="showTestModal = false"><i class="pi pi-times" /></button>
        </div>
        <div class="modal-body">
          <label style="display:block;font-size:12px;font-weight:600;margin-bottom:6px">Recipient email</label>
          <input v-model="sendTestEmailAddr" class="wh-input" type="email" placeholder="test@example.com" />
          <button class="btn btn-primary" style="width:100%;margin-top:12px" :disabled="sendingTest" @click="doSendTest">
            {{ sendingTest ? 'Sending…' : 'Send test' }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

interface TestResult {
  success: boolean
  latency_ms: number
  error?: string | null
}

interface SmtpConfig {
  id: number
  name: string
  host: string
  port: number
  encryption: string
  username: string
  from_email: string
  from_name: string
  is_default: boolean
  is_active: boolean
  last_tested_at?: string
  test_result?: TestResult | null
}

interface ConfigForm {
  name: string
  host: string
  port: number
  encryption: string
  username: string
  password: string
  from_email: string
  from_name: string
  is_active: boolean
}

const configs = ref<SmtpConfig[]>([])
const loading = ref(false)
const showModal = ref(false)
const editingConfig = ref<SmtpConfig | null>(null)
const submitting = ref(false)
const testingId = ref<number | null>(null)
const modalTesting = ref(false)
const modalTestResult = ref<TestResult | null>(null)
const showTestModal = ref(false)
const testTargetConfig = ref<SmtpConfig | null>(null)
const sendTestEmailAddr = ref('')
const sendingTest = ref(false)

const defaultForm = (): ConfigForm => ({
  name: '',
  host: '',
  port: 587,
  encryption: 'tls',
  username: '',
  password: '',
  from_email: '',
  from_name: '',
  is_active: true,
})

const form = ref<ConfigForm>(defaultForm())

function formatDate(date?: string): string {
  if (!date) return '—'
  return new Date(date).toLocaleDateString()
}

function openCreate(): void {
  editingConfig.value = null
  form.value = defaultForm()
  modalTestResult.value = null
  showModal.value = true
}

function openEdit(cfg: SmtpConfig): void {
  editingConfig.value = cfg
  form.value = {
    name: cfg.name,
    host: cfg.host,
    port: cfg.port,
    encryption: cfg.encryption,
    username: cfg.username,
    password: '',
    from_email: cfg.from_email,
    from_name: cfg.from_name,
    is_active: cfg.is_active,
  }
  modalTestResult.value = null
  showModal.value = true
}

function closeModal(): void {
  showModal.value = false
}

async function submitForm(): Promise<void> {
  submitting.value = true
  try {
    const payload: Partial<ConfigForm> = { ...form.value }
    if (editingConfig.value && !payload.password) delete payload.password

    if (editingConfig.value) {
      await axios.put(`/api/v1/email/smtp-configs/${editingConfig.value.id}`, payload)
    } else {
      await axios.post('/api/v1/email/smtp-configs', payload)
    }

    closeModal()
    await loadConfigs()
  } finally {
    submitting.value = false
  }
}

async function testConnection(cfg: SmtpConfig): Promise<void> {
  testingId.value = cfg.id
  try {
    await axios.post(`/api/v1/email/smtp-configs/${cfg.id}/test`)
    await loadConfigs()
  } finally {
    testingId.value = null
  }
}

async function testModalConfig(): Promise<void> {
  if (editingConfig.value) {
    modalTesting.value = true
    try {
      const res = await axios.post(`/api/v1/email/smtp-configs/${editingConfig.value.id}/test`)
      modalTestResult.value = res.data
    } finally {
      modalTesting.value = false
    }
  }
}

async function setDefault(cfg: SmtpConfig): Promise<void> {
  await axios.post(`/api/v1/email/smtp-configs/${cfg.id}/set-default`)
  await loadConfigs()
}

function promptSendTest(cfg: SmtpConfig): void {
  testTargetConfig.value = cfg
  sendTestEmailAddr.value = ''
  showTestModal.value = true
}

async function doSendTest(): Promise<void> {
  if (!testTargetConfig.value) return
  sendingTest.value = true
  try {
    await axios.post(`/api/v1/email/smtp-configs/${testTargetConfig.value.id}/send-test`, {
      email: sendTestEmailAddr.value,
    })
    showTestModal.value = false
  } finally {
    sendingTest.value = false
  }
}

async function deleteConfig(cfg: SmtpConfig): Promise<void> {
  if (!confirm(`Delete SMTP config "${cfg.name}"?`)) return
  await axios.delete(`/api/v1/email/smtp-configs/${cfg.id}`)
  await loadConfigs()
}

async function loadConfigs(): Promise<void> {
  loading.value = true
  try {
    const res = await axios.get('/api/v1/email/smtp-configs')
    configs.value = res.data
  } finally {
    loading.value = false
  }
}

onMounted(loadConfigs)
</script>

<style scoped>
.page-head { display: flex; justify-content: space-between; align-items: flex-start; padding: 20px 24px; }
.wh-page-title { font-size: 22px; font-weight: 700; margin: 0; }
.wh-page-subtitle { font-size: 14px; color: var(--fg-3, #6b7280); margin: 4px 0 0; }
.page-actions { display: flex; gap: 8px; }
.wh-panel { background: #fff; border-radius: 10px; border: 1px solid var(--border-1, #e5e7eb); margin: 0 24px 24px; overflow: hidden; }
.wh-dt { width: 100%; border-collapse: collapse; }
.wh-dt th { padding: 10px 14px; font-size: 12px; font-weight: 600; color: var(--fg-3, #6b7280); text-transform: uppercase; letter-spacing: .04em; border-bottom: 1px solid var(--border-1, #e5e7eb); text-align: left; }
.wh-dt-row td { padding: 12px 14px; border-bottom: 1px solid var(--border-2, #f3f4f6); font-size: 14px; }
.wh-dt-row:last-child td { border-bottom: none; }

.wh-badge { display: inline-flex; align-items: center; gap: 3px; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 500; }
.badge-gold { background: #fef9c3; color: #ca8a04; }
.badge-green { background: #dcfce7; color: #16a34a; }
.badge-gray { background: #f3f4f6; color: #6b7280; }
.badge-red { background: #fee2e2; color: #dc2626; }

.row-actions { display: flex; gap: 4px; align-items: center; flex-wrap: wrap; }
.btn { padding: 7px 14px; border-radius: 6px; font-size: 14px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
.btn-primary { background: var(--primary-600, #2563eb); color: #fff; }
.btn-primary:hover { background: var(--primary-700, #1d4ed8); }
.btn-primary:disabled { opacity: .6; cursor: default; }
.btn-ghost { background: transparent; border: 1px solid var(--border-1, #d1d5db); color: var(--fg-2, #374151); }
.btn-sm { padding: 4px 8px; border-radius: 5px; font-size: 12px; cursor: pointer; border: 1px solid var(--border-1, #d1d5db); background: transparent; display: inline-flex; align-items: center; gap: 4px; }
.btn-sm:disabled { opacity: .5; cursor: default; }
.btn-outline { color: var(--fg-2, #374151); }
.btn-danger { background: #fee2e2; color: #dc2626; border-color: #fca5a5; }

.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.5); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal { background: #fff; border-radius: 10px; width: 600px; max-height: 80vh; display: flex; flex-direction: column; }
.modal-sm { width: 380px; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; border-bottom: 1px solid #e5e7eb; font-weight: 600; }
.modal-header button { background: none; border: none; cursor: pointer; font-size: 16px; }
.modal-body { padding: 20px; overflow-y: auto; flex: 1; }
.modal-footer { display: flex; align-items: center; gap: 8px; padding: 14px 20px; border-top: 1px solid #e5e7eb; }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-group { display: flex; flex-direction: column; }
.form-group.full { grid-column: 1 / -1; }
.form-group label { font-size: 12px; font-weight: 600; color: var(--fg-2, #374151); margin-bottom: 5px; }
.wh-input { width: 100%; border: 1px solid var(--border-1, #d1d5db); border-radius: 6px; padding: 7px 10px; font-size: 13px; box-sizing: border-box; }

.enc-selector { display: flex; gap: 8px; }
.enc-btn { flex: 1; padding: 7px; border: 1px solid var(--border-1, #d1d5db); border-radius: 6px; cursor: pointer; background: transparent; font-size: 13px; font-weight: 500; }
.enc-btn.active { background: var(--primary-50, #eff6ff); border-color: var(--primary-500, #3b82f6); color: var(--primary-700, #1d4ed8); }

.test-result-box { margin-top: 14px; padding: 10px 14px; border-radius: 6px; font-size: 13px; display: flex; align-items: center; gap: 8px; }
.test-result-box.success { background: #dcfce7; color: #166534; }
.test-result-box.error { background: #fee2e2; color: #991b1b; }
</style>
