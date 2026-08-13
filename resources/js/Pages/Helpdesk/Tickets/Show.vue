<template>
  <AppLayout>
    <Head :title="ticket.subject" />

    <div class="page-head">
      <div style="display:flex;align-items:center;gap:12px">
        <a href="/helpdesk/tickets" class="btn btn-icon"><i class="pi pi-arrow-left" style="font-size:14px" /></a>
        <div>
          <h1 class="wh-page-title">{{ ticket.subject }}</h1>
          <div style="display:flex;gap:6px;margin-top:6px">
            <span :class="['wh-badge', ticketStatusClass(ticket.status)]">
              <span class="wh-badge-dot" />{{ statusLabel(ticket.status) }}
            </span>
            <span :class="['wh-badge', priorityClass(ticket.priority)]">{{ priorityLabel(ticket.priority) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Ticket info -->
    <div class="wh-panel" style="margin-bottom:16px">
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:0;border-bottom:1px solid var(--border-subtle)">
        <div v-for="info in infoFields" :key="info.label" class="info-cell">
          <p class="info-cell-label">{{ info.label }}</p>
          <p class="info-cell-value">{{ info.value }}</p>
        </div>
      </div>
      <div style="padding:20px">
        <p style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.06em;color:var(--fg-3);margin:0 0 8px">Description</p>
        <p style="font-size:14px;color:var(--fg-1);margin:0;white-space:pre-line;line-height:1.6">{{ ticket.description || '—' }}</p>
      </div>
    </div>

    <!-- Comments -->
    <div class="wh-panel">
      <div class="wh-panel-head">
        <h3>Commentaires ({{ ticket.comments?.length ?? 0 }})</h3>
      </div>
      <div>
        <div v-if="ticket.comments?.length" class="comment-list">
          <div v-for="comment in ticket.comments" :key="comment.id" class="comment-row">
            <div class="comment-avatar">{{ initials(comment.user?.name) }}</div>
            <div style="flex:1;min-width:0">
              <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                <span style="font-size:13px;font-weight:600;color:var(--fg-1)">{{ comment.user?.name || 'Inconnu' }}</span>
                <span style="font-size:11px;color:var(--fg-4)">{{ formatDate(comment.created_at) }}</span>
              </div>
              <div class="comment-body">{{ comment.body }}</div>
            </div>
          </div>
        </div>
        <p v-else style="padding:32px 18px;text-align:center;font-size:13px;color:var(--fg-3);margin:0">Aucun commentaire.</p>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { computed } from 'vue'

const props = defineProps({
  ticket: { type: Object, required: true },
})

const infoFields = computed(() => [
  { label: 'Canal',       value: channelLabel(props.ticket.channel) },
  { label: 'Équipe',      value: props.ticket.team?.name ?? '—' },
  { label: 'Assigné à',   value: props.ticket.assignee?.name ?? 'Non assigné' },
  { label: 'Rapporteur',  value: props.ticket.reporter?.name ?? '—' },
  { label: 'Créé le',     value: formatDate(props.ticket.created_at) },
  { label: 'Résolu le',   value: props.ticket.resolved_at ? formatDate(props.ticket.resolved_at) : '—' },
])

const ticketStatusClass = (s) => ({
  open: 'wh-badge-red', in_progress: 'wh-badge-amber', resolved: 'wh-badge-green', closed: 'wh-badge-slate',
}[s] ?? 'wh-badge-slate')

const statusLabel = (s) => ({
  open: 'Ouvert', in_progress: 'En cours', resolved: 'Résolu', closed: 'Fermé',
}[s] ?? s)

const priorityClass = (p) => ({
  low: 'wh-badge-slate', medium: 'wh-badge-blue', high: 'wh-badge-amber', critical: 'wh-badge-red',
}[p] ?? 'wh-badge-slate')

const priorityLabel = (p) => ({
  low: 'Faible', medium: 'Moyenne', high: 'Haute', critical: 'Critique',
}[p] ?? p)

const channelLabel = (c) => ({
  email: 'Email', web: 'Web', whatsapp: 'WhatsApp',
}[c] ?? c)

const formatDate = (date) => date ? new Date(date).toLocaleString('fr-FR') : '—'
const initials = (name) => name?.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase() ?? '?'
</script>

<style scoped>
.page-head { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:24px; gap:16px; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:22px; font-weight:600; letter-spacing:-0.02em; color:var(--fg-1); }
.btn-icon { background:var(--bg-canvas); color:var(--fg-2); border:1px solid var(--border-subtle); border-radius:var(--r-md); width:34px; height:34px; padding:0; display:inline-flex; align-items:center; justify-content:center; cursor:pointer; text-decoration:none; flex-shrink:0; }
.info-cell { padding:14px 18px; border-right:1px solid var(--border-subtle); }
.info-cell:last-child { border-right:0; }
.info-cell-label { font-size:11px;color:var(--fg-3);text-transform:uppercase;letter-spacing:0.06em;margin:0 0 4px; }
.info-cell-value { font-size:13px;font-weight:500;color:var(--fg-1);margin:0; }
.wh-panel-head { display:flex; align-items:center; justify-content:space-between; padding:14px 18px; border-bottom:1px solid var(--border-subtle); }
.wh-panel-head h3 { margin:0; font-size:14px; font-weight:600; color:var(--fg-1); }
.comment-list { display:flex; flex-direction:column; }
.comment-row { display:flex; gap:12px; padding:16px 18px; border-bottom:1px solid var(--border-subtle); }
.comment-row:last-child { border-bottom:0; }
.comment-avatar { width:30px; height:30px; border-radius:50%; background:var(--halo-50); color:var(--halo-700); font-size:11px; font-weight:600; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.comment-body { background:var(--bg-sunken); border-radius:var(--r-md); padding:10px 12px; font-size:13px; color:var(--fg-1); line-height:1.5; white-space:pre-line; }
.wh-badge { display:inline-flex; align-items:center; gap:5px; padding:2px 8px; border-radius:var(--r-pill); font-size:11px; font-weight:500; }
.wh-badge-dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
.wh-badge-green { background:var(--success-bg); color:var(--success-fg); }
.wh-badge-blue { background:var(--halo-50); color:var(--halo-700); }
.wh-badge-amber { background:var(--warn-bg); color:var(--warn-fg); }
.wh-badge-red { background:var(--danger-bg); color:var(--danger-fg); }
.wh-badge-slate { background:var(--bg-sunken); color:var(--fg-2); }
</style>
