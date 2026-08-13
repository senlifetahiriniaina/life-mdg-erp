<template>
  <AppLayout title="Plan de salle">
    <Head title="POS — Tables" />

    <!-- Header -->
    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Plan de salle</h1>
        <p class="wh-page-subtitle">Vue d'ensemble des tables — cliquez pour gérer une commande</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-ghost" @click="loadTables" :disabled="loading">
          <i :class="loading ? 'pi pi-spin pi-spinner' : 'pi pi-refresh'" style="font-size:12px" />
          Actualiser
        </button>
        <button class="btn btn-primary" @click="showAddTableDialog = true">
          <i class="pi pi-plus" style="font-size:12px" />
          Ajouter une table
        </button>
      </div>
    </div>

    <!-- Legend + Stats -->
    <div class="toolbar">
      <div class="legend">
        <div class="legend-item" v-for="s in statusList" :key="s.value" @click="toggleFilter(s.value)" :class="{ 'leg-active': activeFilters.includes(s.value) }">
          <div class="leg-dot" :class="s.dotClass" />
          <span>{{ s.label }}</span>
          <span class="leg-count">{{ tablesByStatus[s.value] ?? 0 }}</span>
        </div>
      </div>
      <div class="section-tabs">
        <button
          class="section-tab"
          :class="{ 'tab-on': selectedSection === null }"
          @click="selectedSection = null"
        >Toutes</button>
        <button
          v-for="sec in sections"
          :key="sec.id"
          class="section-tab"
          :class="{ 'tab-on': selectedSection === sec.id }"
          @click="selectedSection = sec.id"
        >{{ sec.name }}</button>
      </div>
    </div>

    <!-- Floor plan grid -->
    <div v-if="loading" class="loading-state">
      <i class="pi pi-spin pi-spinner" style="font-size:28px;color:var(--halo-500)" />
      <p style="font-size:13px;color:var(--fg-3);margin:10px 0 0">Chargement des tables…</p>
    </div>

    <div v-else-if="filteredTables.length === 0" class="empty-floor">
      <i class="pi pi-table" style="font-size:40px;color:var(--fg-4)" />
      <p style="font-size:14px;color:var(--fg-3);margin:10px 0">Aucune table trouvée</p>
      <button class="btn btn-primary" @click="showAddTableDialog = true">
        <i class="pi pi-plus" style="font-size:12px" />
        Ajouter la première table
      </button>
    </div>

    <div v-else class="floor-grid">
      <div
        v-for="table in filteredTables"
        :key="table.id"
        class="table-card"
        :class="tableCardClass(table.status)"
        @click="openTable(table)"
      >
        <!-- Table shape icon -->
        <div class="table-icon" :class="tableIconClass(table.status)">
          <i class="pi pi-table" style="font-size:20px" />
        </div>
        <!-- Table info -->
        <p class="table-number">Table {{ table.number }}</p>
        <p class="table-name">{{ table.name ?? '' }}</p>
        <div class="table-meta">
          <span class="table-capacity">
            <i class="pi pi-users" style="font-size:10px" />
            {{ table.capacity ?? '—' }}
          </span>
          <span class="table-status-badge" :class="statusPillClass(table.status)">
            {{ statusLabel(table.status) }}
          </span>
        </div>
        <!-- Occupied: show order info -->
        <div v-if="table.status === 'occupied' && table.current_order" class="table-order">
          <span>{{ formatCurrency(table.current_order.total) }}</span>
          <span class="order-time">{{ formatTime(table.current_order.created_at) }}</span>
        </div>
        <!-- Quick actions on hover -->
        <div class="table-actions" @click.stop>
          <button
            v-if="table.status === 'free'"
            class="t-action-btn t-btn-occupy"
            @click="occupyTable(table)"
            title="Occuper"
          ><i class="pi pi-user-plus" /></button>
          <button
            v-if="table.status === 'occupied'"
            class="t-action-btn t-btn-free"
            @click="freeTable(table)"
            title="Libérer"
          ><i class="pi pi-user-minus" /></button>
          <button
            v-if="table.status === 'free'"
            class="t-action-btn t-btn-reserve"
            @click="promptReserve(table)"
            title="Réserver"
          ><i class="pi pi-calendar-plus" /></button>
          <button
            class="t-action-btn t-btn-cashier"
            @click="goToCashierForTable(table)"
            title="Encaisser"
          ><i class="pi pi-desktop" /></button>
        </div>
      </div>
    </div>

    <!-- ══════════ DIALOG : Détail table / Commande ══════════ -->
    <Dialog
      v-model:visible="showTableDialog"
      :header="`Table ${selectedTable?.number} — ${statusLabel(selectedTable?.status)}`"
      :modal="true"
      :style="{ width: '460px' }"
    >
      <div v-if="selectedTable" style="display:flex;flex-direction:column;gap:16px">
        <div class="tbl-info-grid">
          <div>
            <p class="meta-label">Numéro</p>
            <p class="meta-value">{{ selectedTable.number }}</p>
          </div>
          <div>
            <p class="meta-label">Nom</p>
            <p class="meta-value">{{ selectedTable.name ?? '—' }}</p>
          </div>
          <div>
            <p class="meta-label">Capacité</p>
            <p class="meta-value">{{ selectedTable.capacity ?? '—' }} pers.</p>
          </div>
          <div>
            <p class="meta-label">Section</p>
            <p class="meta-value">{{ sectionName(selectedTable.section_id) }}</p>
          </div>
        </div>

        <div v-if="selectedTable.current_order" class="current-order-card">
          <p class="co-title">Commande en cours</p>
          <div class="co-row"><span>N°</span><strong>#{{ selectedTable.current_order.order_number ?? selectedTable.current_order.id }}</strong></div>
          <div class="co-row"><span>Ouverture</span><strong>{{ formatTime(selectedTable.current_order.created_at) }}</strong></div>
          <div class="co-row"><span>Total</span><strong>{{ formatCurrency(selectedTable.current_order.total) }}</strong></div>
        </div>
        <div v-else class="no-order-card">
          <i class="pi pi-check-circle" style="font-size:20px;color:var(--success-fg)" />
          <p>Table libre — pas de commande en cours</p>
        </div>
      </div>
      <template #footer>
        <button class="btn btn-ghost" @click="showTableDialog = false">Fermer</button>
        <button
          v-if="selectedTable?.status === 'free'"
          class="btn btn-ghost"
          @click="occupyTable(selectedTable); showTableDialog = false"
        ><i class="pi pi-user-plus" style="font-size:12px" />Occuper</button>
        <button
          v-if="selectedTable?.status === 'occupied'"
          class="btn btn-danger"
          @click="freeTable(selectedTable); showTableDialog = false"
        ><i class="pi pi-user-minus" style="font-size:12px" />Libérer</button>
        <button class="btn btn-primary" @click="goToCashierForTable(selectedTable); showTableDialog = false">
          <i class="pi pi-desktop" style="font-size:12px" />
          Encaisser
        </button>
      </template>
    </Dialog>

    <!-- ══════════ DIALOG : Ajouter table ══════════ -->
    <Dialog
      v-model:visible="showAddTableDialog"
      header="Ajouter une table"
      :modal="true"
      :style="{ width: '420px' }"
    >
      <div style="display:flex;flex-direction:column;gap:14px;padding:8px 0">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Numéro *</label>
            <InputText v-model.number="addForm.number" type="number" min="1" class="w-full" />
          </div>
          <div class="form-group">
            <label class="form-label">Nom</label>
            <InputText v-model="addForm.name" placeholder="Ex : Terrasse A" class="w-full" />
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Capacité</label>
            <InputText v-model.number="addForm.capacity" type="number" min="1" placeholder="Ex : 4" class="w-full" />
          </div>
          <div class="form-group">
            <label class="form-label">Section</label>
            <select v-model="addForm.sectionId" class="form-select">
              <option :value="null">—</option>
              <option v-for="sec in sections" :key="sec.id" :value="sec.id">{{ sec.name }}</option>
            </select>
          </div>
        </div>
      </div>
      <template #footer>
        <button class="btn btn-ghost" @click="showAddTableDialog = false">Annuler</button>
        <button class="btn btn-primary" @click="addTable" :disabled="submitting">
          <i v-if="submitting" class="pi pi-spin pi-spinner" style="font-size:12px" />
          Ajouter
        </button>
      </template>
    </Dialog>

    <!-- ══════════ DIALOG : Réservation ══════════ -->
    <Dialog
      v-model:visible="showReserveDialog"
      :header="`Réserver la table ${tableToReserve?.number}`"
      :modal="true"
      :style="{ width: '420px' }"
    >
      <div style="display:flex;flex-direction:column;gap:14px;padding:8px 0">
        <div class="form-group">
          <label class="form-label">Nom du client</label>
          <InputText v-model="reserveForm.customerName" placeholder="Nom…" class="w-full" />
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Date et heure</label>
            <InputText v-model="reserveForm.reservedAt" type="datetime-local" class="w-full" />
          </div>
          <div class="form-group">
            <label class="form-label">Personnes</label>
            <InputText v-model.number="reserveForm.partySize" type="number" min="1" class="w-full" />
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Note</label>
          <Textarea v-model="reserveForm.note" rows="2" placeholder="Demandes spéciales…" class="w-full" />
        </div>
      </div>
      <template #footer>
        <button class="btn btn-ghost" @click="showReserveDialog = false">Annuler</button>
        <button class="btn btn-primary" @click="createReservation" :disabled="submitting">
          <i v-if="submitting" class="pi pi-spin pi-spinner" style="font-size:12px" />
          Réserver
        </button>
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { Dialog, InputText, Textarea } from 'primevue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { usePosStore } from '@/stores/posStore'
import axios from 'axios'

const store = usePosStore()

// ─── State ─────────────────────────────────────────────────────────────────
const tables          = ref([])
const sections        = ref([])
const loading         = ref(false)
const submitting      = ref(false)
const activeFilters   = ref([])
const selectedSection = ref(null)

const showTableDialog    = ref(false)
const showAddTableDialog = ref(false)
const showReserveDialog  = ref(false)
const selectedTable      = ref(null)
const tableToReserve     = ref(null)

const addForm = ref({ number: '', name: '', capacity: 4, sectionId: null })
const reserveForm = ref({ customerName: '', reservedAt: '', partySize: 2, note: '' })

// ─── Computed ───────────────────────────────────────────────────────────────
const filteredTables = computed(() => {
  let list = tables.value
  if (selectedSection.value !== null) list = list.filter((t) => t.section_id === selectedSection.value)
  if (activeFilters.value.length) list = list.filter((t) => activeFilters.value.includes(t.status))
  return list
})

const tablesByStatus = computed(() => {
  const map = { free: 0, occupied: 0, reserved: 0 }
  tables.value.forEach((t) => { if (map[t.status] !== undefined) map[t.status]++ })
  return map
})

// ─── Status definitions ─────────────────────────────────────────────────────
const statusList = [
  { value: 'free',     label: 'Libre',    dotClass: 'dot-green' },
  { value: 'occupied', label: 'Occupée',  dotClass: 'dot-red' },
  { value: 'reserved', label: 'Réservée', dotClass: 'dot-amber' },
]

function toggleFilter(status) {
  const idx = activeFilters.value.indexOf(status)
  if (idx >= 0) {
    activeFilters.value.splice(idx, 1)
  } else {
    activeFilters.value.push(status)
  }
}

// ─── Styling ────────────────────────────────────────────────────────────────
const tableCardClass = (status) => ({
  free:     'tc-free',
  occupied: 'tc-occupied',
  reserved: 'tc-reserved',
}[status] ?? '')

const tableIconClass = (status) => ({
  free:     'ti-green',
  occupied: 'ti-red',
  reserved: 'ti-amber',
}[status] ?? '')

const statusPillClass = (status) => ({
  free:     'pill-green',
  occupied: 'pill-red',
  reserved: 'pill-amber',
}[status] ?? '')

const statusLabel = (status) => ({
  free:     'Libre',
  occupied: 'Occupée',
  reserved: 'Réservée',
}[status] ?? status ?? '—')

// ─── Data ───────────────────────────────────────────────────────────────────
async function loadTables() {
  loading.value = true
  try {
    const [tablesRes, sectionsRes] = await Promise.all([
      axios.get('/api/v1/pos/tables?per_page=100'),
      axios.get('/api/v1/pos/table-sections'),
    ])
    tables.value   = tablesRes.data.data ?? tablesRes.data ?? []
    sections.value = sectionsRes.data.data ?? sectionsRes.data ?? []
  } catch { /* silently degrade */ } finally {
    loading.value = false
  }
}

// ─── Actions ────────────────────────────────────────────────────────────────
function openTable(table) {
  selectedTable.value = table
  showTableDialog.value = true
}

async function occupyTable(table) {
  try {
    await axios.post(`/api/v1/pos/tables/${table.id}/occupy`)
    await loadTables()
  } catch (e) { console.error(e) }
}

async function freeTable(table) {
  if (!confirm(`Libérer la table ${table.number} ?`)) return
  try {
    await axios.post(`/api/v1/pos/tables/${table.id}/free`)
    await loadTables()
  } catch (e) { console.error(e) }
}

function promptReserve(table) {
  tableToReserve.value  = table
  reserveForm.value     = { customerName: '', reservedAt: '', partySize: 2, note: '' }
  showReserveDialog.value = true
}

async function createReservation() {
  if (!tableToReserve.value) return
  submitting.value = true
  try {
    await axios.post('/api/v1/pos/reservations', {
      table_id:      tableToReserve.value.id,
      customer_name: reserveForm.value.customerName,
      reserved_at:   reserveForm.value.reservedAt,
      party_size:    reserveForm.value.partySize,
      note:          reserveForm.value.note,
    })
    showReserveDialog.value = false
    await loadTables()
  } finally {
    submitting.value = false
  }
}

async function addTable() {
  submitting.value = true
  try {
    await axios.post('/api/v1/pos/tables', {
      number:     addForm.value.number,
      name:       addForm.value.name,
      capacity:   addForm.value.capacity,
      section_id: addForm.value.sectionId,
    })
    showAddTableDialog.value = false
    addForm.value = { number: '', name: '', capacity: 4, sectionId: null }
    await loadTables()
  } finally {
    submitting.value = false
  }
}

function goToCashierForTable(table) {
  store.selectTable(table)
  router.visit('/pos/cashier')
}

// ─── Helpers ────────────────────────────────────────────────────────────────
const formatCurrency = (v) =>
  new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(Number(v ?? 0))

const formatTime = (iso) => {
  if (!iso) return '—'
  return new Date(iso).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
}

const sectionName = (id) => sections.value.find((s) => s.id === id)?.name ?? '—'

onMounted(loadTables)
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:20px; gap:16px; flex-wrap:wrap; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; gap:8px; }

/* Toolbar */
.toolbar { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:18px; flex-wrap:wrap; }
.legend { display:flex; gap:8px; flex-wrap:wrap; }
.legend-item { display:inline-flex; align-items:center; gap:7px; padding:5px 12px; border-radius:var(--r-pill); border:1px solid var(--border-subtle); background:var(--bg-canvas); font-size:13px; font-weight:500; color:var(--fg-2); cursor:pointer; transition:all var(--dur-fast); user-select:none; }
.legend-item:hover { border-color:var(--halo-300); color:var(--fg-1); }
.leg-active { background:var(--bg-sunken); border-color:var(--halo-300); color:var(--fg-1); }
.leg-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
.dot-green { background:#10B981; }
.dot-red   { background:#EF4444; }
.dot-amber { background:#F59E0B; }
.leg-count { background:var(--bg-sunken); border-radius:var(--r-pill); padding:0 6px; font-size:11px; font-weight:600; color:var(--fg-3); margin-left:2px; }

.section-tabs { display:flex; gap:4px; flex-wrap:wrap; }
.section-tab { padding:5px 12px; border-radius:var(--r-pill); border:1px solid var(--border-subtle); background:var(--bg-canvas); font-size:13px; font-weight:500; color:var(--fg-2); cursor:pointer; transition:all var(--dur-fast); font-family:var(--font-sans); }
.section-tab:hover { border-color:var(--halo-300); color:var(--halo-600); }
.tab-on { background:var(--halo-50); border-color:var(--halo-400); color:var(--halo-700) !important; }

/* Loading / Empty */
.loading-state { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:80px; }
.empty-floor { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:80px; background:var(--bg-canvas); border:2px dashed var(--border-subtle); border-radius:var(--r-lg); }

/* Floor grid */
.floor-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:14px; }

/* Table card */
.table-card {
  position: relative;
  background: var(--bg-canvas);
  border: 2px solid var(--border-subtle);
  border-radius: var(--r-lg);
  padding: 16px 14px 12px;
  cursor: pointer;
  text-align: center;
  transition: all var(--dur-fast);
  overflow: hidden;
}
.table-card:hover { box-shadow:var(--shadow-lg); transform:translateY(-2px); }
.table-card:hover .table-actions { opacity:1; }

/* Status variants */
.tc-free     { border-color:#A7F3D0; background:#ECFDF5; }
.tc-occupied { border-color:#FCA5A5; background:#FFF1F2; }
.tc-reserved { border-color:#FDE68A; background:#FFFBEB; }

.table-icon { width:52px; height:52px; border-radius:var(--r-md); display:flex; align-items:center; justify-content:center; margin:0 auto 10px; }
.ti-green  { background:#D1FAE5; color:#059669; }
.ti-red    { background:#FEE2E2; color:#DC2626; }
.ti-amber  { background:#FEF3C7; color:#D97706; }

.table-number { font-size:16px; font-weight:700; color:var(--fg-1); margin:0 0 2px; }
.table-name { font-size:12px; color:var(--fg-3); margin:0 0 8px; min-height:16px; }

.table-meta { display:flex; align-items:center; justify-content:center; gap:6px; }
.table-capacity { font-size:11px; color:var(--fg-3); display:inline-flex; align-items:center; gap:3px; }
.table-status-badge { font-size:10px; font-weight:600; padding:2px 7px; border-radius:var(--r-pill); }
.pill-green { background:#D1FAE5; color:#065F46; }
.pill-red   { background:#FEE2E2; color:#991B1B; }
.pill-amber { background:#FEF3C7; color:#92400E; }

.table-order { margin-top:8px; padding-top:8px; border-top:1px dashed rgba(0,0,0,0.08); display:flex; justify-content:space-between; font-size:12px; }
.order-time { color:var(--fg-3); }

/* Hover actions overlay */
.table-actions {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  background: rgba(255,255,255,0.96);
  border-top: 1px solid var(--border-subtle);
  display: flex;
  justify-content: center;
  gap: 4px;
  padding: 6px;
  opacity: 0;
  transition: opacity var(--dur-fast);
}
.t-action-btn { width:28px; height:28px; border-radius:var(--r-sm); border:1px solid var(--border-subtle); background:var(--bg-canvas); cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:12px; transition:all var(--dur-fast); }
.t-btn-occupy  { color:#059669; } .t-btn-occupy:hover  { background:#D1FAE5; border-color:#A7F3D0; }
.t-btn-free    { color:#DC2626; } .t-btn-free:hover    { background:#FEE2E2; border-color:#FCA5A5; }
.t-btn-reserve { color:#D97706; } .t-btn-reserve:hover { background:#FEF3C7; border-color:#FDE68A; }
.t-btn-cashier { color:var(--halo-600); } .t-btn-cashier:hover { background:var(--halo-50); border-color:var(--halo-300); }

/* Dialog details */
.tbl-info-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:12px; background:var(--bg-sunken); border-radius:var(--r-md); padding:14px; }
.meta-label { font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-3); margin:0 0 3px; }
.meta-value { font-size:14px; font-weight:500; color:var(--fg-1); margin:0; }
.current-order-card { background:var(--warn-bg,#FEF3C7); border:1px solid #FDE68A; border-radius:var(--r-md); padding:14px; display:flex; flex-direction:column; gap:6px; }
.co-title { font-size:12px; font-weight:600; color:#92400E; text-transform:uppercase; letter-spacing:0.04em; margin:0 0 4px; }
.co-row { display:flex; justify-content:space-between; font-size:13px; color:#78350F; }
.no-order-card { display:flex; align-items:center; gap:10px; background:var(--success-bg); border:1px solid #A7F3D0; border-radius:var(--r-md); padding:14px; font-size:13px; color:var(--success-fg); }

/* Form */
.form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.form-group { display:flex; flex-direction:column; gap:5px; }
.form-label { font-size:13px; font-weight:500; color:var(--fg-2); }
.form-select { padding:8px 10px; border-radius:var(--r-md); border:1px solid var(--border-subtle); background:var(--bg-canvas); font-size:14px; color:var(--fg-1); outline:none; font-family:var(--font-sans); }
.w-full { width:100%; }

/* Buttons */
.btn { font-family:var(--font-sans); font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:background var(--dur-base); line-height:1.2; }
.btn-sm { font-size:12px; padding:5px 9px; }
.btn-primary { background:var(--halo-500); color:#fff; border-color:var(--halo-500); }
.btn-primary:hover:not(:disabled) { background:var(--halo-700); }
.btn-primary:disabled { opacity:0.6; cursor:not-allowed; }
.btn-ghost { background:transparent; color:var(--fg-2); border-color:var(--border-subtle); }
.btn-ghost:hover:not(:disabled) { background:var(--bg-sunken); color:var(--fg-1); }
.btn-ghost:disabled { opacity:0.5; cursor:not-allowed; }
.btn-danger { background:var(--danger-fg); color:#fff; border-color:var(--danger-fg); }
.btn-danger:hover:not(:disabled) { opacity:0.85; }
</style>
