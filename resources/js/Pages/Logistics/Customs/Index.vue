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

// Chantier 19 Lot 4: this page's POST fetch() call (submit) sent no CSRF
// token at all — same fix pattern as Carriers/Index.vue, confirmed
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
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': getCsrf() },
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

// Chantier 19 Lot 4: "Nouvelle déclaration" and "Voir" both router.visit()'d
// to /logistics/customs/create and /logistics/customs/{id} — neither route
// nor page has ever existed anywhere in this app (confirmed via
// `php artisan route:list` + a repo-wide Glob), a guaranteed 404 on every
// click despite the real, already-tested CustomsDeclarationController
// store()/show() API existing. Fixed with a compact inline create modal and
// a read-only detail modal (matching Carriers/DeliveryRounds precedent).
const showFormModal = ref(false)
const savingDeclaration = ref(false)
const declForm = reactive({
  shipment_id: '', type: null as string | null, declared_value: '', currency: 'MGA',
  country_export: '', country_import: '', incoterm: null as string | null,
})
const declErrors = reactive<Record<string, string>>({})

const incotermOptions = ['EXW', 'FCA', 'CPT', 'CIP', 'DAP', 'DDP', 'FOB', 'CFR', 'CIF'].map(v => ({ label: v, value: v }))

function openCreateDeclaration() {
  declForm.shipment_id = ''
  declForm.type = null
  declForm.declared_value = ''
  declForm.currency = 'MGA'
  declForm.country_export = ''
  declForm.country_import = ''
  declForm.incoterm = null
  Object.keys(declErrors).forEach(k => delete declErrors[k])
  showFormModal.value = true
}

async function submitDeclarationForm() {
  savingDeclaration.value = true
  Object.keys(declErrors).forEach(k => delete declErrors[k])
  try {
    const res = await fetch('/api/v1/logistics/customs-declarations', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': getCsrf() },
      credentials: 'same-origin',
      body: JSON.stringify({ ...declForm, shipment_id: Number(declForm.shipment_id), declared_value: Number(declForm.declared_value) }),
    })
    if (!res.ok) {
      const data = await res.json()
      if (data.errors) Object.assign(declErrors, data.errors)
      return
    }
    showFormModal.value = false
    load(declarations.value.current_page)
  } finally {
    savingDeclaration.value = false
  }
}

const showDetailModal = ref(false)
const detailLoading = ref(false)
const detailDeclaration = ref<any>(null)

async function viewDeclaration(decl: CustomsDeclaration) {
  showDetailModal.value = true
  detailLoading.value = true
  detailDeclaration.value = null
  try {
    const res = await fetch(`/api/v1/logistics/customs-declarations/${decl.id}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    if (res.ok) detailDeclaration.value = await res.json()
  } finally {
    detailLoading.value = false
  }
}

load()

// Chantier 32.23 (deep 14-layer audit, layer 13 — IA): real, routed backend
// (POST /api/v1/logistics/ai/assist), never called from this page before.
const { guidance } = useAiAssistant('Logistics', 'manage_customs')
const showAiPanel = ref(true)
</script>

<template>
  <AppLayout>
    <Head title="Déclarations douanières" />
    <AIAssistantPanel v-if="showAiPanel" :guidance="guidance" @close="showAiPanel = false" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Déclarations douanières</h1>
        <p class="wh-page-subtitle">{{ declarations.total }} déclaration{{ declarations.total !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="openCreateDeclaration">
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
                <button class="wh-row-btn" title="Voir" @click="viewDeclaration(d)">
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

    <Dialog v-model:visible="showFormModal" header="Nouvelle déclaration douanière" modal style="width: 30rem">
      <form class="decl-form" @submit.prevent="submitDeclarationForm">
        <div class="decl-field">
          <label>Expédition (ID) *</label>
          <InputText v-model="declForm.shipment_id" :class="{ 'p-invalid': declErrors.shipment_id }" />
          <small v-if="declErrors.shipment_id" class="decl-error">{{ declErrors.shipment_id }}</small>
        </div>
        <div class="decl-field">
          <label>Type</label>
          <Select v-model="declForm.type" :options="typeOptions" option-label="label" option-value="value" show-clear class="w-full" />
        </div>
        <div class="decl-field">
          <label>Valeur déclarée *</label>
          <InputText v-model="declForm.declared_value" :class="{ 'p-invalid': declErrors.declared_value }" />
          <small v-if="declErrors.declared_value" class="decl-error">{{ declErrors.declared_value }}</small>
        </div>
        <div class="decl-field">
          <label>Devise (ISO 3) *</label>
          <InputText v-model="declForm.currency" maxlength="3" :class="{ 'p-invalid': declErrors.currency }" />
        </div>
        <div class="decl-field">
          <label>Pays export (ISO 2)</label>
          <InputText v-model="declForm.country_export" maxlength="2" />
        </div>
        <div class="decl-field">
          <label>Pays import (ISO 2)</label>
          <InputText v-model="declForm.country_import" maxlength="2" />
        </div>
        <div class="decl-field">
          <label>Incoterm</label>
          <Select v-model="declForm.incoterm" :options="incotermOptions" option-label="label" option-value="value" show-clear class="w-full" />
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px">
          <button type="button" class="btn btn-secondary" @click="showFormModal = false">Annuler</button>
          <button type="submit" class="btn btn-primary" :disabled="savingDeclaration">Créer</button>
        </div>
      </form>
    </Dialog>

    <Dialog v-model:visible="showDetailModal" :header="`Déclaration ${detailDeclaration?.reference ?? ''}`" modal style="width: 32rem">
      <div v-if="detailLoading" style="text-align:center;padding:24px"><i class="pi pi-spin pi-spinner" /></div>
      <div v-else-if="detailDeclaration">
        <p><strong>Type :</strong> {{ detailDeclaration.type }}</p>
        <p><strong>Statut :</strong> {{ detailDeclaration.status }}</p>
        <p><strong>Trajet :</strong> {{ detailDeclaration.country_export }} → {{ detailDeclaration.country_import }}</p>
        <p><strong>Incoterm :</strong> {{ detailDeclaration.incoterm ?? '—' }}</p>
        <p><strong>Valeur déclarée :</strong> {{ formatAmount(detailDeclaration.total_declared_value ?? detailDeclaration.declared_value, detailDeclaration.currency) }}</p>
        <p><strong>Code SH :</strong> {{ detailDeclaration.hs_code ?? '—' }}</p>
      </div>
      <p v-else style="color:var(--fg-3)">Aucune donnée disponible.</p>
    </Dialog>
  </AppLayout>
</template>

<style scoped>
.wh-row-btn { width:28px; height:28px; border-radius:var(--r-sm); border:1px solid var(--border-subtle); background:var(--bg-canvas); color:var(--fg-2); cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:all var(--dur-fast); }
.wh-row-btn:hover { background:var(--bg-sunken); color:var(--fg-1); border-color:var(--border-strong); }
.decl-form { display:flex; flex-direction:column; gap:12px; }
.decl-field { display:flex; flex-direction:column; gap:4px; }
.decl-field label { font-size:12px; font-weight:500; color:var(--fg-2); }
.decl-error { color:var(--red-500); font-size:11px; }
</style>
