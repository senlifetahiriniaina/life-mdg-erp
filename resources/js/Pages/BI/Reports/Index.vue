<template>
  <AppLayout>
    <Head title="Rapports BI" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">BI · Rapports</h1>
        <p class="wh-page-subtitle">{{ reports.total }} rapport{{ reports.total !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions">
        <ExportButton type="widget" :id="1" />
        <button class="btn btn-primary"><i class="pi pi-plus" style="font-size:13px" /> Nouveau rapport</button>
      </div>
    </div>

    <div class="wh-panel">
      <table class="wh-dt">
        <thead>
          <tr>
            <th>Nom</th>
            <th>Type</th>
            <th>Statut</th>
            <th>Créé le</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="report in reports.data" :key="report.id" class="wh-dt-row">
            <td style="font-weight:500;color:var(--fg-1)">{{ report.name }}</td>
            <td style="color:var(--fg-2);text-transform:capitalize">{{ report.type }}</td>
            <td>
              <span :class="statusBadge(report.status)" class="wh-badge">
                <span class="wh-badge-dot" />
                {{ statusLabel(report.status) }}
              </span>
            </td>
            <td style="color:var(--fg-3)">{{ formatDate(report.created_at) }}</td>
          </tr>
          <tr v-if="!reports.data.length">
            <td colspan="4" style="text-align:center;color:var(--fg-3);padding:32px 18px">Aucun rapport trouvé.</td>
          </tr>
        </tbody>
      </table>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-top:1px solid var(--border-subtle)">
        <span style="font-size:13px;color:var(--fg-3)">{{ reports.total }} résultat{{ reports.total !== 1 ? 's' : '' }}</span>
        <Paginator
          :rows="reports.per_page"
          :total-records="reports.total"
          :first="(reports.current_page - 1) * reports.per_page"
        />
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { Paginator } from 'primevue'
import AppLayout from '@/Layouts/AppLayout.vue'
import ExportButton from '@/Components/BI/ExportButton.vue'

defineProps({
  reports: { type: Object, required: true },
})

const statusBadge = (status) => ({
  draft:     'wh-badge-slate',
  published: 'wh-badge-green',
}[status] ?? 'wh-badge-slate')

const statusLabel = (status) => ({
  draft:     'Brouillon',
  published: 'Publié',
}[status] ?? status)

const formatDate = (value) =>
  value ? new Date(value).toLocaleDateString('fr-FR', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; gap:16px; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; gap:8px; }
.btn { font-family:var(--font-sans); font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:background var(--dur-base) var(--ease-out); line-height:1.2; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover { background:var(--halo-700); }
.wh-panel { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); overflow:hidden; }
.wh-dt { width:100%; border-collapse:collapse; font-size:14px; }
.wh-dt thead th { text-align:left; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:var(--fg-3); font-weight:600; padding:10px 18px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sunken); }
.wh-dt-row td { padding:11px 18px; border-bottom:1px solid var(--border-subtle); }
.wh-dt-row:last-child td { border-bottom:0; }
.wh-dt-row { cursor:pointer; transition:background var(--dur-fast); }
.wh-dt-row:hover { background:var(--bg-sunken); }
.wh-badge { display:inline-flex; align-items:center; gap:5px; padding:2px 8px; border-radius:var(--r-pill); font-size:11px; font-weight:500; }
.wh-badge-dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
.wh-badge-green  { background:var(--success-bg); color:var(--success-fg); }
.wh-badge-slate  { background:var(--bg-sunken); color:var(--fg-2); }
</style>
