<template>
  <AppLayout>
    <Head :title="$t('ecommerce.products.title')" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">{{ $t('ecommerce.products.title') }}</h1>
        <p class="wh-page-subtitle">{{ products.total }} product{{ products.total !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary">
          <i class="pi pi-plus" style="font-size:13px" /> {{ $t('ecommerce.products.new') }}
        </button>
      </div>
    </div>

    <!-- KPI row -->
    <div class="wh-kpi-grid" style="margin-bottom:16px">
      <div
        v-for="s in kpiList"
        :key="s.label"
        class="wh-kpi"
        :class="{ 'wh-kpi-danger': s.danger }"
      >
        <div class="wh-kpi-label">{{ s.label }}</div>
        <div class="wh-kpi-num font-display">{{ s.value }}</div>
      </div>
    </div>

    <!-- Filter bar -->
    <div class="filter-bar" style="margin-bottom:12px">
      <span class="p-input-icon-left search-wrap">
        <i class="pi pi-search" />
        <InputText
          v-model="filterSearch"
          :placeholder="$t('ecommerce.products.search_placeholder')"
          style="width:280px"
          @keydown.enter="applyFilters"
        />
      </span>
      <Select
        v-model="filterStatus"
        :options="statusOptions"
        option-label="label"
        option-value="value"
        :placeholder="$t('common.status')"
        style="min-width:160px"
        @change="applyFilters"
      />
      <button v-if="filterSearch || filterStatus" class="btn btn-ghost" @click="resetFilters">
        <i class="pi pi-times" style="font-size:12px" /> {{ $t('common.reset_filters') }}
      </button>
    </div>

    <!-- Products table -->
    <div class="wh-panel">
      <table class="wh-dt">
        <thead>
          <tr>
            <th style="width:60px">Image</th>
            <th>{{ $t('common.name') }}</th>
            <th>{{ $t('common.category') }}</th>
            <th class="num">{{ $t('common.price') }}</th>
            <th class="num">Stock</th>
            <th>Store</th>
            <th>{{ $t('common.status') }}</th>
            <th>{{ $t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="product in products.data"
            :key="product.id"
            class="wh-dt-row"
          >
            <!-- Image -->
            <td>
              <div class="product-thumb">
                <img
                  v-if="product.image"
                  :src="product.image"
                  :alt="product.name"
                  class="thumb-img"
                />
                <div v-else class="thumb-placeholder">
                  <i class="pi pi-image" style="font-size:18px;color:var(--fg-4)" />
                </div>
              </div>
            </td>
            <!-- Name + SKU -->
            <td>
              <div style="font-weight:500;color:var(--fg-1)">{{ product.name }}</div>
              <div v-if="product.sku" style="font-size:12px;color:var(--fg-3);font-family:var(--font-mono)">{{ product.sku }}</div>
            </td>
            <!-- Category -->
            <td style="color:var(--fg-2)">{{ product.category?.name ?? '—' }}</td>
            <!-- Price -->
            <td class="num">
              <div style="font-weight:500;color:var(--fg-1)">{{ formatCurrency(product.price) }}</div>
              <div
                v-if="product.compare_price && Number(product.compare_price) > Number(product.price)"
                style="font-size:12px;color:var(--fg-3);text-decoration:line-through"
              >
                {{ formatCurrency(product.compare_price) }}
              </div>
            </td>
            <!-- Stock -->
            <td class="num">
              <span :class="stockClass(product.stock_quantity)" style="font-weight:500">
                {{ product.stock_quantity }}
              </span>
              <div style="font-size:11px;color:var(--fg-3)">
                {{ product.stock_quantity < 10 ? $t('ecommerce.products.low_stock') : $t('ecommerce.products.in_stock') }}
              </div>
            </td>
            <!-- Store -->
            <td style="color:var(--fg-2)">{{ product.store?.name ?? '—' }}</td>
            <!-- Active badge -->
            <td>
              <span :class="product.is_active ? 'wh-badge-green' : 'wh-badge-slate'" class="wh-badge">
                <span class="wh-badge-dot" />
                {{ product.is_active ? $t('common.active') : $t('common.inactive') }}
              </span>
            </td>
            <!-- Actions -->
            <td>
              <div style="display:flex;gap:6px">
                <button class="btn btn-ghost btn-sm" @click="viewProduct(product)">
                  <i class="pi pi-eye" style="font-size:12px" /> {{ $t('common.view') }}
                </button>
                <button class="btn btn-ghost btn-sm" @click="editProduct(product)">
                  <i class="pi pi-pencil" style="font-size:12px" /> {{ $t('common.edit') }}
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!products.data.length">
            <td colspan="8" class="empty-state">
              <i class="pi pi-inbox" style="font-size:28px;color:var(--fg-4);display:block;margin-bottom:8px" />
              No products found.
            </td>
          </tr>
        </tbody>
      </table>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-top:1px solid var(--border-subtle)">
        <span style="font-size:13px;color:var(--fg-3)">{{ products.total }} result{{ products.total !== 1 ? 's' : '' }}</span>
        <Paginator
          :rows="products.per_page"
          :total-records="products.total"
          :first="(products.current_page - 1) * products.per_page"
          @page="onPage"
        />
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Head, router } from '@inertiajs/vue3'
import { Paginator, Select, InputText } from 'primevue'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  products: { type: Object, required: true },
  stats:    { type: Object, required: true },
  filters:  { type: Object, default: () => ({}) },
})

const filterSearch = ref(props.filters.search ?? '')
const filterStatus = ref(props.filters.status ?? '')

const statusOptions = [
  { label: 'All',      value: '' },
  { label: t('common.active'),   value: 'active' },
  { label: t('common.inactive'), value: 'inactive' },
]

const kpiList = computed(() => [
  { label: 'Total',     value: props.stats.total,     danger: false },
  { label: t('common.active'),    value: props.stats.active,    danger: false },
  { label: t('common.inactive'),  value: props.stats.inactive,  danger: false },
  { label: 'Low Stock', value: props.stats.low_stock, danger: props.stats.low_stock > 0 },
])

const stockClass = (qty) => qty < 10 ? 'stock-low' : 'stock-ok'

const formatCurrency = (value) =>
  new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(value ?? 0))

const buildQuery = () => {
  const q = {}
  if (filterSearch.value) q.search = filterSearch.value
  if (filterStatus.value) q.status = filterStatus.value
  return q
}

const applyFilters = () => {
  router.get('/ecommerce/products', buildQuery(), { preserveState: true, replace: true })
}

const resetFilters = () => {
  filterSearch.value = ''
  filterStatus.value = ''
  router.get('/ecommerce/products', {}, { preserveState: true, replace: true })
}

const onPage = (event) => {
  const page = Math.floor(event.first / event.rows) + 1
  router.get('/ecommerce/products', { ...buildQuery(), page }, { preserveState: true })
}

const viewProduct = (product) => {
  router.visit(`/ecommerce/products/${product.id}`)
}

const editProduct = (product) => {
  router.visit(`/ecommerce/products/${product.id}/edit`)
}
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; gap:16px; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; gap:8px; }
.filter-bar { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.search-wrap { position:relative; display:inline-flex; align-items:center; }
.search-wrap .pi { position:absolute; left:10px; color:var(--fg-3); font-size:14px; z-index:1; pointer-events:none; }
.btn { font-family:var(--font-sans); font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:background var(--dur-base) var(--ease-out); line-height:1.2; text-decoration:none; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover { background:var(--halo-700); }
.btn-ghost { background:transparent; color:var(--fg-2); border-color:var(--border-subtle); }
.btn-ghost:hover { background:var(--bg-sunken); color:var(--fg-1); }
.btn-sm { font-size:12px; padding:4px 10px; }
.wh-kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; }
@media (max-width:640px) { .wh-kpi-grid { grid-template-columns:repeat(2,1fr); } }
.wh-kpi { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); padding:16px 20px; }
.wh-kpi-danger { border-color:var(--danger-border, var(--danger-fg)); }
.wh-kpi-label { font-size:12px; font-weight:500; color:var(--fg-3); letter-spacing:0.04em; text-transform:uppercase; margin-bottom:6px; }
.wh-kpi-num { font-size:26px; font-weight:700; color:var(--fg-1); line-height:1; }
.wh-kpi-danger .wh-kpi-num { color:var(--danger-fg); }
.wh-panel { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); overflow:hidden; }
.wh-dt { width:100%; border-collapse:collapse; font-size:14px; }
.wh-dt thead th { text-align:left; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:var(--fg-3); font-weight:600; padding:10px 18px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sunken); }
.wh-dt thead th.num { text-align:right; }
.wh-dt-row td { padding:11px 18px; border-bottom:1px solid var(--border-subtle); vertical-align:middle; }
.wh-dt-row td.num { text-align:right; }
.wh-dt-row:last-child td { border-bottom:0; }
.wh-dt-row { transition:background var(--dur-fast); }
.wh-dt-row:hover { background:var(--bg-sunken); }
.product-thumb { width:40px; height:40px; border-radius:var(--r-md); overflow:hidden; border:1px solid var(--border-subtle); flex-shrink:0; }
.thumb-img { width:40px; height:40px; object-fit:cover; display:block; }
.thumb-placeholder { width:40px; height:40px; display:flex; align-items:center; justify-content:center; background:var(--bg-sunken); }
.wh-badge { display:inline-flex; align-items:center; gap:5px; padding:2px 8px; border-radius:var(--r-pill); font-size:11px; font-weight:500; }
.wh-badge-dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
.wh-badge-green  { background:var(--success-bg); color:var(--success-fg); }
.wh-badge-slate  { background:var(--bg-sunken); color:var(--fg-2); }
.stock-ok  { color:var(--success-fg); }
.stock-low { color:var(--danger-fg); }
.empty-state { text-align:center; color:var(--fg-3); padding:48px 18px; }
</style>
