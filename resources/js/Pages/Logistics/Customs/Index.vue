<script setup lang="ts">
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Select from 'primevue/select'
import Paginator from 'primevue/paginator'

interface CustomsDeclaration {
  id: number
  reference: string
  type: string
  status: string
  country_export: string
  country_import: string
  incoterm: string
  total_declared_value: number
  currency: string
  submitted_at?: string
  shipment?: { id: number; reference: string }
}

interface Paginated<T> { data: T[]; total: number; current_page: number; last_page: number; per_page: number }

const declarations = ref<Paginated<CustomsDeclaration>>({ data: [], total: 0, current_page: 1, last_page: 1, per_page: 20 })
const loading      = ref(false)
const statusFilter = ref<string | null>(null)
const typeFilter   = ref<string | null>(null)

const statusOptions = [
  { label: 'Brouillon', value: 'draft' },
  { label: 'Soumis', value: 'submitted' },
  { label: 'En cours', value: 'processing' },
  { label: 'Dédouané', value: 'cleared' },
  { label: 'Retenu', value: 'held' },
  { label: 'Rejeté', value: 'rejected' },
]

const typeOptions = [
  { label: 'Export', value: 'export' },
  { label: 'Import', value: 'import' },
  { label: 'Transit', value: 'transit' },
]

function statusBadgeClass(status: string): string {
  const map: Record<string, string> = {
    draft: 'badge-gray', submitted: 'badge-blue', processing: 'badge-orange',
    cleared: 'badge-green', held: 'badge-orange', rejected: 'badge-red',
  }
  return map[status] ?? 'badge-gray'
}

function typeBadgeClass(type: string): string {
  const map: Record<string, string> = { export: 'badge-green', import: 'badge-blue', transit: 'badge-blue' }
  return map[type] ?? 'badge-gray'
}

function formatDate(d?: string): string {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('fr-FR')
}

function formatAmount(amount: number, currency: string): string {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency }).format(amount)
}

async function load(page = 1) {
  loading.value = true
  try {
    const params = new URLSearchParams()
    params.set('page', String(page))
    if (statusFilter.value) params.set('status', statusFilter.value)
    if (typeFilter.value) params.set('type', typeFilter.value)
    const res = await fetch(`/api/v1/logistics/customs-declarations?${params}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    declarations.value = await res.json()
  } finally { loading.value = false }
}

async function submitDeclaration(decl: CustomsDeclaration) {
  const res = await fetch(`/api/v1/logistics/customs-declarations/${decl.id}/submit`, {
    method: 'POST',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin',
  })
  if (res.ok) load(declarations.value.current_page)
}

function reset() {
  statusFilter.value = null
  typeFilter.value = null
  load()
}

const onPageChange = (e: { page: number }) => load(e.page + 1)

load()
</script>

<template>
  <AppLayout>
    <Head title="Déclarations douanières" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Déclarations douanières</h1>
        <p class="wh-page-subtitle">{{ declarations.total }} déclaration{{ declarations.total !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="router.visit('/logistics/customs/create')">
          <i class="pi pi-plus" style="font-size:13px" /> Nouvelle déclaration
        </button>
      </div>
    </div>

    <div class="wh-panel" style="margin-bottom:16px">
      <div style="padding:12px 16px;display:flex;flex-wrap:wrap;gap:10px;align-items:center">
        <Select v-model="typeFilter" :options="typeOptions" option-label="label" option-value="value" placeholder="Type" show-clear style="width:140px" @change="load()" />
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
            <th>Type</th>
            <th>Trajet</th>
            <th>Incoterm</th>
            <th>Valeur déclarée</th>
            <th>Statut</th>
            <th>Soumis le</th>
            <th style="width:80px">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!loading && declarations.data.length === 0">
            <td colspan="8" style="text-align:center;padding:48px;color:var(--fg-3);font-size:14px">Aucune déclaration</td>
          </tr>
          <tr v-for="d in declarations.data" :key="d.id" class="wh-dt-row">
            <td>
              <span style="font-family:var(--font-mono);font-weight:600;color:var(--halo-600)">{{ d.reference }}</span>
            </td>
            <td><span :class="['wh-badge', typeBadgeClass(d.type)]">{{ d.type }}</span></td>
            <td>
              <div style="display:flex;align-items:center;gap:6px;font-size:13px">
                <span style="background:var(--bg-sunken);padding:1px 6px;border-radius:4px;font-family:var(--font-mono)">{{ d.country_export }}</span>
                <i class="pi pi-arrow-right" style="font-size:10px;color:var(--fg-3)" />
                <span style="background:var(--bg-sunken);padding:1px 6px;border-radius:4px;font-family:var(--font-mono)">{{ d.country_import }}</span>
              </div>
            </td>
            <td>
              <span style="font-size:12px;font-weight:600;color:var(--fg-2)">{{ d.incoterm }}</span>
            </td>
            <td style="font-weight:600">{{ formatAmount(d.total_declared_value, d.currency) }}</td>
            <td><span :class="['wh-badge', statusBadgeClass(d.status)]">{{ d.status }}</span></td>
            <td style="font-size:12px;color:var(--fg-3)">{{ formatDate(d.submitted_at) }}</td>
            <td>
              <div style="display:flex;gap:4px">
                <button
                  v-if="d.status === 'draft'"
                  class="wh-row-btn"
                  title="Soumettre"
                  @click="submitDeclaration(d)"
                >
                  <i class="pi pi-send" style="font-size:13px" />
                </button>
                <button class="wh-row-btn" title="Voir" @click="router.visit(`/logistics/customs/${d.id}`)">
                  <i class="pi pi-eye" style="font-size:13px" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <div
        v-if="declarations.total > declarations.per_page"
        style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-top:1px solid var(--border-subtle)"
      >
        <span style="font-size:13px;color:var(--fg-3)">
          {{ (declarations.current_page - 1) * declarations.per_page + 1 }}–{{ Math.min(declarations.current_page * declarations.per_page, declarations.total) }}
          sur {{ declarations.total }}
        </span>
        <Paginator
          :rows="declarations.per_page"
          :total-records="declarations.total"
          :first="(declarations.current_page - 1) * declarations.per_page"
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
