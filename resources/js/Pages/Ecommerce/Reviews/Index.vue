<template>
  <AppLayout>
    <Head title="Reviews" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Ecommerce · Customer Reviews</h1>
        <p class="wh-page-subtitle">{{ reviews.total }} review{{ reviews.total !== 1 ? 's' : '' }}</p>
      </div>
    </div>

    <!-- KPIs -->
    <div class="wh-kpi-grid" style="margin-bottom:16px">
      <div class="wh-kpi">
        <div class="wh-kpi-label">Total</div>
        <div class="wh-kpi-num">{{ stats.total }}</div>
      </div>
      <div class="wh-kpi">
        <div class="wh-kpi-label">Pending</div>
        <div class="wh-kpi-num" style="color:var(--warning)">{{ stats.pending }}</div>
      </div>
      <div class="wh-kpi">
        <div class="wh-kpi-label">Approved</div>
        <div class="wh-kpi-num" style="color:var(--success)">{{ stats.approved }}</div>
      </div>
      <div class="wh-kpi">
        <div class="wh-kpi-label">Rejected</div>
        <div class="wh-kpi-num" style="color:var(--danger)">{{ stats.rejected }}</div>
      </div>
    </div>

    <!-- Status filter -->
    <div style="display:flex;gap:8px;margin-bottom:12px">
      <button
        v-for="s in statuses"
        :key="s.value"
        class="wh-btn"
        :class="statusFilter === s.value ? 'wh-btn-primary' : 'wh-btn-ghost'"
        @click="setStatus(s.value)"
      >
        {{ s.label }}
      </button>
    </div>

    <div class="wh-panel">
      <table class="wh-dt">
        <thead>
          <tr>
            <th>Product</th>
            <th>Customer</th>
            <th>Rating</th>
            <th>Title</th>
            <th>Date</th>
            <th>Status</th>
            <th style="width:140px"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in reviews.data" :key="r.id" class="wh-dt-row">
            <td style="color:var(--fg-2)">{{ r.product?.name ?? '—' }}</td>
            <td>
              <div style="font-weight:500">{{ r.customer_name }}</div>
              <div style="font-size:12px;color:var(--fg-3)">{{ r.customer_email }}</div>
            </td>
            <td>
              <div style="display:flex;gap:2px">
                <span v-for="s in 5" :key="s" :style="{ color: s <= r.rating ? '#f59e0b' : 'var(--fg-4)' }">★</span>
              </div>
            </td>
            <td style="color:var(--fg-2);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
              {{ r.title ?? r.body ?? '—' }}
            </td>
            <td style="color:var(--fg-3);font-size:13px">{{ formatDate(r.created_at) }}</td>
            <td>
              <span class="wh-badge" :class="statusBadge(r.status)">
                <span class="wh-badge-dot" />{{ r.status }}
              </span>
            </td>
            <td>
              <div v-if="r.status === 'pending'" style="display:flex;gap:6px">
                <button class="wh-btn-ghost wh-btn-sm" style="color:var(--success)" @click="approve(r)">Approve</button>
                <button class="wh-btn-ghost wh-btn-sm" style="color:var(--danger)" @click="reject(r)">Reject</button>
              </div>
              <span v-else style="color:var(--fg-4);font-size:13px">—</span>
            </td>
          </tr>
          <tr v-if="!reviews.data.length">
            <td colspan="7" style="text-align:center;color:var(--fg-3);padding:32px">No reviews found.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Rating distribution chart -->
    <div class="wh-panel" style="margin-top:16px">
      <h3 style="font-size:14px;font-weight:600;margin-bottom:16px">Rating Distribution</h3>
      <div v-for="star in [5,4,3,2,1]" :key="star" style="display:flex;align-items:center;gap:12px;margin-bottom:8px">
        <div style="display:flex;gap:2px;width:80px;flex-shrink:0">
          <span v-for="s in 5" :key="s" :style="{ color: s <= star ? '#f59e0b' : 'var(--fg-4)', fontSize:'12px' }">★</span>
        </div>
        <div style="flex:1;height:8px;background:var(--bg-2);border-radius:4px;overflow:hidden">
          <div
            :style="{ width: distribution(star) + '%', height:'100%', background:'#f59e0b', borderRadius:'4px', transition:'width .3s' }"
          />
        </div>
        <div style="width:32px;text-align:right;font-size:13px;color:var(--fg-3)">{{ ratingCount(star) }}</div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

interface Review {
  id: number
  product: { id: number; name: string } | null
  customer_name: string
  customer_email: string
  rating: number
  title: string | null
  body: string | null
  status: string
  created_at: string
}

const props = defineProps<{
  reviews: { data: Review[]; total: number }
  stats: { total: number; pending: number; approved: number; rejected: number }
  filters: { status?: string }
}>()

const statusFilter = ref(props.filters.status ?? '')

const statuses = [
  { value: '', label: 'All' },
  { value: 'pending', label: 'Pending' },
  { value: 'approved', label: 'Approved' },
  { value: 'rejected', label: 'Rejected' },
]

function setStatus(s: string): void {
  statusFilter.value = s
  router.get('/ecommerce/reviews', { status: s }, { preserveState: true })
}

async function approve(r: Review): Promise<void> {
  await fetch(`/api/v1/ecommerce/reviews/${r.id}/approve`, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
  router.reload()
}

async function reject(r: Review): Promise<void> {
  await fetch(`/api/v1/ecommerce/reviews/${r.id}/reject`, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
  router.reload()
}

function ratingCount(star: number): number {
  return props.reviews.data.filter(r => r.rating === star).length
}

function distribution(star: number): number {
  const total = props.reviews.data.length
  if (total === 0) return 0
  return Math.round((ratingCount(star) / total) * 100)
}

function statusBadge(status: string): string {
  return { pending: 'wh-badge-yellow', approved: 'wh-badge-green', rejected: 'wh-badge-red' }[status] ?? 'wh-badge-gray'
}

function formatDate(d: string): string {
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}
</script>
