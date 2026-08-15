<template>
  <AppLayout>
    <Head title="Journal d'audit" />

    <!-- Page header -->
    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Journal d'audit</h1>
        <p class="wh-page-subtitle">{{ logs.total }} événement{{ logs.total !== 1 ? 's' : '' }} tracé{{ logs.total !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-secondary" @click="clearFilters">
          <i class="pi pi-filter-slash" style="font-size:13px" />
          Réinitialiser
        </button>
      </div>
    </div>

    <!-- Stats row -->
    <div class="stats-row">
      <div class="stat-card">
        <div class="stat-label">Aujourd'hui</div>
        <div class="stat-value">{{ stats.today }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Sélection actuelle</div>
        <div class="stat-value">{{ logs.total }}</div>
      </div>
      <div v-for="(count, mod) in topModules" :key="mod" class="stat-card">
        <div class="stat-label">{{ mod }}</div>
        <div class="stat-value">{{ count }}</div>
      </div>
    </div>

    <!-- Filters -->
    <div class="wh-panel filter-panel">
      <div class="filter-row">
        <!-- Search -->
        <div class="search-wrap">
          <i class="pi pi-search search-icon" />
          <input
            v-model="filters.search"
            placeholder="Rechercher (description, utilisateur…)"
            class="wh-filter-input"
            @input="onSearchInput"
          />
        </div>

        <!-- Module -->
        <select v-model="filters.module" class="wh-select" @change="applyFilters()">
          <option value="">Tous les modules</option>
          <option v-for="mod in modules" :key="mod" :value="mod">{{ mod }}</option>
        </select>

        <!-- Event type -->
        <select v-model="filters.event_type" class="wh-select" @change="applyFilters()">
          <option value="">Tous les types</option>
          <option v-for="et in eventTypes" :key="et" :value="et">{{ eventTypeLabel(et) }}</option>
        </select>

        <!-- Date from -->
        <input v-model="filters.date_from" type="date" class="wh-select" aria-label="Filtrer à partir du" @change="applyFilters()" />

        <!-- Date to -->
        <input v-model="filters.date_to" type="date" class="wh-select" aria-label="Filtrer jusqu'au" @change="applyFilters()" />
      </div>
    </div>

    <!-- Log table -->
    <div class="wh-panel" style="position:relative">
      <table class="wh-dt">
        <thead>
          <tr>
            <th style="width:160px">Date / Heure</th>
            <th style="width:140px">Utilisateur</th>
            <th style="width:90px">Module</th>
            <th style="width:120px">Type d'événement</th>
            <th>Description / Impact</th>
            <th style="width:50px"></th>
          </tr>
        </thead>
        <tbody>
          <template v-for="log in logs.data" :key="log.id">
            <tr class="wh-dt-row" @click="toggleExpand(log.id)">
              <td>
                <span style="font-variant-numeric:tabular-nums;font-size:12px;color:var(--fg-2)">
                  {{ formatDate(log.created_at) }}
                </span>
              </td>
              <td>
                <div style="display:flex;align-items:center;gap:8px">
                  <div class="user-avatar">{{ initials(log.user_name) }}</div>
                  <div>
                    <div style="font-size:13px;font-weight:500;color:var(--fg-1)">{{ log.user_name ?? '—' }}</div>
                    <div v-if="log.user_role" style="font-size:11px;color:var(--fg-3)">{{ log.user_role }}</div>
                  </div>
                </div>
              </td>
              <td>
                <span v-if="log.module" :class="['mod-badge', moduleBadgeClass(log.module)]">
                  {{ log.module }}
                </span>
                <span v-else class="wh-null">—</span>
              </td>
              <td>
                <span :class="['event-badge', eventBadgeClass(log.event_type)]">
                  <i :class="['pi', eventIcon(log.event_type)]" style="font-size:11px" />
                  {{ eventTypeLabel(log.event_type) }}
                </span>
              </td>
              <td style="color:var(--fg-1);font-size:13px">
                {{ log.description ?? log.action }}
                <span v-if="log.subject_type" class="subject-type">
                  {{ shortClass(log.subject_type) }}
                  <span v-if="log.subject_id">#{{ log.subject_id }}</span>
                </span>
              </td>
              <td>
                <button
                  v-if="hasDiff(log)"
                  class="expand-btn"
                  :title="expanded.has(log.id) ? 'Réduire' : 'Voir les changements'"
                  @click.stop="toggleExpand(log.id)"
                >
                  <i :class="['pi', expanded.has(log.id) ? 'pi-chevron-up' : 'pi-chevron-down']" style="font-size:11px" />
                </button>
              </td>
            </tr>

            <!-- Diff expansion row -->
            <tr v-if="expanded.has(log.id) && hasDiff(log)" :key="`diff-${log.id}`" class="diff-row">
              <td colspan="6">
                <div class="diff-container">
                  <div class="diff-grid">
                    <!-- Deleted fields (old only) -->
                    <template v-for="(val, field) in deletedFields(log)" :key="`del-${field}`">
                      <div class="diff-field">{{ field }}</div>
                      <div class="diff-old">{{ formatValue(val) }}</div>
                      <div class="diff-new diff-removed">—</div>
                    </template>

                    <!-- Changed fields -->
                    <template v-for="(newVal, field) in changedFields(log)" :key="`chg-${field}`">
                      <div class="diff-field">{{ field }}</div>
                      <div class="diff-old">{{ formatValue(log.old_values?.[field]) }}</div>
                      <div class="diff-new diff-changed">{{ formatValue(newVal) }}</div>
                    </template>

                    <!-- Added fields (new only) -->
                    <template v-for="(val, field) in addedFields(log)" :key="`add-${field}`">
                      <div class="diff-field">{{ field }}</div>
                      <div class="diff-old">—</div>
                      <div class="diff-new diff-added">{{ formatValue(val) }}</div>
                    </template>
                  </div>

                  <div v-if="log.ip_address" class="diff-meta">
                    <i class="pi pi-map-marker" style="font-size:11px" />
                    {{ log.ip_address }}
                    <span v-if="log.user_agent" class="diff-ua">· {{ truncate(log.user_agent, 60) }}</span>
                  </div>
                </div>
              </td>
            </tr>

            <!-- Meta row (IP only, no diff) -->
            <tr v-else-if="expanded.has(log.id)" :key="`meta-${log.id}`" class="diff-row">
              <td colspan="6">
                <div class="diff-container">
                  <div class="diff-meta">
                    <i class="pi pi-map-marker" style="font-size:11px" />
                    {{ log.ip_address ?? 'IP inconnue' }}
                    <span v-if="log.user_agent" class="diff-ua">· {{ truncate(log.user_agent, 80) }}</span>
                  </div>
                </div>
              </td>
            </tr>
          </template>

          <tr v-if="logs.data.length === 0">
            <td colspan="6" style="text-align:center;padding:64px;color:var(--fg-3)">
              <i class="pi pi-list" style="font-size:32px;opacity:0.3;display:block;margin-bottom:12px" />
              Aucun événement trouvé.
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="logs.total > logs.per_page" style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-top:1px solid var(--border-subtle)">
        <span style="font-size:13px;color:var(--fg-3)">
          {{ (logs.current_page - 1) * logs.per_page + 1 }}–{{ Math.min(logs.current_page * logs.per_page, logs.total) }}
          sur {{ logs.total }}
        </span>
        <Paginator
          :rows="logs.per_page"
          :total-records="logs.total"
          :first="(logs.current_page - 1) * logs.per_page"
          @page="onPageChange"
        />
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import Paginator from 'primevue/paginator'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps<{
  logs: {
    data: any[]
    total: number
    per_page: number
    current_page: number
    last_page: number
  }
  stats: {
    today: number
    total: number
    by_module: Record<string, number>
    by_event_type: Record<string, number>
  }
  modules: string[]
  eventTypes: string[]
  filters: Record<string, string>
}>()

const expanded = ref<Set<number>>(new Set())
let searchTimer: ReturnType<typeof setTimeout> | null = null

const filters = reactive({
  search:     props.filters?.search     ?? '',
  module:     props.filters?.module     ?? '',
  event_type: props.filters?.event_type ?? '',
  user_id:    props.filters?.user_id    ?? '',
  date_from:  props.filters?.date_from  ?? '',
  date_to:    props.filters?.date_to    ?? '',
})

const topModules = computed(() => {
  const entries = Object.entries(props.stats.by_module)
    .sort((a, b) => b[1] - a[1])
    .slice(0, 3)

  return Object.fromEntries(entries)
})

function buildQuery(page?: number) {
  const params: Record<string, string | number> = {}
  if (page && page > 1) params.page = page
  if (filters.search)     params.search     = filters.search
  if (filters.module)     params.module     = filters.module
  if (filters.event_type) params.event_type = filters.event_type
  if (filters.user_id)    params.user_id    = filters.user_id
  if (filters.date_from)  params.date_from  = filters.date_from
  if (filters.date_to)    params.date_to    = filters.date_to

  return params
}

function applyFilters(page?: number) {
  expanded.value.clear()
  router.visit('/audit/logs', {
    data: buildQuery(typeof page === 'number' ? page : 1),
    preserveState: true,
    preserveScroll: true,
  })
}

function onSearchInput() {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => applyFilters(1), 400)
}

function clearFilters() {
  filters.search = ''
  filters.module = ''
  filters.event_type = ''
  filters.user_id = ''
  filters.date_from = ''
  filters.date_to = ''
  applyFilters(1)
}

function onPageChange(e: any) {
  applyFilters(e.page + 1)
}

function toggleExpand(id: number) {
  if (expanded.value.has(id)) {
    expanded.value.delete(id)
  } else {
    expanded.value.add(id)
  }
}

function hasDiff(log: any) {
  return log.old_values !== null || log.new_values !== null || log.ip_address !== null
}

function changedFields(log: any): Record<string, any> {
  if (!log.new_values) return {}
  const result: Record<string, any> = {}

  for (const [k, v] of Object.entries(log.new_values)) {
    const old = log.old_values?.[k]
    if (old !== undefined && old !== v) result[k] = v
  }

  return result
}

function addedFields(log: any): Record<string, any> {
  if (!log.new_values || log.old_values) return {}
  return log.new_values
}

function deletedFields(log: any): Record<string, any> {
  if (!log.old_values || log.new_values) return {}
  return log.old_values
}

function formatDate(d: string) {
  return new Date(d).toLocaleString('fr-FR', {
    day: '2-digit', month: '2-digit', year: '2-digit',
    hour: '2-digit', minute: '2-digit', second: '2-digit',
  })
}

function initials(name?: string) {
  if (!name) return '?'

  return name.split(' ').map((w: string) => w[0]).slice(0, 2).join('').toUpperCase()
}

function shortClass(fullClass: string) {
  return fullClass.split('\\').pop() ?? fullClass
}

function truncate(str: string, len: number) {
  return str.length > len ? str.slice(0, len) + '…' : str
}

function formatValue(v: any): string {
  if (v === null || v === undefined) return '—'
  if (typeof v === 'object') return JSON.stringify(v)

  return String(v)
}

function eventTypeLabel(type: string) {
  const labels: Record<string, string> = {
    login: 'Connexion',
    logout: 'Déconnexion',
    login_failed: 'Échec connexion',
    model_created: 'Création',
    model_updated: 'Modification',
    model_deleted: 'Suppression',
    export: 'Export',
    bulk_delete: 'Suppression en masse',
    api_access: 'Accès API',
  }

  return labels[type] ?? type
}

function eventIcon(type: string) {
  const icons: Record<string, string> = {
    login: 'pi-sign-in',
    logout: 'pi-sign-out',
    login_failed: 'pi-lock',
    model_created: 'pi-plus-circle',
    model_updated: 'pi-pencil',
    model_deleted: 'pi-trash',
    export: 'pi-file-export',
    bulk_delete: 'pi-eraser',
  }

  return icons[type] ?? 'pi-circle'
}

function eventBadgeClass(type: string) {
  if (type?.includes('created'))     return 'event-green'
  if (type?.includes('updated'))     return 'event-blue'
  if (type?.includes('deleted'))     return 'event-red'
  if (type === 'login')              return 'event-teal'
  if (type === 'logout')             return 'event-slate'
  if (type === 'login_failed')       return 'event-orange'
  if (type === 'export')             return 'event-violet'

  return 'event-slate'
}

const moduleColors = [
  'mod-blue', 'mod-green', 'mod-purple', 'mod-orange',
  'mod-teal', 'mod-pink', 'mod-yellow', 'mod-red',
]
const moduleColorMap: Record<string, string> = {}
let colorIdx = 0

function moduleBadgeClass(mod: string) {
  if (!moduleColorMap[mod]) {
    moduleColorMap[mod] = moduleColors[colorIdx % moduleColors.length]
    colorIdx++
  }

  return moduleColorMap[mod]
}
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; gap:16px; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; gap:8px; }
.btn { font-family:var(--font-sans); font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:background var(--dur-base); line-height:1.2; }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover { background:var(--bg-sunken); }

/* Stats */
.stats-row { display:flex; gap:12px; margin-bottom:16px; flex-wrap:wrap; }
.stat-card { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-md); padding:12px 18px; min-width:110px; }
.stat-label { font-size:11px; text-transform:uppercase; letter-spacing:0.06em; color:var(--fg-3); font-weight:600; margin-bottom:4px; }
.stat-value { font-size:22px; font-weight:700; color:var(--fg-1); font-variant-numeric:tabular-nums; }

/* Filters */
.filter-panel { margin-bottom:16px; padding:12px 16px; }
.filter-row { display:flex; flex-wrap:wrap; gap:10px; align-items:center; }
.search-wrap { position:relative; flex:1; min-width:220px; max-width:340px; }
.search-icon { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--fg-4); font-size:13px; pointer-events:none; }
.wh-filter-input { width:100%; padding:7px 12px 7px 32px; border-radius:var(--r-md); border:1px solid var(--border-subtle); background:var(--bg-sunken); font-family:var(--font-sans); font-size:13px; color:var(--fg-1); outline:none; transition:border-color var(--dur-base), box-shadow var(--dur-base); }
.wh-filter-input:focus { border-color:var(--halo-500); background:var(--bg-canvas); box-shadow:0 0 0 3px rgba(46,91,232,0.12); }
.wh-filter-input::placeholder { color:var(--fg-4); }
.wh-select { padding:7px 10px; border-radius:var(--r-md); border:1px solid var(--border-subtle); background:var(--bg-sunken); font-family:var(--font-sans); font-size:13px; color:var(--fg-1); outline:none; cursor:pointer; }
.wh-select:focus { border-color:var(--halo-500); }

/* Table */
.wh-dt { width:100%; border-collapse:collapse; font-size:13px; }
.wh-dt thead th { text-align:left; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:var(--fg-3); font-weight:600; padding:10px 14px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sunken); }
.wh-dt-row td { padding:10px 14px; border-bottom:1px solid var(--border-subtle); }
.wh-dt-row:last-child td { border-bottom:0; }
.wh-dt-row { cursor:pointer; transition:background var(--dur-fast); }
.wh-dt-row:hover { background:var(--bg-sunken); }

/* User avatar */
.user-avatar { width:26px; height:26px; border-radius:var(--r-sm); background:var(--halo-50); color:var(--halo-700); font-size:10px; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; }

/* Module badge */
.mod-badge { display:inline-block; padding:2px 7px; border-radius:var(--r-pill); font-size:11px; font-weight:600; }
.mod-blue   { background:#eff6ff; color:#1e40af; }
.mod-green  { background:#f0fdf4; color:#166534; }
.mod-purple { background:#faf5ff; color:#6b21a8; }
.mod-orange { background:#fff7ed; color:#9a3412; }
.mod-teal   { background:#f0fdfa; color:#134e4a; }
.mod-pink   { background:#fdf2f8; color:#9d174d; }
.mod-yellow { background:#fefce8; color:#854d0e; }
.mod-red    { background:#fef2f2; color:#991b1b; }

/* Event badge */
.event-badge { display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:var(--r-pill); font-size:11px; font-weight:500; white-space:nowrap; }
.event-green  { background:var(--success-bg); color:var(--success-fg); }
.event-blue   { background:var(--halo-50); color:var(--halo-700); }
.event-red    { background:var(--danger-bg); color:var(--danger-fg); }
.event-teal   { background:#f0fdfa; color:#134e4a; }
.event-slate  { background:var(--bg-sunken); color:var(--fg-2); }
.event-orange { background:#fff7ed; color:#9a3412; }
.event-violet { background:#faf5ff; color:#6b21a8; }

/* Subject type chip */
.subject-type { display:inline-block; margin-left:6px; padding:1px 6px; border-radius:var(--r-sm); background:var(--bg-sunken); color:var(--fg-3); font-size:11px; }

/* Expand button */
.expand-btn { width:24px; height:24px; border-radius:var(--r-sm); border:1px solid var(--border-subtle); background:var(--bg-canvas); color:var(--fg-3); cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:all var(--dur-fast); }
.expand-btn:hover { background:var(--bg-sunken); color:var(--fg-1); }

/* Diff row */
.diff-row { background:var(--bg-sunken); }
.diff-container { padding:10px 14px; }
.diff-grid { display:grid; grid-template-columns:180px 1fr 1fr; gap:2px 8px; font-size:12px; font-family:var(--font-mono); }
.diff-field { color:var(--fg-3); padding:3px 0; font-family:var(--font-sans); font-size:11px; font-weight:600; }
.diff-old { color:var(--fg-3); padding:3px 6px; background:var(--bg-canvas); border-radius:var(--r-sm); word-break:break-all; }
.diff-new { padding:3px 6px; border-radius:var(--r-sm); word-break:break-all; }
.diff-added   { background:#dcfce7; color:#166534; }
.diff-changed { background:#dbeafe; color:#1e40af; }
.diff-removed { background:#fee2e2; color:#991b1b; }
.diff-meta { margin-top:8px; font-size:11px; color:var(--fg-3); display:flex; align-items:center; gap:6px; }
.diff-ua { color:var(--fg-4); }

.wh-null { color:var(--fg-4); }
</style>
