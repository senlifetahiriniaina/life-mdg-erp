<script setup lang="ts">
import { ref, reactive } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Select from 'primevue/select'
import Paginator from 'primevue/paginator'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'

interface Carrier { id: number; name: string; type: string }
interface Shipment {
  id: number; reference: string; type: string; status: string
  carrier?: { id: number; name: string }; consignee_name?: string
  consignee_country?: string; transport_mode?: string
  weight_kg?: number; estimated_delivery_at?: string; created_at: string
}
interface Paginated<T> { data: T[]; total: number; current_page: number; last_page: number; per_page: number }

const props = defineProps<{ carriers: Carrier[] }>()

// Chantier 19 Lot 4: this page's mutating fetch() calls (create) sent no
// CSRF token at all — same fix pattern as Carriers/Index.vue, confirmed
// empirically via a real php artisan serve + login session (419 without it).
function getCsrf(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? ''
}

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

// Chantier 19 Lot 4: "Nouvelle expédition" and "Voir" both router.visit()'d
// to /logistics/shipments/create and /logistics/shipments/{id} — neither
// route nor page has ever existed anywhere in this app (confirmed via
// `php artisan route:list` + a repo-wide Glob), a guaranteed 404 on every
// click despite the real, already-tested ShipmentController store()/show()/
// book()/dispatch()/deliver()/cancel() API existing. Fixed with a compact
// inline create modal and a detail modal exposing the real lifecycle
// actions (matching Carriers/DeliveryRounds precedent) — this is the TMS
// Shipment (Modules\Logistics\Models\Shipment), a distinct, richer concept
// from Inventory's own carrier-shipping Shipment, already documented
// elsewhere as legitimately separate, not a duplicate.
const showFormModal = ref(false)
const savingShipment = ref(false)
const shipmentForm = reactive({
  carrier_id: null as number | null, type: null as string | null, transport_mode: null as string | null,
  consignee_name: '', consignee_country: '', weight_kg: '',
})
const shipmentErrors = reactive<Record<string, string>>({})

function openCreateShipment() {
  shipmentForm.carrier_id = null
  shipmentForm.type = null
  shipmentForm.transport_mode = null
  shipmentForm.consignee_name = ''
  shipmentForm.consignee_country = ''
  shipmentForm.weight_kg = ''
  Object.keys(shipmentErrors).forEach(k => delete shipmentErrors[k])
  showFormModal.value = true
}

async function submitShipmentForm() {
  savingShipment.value = true
  Object.keys(shipmentErrors).forEach(k => delete shipmentErrors[k])
  try {
    const res = await fetch('/api/v1/logistics/shipments', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': getCsrf() },
      credentials: 'same-origin',
      body: JSON.stringify({
        ...shipmentForm,
        weight_kg: shipmentForm.weight_kg ? Number(shipmentForm.weight_kg) : null,
      }),
    })
    if (!res.ok) {
      const data = await res.json()
      if (data.errors) Object.assign(shipmentErrors, data.errors)
      return
    }
    showFormModal.value = false
    load(shipments.value.current_page)
  } finally {
    savingShipment.value = false
  }
}

const showDetailModal = ref(false)
const detailLoading = ref(false)
const detailShipment = ref<any>(null)
const detailActionLoading = ref(false)

async function viewShipment(shipment: Shipment) {
  showDetailModal.value = true
  detailLoading.value = true
  detailShipment.value = null
  try {
    const res = await fetch(`/api/v1/logistics/shipments/${shipment.id}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    if (res.ok) {
      const json = await res.json()
      detailShipment.value = json.data ?? json
    }
  } finally {
    detailLoading.value = false
  }
}

async function runShipmentAction(action: 'book' | 'dispatch' | 'deliver' | 'cancel') {
  if (!detailShipment.value) return
  detailActionLoading.value = true
  try {
    const res = await fetch(`/api/v1/logistics/shipments/${detailShipment.value.id}/${action}`, {
      method: 'POST',
      headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrf() },
      credentials: 'same-origin',
    })
    if (res.ok) {
      const json = await res.json()
      detailShipment.value = json.data ?? json
      load(shipments.value.current_page)
    }
  } finally {
    detailActionLoading.value = false
  }
}

load()

// Chantier 32.23 (deep 14-layer audit, layer 13 — IA): this real, routed
// page never called useAiAssistant() at all despite LogisticsAiAssistController
// (POST /api/v1/logistics/ai/assist) already existing.
const { guidance } = useAiAssistant('Logistics', 'create_shipment')
const showAiPanel = ref(true)
</script>

<template>
  <AppLayout>
    <Head title="Expéditions" />
    <AIAssistantPanel v-if="showAiPanel" :guidance="guidance" @close="showAiPanel = false" />
    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Expéditions</h1>
        <p class="wh-page-subtitle">{{ shipments.total }} expédition{{ shipments.total !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="openCreateShipment">
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
              <button class="wh-row-btn" title="Voir" @click="viewShipment(s)">
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

    <Dialog v-model:visible="showFormModal" header="Nouvelle expédition" modal style="width: 30rem">
      <form class="shp-form" @submit.prevent="submitShipmentForm">
        <div class="shp-field">
          <label>Transporteur *</label>
          <Select v-model="shipmentForm.carrier_id" :options="props.carriers" option-label="name" option-value="id" class="w-full" :class="{ 'p-invalid': shipmentErrors.carrier_id }" />
          <small v-if="shipmentErrors.carrier_id" class="shp-error">{{ shipmentErrors.carrier_id }}</small>
        </div>
        <div class="shp-field">
          <label>Type</label>
          <Select v-model="shipmentForm.type" :options="typeOptions" option-label="label" option-value="value" show-clear class="w-full" />
        </div>
        <div class="shp-field">
          <label>Mode de transport</label>
          <Select v-model="shipmentForm.transport_mode" :options="modeOptions" option-label="label" option-value="value" show-clear class="w-full" />
        </div>
        <div class="shp-field">
          <label>Destinataire</label>
          <InputText v-model="shipmentForm.consignee_name" />
        </div>
        <div class="shp-field">
          <label>Pays destinataire (ISO 2)</label>
          <InputText v-model="shipmentForm.consignee_country" maxlength="2" />
        </div>
        <div class="shp-field">
          <label>Poids (kg)</label>
          <InputText v-model="shipmentForm.weight_kg" />
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px">
          <button type="button" class="btn btn-secondary" @click="showFormModal = false">Annuler</button>
          <button type="submit" class="btn btn-primary" :disabled="savingShipment">Créer</button>
        </div>
      </form>
    </Dialog>

    <Dialog v-model:visible="showDetailModal" :header="`Expédition ${detailShipment?.reference ?? ''}`" modal style="width: 32rem">
      <div v-if="detailLoading" style="text-align:center;padding:24px"><i class="pi pi-spin pi-spinner" /></div>
      <div v-else-if="detailShipment">
        <p><strong>Statut :</strong> {{ detailShipment.status }}</p>
        <p><strong>Transporteur :</strong> {{ detailShipment.carrier?.name ?? '—' }}</p>
        <p><strong>Destinataire :</strong> {{ detailShipment.consignee_name ?? '—' }} ({{ detailShipment.consignee_country ?? '—' }})</p>
        <p><strong>N° de suivi :</strong> {{ detailShipment.tracking_number ?? '—' }}</p>
        <div style="display:flex;gap:8px;margin-top:16px;flex-wrap:wrap">
          <button v-if="detailShipment.status === 'draft'" class="btn btn-secondary" :disabled="detailActionLoading" @click="runShipmentAction('book')">Réserver</button>
          <button v-if="detailShipment.status === 'booked'" class="btn btn-secondary" :disabled="detailActionLoading" @click="runShipmentAction('dispatch')">Enlever</button>
          <button v-if="['picked_up','in_transit','out_for_delivery'].includes(detailShipment.status)" class="btn btn-secondary" :disabled="detailActionLoading" @click="runShipmentAction('deliver')">Livrer</button>
          <button v-if="!['delivered','cancelled'].includes(detailShipment.status)" class="btn btn-secondary" :disabled="detailActionLoading" @click="runShipmentAction('cancel')">Annuler</button>
        </div>
      </div>
      <p v-else style="color:var(--fg-3)">Aucune donnée disponible.</p>
    </Dialog>
  </AppLayout>
</template>

<style scoped>
.wh-filter-input { width:100%; padding:7px 12px 7px 32px; border-radius:var(--r-md); border:1px solid var(--border-subtle); background:var(--bg-sunken); font-family:var(--font-sans); font-size:13px; color:var(--fg-1); outline:none; transition:border-color var(--dur-base), box-shadow var(--dur-base); }
.wh-filter-input:focus { border-color:var(--halo-500); background:var(--bg-canvas); box-shadow:0 0 0 3px rgba(46,91,232,0.12); }
.wh-filter-input::placeholder { color:var(--fg-4); }
.wh-row-btn { width:28px; height:28px; border-radius:var(--r-sm); border:1px solid var(--border-subtle); background:var(--bg-canvas); color:var(--fg-2); cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:all var(--dur-fast); }
.wh-row-btn:hover { background:var(--bg-sunken); color:var(--fg-1); border-color:var(--border-strong); }
.shp-form { display:flex; flex-direction:column; gap:12px; }
.shp-field { display:flex; flex-direction:column; gap:4px; }
.shp-field label { font-size:12px; font-weight:500; color:var(--fg-2); }
.shp-error { color:var(--red-500); font-size:11px; }
</style>
