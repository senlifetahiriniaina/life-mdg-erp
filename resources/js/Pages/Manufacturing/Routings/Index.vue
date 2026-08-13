<template>
  <AppLayout>
    <Head :title="$t('manufacturing.routings.title')" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">{{ $t('manufacturing.routings.title') }}</h1>
        <p class="wh-page-subtitle">{{ routings.total }} {{ $t('common.total').toLowerCase() }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="openCreate">
          <i class="pi pi-plus" style="font-size:13px" /> {{ $t('manufacturing.routings.new') }}
        </button>
      </div>
    </div>

    <!-- Filter bar -->
    <div class="filter-bar">
      <Select
        v-model="filterStatus"
        :options="statusOptions"
        option-label="label"
        option-value="value"
        :placeholder="$t('common.all_statuses')"
        class="filter-select"
        @change="applyFilters"
      />
      <button class="btn btn-secondary" @click="resetFilters">
        <i class="pi pi-filter-slash" style="font-size:13px" /> {{ $t('common.reset_filters') }}
      </button>
    </div>

    <!-- Table -->
    <div class="wh-panel">
      <table class="wh-dt">
        <thead>
          <tr>
            <th>{{ $t('manufacturing.routings.name') }}</th>
            <th>{{ $t('manufacturing.routings.code') }}</th>
            <th>{{ $t('manufacturing.routings.bom') }}</th>
            <th class="num">{{ $t('manufacturing.routings.operations_count') }}</th>
            <th>{{ $t('common.status') }}</th>
            <th>{{ $t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="routing in routings.data" :key="routing.id" class="wh-dt-row">
            <td style="font-weight:500;color:var(--fg-1)">{{ routing.name }}</td>
            <td><code>{{ routing.code }}</code></td>
            <td>{{ routing.bom?.reference ?? '—' }}</td>
            <td class="num">{{ routing.operations_count }}</td>
            <td>
              <span :class="['badge', routing.status === 'active' ? 'badge-success' : 'badge-muted']">
                {{ routing.status }}
              </span>
            </td>
            <td>
              <button class="btn btn-sm btn-secondary" @click="openDetail(routing)">
                <i class="pi pi-eye" />
              </button>
            </td>
          </tr>
          <tr v-if="routings.data.length === 0">
            <td colspan="6" class="empty-state">{{ $t('common.no_results') }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Detail Drawer -->
    <Drawer v-model:visible="drawerVisible" position="right" style="width:520px">
      <template #header>
        <span class="font-semibold">{{ selectedRouting?.name }}</span>
      </template>
      <div v-if="selectedRouting" class="drawer-body">
        <div class="detail-grid">
          <div class="detail-row">
            <span class="detail-label">{{ $t('manufacturing.routings.code') }}</span>
            <span><code>{{ selectedRouting.code }}</code></span>
          </div>
          <div class="detail-row">
            <span class="detail-label">{{ $t('manufacturing.routings.bom') }}</span>
            <span>{{ selectedRouting.bom?.reference ?? '—' }}</span>
          </div>
          <div class="detail-row">
            <span class="detail-label">{{ $t('common.status') }}</span>
            <span :class="['badge', selectedRouting.status === 'active' ? 'badge-success' : 'badge-muted']">
              {{ selectedRouting.status }}
            </span>
          </div>
          <div v-if="selectedRouting.notes" class="detail-row">
            <span class="detail-label">{{ $t('common.notes') }}</span>
            <span>{{ selectedRouting.notes }}</span>
          </div>
        </div>

        <h3 class="section-title mt-4">{{ $t('manufacturing.routings.operations') }}</h3>
        <div v-if="loadingOps" class="text-muted">{{ $t('common.loading') }}…</div>
        <div v-else-if="operations.length === 0" class="text-muted">{{ $t('common.no_results') }}</div>
        <div v-else class="ops-list">
          <div v-for="op in operations" :key="op.id" class="op-card">
            <div class="op-header">
              <span class="op-order">#{{ op.operation_order }}</span>
              <span class="op-name">{{ op.name }}</span>
              <span class="op-workcenter">{{ op.workcenter?.name ?? '—' }}</span>
            </div>
            <div class="op-times">
              <span>{{ $t('manufacturing.routings.setup') }}: {{ op.setup_time_minutes }} min</span>
              <span>{{ $t('manufacturing.routings.run') }}: {{ op.run_time_minutes }} min</span>
              <span>{{ $t('manufacturing.routings.cleanup') }}: {{ op.cleanup_time_minutes }} min</span>
              <span class="op-total">{{ $t('manufacturing.routings.total') }}: {{ totalTime(op) }} min</span>
            </div>
          </div>
        </div>
      </div>
    </Drawer>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Drawer, Select } from 'primevue'
import axios from 'axios'

interface BomRef { id: number; reference: string }
interface RoutingOperation {
  id: number
  operation_order: number
  name: string
  workcenter: { id: number; name: string } | null
  setup_time_minutes: number
  run_time_minutes: number
  cleanup_time_minutes: number
  notes: string | null
}
interface Routing {
  id: number
  name: string
  code: string
  status: string
  notes: string | null
  bom: BomRef | null
  operations_count: number
}
interface Pagination<T> { data: T[]; total: number; page: number; last_page: number }

const props = defineProps<{ routings: Pagination<Routing>; filters: Record<string, string> }>()

const filterStatus = ref(props.filters.status ?? null)
const statusOptions = [
  { label: t('common.active'), value: 'active' },
  { label: t('common.inactive'), value: 'inactive' },
]

function applyFilters() {
  router.get('/manufacturing/routings', { status: filterStatus.value ?? undefined }, { preserveState: true })
}
function resetFilters() {
  filterStatus.value = null
  router.get('/manufacturing/routings', {}, { preserveState: true })
}

const drawerVisible = ref(false)
const selectedRouting = ref<Routing | null>(null)
const operations = ref<RoutingOperation[]>([])
const loadingOps = ref(false)

async function openDetail(routing: Routing) {
  selectedRouting.value = routing
  drawerVisible.value = true
  loadingOps.value = true
  try {
    const { data } = await axios.get(`/api/v1/manufacturing/routings/${routing.id}/operations`)
    operations.value = data.data ?? []
  } finally {
    loadingOps.value = false
  }
}

function openCreate() {
  // TODO: open create form
}

function totalTime(op: RoutingOperation): number {
  return Number(op.setup_time_minutes) + Number(op.run_time_minutes) + Number(op.cleanup_time_minutes)
}
</script>

<style scoped>
.ops-list { display: flex; flex-direction: column; gap: 10px; }
.op-card { border: 1px solid var(--border); border-radius: 8px; padding: 12px; }
.op-header { display: flex; align-items: center; gap: 10px; font-weight: 600; }
.op-order { background: var(--primary); color: #fff; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-size: 12px; }
.op-workcenter { margin-left: auto; color: var(--fg-2); font-size: 13px; }
.op-times { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 8px; font-size: 13px; color: var(--fg-2); }
.op-total { font-weight: 600; color: var(--fg-1); }
.detail-grid { display: flex; flex-direction: column; gap: 8px; }
.detail-row { display: flex; gap: 12px; }
.detail-label { min-width: 120px; color: var(--fg-2); font-size: 13px; }
.section-title { font-size: 14px; font-weight: 600; color: var(--fg-1); margin-bottom: 8px; }
</style>
