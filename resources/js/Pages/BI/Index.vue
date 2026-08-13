<template>
  <AppLayout>
    <Head title="BI · Hub" />

    <!-- Header -->
    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Business Intelligence</h1>
        <p class="wh-page-subtitle">
          {{ dashboards.length }} tableau{{ dashboards.length !== 1 ? 'x' : '' }} de bord ·
          {{ kpis.length }} indicateur{{ kpis.length !== 1 ? 's' : '' }}
        </p>
      </div>
      <div class="page-actions">
        <a href="/bi/dashboards/builder" class="btn btn-primary">
          <i class="pi pi-plus" style="font-size:13px" />
          Nouveau dashboard
        </a>
        <a href="/bi/reports" class="btn btn-secondary">
          <i class="pi pi-file" style="font-size:13px" />
          Rapports
        </a>
      </div>
    </div>

    <!-- Quick navigation cards -->
    <div class="nav-grid" style="margin-bottom:28px">
      <a v-for="nav in navItems" :key="nav.href" :href="nav.href" class="nav-card">
        <div class="nav-card__icon" :style="{ background: nav.bg, color: nav.color }">
          <i :class="nav.icon" style="font-size:20px" />
        </div>
        <div>
          <p class="nav-card__title">{{ nav.title }}</p>
          <p class="nav-card__sub">{{ nav.sub }}</p>
        </div>
        <i class="pi pi-chevron-right nav-card__arrow" />
      </a>
    </div>

    <!-- KPI grid -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
      <span class="section-label">Indicateurs clés</span>
      <a href="/bi/kpis" class="wh-link">Voir tout <i class="pi pi-arrow-right" style="font-size:11px" /></a>
    </div>
    <div class="kpi-grid" style="margin-bottom:28px">
      <KpiCard
        v-for="kpi in kpis.slice(0, 6)"
        :key="kpi.id"
        :label="kpi.name"
        :value="kpi.value"
        :unit="kpi.unit"
        :trend="kpi.trend_percentage"
        :threshold="kpi.threshold"
        :sparkline="kpi.sparkline ?? []"
        :format="kpi.format ?? 'raw'"
      />
    </div>

    <!-- Dashboards list -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
      <span class="section-label">Tableaux de bord</span>
    </div>
    <div v-if="dashboards.length" class="dash-grid" style="margin-bottom:28px">
      <a
        v-for="db in dashboards"
        :key="db.id"
        :href="`/bi/dashboards/${db.id}`"
        class="dash-card"
      >
        <div class="dash-card__header">
          <i class="pi pi-th-large" style="font-size:16px;color:var(--halo-500)" />
          <span class="dash-card__name">{{ db.name }}</span>
        </div>
        <p class="dash-card__desc">{{ db.description ?? 'Tableau de bord personnalisé' }}</p>
        <div class="dash-card__footer">
          <span style="font-size:11px;color:var(--fg-3)">
            {{ db.widgets_count ?? 0 }} widget{{ (db.widgets_count ?? 0) !== 1 ? 's' : '' }}
          </span>
          <span style="font-size:11px;color:var(--fg-3)">{{ formatDate(db.updated_at) }}</span>
        </div>
      </a>

      <!-- New dashboard card -->
      <a href="/bi/dashboards/builder" class="dash-card dash-card--new">
        <i class="pi pi-plus-circle" style="font-size:28px;color:var(--halo-400);margin-bottom:8px" />
        <span style="font-size:13px;font-weight:500;color:var(--halo-600)">Créer un dashboard</span>
      </a>
    </div>
    <div v-else class="wh-empty-state" style="margin-bottom:28px">
      <i class="pi pi-th-large" style="font-size:36px;color:var(--fg-4,var(--fg-3));margin-bottom:12px" />
      <p style="font-size:15px;font-weight:500;color:var(--fg-2);margin:0 0 4px">Aucun tableau de bord</p>
      <p style="font-size:13px;color:var(--fg-3);margin:0 0 18px">Créez votre premier dashboard drag-and-drop.</p>
      <a href="/bi/dashboards/builder" class="btn btn-primary">
        <i class="pi pi-plus" style="font-size:13px" /> Créer un dashboard
      </a>
    </div>

    <!-- Recent Reports -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
      <span class="section-label">Rapports récents</span>
      <a href="/bi/reports" class="wh-link">Voir tout <i class="pi pi-arrow-right" style="font-size:11px" /></a>
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
          <tr v-for="report in recentReports" :key="report.id" class="wh-dt-row">
            <td style="font-weight:500;color:var(--fg-1)">{{ report.name }}</td>
            <td style="color:var(--fg-2);text-transform:capitalize">{{ report.type }}</td>
            <td>
              <span :class="reportStatusBadge(report.status)" class="wh-badge">
                <span class="wh-badge-dot" />
                {{ reportStatusLabel(report.status) }}
              </span>
            </td>
            <td style="color:var(--fg-3)">{{ formatDate(report.created_at) }}</td>
          </tr>
          <tr v-if="!recentReports.length">
            <td colspan="4" style="text-align:center;color:var(--fg-3);padding:32px 18px">Aucun rapport récent.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import KpiCard from '@/Components/BI/KpiCard.vue'

defineProps({
  kpis:          { type: Array, default: () => [] },
  dashboards:    { type: Array, default: () => [] },
  recentReports: { type: Array, default: () => [] },
})

const navItems = [
  { href: '/bi/analytics',    icon: 'pi pi-chart-line', title: 'Analytics',         sub: 'Revenus, tickets, leads',   bg: 'var(--halo-50)',    color: 'var(--halo-600)' },
  { href: '/bi/kpis',         icon: 'pi pi-gauge',      title: 'KPIs',              sub: 'Indicateurs & objectifs',   bg: '#f0fdf4',           color: '#16a34a' },
  { href: '/bi/reports',      icon: 'pi pi-file-pdf',   title: 'Rapports',          sub: 'Planifiés & exports',       bg: '#fff7ed',           color: '#c2410c' },
  { href: '/bi/alerts',       icon: 'pi pi-bell',       title: 'Alertes',           sub: 'Seuils & notifications',    bg: '#fefce8',           color: '#a16207' },
  { href: '/bi/data-sources', icon: 'pi pi-database',   title: 'Sources de données',sub: 'Connexions externes',       bg: '#f0f9ff',           color: '#0369a1' },
  { href: '/bi/sql-editor',   icon: 'pi pi-code',       title: 'Éditeur SQL',       sub: 'Requêtes personnalisées',   bg: '#faf5ff',           color: '#7c3aed' },
]

const reportStatusBadge = (status) => ({
  draft:     'wh-badge-slate',
  published: 'wh-badge-green',
}[status] ?? 'wh-badge-slate')

const reportStatusLabel = (status) => ({
  draft:     'Brouillon',
  published: 'Publié',
}[status] ?? status)

const formatDate = (value) =>
  value ? new Date(value).toLocaleDateString('fr-FR', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; gap:16px; flex-wrap:wrap; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; gap:8px; }
.btn { font-family:var(--font-sans); font-weight:500; font-size:13px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:background var(--dur-base); line-height:1.2; text-decoration:none; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover { background:var(--halo-700); }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover { background:var(--bg-sunken); }
.section-label { font-size:12px; font-weight:600; color:var(--fg-2); letter-spacing:0.05em; text-transform:uppercase; }
.wh-link { font-size:12px; color:var(--halo-600); text-decoration:none; display:inline-flex; align-items:center; gap:4px; }
.wh-link:hover { text-decoration:underline; }

/* Nav cards */
.nav-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:10px; }
.nav-card { display:flex; align-items:center; gap:12px; padding:14px 16px; background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); text-decoration:none; transition:all var(--dur-fast); }
.nav-card:hover { border-color:var(--halo-300); box-shadow:0 2px 8px rgba(0,0,0,0.06); }
.nav-card__icon { width:40px; height:40px; border-radius:var(--r-md); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.nav-card__title { font-size:13px; font-weight:600; color:var(--fg-1); margin:0; }
.nav-card__sub { font-size:11px; color:var(--fg-3); margin:2px 0 0; }
.nav-card__arrow { margin-left:auto; font-size:12px; color:var(--fg-4,var(--fg-3)); flex-shrink:0; }

/* KPI grid */
.kpi-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:12px; }

/* Dashboard cards */
.dash-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:12px; }
.dash-card { display:flex; flex-direction:column; padding:16px 18px; background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); text-decoration:none; transition:all var(--dur-fast); }
.dash-card:hover { border-color:var(--halo-300); box-shadow:0 2px 8px rgba(0,0,0,0.06); }
.dash-card__header { display:flex; align-items:center; gap:8px; margin-bottom:6px; }
.dash-card__name { font-size:14px; font-weight:600; color:var(--fg-1); }
.dash-card__desc { font-size:12px; color:var(--fg-3); margin:0 0 auto; flex:1; }
.dash-card__footer { display:flex; align-items:center; justify-content:space-between; margin-top:12px; }
.dash-card--new { align-items:center; justify-content:center; border-style:dashed; border-color:var(--halo-300); background:var(--halo-50,#eff4ff); }
.dash-card--new:hover { background:var(--halo-100,#e0e9ff); }

/* Empty state */
.wh-empty-state { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:60px 20px; background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); text-align:center; }

/* Table */
.wh-panel { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); overflow:hidden; }
.wh-dt { width:100%; border-collapse:collapse; font-size:14px; }
.wh-dt thead th { text-align:left; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:var(--fg-3); font-weight:600; padding:10px 18px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sunken); }
.wh-dt-row td { padding:11px 18px; border-bottom:1px solid var(--border-subtle); }
.wh-dt-row:last-child td { border-bottom:0; }
.wh-dt-row { cursor:pointer; transition:background var(--dur-fast); }
.wh-dt-row:hover { background:var(--bg-sunken); }
.wh-badge { display:inline-flex; align-items:center; gap:5px; padding:2px 8px; border-radius:var(--r-pill); font-size:11px; font-weight:500; }
.wh-badge-dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
.wh-badge-green { background:var(--success-bg); color:var(--success-fg); }
.wh-badge-slate { background:var(--bg-sunken); color:var(--fg-2); }
</style>
