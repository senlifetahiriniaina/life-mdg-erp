<script setup lang="ts">
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Select from 'primevue/select'
import Paginator from 'primevue/paginator'

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
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin',
  })
  if (res.ok) load(rounds.value.current_page)
}

async function completeRound(round: DeliveryRound) {
  const res = await fetch(`/api/v1/logistics/delivery-rounds/${round.id}/complete`, {
    method: 'POST',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin',
  })
  if (res.ok) load(rounds.value.current_page)
}

function reset() {
  statusFilter.value = null
  load()
}

const onPageChange = (e: { page: number }) => load(e.page + 1)

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
        <button class="btn btn-primary" @click="router.visit('/logistics/delivery-rounds/create')">
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
                <button class="wh-row-btn" title="Voir" @click="router.visit(`/logistics/delivery-rounds/${r.id}`)">
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
  </AppLayout>
</template>

<style scoped>
.wh-row-btn { width:28px; height:28px; border-radius:var(--r-sm); border:1px solid var(--border-subtle); background:var(--bg-canvas); color:var(--fg-2); cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:all var(--dur-fast); }
.wh-row-btn:hover { background:var(--bg-sunken); color:var(--fg-1); border-color:var(--border-strong); }
</style>
