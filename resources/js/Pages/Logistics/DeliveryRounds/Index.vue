<script setup lang="ts">
import { ref, reactive } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Select from 'primevue/select'
import Paginator from 'primevue/paginator'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'

interface DeliveryRound {
  id: number
  reference: string
  driver_name: string
  vehicle_type: string
  vehicle_plate: string
  planned_date: string
  status: string
  total_stops: number
  started_at?: string
  completed_at?: string
}

interface Paginated<T> { data: T[]; total: number; current_page: number; last_page: number; per_page: number }

const rounds  = ref<Paginated<DeliveryRound>>({ data: [], total: 0, current_page: 1, last_page: 1, per_page: 20 })
const loading = ref(false)
const statusFilter = ref<string | null>(null)

const statusOptions = [
  { label: 'Planifié', value: 'planned' },
  { label: 'En cours', value: 'in_progress' },
  { label: 'Terminé', value: 'completed' },
  { label: 'Annulé', value: 'cancelled' },
]

function statusBadgeClass(status: string): string {
  const map: Record<string, string> = {
    planned: 'badge-blue', in_progress: 'badge-orange',
    completed: 'badge-green', cancelled: 'badge-red',
  }
  return map[status] ?? 'badge-gray'
}

function vehicleIcon(type: string): string {
  const map: Record<string, string> = {
    van: 'pi pi-car', truck: 'pi pi-send', motorcycle: 'pi pi-bolt', bicycle: 'pi pi-circle',
  }
  return map[type] ?? 'pi pi-car'
}

function formatDate(d?: string) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('fr-FR')
}

// Chantier 19 Lot 4: this page's POST fetch() calls (start/complete) sent no
// CSRF token at all — same fix pattern as Carriers/Index.vue, confirmed
// empirically via a real php artisan serve + login session (419 without it).
function getCsrf(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? ''
}

async function load(page = 1) {
  loading.value = true
  try {
    const params = new URLSearchParams()
    params.set('page', String(page))
    if (statusFilter.value) params.set('status', statusFilter.value)
    const res = await fetch(`/api/v1/logistics/delivery-rounds?${params}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    rounds.value = await res.json()
  } finally { loading.value = false }
}

async function startRound(round: DeliveryRound) {
  const res = await fetch(`/api/v1/logistics/delivery-rounds/${round.id}/start`, {
    method: 'POST',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': getCsrf() },
    credentials: 'same-origin',
  })
  if (res.ok) load(rounds.value.current_page)
}

async function completeRound(round: DeliveryRound) {
  const res = await fetch(`/api/v1/logistics/delivery-rounds/${round.id}/complete`, {
    method: 'POST',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': getCsrf() },
    credentials: 'same-origin',
  })
  if (res.ok) load(rounds.value.current_page)
}

function reset() {
  statusFilter.value = null
  load()
}

const onPageChange = (e: { page: number }) => load(e.page + 1)

// Chantier 19 Lot 4: "Nouvelle tournée" and "Voir" both router.visit()'d to
// /logistics/delivery-rounds/create and /logistics/delivery-rounds/{id} —
// neither route nor page has ever existed anywhere in this app (confirmed
// via `php artisan route:list` + a repo-wide Glob), a guaranteed 404 on
// every click despite the real, already-tested DeliveryRoundController
// store()/show() API existing. Fixed with a compact inline create modal
// and a read-only detail modal (matching the Carriers/Index.vue precedent).
const showFormModal = ref(false)
const savingRound = ref(false)
const roundForm = reactive({
  driver_name: '', driver_phone: '', vehicle_plate: '', vehicle_type: null as string | null, planned_date: '',
})
const roundErrors = reactive<Record<string, string>>({})

const vehicleTypeOptions = [
  { label: 'Camionnette', value: 'van' },
  { label: 'Camion', value: 'truck' },
  { label: 'Moto', value: 'motorcycle' },
  { label: 'Vélo', value: 'bicycle' },
]

function openCreateRound() {
  roundForm.driver_name = ''
  roundForm.driver_phone = ''
  roundForm.vehicle_plate = ''
  roundForm.vehicle_type = null
  roundForm.planned_date = ''
  Object.keys(roundErrors).forEach(k => delete roundErrors[k])
  showFormModal.value = true
}

async function submitRoundForm() {
  savingRound.value = true
  Object.keys(roundErrors).forEach(k => delete roundErrors[k])
  try {
    const res = await fetch('/api/v1/logistics/delivery-rounds', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': getCsrf() },
      credentials: 'same-origin',
      body: JSON.stringify(roundForm),
    })
    if (!res.ok) {
      const data = await res.json()
      if (data.errors) Object.assign(roundErrors, data.errors)
      return
    }
    showFormModal.value = false
    load(rounds.value.current_page)
  } finally {
    savingRound.value = false
  }
}

const showDetailModal = ref(false)
const detailLoading = ref(false)
const detailRound = ref<any>(null)

async function viewRound(round: DeliveryRound) {
  showDetailModal.value = true
  detailLoading.value = true
  detailRound.value = null
  try {
    const res = await fetch(`/api/v1/logistics/delivery-rounds/${round.id}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    if (res.ok) detailRound.value = await res.json()
  } finally {
    detailLoading.value = false
  }
}

load()
</script>

<template>
  <AppLayout>
    <Head title="Tournées de livraison" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Tournées de livraison</h1>
        <p class="wh-page-subtitle">{{ rounds.total }} tournée{{ rounds.total !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="openCreateRound">
          <i class="pi pi-plus" style="font-size:13px" /> Nouvelle tournée
        </button>
      </div>
    </div>

    <div class="wh-panel" style="margin-bottom:16px">
      <div style="padding:12px 16px;display:flex;gap:10px;align-items:center">
        <Select v-model="statusFilter" :options="statusOptions" option-label="label" option-value="value" placeholder="Statut" show-clear style="width:160px" @change="load()" />
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
            <th>Chauffeur</th>
            <th>Véhicule</th>
            <th>Date prévue</th>
            <th>Statut</th>
            <th>Arrêts</th>
            <th style="width:100px">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!loading && rounds.data.length === 0">
            <td colspan="7" style="text-align:center;padding:48px;color:var(--fg-3);font-size:14px">Aucune tournée</td>
          </tr>
          <tr v-for="r in rounds.data" :key="r.id" class="wh-dt-row">
            <td>
              <span style="font-family:var(--font-mono);font-weight:600;color:var(--halo-600)">{{ r.reference }}</span>
            </td>
            <td>{{ r.driver_name }}</td>
            <td>
              <div style="display:flex;align-items:center;gap:6px">
                <i :class="[vehicleIcon(r.vehicle_type)]" style="color:var(--fg-3)" />
                <div>
                  <div>{{ r.vehicle_type }}</div>
                  <div style="font-size:11px;color:var(--fg-3);font-family:var(--font-mono)">{{ r.vehicle_plate }}</div>
                </div>
              </div>
            </td>
            <td>{{ formatDate(r.planned_date) }}</td>
            <td><span :class="['wh-badge', statusBadgeClass(r.status)]">{{ r.status }}</span></td>
            <td>{{ r.total_stops ?? 0 }}</td>
            <td>
              <div style="display:flex;gap:4px">
                <button
                  v-if="r.status === 'planned'"
                  class="wh-row-btn"
                  title="Démarrer"
                  @click="startRound(r)"
                >
                  <i class="pi pi-play" style="font-size:13px" />
                </button>
                <button
                  v-if="r.status === 'in_progress'"
                  class="wh-row-btn"
                  title="Terminer"
                  @click="completeRound(r)"
                >
                  <i class="pi pi-check" style="font-size:13px" />
                </button>
                <button class="wh-row-btn" title="Voir" @click="viewRound(r)">
                  <i class="pi pi-eye" style="font-size:13px" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <div
        v-if="rounds.total > rounds.per_page"
        style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-top:1px solid var(--border-subtle)"
      >
        <span style="font-size:13px;color:var(--fg-3)">
          {{ (rounds.current_page - 1) * rounds.per_page + 1 }}–{{ Math.min(rounds.current_page * rounds.per_page, rounds.total) }}
          sur {{ rounds.total }}
        </span>
        <Paginator
          :rows="rounds.per_page"
          :total-records="rounds.total"
          :first="(rounds.current_page - 1) * rounds.per_page"
          @page="onPageChange"
        />
      </div>
    </div>

    <Dialog v-model:visible="showFormModal" header="Nouvelle tournée" modal style="width: 30rem">
      <form class="round-form" @submit.prevent="submitRoundForm">
        <div class="round-field">
          <label>Chauffeur *</label>
          <InputText v-model="roundForm.driver_name" :class="{ 'p-invalid': roundErrors.driver_name }" />
          <small v-if="roundErrors.driver_name" class="round-error">{{ roundErrors.driver_name }}</small>
        </div>
        <div class="round-field">
          <label>Téléphone chauffeur</label>
          <InputText v-model="roundForm.driver_phone" />
        </div>
        <div class="round-field">
          <label>Type de véhicule</label>
          <Select v-model="roundForm.vehicle_type" :options="vehicleTypeOptions" option-label="label" option-value="value" show-clear class="w-full" />
        </div>
        <div class="round-field">
          <label>Plaque</label>
          <InputText v-model="roundForm.vehicle_plate" />
        </div>
        <div class="round-field">
          <label>Date prévue</label>
          <InputText v-model="roundForm.planned_date" type="date" />
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px">
          <button type="button" class="btn btn-secondary" @click="showFormModal = false">Annuler</button>
          <button type="submit" class="btn btn-primary" :disabled="savingRound">Créer</button>
        </div>
      </form>
    </Dialog>

    <Dialog v-model:visible="showDetailModal" :header="`Tournée ${detailRound?.reference ?? ''}`" modal style="width: 32rem">
      <div v-if="detailLoading" style="text-align:center;padding:24px"><i class="pi pi-spin pi-spinner" /></div>
      <div v-else-if="detailRound">
        <p><strong>Chauffeur :</strong> {{ detailRound.driver_name }}</p>
        <p><strong>Statut :</strong> {{ detailRound.status }}</p>
        <p><strong>Transporteur :</strong> {{ detailRound.carrier?.name ?? '—' }}</p>
        <p><strong>Créé par :</strong> {{ detailRound.creator?.name ?? '—' }}</p>
        <p><strong>Arrêts :</strong> {{ detailRound.stops?.length ?? 0 }}</p>
        <ul v-if="detailRound.stops?.length" style="font-size:12px;margin-top:8px">
          <li v-for="s in detailRound.stops" :key="s.id">
            {{ s.shipment?.consignee_name ?? s.shipment?.reference ?? `Arrêt #${s.id}` }} — {{ s.status }}
          </li>
        </ul>
      </div>
      <p v-else style="color:var(--fg-3)">Aucune donnée disponible.</p>
    </Dialog>
  </AppLayout>
</template>

<style scoped>
.wh-row-btn { width:28px; height:28px; border-radius:var(--r-sm); border:1px solid var(--border-subtle); background:var(--bg-canvas); color:var(--fg-2); cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:all var(--dur-fast); }
.wh-row-btn:hover { background:var(--bg-sunken); color:var(--fg-1); border-color:var(--border-strong); }
.round-form { display:flex; flex-direction:column; gap:12px; }
.round-field { display:flex; flex-direction:column; gap:4px; }
.round-field label { font-size:12px; font-weight:500; color:var(--fg-2); }
.round-error { color:var(--red-500); font-size:11px; }
</style>
