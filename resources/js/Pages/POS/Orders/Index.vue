<template>
  <AppLayout>
    <Head title="Commandes POS" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">POS · Commandes</h1>
        <p class="wh-page-subtitle">{{ orders.total }} commande{{ orders.total !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="visitCashier">
          <i class="pi pi-desktop" style="font-size:13px" /> Ouvrir la caisse
        </button>
      </div>
    </div>

    <!-- KPI row -->
    <div class="wh-kpi-grid" style="margin-bottom:16px">
      <div class="wh-kpi" v-for="s in kpiList" :key="s.label">
        <div class="wh-kpi-label">{{ s.label }}</div>
        <div class="wh-kpi-num font-display">{{ s.value }}</div>
      </div>
    </div>

    <!-- Orders table -->
    <div class="wh-panel">
      <table class="wh-dt">
        <thead>
          <tr>
            <th>N° commande</th>
            <th>Statut</th>
            <th>Mode de paiement</th>
            <th class="num">Total</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="order in orders.data" :key="order.id" class="wh-dt-row">
            <td><span style="font-family:var(--font-mono);font-size:13px;color:var(--fg-1)">#{{ order.order_number }}</span></td>
            <td>
              <span :class="statusBadge(order.status)" class="wh-badge">
                <span class="wh-badge-dot" />
                {{ statusLabel(order.status) }}
              </span>
            </td>
            <td style="color:var(--fg-2);text-transform:capitalize">{{ order.payment_method }}</td>
            <td class="num" style="font-weight:500;color:var(--fg-1)">{{ formatCurrency(order.total) }}</td>
            <td style="color:var(--fg-3)">{{ formatDate(order.created_at) }}</td>
          </tr>
          <tr v-if="!orders.data.length">
            <td colspan="5" style="text-align:center;color:var(--fg-3);padding:32px 18px">Aucune commande trouvée.</td>
          </tr>
        </tbody>
      </table>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-top:1px solid var(--border-subtle)">
        <span style="font-size:13px;color:var(--fg-3)">{{ orders.total }} résultat{{ orders.total !== 1 ? 's' : '' }}</span>
        <Paginator
          :rows="orders.per_page"
          :total-records="orders.total"
          :first="(orders.current_page - 1) * orders.per_page"
        />
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { Paginator } from 'primevue'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  orders: { type: Object, required: true },
  stats:  { type: Object, required: true },
})

const kpiList = computed(() => [
  { label: 'Total',       value: props.stats.total },
  { label: 'En attente',  value: props.stats.pending },
  { label: 'Payées',      value: props.stats.paid },
  { label: 'Revenus',     value: formatCurrency(props.stats.revenue) },
])

const visitCashier = () => router.visit('/pos/cashier')

const statusBadge = (status) => ({
  pending:   'wh-badge-amber',
  paid:      'wh-badge-green',
  refunded:  'wh-badge-blue',
  cancelled: 'wh-badge-red',
}[status] ?? 'wh-badge-slate')

const statusLabel = (status) => ({
  pending:   'En attente',
  paid:      'Payée',
  refunded:  'Remboursée',
  cancelled: 'Annulée',
}[status] ?? status)

const formatCurrency = (value) =>
  new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'USD' }).format(Number(value ?? 0))

const formatDate = (value) =>
  value ? new Date(value).toLocaleDateString('fr-FR', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; gap:16px; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; gap:8px; }
.btn { font-family:var(--font-sans); font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:background var(--dur-base) var(--ease-out); line-height:1.2; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover { background:var(--halo-700); }
.wh-kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; }
@media (max-width:640px) { .wh-kpi-grid { grid-template-columns:repeat(2,1fr); } }
.wh-kpi { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); padding:16px 20px; }
.wh-kpi-label { font-size:12px; font-weight:500; color:var(--fg-3); letter-spacing:0.04em; text-transform:uppercase; margin-bottom:6px; }
.wh-kpi-num { font-size:26px; font-weight:700; color:var(--fg-1); line-height:1; }
.wh-panel { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); overflow:hidden; }
.wh-dt { width:100%; border-collapse:collapse; font-size:14px; }
.wh-dt thead th { text-align:left; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:var(--fg-3); font-weight:600; padding:10px 18px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sunken); }
.wh-dt thead th.num { text-align:right; }
.wh-dt-row td { padding:11px 18px; border-bottom:1px solid var(--border-subtle); }
.wh-dt-row td.num { text-align:right; }
.wh-dt-row:last-child td { border-bottom:0; }
.wh-dt-row { cursor:pointer; transition:background var(--dur-fast); }
.wh-dt-row:hover { background:var(--bg-sunken); }
.wh-badge { display:inline-flex; align-items:center; gap:5px; padding:2px 8px; border-radius:var(--r-pill); font-size:11px; font-weight:500; }
.wh-badge-dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
.wh-badge-green  { background:var(--success-bg); color:var(--success-fg); }
.wh-badge-blue   { background:var(--halo-50); color:var(--halo-700); }
.wh-badge-amber  { background:var(--warn-bg); color:var(--warn-fg); }
.wh-badge-red    { background:var(--danger-bg); color:var(--danger-fg); }
.wh-badge-slate  { background:var(--bg-sunken); color:var(--fg-2); }
</style>
