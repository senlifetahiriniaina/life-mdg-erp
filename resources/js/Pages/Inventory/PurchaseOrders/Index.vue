<template>
  <AppLayout>
    <Head title="Purchase Orders" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Purchase Orders</h1>
        <p class="wh-page-subtitle">{{ orders.total }} order{{ orders.total !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="showCreate = true">
          <i class="pi pi-plus" style="font-size:13px" /> New PO
        </button>
      </div>
    </div>

    <AIAssistantPanel v-if="guidance" :guidance="guidance" />

    <!-- Filters -->
    <div class="wh-panel" style="margin-bottom:16px">
      <div style="padding:12px 16px;display:flex;flex-wrap:wrap;gap:10px;align-items:center">
        <Select v-model="statusFilter" :options="statusOptions" option-label="label" option-value="value" placeholder="Status" show-clear style="width:180px" @change="reload" />
        <Select v-model="supplierFilter" :options="suppliers" option-label="name" option-value="id" placeholder="Supplier" show-clear style="width:200px" @change="reload" />
      </div>
    </div>

    <!-- Table -->
    <div class="wh-panel">
      <table class="wh-dt">
        <thead>
          <tr>
            <th>PO #</th>
            <th>Supplier</th>
            <th>Warehouse</th>
            <th>Status</th>
            <th>Expected</th>
            <th class="num">Lines</th>
            <th class="num">Total</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="o in orders.data" :key="o.id" class="wh-dt-row">
            <td>
              <span style="font-family:var(--font-mono);font-weight:600;color:var(--halo-600)">{{ o.reference }}</span>
            </td>
            <td>{{ o.supplier?.name }}</td>
            <td>{{ o.warehouse?.name || '—' }}</td>
            <td>
              <span :class="['wh-badge', statusBadgeClass(o.status)]">{{ o.status }}</span>
            </td>
            <td>{{ o.expected_at ? formatDate(o.expected_at) : '—' }}</td>
            <td class="num">{{ o.items_count }}</td>
            <td class="num">{{ formatCurrency(o.grand_total, o.currency) }}</td>
            <td>
              <div style="display:flex;gap:6px;justify-content:flex-end">
                <button v-if="['sent','confirmed','partial'].includes(o.status)" class="btn btn-sm btn-secondary" @click="openReceiptModal(o)">
                  <i class="pi pi-inbox" style="font-size:12px" /> Receive
                </button>
                <button v-if="o.status === 'draft'" class="btn btn-sm btn-primary" @click="sendPO(o)">
                  <i class="pi pi-send" style="font-size:12px" /> Send
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!orders.data.length">
            <td colspan="8" style="text-align:center;padding:40px;color:var(--fg-3)">No purchase orders found.</td>
          </tr>
        </tbody>
      </table>

      <div v-if="orders.last_page > 1" style="padding:12px 16px;display:flex;justify-content:center">
        <Paginator :rows="orders.per_page" :total-records="orders.total" :first="(orders.current_page - 1) * orders.per_page" @page="onPage" />
      </div>
    </div>

    <!-- Receive Modal -->
    <Dialog v-model:visible="showReceiptModal" header="Receive Items" modal style="width:600px">
      <div v-if="receivingPO">
        <p style="color:var(--fg-2);margin-bottom:16px">PO: <strong>{{ receivingPO.reference }}</strong></p>
        <table class="wh-dt" style="margin-bottom:0">
          <thead>
            <tr>
              <th>Product</th>
              <th class="num">Ordered</th>
              <th class="num">Received</th>
              <th class="num">Receiving Now</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in receiptItems" :key="item.id">
              <td>{{ item.product_name }}</td>
              <td class="num">{{ item.quantity_ordered }}</td>
              <td class="num">{{ item.quantity_received }}</td>
              <td class="num" style="width:120px">
                <InputNumber v-model="item.qty" :min="0" :max="item.quantity_ordered - item.quantity_received" :min-fraction-digits="0" :max-fraction-digits="4" style="width:100px" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <template #footer>
        <button class="btn btn-secondary" @click="showReceiptModal = false">{{ $t('common.cancel') }}</button>
        <button class="btn btn-primary" :disabled="receiving" @click="confirmReceipt">
          {{ receiving ? 'Processing...' : 'Confirm Receipt' }}
        </button>
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import { Select, Dialog, InputNumber, Paginator } from 'primevue'
import axios from 'axios'

const { guidance } = useAiAssistant('Inventory', 'manage_suppliers')

interface PurchaseOrder {
  id: number
  reference: string
  status: string
  currency: string
  grand_total: string
  expected_at: string | null
  items_count: number
  supplier: { id: number; name: string } | null
  warehouse: { id: number; name: string } | null
}

interface ReceiptItem {
  id: number
  product_name: string
  quantity_ordered: number
  quantity_received: number
  qty: number
}

const props = defineProps<{
  orders: { data: PurchaseOrder[]; total: number; per_page: number; current_page: number; last_page: number }
  suppliers: { id: number; name: string }[]
  warehouses: { id: number; name: string }[]
  filters: { status?: string; supplier_id?: string }
}>()

const statusFilter = ref(props.filters.status || null)
const supplierFilter = ref(props.filters.supplier_id ? Number(props.filters.supplier_id) : null)
const showCreate = ref(false)
const showReceiptModal = ref(false)
const receivingPO = ref<PurchaseOrder | null>(null)
const receiptItems = ref<ReceiptItem[]>([])
const receiving = ref(false)

const statusOptions = [
  { label: 'Draft', value: 'draft' },
  { label: 'Sent', value: 'sent' },
  { label: 'Confirmed', value: 'confirmed' },
  { label: 'Partially Received', value: 'partial' },
  { label: 'Received', value: 'received' },
  { label: 'Cancelled', value: 'cancelled' },
]

function statusBadgeClass(status: string): string {
  const map: Record<string, string> = {
    draft: 'badge-secondary',
    sent: 'badge-info',
    confirmed: 'badge-primary',
    partial: 'badge-warning',
    received: 'badge-success',
    cancelled: 'badge-danger',
  }
  return map[status] || 'badge-secondary'
}

function formatDate(d: string): string {
  return new Date(d).toLocaleDateString()
}

function formatCurrency(amount: string, currency: string): string {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency }).format(Number(amount))
}

function reload() {
  router.get(route('inventory.purchase_orders.index'), {
    status: statusFilter.value || undefined,
    supplier_id: supplierFilter.value || undefined,
  }, { preserveState: true, replace: true })
}

async function sendPO(po: PurchaseOrder) {
  if (!confirm(`Send PO ${po.reference}?`)) return
  await axios.post(`/api/v1/inventory/purchase-orders/${po.id}/send`)
  router.reload()
}

async function openReceiptModal(po: PurchaseOrder) {
  const { data } = await axios.get(`/api/v1/inventory/purchase-orders/${po.id}`)
  receivingPO.value = po
  receiptItems.value = (data.items || []).map((item: ReceiptItem) => ({
    ...item,
    qty: Math.max(0, Number(item.quantity_ordered) - Number(item.quantity_received)),
  }))
  showReceiptModal.value = true
}

async function confirmReceipt() {
  if (!receivingPO.value) return
  receiving.value = true
  try {
    await axios.post(`/api/v1/inventory/purchase-orders/${receivingPO.value.id}/receive`, {
      items: receiptItems.value.map(i => ({ id: i.id, qty: i.qty })),
    })
    showReceiptModal.value = false
    router.reload()
  } finally {
    receiving.value = false
  }
}

function onPage(event: { page: number }) {
  router.get(route('inventory.purchase_orders.index'), {
    ...props.filters,
    page: event.page + 1,
  }, { preserveState: true })
}
</script>
