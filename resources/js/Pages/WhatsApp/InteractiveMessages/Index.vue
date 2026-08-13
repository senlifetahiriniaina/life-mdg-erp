<template>
  <AppLayout>
    <Head title="Interactive Messages" />

    <div class="p-6">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-surface-50 dark:text-white">Interactive Messages</h1>
          <p class="text-sm text-gray-500 dark:text-surface-400 mt-1">Manage button, list, and quick reply templates</p>
        </div>
        <Button label="New Template" icon="pi pi-plus" @click="openCreate" />
      </div>

      <!-- Template List -->
      <DataTable
        :value="templates.data"
        :loading="loading"
        stripedRows
        class="rounded-xl overflow-hidden"
      >
        <Column field="name" header="Name" />
        <Column header="Type">
          <template #body="{ data }">
            <span :class="typeBadgeClass(data.type)" class="px-2 py-1 rounded-full text-xs font-semibold">
              {{ data.type }}
            </span>
          </template>
        </Column>
        <Column header="Status">
          <template #body="{ data }">
            <span :class="statusBadgeClass(data.status)" class="px-2 py-1 rounded-full text-xs font-semibold">
              {{ data.status }}
            </span>
          </template>
        </Column>
        <Column field="language" header="Language" />
        <Column header="Actions">
          <template #body="{ data }">
            <div class="flex gap-2">
              <Button icon="pi pi-eye" text size="small" @click="openPreview(data)" v-tooltip="'Preview'" />
              <Button icon="pi pi-pencil" text size="small" @click="openEdit(data)" v-tooltip="'Edit'" />
              <Button icon="pi pi-send" text size="small" severity="success" @click="openSend(data)" v-tooltip="'Send'" />
              <Button icon="pi pi-trash" text size="small" severity="danger" @click="deleteTemplate(data)" v-tooltip="'Delete'" />
            </div>
          </template>
        </Column>
      </DataTable>

      <!-- Pagination -->
      <Paginator
        v-if="templates.last_page > 1"
        :rows="templates.per_page"
        :totalRecords="templates.total"
        :first="(templates.current_page - 1) * templates.per_page"
        @page="onPage"
        class="mt-4"
      />
    </div>

    <!-- Create / Edit Modal -->
    <Dialog
      v-model:visible="showModal"
      :header="editingTemplate ? 'Edit Template' : 'New Interactive Template'"
      modal
      class="w-full max-w-2xl"
    >
      <div class="flex flex-col gap-4">
        <!-- Type selector -->
        <div>
          <label class="block text-sm font-medium mb-1">Type</label>
          <SelectButton v-model="form.type" :options="typeOptions" optionLabel="label" optionValue="value" />
        </div>

        <!-- Name -->
        <div>
          <label class="block text-sm font-medium mb-1">Template Name</label>
          <InputText v-model="form.name" class="w-full" placeholder="e.g. welcome-button" />
        </div>

        <!-- Header -->
        <div>
          <label class="block text-sm font-medium mb-1">Header Type</label>
          <Select v-model="form.header_type" :options="headerTypeOptions" optionLabel="label" optionValue="value" class="w-full" />
        </div>
        <div v-if="form.header_type !== 'none'">
          <label class="block text-sm font-medium mb-1">Header Content</label>
          <InputText v-model="form.header_content" class="w-full" placeholder="Header text or media URL" />
        </div>

        <!-- Body -->
        <div>
          <label class="block text-sm font-medium mb-1">Body Text *</label>
          <Textarea v-model="form.body_text" rows="3" class="w-full" placeholder="Message body..." />
        </div>

        <!-- Footer -->
        <div>
          <label class="block text-sm font-medium mb-1">Footer Text</label>
          <InputText v-model="form.footer_text" class="w-full" placeholder="Optional footer (max 60 chars)" maxlength="60" />
        </div>

        <!-- Buttons (for button/quick_reply type) -->
        <div v-if="form.type !== 'list'">
          <div class="flex items-center justify-between mb-2">
            <label class="block text-sm font-medium">Buttons (max 3)</label>
            <Button label="Add Button" icon="pi pi-plus" size="small" text @click="addButton" :disabled="form.buttons.length >= 3" />
          </div>
          <div v-for="(btn, i) in form.buttons" :key="i" class="flex gap-2 mb-2">
            <InputText v-model="btn.title" placeholder="Button title" class="flex-1" maxlength="20" />
            <InputText v-model="btn.payload" placeholder="Payload" class="flex-1" />
            <Button icon="pi pi-times" text severity="danger" size="small" @click="form.buttons.splice(i, 1)" />
          </div>
        </div>

        <!-- List Sections (for list type) -->
        <div v-if="form.type === 'list'">
          <div class="flex items-center justify-between mb-2">
            <label class="block text-sm font-medium">Sections</label>
            <Button label="Add Section" icon="pi pi-plus" size="small" text @click="addSection" />
          </div>
          <div v-for="(section, si) in form.list_sections" :key="si" class="border rounded-lg p-3 mb-3">
            <div class="flex gap-2 mb-2">
              <InputText v-model="section.title" placeholder="Section title" class="flex-1" />
              <Button icon="pi pi-times" text severity="danger" size="small" @click="form.list_sections.splice(si, 1)" />
            </div>
            <div v-for="(row, ri) in section.rows" :key="ri" class="flex gap-2 mb-1 ml-4">
              <InputText v-model="row.id" placeholder="ID" class="w-24" />
              <InputText v-model="row.title" placeholder="Title" class="flex-1" />
              <InputText v-model="row.description" placeholder="Description" class="flex-1" />
              <Button icon="pi pi-times" text severity="danger" size="small" @click="section.rows.splice(ri, 1)" />
            </div>
            <Button label="Add Row" icon="pi pi-plus" size="small" text class="ml-4" @click="section.rows.push({ id: '', title: '', description: '' })" />
          </div>
        </div>
      </div>

      <template #footer>
        <Button label="Cancel" text @click="showModal = false" />
        <Button :label="editingTemplate ? 'Update' : 'Create'" icon="pi pi-check" @click="saveTemplate" :loading="saving" />
      </template>
    </Dialog>

    <!-- Preview Dialog -->
    <Dialog v-model:visible="showPreview" header="Preview" modal class="w-80">
      <div v-if="previewTemplate" class="bg-[#ECE5DD] rounded-xl p-4">
        <!-- WA-style bubble -->
        <div class="bg-white dark:bg-surface-800 rounded-xl shadow-sm p-3 max-w-xs">
          <div v-if="previewTemplate.header_type !== 'none'" class="font-semibold text-sm mb-2 text-gray-700 dark:text-surface-100">
            {{ previewTemplate.header_content }}
          </div>
          <p class="text-sm text-gray-900 dark:text-surface-50 whitespace-pre-wrap">{{ previewTemplate.body_text }}</p>
          <p v-if="previewTemplate.footer_text" class="text-xs text-gray-400 mt-1">{{ previewTemplate.footer_text }}</p>
          <div class="mt-3 border-t pt-2 flex flex-col gap-1" v-if="previewTemplate.type !== 'list'">
            <button
              v-for="btn in (previewTemplate.buttons || [])"
              :key="btn.payload"
              class="text-center text-sm text-[#00a5f4] font-medium py-1 border rounded-lg"
            >{{ btn.title }}</button>
          </div>
          <div class="mt-2" v-else>
            <button class="w-full text-center text-sm text-[#00a5f4] border rounded-lg py-1">
              ≡ Select
            </button>
          </div>
        </div>
      </div>
    </Dialog>

    <!-- Send Dialog -->
    <Dialog v-model:visible="showSend" header="Send Template" modal class="w-96">
      <div class="flex flex-col gap-3">
        <div>
          <label class="block text-sm font-medium mb-1">Conversation ID</label>
          <InputText v-model="sendConvId" class="w-full" placeholder="Enter conversation ID" type="number" />
        </div>
      </div>
      <template #footer>
        <Button label="Cancel" text @click="showSend = false" />
        <Button label="Send" icon="pi pi-send" severity="success" @click="doSend" :loading="sending" />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

interface Button {
  type: string
  title: string
  payload: string
  url?: string
}

interface ListRow {
  id: string
  title: string
  description: string
}

interface ListSection {
  title: string
  rows: ListRow[]
}

interface Template {
  id: number
  name: string
  type: string
  language: string
  header_type: string
  header_content: string | null
  body_text: string
  footer_text: string | null
  buttons: Button[] | null
  list_sections: ListSection[] | null
  status: string
}

interface PaginatedTemplates {
  data: Template[]
  total: number
  per_page: number
  current_page: number
  last_page: number
}

const templates = ref<PaginatedTemplates>({ data: [], total: 0, per_page: 25, current_page: 1, last_page: 1 })
const loading   = ref(false)
const saving    = ref(false)
const sending   = ref(false)

const showModal   = ref(false)
const showPreview = ref(false)
const showSend    = ref(false)

const editingTemplate  = ref<Template | null>(null)
const previewTemplate  = ref<Template | null>(null)
const selectedTemplate = ref<Template | null>(null)
const sendConvId       = ref('')

const typeOptions = [
  { label: 'Button', value: 'button' },
  { label: 'List', value: 'list' },
  { label: 'Quick Reply', value: 'quick_reply' },
]

const headerTypeOptions = [
  { label: 'None', value: 'none' },
  { label: 'Text', value: 'text' },
  { label: 'Image', value: 'image' },
]

const form = reactive({
  name: '',
  type: 'button',
  language: 'en',
  header_type: 'none',
  header_content: '',
  body_text: '',
  footer_text: '',
  buttons: [] as Button[],
  list_sections: [] as ListSection[],
  status: 'draft',
})

async function fetchTemplates(page = 1) {
  loading.value = true
  try {
    const res = await axios.get('/api/v1/whatsapp/interactive-templates', { params: { page } })
    templates.value = res.data
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingTemplate.value = null
  Object.assign(form, {
    name: '', type: 'button', language: 'en', header_type: 'none',
    header_content: '', body_text: '', footer_text: '',
    buttons: [], list_sections: [], status: 'draft',
  })
  showModal.value = true
}

function openEdit(t: Template) {
  editingTemplate.value = t
  Object.assign(form, {
    name: t.name, type: t.type, language: t.language,
    header_type: t.header_type, header_content: t.header_content ?? '',
    body_text: t.body_text, footer_text: t.footer_text ?? '',
    buttons: t.buttons ? [...t.buttons] : [],
    list_sections: t.list_sections ? [...t.list_sections] : [],
    status: t.status,
  })
  showModal.value = true
}

function openPreview(t: Template) {
  previewTemplate.value = t
  showPreview.value = true
}

function openSend(t: Template) {
  selectedTemplate.value = t
  sendConvId.value = ''
  showSend.value = true
}

function addButton() {
  form.buttons.push({ type: 'reply', title: '', payload: '' })
}

function addSection() {
  form.list_sections.push({ title: '', rows: [] })
}

async function saveTemplate() {
  saving.value = true
  try {
    if (editingTemplate.value) {
      await axios.put(`/api/v1/whatsapp/interactive-templates/${editingTemplate.value.id}`, form)
    } else {
      await axios.post('/api/v1/whatsapp/interactive-templates', form)
    }
    showModal.value = false
    await fetchTemplates()
  } finally {
    saving.value = false
  }
}

async function deleteTemplate(t: Template) {
  if (!confirm(`Delete template "${t.name}"?`)) return
  await axios.delete(`/api/v1/whatsapp/interactive-templates/${t.id}`)
  await fetchTemplates()
}

async function doSend() {
  if (!selectedTemplate.value || !sendConvId.value) return
  sending.value = true
  try {
    await axios.post(`/api/v1/whatsapp/interactive-templates/${selectedTemplate.value.id}/send`, {
      conversation_id: Number(sendConvId.value),
    })
    showSend.value = false
  } finally {
    sending.value = false
  }
}

function onPage(e: { page: number }) {
  fetchTemplates(e.page + 1)
}

function typeBadgeClass(type: string) {
  return {
    button:      'bg-blue-100 text-blue-700',
    list:        'bg-green-100 text-green-700',
    quick_reply: 'bg-purple-100 text-purple-700',
    flow:        'bg-yellow-100 text-yellow-700',
  }[type] ?? 'bg-gray-100 text-gray-700'
}

function statusBadgeClass(status: string) {
  return {
    draft:    'bg-gray-100 text-gray-600',
    approved: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
  }[status] ?? 'bg-gray-100 text-gray-600'
}

onMounted(() => fetchTemplates())
</script>
