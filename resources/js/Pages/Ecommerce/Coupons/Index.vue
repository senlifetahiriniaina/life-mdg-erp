<template>
  <AppLayout>
    <Head title="Coupons" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Ecommerce · Coupons</h1>
        <p class="wh-page-subtitle">{{ coupons.total }} coupon{{ coupons.total !== 1 ? 's' : '' }}</p>
      </div>
      <button class="wh-btn wh-btn-primary" @click="openCreate">{{ $t('ecommerce.new_coupon') }}</button>
    </div>

    <!-- KPIs -->
    <div class="wh-kpi-grid" style="margin-bottom:16px">
      <div class="wh-kpi">
        <div class="wh-kpi-label">Total</div>
        <div class="wh-kpi-num">{{ stats.total }}</div>
      </div>
      <div class="wh-kpi">
        <div class="wh-kpi-label">{{ $t('common.active') }}</div>
        <div class="wh-kpi-num">{{ stats.active }}</div>
      </div>
    </div>

    <!-- Filters -->
    <div style="display:flex;gap:8px;margin-bottom:12px">
      <input v-model="search" type="text" class="wh-input" placeholder="Search by code…" style="max-width:240px" @input="filter" />
      <select v-model="typeFilter" class="wh-input" style="max-width:160px" @change="filter">
        <option value="">All types</option>
        <option value="percentage">Percentage</option>
        <option value="fixed">Fixed</option>
        <option value="free_shipping">Free shipping</option>
      </select>
    </div>

    <div class="wh-panel">
      <table class="wh-dt">
        <thead>
          <tr>
            <th>Code</th>
            <th>Type</th>
            <th>Value</th>
            <th>Uses</th>
            <th>Expiry</th>
            <th>Status</th>
            <th style="width:100px"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in coupons.data" :key="c.id" class="wh-dt-row">
            <td>
              <code style="font-family:var(--font-mono);font-size:13px;background:var(--bg-2);padding:2px 6px;border-radius:4px">
                {{ c.code }}
              </code>
            </td>
            <td>
              <span class="wh-badge" :class="typeBadge(c.type)">
                {{ typeLabel(c.type) }}
              </span>
            </td>
            <td>{{ formatValue(c) }}</td>
            <td style="color:var(--fg-2)">{{ c.used_count }} / {{ c.usage_limit ?? '∞' }}</td>
            <td style="color:var(--fg-3)">{{ c.expires_at ? formatDate(c.expires_at) : 'Never' }}</td>
            <td>
              <span class="wh-badge" :class="c.is_active ? 'wh-badge-green' : 'wh-badge-gray'">
                <span class="wh-badge-dot" />
                {{ c.is_active ? $t('common.active') : $t('common.inactive') }}
              </span>
            </td>
            <td>
              <div style="display:flex;gap:6px">
                <button class="wh-btn-ghost wh-btn-sm" @click="editCoupon(c)">{{ $t('common.edit') }}</button>
                <button class="wh-btn-ghost wh-btn-sm" style="color:var(--danger)" @click="deleteCoupon(c)">{{ $t('common.delete') }}</button>
              </div>
            </td>
          </tr>
          <tr v-if="!coupons.data.length">
            <td colspan="7" style="text-align:center;color:var(--fg-3);padding:32px">No coupons found.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showModal" class="wh-modal-overlay" @click.self="showModal = false">
      <div class="wh-modal" style="max-width:480px">
        <div class="wh-modal-header">
          <h3>{{ editingCoupon ? $t('ecommerce.edit_coupon') : $t('ecommerce.new_coupon') }}</h3>
          <button class="wh-btn-ghost" @click="showModal = false">✕</button>
        </div>
        <div class="wh-modal-body" style="display:flex;flex-direction:column;gap:12px">
          <div>
            <label class="wh-label">Code</label>
            <input v-model="form.code" type="text" class="wh-input" placeholder="SUMMER20" style="text-transform:uppercase" />
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="wh-label">Type</label>
              <select v-model="form.type" class="wh-input">
                <option value="percentage">Percentage (%)</option>
                <option value="fixed">Fixed ($)</option>
                <option value="free_shipping">Free Shipping</option>
              </select>
            </div>
            <div>
              <label class="wh-label">Value</label>
              <input v-model="form.value" type="number" class="wh-input" min="0" step="0.01" />
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="wh-label">Min. order amount</label>
              <input v-model="form.minimum_amount" type="number" class="wh-input" min="0" step="0.01" placeholder="No minimum" />
            </div>
            <div>
              <label class="wh-label">Max uses</label>
              <input v-model="form.usage_limit" type="number" class="wh-input" min="1" placeholder="Unlimited" />
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="wh-label">Starts at</label>
              <input v-model="form.starts_at" type="datetime-local" class="wh-input" />
            </div>
            <div>
              <label class="wh-label">Expires at</label>
              <input v-model="form.expires_at" type="datetime-local" class="wh-input" />
            </div>
          </div>
          <div>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
              <input v-model="form.is_active" type="checkbox" />
              <span class="wh-label" style="margin:0">{{ $t('common.active') }}</span>
            </label>
          </div>
        </div>
        <div class="wh-modal-footer">
          <button class="wh-btn wh-btn-ghost" @click="showModal = false">{{ $t('common.cancel') }}</button>
          <button class="wh-btn wh-btn-primary" :disabled="saving" @click="saveCoupon">
            {{ saving ? $t('common.saving') : $t('common.save') }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

interface Coupon {
  id: number
  code: string
  type: string
  value: number
  used_count: number
  usage_limit: number | null
  minimum_amount: number | null
  expires_at: string | null
  is_active: boolean
}

const props = defineProps<{
  coupons: { data: Coupon[]; total: number }
  stats: { total: number; active: number }
  filters: { search?: string; type?: string }
}>()

const search = ref(props.filters.search ?? '')
const typeFilter = ref(props.filters.type ?? '')
const showModal = ref(false)
const saving = ref(false)
const editingCoupon = ref<Coupon | null>(null)

const form = ref({
  code: '',
  type: 'percentage',
  value: 0,
  minimum_amount: null as number | null,
  usage_limit: null as number | null,
  starts_at: '',
  expires_at: '',
  is_active: true,
})

function filter(): void {
  router.get('/ecommerce/coupons', { search: search.value, type: typeFilter.value }, { preserveState: true })
}

function openCreate(): void {
  editingCoupon.value = null
  form.value = { code: '', type: 'percentage', value: 0, minimum_amount: null, usage_limit: null, starts_at: '', expires_at: '', is_active: true }
  showModal.value = true
}

function editCoupon(c: Coupon): void {
  editingCoupon.value = c
  form.value = { code: c.code, type: c.type, value: c.value, minimum_amount: c.minimum_amount, usage_limit: c.usage_limit, starts_at: '', expires_at: c.expires_at ?? '', is_active: c.is_active }
  showModal.value = true
}

async function saveCoupon(): Promise<void> {
  saving.value = true
  const url = editingCoupon.value
    ? `/api/v1/ecommerce/coupons/${editingCoupon.value.id}`
    : '/api/v1/ecommerce/coupons'
  const method = editingCoupon.value ? 'PUT' : 'POST'

  try {
    await fetch(url, {
      method,
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(form.value),
    })
    showModal.value = false
    router.reload()
  } finally {
    saving.value = false
  }
}

async function deleteCoupon(c: Coupon): Promise<void> {
  if (!confirm(`Delete coupon ${c.code}?`)) return
  await fetch(`/api/v1/ecommerce/coupons/${c.id}`, { method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
  router.reload()
}

function typeBadge(type: string): string {
  return { percentage: 'wh-badge-blue', fixed: 'wh-badge-purple', free_shipping: 'wh-badge-green' }[type] ?? 'wh-badge-gray'
}

function typeLabel(type: string): string {
  return { percentage: 'Percentage', fixed: 'Fixed', free_shipping: 'Free Shipping' }[type] ?? type
}

function formatValue(c: Coupon): string {
  if (c.type === 'percentage') return `${c.value}%`
  if (c.type === 'free_shipping') return 'Free'
  return `$${c.value}`
}

function formatDate(d: string): string {
  return new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}
</script>
