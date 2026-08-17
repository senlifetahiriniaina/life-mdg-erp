<template>
  <div class="app-shell" :class="{ 'sidebar-collapsed': sidebarCollapsed, dark: isDark }">
    <a href="#main-content" class="wh-skip-link">{{ $t('common.skip_to_content') }}</a>
    <div :class="['wh-sidebar-backdrop', mobileMenuOpen ? 'visible' : '']" @click="mobileMenuOpen = false" />
    <div v-if="isOffline" class="bg-yellow-600 text-white text-center py-1 text-sm">
      ⚠️ Mode hors-ligne — Les données peuvent ne pas être à jour
    </div>
    <aside id="wh-sidebar" :class="['wh-sidebar', mobileMenuOpen ? 'mobile-open' : '']" role="navigation" :aria-label="$t('nav.main_navigation')">
      <div class="wh-logo-row">
        <img v-if="!sidebarCollapsed" src="/logo.svg" alt="WideHalo" style="height: 26px" />
        <img v-else src="/logo-icon.svg" alt="W" style="height: 26px; width: 26px" />
      </div>
      <div v-if="!sidebarCollapsed" class="wh-ws-switch">
        <div class="wh-ws-avatar">{{ userInitials }}</div>
        <div class="wh-ws-meta">
          <div class="wh-ws-name">{{ user?.name || 'WideHalo ERP' }}</div>
          <div class="wh-ws-role">{{ user?.email || 'Admin' }}</div>
        </div>
        <i class="pi pi-sort-alt" style="font-size: 12px; color: var(--fg-3)" />
      </div>
      <div v-else style="margin: 8px auto 6px; display: flex; justify-content: center">
        <div class="wh-ws-avatar">{{ userInitials }}</div>
      </div>
      <div class="wh-nav-scroll">
        <template v-for="group in enabledNavGroups" :key="group.label">
          <div class="wh-nav-section">
            <div v-if="!sidebarCollapsed" class="wh-nav-section-label">{{ group.label }}</div>
            <template v-for="item in group.items" :key="item.key">
              <Link :href="item.href" :class="['wh-nav-item', isActive(item.href) ? 'wh-active' : '']" :title="sidebarCollapsed ? $t(`nav.${item.key}`) : undefined" :aria-current="isActive(item.href) ? 'page' : undefined" :aria-label="sidebarCollapsed ? $t(`nav.${item.key}`) : undefined" @click="mobileMenuOpen = false">
                <span class="wh-nav-ic"><i :class="item.icon" aria-hidden="true" /></span>
                <span v-if="!sidebarCollapsed" class="wh-nav-label">{{ $t(`nav.${item.key}`) }}</span>
                <span v-if="!sidebarCollapsed && item.badge" class="wh-nav-badge">{{ item.badge }}</span>
              </Link>
            </template>
          </div>
        </template>
      </div>
      <div class="wh-nav-foot">
        <button :class="['wh-nav-item', aiPanelOpen ? 'wh-active' : '']" :title="sidebarCollapsed ? $t('ai.assistant') : undefined" :aria-label="$t('ai.assistant')" :aria-pressed="aiPanelOpen" @click="toggleAI">
          <span class="wh-nav-ic"><i class="pi pi-sparkles" aria-hidden="true" /></span>
          <span v-if="!sidebarCollapsed" class="wh-nav-label">{{ $t('ai.assistant') }}</span>
          <span v-if="!sidebarCollapsed" class="wh-nav-badge">IA</span>
        </button>
        <button class="wh-nav-item" :title="sidebarCollapsed ? 'Expand' : 'Collapse'" :aria-label="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'" :aria-expanded="!sidebarCollapsed" @click="sidebarCollapsed = !sidebarCollapsed">
          <span class="wh-nav-ic"><i :class="sidebarCollapsed ? 'pi pi-angle-right' : 'pi pi-angle-left'" aria-hidden="true" /></span>
          <span v-if="!sidebarCollapsed" class="wh-nav-label" style="font-size: 13px; color: var(--fg-3)">Réduire</span>
        </button>
      </div>
    </aside>
    <header class="wh-topbar">
      <button class="wh-hamburger" @click="mobileMenuOpen = !mobileMenuOpen" :aria-label="$t('nav.toggle_sidebar')" :aria-expanded="mobileMenuOpen" aria-controls="wh-sidebar"><i class="pi pi-bars" style="font-size: 17px" /></button>
      <div class="wh-crumbs">
        <span>WideHalo</span>
        <span style="color: var(--fg-4)">/</span>
        <span class="wh-here">{{ currentModuleTitle }}</span>
      </div>
      <div class="wh-search" role="search">
        <i class="pi pi-search wh-search-ic" aria-hidden="true" />
        <input v-model="searchQuery" :placeholder="$t('common.search')" aria-label="Global search" @keyup.enter="performSearch" />
      </div>
      <div class="wh-tb-actions">
        <OfflineStatusPill />
        <div class="wh-pill wh-pill-lang" role="group" aria-label="Language selector">
          <button v-for="l in locales" :key="l.value" :class="currentLocale === l.value ? 'wh-lang-on' : ''" :aria-current="currentLocale === l.value ? 'true' : undefined" :aria-label="`Switch to ${l.label}`" @click="switchLocale(l.value)">{{ l.label }}</button>
        </div>
        <button class="wh-pill wh-pill-ai" @click="toggleAI" :aria-label="$t('ai.assistant')" :aria-pressed="aiPanelOpen" title="WideHalo IA · ⌘J"><i class="pi pi-sparkles" style="font-size: 12px" aria-hidden="true" /><span>IA</span></button>
        <QuickTicketButton />
        <ThemeSwitcher />
        <NotificationBell />
        <UserMenu />
      </div>
    </header>
    <main id="main-content" class="wh-main" tabindex="-1">
      <Transition name="fade-slide" mode="out-in">
        <slot />
      </Transition>
    </main>
    <AIAssistantPanel v-if="aiPanelOpen" :module="currentModule" @close="aiPanelOpen = false" />
    <Toast position="bottom-right" />
    <ConfirmDialog />
    <CookieConsentBanner />
    <SimplifiedMode />
    <ModuleAssistant :module="currentModule" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { usePage, Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { useOffline } from '@/composables/useOffline'
import { useTheme } from '@/composables/useTheme'
import { useWebVitals } from '@/composables/useWebVitals'
import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'
import OfflineStatusPill from '@/Components/UI/OfflineStatusPill.vue'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import QuickTicketButton from '@/Components/Helpdesk/QuickTicketButton.vue'
import UserMenu from '@/Components/UI/UserMenu.vue'
import NotificationBell from '@/Components/NotificationBell.vue'
import CookieConsentBanner from '@/Components/UI/CookieConsentBanner.vue'
import ModuleAssistant from '@/Components/AI/ModuleAssistant.vue'
import SimplifiedMode from '@/Components/AI/SimplifiedMode.vue'
import ThemeSwitcher from '@/Components/UI/ThemeSwitcher.vue'

const { t, locale } = useI18n()
const page = usePage()

const currentModule = computed(() => {
  const url = page.url
  if (url.includes('/crm')) return 'CRM'
  if (url.includes('/hr')) return 'HR'
  if (url.includes('/inventory')) return 'Inventory'
  if (url.includes('/accounting')) return 'Accounting'
  if (url.includes('/bi')) return 'BI'
  if (url.includes('/helpdesk')) return 'Helpdesk'
  if (url.includes('/projects')) return 'Projects'
  return 'WideHalo'
})
const { isDark } = useTheme()
const { isOffline } = useOffline()

const sidebarCollapsed = ref(false)
const mobileMenuOpen = ref(false)
const aiPanelOpen = ref(false)
const searchQuery = ref('')
const currentLocale = ref(locale.value)
const locales = [{ value: 'en', label: 'EN' }, { value: 'fr', label: 'FR' }, { value: 'pt', label: 'PT' }, { value: 'es', label: 'ES' }]

const user = computed(() => page.props.auth?.user)
const userInitials = computed(() => { const name = user.value?.name ?? ''; return name.split(' ').map((w: string) => w[0]).slice(0, 2).join('').toUpperCase() || 'WH' })

interface NavItem {
  key: string
  module: string
  href: string
  icon: string
  badge?: string
}

interface NavGroup {
  label: string
  adminOnly?: boolean
  items: NavItem[]
}

const allNavGroups: NavGroup[] = [
  { label: 'Pilotage', items: [
    { key: 'dashboard', module: 'Core', href: '/dashboard', icon: 'pi pi-th-large' },
    { key: 'bi', module: 'BI', href: '/bi', icon: 'pi pi-chart-bar' },
    { key: 'import', module: 'Core', href: '/import', icon: 'pi pi-upload' },
  ]},
  { label: 'Ventes & clients', items: [
    { key: 'crm', module: 'CRM', href: '/crm/contacts', icon: 'pi pi-users' },
    { key: 'accounting', module: 'Accounting', href: '/accounting/invoices', icon: 'pi pi-receipt' },
    { key: 'helpdesk', module: 'Helpdesk', href: '/helpdesk/tickets', icon: 'pi pi-headphones' },
  ]},
  { label: 'Opérations', items: [
    { key: 'inventory', module: 'Inventory', href: '/inventory/products', icon: 'pi pi-box' },
    { key: 'projects', module: 'Projects', href: '/projects', icon: 'pi pi-briefcase' },
    { key: 'validation', module: 'Validation', href: '/approval-requests', icon: 'pi pi-check-square' },
  ]},
  { label: 'Finance & RH', items: [
    { key: 'hr', module: 'HR', href: '/hr/employees', icon: 'pi pi-id-card' },
  ]},
]

// Trimmed to life-mdg-erp's 22 seeded roles (database/seeders/RolesAndPermissionsSeeder.php) —
// cashier/community-manager/production-manager/brand-owner/marketplace-admin/content-admin
// and modules like POS/WhatsApp/Email/Manufacturing/Ecommerce belong to Widehalo-ERP's wider
// scope and were never seeded or shipped here — their nav entries and orphaned Pages/ dirs
// were removed to match (they had no backing routes/controllers and 404'd on every fresh
// tenant, since tenant_modules is never seeded and the module-visibility filter below
// silently falls back to "show everything" when it's empty).
const ADMIN_ROLES = ['super-admin', 'admin', 'system-admin', 'security-admin', 'billing-admin', 'support-admin', 'tenant-admin']
const ROLE_MODULE_ACCESS: Record<string, string[]> = {
  'logistics-manager': ['Inventory', 'Logistics'],
  'service-partner': ['Helpdesk', 'Projects'],
  'purchasing-manager': ['Achats', 'Inventory', 'Accounting'],
  'warehouse-operator': ['Inventory', 'Logistics'],
  'sales-manager': ['CRM', 'Sales', 'Accounting', 'BI'],
  'project-manager': ['Projects', 'HR'],
  'finance-manager': ['Accounting', 'BI'],
  'customer-service': ['Helpdesk', 'CRM'],
  'inventory-analyst': ['Inventory', 'BI'],
  'payroll-officer': ['Payroll', 'HR'],
}

const userRoles = computed<string[]>(() => user.value?.roles ?? [])
const isAdmin = computed(() => userRoles.value.some((r: string) => ADMIN_ROLES.includes(r)))
const roleAllowedModules = computed<Set<string> | null>(() => {
  const roles = userRoles.value
  const unrestricted = ['super-admin', 'admin', 'manager', 'employee', 'accountant', 'hr-manager', 'sales-rep', ...ADMIN_ROLES]
  if (roles.some((r: string) => unrestricted.includes(r))) return null
  const allowed = new Set<string>(['Core'])
  for (const role of roles) { const modules = ROLE_MODULE_ACCESS[role] ?? []; modules.forEach((m: string) => allowed.add(m)) }
  return allowed
})

const adminNavGroup: NavGroup = { label: 'Administration', adminOnly: true, items: [
  { key: 'admin', module: 'Core', href: '/admin', icon: 'pi pi-shield' },
  { key: 'admin_servers', module: 'Core', href: '/admin/servers', icon: 'pi pi-server' },
  { key: 'admin_backups', module: 'Core', href: '/admin/backups', icon: 'pi pi-database' },
  { key: 'admin_users', module: 'Core', href: '/admin/users', icon: 'pi pi-users' },
  { key: 'admin_audit', module: 'AuditLog', href: '/audit/logs', icon: 'pi pi-list' },
  { key: 'admin_exchanges', module: 'Core', href: '/admin/exchanges', icon: 'pi pi-arrow-right-arrow-left' },
]}

const enabledNavGroups = computed<NavGroup[]>(() => {
  const enabledModules = page.props.enabledModules || []
  const nonCore = enabledModules.filter((m: string) => m !== 'Core')
  const allowed = roleAllowedModules.value
  const businessGroups = allNavGroups.map(group => ({ ...group, items: group.items.filter(item => {
    const moduleEnabled = item.module === 'Core' || nonCore.length === 0 || enabledModules.includes(item.module)
    const roleAllowed = allowed === null || allowed.has(item.module)
    return moduleEnabled && roleAllowed
  }) })).filter(group => group.items.length > 0)
  if (isAdmin.value) return [...businessGroups, adminNavGroup]
  return businessGroups
})

const currentModuleTitle = computed(() => {
  for (const group of allNavGroups) { for (const item of group.items) { if (isActive(item.href)) return t(`nav.${item.key}`) } }
  return t('nav.dashboard')
})

const isActive = (href: string) => href === '/dashboard' ? window.location.pathname === '/dashboard' : window.location.pathname.startsWith(href)
const toggleAI = () => { aiPanelOpen.value = !aiPanelOpen.value }
const performSearch = () => { /* TODO: global search */ }
const switchLocale = (value: string) => { currentLocale.value = value; locale.value = value; localStorage.setItem('locale', value) }

// Phase 4: Initialize Web Vitals monitoring on app load
onMounted(() => {
  useWebVitals()
})
</script>

<style scoped>
.fade-slide-enter-active, .fade-slide-leave-active { transition: all var(--transition-base, 250ms ease); }
.fade-slide-enter-from { opacity: 0; transform: translateY(8px); }
.fade-slide-leave-to   { opacity: 0; transform: translateY(-8px); }
</style>
