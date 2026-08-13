<template>
  <AppLayout>
    <Head title="Abonnés Email" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Abonnés</h1>
        <p class="wh-page-subtitle">{{ subscribers.total }} abonné{{ subscribers.total !== 1 ? 's' : '' }} au total</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-secondary" @click="showImport = true">
          <i class="pi pi-upload" style="font-size:13px" /> Importer CSV
        </button>
        <button class="btn btn-primary" @click="openAdd">
          <i class="pi pi-user-plus" style="font-size:13px" /> Ajouter un abonné
        </button>
      </div>
    </div>

    <!-- KPI row -->
    <div class="wh-kpi-grid">
      <div class="wh-kpi" v-for="k in kpiList" :key="k.label">
        <div class="wh-kpi-label">{{ k.label }}</div>
        <div class="wh-kpi-num font-display">{{ k.value }}</div>
      </div>
    </div>

    <!-- Filters -->
    <div class="wh-panel filter-bar">
      <div style="position:relative;flex:1;min-width:200px;max-width:320px">
        <i class="pi pi-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--fg-4);font-size:13px;pointer-events:none" />
        <input
          v-model="search"
          class="wh-filter-input"
          placeholder="Rechercher par email ou nom…"
          @input="onSearchInput"
        />
      </div>
      <select v-model="statusFilter" class="wh-select-sm" @change="applyFilters">
        <option value="">Tous les statuts</option>
        <option value="active">Actif</option>
        <option value="unsubscribed">Désabonné</option>
        <option value="bounced">Bounced</option>
        <option value="pending">En attente</option>
      </select>
      <select v-model="listFilter" class="wh-select-sm" @change="applyFilters">
        <option value="">Toutes les listes</option>
        <option v-for="l in emailLists" :key="l.id" :value="l.id">{{ l.name }}</option>
      </select>
      <button v-if="search || statusFilter || listFilter" class="btn-reset" @click="resetFilters">
        <i class="pi pi-filter-slash" style="font-size:12px" /> Réinitialiser
      </button>
    </div>

    <!-- Table -->
    <div class="wh-panel">
      <table class="wh-dt">
        <thead>
          <tr>
            <th style="width:36px"><input type="checkbox" class="wh-checkbox" @change="toggleAll" :checked="allSelected" /></th>
            <th>Abonné</th>
            <th>Liste</th>
            <th>Statut</th>
            <th>Inscrit le</th>
            <th>Tags</th>
            <th style="width:100px"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="sub in subscribers.data" :key="sub.id" class="wh-dt-row">
            <td><input type="checkbox" class="wh-checkbox" :checked="selected.has(sub.id)" @change="toggleOne(sub.id)" /></td>
            <td>
              <div style="display:flex;align-items:center;gap:10px">
                <div class="avatar">{{ initials(sub) }}</div>
                <div>
                  <p style="font-weight:500;color:var(--fg-1);margin:0">{{ fullName(sub) }}</p>
                  <p style="font-size:12px;color:var(--fg-3);margin:1px 0 0">{{ sub.email }}</p>
                </div>
              </div>
            </td>
            <td style="color:var(--fg-2)">{{ sub.list?.name ?? '—' }}</td>
            <td>
              <span :class="statusBadge(sub.status)" class="wh-badge">
                <span class="wh-badge-dot" />{{ statusLabel(sub.status) }}
              </span>
            </td>
            <td style="color:var(--fg-3)">{{ formatDate(sub.subscribed_at) }}</td>
            <td>
              <div style="display:flex;flex-wrap:wrap;gap:3px">
                <span v-for="tag in (sub.tags ?? []).slice(0,3)" :key="tag" class="tag-chip">{{ tag }}</span>
                <span v-if="(sub.tags ?? []).length > 3" style="font-size:11px;color:var(--fg-3)">+{{ (sub.tags ?? []).length - 3 }}</span>
              </div>
            </td>
            <td>
              <div style="display:flex;gap:4px;justify-content:flex-end">
                <button class="row-btn" title="Modifier" @click="editSub(sub)"><i class="pi pi-pencil" style="font-size:11px" /></button>
                <button class="row-btn row-btn-danger" title="Désabonner" @click="unsubscribeSub(sub)" :disabled="sub.status === 'unsubscribed'">
                  <i class="pi pi-user-minus" style="font-size:11px" />
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!subscribers.data.length">
            <td colspan="7" style="text-align:center;color:var(--fg-3);padding:36px">Aucun abonné trouvé.</td>
          </tr>
        </tbody>
      </table>

      <!-- Bulk actions + pagination -->
      <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 16px;border-top:1px solid var(--border-subtle);flex-wrap:wrap;gap:8px">
        <div style="display:flex;align-items:center;gap:8px">
          <span style="font-size:13px;color:var(--fg-3)">{{ subscribers.total }} résultat{{ subscribers.total !== 1 ? 's' : '' }}</span>
          <template v-if="selected.size > 0">
            <span style="font-size:13px;color:var(--halo-600);font-weight:500">{{ selected.size }} sélectionné(s)</span>
            <button class="btn-sm btn-sm-danger" @click="bulkUnsubscribe">Désabonner</button>
            <button class="btn-sm btn-sm-danger" @click="bulkDelete">Supprimer</button>
          </template>
        </div>
        <div style="display:flex;gap:4px">
          <button
            v-for="p in visiblePages"
            :key="p"
            :class="['page-btn', { active: p === subscribers.current_page }]"
            @click="goPage(p)"
          >{{ p }}</button>
        </div>
      </div>
    </div>

    <!-- Add subscriber dialog -->
    <div v-if="showAdd" class="modal-overlay" @click.self="showAdd = false">
      <div class="form-modal">
        <div class="modal-header">
          <h3 style="margin:0;font-size:16px;font-weight:600;color:var(--fg-1)">{{ editTarget ? 'Modifier l\'abonné' : 'Ajouter un abonné' }}</h3>
          <button class="icon-btn" @click="showAdd = false"><i class="pi pi-times" style="font-size:14px" /></button>
        </div>
        <div style="padding:16px;display:flex;flex-direction:column;gap:12px">
          <div class="field-row">
            <div class="field">
              <label class="field-label">Prénom</label>
              <input v-model="subForm.first_name" class="wh-input" placeholder="Prénom" />
            </div>
            <div class="field">
              <label class="field-label">Nom</label>
              <input v-model="subForm.last_name" class="wh-input" placeholder="Nom" />
            </div>
          </div>
          <div class="field">
            <label class="field-label">Email <span style="color:var(--danger-fg)">*</span></label>
            <input v-model="subForm.email" class="wh-input" type="email" placeholder="email@exemple.com" />
          </div>
          <div class="field">
            <label class="field-label">Liste</label>
            <select v-model="subForm.list_id" class="wh-select">
              <option value="">— Sélectionner une liste —</option>
              <option v-for="l in emailLists" :key="l.id" :value="l.id">{{ l.name }}</option>
            </select>
          </div>
          <div class="field">
            <label class="field-label">Tags (séparés par des virgules)</label>
            <input v-model="subForm.tagsRaw" class="wh-input" placeholder="newsletter, vip, client" />
          </div>
          <div v-if="addError" class="alert-error">{{ addError }}</div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;padding:12px 16px;border-top:1px solid var(--border-subtle)">
          <button class="btn btn-secondary" @click="showAdd = false">Annuler</button>
          <button class="btn btn-primary" :disabled="savingSub" @click="submitSub">
            {{ savingSub ? 'Enregistrement…' : (editTarget ? 'Mettre à jour' : 'Ajouter') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Import CSV dialog -->
    <div v-if="showImport" class="modal-overlay" @click.self="showImport = false">
      <div class="form-modal">
        <div class="modal-header">
          <h3 style="margin:0;font-size:16px;font-weight:600;color:var(--fg-1)">Importer des abonnés CSV</h3>
          <button class="icon-btn" @click="showImport = false"><i class="pi pi-times" style="font-size:14px" /></button>
        </div>
        <div style="padding:16px;display:flex;flex-direction:column;gap:12px">
          <div class="field">
            <label class="field-label">Liste cible</label>
            <select v-model="importListId" class="wh-select">
              <option value="">— Sélectionner une liste —</option>
              <option v-for="l in emailLists" :key="l.id" :value="l.id">{{ l.name }}</option>
            </select>
          </div>
          <div class="field">
            <label class="field-label">Fichier CSV</label>
            <div class="csv-drop" @dragover.prevent @drop.prevent="onCsvDrop" @click="$refs.csvInput.click()">
              <i class="pi pi-upload" style="font-size:24px;color:var(--fg-3);margin-bottom:8px" />
              <p style="color:var(--fg-2);font-size:13px;margin:0">
                {{ csvFile ? csvFile.name : 'Glissez un fichier CSV ou cliquez pour parcourir' }}
              </p>
            </div>
            <input ref="csvInput" type="file" accept=".csv,text/csv" style="display:none" @change="onCsvSelect" />
          </div>
          <div class="csv-format-hint">
            <p style="margin:0 0 4px;font-size:12px;font-weight:600;color:var(--fg-2)">Format attendu :</p>
            <code style="font-size:11px;color:var(--fg-3)">email,first_name,last_name,tags</code>
          </div>
          <div v-if="importResult" :class="['alert-' + (importResult.success ? 'success' : 'error')]">
            {{ importResult.message }}
          </div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;padding:12px 16px;border-top:1px solid var(--border-subtle)">
          <button class="btn btn-secondary" @click="showImport = false">Annuler</button>
          <button class="btn btn-primary" :disabled="!csvFile || importing" @click="submitImport">
            <i class="pi pi-upload" style="font-size:13px" />
            {{ importing ? 'Import en cours…' : 'Importer' }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  subscribers: { type: Object, required: true },
  emailLists:  { type: Array,  default: () => [] },
  stats:       { type: Object, default: () => ({}) },
})

const search       = ref('')
const statusFilter = ref('')
const listFilter   = ref('')
const selected     = ref(new Set())
const showAdd      = ref(false)
const showImport   = ref(false)
const editTarget   = ref(null)
const addError     = ref('')
const savingSub    = ref(false)
const importing    = ref(false)
const importResult = ref(null)
const csvFile      = ref(null)
const importListId = ref('')

const subForm = ref({ first_name: '', last_name: '', email: '', list_id: '', tagsRaw: '' })

// KPI
const kpiList = computed(() => [
  { label: 'Total',          value: (props.stats.total       ?? props.subscribers.total ?? 0).toLocaleString('fr-FR') },
  { label: 'Actifs',         value: (props.stats.active      ?? 0).toLocaleString('fr-FR') },
  { label: 'Désabonnés',     value: (props.stats.unsubscribed ?? 0).toLocaleString('fr-FR') },
  { label: 'Bounced',        value: (props.stats.bounced     ?? 0).toLocaleString('fr-FR') },
])

// Selection helpers
const allSelected = computed(() => props.subscribers.data.length > 0 && props.subscribers.data.every(s => selected.value.has(s.id)))
const toggleAll   = () => {
  if (allSelected.value) props.subscribers.data.forEach(s => selected.value.delete(s.id))
  else props.subscribers.data.forEach(s => selected.value.add(s.id))
  selected.value = new Set(selected.value)
}
const toggleOne = (id) => {
  const s = new Set(selected.value)
  s.has(id) ? s.delete(id) : s.add(id)
  selected.value = s
}

// Filters
let searchTimer = null
const onSearchInput = () => { clearTimeout(searchTimer); searchTimer = setTimeout(applyFilters, 400) }
const applyFilters = () => router.get('/email/subscribers', { search: search.value, status: statusFilter.value, list_id: listFilter.value }, { preserveState: true, preserveScroll: true })
const resetFilters = () => { search.value = ''; statusFilter.value = ''; listFilter.value = ''; applyFilters() }

// Pagination
const visiblePages = computed(() => {
  const last = props.subscribers.last_page ?? 1
  const cur  = props.subscribers.current_page ?? 1
  if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1)
  const pages = new Set([1, last, cur, cur - 1, cur + 1].filter(p => p >= 1 && p <= last))
  return [...pages].sort((a, b) => a - b)
})
const goPage = (p) => router.get('/email/subscribers', { page: p, search: search.value, status: statusFilter.value, list_id: listFilter.value }, { preserveState: true })

// Add/Edit subscriber
const openAdd = () => {
  editTarget.value = null
  subForm.value = { first_name: '', last_name: '', email: '', list_id: '', tagsRaw: '' }
  addError.value = ''
  showAdd.value = true
}
const editSub = (s) => {
  editTarget.value = s
  subForm.value = { first_name: s.first_name ?? '', last_name: s.last_name ?? '', email: s.email, list_id: s.list_id ?? '', tagsRaw: (s.tags ?? []).join(', ') }
  addError.value = ''
  showAdd.value = true
}

const submitSub = async () => {
  if (!subForm.value.email) { addError.value = 'L\'email est requis.'; return }
  savingSub.value = true; addError.value = ''
  const payload = {
    ...subForm.value,
    tags: subForm.value.tagsRaw ? subForm.value.tagsRaw.split(',').map(t => t.trim()).filter(Boolean) : [],
  }
  try {
    const url    = editTarget.value ? `/api/v1/email/subscribers/${editTarget.value.id}` : '/api/v1/email/subscribers'
    const method = editTarget.value ? 'PUT' : 'POST'
    const res = await fetch(url, {
      method, headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content },
      body: JSON.stringify(payload),
    })
    if (!res.ok) { const e = await res.json(); addError.value = e.message ?? 'Erreur'; return }
    showAdd.value = false; router.reload()
  } finally { savingSub.value = false }
}

const unsubscribeSub = async (s) => {
  await fetch(`/api/v1/email/subscribers/${s.id}/unsubscribe`, {
    method: 'POST',
    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content },
  })
  router.reload()
}

const bulkUnsubscribe = async () => {
  await fetch('/api/v1/email/subscribers/bulk-unsubscribe', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content },
    body: JSON.stringify({ ids: [...selected.value] }),
  })
  selected.value = new Set(); router.reload()
}

const bulkDelete = async () => {
  if (!confirm(`Supprimer ${selected.value.size} abonné(s) ?`)) return
  await fetch('/api/v1/email/subscribers/bulk-delete', {
    method: 'DELETE',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content },
    body: JSON.stringify({ ids: [...selected.value] }),
  })
  selected.value = new Set(); router.reload()
}

// CSV import
const onCsvDrop   = (e) => { const f = e.dataTransfer.files[0]; if (f) csvFile.value = f }
const onCsvSelect = (e) => { csvFile.value = e.target.files[0] || null }
const submitImport = async () => {
  if (!csvFile.value) return
  importing.value = true; importResult.value = null
  const fd = new FormData()
  fd.append('file', csvFile.value)
  if (importListId.value) fd.append('list_id', importListId.value)
  try {
    const res = await fetch('/api/v1/email/subscribers/import', {
      method: 'POST',
      headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content },
      body: fd,
    })
    const data = await res.json()
    importResult.value = { success: res.ok, message: res.ok ? `Import réussi : ${data.imported ?? 0} abonné(s) ajouté(s).` : (data.message ?? 'Erreur lors de l\'import.') }
    if (res.ok) { csvFile.value = null; setTimeout(() => { showImport.value = false; router.reload() }, 1500) }
  } finally { importing.value = false }
}

// Helpers
const initials  = (s) => `${(s.first_name?.[0] ?? s.email[0]).toUpperCase()}${(s.last_name?.[0] ?? '').toUpperCase()}`
const fullName  = (s) => [s.first_name, s.last_name].filter(Boolean).join(' ') || s.email
const formatDate = (v) => v ? new Date(v).toLocaleDateString('fr-FR', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'

const statusBadge = (s) => ({ active: 'wh-badge-green', unsubscribed: 'wh-badge-slate', bounced: 'wh-badge-red', pending: 'wh-badge-amber' }[s] ?? 'wh-badge-slate')
const statusLabel = (s) => ({ active: 'Actif', unsubscribed: 'Désabonné', bounced: 'Bounced', pending: 'En attente' }[s] ?? s)
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:20px; gap:16px; flex-wrap:wrap; }
.wh-page-title { margin:0; font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; gap:8px; }
.btn { font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:background var(--dur-base); line-height:1.2; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover:not(:disabled) { background:var(--halo-700); }
.btn-primary:disabled { opacity:.6; cursor:not-allowed; }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover { background:var(--bg-sunken); }
.btn-sm { padding:4px 10px; border-radius:var(--r-sm); border:1px solid transparent; font-size:12px; font-weight:500; cursor:pointer; }
.btn-sm-danger { background:var(--danger-bg); color:var(--danger-fg); border-color:var(--danger-border,#fca5a5); }
.btn-reset { display:inline-flex; align-items:center; gap:6px; padding:7px 12px; border-radius:var(--r-md); border:1px solid var(--border-subtle); background:var(--bg-canvas); color:var(--fg-2); font-size:13px; cursor:pointer; }
.btn-reset:hover { background:var(--bg-sunken); }
.wh-kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:16px; }
@media (max-width:640px) { .wh-kpi-grid { grid-template-columns:repeat(2,1fr); } }
.wh-kpi { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); padding:16px 20px; }
.wh-kpi-label { font-size:12px; font-weight:500; color:var(--fg-3); letter-spacing:0.04em; text-transform:uppercase; margin-bottom:6px; }
.wh-kpi-num { font-size:26px; font-weight:700; color:var(--fg-1); line-height:1; }
.filter-bar { display:flex; align-items:center; gap:10px; padding:10px 14px; margin-bottom:16px; flex-wrap:wrap; }
.wh-filter-input { width:100%; padding:8px 10px 8px 30px; border:1px solid var(--border-subtle); border-radius:var(--r-md); background:var(--bg-canvas); color:var(--fg-1); font-size:14px; }
.wh-filter-input:focus { outline:none; border-color:var(--halo-400); }
.wh-select-sm { padding:7px 10px; border:1px solid var(--border-subtle); border-radius:var(--r-md); background:var(--bg-canvas); color:var(--fg-1); font-size:13px; cursor:pointer; }
.wh-panel { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); overflow:hidden; }
.wh-dt { width:100%; border-collapse:collapse; font-size:14px; }
.wh-dt thead th { text-align:left; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:var(--fg-3); font-weight:600; padding:10px 16px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sunken); }
.wh-dt-row td { padding:10px 16px; border-bottom:1px solid var(--border-subtle); }
.wh-dt-row:last-child td { border-bottom:0; }
.wh-dt-row { transition:background var(--dur-fast); }
.wh-dt-row:hover { background:var(--bg-sunken); }
.wh-checkbox { cursor:pointer; }
.wh-badge { display:inline-flex; align-items:center; gap:5px; padding:2px 8px; border-radius:var(--r-pill); font-size:11px; font-weight:500; }
.wh-badge-dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
.wh-badge-green  { background:var(--success-bg); color:var(--success-fg); }
.wh-badge-amber  { background:var(--warn-bg); color:var(--warn-fg); }
.wh-badge-red    { background:var(--danger-bg); color:var(--danger-fg); }
.wh-badge-slate  { background:var(--bg-sunken); color:var(--fg-2); }
.avatar { width:34px; height:34px; border-radius:50%; background:var(--halo-100,#e0e7ff); color:var(--halo-700); font-size:12px; font-weight:600; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.tag-chip { padding:1px 7px; border-radius:var(--r-pill); background:var(--bg-sunken); border:1px solid var(--border-subtle); font-size:11px; color:var(--fg-2); }
.row-btn { display:flex; align-items:center; justify-content:center; width:26px; height:26px; border:1px solid var(--border-subtle); border-radius:var(--r-sm); background:var(--bg-canvas); color:var(--fg-2); cursor:pointer; transition:all var(--dur-fast); }
.row-btn:hover:not(:disabled) { background:var(--bg-sunken); color:var(--fg-1); }
.row-btn:disabled { opacity:.4; cursor:not-allowed; }
.row-btn-danger:hover:not(:disabled) { background:var(--danger-bg); color:var(--danger-fg); }
.page-btn { padding:5px 10px; border:1px solid var(--border-subtle); border-radius:var(--r-sm); background:var(--bg-canvas); color:var(--fg-2); font-size:13px; cursor:pointer; transition:all var(--dur-fast); }
.page-btn.active { background:var(--halo-500); color:#fff; border-color:var(--halo-500); }
.page-btn:hover:not(.active) { background:var(--bg-sunken); }
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; display:flex; align-items:center; justify-content:center; padding:16px; }
.form-modal { background:var(--bg-canvas); border-radius:var(--r-lg); width:480px; max-width:100%; }
.modal-header { display:flex; align-items:center; justify-content:space-between; padding:14px 16px; border-bottom:1px solid var(--border-subtle); }
.icon-btn { display:flex; align-items:center; justify-content:center; width:28px; height:28px; border:none; background:transparent; color:var(--fg-3); border-radius:var(--r-sm); cursor:pointer; }
.icon-btn:hover { background:var(--bg-sunken); }
.field { display:flex; flex-direction:column; gap:4px; }
.field-row { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.field-label { font-size:13px; font-weight:500; color:var(--fg-2); }
.wh-input { width:100%; padding:8px 10px; border:1px solid var(--border-subtle); border-radius:var(--r-md); background:var(--bg-canvas); color:var(--fg-1); font-size:14px; box-sizing:border-box; }
.wh-input:focus { outline:none; border-color:var(--halo-400); }
.wh-select { width:100%; padding:8px 10px; border:1px solid var(--border-subtle); border-radius:var(--r-md); background:var(--bg-canvas); color:var(--fg-1); font-size:14px; cursor:pointer; }
.wh-select:focus { outline:none; border-color:var(--halo-400); }
.csv-drop { border:2px dashed var(--border-subtle); border-radius:var(--r-md); padding:24px; display:flex; flex-direction:column; align-items:center; justify-content:center; cursor:pointer; transition:border-color var(--dur-fast); }
.csv-drop:hover { border-color:var(--halo-400); }
.csv-format-hint { background:var(--bg-sunken); border-radius:var(--r-sm); padding:10px 12px; }
.alert-error { display:flex; align-items:center; gap:8px; background:var(--danger-bg); color:var(--danger-fg); border:1px solid var(--danger-border,#fca5a5); border-radius:var(--r-sm); padding:10px 12px; font-size:13px; }
.alert-success { display:flex; align-items:center; gap:8px; background:var(--success-bg); color:var(--success-fg); border-radius:var(--r-sm); padding:10px 12px; font-size:13px; }
</style>
