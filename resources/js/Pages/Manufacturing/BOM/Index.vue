<template>
  <AppLayout>
    <Head :title="$t('manufacturing.bom.title')" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">{{ $t('manufacturing.bom.title') }}</h1>
        <p class="wh-page-subtitle">{{ boms.total }} {{ $t('common.total').toLowerCase() }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary">
          <i class="pi pi-plus" style="font-size:13px" /> {{ $t('manufacturing.bom.new') }}
        </button>
      </div>
    </div>

    <!-- Filter bar -->
    <div class="filter-bar">
      <InputText
        v-model="filterSearch"
        :placeholder="$t('manufacturing.bom.search_placeholder')"
        class="filter-input"
        @keyup.enter="applyFilters"
      />
      <button class="btn btn-primary" @click="applyFilters">
        <i class="pi pi-search" style="font-size:13px" />
      </button>
      <button class="btn btn-secondary" @click="resetFilters">
        <i class="pi pi-filter-slash" style="font-size:13px" /> {{ $t('common.reset_filters') }}
      </button>
    </div>

    <!-- Table -->
    <div class="wh-panel">
      <table class="wh-dt">
        <thead>
          <tr>
            <th>{{ $t('common.name') }}</th>
            <th>{{ $t('manufacturing.bom.reference') }}</th>
            <th>{{ $t('common.type') }}</th>
            <th class="num">{{ $t('manufacturing.bom.base_qty') }}</th>
            <th class="num">{{ $t('manufacturing.bom.components') }}</th>
            <th>{{ $t('common.status') }}</th>
            <th>{{ $t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="bom in boms.data" :key="bom.id" class="wh-dt-row">
            <td style="font-weight:500;color:var(--fg-1)">{{ bom.product_name }}</td>
            <td>
              <span style="font-family:var(--font-mono);font-size:13px;color:var(--fg-2)">
                {{ bom.reference }}
              </span>
            </td>
            <td style="color:var(--fg-2)">{{ bom.type }}</td>
            <td class="num" style="font-variant-numeric:tabular-nums;color:var(--fg-2)">{{ bom.quantity }}</td>
            <td class="num">
              <span class="wh-badge wh-badge-blue">{{ bom.components_count }}</span>
            </td>
            <td>
              <span :class="['wh-badge', bom.is_active ? 'wh-badge-green' : 'wh-badge-slate']">
                <span class="wh-badge-dot" />
                {{ bom.is_active ? $t('common.active') : $t('common.inactive') }}
              </span>
            </td>
            <td>
              <div class="row-actions">
                <button class="action-btn" :title="$t('common.view')">
                  <i class="pi pi-eye" style="font-size:13px" />
                </button>
                <button class="action-btn" :title="$t('common.edit')">
                  <i class="pi pi-pencil" style="font-size:13px" />
                </button>
                <button class="action-btn action-btn-danger" :title="$t('common.delete')">
                  <i class="pi pi-trash" style="font-size:13px" />
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="boms.data.length === 0">
            <td colspan="7" class="empty-state">
              <i class="pi pi-inbox" style="font-size:32px;color:var(--fg-3);display:block;margin-bottom:8px" />
              {{ $t('common.no_results') }}
            </td>
          </tr>
        </tbody>
      </table>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-top:1px solid var(--border-subtle)">
        <span style="font-size:13px;color:var(--fg-3)">{{ boms.total }} {{ $t('common.total').toLowerCase() }}</span>
        <Paginator
          :rows="boms.per_page"
          :total-records="boms.total"
          :first="(boms.current_page - 1) * boms.per_page"
          @page="onPage"
        />
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { Paginator, InputText } from 'primevue'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  boms:    { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
})

const filterSearch = ref(props.filters.search ?? '')

const applyFilters = () => {
  router.get(
    route('manufacturing.bom.index'),
    { search: filterSearch.value || undefined },
    { preserveState: true, replace: true },
  )
}

const resetFilters = () => {
  filterSearch.value = ''
  router.get(route('manufacturing.bom.index'), {}, { preserveState: false })
}

const onPage = (event) => {
  router.get(
    route('manufacturing.bom.index'),
    { search: filterSearch.value || undefined, page: event.page + 1 },
    { preserveState: true, replace: true },
  )
}
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; gap:16px; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; gap:8px; }

.btn { font-family:var(--font-sans); font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:background var(--dur-base) var(--ease-out); line-height:1.2; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover { background:var(--halo-700); }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover { background:var(--bg-sunken); }

.filter-bar { display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap; }
.filter-input { flex:1; min-width:280px; }

.wh-panel { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); overflow:hidden; }
.wh-dt { width:100%; border-collapse:collapse; font-size:14px; }
.wh-dt thead th { text-align:left; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:var(--fg-3); font-weight:600; padding:10px 18px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sunken); }
.wh-dt thead th.num { text-align:right; }
.wh-dt-row td { padding:11px 18px; border-bottom:1px solid var(--border-subtle); }
.wh-dt-row td.num { text-align:right; }
.wh-dt-row:last-child td { border-bottom:0; }
.wh-dt-row { transition:background var(--dur-fast); }
.wh-dt-row:hover { background:var(--bg-sunken); }

.wh-badge { display:inline-flex; align-items:center; gap:5px; padding:2px 8px; border-radius:var(--r-pill); font-size:11px; font-weight:500; }
.wh-badge-dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
.wh-badge-green  { background:var(--success-bg); color:var(--success-fg); }
.wh-badge-blue   { background:var(--halo-50); color:var(--halo-700); }
.wh-badge-slate  { background:var(--bg-sunken); color:var(--fg-2); }

.row-actions { display:flex; gap:4px; }
.action-btn { background:none; border:1px solid var(--border-subtle); border-radius:var(--r-md); padding:4px 8px; cursor:pointer; color:var(--fg-2); display:inline-flex; align-items:center; transition:background var(--dur-fast); }
.action-btn:hover { background:var(--bg-sunken); color:var(--fg-1); }
.action-btn-danger:hover { background:var(--danger-bg); color:var(--danger-fg); border-color:var(--danger-fg); }

.empty-state { text-align:center; padding:48px; color:var(--fg-3); font-size:14px; }
</style>
