<template>
  <AppLayout>
    <Head title="Expéditions" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Expéditions multi-carrier</h1>
        <p class="wh-page-subtitle">Gérez vos expéditions et transporteurs</p>
      </div>
      <div class="page-actions">
        <Button label="Nouvelle expédition" icon="pi pi-plus" severity="primary" @click="showNewShipmentDialog = true" />
      </div>
    </div>

    <TabView v-model:activeIndex="activeTab">
      <!-- Onglet Expéditions -->
      <TabPanel header="Expéditions">
        <!-- KPIs -->
        <div class="wh-kpi-grid" style="margin-bottom: 24px">
          <div class="wh-kpi-card">
            <div class="wh-kpi-label">En transit</div>
            <div class="wh-kpi-value">{{ kpis.inTransit }}</div>
          </div>
          <div class="wh-kpi-card">
            <div class="wh-kpi-label">Livrées ce mois</div>
            <div class="wh-kpi-value">{{ kpis.deliveredThisMonth }}</div>
          </div>
          <div class="wh-kpi-card">
            <div class="wh-kpi-label">En retard</div>
            <div class="wh-kpi-value" :style="kpis.late > 0 ? 'color: var(--red-600)' : ''">{{ kpis.late }}</div>
          </div>
          <div class="wh-kpi-card">
            <div class="wh-kpi-label">Coût moyen</div>
            <div class="wh-kpi-value">{{ fmt(kpis.avgCost) }} €</div>
          </div>
        </div>

        <!-- Filters -->
        <div style="display: flex; gap: 8px; margin-bottom: 16px">
          <Dropdown
            v-model="statusFilter"
            :options="statusOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Tous les statuts"
            style="min-width: 180px"
            @change="loadShipments"
          />
          <Dropdown
            v-model="carrierFilter"
            :options="carrierOptions"
            optionLabel="name"
            optionValue="id"
            placeholder="Tous les transporteurs"
            style="min-width: 180px"
            @change="loadShipments"
          />
        </div>

        <!-- DataTable -->
        <DataTable
          :value="shipments"
          :loading="loading"
          stripedRows
          size="small"
          selectionMode="single"
          @rowSelect="openTrackingPanel"
          :rows="20"
          paginator
          paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown"
          :rowsPerPageOptions="[20, 50, 100]"
        >
          <Column field="reference" header="Référence" style="width: 160px; font-weight: 600" />
          <Column field="destination_address" header="Destinataire" style="min-width: 180px">
            <template #body="{ data }">
              <div>{{ data.destination_address?.name }}</div>
              <div style="font-size: 11px; color: var(--fg-3)">{{ data.destination_address?.city }}, {{ data.destination_address?.country }}</div>
            </template>
          </Column>
          <Column field="carrier" header="Transporteur" style="width: 140px">
            <template #body="{ data }">
              <div style="font-weight: 600">{{ data.carrier?.name ?? '—' }}</div>
              <div style="font-size: 11px; color: var(--fg-3)">{{ data.service_type }}</div>
            </template>
          </Column>
          <Column field="weight_kg" header="Poids" style="width: 80px; text-align: right">
            <template #body="{ data }">{{ data.weight_kg }} kg</template>
          </Column>
          <Column field="status" header="Statut" style="width: 130px">
            <template #body="{ data }">
              <Tag
                :value="statusLabel(data.status)"
                :severity="statusSeverity(data.status)"
                style="font-size: 11px"
              />
            </template>
          </Column>
          <Column field="tracking_number" header="N° Suivi" style="width: 160px">
            <template #body="{ data }">
              <a
                v-if="data.tracking_number && data.carrier?.tracking_url_template"
                :href="data.carrier.tracking_url_template.replace('{tracking_number}', data.tracking_number)"
                target="_blank"
                style="font-size: 12px; color: var(--primary-color)"
              >{{ data.tracking_number }}</a>
              <span v-else-if="data.tracking_number" style="font-size: 12px">{{ data.tracking_number }}</span>
              <span v-else style="color: var(--fg-3)">—</span>
            </template>
          </Column>
          <Column field="estimated_delivery_at" header="Livraison prévue" style="width: 140px">
            <template #body="{ data }">
              <span v-if="data.estimated_delivery_at" :class="isLate(data) ? 'amount-negative' : ''">
                {{ fmtDate(data.estimated_delivery_at) }}
              </span>
              <span v-else style="color: var(--fg-3)">—</span>
            </template>
          </Column>
        </DataTable>

        <!-- Tracking panel (side panel) -->
        <div v-if="selectedShipment" class="tracking-panel wh-panel" style="margin-top: 24px">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px">
            <div>
              <div style="font-weight: 700; font-size: 16px">{{ selectedShipment.reference }}</div>
              <div style="font-size: 13px; color: var(--fg-3)">{{ selectedShipment.carrier?.name }} · {{ selectedShipment.service_type }}</div>
            </div>
            <Button icon="pi pi-times" text rounded @click="selectedShipment = null" />
          </div>

          <!-- Timeline -->
          <div v-if="trackingEvents.length > 0">
            <div style="font-weight: 600; margin-bottom: 12px">Suivi de l'expédition</div>
            <div class="timeline">
              <div v-for="(event, index) in trackingEvents" :key="index" class="timeline-item">
                <div class="timeline-dot" :class="index === 0 ? 'dot-active' : ''"></div>
                <div class="timeline-content">
                  <div style="font-weight: 600; font-size: 13px">{{ statusLabel(event.status) }}</div>
                  <div style="font-size: 12px; color: var(--fg-3)">{{ event.location }}</div>
                  <div style="font-size: 11px; color: var(--fg-3)">{{ fmtDateTime(event.occurred_at) }}</div>
                  <div v-if="event.description" style="font-size: 12px; margin-top: 2px">{{ event.description }}</div>
                </div>
              </div>
            </div>
          </div>
          <div v-else-if="trackingLoading" style="color: var(--fg-3); font-size: 13px">Chargement du suivi...</div>
          <div v-else style="color: var(--fg-3); font-size: 13px">Aucun événement de suivi</div>
        </div>
      </TabPanel>

      <!-- Onglet Transporteurs -->
      <TabPanel header="Transporteurs">
        <div style="display: flex; justify-content: flex-end; margin-bottom: 16px">
          <Button label="Ajouter un transporteur" icon="pi pi-plus" @click="showCarrierDialog = true" />
        </div>

        <div class="wh-kpi-grid">
          <div v-for="carrier in carriers" :key="carrier.id" class="wh-panel carrier-card">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px">
              <div style="font-weight: 700; font-size: 16px">{{ carrier.name }}</div>
              <Tag
                :value="carrier.active ? 'Actif' : 'Inactif'"
                :severity="carrier.active ? 'success' : 'secondary'"
                style="font-size: 11px"
              />
            </div>
            <div style="font-size: 12px; color: var(--fg-3); margin-bottom: 4px">Code: {{ carrier.code.toUpperCase() }}</div>
            <div style="font-size: 12px; color: var(--fg-3)">
              {{ carrier.shipments_count ?? 0 }} expédition{{ (carrier.shipments_count ?? 0) !== 1 ? 's' : '' }}
            </div>
          </div>
        </div>

        <div v-if="carriers.length === 0" style="color: var(--fg-3); font-size: 13px; text-align: center; padding: 48px">
          Aucun transporteur configuré
        </div>
      </TabPanel>
    </TabView>

    <!-- Dialog: Nouvelle expédition -->
    <Dialog v-model:visible="showNewShipmentDialog" header="Nouvelle expédition" :style="{ width: '640px' }" modal>
      <!-- Step 1: Addresses & weight -->
      <div v-if="shipmentStep === 1" style="display: flex; flex-direction: column; gap: 16px">
        <div>
          <div style="font-weight: 600; margin-bottom: 8px">Adresse d'origine</div>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px">
            <div>
              <label class="form-label">Nom / Société</label>
              <InputText v-model="newShipment.origin_address.name" style="width: 100%" />
            </div>
            <div>
              <label class="form-label">Rue</label>
              <InputText v-model="newShipment.origin_address.street" style="width: 100%" />
            </div>
            <div>
              <label class="form-label">Ville</label>
              <InputText v-model="newShipment.origin_address.city" style="width: 100%" />
            </div>
            <div>
              <label class="form-label">Code postal</label>
              <InputText v-model="newShipment.origin_address.zip" style="width: 100%" />
            </div>
            <div>
              <label class="form-label">Pays (2 lettres)</label>
              <InputText v-model="newShipment.origin_address.country" maxlength="2" style="width: 100%" />
            </div>
          </div>
        </div>

        <div>
          <div style="font-weight: 600; margin-bottom: 8px">Adresse de destination</div>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px">
            <div>
              <label class="form-label">Nom / Société</label>
              <InputText v-model="newShipment.destination_address.name" style="width: 100%" />
            </div>
            <div>
              <label class="form-label">Rue</label>
              <InputText v-model="newShipment.destination_address.street" style="width: 100%" />
            </div>
            <div>
              <label class="form-label">Ville</label>
              <InputText v-model="newShipment.destination_address.city" style="width: 100%" />
            </div>
            <div>
              <label class="form-label">Code postal</label>
              <InputText v-model="newShipment.destination_address.zip" style="width: 100%" />
            </div>
            <div>
              <label class="form-label">Pays (2 lettres)</label>
              <InputText v-model="newShipment.destination_address.country" maxlength="2" style="width: 100%" />
            </div>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px">
          <div>
            <label class="form-label">Poids (kg)</label>
            <InputText v-model="newShipment.weight_kg" type="number" step="0.1" min="0.001" style="width: 100%" />
          </div>
          <div>
            <label class="form-label">Longueur (cm)</label>
            <InputText v-model="newShipment.dimensions.length" type="number" style="width: 100%" />
          </div>
          <div>
            <label class="form-label">Largeur (cm)</label>
            <InputText v-model="newShipment.dimensions.width" type="number" style="width: 100%" />
          </div>
        </div>
      </div>

      <!-- Step 2: Choose carrier from rates -->
      <div v-if="shipmentStep === 2">
        <div style="font-weight: 600; margin-bottom: 12px">Sélectionnez un tarif</div>
        <div v-if="ratesLoading" style="color: var(--fg-3); font-size: 13px">Calcul des tarifs...</div>
        <div v-else>
          <div
            v-for="rate in rates"
            :key="rate.carrier_id + '-' + rate.service"
            class="rate-item"
            :class="{ 'rate-selected': selectedRate?.carrier_id === rate.carrier_id && selectedRate?.service === rate.service }"
            @click="selectedRate = rate"
          >
            <div style="display: flex; justify-content: space-between; align-items: center">
              <div>
                <div style="font-weight: 600">{{ rate.carrier_name }}</div>
                <div style="font-size: 12px; color: var(--fg-3)">{{ serviceLabel(rate.service) }} · {{ rate.days }} jour{{ rate.days > 1 ? 's' : '' }}</div>
              </div>
              <div style="font-weight: 700; font-size: 16px">{{ rate.price }} €</div>
            </div>
          </div>
          <div v-if="rates.length === 0" style="color: var(--fg-3); font-size: 13px; text-align: center; padding: 24px">
            Aucun tarif disponible
          </div>
        </div>
      </div>

      <template #footer>
        <div style="display: flex; gap: 8px; justify-content: flex-end">
          <Button label="Annuler" text @click="closeNewShipmentDialog" />
          <Button v-if="shipmentStep === 1" label="Calculer les tarifs" icon="pi pi-arrow-right" :loading="ratesLoading" @click="calculateRates" />
          <Button v-if="shipmentStep === 2" label="Retour" text @click="shipmentStep = 1" />
          <Button
            v-if="shipmentStep === 2"
            label="Créer l'expédition"
            icon="pi pi-check"
            :loading="creating"
            :disabled="!selectedRate"
            @click="createShipment"
          />
        </div>
      </template>
    </Dialog>

    <!-- Dialog: Nouveau transporteur -->
    <Dialog v-model:visible="showCarrierDialog" header="Nouveau transporteur" :style="{ width: '480px' }" modal>
      <div style="display: flex; flex-direction: column; gap: 16px">
        <div>
          <label class="form-label">Nom</label>
          <InputText v-model="newCarrier.name" style="width: 100%" />
        </div>
        <div>
          <label class="form-label">Code unique</label>
          <InputText v-model="newCarrier.code" style="width: 100%" />
        </div>
        <div>
          <label class="form-label">URL de suivi (avec {tracking_number})</label>
          <InputText v-model="newCarrier.tracking_url_template" placeholder="https://track.example.com/{tracking_number}" style="width: 100%" />
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" text @click="showCarrierDialog = false" />
        <Button label="Créer" icon="pi pi-check" :loading="creatingCarrier" :disabled="!newCarrier.name || !newCarrier.code" @click="createCarrier" />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, DataTable, Column, Dialog, Tag, InputText, Dropdown, TabView, TabPanel } from 'primevue'

interface Carrier {
  id: number
  name: string
  code: string
  tracking_url_template: string | null
  active: boolean
  shipments_count?: number
}

interface Shipment {
  id: number
  reference: string
  carrier: Carrier | null
  status: string
  tracking_number: string | null
  destination_address: { name: string; city: string; country: string }
  weight_kg: string
  service_type: string | null
  estimated_delivery_at: string | null
  estimated_cost: string | null
}

interface Rate {
  carrier_id: number
  carrier_name: string
  carrier_code: string
  service: string
  price: string
  days: number
}

interface TrackingEvent {
  status: string
  location: string | null
  description: string | null
  occurred_at: string
}

const activeTab = ref(0)
const shipments = ref<Shipment[]>([])
const carriers = ref<Carrier[]>([])
const loading = ref(false)
const statusFilter = ref('')
const carrierFilter = ref<number | null>(null)
const selectedShipment = ref<Shipment | null>(null)
const trackingEvents = ref<TrackingEvent[]>([])
const trackingLoading = ref(false)

const showNewShipmentDialog = ref(false)
const showCarrierDialog = ref(false)
const shipmentStep = ref(1)
const rates = ref<Rate[]>([])
const selectedRate = ref<Rate | null>(null)
const ratesLoading = ref(false)
const creating = ref(false)
const creatingCarrier = ref(false)

const newShipment = ref({
  origin_address: { name: '', street: '', city: '', zip: '', country: 'FR' },
  destination_address: { name: '', street: '', city: '', zip: '', country: 'FR' },
  weight_kg: '1',
  dimensions: { length: '', width: '', height: '' },
})

const newCarrier = ref({ name: '', code: '', tracking_url_template: '' })

const statusOptions = [
  { label: 'Tous', value: '' },
  { label: 'Brouillon', value: 'draft' },
  { label: 'Réservé', value: 'booked' },
  { label: 'Enlevé', value: 'picked_up' },
  { label: 'En transit', value: 'in_transit' },
  { label: 'Livré', value: 'delivered' },
  { label: 'Retourné', value: 'returned' },
  { label: 'Échoué', value: 'failed' },
]

const carrierOptions = computed(() => [
  { id: null, name: 'Tous les transporteurs' },
  ...carriers.value,
])

const kpis = computed(() => {
  const now = new Date()
  const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1)
  const inTransit = shipments.value.filter(s => s.status === 'in_transit').length
  const deliveredThisMonth = shipments.value.filter(s => {
    // approximate: no delivered_at in listing
    return s.status === 'delivered'
  }).length
  const late = shipments.value.filter(s => isLate(s)).length
  const costsWithValue = shipments.value.filter(s => s.estimated_cost !== null)
  const avgCost = costsWithValue.length > 0
    ? costsWithValue.reduce((sum, s) => sum + parseFloat(s.estimated_cost ?? '0'), 0) / costsWithValue.length
    : 0
  return { inTransit, deliveredThisMonth, late, avgCost }
})

function isLate(shipment: Shipment): boolean {
  if (!shipment.estimated_delivery_at) return false
  if (shipment.status === 'delivered') return false
  return new Date(shipment.estimated_delivery_at) < new Date()
}

function statusLabel(status: string): string {
  const map: Record<string, string> = {
    draft: 'Brouillon',
    booked: 'Réservé',
    picked_up: 'Enlevé',
    in_transit: 'En transit',
    out_for_delivery: 'En livraison',
    delivered: 'Livré',
    returned: 'Retourné',
    failed: 'Échoué',
  }
  return map[status] ?? status
}

function statusSeverity(status: string): string {
  const map: Record<string, string> = {
    draft: 'secondary',
    booked: 'info',
    picked_up: 'info',
    in_transit: 'warn',
    delivered: 'success',
    returned: 'danger',
    failed: 'danger',
  }
  return map[status] ?? 'secondary'
}

function serviceLabel(service: string): string {
  return { express: 'Express', standard: 'Standard', economy: 'Économique' }[service] ?? service
}

function fmt(value: number | string): string {
  return parseFloat(String(value)).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function fmtDate(date: string): string {
  return new Date(date).toLocaleDateString('fr-FR')
}

function fmtDateTime(date: string): string {
  return new Date(date).toLocaleString('fr-FR')
}

function getCsrf(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? ''
}

async function loadShipments() {
  loading.value = true
  try {
    const params = new URLSearchParams()
    if (statusFilter.value) params.set('status', statusFilter.value)
    if (carrierFilter.value) params.set('carrier_id', String(carrierFilter.value))
    const res = await fetch(`/api/v1/inventory/shipments?${params}`, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    })
    if (res.ok) {
      const json = await res.json()
      shipments.value = json.data ?? json
    }
  } finally {
    loading.value = false
  }
}

async function loadCarriers() {
  const res = await fetch('/api/v1/inventory/carriers', {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  })
  if (res.ok) {
    const json = await res.json()
    carriers.value = json.data ?? json
  }
}

async function openTrackingPanel(event: { data: Shipment }) {
  selectedShipment.value = event.data
  trackingLoading.value = true
  trackingEvents.value = []
  try {
    const res = await fetch(`/api/v1/inventory/shipments/${event.data.id}/track`, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    })
    if (res.ok) {
      const json = await res.json()
      trackingEvents.value = json.events ?? []
    }
  } finally {
    trackingLoading.value = false
  }
}

function closeNewShipmentDialog() {
  showNewShipmentDialog.value = false
  shipmentStep.value = 1
  rates.value = []
  selectedRate.value = null
}

async function calculateRates() {
  ratesLoading.value = true
  try {
    const res = await fetch('/api/v1/inventory/shipments/rates', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
      body: JSON.stringify({
        origin_address: newShipment.value.origin_address,
        destination_address: newShipment.value.destination_address,
        weight_kg: parseFloat(newShipment.value.weight_kg),
      }),
    })
    if (res.ok) {
      const json = await res.json()
      rates.value = json.data ?? []
      shipmentStep.value = 2
    }
  } finally {
    ratesLoading.value = false
  }
}

async function createShipment() {
  if (!selectedRate.value) return
  creating.value = true
  try {
    const res = await fetch('/api/v1/inventory/shipments', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
      body: JSON.stringify({
        carrier_id: selectedRate.value.carrier_id,
        origin_address: newShipment.value.origin_address,
        destination_address: newShipment.value.destination_address,
        weight_kg: parseFloat(newShipment.value.weight_kg),
        service_type: selectedRate.value.service,
        estimated_cost: selectedRate.value.price,
      }),
    })
    if (res.ok) {
      closeNewShipmentDialog()
      await loadShipments()
    }
  } finally {
    creating.value = false
  }
}

async function createCarrier() {
  creatingCarrier.value = true
  try {
    const res = await fetch('/api/v1/inventory/carriers', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
      body: JSON.stringify(newCarrier.value),
    })
    if (res.ok) {
      showCarrierDialog.value = false
      newCarrier.value = { name: '', code: '', tracking_url_template: '' }
      await loadCarriers()
    }
  } finally {
    creatingCarrier.value = false
  }
}

onMounted(async () => {
  await Promise.all([loadShipments(), loadCarriers()])
})
</script>

<style scoped>
.amount-negative {
  color: var(--red-600);
  font-weight: 600;
}
.carrier-card {
  padding: 16px;
}
.form-label {
  display: block;
  font-size: 13px;
  font-weight: 500;
  margin-bottom: 4px;
}
.rate-item {
  padding: 12px 16px;
  border: 1px solid var(--surface-border);
  border-radius: 8px;
  margin-bottom: 8px;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}
.rate-item:hover {
  border-color: var(--primary-400);
  background: var(--surface-hover);
}
.rate-selected {
  border-color: var(--primary-color);
  background: var(--primary-50);
}
.timeline {
  display: flex;
  flex-direction: column;
  gap: 0;
}
.timeline-item {
  display: flex;
  gap: 12px;
  padding-bottom: 16px;
  position: relative;
}
.timeline-item:last-child {
  padding-bottom: 0;
}
.timeline-item:not(:last-child)::before {
  content: '';
  position: absolute;
  left: 6px;
  top: 16px;
  bottom: 0;
  width: 2px;
  background: var(--surface-border);
}
.timeline-dot {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: var(--surface-border);
  border: 2px solid var(--surface-border);
  flex-shrink: 0;
  margin-top: 2px;
  z-index: 1;
}
.dot-active {
  background: var(--primary-color);
  border-color: var(--primary-color);
}
.timeline-content {
  flex: 1;
}
</style>
