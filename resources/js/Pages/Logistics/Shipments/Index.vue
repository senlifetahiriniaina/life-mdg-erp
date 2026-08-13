<script setup lang="ts">
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Select from 'primevue/select'
import Paginator from 'primevue/paginator'

interface Carrier { id: number; name: string; type: string }
interface Shipment {
  id: number; reference: string; type: string; status: string
  carrier?: { id: number; name: string }; consignee_name?: string
  consignee_country?: string; transport_mode?: string
  weight_kg?: number; estimated_delivery_at?: string; created_at: string
}
interface Paginated<T> { data: T[]; total: number; current_page: number; last_page: number; per_page: number }

defineProps<{ carriers: Carrier[] }>()

const shipments = ref<Paginated<Shipment>>({ data: [], total: 0, current_page: 1, last_page: 1, per_page: 20 })
const loading   = ref(false)
const search    = ref('')
const statusFilter = ref<string | null>(null)
const typeFilter   = ref<string | null>(null)
const modeFilter   = ref<string | null>(null)

const statusOptions = [
  { label: 'Brouillon', value: 'draft' },
  { label: 'Réservé', value: 'booked' },
  { label: 'Enlevé', value: 'picked_up' },
  { label: 'En transit', value: 'in_transit' },
  { label: 'En livraison', value: 'out_for_delivery' },
  { label: 'Livré', value: 'delivered' },
  { label: 'Annulé', value: 'cancelled' },
  { label: 'Retour', value: 'returned' },
]
const typeOptions  = [
  { label: 'Sortant', value: 'outbound' },
  { label: 'Entrant', value: 'inbound' },
  { label: 'Transfert', value: 'transfer' },
  { label: 'Retour', value: 'return' },
]
const modeOptions = [
  { label: 'Route', value: 'road' },
  { label: 'Aérien', value: 'air' },
  { label: 'Maritime', value: 'sea' },
  { label: 'Ferroviaire', value: 'rail' },
  { label: 'Multimodal', value: 'multimodal' },
]

async function load(page = 1) {
  loading.value = true
  try {
    const params = new URLSearchParams()
    params.set('page', String(page))
    if (search.value) params.set('search', search.value)
    if (statusFilter.value) params.set('status', statusFilter.value)
    if (typeFilter.value) params.set('type', typeFilter.value)
    if (modeFilter.value) params.set('transport_mode', modeFilter.value)
    const res = await fetch(`/api/v1/logistics/shipments?${params}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    shipments.value = await res.json()
  } finally { loading.value = false }
}

function statusBadgeClass(status: string): string {
  const map: Record<string, string> = {
    draft: 'badge-gray', booked: 'badge-blue', picked_up: 'badge-blue',
    in_transit: 'badge-orange', out_for_delivery: 'badge-orange',
    delivered: 'badge-green', cancelled: 'badge-red', returned: 'badge-gray',
  }
  return map[status] ?? 'badge-gray'
}

function modeBadgeClass(mode: string): string {
  const map: Record<string, string> = {
    road: 'badge-blue', air: 'badge-blue', sea: 'badge-blue',
    rail: 'badge-blue', multimodal: 'badge-orange',
  }
  return map[mode] ?? 'badge-gray'
}

function modeIcon(mode: string): string {
  const map: Record<string, string> = {
    road: 'pi pi-car', air: 'pi pi-send', sea: 'pi pi-map',
    rail: 'pi pi-directions', multimodal: 'pi pi-share-alt',
  }
  return map[mode] ?? 'pi pi-box'
}

function formatDate(d?: string) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('fr-FR')
}

function reset() {
  search.value = ''
  statusFilter.value = null
  typeFilter.value = null
  modeFilter.value = null
  load()
}

const onPageChange = (e: { page: number }) => load(e.page + 1)

load()
</script>

<template>
  <AppLayout>
    <Head title="Expéditions" />
    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Expéditions</h1>
        <p class="wh-page-subtitle">{{ shipments.total }} expédition{{ shipments.total !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="router.visit('/logistics/shipments/create')">
          <i class="pi pi-plus" style="font-size:13px" /> Nouvelle expédition
        </button>
      </div>
    </div>

    <div class="wh-panel" style="margin-bottom:16px">
      <div style="padding:12px 16px;display:flex;flex-wrap:wrap;gap:10px;align-items:center">
        <div style="position:relative;flex:1;min-width:200px;max-width:320px">
          <i class="pi pi-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--fg-4);font-size:13px;pointer-events:none" />
          <input v-model="search" class="wh-filter-input" placeholder="Référence, n° suivi, destinataire…" @input="load()" />
        </div>
        <Select v-model="statusFilter" :options="statusOptions" option-label="label" option-value="value" placeholder="Statut" show-clear style="width:160px" @change="load()" />
        <Select v-model="typeFilter" :options="typeOptions" option-label="label" option-value="value" placeholder="Type" show-clear style="width:150px" @change="load()" />
        <Select v-model="modeFilter" :options="modeOptions" option-label="label" option-value="value" placeholder="Mode" show-clear style="width:150px" @change="load()" />
        <button class="btn btn-secondary" @click="reset">
          <i class="pi pi-filter-slash" style="font-size:13px" /> Réinitialiser
        </button>
      </div>
    </div>

    <div class="wh-panel" style="position:relative">
      <div v-if="loading" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(247,248,251,0.6);z-index:1">
        <i class="pi pi-spin pi-spinner" style="font-size:20px;color:var(--halo-500)" />
      </div>
      <table class="wh-dt" :class="{ 'wh-dt-loading': loading }">
        <thead>
          <tr>
            <th>Référence</th>
            <th>Type / Mode</th>
            <th>Transporteur</th>
            <th>Destinataire</th>
            <th>Statut</th>
            <th>Livraison estimée</th>
            <th>Date</th>
            <th style="width:60px"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!loading && shipments.data.length === 0">
            <td colspan="8" style="text-align:center;padding:48px;color:var(--fg-3);font-size:14px">Aucune expédition</td>
          </tr>
          <tr v-for="s in shipments.data" :key="s.id" class="wh-dt-row">
            <td>
              <span style="font-family:var(--font-mono);font-weight:600;color:var(--halo-600)">{{ s.reference }}</span>
            </td>
            <td>
              <div style="display:flex;gap:4px;align-items:center">
                <span class="wh-badge badge-gray">{{ s.type }}</span>
                <span v-if="s.transport_mode" :class="['wh-badge', modeBadgeClass(s.transport_mode)]">
                  <i :class="[modeIcon(s.transport_mode)]" style="font-size:10px;margin-right:2px" />
                  {{ s.transport_mode }}
                </span>
              </div>
            </td>
            <td>{{ s.carrier?.name || '—' }}</td>
            <td>
              <div>{{ s.consignee_name || '—' }}</div>
              <div v-if="s.consignee_country" style="font-size:11px;color:var(--fg-3)">{{ s.consignee_country }}</div>
            </td>
            <td><span :class="['wh-badge', statusBadgeClass(s.status)]">{{ s.status }}</span></td>
            <td>{{ formatDate(s.estimated_delivery_at) }}</td>
            <td style="font-size:12px;color:var(--fg-3)">{{ formatDate(s.created_at) }}</td>
            <td>
              <button class="wh-row-btn" title="Voir" @click="router.visit(`/logistics/shipments/${s.id}`)">
                <i class="pi pi-eye" style="font-size:13px" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <div
        v-if="shipments.total > shipments.per_page"
        style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-top:1px solid var(--border-subtle)"
      >
        <span style="font-size:13px;color:var(--fg-3)">
          {{ (shipments.current_page - 1) * shipments.per_page + 1 }}–{{ Math.min(shipments.current_page * shipments.per_page, shipments.total) }}
          sur {{ shipments.total }}
        </span>
        <Paginator
          :rows="shipments.per_page"
          :total-records="shipments.total"
          :first="(shipments.current_page - 1) * shipments.per_page"
          @page="onPageChange"
        />
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.wh-filter-input { width:100%; padding:7px 12px 7px 32px; border-radius:var(--r-md); border:1px solid var(--border-subtle); background:var(--bg-sunken); font-family:var(--font-sans); font-size:13px; color:var(--fg-1); outline:none; transition:border-color var(--dur-base), box-shadow var(--dur-base); }
.wh-filter-input:focus { border-color:var(--halo-500); background:var(--bg-canvas); box-shadow:0 0 0 3px rgba(46,91,232,0.12); }
.wh-filter-input::placeholder { color:var(--fg-4); }
.wh-row-btn { width:28px; height:28px; border-radius:var(--r-sm); border:1px solid var(--border-subtle); background:var(--bg-canvas); color:var(--fg-2); cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:all var(--dur-fast); }
.wh-row-btn:hover { background:var(--bg-sunken); color:var(--fg-1); border-color:var(--border-strong); }
</style>
