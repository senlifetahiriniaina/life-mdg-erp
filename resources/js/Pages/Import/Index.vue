<template>
  <AppLayout>
    <div class="import-page">
      <div class="page-header">
        <h1><i class="pi pi-upload" /> {{ $t('import.title') }}</h1>
        <p class="subtitle">Upload CSV, XLSX, PDF or image files — AI will map your data automatically.</p>
      </div>

      <!-- Step indicators -->
      <div class="steps-bar">
        <div
          v-for="(step, i) in steps"
          :key="step.key"
          :class="['step-item', currentStep === i ? 'active' : '', currentStep > i ? 'done' : '']"
        >
          <div class="step-circle">
            <i v-if="currentStep > i" class="pi pi-check" />
            <span v-else>{{ i + 1 }}</span>
          </div>
          <span class="step-label">{{ step.label }}</span>
        </div>
      </div>

      <!-- STEP 1: Upload -->
      <div v-if="currentStep === 0" class="step-panel">
        <div
          class="dropzone"
          :class="{ 'drag-over': dragging }"
          @dragover.prevent="dragging = true"
          @dragleave="dragging = false"
          @drop.prevent="onDrop"
          @click="$refs.fileInput.click()"
        >
          <i class="pi pi-cloud-upload upload-icon" />
          <p class="dropzone-text">Drag &amp; drop your file here, or <strong>click to browse</strong></p>
          <div class="file-badges">
            <span v-for="ext in ['CSV', 'XLSX', 'PDF', 'PNG', 'JPG']" :key="ext" class="badge">{{ ext }}</span>
          </div>
          <input
            ref="fileInput"
            type="file"
            accept=".csv,.xlsx,.pdf,.png,.jpg,.jpeg"
            style="display: none"
            @change="onFileSelected"
          />
        </div>

        <div v-if="selectedFile" class="selected-file">
          <i class="pi pi-file" /> {{ selectedFile.name }}
          <span class="file-size">({{ (selectedFile.size / 1024).toFixed(1) }} KB)</span>
        </div>

        <div class="entity-selector">
          <label>{{ $t('import.target_entity') }}</label>
          <div class="entity-grid">
            <button
              v-for="(label, key) in entities"
              :key="key"
              :class="['entity-btn', targetEntity === key ? 'selected' : '']"
              @click="targetEntity = key"
            >
              <i :class="entityIcons[key]" />
              {{ label }}
            </button>
          </div>
        </div>

        <button
          class="btn-primary"
          :disabled="!selectedFile || !targetEntity || uploading"
          @click="uploadFile"
        >
          <i v-if="uploading" class="pi pi-spin pi-spinner" />
          <i v-else class="pi pi-upload" />
          {{ uploading ? 'Uploading...' : $t('import.upload') }}
        </button>

        <div v-if="uploadError" class="error-banner">{{ uploadError }}</div>
      </div>

      <!-- STEP 2: Extracting -->
      <div v-if="currentStep === 1" class="step-panel center">
        <div class="spinner-block">
          <i class="pi pi-spin pi-spinner large-spinner" />
          <p>{{ statusLabel }}</p>
        </div>
        <div v-if="currentJob" class="status-badge" :class="currentJob.status">
          {{ currentJob.status }}
        </div>
      </div>

      <!-- STEP 3: Mapping -->
      <div v-if="currentStep === 2" class="step-panel">
        <div class="mapping-layout">
          <!-- Left: data preview -->
          <div class="preview-panel">
            <h3>Data Preview (first 5 rows)</h3>
            <div v-if="previewRows.length" class="table-scroll">
              <table class="preview-table">
                <thead>
                  <tr>
                    <th v-for="col in previewColumns" :key="col">{{ col }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, i) in previewRows" :key="i">
                    <td v-for="col in previewColumns" :key="col">{{ row.raw_data?.[col] ?? '—' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p v-else class="muted">No preview available.</p>
          </div>

          <!-- Right: mapping -->
          <div class="mapping-panel">
            <div class="mapping-header">
              <h3>{{ $t('import.mapping') }}</h3>
              <div v-if="aiConfidence" class="confidence-badge">
                ✨ {{ $t('import.confidence') }}: {{ Math.round(aiConfidence * 100) }}%
              </div>
            </div>

            <table class="mapping-table">
              <thead>
                <tr>
                  <th>ERP Field</th>
                  <th></th>
                  <th>CSV Column</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="field in entityFields" :key="field">
                  <td>
                    {{ field }}
                    <span v-if="isRequired(field)" class="required-star">*</span>
                  </td>
                  <td><i class="pi pi-arrow-left mapping-arrow" /></td>
                  <td>
                    <select v-model="userMapping[field]" class="mapping-select">
                      <option value="">— Unmapped —</option>
                      <option v-for="col in availableColumns" :key="col" :value="col">
                        {{ col }}
                        <template v-if="aiSuggestedFor(col, field)"> ✨</template>
                      </option>
                    </select>
                    <span v-if="aiSuggestedForField(field)" class="ai-badge">✨ AI</span>
                  </td>
                </tr>
              </tbody>
            </table>

            <button class="btn-primary" :disabled="savingMapping" @click="confirmMapping">
              <i v-if="savingMapping" class="pi pi-spin pi-spinner" />
              Confirm mapping
            </button>
          </div>
        </div>
      </div>

      <!-- STEP 4: Import & Results -->
      <div v-if="currentStep === 3" class="step-panel">
        <div v-if="!importStarted" class="import-start">
          <p>Your data is ready to import. Click below to start.</p>
          <button class="btn-primary" @click="startImport">
            <i class="pi pi-play" /> {{ $t('import.execute') }}
          </button>
        </div>

        <div v-if="importStarted" class="import-progress">
          <div v-if="currentJob?.status !== 'completed' && currentJob?.status !== 'failed'" class="progress-info">
            <i class="pi pi-spin pi-spinner" />
            Importing row {{ currentJob?.processed_rows ?? 0 }} of {{ currentJob?.total_rows ?? '...' }}...
          </div>

          <div v-if="currentJob?.status === 'completed' || currentJob?.status === 'failed'" class="results-summary">
            <div class="result-card success">
              <i class="pi pi-check-circle" />
              <strong>{{ $t('import.success_count') }}:</strong> {{ currentJob?.processed_rows ?? 0 }}
            </div>
            <div class="result-card error">
              <i class="pi pi-times-circle" />
              <strong>{{ $t('import.failed_count') }}:</strong> {{ currentJob?.failed_rows ?? 0 }}
            </div>

            <div v-if="failedRows.length" class="failed-rows">
              <h4>Failed rows</h4>
              <table class="preview-table">
                <thead>
                  <tr><th>#</th><th>Error</th></tr>
                </thead>
                <tbody>
                  <tr v-for="row in failedRows" :key="row.id">
                    <td>{{ row.row_index }}</td>
                    <td>{{ row.error_message }}</td>
                  </tr>
                </tbody>
              </table>
              <button class="btn-secondary" @click="downloadErrors">Download error report</button>
            </div>

            <div class="action-row">
              <button class="btn-danger" @click="rollback">
                <i class="pi pi-undo" /> {{ $t('import.rollback') }}
              </button>
              <button class="btn-secondary" @click="resetWizard">
                <i class="pi pi-plus" /> Import another file
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent imports -->
      <div class="recent-imports">
        <h3>Recent imports</h3>
        <table v-if="recentJobs.length" class="preview-table">
          <thead>
            <tr>
              <th>File</th>
              <th>Entity</th>
              <th>Status</th>
              <th>Rows</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="job in recentJobs" :key="job.id">
              <td>{{ job.filename }}</td>
              <td>{{ job.target_entity }}</td>
              <td><span :class="['status-badge', job.status]">{{ job.status }}</span></td>
              <td>{{ job.total_rows ?? '—' }}</td>
              <td>{{ formatDate(job.created_at) }}</td>
            </tr>
          </tbody>
        </table>
        <p v-else class="muted">No recent imports.</p>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'

const { t } = useI18n()

// ── State ──────────────────────────────────────────────────────────────────────
const currentStep   = ref(0)
const selectedFile  = ref<File | null>(null)
const targetEntity  = ref('')
const dragging      = ref(false)
const uploading     = ref(false)
const uploadError   = ref('')
const savingMapping = ref(false)
const importStarted = ref(false)

const currentJob  = ref<Record<string, unknown> | null>(null)
const previewRows = ref<Record<string, unknown>[]>([])
const userMapping = ref<Record<string, string>>({})
const failedRows  = ref<Record<string, unknown>[]>([])
const recentJobs  = ref<Record<string, unknown>[]>([])

let pollInterval: ReturnType<typeof setInterval> | null = null

// ── Constants ──────────────────────────────────────────────────────────────────
const steps = [
  { key: 'upload',    label: 'Upload' },
  { key: 'extract',   label: 'Extract' },
  { key: 'mapping',   label: 'Mapping' },
  { key: 'import',    label: 'Import' },
]

const entities: Record<string, string> = {
  contact:  t('import.entities.contact'),
  lead:     t('import.entities.lead'),
  product:  t('import.entities.product'),
  employee: t('import.entities.employee'),
  supplier: t('import.entities.supplier'),
  invoice:  t('import.entities.invoice'),
}

const entityIcons: Record<string, string> = {
  contact:  'pi pi-user',
  lead:     'pi pi-star',
  product:  'pi pi-box',
  employee: 'pi pi-id-card',
  supplier: 'pi pi-truck',
  invoice:  'pi pi-receipt',
}

const requiredFields: Record<string, string[]> = {
  contact:  ['first_name', 'last_name'],
  lead:     ['title'],
  product:  ['name', 'sku'],
  employee: ['first_name', 'last_name', 'email'],
  supplier: ['name'],
  invoice:  ['number', 'customer_email', 'amount'],
}

// ── Computed ───────────────────────────────────────────────────────────────────
const statusLabel = computed(() => {
  const s = (currentJob.value?.status as string) ?? ''
  const labels: Record<string, string> = {
    uploaded:   'File uploaded, extracting data...',
    extracting: 'Extracting data from file...',
    extracted:  'Data extracted, generating AI mapping...',
    mapping:    'AI is mapping your columns...',
    mapped:     'Mapping complete!',
  }
  return labels[s] ?? 'Processing...'
})

const aiSuggestions = computed(() => {
  const sug = currentJob.value?.ai_suggestions as Record<string, unknown> | null
  return (sug?.column_mapping as Record<string, string> | null) ?? {}
})

const aiConfidence = computed(() => {
  const sug = currentJob.value?.ai_suggestions as Record<string, unknown> | null
  return (sug?.confidence as number | null) ?? null
})

const availableColumns = computed(() => Object.keys(aiSuggestions.value))

const entityFields = computed<string[]>(() => {
  const fieldMap: Record<string, string[]> = {
    contact:  ['first_name', 'last_name', 'email', 'phone', 'job_title', 'company', 'status'],
    lead:     ['title', 'status', 'source', 'score', 'email', 'phone', 'owner_email'],
    product:  ['name', 'sku', 'price', 'stock', 'category', 'description'],
    employee: ['first_name', 'last_name', 'email', 'department', 'job_title', 'hire_date', 'salary'],
    supplier: ['name', 'email', 'phone', 'country', 'currency', 'payment_terms'],
    invoice:  ['number', 'customer_email', 'amount', 'tax', 'currency', 'date', 'due_date'],
  }
  return fieldMap[targetEntity.value] ?? []
})

const previewColumns = computed(() => {
  if (!previewRows.value.length) return []
  const raw = previewRows.value[0]?.raw_data as Record<string, unknown> | undefined
  return raw ? Object.keys(raw) : []
})

// ── Methods ────────────────────────────────────────────────────────────────────
function onDrop(e: DragEvent) {
  dragging.value = false
  const file = e.dataTransfer?.files?.[0]
  if (file) selectedFile.value = file
}

function onFileSelected(e: Event) {
  const input = e.target as HTMLInputElement
  if (input.files?.[0]) selectedFile.value = input.files[0]
}

async function uploadFile() {
  if (!selectedFile.value || !targetEntity.value) return
  uploading.value = true
  uploadError.value = ''

  const form = new FormData()
  form.append('file', selectedFile.value)
  form.append('target_entity', targetEntity.value)

  try {
    const res = await fetch('/api/v1/import/upload', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
      body: form,
    })

    if (!res.ok) {
      const err = await res.json()
      uploadError.value = err.message ?? 'Upload failed.'
      return
    }

    const job = await res.json()
    currentJob.value = job
    currentStep.value = 1
    startPolling()
  } catch (e) {
    uploadError.value = 'Network error. Please try again.'
  } finally {
    uploading.value = false
  }
}

function startPolling() {
  pollInterval = setInterval(async () => {
    if (!currentJob.value) return
    const id = currentJob.value.id as number
    const res = await fetch(`/api/v1/import/jobs/${id}`, { credentials: 'same-origin' })
    if (!res.ok) return

    const data = await res.json()
    currentJob.value = data
    previewRows.value = (data.preview_rows as Record<string, unknown>[]) ?? []

    const status = data.status as string

    if (['extracted', 'mapped'].includes(status)) {
      stopPolling()
      // Pre-fill userMapping from AI suggestions
      const mapping = aiSuggestions.value
      entityFields.value.forEach(field => {
        const matchedCol = Object.entries(mapping).find(([, v]) => v === field)?.[0] ?? ''
        userMapping.value[field] = matchedCol
      })
      currentStep.value = 2
    } else if (status === 'failed') {
      stopPolling()
    } else if (['completed'].includes(status)) {
      stopPolling()
      currentStep.value = 3
      await loadFailedRows()
    } else if (['importing'].includes(status)) {
      // stay on step 3 and keep polling
      currentStep.value = 3
    }
  }, 2000)
}

function stopPolling() {
  if (pollInterval) {
    clearInterval(pollInterval)
    pollInterval = null
  }
}

async function confirmMapping() {
  if (!currentJob.value) return
  savingMapping.value = true

  // Invert the mapping: entity_field → csv_column becomes csv_column → entity_field
  const invertedMapping: Record<string, string> = {}
  Object.entries(userMapping.value).forEach(([field, col]) => {
    if (col) invertedMapping[col] = field
  })

  const id = currentJob.value.id as number
  await fetch(`/api/v1/import/jobs/${id}/mapping`, {
    method: 'PUT',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify({ column_mapping: invertedMapping }),
  })

  savingMapping.value = false
  currentStep.value = 3
}

async function startImport() {
  if (!currentJob.value) return
  importStarted.value = true
  const id = currentJob.value.id as number
  await fetch(`/api/v1/import/jobs/${id}/execute`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  })
  startPolling()
}

async function loadFailedRows() {
  if (!currentJob.value) return
  const id = currentJob.value.id as number
  const res = await fetch(`/api/v1/import/jobs/${id}/rows?status=failed`, { credentials: 'same-origin' })
  if (res.ok) {
    const data = await res.json()
    failedRows.value = (data.data as Record<string, unknown>[]) ?? []
  }
}

async function rollback() {
  if (!currentJob.value) return
  const id = currentJob.value.id as number
  await fetch(`/api/v1/import/jobs/${id}/rollback`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  })
  const res = await fetch(`/api/v1/import/jobs/${id}`, { credentials: 'same-origin' })
  if (res.ok) currentJob.value = await res.json()
}

function downloadErrors() {
  const rows = failedRows.value
  const csv = ['Row,Error', ...rows.map(r => `${r.row_index},"${String(r.error_message ?? '').replace(/"/g, '""')}"`)]
  const blob = new Blob([csv.join('\n')], { type: 'text/csv' })
  const url  = URL.createObjectURL(blob)
  const a    = document.createElement('a')
  a.href     = url
  a.download = 'import_errors.csv'
  a.click()
  URL.revokeObjectURL(url)
}

function resetWizard() {
  stopPolling()
  currentStep.value   = 0
  selectedFile.value  = null
  targetEntity.value  = ''
  currentJob.value    = null
  previewRows.value   = []
  userMapping.value   = {}
  failedRows.value    = []
  importStarted.value = false
  loadRecentJobs()
}

async function loadRecentJobs() {
  const res = await fetch('/api/v1/import/jobs', { credentials: 'same-origin' })
  if (res.ok) {
    const data = await res.json()
    recentJobs.value = (data.data as Record<string, unknown>[]) ?? []
  }
}

function isRequired(field: string): boolean {
  return (requiredFields[targetEntity.value] ?? []).includes(field)
}

function aiSuggestedFor(col: string, field: string): boolean {
  return aiSuggestions.value[col] === field
}

function aiSuggestedForField(field: string): boolean {
  return Object.values(aiSuggestions.value).includes(field)
}

function formatDate(iso: unknown): string {
  if (!iso || typeof iso !== 'string') return '—'
  return new Date(iso).toLocaleDateString()
}

onMounted(() => {
  loadRecentJobs()
})

onUnmounted(() => {
  stopPolling()
})
</script>

<style scoped>
.import-page { max-width: 1100px; margin: 0 auto; padding: 24px; }
.page-header h1 { font-size: 24px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
.subtitle { color: var(--fg-3, #888); margin-top: 4px; }

.steps-bar {
  display: flex;
  align-items: center;
  gap: 0;
  margin: 24px 0;
}
.step-item {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
  padding: 12px 16px;
  border-radius: 8px;
  background: var(--surface-2, #f5f5f5);
  color: var(--fg-3, #888);
}
.step-item.active { background: var(--primary-50, #eff6ff); color: var(--primary, #2563eb); font-weight: 600; }
.step-item.done { background: var(--green-50, #f0fdf4); color: var(--green-600, #16a34a); }
.step-circle {
  width: 28px; height: 28px;
  border-radius: 50%;
  background: currentColor;
  color: white;
  display: flex; align-items: center; justify-content: center;
  font-size: 12px;
  font-weight: 700;
}
.step-item.active .step-circle { background: var(--primary, #2563eb); }
.step-item.done .step-circle { background: var(--green-600, #16a34a); }

.step-panel { background: var(--surface-1, #fff); border-radius: 12px; padding: 32px; margin-bottom: 24px; border: 1px solid var(--border, #e5e7eb); }
.step-panel.center { display: flex; flex-direction: column; align-items: center; gap: 16px; }

.dropzone {
  border: 2px dashed var(--border, #d1d5db);
  border-radius: 12px;
  padding: 48px;
  text-align: center;
  cursor: pointer;
  transition: border-color 0.2s, background 0.2s;
}
.dropzone:hover, .dropzone.drag-over {
  border-color: var(--primary, #2563eb);
  background: var(--primary-50, #eff6ff);
}
.upload-icon { font-size: 48px; color: var(--fg-3, #9ca3af); margin-bottom: 12px; display: block; }
.dropzone-text { font-size: 16px; margin-bottom: 12px; }
.file-badges { display: flex; gap: 8px; justify-content: center; }
.badge { background: var(--surface-2, #f3f4f6); padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }

.selected-file { margin-top: 12px; padding: 8px 12px; background: var(--green-50, #f0fdf4); border-radius: 6px; display: flex; align-items: center; gap: 8px; }
.file-size { color: var(--fg-3, #9ca3af); font-size: 12px; }

.entity-selector { margin: 24px 0; }
.entity-selector label { display: block; font-weight: 600; margin-bottom: 12px; }
.entity-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
.entity-btn {
  display: flex; flex-direction: column; align-items: center; gap: 6px;
  padding: 16px; border-radius: 10px;
  border: 2px solid var(--border, #e5e7eb);
  background: var(--surface-1, #fff);
  cursor: pointer; transition: all 0.15s;
  font-size: 14px;
}
.entity-btn:hover { border-color: var(--primary, #2563eb); }
.entity-btn.selected { border-color: var(--primary, #2563eb); background: var(--primary-50, #eff6ff); color: var(--primary, #2563eb); }
.entity-btn i { font-size: 22px; }

.btn-primary {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 10px 24px; border-radius: 8px;
  background: var(--primary, #2563eb); color: white;
  border: none; cursor: pointer; font-weight: 600;
  transition: background 0.15s;
  margin-top: 16px;
}
.btn-primary:hover:not(:disabled) { background: var(--primary-dark, #1d4ed8); }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-secondary {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 10px 20px; border-radius: 8px;
  background: var(--surface-2, #f3f4f6); color: var(--fg-1, #111);
  border: 1px solid var(--border, #e5e7eb); cursor: pointer;
  font-weight: 600;
}
.btn-danger {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 10px 20px; border-radius: 8px;
  background: #fee2e2; color: #b91c1c;
  border: 1px solid #fca5a5; cursor: pointer;
  font-weight: 600;
}

.error-banner { margin-top: 12px; padding: 10px 14px; background: #fee2e2; color: #b91c1c; border-radius: 6px; }

.spinner-block { display: flex; flex-direction: column; align-items: center; gap: 12px; }
.large-spinner { font-size: 48px; color: var(--primary, #2563eb); }
.status-badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: var(--surface-2, #f3f4f6); }
.status-badge.mapped, .status-badge.extracted { background: #d1fae5; color: #065f46; }
.status-badge.failed { background: #fee2e2; color: #b91c1c; }
.status-badge.completed { background: #d1fae5; color: #065f46; }
.status-badge.importing { background: #dbeafe; color: #1e40af; }

.mapping-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
.preview-panel, .mapping-panel { min-width: 0; }
.preview-panel h3, .mapping-panel h3 { font-size: 16px; font-weight: 700; margin-bottom: 12px; }
.table-scroll { overflow-x: auto; }
.preview-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.preview-table th { background: var(--surface-2, #f3f4f6); padding: 8px 12px; text-align: left; border-bottom: 1px solid var(--border, #e5e7eb); }
.preview-table td { padding: 8px 12px; border-bottom: 1px solid var(--border, #f3f4f6); }

.mapping-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.confidence-badge { padding: 4px 12px; border-radius: 20px; background: #fef3c7; color: #92400e; font-size: 13px; font-weight: 600; }
.mapping-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
.mapping-table th { padding: 8px; text-align: left; font-size: 12px; color: var(--fg-3, #888); }
.mapping-table td { padding: 8px 4px; vertical-align: middle; }
.mapping-arrow { color: var(--primary, #2563eb); font-size: 14px; }
.mapping-select { width: 100%; padding: 6px 8px; border-radius: 6px; border: 1px solid var(--border, #d1d5db); font-size: 13px; background: var(--surface-1, #fff); }
.required-star { color: #dc2626; margin-left: 2px; }
.ai-badge { font-size: 10px; padding: 2px 6px; background: #fef3c7; color: #92400e; border-radius: 4px; margin-left: 4px; }

.results-summary { display: flex; flex-direction: column; gap: 16px; }
.result-card { display: flex; align-items: center; gap: 10px; padding: 16px; border-radius: 8px; font-size: 16px; }
.result-card.success { background: #d1fae5; color: #065f46; }
.result-card.error { background: #fee2e2; color: #b91c1c; }
.action-row { display: flex; gap: 12px; margin-top: 8px; }
.failed-rows { margin-top: 12px; }
.failed-rows h4 { margin-bottom: 8px; font-weight: 600; }

.muted { color: var(--fg-3, #9ca3af); font-size: 14px; }

.recent-imports { margin-top: 32px; }
.recent-imports h3 { font-size: 18px; font-weight: 700; margin-bottom: 16px; }
</style>
