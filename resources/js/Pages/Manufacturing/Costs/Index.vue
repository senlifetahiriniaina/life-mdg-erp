<template>
  <AppLayout>
    <Head :title="$t('manufacturing.costs.title')" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">{{ $t('manufacturing.costs.title') }}</h1>
        <p class="wh-page-subtitle">{{ $t('manufacturing.costs.subtitle') }}</p>
      </div>
    </div>

    <!-- Summary cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">{{ $t('manufacturing.costs.avg_variance') }}</div>
        <div class="stat-value" :class="summary.avg_variance_pct > 0 ? 'text-error' : 'text-success'">
          {{ summary.avg_variance_pct?.toFixed(1) ?? '0.0' }}%
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-label">{{ $t('manufacturing.costs.total_production_cost') }}</div>
        <div class="stat-value">{{ formatCurrency(summary.total_cost_this_month) }}</div>
      </div>
    </div>

    <!-- Cost table -->
    <div class="wh-panel mt-4">
      <table class="wh-dt">
        <thead>
          <tr>
            <th>{{ $t('manufacturing.costs.production_order') }}</th>
            <th>{{ $t('manufacturing.costs.product') }}</th>
            <th class="num">{{ $t('manufacturing.costs.standard_cost') }}</th>
            <th class="num">{{ $t('manufacturing.costs.actual_cost') }}</th>
            <th class="num">{{ $t('manufacturing.costs.variance_eur') }}</th>
            <th class="num">{{ $t('manufacturing.costs.variance_pct') }}</th>
            <th>{{ $t('manufacturing.costs.trend') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="record in records.data" :key="record.id" class="wh-dt-row">
            <td>{{ record.production_order?.reference ?? '—' }}</td>
            <td>{{ record.production_order?.bom?.product?.name ?? '—' }}</td>
            <td class="num">{{ formatCurrency(record.standard_cost_ref) }}</td>
            <td class="num">{{ formatCurrency(record.total_actual) }}</td>
            <td class="num" :class="record.variance > 0 ? 'text-error' : 'text-success'">
              {{ formatCurrency(record.variance) }}
            </td>
            <td class="num" :class="record.variance_pct > 0 ? 'text-error' : 'text-success'">
              {{ Number(record.variance_pct).toFixed(1) }}%
            </td>
            <td>
              <i v-if="record.variance > 0" class="pi pi-arrow-up text-error" />
              <i v-else-if="record.variance < 0" class="pi pi-arrow-down text-success" />
              <i v-else class="pi pi-minus text-muted" />
            </td>
          </tr>
          <tr v-if="records.data.length === 0">
            <td colspan="7" class="empty-state">{{ $t('common.no_results') }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

interface CostRecord {
  id: number
  production_order: { reference: string; bom: { product: { name: string } | null } | null } | null
  material_cost_actual: number
  labor_cost_actual: number
  overhead_cost_actual: number
  total_actual: number
  standard_cost_ref: number
  variance: number
  variance_pct: number
}
interface Pagination<T> { data: T[]; total: number; page: number; last_page: number }

defineProps<{
  records: Pagination<CostRecord>
  summary: { avg_variance_pct: number; total_cost_this_month: number }
}>()

function formatCurrency(val: number | string | null): string {
  const n = Number(val ?? 0)
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(n)
}
</script>

<style scoped>
.stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; }
.stat-card { background: var(--surface-1); border: 1px solid var(--border); border-radius: 8px; padding: 16px; }
.stat-label { font-size: 13px; color: var(--fg-2); margin-bottom: 4px; }
.stat-value { font-size: 22px; font-weight: 700; color: var(--fg-1); }
.text-error { color: var(--red, #ef4444); }
.text-success { color: var(--green, #22c55e); }
.text-muted { color: var(--fg-2); }
</style>
