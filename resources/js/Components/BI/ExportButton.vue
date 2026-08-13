<template>
  <div class="wh-export-btn" style="position:relative;display:inline-block">
    <button
      class="btn btn-ghost btn-sm"
      style="display:flex;align-items:center;gap:6px"
      @click="toggleDropdown"
    >
      <i class="pi pi-download" style="font-size:13px" />
      Exporter
      <i class="pi pi-chevron-down" style="font-size:10px" />
    </button>

    <div
      v-if="open"
      class="wh-dropdown"
      style="position:absolute;right:0;top:100%;margin-top:4px;z-index:100;background:var(--surface-1);border:1px solid var(--border-subtle);border-radius:8px;box-shadow:var(--shadow-md);min-width:160px;overflow:hidden"
    >
      <button
        v-if="showCsv"
        class="wh-dropdown-item"
        style="width:100%;padding:9px 14px;text-align:left;display:flex;align-items:center;gap:8px;font-size:13px;background:none;border:none;cursor:pointer;color:var(--fg-1)"
        @click="doExport('csv')"
      >
        <i class="pi pi-file" style="font-size:13px;color:var(--fg-3)" />
        Export CSV
      </button>
      <button
        v-if="showPdf"
        class="wh-dropdown-item"
        style="width:100%;padding:9px 14px;text-align:left;display:flex;align-items:center;gap:8px;font-size:13px;background:none;border:none;cursor:pointer;color:var(--fg-1)"
        @click="doExport('pdf')"
      >
        <i class="pi pi-file-pdf" style="font-size:13px;color:var(--red-600)" />
        Export PDF
      </button>
      <button
        v-if="showXlsx"
        class="wh-dropdown-item"
        style="width:100%;padding:9px 14px;text-align:left;display:flex;align-items:center;gap:8px;font-size:13px;background:none;border:none;cursor:pointer;color:var(--fg-1)"
        @click="doExport('xlsx')"
      >
        <i class="pi pi-file-excel" style="font-size:13px;color:var(--green-600)" />
        Export XLSX
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'

type ExportType = 'dashboard' | 'widget' | 'query'
type ExportFormat = 'csv' | 'pdf' | 'xlsx'

const props = withDefaults(defineProps<{
  type: ExportType
  id: number | string
}>(), {})

const open = ref(false)

const showCsv  = computed(() => props.type !== 'dashboard')
const showPdf  = computed(() => props.type !== 'query')
const showXlsx = computed(() => props.type === 'query')

function toggleDropdown() {
  open.value = !open.value
}

function doExport(format: ExportFormat) {
  open.value = false
  const url = buildUrl(format)
  window.location.href = url
}

function buildUrl(format: ExportFormat): string {
  switch (props.type) {
    case 'dashboard':
      return `/api/v1/bi/dashboards/${props.id}/export?format=${format}`
    case 'widget':
      return `/api/v1/bi/widgets/${props.id}/export?format=${format}`
    case 'query':
      return `/api/v1/bi/queries/${props.id}/export?format=${format}`
    default:
      return '#'
  }
}

function onClickOutside(e: MouseEvent) {
  const el = e.target as HTMLElement
  if (!el.closest('.wh-export-btn')) {
    open.value = false
  }
}

onMounted(() => document.addEventListener('click', onClickOutside))
onBeforeUnmount(() => document.removeEventListener('click', onClickOutside))
</script>
