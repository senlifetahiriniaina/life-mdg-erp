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

interface Carrier {
  id: number
  name: string
  code: string
  type: string
  country: string
  rating: number
  shipments_count: number
  is_active: boolean
  contact_email?: string
  contact_phone?: string
}

interface Paginated<T> { data: T[]; total: number; current_page: number; last_page: number; per_page: number }

const carriers = ref<Paginated<Carrier>>({ data: [], total: 0, current_page: 1, last_page: 1, per_page: 20 })
const loading  = ref(false)
const search   = ref('')
const typeFilter = ref<string | null>(null)
const activeFilter = ref<boolean | null>(null)

const typeOptions = [
  { label: 'Route', value: 'road' },
  { label: 'Aérien', value: 'air' },
  { label: 'Maritime', value: 'sea' },
  { label: 'Ferroviaire', value: 'rail' },
  { label: 'Multimodal', value: 'multimodal' },
]

const activeOptions = [
  { label: 'Actif', value: true },
  { label: 'Inactif', value: false },
]

// Chantier 19 Lot 4: this page's PUT/DELETE fetch() calls sent no CSRF token
// at all — unlike axios (used elsewhere in this app), which auto-attaches
// X-XSRF-TOKEN from the cookie, a raw fetch() does nothing on its own.
// Confirmed empirically (curl against a real php artisan serve instance,
// real login session, real Referer header matching config('sanctum.stateful'))
// that every mutation on this page returned 419 "CSRF token mismatch" — Pest
// tests can never catch this since VerifyCsrfToken::runningUnitTests()
// unconditionally bypasses the check whenever app()->runningUnitTests() is
// true. Same fix pattern already used by Inventory's Shipments/Index.vue.
function getCsrf(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? ''
}

function typeBadgeClass(type: string): string {
  const map: Record<string, string> = {
    road: 'badge-blue', air: 'badge-blue', sea: 'badge-blue',
    rail: 'badge-blue', multimodal: 'badge-orange',
  }
  return map[type] ?? 'badge-gray'
}

function typeIcon(type: string): string {
  const map: Record<string, string> = {
    road: 'pi pi-car', air: 'pi pi-send', sea: 'pi pi-map',
    rail: 'pi pi-directions', multimodal: 'pi pi-share-alt',
  }
  return map[type] ?? 'pi pi-truck'
}

function ratingStars(rating: number): string {
  const full = Math.floor(rating)
  return '★'.repeat(full) + '☆'.repeat(5 - full)
}

async function load(page = 1) {
  loading.value = true
  try {
    const params = new URLSearchParams()
    params.set('page', String(page))
    if (search.value) params.set('search', search.value)
    if (typeFilter.value) params.set('type', typeFilter.value)
    if (activeFilter.value !== null) params.set('is_active', String(activeFilter.value))
    const res = await fetch(`/api/v1/logistics/carriers?${params}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    carriers.value = await res.json()
  } finally { loading.value = false }
}

async function toggleActive(carrier: Carrier) {
  await fetch(`/api/v1/logistics/carriers/${carrier.id}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': getCsrf() },
    credentials: 'same-origin',
    body: JSON.stringify({ is_active: !carrier.is_active }),
  })
  load(carriers.value.current_page)
}

async function deleteCarrier(carrier: Carrier) {
  if (!confirm(`Supprimer le transporteur "${carrier.name}" ?`)) return
  const res = await fetch(`/api/v1/logistics/carriers/${carrier.id}`, {
    method: 'DELETE',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': getCsrf() },
    credentials: 'same-origin',
  })
  if (res.ok) {
    load(carriers.value.current_page)
  } else {
    const data = await res.json()
    alert(data.message ?? 'Impossible de supprimer ce transporteur.')
  }
}

// Chantier 19 Lot 4: "Nouveau transporteur"/"Modifier" both router.visit()'d
// to /logistics/carriers/create and /logistics/carriers/{id}/edit — neither
// route (nor a Create.vue/Edit.vue page) has ever existed anywhere in this
// app, confirmed via `php artisan route:list` and a repo-wide Glob — every
// click was a guaranteed 404, meaning a carrier could never actually be
// created or edited through the UI despite the real, already-tested
// CarrierController::store()/update() API existing. Fixed with a compact
// inline modal (matching the Categories/Warehouses/Channels precedent
// elsewhere in this session) instead of building 2 new full pages.
const showFormModal = ref(false)
const editingCarrier = ref<Carrier | null>(null)
const savingCarrier = ref(false)
const carrierForm = reactive({
  name: '', code: '', type: null as string | null,
  contact_email: '', contact_phone: '', country: '', tracking_url_template: '',
})
const carrierErrors = reactive<Record<string, string>>({})

function openCreateCarrier() {
  editingCarrier.value = null
  carrierForm.name = ''
  carrierForm.code = ''
  carrierForm.type = null
  carrierForm.contact_email = ''
  carrierForm.contact_phone = ''
  carrierForm.country = ''
  carrierForm.tracking_url_template = ''
  Object.keys(carrierErrors).forEach(k => delete carrierErrors[k])
  showFormModal.value = true
}

function openEditCarrier(carrier: Carrier) {
  editingCarrier.value = carrier
  carrierForm.name = carrier.name
  carrierForm.code = carrier.code ?? ''
  carrierForm.type = carrier.type ?? null
  carrierForm.contact_email = carrier.contact_email ?? ''
  carrierForm.contact_phone = carrier.contact_phone ?? ''
  carrierForm.country = carrier.country ?? ''
  carrierForm.tracking_url_template = ''
  Object.keys(carrierErrors).forEach(k => delete carrierErrors[k])
  showFormModal.value = true
}

const showPerfModal = ref(false)
const perfCarrier = ref<Carrier | null>(null)
const perfData = ref<Record<string, unknown> | null>(null)
const perfLoading = ref(false)

async function showPerformance(carrier: Carrier) {
  perfCarrier.value = carrier
  perfData.value = null
  showPerfModal.value = true
  perfLoading.value = true
  try {
    const res = await fetch(`/api/v1/logistics/carriers/${carrier.id}/performance`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    if (res.ok) perfData.value = await res.json()
  } finally {
    perfLoading.value = false
  }
}

async function submitCarrierForm() {
  savingCarrier.value = true
  Object.keys(carrierErrors).forEach(k => delete carrierErrors[k])
  const url = editingCarrier.value ? `/api/v1/logistics/carriers/${editingCarrier.value.id}` : '/api/v1/logistics/carriers'
  const method = editingCarrier.value ? 'PUT' : 'POST'
  try {
    const res = await fetch(url, {
      method,
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': getCsrf() },
      credentials: 'same-origin',
      body: JSON.stringify(carrierForm),
    })
    if (!res.ok) {
      const data = await res.json()
      if (data.errors) Object.assign(carrierErrors, data.errors)
      return
    }
    showFormModal.value = false
    load(carriers.value.current_page)
  } finally {
    savingCarrier.value = false
  }
}

function reset() {
  search.value = ''
  typeFilter.value = null
  activeFilter.value = null
  load()
}

const onPageChange = (e: { page: number }) => load(e.page + 1)

load()

// Chantier 32.23 (deep 14-layer audit, layer 13 — IA): real, routed backend
// (POST /api/v1/logistics/ai/assist), never called from this page before.
const { guidance } = useAiAssistant('Logistics', 'manage_carrier')
const showAiPanel = ref(true)
</script>

<template>
  <AppLayout>
    <Head title="Transporteurs" />
    <AIAssistantPanel v-if="showAiPanel" :guidance="guidance" @close="showAiPanel = false" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Transporteurs</h1>
        <p class="wh-page-subtitle">{{ carriers.total }} transporteur{{ carriers.total !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="openCreateCarrier">
          <i class="pi pi-plus" style="font-size:13px" /> Nouveau transporteur
        </button>
      </div>
    </div>

    <div class="wh-panel" style="margin-bottom:16px">
      <div style="padding:12px 16px;display:flex;flex-wrap:wrap;gap:10px;align-items:center">
        <div style="position:relative;flex:1;min-width:200px;max-width:320px">
          <i class="pi pi-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--fg-4);font-size:13px;pointer-events:none" />
          <input v-model="search" class="wh-filter-input" placeholder="Nom, code…" @input="load()" />
        </div>
        <Select v-model="typeFilter" :options="typeOptions" option-label="label" option-value="value" placeholder="Type" show-clear style="width:150px" @change="load()" />
        <Select v-model="activeFilter" :options="activeOptions" option-label="label" option-value="value" placeholder="Statut" show-clear style="width:130px" @change="load()" />
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
            <th>Transporteur</th>
            <th>Code</th>
            <th>Type</th>
            <th>Pays</th>
            <th>Note</th>
            <th>Expéditions</th>
            <th>Actif</th>
            <th style="width:100px">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!loading && carriers.data.length === 0">
            <td colspan="8" style="text-align:center;padding:48px;color:var(--fg-3);font-size:14px">Aucun transporteur</td>
          </tr>
          <tr v-for="c in carriers.data" :key="c.id" class="wh-dt-row">
            <td>
              <div style="font-weight:500;color:var(--fg-1)">{{ c.name }}</div>
              <div v-if="c.contact_email" style="font-size:11px;color:var(--fg-3)">{{ c.contact_email }}</div>
            </td>
            <td>
              <span style="font-family:var(--font-mono);font-size:12px;background:var(--bg-sunken);padding:2px 6px;border-radius:4px">{{ c.code }}</span>
            </td>
            <td>
              <span :class="['wh-badge', typeBadgeClass(c.type)]">
                <i :class="[typeIcon(c.type)]" style="font-size:10px;margin-right:2px" />
                {{ c.type }}
              </span>
            </td>
            <td style="color:var(--fg-2)">{{ c.country }}</td>
            <td>
              <span style="color:#f59e0b;letter-spacing:1px;font-size:13px">{{ ratingStars(c.rating) }}</span>
              <span style="font-size:11px;color:var(--fg-3);margin-left:4px">{{ c.rating?.toFixed(1) }}</span>
            </td>
            <td>{{ c.shipments_count ?? 0 }}</td>
            <td>
              <span
                :class="['wh-badge', c.is_active ? 'badge-green' : 'badge-gray']"
                style="cursor:pointer"
                @click="toggleActive(c)"
              >
                {{ c.is_active ? 'Actif' : 'Inactif' }}
              </span>
            </td>
            <td>
              <div style="display:flex;gap:4px">
                <button class="wh-row-btn" title="Performance" @click="showPerformance(c)">
                  <i class="pi pi-chart-line" style="font-size:13px" />
                </button>
                <button class="wh-row-btn" title="Modifier" @click="openEditCarrier(c)">
                  <i class="pi pi-pencil" style="font-size:13px" />
                </button>
                <button class="wh-row-btn wh-row-btn-danger" title="Supprimer" @click="deleteCarrier(c)">
                  <i class="pi pi-trash" style="font-size:13px" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <div
        v-if="carriers.total > carriers.per_page"
        style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-top:1px solid var(--border-subtle)"
      >
        <span style="font-size:13px;color:var(--fg-3)">
          {{ (carriers.current_page - 1) * carriers.per_page + 1 }}–{{ Math.min(carriers.current_page * carriers.per_page, carriers.total) }}
          sur {{ carriers.total }}
        </span>
        <Paginator
          :rows="carriers.per_page"
          :total-records="carriers.total"
          :first="(carriers.current_page - 1) * carriers.per_page"
          @page="onPageChange"
        />
      </div>
    </div>

    <Dialog v-model:visible="showFormModal" :header="editingCarrier ? 'Modifier le transporteur' : 'Nouveau transporteur'" modal style="width: 32rem">
      <form class="carrier-form" @submit.prevent="submitCarrierForm">
        <div class="carrier-field">
          <label>Nom *</label>
          <InputText v-model="carrierForm.name" :class="{ 'p-invalid': carrierErrors.name }" />
          <small v-if="carrierErrors.name" class="carrier-error">{{ carrierErrors.name }}</small>
        </div>
        <div class="carrier-field">
          <label>Code</label>
          <InputText v-model="carrierForm.code" :class="{ 'p-invalid': carrierErrors.code }" />
          <small v-if="carrierErrors.code" class="carrier-error">{{ carrierErrors.code }}</small>
        </div>
        <div class="carrier-field">
          <label>Type</label>
          <Select v-model="carrierForm.type" :options="typeOptions" option-label="label" option-value="value" show-clear class="w-full" />
        </div>
        <div class="carrier-field">
          <label>Pays (ISO 2)</label>
          <InputText v-model="carrierForm.country" maxlength="2" />
        </div>
        <div class="carrier-field">
          <label>Email de contact</label>
          <InputText v-model="carrierForm.contact_email" :class="{ 'p-invalid': carrierErrors.contact_email }" />
        </div>
        <div class="carrier-field">
          <label>Téléphone de contact</label>
          <InputText v-model="carrierForm.contact_phone" />
        </div>
        <div class="carrier-field">
          <label>URL de suivi (template, {tracking_number} sera remplacé)</label>
          <InputText v-model="carrierForm.tracking_url_template" />
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px">
          <button type="button" class="btn btn-secondary" @click="showFormModal = false">Annuler</button>
          <button type="submit" class="btn btn-primary" :disabled="savingCarrier">
            {{ editingCarrier ? 'Enregistrer' : 'Créer' }}
          </button>
        </div>
      </form>
    </Dialog>

    <Dialog v-model:visible="showPerfModal" :header="`Performance — ${perfCarrier?.name ?? ''}`" modal style="width: 28rem">
      <div v-if="perfLoading" style="text-align:center;padding:24px"><i class="pi pi-spin pi-spinner" /></div>
      <pre v-else-if="perfData" style="white-space:pre-wrap;font-size:12px">{{ JSON.stringify(perfData, null, 2) }}</pre>
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
.wh-row-btn-danger { color:var(--red-500); }
.wh-row-btn-danger:hover { background:var(--red-50); border-color:var(--red-300); color:var(--red-600); }
.carrier-form { display:flex; flex-direction:column; gap:12px; }
.carrier-field { display:flex; flex-direction:column; gap:4px; }
.carrier-field label { font-size:12px; font-weight:500; color:var(--fg-2); }
.carrier-error { color:var(--red-500); font-size:11px; }
</style>
