<template>
  <AppLayout>
    <Head title="Shipping" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Ecommerce · Shipping Methods</h1>
        <p class="wh-page-subtitle">Configure shipping carriers and rates</p>
      </div>
      <button class="wh-btn wh-btn-primary" @click="openCreate">New Method</button>
    </div>

    <div class="wh-panel">
      <table class="wh-dt">
        <thead>
          <tr>
            <th>Name</th>
            <th>Carrier</th>
            <th>Type</th>
            <th>Base cost</th>
            <th>Free over</th>
            <th>Delivery (days)</th>
            <th>Countries</th>
            <th>Status</th>
            <th style="width:100px"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="m in methods.data" :key="m.id" class="wh-dt-row">
            <td style="font-weight:500;color:var(--fg-1)">{{ m.name }}</td>
            <td style="color:var(--fg-2)">{{ m.carrier }}</td>
            <td>
              <span class="wh-badge" :class="typeBadge(m.type)">{{ typeLabel(m.type) }}</span>
            </td>
            <td>{{ formatCurrency(m.base_cost) }}</td>
            <td style="color:var(--fg-3)">{{ m.free_over ? formatCurrency(m.free_over) : '—' }}</td>
            <td style="color:var(--fg-2)">{{ m.estimated_days_min }}–{{ m.estimated_days_max }}</td>
            <td style="color:var(--fg-3);font-size:12px">
              {{ m.countries ? m.countries.join(', ') : 'Worldwide' }}
            </td>
            <td>
              <span class="wh-badge" :class="m.active ? 'wh-badge-green' : 'wh-badge-gray'">
                <span class="wh-badge-dot" />{{ m.active ? $t('common.active') : $t('common.inactive') }}
              </span>
            </td>
            <td>
              <div style="display:flex;gap:6px">
                <button class="wh-btn-ghost wh-btn-sm" @click="editMethod(m)">{{ $t('common.edit') }}</button>
                <button class="wh-btn-ghost wh-btn-sm" style="color:var(--danger)" @click="deleteMethod(m)">Del</button>
              </div>
            </td>
          </tr>
          <tr v-if="!methods.data.length">
            <td colspan="9" style="text-align:center;color:var(--fg-3);padding:32px">No shipping methods configured.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showModal" class="wh-modal-overlay" @click.self="showModal = false">
      <div class="wh-modal" style="max-width:520px">
        <div class="wh-modal-header">
          <h3>{{ editing ? 'Edit Shipping Method' : 'New Shipping Method' }}</h3>
          <button class="wh-btn-ghost" @click="showModal = false">✕</button>
        </div>
        <div class="wh-modal-body" style="display:flex;flex-direction:column;gap:12px">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="wh-label">Name</label>
              <input v-model="form.name" type="text" class="wh-input" placeholder="Standard Shipping" />
            </div>
            <div>
              <label class="wh-label">Carrier</label>
              <input v-model="form.carrier" type="text" class="wh-input" placeholder="UPS, FedEx…" />
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="wh-label">Type</label>
              <select v-model="form.type" class="wh-input">
                <option value="flat_rate">Flat Rate</option>
                <option value="weight_based">Weight Based</option>
                <option value="free">Free</option>
                <option value="pickup">Pickup</option>
              </select>
            </div>
            <div>
              <label class="wh-label">Base cost ($)</label>
              <input v-model="form.base_cost" type="number" class="wh-input" min="0" step="0.01" />
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="wh-label">Per kg cost ($)</label>
              <input v-model="form.per_kg_cost" type="number" class="wh-input" min="0" step="0.0001" />
            </div>
            <div>
              <label class="wh-label">Free over ($)</label>
              <input v-model="form.free_over" type="number" class="wh-input" min="0" step="0.01" placeholder="No threshold" />
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="wh-label">Min days</label>
              <input v-model="form.estimated_days_min" type="number" class="wh-input" min="0" />
            </div>
            <div>
              <label class="wh-label">Max days</label>
              <input v-model="form.estimated_days_max" type="number" class="wh-input" min="0" />
            </div>
          </div>
          <div>
            <label class="wh-label">Countries (comma-separated 2-letter codes, blank = worldwide)</label>
            <input v-model="countriesInput" type="text" class="wh-input" placeholder="FR,DE,US" />
          </div>
          <div>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
              <input v-model="form.active" type="checkbox" />
              <span class="wh-label" style="margin:0">{{ $t('common.active') }}</span>
            </label>
          </div>
        </div>
        <div class="wh-modal-footer">
          <button class="wh-btn wh-btn-ghost" @click="showModal = false">{{ $t('common.cancel') }}</button>
          <button class="wh-btn wh-btn-primary" :disabled="saving" @click="save">
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

interface ShippingMethod {
  id: number
  name: string
  carrier: string
  type: string
  base_cost: number
  per_kg_cost: number
  free_over: number | null
  estimated_days_min: number
  estimated_days_max: number
  countries: string[] | null
  active: boolean
}

const props = defineProps<{
  methods: { data: ShippingMethod[]; total: number }
  stores: { id: number; name: string }[]
  filters: Record<string, string>
}>()

const showModal = ref(false)
const saving = ref(false)
const editing = ref<ShippingMethod | null>(null)
const countriesInput = ref('')

const form = ref({
  name: '', carrier: '', type: 'flat_rate',
  base_cost: 0, per_kg_cost: 0, free_over: null as number | null,
  estimated_days_min: 1, estimated_days_max: 7,
  active: true,
})

function openCreate(): void {
  editing.value = null
  form.value = { name: '', carrier: '', type: 'flat_rate', base_cost: 0, per_kg_cost: 0, free_over: null, estimated_days_min: 1, estimated_days_max: 7, active: true }
  countriesInput.value = ''
  showModal.value = true
}

function editMethod(m: ShippingMethod): void {
  editing.value = m
  form.value = { name: m.name, carrier: m.carrier, type: m.type, base_cost: m.base_cost, per_kg_cost: m.per_kg_cost, free_over: m.free_over, estimated_days_min: m.estimated_days_min, estimated_days_max: m.estimated_days_max, active: m.active }
  countriesInput.value = m.countries ? m.countries.join(',') : ''
  showModal.value = true
}

async function save(): Promise<void> {
  saving.value = true
  const countries = countriesInput.value ? countriesInput.value.split(',').map(c => c.trim().toUpperCase()).filter(Boolean) : null
  const payload = { ...form.value, countries }

  const url = editing.value ? `/api/v1/ecommerce/shipping-methods/${editing.value.id}` : '/api/v1/ecommerce/shipping-methods'
  const method = editing.value ? 'PUT' : 'POST'

  try {
    await fetch(url, { method, headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(payload) })
    showModal.value = false
    router.reload()
  } finally {
    saving.value = false
  }
}

async function deleteMethod(m: ShippingMethod): Promise<void> {
  if (!confirm(`Delete "${m.name}"?`)) return
  await fetch(`/api/v1/ecommerce/shipping-methods/${m.id}`, { method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
  router.reload()
}

function typeBadge(type: string): string {
  return { flat_rate: 'wh-badge-blue', weight_based: 'wh-badge-orange', free: 'wh-badge-green', pickup: 'wh-badge-purple' }[type] ?? 'wh-badge-gray'
}

function typeLabel(type: string): string {
  return { flat_rate: 'Flat Rate', weight_based: 'Weight Based', free: 'Free', pickup: 'Pickup' }[type] ?? type
}

function formatCurrency(v: number): string {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v)
}
</script>
