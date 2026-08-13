<template>
  <AppLayout>
    <Head title="Email Marketing" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Email Marketing</h1>
        <p class="wh-page-subtitle">Vue d'ensemble de vos campagnes et performances</p>
      </div>
      <div class="page-actions">
        <a href="/email/campaigns/create" class="btn btn-secondary">
          <i class="pi pi-envelope" style="font-size:13px" /> Nouvelle campagne
        </a>
        <a href="/email/builder" class="btn btn-primary">
          <i class="pi pi-palette" style="font-size:13px" /> Builder Email
        </a>
      </div>
    </div>

    <!-- KPI row -->
    <div class="wh-kpi-grid">
      <div class="wh-kpi" v-for="kpi in kpiList" :key="kpi.label">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
          <div class="wh-kpi-icon" :style="{ background: kpi.iconBg }">
            <i :class="['pi', kpi.icon]" :style="{ color: kpi.iconColor, fontSize: '16px' }" />
          </div>
          <span class="wh-kpi-label">{{ kpi.label }}</span>
        </div>
        <div class="wh-kpi-num font-display">{{ kpi.value }}</div>
        <div v-if="kpi.sub" style="font-size:12px;color:var(--fg-3);margin-top:4px">{{ kpi.sub }}</div>
      </div>
    </div>

    <div class="content-grid">
      <!-- Recent Campaigns -->
      <div class="wh-panel section-main">
        <div class="section-header">
          <h2 class="section-title">Campagnes récentes</h2>
          <a href="/email/campaigns" class="link-more">Voir toutes <i class="pi pi-arrow-right" style="font-size:11px" /></a>
        </div>
        <table class="wh-dt">
          <thead>
            <tr>
              <th>Nom</th>
              <th>Statut</th>
              <th class="num">Envoyés</th>
              <th class="num">Ouverture</th>
              <th class="num">Clics</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in recentCampaigns" :key="c.id" class="wh-dt-row">
              <td>
                <p style="font-weight:500;color:var(--fg-1);margin:0">{{ c.name }}</p>
                <p style="font-size:12px;color:var(--fg-3);margin:2px 0 0">{{ c.subject }}</p>
              </td>
              <td>
                <span :class="statusBadge(c.status)" class="wh-badge">
                  <span class="wh-badge-dot" />{{ statusLabel(c.status) }}
                </span>
              </td>
              <td class="num" style="color:var(--fg-2)">{{ c.total_sent }}</td>
              <td class="num" style="color:var(--fg-2)">{{ openRate(c) }}%</td>
              <td class="num" style="color:var(--fg-2)">{{ clickRate(c) }}%</td>
            </tr>
            <tr v-if="!recentCampaigns.length">
              <td colspan="5" style="text-align:center;color:var(--fg-3);padding:32px">Aucune campagne pour l'instant.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Quick access sidebar -->
      <div class="section-sidebar">
        <!-- Quick links -->
        <div class="wh-panel" style="margin-bottom:16px">
          <div class="section-header" style="padding:14px 18px 0">
            <h2 class="section-title">Accès rapide</h2>
          </div>
          <div style="padding:8px 8px 12px">
            <a v-for="link in quickLinks" :key="link.href" :href="link.href" class="quick-link">
              <div class="quick-link-icon" :style="{ background: link.iconBg }">
                <i :class="['pi', link.icon]" :style="{ color: link.iconColor, fontSize: '15px' }" />
              </div>
              <div>
                <p style="font-weight:500;font-size:14px;color:var(--fg-1);margin:0">{{ link.label }}</p>
                <p style="font-size:12px;color:var(--fg-3);margin:1px 0 0">{{ link.sub }}</p>
              </div>
              <i class="pi pi-chevron-right" style="margin-left:auto;color:var(--fg-4);font-size:11px" />
            </a>
          </div>
        </div>

        <!-- Top templates -->
        <div class="wh-panel">
          <div class="section-header" style="padding:14px 18px 0">
            <h2 class="section-title">Templates populaires</h2>
            <a href="/email/templates" class="link-more">Voir tout</a>
          </div>
          <div style="padding:8px 8px 12px">
            <div v-for="t in topTemplates" :key="t.id" class="template-item">
              <div class="template-thumb">
                <i class="pi pi-file-edit" style="font-size:18px;color:var(--fg-3)" />
              </div>
              <div style="flex:1;min-width:0">
                <p style="font-weight:500;font-size:13px;color:var(--fg-1);margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ t.name }}</p>
                <p style="font-size:11px;color:var(--fg-3);margin:1px 0 0;text-transform:capitalize">{{ t.category ?? 'général' }}</p>
              </div>
              <a :href="`/email/builder?template=${t.id}`" style="font-size:11px;color:var(--halo-500);font-weight:500;white-space:nowrap">Éditer</a>
            </div>
            <div v-if="!topTemplates.length" style="padding:16px 10px;text-align:center;color:var(--fg-3);font-size:13px">
              Aucun template disponible.
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  stats:           { type: Object, default: () => ({}) },
  recentCampaigns: { type: Array,  default: () => [] },
  topTemplates:    { type: Array,  default: () => [] },
})

const kpiList = computed(() => [
  {
    label: 'Emails envoyés',
    value: (props.stats.total_sent ?? 0).toLocaleString('fr-FR'),
    sub: 'tous temps',
    icon: 'pi-send',
    iconBg: 'var(--halo-50)',
    iconColor: 'var(--halo-600)',
  },
  {
    label: 'Taux d\'ouverture moyen',
    value: `${(props.stats.avg_open_rate ?? 0).toFixed(1)}%`,
    sub: 'sur toutes les campagnes',
    icon: 'pi-eye',
    iconBg: '#ecfdf5',
    iconColor: '#059669',
  },
  {
    label: 'Taux de clic moyen',
    value: `${(props.stats.avg_click_rate ?? 0).toFixed(1)}%`,
    sub: 'sur toutes les campagnes',
    icon: 'pi-external-link',
    iconBg: '#eff6ff',
    iconColor: '#2563eb',
  },
  {
    label: 'Désabonnements',
    value: (props.stats.total_unsubscribed ?? 0).toLocaleString('fr-FR'),
    sub: `${(props.stats.unsubscribe_rate ?? 0).toFixed(2)}% taux global`,
    icon: 'pi-user-minus',
    iconBg: '#fff7ed',
    iconColor: '#ea580c',
  },
])

const quickLinks = [
  { href: '/email/campaigns',        label: 'Campagnes',       sub: 'Gérer et envoyer',        icon: 'pi-send',       iconBg: 'var(--halo-50)',   iconColor: 'var(--halo-600)' },
  { href: '/email/templates',        label: 'Templates',       sub: 'Bibliothèque de modèles', icon: 'pi-file-edit',  iconBg: '#ecfdf5',          iconColor: '#059669' },
  { href: '/email/subscribers',      label: 'Abonnés',         sub: 'Gérer les listes',        icon: 'pi-users',      iconBg: '#eff6ff',          iconColor: '#2563eb' },
  { href: '/email/automation',       label: 'Automatisation',  sub: 'Flows et séquences',      icon: 'pi-sync',       iconBg: '#fdf4ff',          iconColor: '#9333ea' },
  { href: '/email/builder',          label: 'Builder WYSIWYG', sub: 'Créer un template visuel',icon: 'pi-palette',    iconBg: '#fff7ed',          iconColor: '#ea580c' },
]

const statusBadge = (s) => ({
  draft: 'wh-badge-slate', sent: 'wh-badge-green', scheduled: 'wh-badge-blue',
  sending: 'wh-badge-amber', paused: 'wh-badge-amber', cancelled: 'wh-badge-red',
}[s] ?? 'wh-badge-slate')

const statusLabel = (s) => ({
  draft: 'Brouillon', sent: 'Envoyée', scheduled: 'Planifiée',
  sending: 'En envoi', paused: 'En pause', cancelled: 'Annulée',
}[s] ?? s)

const openRate  = (c) => !c.total_sent ? '0.0' : ((c.total_opened  / c.total_sent) * 100).toFixed(1)
const clickRate = (c) => !c.total_sent ? '0.0' : ((c.total_clicked / c.total_sent) * 100).toFixed(1)
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; gap:16px; }
.wh-page-title { margin:0; font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; gap:8px; }
.btn { font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:background var(--dur-base); line-height:1.2; text-decoration:none; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover { background:var(--halo-700); }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover { background:var(--bg-sunken); }
.wh-kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:24px; }
@media (max-width:900px) { .wh-kpi-grid { grid-template-columns:repeat(2,1fr); } }
.wh-kpi { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); padding:18px 20px; }
.wh-kpi-icon { width:36px; height:36px; border-radius:var(--r-md); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.wh-kpi-label { font-size:12px; font-weight:500; color:var(--fg-3); letter-spacing:0.04em; text-transform:uppercase; }
.wh-kpi-num { font-size:26px; font-weight:700; color:var(--fg-1); line-height:1; }
.content-grid { display:grid; grid-template-columns:1fr 340px; gap:16px; align-items:start; }
@media (max-width:1100px) { .content-grid { grid-template-columns:1fr; } }
.section-main { overflow:hidden; }
.section-sidebar { display:flex; flex-direction:column; gap:0; }
.wh-panel { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); overflow:hidden; }
.section-header { display:flex; align-items:center; justify-content:space-between; padding:14px 18px 10px; }
.section-title { margin:0; font-size:15px; font-weight:600; color:var(--fg-1); }
.link-more { font-size:12px; color:var(--halo-500); text-decoration:none; font-weight:500; display:flex; align-items:center; gap:4px; }
.link-more:hover { color:var(--halo-700); }
.wh-dt { width:100%; border-collapse:collapse; font-size:14px; }
.wh-dt thead th { text-align:left; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:var(--fg-3); font-weight:600; padding:10px 18px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sunken); }
.wh-dt thead th.num { text-align:right; }
.wh-dt-row td { padding:11px 18px; border-bottom:1px solid var(--border-subtle); }
.wh-dt-row td.num { text-align:right; }
.wh-dt-row:last-child td { border-bottom:0; }
.wh-dt-row { cursor:pointer; transition:background var(--dur-fast); }
.wh-dt-row:hover { background:var(--bg-sunken); }
.wh-badge { display:inline-flex; align-items:center; gap:5px; padding:2px 8px; border-radius:var(--r-pill); font-size:11px; font-weight:500; }
.wh-badge-dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
.wh-badge-green  { background:var(--success-bg); color:var(--success-fg); }
.wh-badge-blue   { background:var(--halo-50); color:var(--halo-700); }
.wh-badge-amber  { background:var(--warn-bg); color:var(--warn-fg); }
.wh-badge-red    { background:var(--danger-bg); color:var(--danger-fg); }
.wh-badge-slate  { background:var(--bg-sunken); color:var(--fg-2); }
.quick-link { display:flex; align-items:center; gap:12px; padding:10px; border-radius:var(--r-md); text-decoration:none; transition:background var(--dur-fast); }
.quick-link:hover { background:var(--bg-sunken); }
.quick-link-icon { width:36px; height:36px; border-radius:var(--r-md); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.template-item { display:flex; align-items:center; gap:10px; padding:8px 10px; border-radius:var(--r-md); transition:background var(--dur-fast); }
.template-item:hover { background:var(--bg-sunken); }
.template-thumb { width:40px; height:40px; background:var(--bg-sunken); border:1px solid var(--border-subtle); border-radius:var(--r-sm); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
</style>
