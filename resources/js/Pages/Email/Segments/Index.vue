<template>
  <AppLayout>
    <Head title="Email Segments" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Email · Segments</h1>
        <p class="wh-page-subtitle">Manage contact segments for targeted campaigns</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="openCreate">
          <i class="pi pi-plus" /> New Segment
        </button>
      </div>
    </div>

    <!-- Segments table -->
    <div class="wh-panel">
      <div v-if="loading" style="padding:40px;text-align:center;color:var(--fg-3)">
        <i class="pi pi-spin pi-spinner" style="font-size:24px" />
      </div>
      <table v-else class="wh-dt">
        <thead>
          <tr>
            <th>Name</th>
            <th>Type</th>
            <th class="num">Contacts</th>
            <th>Last computed</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="seg in segments" :key="seg.id" class="wh-dt-row">
            <td>
              <p style="font-weight:500;margin:0">{{ seg.name }}</p>
              <p v-if="seg.description" style="font-size:12px;color:var(--fg-3);margin:2px 0 0">{{ seg.description }}</p>
            </td>
            <td>
              <span :class="seg.type === 'dynamic' ? 'badge-purple' : 'badge-blue'" class="wh-badge">
                {{ seg.type }}
              </span>
            </td>
            <td class="num">{{ seg.contact_count_cache ?? 0 }}</td>
            <td style="color:var(--fg-3);font-size:13px">{{ formatDate(seg.last_computed_at) }}</td>
            <td>
              <div class="row-actions">
                <button
                  v-if="seg.type === 'dynamic'"
                  class="btn-sm btn-outline"
                  :disabled="computingId === seg.id"
                  @click="compute(seg)"
                  title="Recompute"
                >
                  <i :class="computingId === seg.id ? 'pi pi-spin pi-spinner' : 'pi pi-refresh'" />
                  {{ computingId === seg.id ? 'Computing…' : 'Compute' }}
                </button>
                <button class="btn-sm btn-outline" @click="openEdit(seg)">
                  <i class="pi pi-pencil" />
                </button>
                <button class="btn-sm btn-danger" @click="deleteSegment(seg)">
                  <i class="pi pi-trash" />
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="segments.length === 0">
            <td colspan="5" style="text-align:center;padding:32px;color:var(--fg-3)">No segments yet</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
      <div class="modal">
        <div class="modal-header">
          <span>{{ editingSegment ? 'Edit Segment' : 'New Segment' }}</span>
          <button @click="closeModal"><i class="pi pi-times" /></button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Name *</label>
            <input v-model="form.name" class="wh-input" placeholder="e.g. Active subscribers" />
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea v-model="form.description" class="wh-input" rows="2" />
          </div>
          <div class="form-group">
            <label>Type *</label>
            <div class="type-selector">
              <button
                :class="['type-btn', form.type === 'static' ? 'active' : '']"
                @click="form.type = 'static'"
              >
                <i class="pi pi-list" /> Static
              </button>
              <button
                :class="['type-btn', form.type === 'dynamic' ? 'active' : '']"
                @click="form.type = 'dynamic'"
              >
                <i class="pi pi-filter" /> Dynamic
              </button>
            </div>
          </div>

          <!-- Dynamic rule builder -->
          <div v-if="form.type === 'dynamic'" class="rule-builder">
            <div class="rule-builder-header">
              <span style="font-weight:600;font-size:14px">Filter Rules</span>
              <button class="btn-sm btn-outline" @click="addRule">
                <i class="pi pi-plus" /> Add rule
              </button>
            </div>
            <div
              v-for="(rule, i) in form.filter_conditions"
              :key="i"
              class="rule-row"
            >
              <select v-model="rule.field" class="wh-input rule-field">
                <option value="status">Status</option>
                <option value="created_at">Created date</option>
                <option value="email">Email</option>
                <option value="first_name">First name</option>
                <option value="last_name">Last name</option>
                <option value="company">Company</option>
              </select>
              <select v-model="rule.operator" class="wh-input rule-op">
                <option value="equals">equals</option>
                <option value="not_equals">not equals</option>
                <option value="contains">contains</option>
                <option value="starts_with">starts with</option>
                <option value="after">after</option>
                <option value="before">before</option>
                <option value="is_null">is empty</option>
                <option value="is_not_null">is not empty</option>
              </select>
              <input
                v-if="!['is_null','is_not_null'].includes(rule.operator)"
                v-model="rule.value"
                class="wh-input rule-value"
                placeholder="value"
              />
              <button class="btn-sm btn-danger" @click="removeRule(i)"><i class="pi pi-times" /></button>
            </div>
            <p v-if="form.filter_conditions.length === 0" style="color:var(--fg-3);font-size:13px;margin:0">
              No rules — click "Add rule" to start
            </p>
          </div>

          <!-- Static contact search -->
          <div v-if="form.type === 'static' && editingSegment" class="static-contacts">
            <div class="rule-builder-header">
              <span style="font-weight:600;font-size:14px">Add Contacts</span>
            </div>
            <div style="display:flex;gap:8px">
              <input
                v-model="contactIdInput"
                class="wh-input"
                placeholder="Enter contact ID(s) comma-separated"
                style="flex:1"
              />
              <button class="btn btn-outline" @click="addStaticContacts">Add</button>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-ghost" @click="closeModal">{{ $t('common.cancel') }}</button>
          <button class="btn btn-primary" :disabled="submitting" @click="submitForm">
            {{ submitting ? 'Saving…' : (editingSegment ? 'Update' : 'Create') }}
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

interface FilterRule {
  field: string
  operator: string
  value?: string
}

interface Segment {
  id: number
  name: string
  description?: string
  type: string
  filter_conditions?: FilterRule[]
  contact_count_cache: number
  last_computed_at?: string
}

interface SegmentForm {
  name: string
  description: string
  type: string
  filter_conditions: FilterRule[]
}

const segments = ref<Segment[]>([])
const loading = ref(false)
const showModal = ref(false)
const editingSegment = ref<Segment | null>(null)
const submitting = ref(false)
const computingId = ref<number | null>(null)
const contactIdInput = ref('')

const form = ref<SegmentForm>({
  name: '',
  description: '',
  type: 'static',
  filter_conditions: [],
})

function formatDate(date?: string): string {
  if (!date) return '—'
  return new Date(date).toLocaleDateString()
}

function openCreate(): void {
  editingSegment.value = null
  form.value = { name: '', description: '', type: 'static', filter_conditions: [] }
  showModal.value = true
}

function openEdit(seg: Segment): void {
  editingSegment.value = seg
  form.value = {
    name: seg.name,
    description: seg.description ?? '',
    type: seg.type,
    filter_conditions: JSON.parse(JSON.stringify(seg.filter_conditions ?? [])),
  }
  showModal.value = true
}

function closeModal(): void {
  showModal.value = false
}

function addRule(): void {
  form.value.filter_conditions.push({ field: 'status', operator: 'equals', value: '' })
}

function removeRule(index: number): void {
  form.value.filter_conditions.splice(index, 1)
}

async function submitForm(): Promise<void> {
  submitting.value = true
  try {
    const payload = {
      name: form.value.name,
      description: form.value.description || null,
      type: form.value.type,
      filter_conditions: form.value.type === 'dynamic' ? form.value.filter_conditions : null,
    }

    if (editingSegment.value) {
      await axios.put(`/api/v1/email/segments/${editingSegment.value.id}`, payload)
    } else {
      await axios.post('/api/v1/email/segments', payload)
    }

    closeModal()
    await loadSegments()
  } finally {
    submitting.value = false
  }
}

async function compute(seg: Segment): Promise<void> {
  computingId.value = seg.id
  try {
    const res = await axios.post(`/api/v1/email/segments/${seg.id}/compute`)
    const idx = segments.value.findIndex(s => s.id === seg.id)
    if (idx !== -1) segments.value[idx] = res.data.segment
  } finally {
    computingId.value = null
  }
}

async function deleteSegment(seg: Segment): Promise<void> {
  if (!confirm(`Delete segment "${seg.name}"?`)) return
  await axios.delete(`/api/v1/email/segments/${seg.id}`)
  await loadSegments()
}

async function addStaticContacts(): Promise<void> {
  if (!editingSegment.value || !contactIdInput.value) return
  const ids = contactIdInput.value.split(',').map(s => parseInt(s.trim())).filter(n => !isNaN(n))
  await axios.post(`/api/v1/email/segments/${editingSegment.value.id}/contacts`, { contact_ids: ids })
  contactIdInput.value = ''
  await loadSegments()
}

async function loadSegments(): Promise<void> {
  loading.value = true
  try {
    const res = await axios.get('/api/v1/email/segments')
    segments.value = res.data.data ?? res.data
  } finally {
    loading.value = false
  }
}

onMounted(loadSegments)
</script>

<style scoped>
.page-head { display: flex; justify-content: space-between; align-items: flex-start; padding: 20px 24px; }
.wh-page-title { font-size: 22px; font-weight: 700; margin: 0; }
.wh-page-subtitle { font-size: 14px; color: var(--fg-3, #6b7280); margin: 4px 0 0; }
.page-actions { display: flex; gap: 8px; }
.wh-panel { background: #fff; border-radius: 10px; border: 1px solid var(--border-1, #e5e7eb); margin: 0 24px 24px; overflow: hidden; }
.wh-dt { width: 100%; border-collapse: collapse; }
.wh-dt th { padding: 10px 14px; font-size: 12px; font-weight: 600; color: var(--fg-3, #6b7280); text-transform: uppercase; letter-spacing: .04em; border-bottom: 1px solid var(--border-1, #e5e7eb); text-align: left; }
.wh-dt th.num, .wh-dt td.num { text-align: right; }
.wh-dt-row td { padding: 12px 14px; border-bottom: 1px solid var(--border-2, #f3f4f6); font-size: 14px; }
.wh-dt-row:last-child td { border-bottom: none; }

.wh-badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 999px; font-size: 12px; font-weight: 500; }
.badge-blue { background: #dbeafe; color: #1d4ed8; }
.badge-purple { background: #ede9fe; color: #7c3aed; }

.row-actions { display: flex; gap: 6px; align-items: center; }
.btn { padding: 7px 14px; border-radius: 6px; font-size: 14px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
.btn-primary { background: var(--primary-600, #2563eb); color: #fff; }
.btn-primary:hover { background: var(--primary-700, #1d4ed8); }
.btn-primary:disabled { opacity: .6; cursor: default; }
.btn-ghost { background: transparent; border: 1px solid var(--border-1, #d1d5db); color: var(--fg-2, #374151); }
.btn-outline { background: transparent; border: 1px solid var(--border-1, #d1d5db); color: var(--fg-2, #374151); border-radius: 6px; padding: 5px 10px; cursor: pointer; font-size: 13px; display: inline-flex; align-items: center; gap: 4px; }
.btn-sm { padding: 4px 8px; border-radius: 5px; font-size: 12px; cursor: pointer; border: 1px solid var(--border-1, #d1d5db); background: transparent; display: inline-flex; align-items: center; gap: 4px; }
.btn-sm:disabled { opacity: .5; cursor: default; }
.btn-danger { background: #fee2e2; color: #dc2626; border-color: #fca5a5; }

.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.5); display: flex; align-items: center; justify-content: center; z-index: 1000; }
.modal { background: #fff; border-radius: 10px; width: 560px; max-height: 80vh; display: flex; flex-direction: column; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; border-bottom: 1px solid #e5e7eb; font-weight: 600; }
.modal-header button { background: none; border: none; cursor: pointer; font-size: 16px; }
.modal-body { padding: 20px; overflow-y: auto; flex: 1; }
.modal-footer { display: flex; justify-content: flex-end; gap: 8px; padding: 14px 20px; border-top: 1px solid #e5e7eb; }

.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 12px; font-weight: 600; color: var(--fg-2, #374151); margin-bottom: 6px; }
.wh-input { width: 100%; border: 1px solid var(--border-1, #d1d5db); border-radius: 6px; padding: 6px 10px; font-size: 13px; box-sizing: border-box; }

.type-selector { display: flex; gap: 8px; }
.type-btn { flex: 1; padding: 8px; border: 1px solid var(--border-1, #d1d5db); border-radius: 6px; cursor: pointer; background: transparent; display: flex; align-items: center; justify-content: center; gap: 6px; font-size: 13px; }
.type-btn.active { background: var(--primary-50, #eff6ff); border-color: var(--primary-500, #3b82f6); color: var(--primary-700, #1d4ed8); font-weight: 600; }

.rule-builder { border: 1px solid var(--border-1, #e5e7eb); border-radius: 8px; padding: 14px; margin-top: 8px; }
.rule-builder-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.rule-row { display: flex; gap: 6px; align-items: center; margin-bottom: 8px; flex-wrap: wrap; }
.rule-field { flex: 0 0 140px; }
.rule-op { flex: 0 0 130px; }
.rule-value { flex: 1; min-width: 80px; }

.static-contacts { border: 1px solid var(--border-1, #e5e7eb); border-radius: 8px; padding: 14px; margin-top: 16px; }
</style>
