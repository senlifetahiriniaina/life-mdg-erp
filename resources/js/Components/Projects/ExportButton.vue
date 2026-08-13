<template>
  <div class="export-wrap" style="position:relative">
    <button class="btn btn-outline" @click="open = !open">
      <i class="pi pi-download" /> Export
      <i class="pi pi-chevron-down" style="font-size:10px" />
    </button>
    <div v-if="open" class="dropdown" @click.stop>
      <button class="dropdown-item" @click="exportPdf">
        <i class="pi pi-file-pdf" /> Export PDF
      </button>
      <button class="dropdown-item" @click="exportJson">
        <i class="pi pi-code" /> Report Data (JSON)
      </button>
      <button class="dropdown-item" @click="printReport">
        <i class="pi pi-print" /> Print Report
      </button>
    </div>
    <div v-if="open" class="overlay" @click="open = false" />
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'

const props = defineProps<{
  projectId: number
}>()

const open = ref(false)

function exportPdf() {
  window.location.href = `/api/v1/projects/${props.projectId}/report/pdf`
  open.value = false
}

async function exportJson() {
  const res = await fetch(`/api/v1/projects/${props.projectId}/report`, {
    headers: { Accept: 'application/json' },
  })
  const data = await res.json()
  const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' })
  const url  = URL.createObjectURL(blob)
  const a    = document.createElement('a')
  a.href     = url
  a.download = `project-${props.projectId}-report.json`
  a.click()
  URL.revokeObjectURL(url)
  open.value = false
}

async function printReport() {
  const res  = await fetch(`/api/v1/projects/${props.projectId}/report/html`, {
    headers: { Accept: 'text/html' },
  })
  const html = await res.text()
  const win  = window.open('', '_blank')
  if (win) {
    win.document.write(html)
    win.document.close()
    win.print()
  }
  open.value = false
}
</script>

<style scoped>
.export-wrap { display:inline-block; }
.btn { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border-radius:var(--r-md); font-size:13px; font-weight:500; cursor:pointer; transition:background var(--dur-fast); }
.btn-outline { background:var(--bg-canvas); color:var(--fg-1); border:1px solid var(--border-subtle); }
.btn-outline:hover { background:var(--bg-sunken); }
.dropdown { position:absolute; top:calc(100% + 4px); right:0; background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-md); box-shadow:0 8px 24px rgba(0,0,0,0.12); min-width:200px; z-index:50; overflow:hidden; }
.dropdown-item { display:flex; align-items:center; gap:8px; width:100%; padding:10px 14px; font-size:13px; color:var(--fg-1); background:transparent; border:none; cursor:pointer; text-align:left; transition:background var(--dur-fast); }
.dropdown-item:hover { background:var(--bg-sunken); }
.overlay { position:fixed; inset:0; z-index:49; }
</style>
