<script setup lang="ts">
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Select from 'primevue/select'
import Paginator from 'primevue/paginator'

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
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin',
    body: JSON.stringify({ is_active: !carrier.is_active }),
  })
  load(carriers.value.current_page)
}

async function deleteCarrier(carrier: Carrier) {
  if (!confirm(`Supprimer le transporteur "${carrier.name}" ?`)) return
  const res = await fetch(`/api/v1/logistics/carriers/${carrier.id}`, {
    method: 'DELETE',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin',
  })
  if (res.ok) {
    load(carriers.value.current_page)
  } else {
    const data = await res.json()
    alert(data.message ?? 'Impossible de supprimer ce transporteur.')
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
</script>

<template>
  <AppLayout>
    <Head title="Transporteurs" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Transporteurs</h1>
        <p class="wh-page-subtitle">{{ carriers.total }} transporteur{{ carriers.total !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="router.visit('/logistics/carriers/create')">
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
                <button class="wh-row-btn" title="Performance" @click="router.visit(`/logistics/carriers/${c.id}/performance`)">
                  <i class="pi pi-chart-line" style="font-size:13px" />
                </button>
                <button class="wh-row-btn" title="Modifier" @click="router.visit(`/logistics/carriers/${c.id}/edit`)">
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
</style>
