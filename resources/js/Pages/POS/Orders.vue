<template>
  <AppLayout title="Commandes POS">
    <Head title="POS — Commandes" />

    <!-- Header -->
    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Commandes POS</h1>
        <p class="wh-page-subtitle">Historique des ventes de la session en cours</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="router.visit('/pos/cashier')">
          <i class="pi pi-desktop" style="font-size:13px" />
          Ouvrir la caisse
        </button>
      </div>
    </div>

    <!-- KPIs -->
    <div class="kpi-grid">
      <div class="kpi-card kpi-revenue">
        <div class="kpi-icon kpi-icon-green"><i class="pi pi-wallet" /></div>
        <div>
          <p class="kpi-label">CA session</p>
          <p class="kpi-value">{{ formatCurrency(stats.revenue) }}</p>
        </div>
      </div>
      <div class="kpi-card">
        <div class="kpi-icon kpi-icon-blue"><i class="pi pi-shopping-cart" /></div>
        <div>
          <p class="kpi-label">Commandes</p>
          <p class="kpi-value">{{ stats.total ?? 0 }}</p>
        </div>
      </div>
      <div class="kpi-card">
        <div class="kpi-icon kpi-icon-amber"><i class="pi pi-clock" /></div>
        <div>
          <p class="kpi-label">En attente</p>
          <p class="kpi-value">{{ stats.pending ?? 0 }}</p>
        </div>
      </div>
      <div class="kpi-card">
        <div class="kpi-icon kpi-icon-violet"><i class="pi pi-wallet" /></div>
        <div>
          <p class="kpi-label">Panier moyen</p>
          <p class="kpi-value">{{ formatCurrency(stats.averageBasket) }}</p>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="filter-bar">
      <div class="search-wrap">
        <i class="pi pi-search search-icon" />
        <input v-model="filterSearch" class="filter-input" placeholder="N° commande…" @input="applyFilters" />
      </div>
      <select v-model="filterStatus" class="filter-select" @change="applyFilters">
        <option value="">Tous les statuts</option>
        <option value="pending">En attente</option>
        <option value="paid">Payée</option>
        <option value="refunded">Remboursée</option>
        <option value="cancelled">Annulée</option>
      </select>
      <select v-model="filterMethod" class="filter-select" @change="applyFilters">
        <option value="">Tout mode</option>
        <option value="cash">Espèces</option>
        <option value="card">Carte</option>
        <option value="mobile">Mobile</option>
      </select>
      <button v-if="filterSearch || filterStatus || filterMethod" class="btn btn-ghost btn-sm" @click="resetFilters">
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
            <th>N° commande</th>
            <th>Statut</th>
            <th>Paiement</th>
            <th>Articles</th>
            <th class="num">Sous-total</th>
            <th class="num">TVA</th>
            <th class="num">Total</th>
            <th>Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="order in filteredOrders"
            :key="order.id"
            class="wh-dt-row"
            @click="openOrderDetail(order)"
          >
            <td>
              <span class="order-num">#{{ order.order_number ?? order.id }}</span>
            </td>
            <td>
              <span class="wh-badge" :class="statusBadge(order.status)">
                <span class="badge-dot" />
                {{ statusLabel(order.status) }}
              </span>
            </td>
            <td>
              <div class="method-chip">
                <i :class="methodIcon(order.payment_method)" style="font-size:12px" />
                {{ methodLabel(order.payment_method) }}
              </div>
            </td>
            <td style="color:var(--fg-3)">{{ order.lines_count ?? '—' }} art.</td>
            <td class="num">{{ formatCurrency(order.subtotal) }}</td>
            <td class="num">{{ formatCurrency(order.tax_amount) }}</td>
            <td class="num total-cell">{{ formatCurrency(order.total) }}</td>
            <td class="date-cell">{{ formatDateTime(order.created_at) }}</td>
            <td @click.stop>
              <div style="display:flex;gap:5px">
                <button class="btn btn-ghost btn-sm" @click="openOrderDetail(order)" title="Voir le détail">
                  <i class="pi pi-eye" style="font-size:11px" />
                </button>
                <button class="btn btn-ghost btn-sm" @click="printOrder(order)" title="Imprimer le reçu">
                  <i class="pi pi-print" style="font-size:11px" />
                </button>
                <button
                  v-if="order.status === 'paid'"
                  class="btn btn-ghost btn-sm"
                  @click="initiateRefund(order)"
                  title="Rembourser"
                >
                  <i class="pi pi-replay" style="font-size:11px" />
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="filteredOrders.length === 0">
            <td colspan="9" class="empty-state">
              <i class="pi pi-shopping-cart" style="font-size:32px;display:block;margin-bottom:8px;color:var(--fg-4)" />
              Aucune commande trouvée.
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div class="pagination-row">
        <span class="results-label">{{ filteredOrders.length }} résultat{{ filteredOrders.length !== 1 ? 's' : '' }}</span>
        <div style="display:flex;gap:6px;align-items:center">
          <button class="btn btn-ghost btn-sm" :disabled="page <= 1" @click="page--; loadOrders()">
            <i class="pi pi-chevron-left" style="font-size:11px" />
          </button>
          <span style="font-size:13px;color:var(--fg-2)">Page {{ page }}</span>
          <button class="btn btn-ghost btn-sm" :disabled="!hasNextPage" @click="page++; loadOrders()">
            <i class="pi pi-chevron-right" style="font-size:11px" />
          </button>
        </div>
      </div>
    </div>

    <!-- ══════════ DIALOG : Détail commande ══════════ -->
    <Dialog
      v-model:visible="showOrderDetail"
      :header="`Commande #${selectedOrder?.order_number ?? selectedOrder?.id}`"
      :modal="true"
      :style="{ width: '560px' }"
    >
      <div v-if="selectedOrder" style="display:flex;flex-direction:column;gap:14px">
        <div class="order-meta-grid">
          <div>
            <p class="meta-label">Statut</p>
            <span class="wh-badge" :class="statusBadge(selectedOrder.status)">
              <span class="badge-dot" />
              {{ statusLabel(selectedOrder.status) }}
            </span>
          </div>
          <div>
            <p class="meta-label">Paiement</p>
            <p class="meta-value">{{ methodLabel(selectedOrder.payment_method) }}</p>
          </div>
          <div>
            <p class="meta-label">Date</p>
            <p class="meta-value">{{ formatDateTime(selectedOrder.created_at) }}</p>
          </div>
          <div>
            <p class="meta-label">Session</p>
            <p class="meta-value mono">#{{ selectedOrder.session_id }}</p>
          </div>
        </div>

        <!-- Lines -->
        <div class="order-lines">
          <p class="lines-title">Articles</p>
          <table class="lines-table">
            <thead>
              <tr>
                <th>Produit</th>
                <th class="num">Qté</th>
                <th class="num">P.U.</th>
                <th class="num">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(line, i) in (selectedOrder.lines ?? selectedOrder.items ?? [])" :key="i">
                <td>{{ line.product_name ?? line.name }}</td>
                <td class="num">{{ line.qty ?? line.quantity }}</td>
                <td class="num">{{ formatCurrency(line.unit_price ?? line.price) }}</td>
                <td class="num">{{ formatCurrency(line.subtotal ?? ((line.unit_price ?? line.price) * (line.qty ?? line.quantity))) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Totals -->
        <div class="order-totals">
          <div class="tot-row"><span>Sous-total</span><span>{{ formatCurrency(selectedOrder.subtotal) }}</span></div>
          <div v-if="selectedOrder.discount > 0" class="tot-row discount-row">
            <span>Remise</span><span>- {{ formatCurrency(selectedOrder.discount) }}</span>
          </div>
          <div class="tot-row"><span>TVA</span><span>{{ formatCurrency(selectedOrder.tax_amount) }}</span></div>
          <div class="tot-row tot-final"><span>Total TTC</span><span>{{ formatCurrency(selectedOrder.total) }}</span></div>
        </div>
      </div>
      <template #footer>
        <button class="btn btn-ghost" @click="showOrderDetail = false">Fermer</button>
        <button class="btn btn-ghost" @click="printOrder(selectedOrder)">
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
import { Dialog } from 'primevue'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

// ─── State ─────────────────────────────────────────────────────────────────
const orders       = ref([])
const loading      = ref(false)
const page         = ref(1)
const perPage      = ref(25)
const hasNextPage  = ref(false)
const filterSearch = ref('')
const filterStatus = ref('')
const filterMethod = ref('')

const showOrderDetail = ref(false)
const selectedOrder   = ref(null)

const stats = ref({
  total: 0, pending: 0, paid: 0, revenue: 0, averageBasket: 0,
})

// ─── Computed ───────────────────────────────────────────────────────────────
const filteredOrders = computed(() => {
  let list = orders.value
  if (filterSearch.value) {
    const q = filterSearch.value.toLowerCase()
    list = list.filter((o) =>
      String(o.order_number ?? o.id).toLowerCase().includes(q)
    )
  }
  if (filterStatus.value) list = list.filter((o) => o.status === filterStatus.value)
  if (filterMethod.value) list = list.filter((o) => o.payment_method === filterMethod.value)
  return list
})

// ─── Data ───────────────────────────────────────────────────────────────────
async function loadOrders() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/pos/orders', {
      params: { page: page.value, per_page: perPage.value },
    })
    orders.value   = data.data ?? []
    hasNextPage.value = !!(data.next_page_url)

    // Stats from meta
    const meta = data.meta ?? data
    stats.value = {
      total:         meta.total        ?? orders.value.length,
      pending:       meta.pending      ?? orders.value.filter((o) => o.status === 'pending').length,
      paid:          meta.paid         ?? orders.value.filter((o) => o.status === 'paid').length,
      revenue:       meta.revenue      ?? orders.value.reduce((s, o) => s + Number(o.total ?? 0), 0),
      averageBasket: meta.average_basket ?? 0,
    }
  } finally {
    loading.value = false
  }
}

// ─── Actions ────────────────────────────────────────────────────────────────
function openOrderDetail(order) {
  selectedOrder.value  = order
  showOrderDetail.value = true
}

function printOrder(order) {
  // Simple: open print dialog; production would open a receipt window
  window.print()
}

async function initiateRefund(order) {
  if (!confirm(`Rembourser la commande #${order.order_number} ?`)) return
  try {
    await axios.post('/api/v1/pos/returns', {
      order_id: order.id,
      reason:   'customer_request',
      lines:    (order.lines ?? order.items ?? []).map((l) => ({
        order_item_id: l.id,
        qty:           l.qty ?? l.quantity,
      })),
    })
    await loadOrders()
  } catch (err) {
    console.error('Refund error', err)
  }
}

function applyFilters() {
  page.value = 1
}

function resetFilters() {
  filterSearch.value = ''
  filterStatus.value = ''
  filterMethod.value = ''
}

// ─── Helpers ────────────────────────────────────────────────────────────────
const formatCurrency = (v) =>
  new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(Number(v ?? 0))

const formatDateTime = (iso) => {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('fr-FR', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

const statusBadge  = (s) => ({ pending: 'badge-amber', paid: 'badge-green', refunded: 'badge-blue', cancelled: 'badge-red' }[s] ?? 'badge-slate')
const statusLabel  = (s) => ({ pending: 'En attente', paid: 'Payée', refunded: 'Remboursée', cancelled: 'Annulée' }[s] ?? s)
const methodIcon   = (m) => ({ cash: 'pi pi-money-bill', card: 'pi pi-credit-card', mobile: 'pi pi-mobile' }[m] ?? 'pi pi-wallet')
const methodLabel  = (m) => ({ cash: 'Espèces', card: 'Carte', mobile: 'Mobile Money' }[m] ?? m ?? '—')

onMounted(loadOrders)
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; gap:16px; flex-wrap:wrap; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; gap:8px; }

.kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:20px; }
@media(max-width:900px) { .kpi-grid { grid-template-columns:repeat(2,1fr); } }
.kpi-card { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); padding:16px; display:flex; gap:12px; align-items:center; }
.kpi-revenue { border-top:3px solid #10B981; }
.kpi-icon { width:40px; height:40px; border-radius:var(--r-md); display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
.kpi-icon-green  { background:var(--success-bg); color:var(--success-fg); }
.kpi-icon-blue   { background:#EFF6FF; color:#2563EB; }
.kpi-icon-amber  { background:#FEF3C7; color:#D97706; }
.kpi-icon-violet { background:#F5F3FF; color:#7C3AED; }
.kpi-label { font-size:11px; font-weight:500; color:var(--fg-3); text-transform:uppercase; letter-spacing:0.04em; margin:0 0 3px; }
.kpi-value { font-size:20px; font-weight:700; color:var(--fg-1); margin:0; line-height:1; }

.filter-bar { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:14px; }
.search-wrap { position:relative; }
.filter-input { padding:8px 10px 8px 32px; border-radius:var(--r-md); border:1px solid var(--border-subtle); background:var(--bg-canvas); font-size:13px; color:var(--fg-1); outline:none; width:180px; font-family:var(--font-sans); }
.filter-input:focus { border-color:var(--halo-500); }
.search-icon { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--fg-4); font-size:12px; pointer-events:none; }
.filter-select { padding:8px 10px; border-radius:var(--r-md); border:1px solid var(--border-subtle); background:var(--bg-canvas); font-size:13px; color:var(--fg-1); outline:none; font-family:var(--font-sans); }

.wh-panel { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); overflow:hidden; }
.wh-dt { width:100%; border-collapse:collapse; font-size:14px; }
.wh-dt thead th { text-align:left; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:var(--fg-3); font-weight:600; padding:10px 16px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sunken); }
.wh-dt thead th.num { text-align:right; }
.wh-dt-row { cursor:pointer; transition:background var(--dur-fast); }
.wh-dt-row td { padding:11px 16px; border-bottom:1px solid var(--border-subtle); }
.wh-dt-row td.num { text-align:right; }
.wh-dt-row:last-child td { border-bottom:0; }
.wh-dt-row:hover { background:var(--bg-sunken); }

.order-num { font-family:var(--font-mono); font-size:13px; color:var(--fg-1); font-weight:500; }
.method-chip { display:inline-flex; align-items:center; gap:5px; font-size:13px; color:var(--fg-2); }
.total-cell { font-weight:600; color:var(--fg-1); }
.date-cell { font-size:12px; color:var(--fg-3); white-space:nowrap; }

.wh-badge { display:inline-flex; align-items:center; gap:5px; padding:2px 8px; border-radius:var(--r-pill); font-size:11px; font-weight:500; }
.badge-dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
.badge-green  { background:var(--success-bg); color:var(--success-fg); }
.badge-amber  { background:#FEF3C7; color:#D97706; }
.badge-blue   { background:var(--halo-50); color:var(--halo-700); }
.badge-red    { background:var(--danger-bg); color:var(--danger-fg); }
.badge-slate  { background:var(--bg-sunken); color:var(--fg-2); }

.pagination-row { display:flex; align-items:center; justify-content:space-between; padding:12px 16px; border-top:1px solid var(--border-subtle); }
.results-label { font-size:13px; color:var(--fg-3); }

.loading-state { display:flex; align-items:center; justify-content:center; padding:48px; }
.empty-state { text-align:center; color:var(--fg-3); padding:48px 16px; }

/* Order detail dialog */
.order-meta-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:14px; background:var(--bg-sunken); border-radius:var(--r-md); padding:14px; }
.meta-label { font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:var(--fg-3); margin:0 0 4px; }
.meta-value { font-size:14px; font-weight:500; color:var(--fg-1); margin:0; }
.mono { font-family:var(--font-mono); }

.order-lines { border:1px solid var(--border-subtle); border-radius:var(--r-md); overflow:hidden; }
.lines-title { font-size:12px; font-weight:600; color:var(--fg-3); text-transform:uppercase; letter-spacing:0.06em; margin:0 0 0; padding:10px 14px; background:var(--bg-sunken); border-bottom:1px solid var(--border-subtle); }
.lines-table { width:100%; border-collapse:collapse; font-size:13px; }
.lines-table th { padding:8px 14px; text-align:left; font-size:11px; color:var(--fg-3); font-weight:600; text-transform:uppercase; letter-spacing:0.04em; }
.lines-table th.num { text-align:right; }
.lines-table td { padding:9px 14px; border-top:1px solid var(--border-subtle); color:var(--fg-2); }
.lines-table td.num { text-align:right; }

.order-totals { background:var(--bg-sunken); border-radius:var(--r-md); padding:14px; display:flex; flex-direction:column; gap:6px; }
.tot-row { display:flex; justify-content:space-between; font-size:13px; color:var(--fg-2); }
.discount-row { color:var(--success-fg); }
.tot-final { font-size:15px; font-weight:700; color:var(--fg-1); padding-top:8px; border-top:1px solid var(--border-subtle); margin-top:4px; }

/* Buttons */
.btn { font-family:var(--font-sans); font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:background var(--dur-base); line-height:1.2; }
.btn-sm { font-size:12px; padding:5px 9px; }
.btn-primary { background:var(--halo-500); color:#fff; border-color:var(--halo-500); }
.btn-primary:hover { background:var(--halo-700); }
.btn-ghost { background:transparent; color:var(--fg-2); border-color:var(--border-subtle); }
.btn-ghost:hover:not(:disabled) { background:var(--bg-sunken); color:var(--fg-1); }
.btn-ghost:disabled { opacity:0.5; cursor:not-allowed; }
</style>
