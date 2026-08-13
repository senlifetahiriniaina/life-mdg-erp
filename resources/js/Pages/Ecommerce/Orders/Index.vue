<template>
  <AppLayout>
    <Head title="Commandes E-commerce" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">E-commerce · Commandes</h1>
        <p class="wh-page-subtitle">{{ orders.total }} commande{{ orders.total !== 1 ? 's' : '' }}</p>
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
            <th>Boutique</th>
            <th>Client</th>
            <th>Statut</th>
            <th>Paiement</th>
            <th>Expédition</th>
            <th class="num">Total</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="order in orders.data"
            :key="order.id"
            class="wh-dt-row"
            @click="selectOrder(order)"
          >
            <td><span style="font-family:var(--font-mono);font-size:13px;color:var(--fg-1)">#{{ order.order_number }}</span></td>
            <td style="color:var(--fg-2)">{{ order.store?.name ?? '—' }}</td>
            <td style="color:var(--fg-2)">{{ order.customer?.name ?? '—' }}</td>
            <td>
              <span :class="orderStatusBadge(order.status)" class="wh-badge">
                <span class="wh-badge-dot" />
                {{ orderStatusLabel(order.status) }}
              </span>
            </td>
            <td>
              <span :class="paymentStatusBadge(order.payment_status)" class="wh-badge">
                <span class="wh-badge-dot" />
                {{ paymentStatusLabel(order.payment_status) }}
              </span>
            </td>
            <td>
              <span :class="fulfillmentStatusBadge(order.fulfillment_status)" class="wh-badge">
                <span class="wh-badge-dot" />
                {{ fulfillmentStatusLabel(order.fulfillment_status) }}
              </span>
            </td>
            <td class="num" style="font-weight:500;color:var(--fg-1)">{{ formatCurrency(order.grand_total) }}</td>
            <td style="color:var(--fg-3)">{{ formatDate(order.created_at) }}</td>
          </tr>
          <tr v-if="!orders.data.length">
            <td colspan="8" style="text-align:center;color:var(--fg-3);padding:32px 18px">Aucune commande trouvée.</td>
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

    <!-- Order detail sidebar -->
    <Drawer v-model:visible="showDetail" position="right" :style="{ width: '420px' }" header="Détail commande">
      <template v-if="selectedOrder">
        <div style="display:flex;flex-direction:column;gap:16px">
          <!-- Header info -->
          <div style="display:flex;align-items:flex-start;justify-content:space-between">
            <div>
              <p style="font-size:17px;font-weight:700;color:var(--fg-1);margin:0">#{{ selectedOrder.order_number }}</p>
              <p style="font-size:13px;color:var(--fg-3);margin:4px 0 0">{{ formatDate(selectedOrder.created_at) }}</p>
            </div>
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px">
              <span :class="orderStatusBadge(selectedOrder.status)" class="wh-badge">
                <span class="wh-badge-dot" />{{ orderStatusLabel(selectedOrder.status) }}
              </span>
              <span :class="paymentStatusBadge(selectedOrder.payment_status)" class="wh-badge">
                <span class="wh-badge-dot" />{{ paymentStatusLabel(selectedOrder.payment_status) }}
              </span>
            </div>
          </div>

          <!-- Customer -->
          <div class="drawer-section">
            <p class="drawer-section-label">Client</p>
            <p style="font-weight:500;color:var(--fg-1);margin:0">{{ selectedOrder.customer?.name ?? '—' }}</p>
            <p style="font-size:13px;color:var(--fg-3);margin:2px 0 0">{{ selectedOrder.customer?.email ?? '' }}</p>
          </div>

          <!-- Store -->
          <div class="drawer-section">
            <p class="drawer-section-label">Boutique</p>
            <p style="font-weight:500;color:var(--fg-1);margin:0">{{ selectedOrder.store?.name ?? '—' }}</p>
          </div>

          <!-- Totals -->
          <div class="drawer-section">
            <p class="drawer-section-label">Finances</p>
            <div style="display:flex;flex-direction:column;gap:6px">
              <div style="display:flex;justify-content:space-between;font-size:13px;color:var(--fg-2)">
                <span>Sous-total</span><span>{{ formatCurrency(selectedOrder.subtotal) }}</span>
              </div>
              <div v-if="selectedOrder.tax_amount" style="display:flex;justify-content:space-between;font-size:13px;color:var(--fg-2)">
                <span>Taxes</span><span>{{ formatCurrency(selectedOrder.tax_amount) }}</span>
              </div>
              <div v-if="selectedOrder.shipping_amount" style="display:flex;justify-content:space-between;font-size:13px;color:var(--fg-2)">
                <span>Livraison</span><span>{{ formatCurrency(selectedOrder.shipping_amount) }}</span>
              </div>
              <div v-if="selectedOrder.discount_amount" style="display:flex;justify-content:space-between;font-size:13px;color:var(--danger-fg)">
                <span>Remise</span><span>-{{ formatCurrency(selectedOrder.discount_amount) }}</span>
              </div>
              <Divider class="!my-1" />
              <div style="display:flex;justify-content:space-between;font-weight:700;color:var(--fg-1)">
                <span>Total</span><span>{{ formatCurrency(selectedOrder.grand_total) }}</span>
              </div>
            </div>
          </div>

          <!-- Shipping address -->
          <div v-if="selectedOrder.shipping_address" class="drawer-section">
            <p class="drawer-section-label">Adresse de livraison</p>
            <p style="font-size:13px;color:var(--fg-2);margin:0;white-space:pre-line">{{ formatAddress(selectedOrder.shipping_address) }}</p>
          </div>

          <!-- Notes -->
          <div v-if="selectedOrder.notes">
            <p class="drawer-section-label">Notes</p>
            <p style="font-size:13px;color:var(--fg-2);margin:0">{{ selectedOrder.notes }}</p>
          </div>
        </div>
      </template>
    </Drawer>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import { Paginator, Drawer, Divider } from 'primevue'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  orders: { type: Object, required: true },
  stats:  { type: Object, required: true },
})

const selectedOrder = ref(null)
const showDetail    = ref(false)

const kpiList = computed(() => [
  { label: 'Total',       value: props.stats.total },
  { label: 'En attente',  value: props.stats.pending },
  { label: 'Payées',      value: props.stats.paid },
  { label: 'Revenus',     value: formatCurrency(props.stats.revenue) },
])

const selectOrder = (order) => {
  selectedOrder.value = order
  showDetail.value = true
}

const orderStatusBadge = (s) => ({
  pending: 'wh-badge-amber', processing: 'wh-badge-blue', completed: 'wh-badge-green',
  cancelled: 'wh-badge-red', refunded: 'wh-badge-slate',
}[s] ?? 'wh-badge-slate')

const orderStatusLabel = (s) => ({
  pending: 'En attente', processing: 'En cours', completed: 'Terminée',
  cancelled: 'Annulée', refunded: 'Remboursée',
}[s] ?? s)

const paymentStatusBadge = (s) => ({
  unpaid: 'wh-badge-amber', paid: 'wh-badge-green', partially_refunded: 'wh-badge-blue',
  refunded: 'wh-badge-slate', failed: 'wh-badge-red',
}[s] ?? 'wh-badge-slate')

const paymentStatusLabel = (s) => ({
  unpaid: 'Non payée', paid: 'Payée', partially_refunded: 'Part. remboursée',
  refunded: 'Remboursée', failed: 'Échoué',
}[s] ?? s)

const fulfillmentStatusBadge = (s) => ({
  unfulfilled: 'wh-badge-amber', partially_fulfilled: 'wh-badge-blue',
  fulfilled: 'wh-badge-green', returned: 'wh-badge-slate',
}[s] ?? 'wh-badge-slate')

const fulfillmentStatusLabel = (s) => ({
  unfulfilled: 'Non expédiée', partially_fulfilled: 'Part. expédiée',
  fulfilled: 'Expédiée', returned: 'Retournée',
}[s] ?? s)

const formatCurrency = (v) =>
  new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'USD' }).format(Number(v ?? 0))

const formatDate = (v) =>
  v ? new Date(v).toLocaleDateString('fr-FR', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'

const formatAddress = (addr) => {
  if (!addr) return '—'
  if (typeof addr === 'string') return addr
  return [addr.line1, addr.line2, addr.city, addr.state, addr.zip, addr.country].filter(Boolean).join('\n')
}
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; gap:16px; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; gap:8px; }
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
.drawer-section { background:var(--bg-sunken); border:1px solid var(--border-subtle); border-radius:var(--r-md); padding:12px; }
.drawer-section-label { font-size:11px; font-weight:600; color:var(--fg-3); text-transform:uppercase; letter-spacing:0.06em; margin:0 0 6px; }
</style>
