<template>
  <AppLayout>
    <Head :title="$t('documents.folders.title')" />

    <!-- Page header -->
    <div class="page-head">
      <div>
        <h1 class="wh-page-title">{{ $t('documents.folders.title') }}</h1>
        <p class="wh-page-subtitle">{{ folders.length }} {{ folders.length !== 1 ? 'folders' : 'folder' }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="openCreateModal">
          <i class="pi pi-plus" style="font-size:13px" />
          {{ $t('documents.folders.new') }}
        </button>
      </div>
    </div>

    <!-- Breadcrumb -->
    <nav class="folder-breadcrumb" aria-label="Folder navigation">
      <button class="breadcrumb-item" @click="navigateTo(null)">
        <i class="pi pi-home" style="font-size:12px" />
        {{ $t('documents.folders.root') }}
      </button>
      <template v-for="(crumb, i) in breadcrumbs" :key="crumb.id">
        <i class="pi pi-chevron-right breadcrumb-sep" aria-hidden="true" />
        <button
          class="breadcrumb-item"
          :class="i === breadcrumbs.length - 1 ? 'breadcrumb-item-current' : ''"
          @click="i < breadcrumbs.length - 1 ? navigateTo(crumb.id) : undefined"
        >
          {{ crumb.name }}
        </button>
      </template>
    </nav>

    <!-- Folders grid -->
    <div class="folders-grid">
      <div
        v-for="folder in folders"
        :key="folder.id"
        class="folder-card"
        @dblclick="openFolder(folder)"
        @keyup.enter="openFolder(folder)"
        role="button"
        tabindex="0"
        :aria-label="folder.name"
      >
        <i class="pi pi-folder folder-icon" :style="{ color: folder.color || 'var(--halo-500)' }" aria-hidden="true" />
        <div class="folder-info">
          <span class="folder-name">{{ folder.name }}</span>
          <span class="folder-meta">{{ folder.documents_count ?? 0 }} {{ $t('documents.title').toLowerCase() }}</span>
        </div>
        <div class="folder-actions">
          <button class="wh-row-btn" :title="$t('common.edit')" @click.stop="editFolder(folder)">
            <i class="pi pi-pencil" style="font-size:12px" />
          </button>
          <button class="wh-row-btn wh-row-btn-danger" :title="$t('common.delete')" @click.stop="confirmDeleteFolder(folder)">
            <i class="pi pi-trash" style="font-size:12px" />
          </button>
        </div>
      </div>

      <!-- Empty state -->
      <div v-if="folders.length === 0" class="folders-empty">
        <i class="pi pi-folder-open" style="font-size:40px;opacity:0.3" aria-hidden="true" />
        <p style="font-size:14px;margin:0">{{ $t('documents.folders.empty') }}</p>
        <button class="btn btn-primary" @click="openCreateModal">
          <i class="pi pi-plus" style="font-size:13px" />
          {{ $t('documents.folders.new') }}
        </button>
      </div>
    </div>

    <!-- Create/Edit Folder Modal -->
    <Dialog
      v-model:visible="showModal"
      :header="editingFolder ? $t('documents.folders.edit') : $t('documents.folders.new')"
      modal
      style="width:440px"
    >
      <div class="form-grid">
        <div class="form-field" style="grid-column:1/-1">
          <label>{{ $t('common.name') }} *</label>
          <InputText v-model="form.name" class="w-full" :placeholder="$t('common.name')" autofocus />
          <span v-if="errors.name" class="field-error">{{ errors.name[0] }}</span>
        </div>
        <div class="form-field">
          <label>{{ $t('documents.folders.color') }}</label>
          <div style="display:flex;align-items:center;gap:10px">
            <input
              v-model="form.color"
              type="color"
              style="width:36px;height:36px;padding:2px;border:1px solid var(--border-subtle);border-radius:var(--r-md);cursor:pointer"
            />
            <span style="font-size:13px;color:var(--fg-3);font-family:var(--font-mono)">{{ form.color }}</span>
          </div>
        </div>
      </div>
      <template #footer>
        <button class="btn btn-secondary" @click="closeModal">{{ $t('common.cancel') }}</button>
        <button class="btn btn-primary" :disabled="saving" @click="saveFolder">
          <i v-if="saving" class="pi pi-spin pi-spinner" style="font-size:13px" />
          {{ $t('common.save') }}
        </button>
      </template>
    </Dialog>

    <ConfirmDialog />
  </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { useConfirm } from 'primevue/useconfirm'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import ConfirmDialog from 'primevue/confirmdialog'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  folders:     { type: Array,  default: () => [] },
  breadcrumbs: { type: Array,  default: () => [] },
  parent_id:   { type: Number, default: null },
})

const confirm = useConfirm()
const { t }   = useI18n()

const showModal     = ref(false)
const saving        = ref(false)
const editingFolder = ref(null)
const errors        = ref({})

const form = reactive({ name: '', color: '#2563eb', parent_id: props.parent_id ?? null })

function openFolder(folder) {
  router.visit(`/documents/folders?parent_id=${folder.id}`)
}

function navigateTo(id) {
  if (id) {
    router.visit(`/documents/folders?parent_id=${id}`)
  } else {
    router.visit('/documents/folders')
  }
}

function openCreateModal() {
  editingFolder.value = null
  errors.value = {}
  Object.assign(form, { name: '', color: '#2563eb', parent_id: props.parent_id ?? null })
  showModal.value = true
}

function editFolder(folder) {
  editingFolder.value = folder
  errors.value = {}
  Object.assign(form, { name: folder.name, color: folder.color ?? '#2563eb', parent_id: folder.parent_id ?? null })
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editingFolder.value = null
  errors.value = {}
  Object.assign(form, { name: '', color: '#2563eb', parent_id: props.parent_id ?? null })
}

async function saveFolder() {
  saving.value = true
  errors.value = {}
  try {
    const url    = editingFolder.value ? `/api/v1/documents/folders/${editingFolder.value.id}` : '/api/v1/documents/folders'
    const method = editingFolder.value ? 'PUT' : 'POST'
    const payload = editingFolder.value
      ? { name: form.name, color: form.color }
      : { name: form.name, color: form.color, parent_id: props.parent_id ?? null }
    const res = await fetch(url, {
      method,
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(payload),
    })
    if (!res.ok) {
      const data = await res.json()
      if (data.errors) errors.value = data.errors
      return
    }
    closeModal()
    router.reload()
  } finally {
    saving.value = false
  }
}

function confirmDeleteFolder(folder) {
  confirm.require({
    message: `${t('common.delete')} "${folder.name}"?`,
    header: t('common.delete') + ' folder',
    icon: 'pi pi-exclamation-triangle',
    rejectClass: 'p-button-text',
    acceptClass: 'p-button-danger',
    accept: async () => {
      await fetch(`/api/v1/documents/folders/${folder.id}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json' },
      })
      router.reload()
    },
  })
}
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:20px; gap:16px; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; gap:8px; }
.btn { font-family:var(--font-sans); font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:background var(--dur-base) var(--ease-out), border-color var(--dur-base); line-height:1.2; }
.btn:disabled { opacity:.5; cursor:not-allowed; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover:not(:disabled) { background:var(--halo-700); }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover { background:var(--bg-sunken); }

/* Breadcrumb */
.folder-breadcrumb { display:flex; align-items:center; gap:4px; margin-bottom:16px; flex-wrap:wrap; }
.breadcrumb-item { background:none; border:none; cursor:pointer; font-size:13px; color:var(--fg-link); padding:4px 8px; border-radius:var(--r-sm); display:inline-flex; align-items:center; gap:6px; font-family:var(--font-sans); transition:background var(--dur-fast); }
.breadcrumb-item:hover { background:var(--bg-sunken); }
.breadcrumb-item-current { color:var(--fg-1); font-weight:600; cursor:default; }
.breadcrumb-item-current:hover { background:none; }
.breadcrumb-sep { font-size:10px; color:var(--fg-4); }

/* Folders grid */
.folders-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(240px,1fr)); gap:10px; }
.folder-card { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); padding:14px 16px; display:flex; align-items:center; gap:12px; cursor:pointer; transition:box-shadow var(--dur-fast), border-color var(--dur-fast); user-select:none; }
.folder-card:hover { box-shadow:0 2px 8px rgba(0,0,0,.07); border-color:var(--border-strong); }
.folder-card:focus-visible { outline:2px solid var(--halo-500); outline-offset:2px; }
.folder-icon { font-size:28px; flex-shrink:0; }
.folder-info { flex:1; min-width:0; }
.folder-name { display:block; font-weight:600; font-size:14px; color:var(--fg-1); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.folder-meta { font-size:11px; color:var(--fg-3); }
.folder-actions { display:flex; gap:4px; flex-shrink:0; }
.folders-empty { grid-column:1/-1; display:flex; flex-direction:column; align-items:center; gap:12px; padding:56px; color:var(--fg-3); }

.wh-row-btn { width:28px; height:28px; border-radius:var(--r-sm); border:1px solid var(--border-subtle); background:var(--bg-canvas); color:var(--fg-2); cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:all var(--dur-fast); }
.wh-row-btn:hover { background:var(--bg-sunken); color:var(--fg-1); border-color:var(--border-strong); }
.wh-row-btn-danger:hover { background:var(--danger-bg); color:var(--danger-fg); border-color:var(--red-500); }

/* Form */
.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; padding:8px 0; }
.form-field { display:flex; flex-direction:column; gap:6px; }
.form-field label { font-size:13px; font-weight:500; color:var(--fg-2); }
.field-error { font-size:12px; color:var(--danger-fg,#dc2626); }
.w-full { width:100%; }
</style>
