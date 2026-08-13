<template>
  <AppLayout title="Sessions de caisse">
    <Head title="POS — Sessions" />

    <!-- Header -->
    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Sessions de caisse</h1>
        <p class="wh-page-subtitle">Gérez l'ouverture, la fermeture et les rapports de clôture</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="showOpenDialog = true">
          <i class="pi pi-plus" style="font-size:12px" />
          Ouvrir une session
        </button>
      </div>
    </div>

    <!-- KPIs -->
    <div class="kpi-grid">
      <div class="kpi-card" v-for="k in kpis" :key="k.label">
        <div class="kpi-icon" :class="k.iconClass"><i :class="k.icon" /></div>
        <div>
          <p class="kpi-label">{{ k.label }}</p>
          <p class="kpi-value">{{ k.value }}</p>
        </div>
      </div>
    </div>

    <!-- Filter bar -->
    <div class="filter-bar">
      <div class="search-wrap">
        <i class="pi pi-search search-icon" />
        <input v-model="filterSearch" class="filter-input" placeholder="Chercher par caissier…" />
      </div>
      <select v-model="filterStatus" class="filter-select" @change="loadSessions">
        <option value="">Tous les statuts</option>
        <option value="open">Ouvertes</option>
        <option value="closed">Fermées</option>
      </select>
      <button v-if="filterStatus || filterSearch" class="btn btn-ghost btn-sm" @click="resetFilters">
        <i class="pi pi-times" style="font-size:11px" />
        Réinitialiser
      </button>
    </div>

    <!-- Table -->
    <div class="wh-panel">
      <div v-if="loading" class="loading-state">
        <i class="pi pi-spin pi-spinner" style="font-size:24px;color:var(--halo-500)" />
      </div>
      <table v-else class="wh-dt">
        <thead>
          <tr>
            <th>ID</th>
            <th>Caissier</th>
            <th>Statut</th>
            <th class="num">Fond initial</th>
            <th class="num">Fond final</th>
            <th class="num">Solde attendu</th>
            <th class="num">Écart</th>
            <th>Ouverture</th>
            <th>Fermeture</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="session in filteredSessions" :key="session.id" class="wh-dt-row">
            <td><span class="mono">#{{ session.id }}</span></td>
            <td style="font-weight:500;color:var(--fg-1)">{{ session.cashier_name ?? '—' }}</td>
            <td>
              <span class="wh-badge" :class="statusBadge(session.status)">
                <span class="badge-dot" />
                {{ statusLabel(session.status) }}
              </span>
            </td>
            <td class="num">{{ formatCurrency(session.opening_balance) }}</td>
            <td class="num">{{ session.closing_balance != null ? formatCurrency(session.closing_balance) : '—' }}</td>
            <td class="num">{{ session.expected_balance != null ? formatCurrency(session.expected_balance) : '—' }}</td>
            <td class="num" :class="gapClass(session)">
              {{ session.closing_balance != null && session.expected_balance != null
                ? formatCurrency(session.closing_balance - session.expected_balance)
                : '—' }}
            </td>
            <td class="mono-sm">{{ formatDateTime(session.opened_at) }}</td>
            <td class="mono-sm">{{ session.closed_at ? formatDateTime(session.closed_at) : '—' }}</td>
            <td>
              <div style="display:flex;gap:6px">
                <button
                  v-if="session.status === 'open'"
                  class="btn btn-danger btn-sm"
                  @click="promptClose(session)"
                >
                  <i class="pi pi-lock" style="font-size:11px" />
                  Fermer
                </button>
                <button
                  v-if="session.status === 'closed'"
                  class="btn btn-ghost btn-sm"
                  @click="openZReport(session)"
                >
                  <i class="pi pi-file-pdf" style="font-size:11px" />
                  Z-Rapport
                </button>
                <button class="btn btn-ghost btn-sm" @click="viewSession(session)">
                  <i class="pi pi-eye" style="font-size:11px" />
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="filteredSessions.length === 0">
            <td colspan="10" class="empty-state">
              <i class="pi pi-inbox" style="font-size:32px;display:block;margin-bottom:8px;color:var(--fg-4)" />
              Aucune session trouvée.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ══════════ DIALOG : Ouvrir session ══════════ -->
    <Dialog
      v-model:visible="showOpenDialog"
      header="Ouvrir une session de caisse"
      :modal="true"
      :style="{ width: '440px' }"
    >
      <div style="display:flex;flex-direction:column;gap:16px;padding:8px 0">
        <div class="form-group">
          <label class="form-label">Fond de caisse initial (€)</label>
          <InputText
            v-model.number="form.openingBalance"
            type="number"
            min="0"
            step="0.01"
            placeholder="Ex : 200.00"
            class="w-full"
          />
          <p class="form-hint">Montant de monnaie dans le tiroir-caisse à l'ouverture</p>
        </div>
        <div class="form-group">
          <label class="form-label">Remarque</label>
          <Textarea
            v-model="form.note"
            rows="2"
            placeholder="Note optionnelle…"
            class="w-full"
          />
        </div>
      </div>
      <template #footer>
        <button class="btn btn-ghost" @click="showOpenDialog = false">Annuler</button>
        <button class="btn btn-primary" @click="openSession" :disabled="submitting">
          <i v-if="submitting" class="pi pi-spin pi-spinner" style="font-size:12px" />
          Ouvrir la session
        </button>
      </template>
    </Dialog>

    <!-- ══════════ DIALOG : Fermer session ══════════ -->
    <Dialog
      v-model:visible="showCloseDialog"
      header="Fermer la session"
      :modal="true"
      :style="{ width: '460px' }"
    >
      <div v-if="sessionToClose" style="display:flex;flex-direction:column;gap:16px;padding:8px 0">
        <div class="info-grid">
          <div>
            <p class="info-label">Session</p>
            <p class="info-value mono">#{{ sessionToClose.id }}</p>
          </div>
          <div>
            <p class="info-label">Caissier</p>
            <p class="info-value">{{ sessionToClose.cashier_name ?? '—' }}</p>
          </div>
          <div>
            <p class="info-label">Solde attendu</p>
            <p class="info-value">{{ formatCurrency(sessionToClose.expected_balance) }}</p>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Fond de caisse constaté (€)</label>
          <InputText
            v-model.number="closeForm.closingBalance"
            type="number"
            min="0"
            step="0.01"
            class="w-full"
          />
        </div>
        <div v-if="closeForm.closingBalance !== null && sessionToClose.expected_balance != null" class="gap-display" :class="gapOk ? 'gap-ok' : 'gap-nok'">
          <i :class="gapOk ? 'pi pi-check-circle' : 'pi pi-exclamation-triangle'" />
          <span>Écart : <strong>{{ formatCurrency(closeForm.closingBalance - sessionToClose.expected_balance) }}</strong></span>
        </div>
        <div class="form-group">
          <label class="form-label">Remarque de clôture</label>
          <Textarea v-model="closeForm.note" rows="2" placeholder="Observations…" class="w-full" />
        </div>
      </div>
      <template #footer>
        <button class="btn btn-ghost" @click="showCloseDialog = false">Annuler</button>
        <button class="btn btn-danger" @click="closeSession" :disabled="submitting">
          <i v-if="submitting" class="pi pi-spin pi-spinner" style="font-size:12px" />
          Fermer la session
        </button>
      </template>
    </Dialog>

    <!-- ══════════ DIALOG : Z-Rapport ══════════ -->
    <Dialog
      v-model:visible="showZReport"
      header="Z-Rapport de clôture"
      :modal="true"
      :style="{ width: '520px' }"
    >
      <div v-if="zReportData" class="z-report">
        <div class="z-header">
          <p class="z-title">Rapport de clôture</p>
          <p class="z-date">Session #{{ zReportData.session_id ?? '—' }} · {{ formatDateTime(zReportData.closed_at) }}</p>
        </div>
        <div class="z-section">
          <p class="z-section-title">Résumé des ventes</p>
          <div class="z-row"><span>Nb transactions</span><strong>{{ zReportData.order_count ?? 0 }}</strong></div>
          <div class="z-row"><span>Sous-total</span><strong>{{ formatCurrency(zReportData.subtotal) }}</strong></div>
          <div class="z-row"><span>Remises</span><strong>- {{ formatCurrency(zReportData.discount_total) }}</strong></div>
          <div class="z-row"><span>TVA</span><strong>{{ formatCurrency(zReportData.tax_total) }}</strong></div>
          <div class="z-row z-total"><span>Total TTC</span><strong>{{ formatCurrency(zReportData.total) }}</strong></div>
        </div>
        <div class="z-section">
          <p class="z-section-title">Par mode de paiement</p>
          <div v-for="(amount, method) in (zReportData.by_payment_method ?? {})" :key="method" class="z-row">
            <span style="text-transform:capitalize">{{ method }}</span>
            <strong>{{ formatCurrency(amount) }}</strong>
          </div>
        </div>
        <div class="z-section">
          <p class="z-section-title">Fond de caisse</p>
          <div class="z-row"><span>Fond initial</span><strong>{{ formatCurrency(zReportData.opening_balance) }}</strong></div>
          <div class="z-row"><span>Fond attendu</span><strong>{{ formatCurrency(zReportData.expected_balance) }}</strong></div>
          <div class="z-row"><span>Fond constaté</span><strong>{{ formatCurrency(zReportData.closing_balance) }}</strong></div>
          <div class="z-row" :class="(zReportData.closing_balance - zReportData.expected_balance) >= 0 ? 'z-ok' : 'z-nok'">
            <span>Écart</span>
            <strong>{{ formatCurrency((zReportData.closing_balance ?? 0) - (zReportData.expected_balance ?? 0)) }}</strong>
          </div>
        </div>
      </div>
      <div v-else class="loading-state">
        <i class="pi pi-spin pi-spinner" style="font-size:24px;color:var(--halo-500)" />
      </div>
      <template #footer>
        <button class="btn btn-ghost" @click="showZReport = false">Fermer</button>
        <button class="btn btn-primary" @click="printReport">
          <i class="pi pi-print" style="font-size:12px" />
          Imprimer
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
import axios from 'axios'

// ─── State ─────────────────────────────────────────────────────────────────
const sessions     = ref([])
const loading      = ref(false)
const submitting   = ref(false)
const filterSearch = ref('')
const filterStatus = ref('')

// Dialogs
const showOpenDialog  = ref(false)
const showCloseDialog = ref(false)
const showZReport     = ref(false)
const sessionToClose  = ref(null)
const zReportData     = ref(null)

const form = ref({ openingBalance: 0, note: '' })
const closeForm = ref({ closingBalance: 0, note: '' })

// ─── Computed ───────────────────────────────────────────────────────────────
const filteredSessions = computed(() => {
  let list = sessions.value
  if (filterStatus.value) list = list.filter((s) => s.status === filterStatus.value)
  if (filterSearch.value) {
    const q = filterSearch.value.toLowerCase()
    list = list.filter((s) => (s.cashier_name ?? '').toLowerCase().includes(q))
  }
  return list
})

const kpis = computed(() => {
  const total  = sessions.value.length
  const open   = sessions.value.filter((s) => s.status === 'open').length
  const closed = sessions.value.filter((s) => s.status === 'closed').length
  const revenue = sessions.value.reduce((sum, s) => sum + Number(s.total_sales ?? 0), 0)
  return [
    { label: 'Total sessions', value: total,               icon: 'pi pi-desktop',   iconClass: 'kpi-blue' },
    { label: 'Ouvertes',       value: open,                icon: 'pi pi-circle-fill', iconClass: 'kpi-green' },
    { label: 'Fermées',        value: closed,              icon: 'pi pi-lock',       iconClass: 'kpi-slate' },
    { label: 'CA total',       value: formatCurrency(revenue), icon: 'pi pi-chart-line', iconClass: 'kpi-violet' },
  ]
})

const gapOk = computed(() => {
  if (!sessionToClose.value) return true
  return (closeForm.value.closingBalance ?? 0) >= (sessionToClose.value.expected_balance ?? 0)
})

// ─── Data ───────────────────────────────────────────────────────────────────
async function loadSessions() {
  loading.value = true
  try {
    const params = {}
    if (filterStatus.value) params.status = filterStatus.value
    const { data } = await axios.get('/api/v1/pos/sessions', { params: { per_page: 100, ...params } })
    sessions.value = data.data ?? []
  } finally {
    loading.value = false
  }
}

// ─── Actions ────────────────────────────────────────────────────────────────
async function openSession() {
  submitting.value = true
  try {
    await axios.post('/api/v1/pos/sessions', {
      opening_balance: Number(form.value.openingBalance) || 0,
      note:            form.value.note,
    })
    showOpenDialog.value = false
    form.value = { openingBalance: 0, note: '' }
    await loadSessions()
  } finally {
    submitting.value = false
  }
}

function promptClose(session) {
  sessionToClose.value = session
  closeForm.value = {
    closingBalance: session.expected_balance ?? 0,
    note: '',
  }
  showCloseDialog.value = true
}

async function closeSession() {
  if (!sessionToClose.value) return
  submitting.value = true
  try {
    await axios.post(`/api/v1/pos/sessions/${sessionToClose.value.id}/close`, {
      closing_balance: Number(closeForm.value.closingBalance),
      note:            closeForm.value.note,
    })
    showCloseDialog.value = false
    sessionToClose.value  = null
    await loadSessions()
  } finally {
    submitting.value = false
  }
}

async function openZReport(session) {
  zReportData.value = null
  showZReport.value = true
  try {
    const { data } = await axios.get(`/api/v1/pos/shifts/${session.id}/z-report`)
    zReportData.value = data
  } catch {
    // Fallback — build from session data
    zReportData.value = {
      session_id:       session.id,
      closed_at:        session.closed_at,
      order_count:      session.orders_count ?? 0,
      subtotal:         session.total_sales ?? 0,
      discount_total:   0,
      tax_total:        session.tax_total ?? 0,
      total:            session.total_sales ?? 0,
      opening_balance:  session.opening_balance,
      expected_balance: session.expected_balance,
      closing_balance:  session.closing_balance,
      by_payment_method: {},
    }
  }
}

function viewSession(session) {
  router.visit(`/pos/cashier?session_id=${session.id}`)
}

function resetFilters() {
  filterSearch.value = ''
  filterStatus.value = ''
}

function printReport() {
  window.print()
}

// ─── Helpers ────────────────────────────────────────────────────────────────
const formatCurrency = (v) =>
  new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(Number(v ?? 0))

const formatDateTime = (iso) => {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('fr-FR', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const statusBadge  = (s) => ({ open: 'badge-green', closed: 'badge-slate' }[s] ?? 'badge-slate')
const statusLabel  = (s) => ({ open: 'Ouverte', closed: 'Fermée' }[s] ?? s)
const gapClass = (session) => {
  if (session.closing_balance == null || session.expected_balance == null) return ''
  const gap = session.closing_balance - session.expected_balance
  return gap >= 0 ? 'text-success' : 'text-danger'
}

onMounted(loadSessions)
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; gap:16px; flex-wrap:wrap; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; gap:8px; }

.kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:20px; }
@media(max-width:900px) { .kpi-grid { grid-template-columns:repeat(2,1fr); } }
.kpi-card { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); padding:16px; display:flex; gap:12px; align-items:center; }
.kpi-icon { width:40px; height:40px; border-radius:var(--r-md); display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
.kpi-blue   { background:#EFF6FF; color:#2563EB; }
.kpi-green  { background:var(--success-bg); color:var(--success-fg); }
.kpi-slate  { background:var(--bg-sunken); color:var(--fg-2); }
.kpi-violet { background:#F5F3FF; color:#7C3AED; }
.kpi-label { font-size:11px; font-weight:500; color:var(--fg-3); text-transform:uppercase; letter-spacing:0.04em; margin:0 0 3px; }
.kpi-value { font-size:20px; font-weight:700; color:var(--fg-1); margin:0; line-height:1; }

.filter-bar { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:14px; }
.search-wrap { position:relative; }
.filter-input { padding:8px 10px 8px 32px; border-radius:var(--r-md); border:1px solid var(--border-subtle); background:var(--bg-canvas); font-size:13px; color:var(--fg-1); outline:none; width:200px; font-family:var(--font-sans); }
.filter-input:focus { border-color:var(--halo-500); }
.search-icon { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--fg-4); font-size:12px; pointer-events:none; }
.filter-select { padding:8px 10px; border-radius:var(--r-md); border:1px solid var(--border-subtle); background:var(--bg-canvas); font-size:13px; color:var(--fg-1); outline:none; font-family:var(--font-sans); }

.wh-panel { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); overflow:hidden; }
.wh-dt { width:100%; border-collapse:collapse; font-size:14px; }
.wh-dt thead th { text-align:left; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:var(--fg-3); font-weight:600; padding:10px 16px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sunken); }
.wh-dt thead th.num { text-align:right; }
.wh-dt-row td { padding:11px 16px; border-bottom:1px solid var(--border-subtle); }
.wh-dt-row td.num { text-align:right; }
.wh-dt-row:last-child td { border-bottom:0; }
.wh-dt-row:hover { background:var(--bg-sunken); }
.mono { font-family:var(--font-mono); font-size:13px; color:var(--fg-2); }
.mono-sm { font-family:var(--font-mono); font-size:12px; color:var(--fg-3); }

.wh-badge { display:inline-flex; align-items:center; gap:5px; padding:2px 8px; border-radius:var(--r-pill); font-size:11px; font-weight:500; }
.badge-dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
.badge-green { background:var(--success-bg); color:var(--success-fg); }
.badge-slate { background:var(--bg-sunken); color:var(--fg-2); }

.text-success { color:var(--success-fg); font-weight:500; }
.text-danger  { color:var(--danger-fg); font-weight:500; }

.loading-state { display:flex; align-items:center; justify-content:center; padding:48px; }
.empty-state { text-align:center; color:var(--fg-3); padding:48px 16px; }

/* Info grid in close dialog */
.info-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; background:var(--bg-sunken); border-radius:var(--r-md); padding:14px; }
.info-label { font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-3); margin:0 0 3px; }
.info-value { font-size:14px; font-weight:500; color:var(--fg-1); margin:0; }

.gap-display { display:flex; align-items:center; gap:8px; padding:10px 14px; border-radius:var(--r-md); font-size:14px; font-weight:500; }
.gap-ok  { background:var(--success-bg); color:var(--success-fg); }
.gap-nok { background:var(--danger-bg); color:var(--danger-fg); }

/* Z-report styles */
.z-report { display:flex; flex-direction:column; gap:0; }
.z-header { text-align:center; padding:16px; border-bottom:2px dashed var(--border-subtle); }
.z-title { font-size:16px; font-weight:700; color:var(--fg-1); margin:0 0 4px; }
.z-date { font-size:12px; color:var(--fg-3); margin:0; font-family:var(--font-mono); }
.z-section { padding:14px 0; border-bottom:1px dashed var(--border-subtle); }
.z-section:last-child { border-bottom:0; }
.z-section-title { font-size:11px; font-weight:600; color:var(--fg-3); text-transform:uppercase; letter-spacing:0.06em; margin:0 0 10px; }
.z-row { display:flex; justify-content:space-between; font-size:13px; color:var(--fg-2); padding:3px 0; }
.z-total { font-size:15px; font-weight:700; color:var(--fg-1); padding-top:8px; border-top:1px solid var(--border-subtle); margin-top:4px; }
.z-ok  { color:var(--success-fg); font-weight:600; }
.z-nok { color:var(--danger-fg); font-weight:600; }

/* Form */
.form-group { display:flex; flex-direction:column; gap:5px; }
.form-label { font-size:13px; font-weight:500; color:var(--fg-2); }
.form-hint { font-size:12px; color:var(--fg-4); margin:2px 0 0; }
.w-full { width:100%; }

/* Buttons */
.btn { font-family:var(--font-sans); font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:background var(--dur-base); line-height:1.2; }
.btn-sm { font-size:12px; padding:5px 9px; }
.btn-primary { background:var(--halo-500); color:#fff; border-color:var(--halo-500); }
.btn-primary:hover:not(:disabled) { background:var(--halo-700); }
.btn-primary:disabled { opacity:0.6; cursor:not-allowed; }
.btn-ghost { background:transparent; color:var(--fg-2); border-color:var(--border-subtle); }
.btn-ghost:hover { background:var(--bg-sunken); color:var(--fg-1); }
.btn-danger { background:var(--danger-fg); color:#fff; border-color:var(--danger-fg); }
.btn-danger:hover:not(:disabled) { opacity:0.85; }
.btn-danger:disabled { opacity:0.6; cursor:not-allowed; }
</style>
