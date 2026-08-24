<template>
  <AppLayout>
    <Head :title="$t('inventory.products.title')" />

    <GuidedTour tour-id="inventory-products" :steps="inventoryTourSteps" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">{{ $t('inventory.products.title') }}</h1>
        <p class="wh-page-subtitle">{{ products.total }} produit{{ products.total !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-secondary" :disabled="syncing" @click="syncToEcommerce">
          <i class="pi pi-sync" style="font-size:13px" :class="{ 'pi-spin': syncing }" />
          {{ syncing ? 'Syncing...' : 'Sync to Ecommerce' }}
        </button>
        <span v-if="lastSyncAt" style="font-size:11px;color:var(--fg-3);align-self:center">Last sync: {{ formatDate(lastSyncAt) }}</span>
        <button class="btn btn-secondary" @click="router.visit('/inventory/stock/import')"><i class="pi pi-upload" style="font-size:13px" /> {{ $t('common.import_csv') }}</button>
        <button class="btn btn-primary" @click="router.visit('/products/create')"><i class="pi pi-plus" style="font-size:13px" /> {{ $t('inventory.products.new') }}</button>
      </div>
    </div>

    <AIAssistantPanel v-if="guidance" :guidance="guidance" />

    <div style="display:flex;gap:16px;margin-bottom:16px;font-size:12px">
      <Link href="/inventory/stock/movements" class="wh-link">Mouvements de stock</Link>
      <Link href="/inventory/reorder-automation" class="wh-link">Réapprovisionnement</Link>
      <Link href="/inventory/demand-forecast" class="wh-link">Prévision de demande</Link>
      <Link href="/inventory/marketplace-sync" class="wh-link">Synchronisation e-commerce</Link>
    </div>

    <!-- Filters -->
    <div class="wh-panel" style="margin-bottom:16px;overflow:visible">
      <div style="padding:12px 16px;display:flex;flex-wrap:wrap;gap:10px;align-items:center">
        <div style="position:relative;flex:1;min-width:200px;max-width:320px">
          <i class="pi pi-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--fg-4);font-size:13px;pointer-events:none" />
          <input v-model="search" :placeholder="$t('inventory.products.search_placeholder')" class="wh-filter-input" @input="onSearch" />
        </div>
        <Select v-model="typeFilter" :options="typeOptions" option-label="label" option-value="value" placeholder="Type" show-clear style="width:160px" />
      </div>
    </div>

    <!-- Table -->
    <div class="wh-panel" style="position:relative">
      <table class="wh-dt">
        <thead>
          <tr>
            <th>{{ $t('inventory.product') }}</th>
            <th>{{ $t('common.category') }}</th>
            <th>{{ $t('common.type') }}</th>
            <th class="num">{{ $t('common.price') }}</th>
            <th class="num">{{ $t('inventory.stock') }}</th>
            <th>{{ $t('common.status') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in products.data" :key="p.id" class="wh-dt-row">
            <td>
              <div style="display:flex;align-items:center;gap:10px">
                <div class="prod-ic"><i class="pi pi-box" style="font-size:13px;color:var(--halo-600)" /></div>
                <div>
                  <div style="font-weight:500;color:var(--fg-1)">{{ p.name }}</div>
                  <div style="font-family:var(--font-mono);font-size:11px;color:var(--fg-3)">{{ p.sku }}</div>
                </div>
              </div>
            </td>
            <td style="color:var(--fg-2)">{{ p.category?.name ?? '—' }}</td>
            <td><span :class="['wh-badge', typeClass(p.type)]">{{ typeLabel(p.type) }}</span></td>
            <td class="num" style="font-variant-numeric:tabular-nums;font-weight:500">{{ formatMoney(p.sale_price) }}</td>
            <td class="num" :class="isLowStock(p) ? 'text-danger' : ''" style="font-variant-numeric:tabular-nums;font-weight:500">
              {{ p.quantity_on_hand ?? 0 }} {{ p.unit?.symbol ?? '' }}
              <i v-if="isLowStock(p)" class="pi pi-exclamation-triangle" style="font-size:11px;margin-left:4px" />
            </td>
            <td>
              <span :class="['wh-badge', p.active ? 'wh-badge-green' : 'wh-badge-slate']">
                <span class="wh-badge-dot" />{{ p.active ? $t('common.active') : $t('common.inactive') }}
              </span>
            </td>
          </tr>
          <tr v-if="products.data.length === 0">
            <td colspan="6" style="text-align:center;padding:48px;color:var(--fg-3);font-size:14px">{{ $t('common.no_results') }}</td>
          </tr>
        </tbody>
      </table>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-top:1px solid var(--border-subtle)">
        <span style="font-size:13px;color:var(--fg-3)">{{ products.total }} résultat{{ products.total !== 1 ? 's' : '' }}</span>
        <Paginator :rows="products.per_page" :total-records="products.total" :first="(products.current_page - 1) * products.per_page" />
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import Select from 'primevue/select'
import Paginator from 'primevue/paginator'
import AppLayout from '@/Layouts/AppLayout.vue'
import GuidedTour from '@/Components/UI/GuidedTour.vue'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import { useHelpStore } from '@/stores/help'
import { useI18n } from 'vue-i18n'
import axios from 'axios'

const { guidance } = useAiAssistant('Inventory', 'view_catalog')

const help = useHelpStore()
const { t } = useI18n()

const inventoryTourSteps = [
  { tag: 'Inventory', icon: 'pi pi-box',         title: 'Product Catalogue',    description: 'Every product your business sells or uses is listed here. Click a product to see stock levels, variants and movement history.' },
  { tag: 'Inventory', icon: 'pi pi-filter',       title: 'Filter by Category',   description: 'Narrow the list by category, supplier or stock status. Use the search bar for quick lookups by SKU or barcode.' },
  { tag: 'Inventory', icon: 'pi pi-exclamation-triangle', title: 'Low Stock Alerts', description: 'Products highlighted in red are below their minimum stock level. Click to trigger a reorder or adjust the threshold.' },
  { tag: 'Inventory', icon: 'pi pi-warehouse',    title: 'Multi-Warehouse',      description: 'Stock is tracked per warehouse. The quantity shown is the total across all locations — open a product to see the breakdown.' },
  { tag: 'Inventory', icon: 'pi pi-upload',       title: 'Bulk Import',          description: 'Use "Import CSV" to upload hundreds of products at once. Download the template first to ensure correct column mapping.' },
]

const props = defineProps({
  products: { type: Object, required: true },
  filters:  { type: Object, default: () => ({}) },
})

const search     = ref(props.filters.search ?? '')
const typeFilter = ref(props.filters.type ?? null)
const syncing    = ref(false)
const lastSyncAt = ref<string | null>(null)

async function fetchSyncStatus() {
  try {
    const { data } = await axios.get('/api/v1/inventory/sync/ecommerce/status')
    lastSyncAt.value = data.last_sync_at
  } catch {}
}

async function syncToEcommerce() {
  syncing.value = true
  try {
    await axios.post('/api/v1/inventory/sync/ecommerce/all')
    await fetchSyncStatus()
  } finally {
    syncing.value = false
  }
}

function formatDate(d: string): string {
  return new Date(d).toLocaleString()
}

let echoChannel: any = null

onMounted(() => {
  fetchSyncStatus()
  if (window.Echo) {
    echoChannel = window.Echo.private('inventory')
      .listen('.LowStockAlert', () => {
        router.reload({ only: ['products'] })
      })
  }
})

onUnmounted(() => {
  if (echoChannel) window.Echo?.leaveChannel('private-inventory')
})

const typeOptions = [
  { label: 'Storable',   value: 'storable' },
  { label: 'Consumable', value: 'consumable' },
  { label: 'Service',    value: 'service' },
]

interface ProductRow {
  quantity_on_hand?: number | null
  min_stock_qty?: number | null
  unit?: { symbol?: string } | null
}

const TYPE_CLASSES: Record<string, string> = { storable: 'wh-badge-blue', consumable: 'wh-badge-amber', service: 'wh-badge-green' }
const TYPE_LABELS: Record<string, string> = { storable: 'Storable', consumable: 'Consumable', service: 'Service' }

const typeClass  = (type: string) => TYPE_CLASSES[type] ?? 'wh-badge-slate'
const typeLabel  = (type: string) => TYPE_LABELS[type] ?? type
const isLowStock = (p: ProductRow) => p.quantity_on_hand != null && p.min_stock_qty != null && p.quantity_on_hand <= p.min_stock_qty
const formatMoney = (v: number | null | undefined) => v != null ? Number(v).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €' : '—'

let searchTimer: ReturnType<typeof setTimeout> | null = null
const onSearch = () => { if (searchTimer) clearTimeout(searchTimer); searchTimer = setTimeout(() => {/* TODO: fetch */}, 400) }
</script>

<style scoped>
.wh-link { color:var(--fg-link); text-decoration:none; }
.wh-link:hover { text-decoration:underline; text-underline-offset:2px; }
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; gap:16px; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; gap:8px; }
.btn { font-family:var(--font-sans); font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:background var(--dur-base) var(--ease-out); line-height:1.2; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover { background:var(--halo-700); }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover { background:var(--bg-sunken); }
.wh-filter-input { width:100%; padding:7px 12px 7px 32px; border-radius:var(--r-md); border:1px solid var(--border-subtle); background:var(--bg-sunken); font-family:var(--font-sans); font-size:13px; color:var(--fg-1); outline:none; transition:border-color var(--dur-base), box-shadow var(--dur-base); }
.wh-filter-input:focus { border-color:var(--halo-500); background:var(--bg-canvas); box-shadow:0 0 0 3px rgba(46,91,232,0.12); }
.wh-filter-input::placeholder { color:var(--fg-4); }
.prod-ic { width:32px; height:32px; border-radius:var(--r-sm); background:var(--halo-50); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.wh-dt { width:100%; border-collapse:collapse; font-size:14px; }
.wh-dt thead th { text-align:left; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:var(--fg-3); font-weight:600; padding:10px 18px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sunken); }
.wh-dt thead th.num { text-align:right; }
.wh-dt-row td { padding:11px 18px; border-bottom:1px solid var(--border-subtle); }
.wh-dt-row td.num { text-align:right; }
.wh-dt-row:last-child td { border-bottom:0; }
.wh-dt-row { cursor:pointer; transition:background var(--dur-fast); }
.wh-dt-row:hover { background:var(--bg-sunken); }
.text-danger { color:var(--danger-fg); font-weight:600; }
.wh-badge { display:inline-flex; align-items:center; gap:5px; padding:2px 8px; border-radius:var(--r-pill); font-size:11px; font-weight:500; }
.wh-badge-dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
.wh-badge-green  { background:var(--success-bg); color:var(--success-fg); }
.wh-badge-blue   { background:var(--halo-50); color:var(--halo-700); }
.wh-badge-amber  { background:var(--warn-bg); color:var(--warn-fg); }
.wh-badge-slate  { background:var(--bg-sunken); color:var(--fg-2); }
</style>
