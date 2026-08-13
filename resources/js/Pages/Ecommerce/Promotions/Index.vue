<template>
  <AppLayout>
    <Head title="Promotions" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Ecommerce · Promotions</h1>
        <p class="wh-page-subtitle">{{ promotions.total }} promotion{{ promotions.total !== 1 ? 's' : '' }}</p>
      </div>
      <button class="wh-btn wh-btn-primary" @click="showCreate = true">{{ $t('ecommerce.new_promotion') }}</button>
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
            <th>Name</th>
            <th>Type</th>
            <th>Date range</th>
            <th>Priority</th>
            <th>Status</th>
            <th style="width:80px"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in promotions.data" :key="p.id" class="wh-dt-row">
            <td style="font-weight:500;color:var(--fg-1)">{{ p.name }}</td>
            <td>
              <span class="wh-badge" :class="typeBadge(p.type)">
                {{ typeLabel(p.type) }}
              </span>
            </td>
            <td style="color:var(--fg-2);font-size:13px">
              {{ formatDate(p.starts_at) }} → {{ p.ends_at ? formatDate(p.ends_at) : '∞' }}
            </td>
            <td style="color:var(--fg-2)">{{ p.priority }}</td>
            <td>
              <span class="wh-badge" :class="p.status === 'active' ? 'wh-badge-green' : 'wh-badge-gray'">
                <span class="wh-badge-dot" />
                {{ p.status === 'active' ? $t('common.active') : $t('common.inactive') }}
              </span>
            </td>
            <td>
              <button class="wh-btn-ghost wh-btn-sm" style="color:var(--danger)" @click="deletePromotion(p)">{{ $t('common.delete') }}</button>
            </td>
          </tr>
          <tr v-if="!promotions.data.length">
            <td colspan="6" style="text-align:center;color:var(--fg-3);padding:32px">No promotions found.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create modal -->
    <div v-if="showCreate" class="wh-modal-overlay" @click.self="showCreate = false">
      <div class="wh-modal" style="max-width:520px">
        <div class="wh-modal-header">
          <h3>{{ $t('ecommerce.new_promotion') }}</h3>
          <button class="wh-btn-ghost" @click="showCreate = false">✕</button>
        </div>
        <div class="wh-modal-body" style="display:flex;flex-direction:column;gap:12px">
          <div>
            <label class="wh-label">Name</label>
            <input v-model="form.name" type="text" class="wh-input" placeholder="Summer Flash Sale" />
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="wh-label">Type</label>
              <select v-model="form.type" class="wh-input">
                <option value="buy_x_get_y">Buy X Get Y</option>
                <option value="bundle">Bundle</option>
                <option value="flash_sale">Flash Sale</option>
              </select>
            </div>
            <div>
              <label class="wh-label">Priority</label>
              <input v-model="form.priority" type="number" class="wh-input" min="0" />
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="wh-label">Starts at</label>
              <input v-model="form.starts_at" type="datetime-local" class="wh-input" />
            </div>
            <div>
              <label class="wh-label">Ends at</label>
              <input v-model="form.ends_at" type="datetime-local" class="wh-input" />
            </div>
          </div>
          <div>
            <label class="wh-label">Status</label>
            <select v-model="form.status" class="wh-input">
              <option value="active">{{ $t('common.active') }}</option>
              <option value="inactive">{{ $t('common.inactive') }}</option>
            </select>
          </div>
          <div>
            <label class="wh-label">Conditions (JSON)</label>
            <textarea v-model="form.conditions" class="wh-input" rows="3" placeholder='{"min_qty": 2}' />
          </div>
          <div>
            <label class="wh-label">Rewards (JSON)</label>
            <textarea v-model="form.rewards" class="wh-input" rows="3" placeholder='{"discount_pct": 10}' />
          </div>
        </div>
        <div class="wh-modal-footer">
          <button class="wh-btn wh-btn-ghost" @click="showCreate = false">{{ $t('common.cancel') }}</button>
          <button class="wh-btn wh-btn-primary" :disabled="saving" @click="savePromotion">
            {{ saving ? 'Saving…' : 'Create' }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

interface Promotion {
  id: number
  name: string
  type: string
  starts_at: string
  ends_at: string | null
  status: string
  priority: number
}

const props = defineProps<{
  promotions: { data: Promotion[]; total: number }
  filters: { status?: string }
}>()

const statusFilter = ref(props.filters.status ?? '')
const showCreate = ref(false)
const saving = ref(false)

const form = ref({
  name: '', type: 'flash_sale', priority: 0,
  starts_at: '', ends_at: '', status: 'active',
  conditions: '{}', rewards: '{}',
})

const statuses = [
  { value: '', label: 'All' },
  { value: 'active', label: t('common.active') },
  { value: 'inactive', label: t('common.inactive') },
]

function setStatus(s: string): void {
  statusFilter.value = s
  router.get('/ecommerce/promotions', { status: s }, { preserveState: true })
}

async function savePromotion(): Promise<void> {
  saving.value = true
  try {
    let conditions: Record<string, unknown> = {}
    let rewards: Record<string, unknown> = {}
    try { conditions = JSON.parse(form.value.conditions) } catch { conditions = {} }
    try { rewards = JSON.parse(form.value.rewards) } catch { rewards = {} }

    await fetch('/api/v1/ecommerce/promotions', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ ...form.value, conditions, rewards }),
    })
    showCreate.value = false
    router.reload()
  } finally {
    saving.value = false
  }
}

async function deletePromotion(p: Promotion): Promise<void> {
  if (!confirm(`Delete "${p.name}"?`)) return
  await fetch(`/api/v1/ecommerce/promotions/${p.id}`, { method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
  router.reload()
}

function typeBadge(type: string): string {
  return { buy_x_get_y: 'wh-badge-orange', bundle: 'wh-badge-blue', flash_sale: 'wh-badge-red' }[type] ?? 'wh-badge-gray'
}

function typeLabel(type: string): string {
  return { buy_x_get_y: 'Buy X Get Y', bundle: 'Bundle', flash_sale: 'Flash Sale' }[type] ?? type
}

function formatDate(d: string): string {
  return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}
</script>
