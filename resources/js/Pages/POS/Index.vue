<template>
  <AppLayout title="Point de vente">
    <Head title="POS — Accueil" />

    <!-- Page header -->
    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Point de vente</h1>
        <p class="wh-page-subtitle">Gérez vos sessions de caisse et suivez les ventes du jour</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="goToCashier">
          <i class="pi pi-desktop" style="font-size:13px" />
          Ouvrir la caisse
        </button>
        <button class="btn btn-ghost" @click="goToSessions">
          <i class="pi pi-list" style="font-size:13px" />
          Sessions
        </button>
      </div>
    </div>

    <!-- KPIs du jour -->
    <div class="kpi-grid">
      <div class="kpi-card kpi-revenue">
        <div class="kpi-icon"><i class="pi pi-chart-line" /></div>
        <div class="kpi-body">
          <p class="kpi-label">CA du jour</p>
          <p class="kpi-value">{{ formatCurrency(todayStats.revenue) }}</p>
          <p class="kpi-sub" :class="todayStats.revenueGrowth >= 0 ? 'pos' : 'neg'">
            <i :class="todayStats.revenueGrowth >= 0 ? 'pi pi-arrow-up' : 'pi pi-arrow-down'" style="font-size:10px" />
            {{ Math.abs(todayStats.revenueGrowth ?? 0) }}% vs hier
          </p>
        </div>
      </div>
      <div class="kpi-card">
        <div class="kpi-icon kpi-icon-blue"><i class="pi pi-shopping-cart" /></div>
        <div class="kpi-body">
          <p class="kpi-label">Transactions</p>
          <p class="kpi-value">{{ todayStats.transactions ?? 0 }}</p>
          <p class="kpi-sub neutral">Commandes validées</p>
        </div>
      </div>
      <div class="kpi-card">
        <div class="kpi-icon kpi-icon-violet"><i class="pi pi-wallet" /></div>
        <div class="kpi-body">
          <p class="kpi-label">Panier moyen</p>
          <p class="kpi-value">{{ formatCurrency(todayStats.averageBasket) }}</p>
          <p class="kpi-sub neutral">Par transaction</p>
        </div>
      </div>
      <div class="kpi-card">
        <div class="kpi-icon kpi-icon-green"><i class="pi pi-desktop" /></div>
        <div class="kpi-body">
          <p class="kpi-label">Sessions ouvertes</p>
          <p class="kpi-value">{{ openSessions.length }}</p>
          <p class="kpi-sub neutral">En cours</p>
        </div>
      </div>
    </div>

    <!-- Sessions actives -->
    <section class="section">
      <div class="section-header">
        <h2 class="section-title">Sessions actives</h2>
        <button class="btn btn-ghost btn-sm" @click="loadSessions" :disabled="loadingSessions">
          <i :class="loadingSessions ? 'pi pi-spin pi-spinner' : 'pi pi-refresh'" style="font-size:12px" />
          Actualiser
        </button>
      </div>

      <div v-if="loadingSessions" class="loading-state">
        <i class="pi pi-spin pi-spinner" style="font-size:24px;color:var(--halo-500)" />
      </div>

      <div v-else-if="openSessions.length === 0" class="empty-panel">
        <i class="pi pi-desktop" style="font-size:36px;color:var(--fg-4);margin-bottom:10px" />
        <p style="font-size:14px;color:var(--fg-3);margin:0">Aucune session ouverte pour le moment</p>
        <button class="btn btn-primary" style="margin-top:14px" @click="showOpenSessionDialog = true">
          <i class="pi pi-plus" style="font-size:12px" />
          Ouvrir une session
        </button>
      </div>

      <div v-else class="sessions-grid">
        <div
          v-for="session in openSessions"
          :key="session.id"
          class="session-card"
          @click="goToCashierWithSession(session)"
        >
          <div class="session-card-header">
            <div class="session-badge">
              <span class="dot-green" />
              Ouverte
            </div>
            <span class="session-id">#{{ session.id }}</span>
          </div>
          <p class="session-cashier">{{ session.cashier_name ?? 'Caissier' }}</p>
          <p class="session-config">{{ session.config_name ?? 'Caisse principale' }}</p>
          <div class="session-card-footer">
            <div>
              <p class="meta-label">Fond de caisse</p>
              <p class="meta-value">{{ formatCurrency(session.opening_balance) }}</p>
            </div>
            <div>
              <p class="meta-label">Ouverture</p>
              <p class="meta-value">{{ formatTime(session.opened_at) }}</p>
            </div>
            <button class="btn btn-primary btn-sm session-btn" @click.stop="goToCashierWithSession(session)">
              <i class="pi pi-desktop" style="font-size:11px" />
              Encaisser
            </button>
          </div>
        </div>

        <!-- New session card -->
        <button class="session-card session-new" @click="showOpenSessionDialog = true">
          <i class="pi pi-plus-circle" style="font-size:28px;color:var(--halo-400)" />
          <p style="font-size:13px;font-weight:500;color:var(--halo-600);margin:8px 0 0">Ouvrir une session</p>
        </button>
      </div>
    </section>

    <!-- Quick nav -->
    <section class="section">
      <h2 class="section-title" style="margin-bottom:12px">Accès rapide</h2>
      <div class="quick-grid">
        <button class="quick-card" @click="router.visit('/pos/cashier')">
          <i class="pi pi-desktop quick-icon" />
          <span>Caisse</span>
        </button>
        <button class="quick-card" @click="router.visit('/pos/orders')">
          <i class="pi pi-list quick-icon" />
          <span>Commandes</span>
        </button>
        <button class="quick-card" @click="router.visit('/pos/sessions')">
          <i class="pi pi-clock quick-icon" />
          <span>Sessions</span>
        </button>
        <button class="quick-card" @click="router.visit('/pos/tables')">
          <i class="pi pi-table quick-icon" />
          <span>Tables</span>
        </button>
      </div>
    </section>

    <!-- Open Session Dialog -->
    <Dialog
      v-model:visible="showOpenSessionDialog"
      header="Ouvrir une session de caisse"
      :modal="true"
      :style="{ width: '420px' }"
    >
      <div style="display:flex;flex-direction:column;gap:16px;padding:8px 0">
        <div class="form-group">
          <label class="form-label">Fond de caisse initial (€)</label>
          <InputText
            v-model="newSessionBalance"
            type="number"
            min="0"
            step="0.01"
            placeholder="0.00"
            class="w-full"
          />
        </div>
      </div>
      <template #footer>
        <button class="btn btn-ghost" @click="showOpenSessionDialog = false">Annuler</button>
        <button class="btn btn-primary" @click="openNewSession" :disabled="openingSession">
          <i v-if="openingSession" class="pi pi-spin pi-spinner" style="font-size:12px" />
          <i v-else class="pi pi-check" style="font-size:12px" />
          Ouvrir la session
        </button>
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { Dialog, InputText } from 'primevue'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

// ─── State ─────────────────────────────────────────────────────────────────
const allSessions         = ref([])
const loadingSessions     = ref(false)
const showOpenSessionDialog = ref(false)
const openingSession      = ref(false)
const newSessionBalance   = ref(0)

const todayStats = ref({
  revenue:       0,
  revenueGrowth: 0,
  transactions:  0,
  averageBasket: 0,
})

// ─── Computed ───────────────────────────────────────────────────────────────
const openSessions = computed(() =>
  allSessions.value.filter((s) => s.status === 'open')
)

// ─── Helpers ────────────────────────────────────────────────────────────────
const formatCurrency = (v) =>
  new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(Number(v ?? 0))

const formatTime = (iso) => {
  if (!iso) return '—'
  return new Date(iso).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
}

// ─── Data loading ───────────────────────────────────────────────────────────
async function loadSessions() {
  loadingSessions.value = true
  try {
    const { data } = await axios.get('/api/v1/pos/sessions?per_page=50&status=open')
    allSessions.value = data.data ?? []
  } catch { /* silently degrade */ } finally {
    loadingSessions.value = false
  }
}

async function loadTodayStats() {
  try {
    const { data } = await axios.get('/api/v1/pos/orders?per_page=1')
    const stats = data.stats ?? {}
    todayStats.value = {
      revenue:       stats.today_revenue ?? 0,
      revenueGrowth: stats.revenue_growth ?? 0,
      transactions:  stats.today_count   ?? 0,
      averageBasket: stats.average_basket ?? 0,
    }
  } catch { /* silently degrade */ }
}

// ─── Actions ────────────────────────────────────────────────────────────────
async function openNewSession() {
  openingSession.value = true
  try {
    await axios.post('/api/v1/pos/sessions', {
      opening_balance: Number(newSessionBalance.value) || 0,
    })
    showOpenSessionDialog.value = false
    newSessionBalance.value = 0
    await loadSessions()
  } finally {
    openingSession.value = false
  }
}

function goToCashier() {
  router.visit('/pos/cashier')
}

function goToCashierWithSession(session) {
  router.visit('/pos/cashier', { data: { session_id: session.id } })
}

function goToSessions() {
  router.visit('/pos/sessions')
}

// ─── Lifecycle ──────────────────────────────────────────────────────────────
onMounted(() => {
  loadSessions()
  loadTodayStats()
})
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; gap:16px; flex-wrap:wrap; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; gap:8px; }

/* KPIs */
.kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:28px; }
@media (max-width:900px) { .kpi-grid { grid-template-columns:repeat(2,1fr); } }
@media (max-width:500px)  { .kpi-grid { grid-template-columns:1fr; } }
.kpi-card { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); padding:18px; display:flex; gap:14px; align-items:flex-start; }
.kpi-revenue { border-top:3px solid var(--halo-500); }
.kpi-icon { width:44px; height:44px; border-radius:var(--r-md); background:var(--halo-50); display:flex; align-items:center; justify-content:center; color:var(--halo-600); font-size:20px; flex-shrink:0; }
.kpi-icon-blue   { background:#EFF6FF; color:#2563EB; }
.kpi-icon-violet { background:#F5F3FF; color:#7C3AED; }
.kpi-icon-green  { background:var(--success-bg); color:var(--success-fg); }
.kpi-body { flex:1; min-width:0; }
.kpi-label { font-size:12px; font-weight:500; color:var(--fg-3); letter-spacing:0.04em; text-transform:uppercase; margin:0 0 4px; }
.kpi-value { font-size:22px; font-weight:700; color:var(--fg-1); margin:0 0 4px; line-height:1.2; font-family:var(--font-display); }
.kpi-sub { font-size:12px; margin:0; display:flex; align-items:center; gap:4px; }
.kpi-sub.pos { color:var(--success-fg); }
.kpi-sub.neg { color:var(--danger-fg); }
.kpi-sub.neutral { color:var(--fg-3); }

/* Sections */
.section { margin-bottom:28px; }
.section-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; }
.section-title { font-size:16px; font-weight:600; color:var(--fg-1); margin:0; }

/* Sessions */
.sessions-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:14px; }
.session-card { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); padding:18px; cursor:pointer; text-align:left; transition:box-shadow var(--dur-base); }
.session-card:hover { box-shadow:var(--shadow-md); border-color:var(--halo-300); }
.session-card-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
.session-badge { display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:500; color:var(--success-fg); background:var(--success-bg); padding:3px 9px; border-radius:var(--r-pill); }
.dot-green { width:6px; height:6px; border-radius:50%; background:var(--success-fg); display:inline-block; }
.session-id { font-size:12px; color:var(--fg-3); font-family:var(--font-mono); }
.session-cashier { font-size:15px; font-weight:600; color:var(--fg-1); margin:0 0 2px; }
.session-config { font-size:13px; color:var(--fg-3); margin:0 0 14px; }
.session-card-footer { display:flex; align-items:flex-end; gap:12px; }
.meta-label { font-size:11px; color:var(--fg-3); text-transform:uppercase; letter-spacing:0.04em; margin:0 0 2px; }
.meta-value { font-size:13px; font-weight:500; color:var(--fg-1); margin:0; }
.session-btn { margin-left:auto; }
.session-new { display:flex; flex-direction:column; align-items:center; justify-content:center; border:2px dashed var(--border-subtle); background:var(--bg-sunken); min-height:150px; transition:border-color var(--dur-base); }
.session-new:hover { border-color:var(--halo-400); background:var(--halo-50); }

/* Quick nav */
.quick-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; }
@media (max-width:600px) { .quick-grid { grid-template-columns:repeat(2,1fr); } }
.quick-card { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:10px; padding:20px 12px; background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); cursor:pointer; font-size:13px; font-weight:500; color:var(--fg-2); transition:all var(--dur-fast); font-family:var(--font-sans); }
.quick-card:hover { border-color:var(--halo-400); color:var(--halo-600); background:var(--halo-50); box-shadow:var(--shadow-sm); }
.quick-icon { font-size:22px; color:var(--halo-500); }

/* Misc */
.loading-state { display:flex; align-items:center; justify-content:center; padding:48px; }
.empty-panel { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:48px 24px; background:var(--bg-canvas); border:1px dashed var(--border-subtle); border-radius:var(--r-lg); }

/* Buttons */
.btn { font-family:var(--font-sans); font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:background var(--dur-base); line-height:1.2; }
.btn-sm { font-size:12px; padding:5px 10px; }
.btn-primary { background:var(--halo-500); color:#fff; border-color:var(--halo-500); }
.btn-primary:hover:not(:disabled) { background:var(--halo-700); border-color:var(--halo-700); }
.btn-primary:disabled { opacity:0.6; cursor:not-allowed; }
.btn-ghost { background:transparent; color:var(--fg-2); border-color:var(--border-subtle); }
.btn-ghost:hover:not(:disabled) { background:var(--bg-sunken); color:var(--fg-1); }
.btn-ghost:disabled { opacity:0.5; cursor:not-allowed; }

.form-group { display:flex; flex-direction:column; gap:5px; }
.form-label { font-size:13px; font-weight:500; color:var(--fg-2); }
.w-full { width:100%; }
</style>
