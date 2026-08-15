<template>
  <AppLayout>
    <Head title="Dashboard 360°" />

    <!-- Welcome banner (first visit) -->
    <WelcomeBanner
      dismiss-key="dashboard:welcome"
      icon="pi pi-sparkles"
      :title="$t('help.welcomeTitle')"
      :description="$t('help.welcomeDesc')"
      :actions="[
        { label: $t('help.startTour'), icon: 'pi pi-play', primary: true, onClick: () => help.startTour('dashboard') },
        { label: $t('help.skipTour'), onClick: () => help.dismiss('dashboard:welcome') },
      ]"
    />
    <GuidedTour tour-id="dashboard" :steps="dashboardTourSteps" />

    <!-- ── Page header ─────────────────────────────────────────────── -->
    <div class="page-head">
      <div>
        <p class="eyebrow">{{ greeting }}</p>
        <h1 class="page-title">{{ $page.props.auth?.user?.name }}</h1>
        <div style="display:flex;align-items:center;gap:10px;margin-top:6px">
          <span :class="['role-badge', `role-badge--${roleKey}`]">
            <i :class="roleIcon" style="font-size:10px" />
            {{ roleLabel }}
          </span>
          <span style="font-size:13px;color:var(--fg-3)">{{ currentDateTime }}</span>
        </div>
      </div>
      <div style="display:flex;gap:8px">
        <button class="btn btn-secondary" :disabled="refreshing" @click="refreshData">
          <i :class="['pi', refreshing ? 'pi-spin pi-spinner' : 'pi-refresh']" style="font-size:13px" />
          {{ $t('common.refresh') }}
        </button>
      </div>
    </div>

    <!-- ── KPI Big Picture ────────────────────────────────────────── -->
    <section style="margin-bottom:28px" data-tour-step="kpi">
      <div class="section-heading">
        <i class="pi pi-chart-bar" />
        <span>Vue d'ensemble</span>
      </div>
      <div class="kpi-grid">
        <DashKpiCard
          v-for="kpi in kpiCards"
          :key="kpi.key"
          :title="kpi.title"
          :value="kpi.value"
          :icon="kpi.icon"
          :trend="kpi.trend"
          :severity="kpi.severity"
          :format="kpi.format"
          :loading="loading"
        />
      </div>
    </section>

    <!-- ── Cartographie 360° des modules ─────────────────────────── -->
    <section style="margin-bottom:28px" data-tour-step="modules">
      <div class="section-heading">
        <i class="pi pi-th-large" />
        <span>Cartographie 360° des modules</span>
      </div>
      <div class="modules-grid">
        <DashModuleCard
          v-for="mod in roleModules"
          :key="mod.module"
          :module="mod.module"
          :label="mod.label"
          :icon="mod.icon"
          :kpi="mod.kpi"
          :kpi-label="mod.kpiLabel"
          :route="mod.route"
          :color="mod.color"
          :enabled="mod.enabled"
        />
      </div>
    </section>

    <!-- ── Actions IA suggérées + Activité récente ────────────────── -->
    <div class="bottom-grid" data-tour-step="ai">

      <!-- AI Insights -->
      <div class="wh-panel" style="background:linear-gradient(160deg,var(--halo-50) 0%,var(--bg-canvas) 60%)">
        <div class="wh-panel-head" style="border-bottom-color:var(--halo-100)">
          <div style="display:flex;align-items:center;gap:8px">
            <div class="ai-panel-icon-wrap">
              <i class="pi pi-sparkles" style="font-size:11px;color:#fff" />
            </div>
            <h3>Actions IA suggérées</h3>
          </div>
          <span class="eyebrow" style="background:var(--halo-100);color:var(--halo-700);padding:2px 8px;border-radius:var(--r-pill);font-size:10px">Beta</span>
        </div>
        <div style="padding:12px 18px;display:flex;flex-direction:column;gap:8px">
          <template v-if="loading">
            <div v-for="i in 3" :key="i" class="insight-skeleton" />
          </template>
          <template v-else>
            <DashInsightCard
              v-for="(insight, idx) in visibleInsights"
              :key="idx"
              :insight="insight"
              @dismiss="dismissInsight(idx)"
            />
            <div v-if="visibleInsights.length === 0" class="empty-state">
              <i class="pi pi-check-circle" style="font-size:20px;color:#22c55e" />
              <span>Aucune action urgente — tout est sous contrôle !</span>
            </div>
          </template>
        </div>
      </div>

      <!-- Activité récente -->
      <div class="wh-panel" data-tour-step="activity">
        <div class="wh-panel-head">
          <h3>Activité récente</h3>
          <span style="font-size:12px;color:var(--fg-3)">Aujourd'hui</span>
        </div>
        <div>
          <div
            v-for="activity in recentActivities"
            :key="activity.id"
            class="activity-row"
          >
            <div class="activity-ic" :style="{ background: activity.bg }">
              <i :class="activity.icon" style="font-size:11px;color:#fff" />
            </div>
            <div style="flex:1;min-width:0">
              <div style="font-size:13px;color:var(--fg-1)">{{ activity.text }}</div>
              <div style="font-size:11px;color:var(--fg-3);margin-top:2px">{{ activity.time }}</div>
            </div>
          </div>
          <div v-if="recentActivities.length === 0" class="empty-state" style="padding:32px 18px">
            <i class="pi pi-clock" style="font-size:20px;color:var(--fg-4)" />
            <span>Aucune activité récente</span>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'
import WelcomeBanner from '@/Components/UI/WelcomeBanner.vue'
import GuidedTour from '@/Components/UI/GuidedTour.vue'
import DashKpiCard from '@/Components/Dashboard/KpiCard.vue'
import DashModuleCard from '@/Components/Dashboard/ModuleCard.vue'
import DashInsightCard from '@/Components/Dashboard/InsightCard.vue'
import type { Insight } from '@/Components/Dashboard/InsightCard.vue'
import { useHelpStore } from '@/stores/help'

interface Metrics {
  role: string
  [key: string]: unknown
}

const props = defineProps<{
  metrics:  Metrics
  insights: Insight[]
}>()

const { t } = useI18n()
const page  = usePage()
const help  = useHelpStore()

const loading      = ref(false)
const refreshing   = ref(false)
const currentDateTime = ref('')
const visibleInsights = ref<Insight[]>([...props.insights])

let clockInterval: ReturnType<typeof setInterval> | null = null

// ── Clock ─────────────────────────────────────────────────────────

function updateClock() {
  const now = new Date()
  currentDateTime.value = now.toLocaleString('fr-FR', {
    weekday: 'long', day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit',
  })
}

onMounted(() => {
  updateClock()
  clockInterval = setInterval(updateClock, 60_000)
})

onUnmounted(() => {
  if (clockInterval) clearInterval(clockInterval)
})

// ── Role helpers ──────────────────────────────────────────────────

const role    = computed(() => props.metrics.role as string)
const roleKey = computed(() => role.value.replace('-', '_'))

const roleConfig: Record<string, { label: string; icon: string }> = {
  'super-admin': { label: 'Super Administrateur', icon: 'pi pi-shield' },
  'admin':       { label: 'Administrateur',        icon: 'pi pi-cog' },
  'manager':     { label: 'Manager',               icon: 'pi pi-briefcase' },
  'accountant':  { label: 'Comptable',             icon: 'pi pi-receipt' },
  'hr-manager':  { label: 'Responsable RH',        icon: 'pi pi-id-card' },
  'sales-rep':   { label: 'Commercial',            icon: 'pi pi-users' },
  'employee':    { label: 'Employé',               icon: 'pi pi-user' },
}

const roleLabel = computed(() => roleConfig[role.value]?.label ?? role.value)
const roleIcon  = computed(() => roleConfig[role.value]?.icon ?? 'pi pi-user')

// ── Greeting ──────────────────────────────────────────────────────

const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 12) return 'Bonjour'
  if (h < 18) return 'Bon après-midi'
  return 'Bonsoir'
})

// ── Tour steps ────────────────────────────────────────────────────

const dashboardTourSteps = [
  { tag: t('help.tour'), icon: 'pi pi-chart-bar',    title: t('help.tour.dashboard.kpi.title'),     description: t('help.tour.dashboard.kpi.desc') },
  { tag: t('help.tour'), icon: 'pi pi-th-large',     title: t('help.tour.dashboard.modules.title'), description: t('help.tour.dashboard.modules.desc') },
  { tag: t('help.tour'), icon: 'pi pi-sparkles',     title: t('help.tour.dashboard.ai.title'),      description: t('help.tour.dashboard.ai.desc') },
]

// ── KPI Cards — role-aware ────────────────────────────────────────

interface KpiDef {
  key:      string
  title:    string
  value:    number | string
  icon:     string
  trend?:   number
  severity: 'success' | 'warning' | 'danger' | 'info' | 'default'
  format?:  'number' | 'currency' | 'percent' | 'raw'
}

const m = computed(() => props.metrics)

const kpiCards = computed((): KpiDef[] => {
  const r = role.value

  if (r === 'admin' || r === 'super-admin') {
    return [
      { key: 'revenue',   title: 'Revenu total',        value: m.value.total_revenue as number ?? 0,     icon: 'pi pi-euro',      severity: 'success', format: 'currency', trend: 0 },
      { key: 'users',     title: 'Utilisateurs',        value: m.value.users_count as number ?? 0,       icon: 'pi pi-users',     severity: 'info',    format: 'number' },
      { key: 'tickets',   title: 'Tickets ouverts',     value: m.value.open_tickets as number ?? 0,      icon: 'pi pi-headphones', severity: (m.value.open_tickets as number) > 20 ? 'danger' : 'warning', format: 'number' },
      { key: 'approvals', title: 'Approbations',        value: m.value.pending_approvals as number ?? 0, icon: 'pi pi-check-circle', severity: (m.value.pending_approvals as number) > 5 ? 'warning' : 'default', format: 'number' },
      { key: 'mrr',       title: 'MRR du mois',         value: m.value.mrr as number ?? 0,               icon: 'pi pi-chart-line', severity: 'success', format: 'currency', trend: 0 },
      { key: 'active',    title: 'Actifs aujourd\'hui', value: m.value.active_users_today as number ?? 0, icon: 'pi pi-bolt',      severity: 'info',    format: 'number' },
    ]
  }

  if (r === 'manager') {
    return [
      { key: 'team',       title: 'Taille d\'équipe',    value: m.value.team_size as number ?? 0,              icon: 'pi pi-users',     severity: 'info',    format: 'number' },
      { key: 'leaves',     title: 'Congés en attente',   value: m.value.pending_leave_requests as number ?? 0, icon: 'pi pi-calendar',  severity: (m.value.pending_leave_requests as number) > 3 ? 'warning' : 'default', format: 'number' },
      { key: 'tasks',      title: 'Tâches ouvertes',     value: m.value.open_tasks as number ?? 0,             icon: 'pi pi-check-square', severity: 'default', format: 'number' },
      { key: 'overdue',    title: 'Tâches en retard',    value: m.value.overdue_tasks as number ?? 0,          icon: 'pi pi-exclamation-triangle', severity: (m.value.overdue_tasks as number) > 0 ? 'danger' : 'success', format: 'number' },
    ]
  }

  if (r === 'accountant') {
    return [
      { key: 'unpaid',      title: 'Factures impayées',    value: m.value.unpaid_invoices_count as number ?? 0, icon: 'pi pi-receipt',   severity: (m.value.unpaid_invoices_count as number) > 5 ? 'danger' : 'warning', format: 'number' },
      { key: 'unpaid_amt',  title: 'Montant impayé',       value: m.value.unpaid_invoices_total as number ?? 0, icon: 'pi pi-euro',      severity: 'danger',  format: 'currency' },
      { key: 'overdue',     title: 'Factures échues',      value: m.value.overdue_invoices as number ?? 0,      icon: 'pi pi-calendar-times', severity: (m.value.overdue_invoices as number) > 0 ? 'danger' : 'success', format: 'number' },
      { key: 'expenses',    title: 'Notes de frais',       value: m.value.expense_reports_pending as number ?? 0, icon: 'pi pi-wallet',  severity: 'warning', format: 'number' },
    ]
  }

  if (r === 'hr-manager') {
    return [
      { key: 'headcount',  title: 'Effectif actif',        value: m.value.headcount as number ?? 0,       icon: 'pi pi-id-card',   severity: 'info',    format: 'number' },
      { key: 'positions',  title: 'Postes ouverts',        value: m.value.open_positions as number ?? 0,  icon: 'pi pi-briefcase', severity: (m.value.open_positions as number) > 0 ? 'warning' : 'default', format: 'number' },
      { key: 'leaves',     title: 'Congés en attente',     value: m.value.pending_leaves as number ?? 0,  icon: 'pi pi-calendar',  severity: (m.value.pending_leaves as number) > 3 ? 'warning' : 'default', format: 'number' },
      { key: 'reviews',    title: 'Cycles d\'évaluation',  value: m.value.upcoming_reviews as number ?? 0, icon: 'pi pi-star',     severity: 'info',    format: 'number' },
    ]
  }

  if (r === 'sales-rep') {
    return [
      { key: 'leads',    title: 'Mes leads',          value: m.value.my_leads_count as number ?? 0,        icon: 'pi pi-users',    severity: 'info',    format: 'number' },
      { key: 'opps',     title: 'Opportunités',        value: m.value.my_opportunities_count as number ?? 0, icon: 'pi pi-chart-bar', severity: 'default', format: 'number' },
      { key: 'pipeline', title: 'Pipeline',            value: m.value.my_pipeline_value as number ?? 0,     icon: 'pi pi-euro',     severity: 'success', format: 'currency' },
      { key: 'quota',    title: 'Quota mensuel',       value: m.value.quota_progress as number ?? 0,        icon: 'pi pi-percentage', severity: (m.value.quota_progress as number) >= 100 ? 'success' : (m.value.quota_progress as number) < 50 ? 'danger' : 'warning', format: 'percent', trend: 0 },
    ]
  }

  // Employee (default)
  return [
    { key: 'tasks',    title: 'Mes tâches',         value: m.value.my_open_tasks as number ?? 0,       icon: 'pi pi-check-square', severity: 'default', format: 'number' },
    { key: 'expenses', title: 'Notes de frais',     value: m.value.my_pending_expenses as number ?? 0, icon: 'pi pi-wallet',       severity: 'warning', format: 'number' },
    { key: 'tickets',  title: 'Mes tickets',        value: m.value.my_open_tickets as number ?? 0,     icon: 'pi pi-headphones',   severity: 'info',    format: 'number' },
    { key: 'events',   title: 'Événements à venir', value: m.value.upcoming_events as number ?? 0,     icon: 'pi pi-calendar',     severity: 'default', format: 'number' },
  ]
})

// ── Module cards — role-aware ──────────────────────────────────────

interface ModDef {
  module:   string
  label:    string
  icon:     string
  kpi?:     number | string
  kpiLabel: string
  route:    string
  color:    string
  enabled:  boolean
}

const enabledModules = computed<string[]>(() => (page.props.enabledModules as string[]) ?? [])

function modEnabled(name: string): boolean {
  const em = enabledModules.value
  return em.length === 0 || em.includes(name)
}

const roleModules = computed((): ModDef[] => {
  const r = role.value

  const allModules: ModDef[] = [
    { module: 'CRM',           label: 'CRM',             icon: 'pi pi-users',      kpi: m.value.my_leads_count as number ?? m.value.users_count, kpiLabel: 'leads',      route: '/crm/contacts',          color: 'var(--halo-500)',  enabled: modEnabled('CRM') },
    { module: 'Accounting',    label: 'Comptabilité',    icon: 'pi pi-receipt',    kpi: m.value.unpaid_invoices_count as number,                 kpiLabel: 'impayées',   route: '/accounting/invoices',   color: '#f59e0b',          enabled: modEnabled('Accounting') },
    { module: 'HR',            label: 'Ressources humaines', icon: 'pi pi-id-card', kpi: m.value.headcount as number,                           kpiLabel: 'employés',   route: '/hr/employees',          color: '#8b5cf6',          enabled: modEnabled('HR') },
    { module: 'Helpdesk',      label: 'Support',         icon: 'pi pi-headphones', kpi: m.value.open_tickets as number,                          kpiLabel: 'tickets',    route: '/helpdesk/tickets',      color: '#ef4444',          enabled: modEnabled('Helpdesk') },
    { module: 'Projects',      label: 'Projets',         icon: 'pi pi-briefcase',  kpi: m.value.open_tasks as number ?? m.value.my_open_tasks,  kpiLabel: 'tâches',     route: '/projects',              color: '#7c3aed',          enabled: modEnabled('Projects') },
    { module: 'Inventory',     label: 'Inventaire',      icon: 'pi pi-box',        kpi: undefined,                                               kpiLabel: 'produits',   route: '/inventory/products',    color: '#10b981',          enabled: modEnabled('Inventory') },
    { module: 'Manufacturing', label: 'Production',      icon: 'pi pi-cog',        kpi: undefined,                                               kpiLabel: 'OFs',        route: '/manufacturing/orders',  color: '#6366f1',          enabled: modEnabled('Manufacturing') },
    { module: 'POS',           label: 'Point de vente',  icon: 'pi pi-barcode',    kpi: undefined,                                               kpiLabel: 'ventes',     route: '/pos/orders',            color: '#f97316',          enabled: modEnabled('POS') },
    { module: 'Ecommerce',     label: 'E-Commerce',      icon: 'pi pi-shopping-bag', kpi: undefined,                                             kpiLabel: 'commandes',  route: '/ecommerce/orders',      color: '#06b6d4',          enabled: modEnabled('Ecommerce') },
    { module: 'Email',         label: 'Email Marketing', icon: 'pi pi-envelope',   kpi: undefined,                                               kpiLabel: 'campagnes',  route: '/email/campaigns',       color: '#84cc16',          enabled: modEnabled('Email') },
    { module: 'Documents',     label: 'Documents',       icon: 'pi pi-folder',     kpi: undefined,                                               kpiLabel: 'docs',       route: '/documents',             color: '#64748b',          enabled: modEnabled('Documents') },
    { module: 'BI',            label: 'Business Intelligence', icon: 'pi pi-chart-bar', kpi: undefined,                                          kpiLabel: 'rapports',   route: '/bi',                    color: '#0ea5e9',          enabled: modEnabled('BI') },
  ]

  const roleModuleMap: Record<string, string[]> = {
    'admin':       ['CRM', 'Accounting', 'HR', 'Helpdesk', 'Projects', 'Inventory', 'Manufacturing', 'POS', 'Ecommerce', 'Email', 'Documents', 'BI'],
    'super-admin': ['CRM', 'Accounting', 'HR', 'Helpdesk', 'Projects', 'Inventory', 'Manufacturing', 'POS', 'Ecommerce', 'Email', 'Documents', 'BI'],
    'manager':     ['Projects', 'HR', 'CRM', 'Helpdesk', 'Accounting'],
    'accountant':  ['Accounting', 'Documents', 'HR'],
    'hr-manager':  ['HR', 'Documents', 'Projects'],
    'sales-rep':   ['CRM', 'Email', 'Documents', 'Ecommerce'],
    'employee':    ['Projects', 'Documents', 'Helpdesk'],
  }

  const allowed = roleModuleMap[r] ?? roleModuleMap['employee']
  return allModules.filter(mod => allowed.includes(mod.module))
})

// ── Recent activities (role-based mock) ───────────────────────────

interface Activity {
  id:   number
  text: string
  time: string
  icon: string
  bg:   string
}

const recentActivities = computed((): Activity[] => {
  const r = role.value
  const now = new Date()
  const fmt = (mins: number) => {
    const d = new Date(now.getTime() - mins * 60_000)
    return `Il y a ${mins < 60 ? `${mins} min` : `${Math.round(mins / 60)} h`}`
  }

  if (r === 'admin' || r === 'super-admin') {
    return [
      { id: 1, text: 'Nouvel utilisateur inscrit : Marie Laurent',        time: fmt(5),   icon: 'pi pi-user-plus',  bg: 'var(--halo-500)' },
      { id: 2, text: 'Module Ecommerce activé pour le compte',            time: fmt(23),  icon: 'pi pi-check',      bg: '#22c55e' },
      { id: 3, text: '3 tickets critiques escaladés au niveau 2',         time: fmt(45),  icon: 'pi pi-exclamation-triangle', bg: '#ef4444' },
      { id: 4, text: 'Rapport BI généré : performance Q2',                time: fmt(90),  icon: 'pi pi-chart-bar',  bg: '#3b82f6' },
      { id: 5, text: 'Sauvegarde automatique effectuée avec succès',      time: fmt(180), icon: 'pi pi-database',   bg: '#64748b' },
    ]
  }

  if (r === 'sales-rep') {
    return [
      { id: 1, text: 'Opportunité "Projet ERP Acme" mise à jour',         time: fmt(10),  icon: 'pi pi-chart-bar',  bg: 'var(--halo-500)' },
      { id: 2, text: 'Appel planifié avec Sophie Dubois (TechCorp)',       time: fmt(30),  icon: 'pi pi-phone',      bg: '#22c55e' },
      { id: 3, text: 'Devis envoyé à Martin Dupont',                      time: fmt(60),  icon: 'pi pi-file-pdf',   bg: '#3b82f6' },
      { id: 4, text: 'Lead qualifié : Entreprise BetaSystems',             time: fmt(120), icon: 'pi pi-users',      bg: '#8b5cf6' },
      { id: 5, text: 'Email de relance envoyé à 5 prospects',             time: fmt(200), icon: 'pi pi-envelope',   bg: '#f59e0b' },
    ]
  }

  if (r === 'hr-manager') {
    return [
      { id: 1, text: 'Demande de congé approuvée pour Jean Bernard',      time: fmt(8),   icon: 'pi pi-calendar-plus', bg: '#22c55e' },
      { id: 2, text: 'Nouveau candidat pour le poste Dev Senior',         time: fmt(35),  icon: 'pi pi-user-plus',  bg: 'var(--halo-500)' },
      { id: 3, text: 'Cycle d\'évaluation Q2 lancé',                      time: fmt(70),  icon: 'pi pi-star',       bg: '#f59e0b' },
      { id: 4, text: 'Fiche de paie générée pour 45 employés',            time: fmt(150), icon: 'pi pi-wallet',     bg: '#8b5cf6' },
      { id: 5, text: 'Formation "Leadership" planifiée pour juin',         time: fmt(240), icon: 'pi pi-book',       bg: '#06b6d4' },
    ]
  }

  if (r === 'accountant') {
    return [
      { id: 1, text: 'Facture #2024-0892 marquée comme payée',            time: fmt(12),  icon: 'pi pi-check-circle', bg: '#22c55e' },
      { id: 2, text: 'Note de frais de Pierre Martin soumise (€320)',     time: fmt(40),  icon: 'pi pi-wallet',     bg: '#f59e0b' },
      { id: 3, text: 'Rapprochement bancaire BNP : 23 transactions',      time: fmt(75),  icon: 'pi pi-sync',       bg: '#3b82f6' },
      { id: 4, text: 'Déclaration TVA du mois prête pour vérification',   time: fmt(160), icon: 'pi pi-receipt',    bg: '#8b5cf6' },
      { id: 5, text: 'Budget Q3 approuvé par la direction',               time: fmt(300), icon: 'pi pi-chart-bar',  bg: 'var(--halo-500)' },
    ]
  }

  // Default (manager / employee)
  return [
    { id: 1, text: 'Tâche "Rapport mensuel" terminée',                    time: fmt(15),  icon: 'pi pi-check',      bg: '#22c55e' },
    { id: 2, text: 'Réunion d\'équipe planifiée à 14h',                   time: fmt(40),  icon: 'pi pi-calendar',   bg: '#3b82f6' },
    { id: 3, text: 'Document "Procédure onboarding" partagé',             time: fmt(80),  icon: 'pi pi-folder',     bg: '#8b5cf6' },
    { id: 4, text: 'Ticket #1024 résolu par le support',                  time: fmt(130), icon: 'pi pi-headphones', bg: '#ef4444' },
    { id: 5, text: 'Mise à jour du projet Transformation Digitale',       time: fmt(220), icon: 'pi pi-briefcase',  bg: 'var(--halo-500)' },
  ]
})

// ── Insight management ────────────────────────────────────────────

function dismissInsight(idx: number) {
  visibleInsights.value.splice(idx, 1)
}

// ── Refresh ───────────────────────────────────────────────────────

async function refreshData() {
  refreshing.value = true
  try {
    await new Promise<void>(resolve => setTimeout(resolve, 400))
    window.location.reload()
  } finally {
    refreshing.value = false
  }
}
</script>

<style scoped>
/* ─── Page header ──────────────────────────────────────────────── */
.page-head {
  display: flex; align-items: flex-end; justify-content: space-between;
  margin-bottom: 28px; gap: 16px; flex-wrap: wrap;
}
.page-title {
  margin: 4px 0 0; font-family: var(--font-display); font-size: 26px;
  font-weight: 700; letter-spacing: -.022em; color: var(--fg-1);
}

/* ─── Role badge ───────────────────────────────────────────────── */
.role-badge {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 11px; font-weight: 600; padding: 3px 10px;
  border-radius: var(--r-pill); letter-spacing: .03em;
}
.role-badge--admin, .role-badge--super_admin { background: #fee2e2; color: #dc2626; }
.role-badge--manager   { background: #dbeafe; color: #2563eb; }
.role-badge--accountant { background: #fef9c3; color: #ca8a04; }
.role-badge--hr_manager { background: #f3e8ff; color: #7c3aed; }
.role-badge--sales_rep  { background: #dcfce7; color: #15803d; }
.role-badge--employee   { background: var(--bg-sunken); color: var(--fg-2); }

/* ─── Buttons ──────────────────────────────────────────────────── */
.btn {
  font-family: var(--font-sans); font-weight: 500; font-size: 14px;
  padding: 8px 14px; border-radius: var(--r-md); border: 1px solid transparent;
  cursor: pointer; display: inline-flex; align-items: center; gap: 8px;
  transition: background var(--dur-base) var(--ease-out), border-color var(--dur-base);
  line-height: 1.2;
}
.btn:disabled { opacity: .6; cursor: not-allowed; }
.btn-secondary { background: var(--bg-canvas); color: var(--fg-1); border-color: var(--border-subtle); }
.btn-secondary:hover:not(:disabled) { background: var(--bg-sunken); }

/* ─── Section headings ─────────────────────────────────────────── */
.section-heading {
  display: flex; align-items: center; gap: 8px;
  font-size: 13px; font-weight: 700; color: var(--fg-2);
  text-transform: uppercase; letter-spacing: .05em;
  margin-bottom: 14px;
}
.section-heading i { color: var(--halo-500); font-size: 14px; }

/* ─── KPI grid ─────────────────────────────────────────────────── */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 14px;
}

/* ─── Module grid ──────────────────────────────────────────────── */
.modules-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 14px;
}

/* ─── Bottom grid ──────────────────────────────────────────────── */
.bottom-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

/* ─── AI panel icon ────────────────────────────────────────────── */
.ai-panel-icon-wrap {
  width: 24px; height: 24px; border-radius: var(--r-sm);
  background: linear-gradient(135deg, var(--halo-500), var(--halo-700));
  display: flex; align-items: center; justify-content: center;
}

/* ─── Insight skeleton ─────────────────────────────────────────── */
.insight-skeleton {
  height: 88px; border-radius: var(--r-lg);
  background: linear-gradient(90deg, var(--bg-sunken) 25%, var(--border-subtle) 50%, var(--bg-sunken) 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
}
@keyframes shimmer { to { background-position: -200% 0; } }

/* ─── Activity feed ────────────────────────────────────────────── */
.activity-row {
  display: flex; align-items: flex-start; gap: 12px;
  padding: 12px 18px; border-bottom: 1px solid var(--border-subtle);
}
.activity-row:last-child { border-bottom: 0; }
.activity-ic {
  width: 28px; height: 28px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}

/* ─── Empty state ──────────────────────────────────────────────── */
.empty-state {
  display: flex; flex-direction: column; align-items: center; gap: 8px;
  padding: 24px 18px; text-align: center;
  font-size: 13px; color: var(--fg-3);
}

/* ─── Responsive ───────────────────────────────────────────────── */
@media (max-width: 768px) {
  .bottom-grid { grid-template-columns: 1fr; }
  .kpi-grid    { grid-template-columns: 1fr 1fr; }
  .modules-grid { grid-template-columns: 1fr 1fr; }
}
</style>
