<template>
  <AppLayout>
    <Head title="BI · Sources de données" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">BI · Sources de données</h1>
        <p class="wh-page-subtitle">{{ dataSources.length }} source{{ dataSources.length !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="showCreate = true">
          <i class="pi pi-plus" style="font-size:13px" /> Connecter une source
        </button>
      </div>
    </div>

    <!-- Sources grid -->
    <div v-if="dataSources.length" class="sources-grid">
      <div v-for="src in dataSources" :key="src.id" class="source-card">
        <div class="source-card-head">
          <div class="source-icon">
            <i :class="sourceIcon(src.type)" />
          </div>
          <div style="flex:1;min-width:0">
            <p style="font-size:14px;font-weight:600;color:var(--fg-1);margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ src.name }}</p>
            <p style="font-size:12px;color:var(--fg-3);margin:2px 0 0;text-transform:capitalize">{{ src.type }}</p>
          </div>
          <span :class="['wh-badge', statusBadge(src.status)]">
            <span class="wh-badge-dot" />
            {{ statusLabel(src.status) }}
          </span>
        </div>
        <div class="source-card-body">
          <div class="source-meta-row">
            <span class="source-meta-label">Hôte</span>
            <span class="source-meta-value">{{ src.host ?? src.connection_string ?? '—' }}</span>
          </div>
          <div class="source-meta-row">
            <span class="source-meta-label">Base</span>
            <span class="source-meta-value">{{ src.database_name ?? '—' }}</span>
          </div>
          <div class="source-meta-row">
            <span class="source-meta-label">Créée le</span>
            <span class="source-meta-value">{{ formatDate(src.created_at) }}</span>
          </div>
        </div>
        <div class="source-card-foot">
          <button class="btn btn-secondary" style="font-size:12px;padding:5px 10px" @click="testConnection(src)">
            <i class="pi pi-wifi" style="font-size:12px" /> Tester
          </button>
          <button class="btn btn-secondary" style="font-size:12px;padding:5px 10px">
            <i class="pi pi-pencil" style="font-size:12px" /> Modifier
          </button>
          <button class="btn btn-danger" style="font-size:12px;padding:5px 10px">
            <i class="pi pi-trash" style="font-size:12px" />
          </button>
        </div>
      </div>
    </div>

    <div v-else class="wh-empty-state">
      <i class="pi pi-database" style="font-size:40px;color:var(--fg-4);margin-bottom:12px" />
      <p style="font-size:16px;font-weight:500;color:var(--fg-2);margin:0 0 4px">Aucune source connectée</p>
      <p style="font-size:13px;color:var(--fg-3);margin:0 0 20px">Connectez une base de données, une API ou un fichier CSV pour créer vos rapports.</p>
      <button class="btn btn-primary" @click="showCreate = true">
        <i class="pi pi-plus" style="font-size:13px" /> Connecter une source
      </button>
    </div>

    <!-- Create dialog -->
    <Dialog v-model:visible="showCreate" header="Connecter une source de données" modal style="width:520px">
      <div style="display:flex;flex-direction:column;gap:14px;padding:8px 0">
        <div>
          <label class="wh-label">Nom de la source</label>
          <InputText v-model="form.name" class="w-full" placeholder="ex. Production DB" />
        </div>
        <div>
          <label class="wh-label">Type</label>
          <Select
            v-model="form.type"
            :options="typeOptions"
            option-label="label"
            option-value="value"
            class="w-full"
            placeholder="Sélectionner un type"
          />
        </div>
        <template v-if="form.type === 'mysql' || form.type === 'postgresql' || form.type === 'mssql'">
          <div style="display:grid;grid-template-columns:1fr auto;gap:10px">
            <div>
              <label class="wh-label">Hôte</label>
              <InputText v-model="form.host" class="w-full" placeholder="localhost" />
            </div>
            <div style="width:90px">
              <label class="wh-label">Port</label>
              <InputNumber v-model="form.port" class="w-full" :use-grouping="false" placeholder="3306" />
            </div>
          </div>
          <div>
            <label class="wh-label">Base de données</label>
            <InputText v-model="form.database_name" class="w-full" placeholder="my_database" />
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <div>
              <label class="wh-label">Utilisateur</label>
              <InputText v-model="form.username" class="w-full" placeholder="root" />
            </div>
            <div>
              <label class="wh-label">Mot de passe</label>
              <Password v-model="form.password" class="w-full" :feedback="false" placeholder="••••••••" />
            </div>
          </div>
        </template>
        <template v-else-if="form.type === 'csv'">
          <div>
            <label class="wh-label">URL du fichier CSV</label>
            <InputText v-model="form.connection_string" class="w-full" placeholder="https://..." />
          </div>
        </template>
      </div>
      <template #footer>
        <div style="display:flex;justify-content:flex-end;gap:8px">
          <button class="btn btn-secondary" @click="showCreate = false">Annuler</button>
          <button class="btn btn-primary" @click="createSource" :disabled="!form.name || !form.type">
            <i class="pi pi-link" style="font-size:13px" /> Connecter
          </button>
        </div>
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { Head } from '@inertiajs/vue3'
import { Dialog, InputText, InputNumber, Select, Password } from 'primevue'
import AppLayout from '@/Layouts/AppLayout.vue'

interface DataSource {
  id: number
  name: string
  type: string
  status: string
  host?: string | null
  connection_string?: string | null
  database_name?: string | null
  created_at: string
}

defineProps<{
  dataSources: DataSource[]
}>()

const showCreate = ref(false)
const form = reactive({
  name: '',
  type: null as string | null,
  host: '',
  port: null as number | null,
  database_name: '',
  username: '',
  password: '',
  connection_string: '',
})

const typeOptions = [
  { label: 'MySQL', value: 'mysql' },
  { label: 'PostgreSQL', value: 'postgresql' },
  { label: 'SQL Server', value: 'mssql' },
  { label: 'Fichier CSV', value: 'csv' },
  { label: 'API REST', value: 'api' },
]

const sourceIcon = (type: string) => ({
  mysql:      'pi pi-database',
  postgresql: 'pi pi-database',
  mssql:      'pi pi-database',
  csv:        'pi pi-file-excel',
  api:        'pi pi-link',
}[type] ?? 'pi pi-database')

const statusBadge = (s: string) => ({
  connected:    'wh-badge-green',
  disconnected: 'wh-badge-red',
  pending:      'wh-badge-amber',
}[s] ?? 'wh-badge-slate')

const statusLabel = (s: string) => ({
  connected:    'Connecté',
  disconnected: 'Déconnecté',
  pending:      'En attente',
}[s] ?? s)

const formatDate = (v: string) =>
  v ? new Date(v).toLocaleDateString('fr-FR', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'

const testConnection = async (src: DataSource) => {
  await fetch(`/api/v1/bi/data-sources/${src.id}/test`, { method: 'POST', headers: { Accept: 'application/json' } })
}

const createSource = async () => {
  await fetch('/api/v1/bi/data-sources', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(form),
  })
  showCreate.value = false
}
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; gap:16px; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; gap:8px; }
.btn { font-family:var(--font-sans); font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:background var(--dur-base); line-height:1.2; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover { background:var(--halo-700); }
.btn-primary:disabled { opacity:0.5; cursor:not-allowed; }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover { background:var(--bg-sunken); }
.btn-danger { background:var(--danger-bg); color:var(--danger-fg); border-color:transparent; }
.btn-danger:hover { filter:brightness(0.95); }
.wh-label { display:block; font-size:12px; font-weight:600; color:var(--fg-2); margin-bottom:5px; }
.wh-empty-state { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:80px 20px; background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); text-align:center; }
.sources-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:14px; }
.source-card { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); overflow:hidden; }
.source-card-head { display:flex; align-items:center; gap:12px; padding:16px 18px; border-bottom:1px solid var(--border-subtle); }
.source-icon { width:36px; height:36px; border-radius:var(--r-md); background:var(--halo-50); color:var(--halo-600); display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
.source-card-body { padding:14px 18px; display:flex; flex-direction:column; gap:8px; }
.source-meta-row { display:flex; align-items:center; justify-content:space-between; gap:8px; }
.source-meta-label { font-size:11px; color:var(--fg-3); text-transform:uppercase; letter-spacing:0.06em; font-weight:600; flex-shrink:0; }
.source-meta-value { font-size:13px; color:var(--fg-1); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:200px; }
.source-card-foot { display:flex; gap:6px; padding:12px 18px; border-top:1px solid var(--border-subtle); background:var(--bg-sunken); }
.wh-badge { display:inline-flex; align-items:center; gap:5px; padding:2px 8px; border-radius:var(--r-pill); font-size:11px; font-weight:500; flex-shrink:0; }
.wh-badge-dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
.wh-badge-green { background:var(--success-bg); color:var(--success-fg); }
.wh-badge-red   { background:var(--danger-bg); color:var(--danger-fg); }
.wh-badge-amber { background:var(--warn-bg); color:var(--warn-fg); }
.wh-badge-slate { background:var(--bg-sunken); color:var(--fg-2); }
.w-full { width:100%; }
</style>
