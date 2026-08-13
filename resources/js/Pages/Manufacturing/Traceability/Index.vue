<template>
  <AppLayout>
    <Head :title="$t('manufacturing.traceability.title')" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">{{ $t('manufacturing.traceability.title') }}</h1>
        <p class="wh-page-subtitle">{{ lots.total }} {{ $t('common.total').toLowerCase() }}</p>
      </div>
    </div>

    <!-- Search bar -->
    <div class="filter-bar">
      <InputText
        v-model="search"
        :placeholder="$t('manufacturing.traceability.search_placeholder')"
        class="filter-input"
        @keyup.enter="applySearch"
      />
      <button class="btn btn-primary" @click="applySearch">
        <i class="pi pi-search" style="font-size:13px" />
      </button>
      <button class="btn btn-secondary" @click="resetSearch">
        <i class="pi pi-filter-slash" style="font-size:13px" />
      </button>
    </div>

    <!-- Lots table -->
    <div class="wh-panel">
      <table class="wh-dt">
        <thead>
          <tr>
            <th>{{ $t('manufacturing.traceability.lot_number') }}</th>
            <th>{{ $t('manufacturing.traceability.product') }}</th>
            <th class="num">{{ $t('manufacturing.traceability.quantity') }}</th>
            <th>{{ $t('manufacturing.traceability.expiry') }}</th>
            <th>{{ $t('common.status') }}</th>
            <th>{{ $t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="lot in lots.data" :key="lot.id" class="wh-dt-row">
            <td style="font-weight:600"><code>{{ lot.lot_number }}</code></td>
            <td>{{ lot.product?.name ?? '—' }}</td>
            <td class="num">{{ lot.quantity }}</td>
            <td>
              <span v-if="lot.expiry_date" :class="isExpiringSoon(lot.expiry_date) ? 'text-warning' : ''">
                {{ lot.expiry_date }}
              </span>
              <span v-else>—</span>
            </td>
            <td>
              <span :class="['badge', lotStatusClass(lot.status)]">{{ lot.status }}</span>
            </td>
            <td>
              <button class="btn btn-sm btn-secondary" @click="viewTree(lot)">
                <i class="pi pi-sitemap" /> {{ $t('manufacturing.traceability.tree') }}
              </button>
            </td>
          </tr>
          <tr v-if="lots.data.length === 0">
            <td colspan="6" class="empty-state">{{ $t('common.no_results') }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Trace tree drawer -->
    <Drawer v-model:visible="drawerVisible" position="right" style="width:580px">
      <template #header>
        <span class="font-semibold">{{ $t('manufacturing.traceability.tree_for') }}: {{ selectedLot?.lot_number }}</span>
      </template>
      <div v-if="loadingTree" class="p-4 text-muted">{{ $t('common.loading') }}…</div>
      <div v-else-if="tree" class="drawer-body">
        <!-- Upstream materials -->
        <div v-if="tree.upstream_materials?.length" class="tree-section">
          <h3 class="section-title">{{ $t('manufacturing.traceability.upstream') }}</h3>
          <div v-for="(mat, i) in tree.upstream_materials" :key="i" class="tree-node tree-node--upstream">
            <i class="pi pi-box" /> {{ mat.product_name }} × {{ mat.quantity }}
          </div>
        </div>

        <!-- Lot itself -->
        <div class="tree-node tree-node--lot">
          <i class="pi pi-tag" />
          <strong>{{ tree.lot?.lot_number }}</strong> — {{ tree.lot?.product }} ({{ tree.lot?.quantity }} remaining)
        </div>

        <!-- Events -->
        <div v-if="tree.events?.length" class="tree-section mt-3">
          <h3 class="section-title">{{ $t('manufacturing.traceability.events') }}</h3>
          <div v-for="evt in tree.events" :key="evt.id" class="event-row">
            <span class="event-type">{{ evt.event_type }}</span>
            <span class="event-ref">{{ evt.reference ?? '—' }}</span>
            <span class="event-qty">× {{ evt.quantity }}</span>
            <span class="event-date">{{ formatDate(evt.occurred_at) }}</span>
          </div>
        </div>
      </div>
    </Drawer>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Drawer, InputText } from 'primevue'
import axios from 'axios'

interface Lot {
  id: number
  lot_number: string
  product: { id: number; name: string } | null
  quantity: number
  expiry_date: string | null
  status: string
}
interface Pagination<T> { data: T[]; total: number; page: number; last_page: number }

const props = defineProps<{ lots: Pagination<Lot>; filters: Record<string, string> }>()

const search = ref(props.filters.search ?? '')

function applySearch() {
  router.get('/manufacturing/traceability', { search: search.value }, { preserveState: true })
}
function resetSearch() {
  search.value = ''
  router.get('/manufacturing/traceability', {}, { preserveState: true })
}

const drawerVisible = ref(false)
const selectedLot = ref<Lot | null>(null)
const tree = ref<Record<string, unknown> | null>(null)
const loadingTree = ref(false)

async function viewTree(lot: Lot) {
  selectedLot.value = lot
  drawerVisible.value = true
  loadingTree.value = true
  try {
    const { data } = await axios.get(`/api/v1/manufacturing/lots/${lot.id}/tree`)
    tree.value = data
  } finally {
    loadingTree.value = false
  }
}

function lotStatusClass(status: string): string {
  const map: Record<string, string> = {
    active: 'badge-success',
    quarantine: 'badge-warning',
    expired: 'badge-error',
    consumed: 'badge-muted',
  }
  return map[status] ?? 'badge-muted'
}

function isExpiringSoon(date: string): boolean {
  const d = new Date(date)
  const now = new Date()
  const diff = (d.getTime() - now.getTime()) / (1000 * 60 * 60 * 24)
  return diff < 30
}

function formatDate(d: string | null): string {
  if (!d) return '—'
  return new Date(d).toLocaleString()
}
</script>

<style scoped>
.tree-section { margin-bottom: 16px; }
.section-title { font-size: 14px; font-weight: 600; margin-bottom: 8px; color: var(--fg-1); }
.tree-node { display: flex; align-items: center; gap: 8px; padding: 10px 14px; border-radius: 8px; margin-bottom: 6px; }
.tree-node--upstream { background: var(--surface-2); border: 1px solid var(--border); }
.tree-node--lot { background: var(--primary-light, #eff6ff); border: 2px solid var(--primary); font-size: 14px; }
.event-row { display: flex; align-items: center; gap: 12px; padding: 6px 0; border-bottom: 1px solid var(--border); font-size: 13px; }
.event-type { font-weight: 600; min-width: 100px; }
.event-ref { color: var(--fg-2); flex: 1; }
.event-qty { font-weight: 600; }
.event-date { color: var(--fg-2); font-size: 12px; }
.text-warning { color: var(--orange, #f97316); font-weight: 600; }
</style>
