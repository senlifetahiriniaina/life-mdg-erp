<template>
  <AppLayout>
    <Head title="BI · Éditeur SQL" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">BI · Éditeur SQL</h1>
        <p class="wh-page-subtitle">Exécutez des requêtes sur vos sources de données</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-secondary" @click="saveQuery" :disabled="!sqlCode.trim() || !queryName.trim()">
          <i class="pi pi-save" style="font-size:13px" /> Sauvegarder
        </button>
        <button class="btn btn-primary" @click="runQuery" :disabled="!sqlCode.trim() || running">
          <i :class="running ? 'pi pi-spin pi-spinner' : 'pi pi-play'" style="font-size:13px" />
          {{ running ? 'Exécution…' : 'Exécuter' }}
        </button>
      </div>
    </div>

    <div class="sql-shell">
      <!-- Left: saved queries -->
      <div class="sql-sidebar">
        <div class="sql-sidebar-head">
          <span style="font-size:12px;font-weight:600;color:var(--fg-2);text-transform:uppercase;letter-spacing:0.06em">Requêtes sauvegardées</span>
        </div>
        <div class="sql-query-list">
          <div v-if="!savedQueries.length" style="padding:20px 14px;text-align:center;color:var(--fg-4);font-size:12px">
            Aucune requête sauvegardée
          </div>
          <button
            v-for="q in savedQueries"
            :key="q.id"
            class="sql-query-item"
            :class="{ 'sql-query-active': activeQuery?.id === q.id }"
            @click="loadQuery(q)"
          >
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:2px">
              <i class="pi pi-code" style="font-size:11px;color:var(--fg-4)" />
              <span style="font-size:13px;font-weight:500;color:var(--fg-1);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ q.name }}</span>
              <span v-if="q.is_public" style="font-size:10px;background:var(--halo-50);color:var(--halo-600);padding:1px 5px;border-radius:var(--r-pill);font-weight:600">PUBLIC</span>
            </div>
            <p v-if="q.description" style="font-size:11px;color:var(--fg-3);margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ q.description }}</p>
            <p style="font-size:11px;color:var(--fg-4);margin:3px 0 0">{{ formatRelative(q.last_run_at ?? q.updated_at) }}</p>
          </button>
        </div>
      </div>

      <!-- Right: editor + results -->
      <div class="sql-main">
        <!-- Query meta bar -->
        <div class="sql-meta-bar">
          <InputText v-model="queryName" placeholder="Nom de la requête…" class="sql-name-input" />
          <Select
            v-model="selectedDatasource"
            :options="datasourceOptions"
            option-label="label"
            option-value="value"
            placeholder="Source de données"
            style="width:200px;font-size:13px"
          />
        </div>

        <!-- Code editor area -->
        <div class="sql-editor-wrap">
          <textarea
            v-model="sqlCode"
            class="sql-textarea"
            placeholder="SELECT * FROM crm_contacts WHERE status = 'active' LIMIT 100;"
            spellcheck="false"
            @keydown.ctrl.enter.prevent="runQuery"
            @keydown.meta.enter.prevent="runQuery"
          />
          <div class="sql-editor-hint">Ctrl+Enter pour exécuter</div>
        </div>

        <!-- Results -->
        <div class="sql-results">
          <div v-if="!hasRun" class="sql-results-placeholder">
            <i class="pi pi-table" style="font-size:32px;color:var(--fg-4);margin-bottom:8px" />
            <p style="font-size:14px;font-weight:500;color:var(--fg-2);margin:0 0 4px">Pas encore de résultats</p>
            <p style="font-size:13px;color:var(--fg-3);margin:0">Écrivez une requête SQL et cliquez sur Exécuter</p>
          </div>

          <div v-else-if="error" style="padding:20px;display:flex;align-items:flex-start;gap:10px">
            <i class="pi pi-times-circle" style="font-size:18px;color:var(--danger-fg);flex-shrink:0;margin-top:1px" />
            <div>
              <p style="font-size:14px;font-weight:600;color:var(--danger-fg);margin:0 0 4px">Erreur SQL</p>
              <pre style="font-size:12px;font-family:var(--font-mono);color:var(--fg-2);margin:0;white-space:pre-wrap">{{ error }}</pre>
            </div>
          </div>

          <template v-else-if="results.length">
            <div class="sql-results-bar">
              <span style="font-size:13px;color:var(--fg-2);font-weight:500">{{ results.length }} ligne{{ results.length !== 1 ? 's' : '' }}</span>
              <span style="font-size:12px;color:var(--fg-4)">{{ executionTime }}ms</span>
              <button class="btn btn-secondary" style="font-size:12px;padding:4px 10px;margin-left:auto" @click="exportCsv">
                <i class="pi pi-download" style="font-size:12px" /> CSV
              </button>
            </div>
            <div style="overflow:auto;flex:1">
              <table class="wh-dt">
                <thead>
                  <tr>
                    <th v-for="col in resultColumns" :key="col">{{ col }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, i) in results" :key="i" class="wh-dt-row">
                    <td v-for="col in resultColumns" :key="col" style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                      {{ row[col] ?? '—' }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>

          <div v-else style="padding:30px;text-align:center;color:var(--fg-3);font-size:13px">
            Requête exécutée avec succès — 0 ligne retournée.
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { InputText, Select } from 'primevue'
import AppLayout from '@/Layouts/AppLayout.vue'

interface SavedQuery {
  id: number
  name: string
  description?: string
  datasource?: string
  is_public: boolean
  last_run_at?: string
  updated_at?: string
  sql_query?: string
}

const props = defineProps({
  savedQueries: { type: Array as () => SavedQuery[], required: true },
})

const sqlCode           = ref('')
const queryName         = ref('')
const selectedDatasource = ref<string | null>(null)
const activeQuery       = ref<SavedQuery | null>(null)
const running           = ref(false)
const hasRun            = ref(false)
const error             = ref<string | null>(null)
const results           = ref<Record<string, any>[]>([])
const executionTime     = ref(0)

const datasourceOptions = [
  { label: 'Base principale (MySQL)', value: 'mysql' },
  { label: 'Base analytics', value: 'analytics' },
]

const resultColumns = computed(() =>
  results.value.length ? Object.keys(results.value[0]) : []
)

const loadQuery = (q: SavedQuery) => {
  activeQuery.value      = q
  queryName.value        = q.name
  selectedDatasource.value = q.datasource ?? null
  // Chantier 19 Lot 5: was `q.sql` — the real column (and the real
  // BiQuery::$fillable field the backend validates on save) is `sql_query`,
  // confirmed empirically that this always loaded an empty editor for every
  // saved query regardless of its real stored SQL.
  sqlCode.value          = q.sql_query ?? ''
}

const runQuery = async () => {
  if (!sqlCode.value.trim() || running.value) return
  running.value = true
  error.value   = null
  results.value = []
  hasRun.value  = true
  const t0 = Date.now()
  try {
    // Chantier 10: was '/api/v1/bi/queries/run' — a flat URL that has never
    // existed as a route (the real endpoints are POST bi/queries/{query}/run,
    // which runs an already-saved query by id, and POST bi/queries/run-raw,
    // which runs arbitrary ad-hoc SQL text — the shape this editor actually
    // sends). Every "Exécuter" click 404'd. Repointed to the real run-raw
    // endpoint (admin/manager only, matching this page's own route gate).
    const res = await fetch('/api/v1/bi/queries/run-raw', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ sql: sqlCode.value, datasource: selectedDatasource.value }),
    })
    const data = await res.json()
    executionTime.value = Date.now() - t0
    if (!res.ok) {
      error.value = data.message ?? 'Erreur inconnue'
    } else {
      // Chantier 19 Lot 5: was `Array.isArray(data) ? data : (data.data ?? [])`
      // — but QueryController::runRaw()'s real response shape is
      // `{ columns: string[], rows: any[][], duration_ms: number }`, neither
      // a bare array nor `{ data: [...] }` — every real query silently
      // rendered "0 ligne retournée" no matter what the query actually
      // returned. Reshape rows (arrays of values) into the row objects
      // (keyed by column name) this page's table/CSV export already expect.
      const columns: string[] = data.columns ?? []
      const rows: any[][] = data.rows ?? []
      results.value = rows.map((row) =>
        Object.fromEntries(columns.map((col, i) => [col, row[i]]))
      )
    }
  } catch (e: any) {
    error.value = e.message
  } finally {
    running.value = false
  }
}

const saveQuery = async () => {
  // Chantier 19 Lot 5: was `{ name, sql: sqlCode.value, datasource }` — but
  // QueryController::store() validates `sql_query` as `required|string`
  // (BiQuery::$fillable has no `sql` column at all) — every "Sauvegarder"
  // click 422'd silently (the response was never checked either), so this
  // button has never actually saved a query since the page was built.
  const res = await fetch('/api/v1/bi/queries', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ name: queryName.value, sql_query: sqlCode.value, datasource: selectedDatasource.value }),
  })
  if (!res.ok) {
    const data = await res.json().catch(() => ({}))
    error.value = data.message ?? 'Échec de la sauvegarde de la requête'
    hasRun.value = true
    return
  }
  router.reload({ only: ['savedQueries'] })
}

const exportCsv = () => {
  if (!results.value.length) return
  const cols = resultColumns.value
  const rows = [cols.join(','), ...results.value.map(r => cols.map(c => JSON.stringify(r[c] ?? '')).join(','))]
  const blob = new Blob([rows.join('\n')], { type: 'text/csv' })
  const url  = URL.createObjectURL(blob)
  const a    = Object.assign(document.createElement('a'), { href: url, download: `${queryName.value || 'export'}.csv` })
  a.click()
  URL.revokeObjectURL(url)
}

const formatRelative = (v?: string) => {
  if (!v) return ''
  const d = new Date(v), diffMins = Math.floor((Date.now() - d.getTime()) / 60000)
  if (diffMins < 1) return 'à l\'instant'
  if (diffMins < 60) return `il y a ${diffMins}m`
  const h = Math.floor(diffMins / 60)
  if (h < 24) return `il y a ${h}h`
  return d.toLocaleDateString('fr-FR', { month: 'short', day: 'numeric' })
}
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; gap:16px; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; gap:8px; }
.btn { font-family:var(--font-sans); font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:background var(--dur-base); line-height:1.2; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover:not(:disabled) { background:var(--halo-700); }
.btn-primary:disabled { opacity:0.5; cursor:not-allowed; }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover:not(:disabled) { background:var(--bg-sunken); }
.btn-secondary:disabled { opacity:0.5; cursor:not-allowed; }
.sql-shell { display:flex; height:calc(100vh - 170px); border:1px solid var(--border-subtle); border-radius:var(--r-lg); overflow:hidden; background:var(--bg-canvas); }
.sql-sidebar { width:240px; flex-shrink:0; border-right:1px solid var(--border-subtle); display:flex; flex-direction:column; }
.sql-sidebar-head { padding:12px 14px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sunken); }
.sql-query-list { flex:1; overflow-y:auto; }
.sql-query-item { width:100%; text-align:left; display:flex; flex-direction:column; padding:10px 14px; border-bottom:1px solid var(--border-subtle); background:none; border-left:none; border-right:none; border-top:none; cursor:pointer; transition:background var(--dur-fast); }
.sql-query-item:hover { background:var(--bg-sunken); }
.sql-query-active { background:var(--halo-50); }
.sql-main { flex:1; display:flex; flex-direction:column; min-width:0; }
.sql-meta-bar { display:flex; gap:10px; padding:10px 14px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sunken); }
.sql-name-input { flex:1; font-size:13px; }
.sql-editor-wrap { position:relative; }
.sql-textarea { width:100%; height:200px; border:none; border-bottom:1px solid var(--border-subtle); resize:vertical; font-family:var(--font-mono,'ui-monospace',monospace); font-size:13px; line-height:1.6; color:var(--fg-1); background:var(--bg-canvas); padding:14px 16px; outline:none; box-sizing:border-box; }
.sql-textarea::placeholder { color:var(--fg-4); }
.sql-editor-hint { position:absolute; bottom:8px; right:12px; font-size:11px; color:var(--fg-4); pointer-events:none; }
.sql-results { flex:1; display:flex; flex-direction:column; overflow:hidden; }
.sql-results-placeholder { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:40px; }
.sql-results-bar { display:flex; align-items:center; gap:10px; padding:8px 14px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sunken); font-size:12px; }
.wh-dt { width:100%; border-collapse:collapse; font-size:13px; }
.wh-dt thead th { text-align:left; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:var(--fg-3); font-weight:600; padding:8px 14px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sunken); position:sticky; top:0; }
.wh-dt-row td { padding:9px 14px; border-bottom:1px solid var(--border-subtle); color:var(--fg-1); }
.wh-dt-row:last-child td { border-bottom:0; }
.wh-dt-row:hover { background:var(--bg-sunken); }
</style>
