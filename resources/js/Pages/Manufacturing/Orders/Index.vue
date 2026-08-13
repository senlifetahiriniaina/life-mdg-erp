<template>
  <AppLayout>
    <Head title="Ordres de fabrication" />

    <GuidedTour tour-id="manufacturing-orders" :steps="mfgTourSteps" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Production · Ordres de fabrication</h1>
        <p class="wh-page-subtitle">{{ orders.total }} ordre{{ orders.total !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="openCreate">
          <i class="pi pi-plus" style="font-size:13px" /> Nouvel ordre
        </button>
      </div>
    </div>

    <!-- KPI row -->
    <div class="wh-kpi-grid" style="margin-bottom:16px">
      <div class="wh-kpi" v-for="s in kpiList" :key="s.label">
        <div class="wh-kpi-label">{{ s.label }}</div>
        <div class="wh-kpi-num font-display">{{ s.value }}</div>
      </div>
    </div>

    <!-- Orders table -->
    <div class="wh-panel">
      <table class="wh-dt">
        <thead>
          <tr>
            <th>Référence</th>
            <th>Nomenclature</th>
            <th class="num">Qté prod. / total</th>
            <th>Statut</th>
            <th>Planifié</th>
            <th>Délai</th>
            <th>Créé par</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="order in orders.data" :key="order.id" class="wh-dt-row">
            <td><span style="font-family:var(--font-mono);font-size:13px;color:var(--fg-1)">{{ order.reference }}</span></td>
            <td style="color:var(--fg-2)">{{ order.bom?.name ?? '—' }}</td>
            <td class="num" style="color:var(--fg-2)">{{ order.quantity_produced }} / {{ order.quantity }}</td>
            <td>
              <span :class="statusBadge(order.status)" class="wh-badge">
                <span class="wh-badge-dot" />
                {{ statusLabel(order.status) }}
              </span>
            </td>
            <td style="color:var(--fg-3)">{{ formatDate(order.scheduled_date) }}</td>
            <td>
              <span :class="isOverdue(order) ? 'text-danger' : ''" style="font-size:14px">
                {{ formatDate(order.deadline) }}
              </span>
            </td>
            <td style="color:var(--fg-3)">{{ order.created_by?.name ?? '—' }}</td>
          </tr>
          <tr v-if="!orders.data.length">
            <td colspan="7" style="text-align:center;color:var(--fg-3);padding:32px 18px">Aucun ordre de fabrication trouvé.</td>
          </tr>
        </tbody>
      </table>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-top:1px solid var(--border-subtle)">
        <span style="font-size:13px;color:var(--fg-3)">{{ orders.total }} résultat{{ orders.total !== 1 ? 's' : '' }}</span>
        <Paginator
          :rows="orders.per_page"
          :total-records="orders.total"
          :first="(orders.current_page - 1) * orders.per_page"
        />
      </div>
    </div>

    <!-- Create Production Order Dialog -->
    <Dialog v-model:visible="showCreate" header="Nouvel ordre de fabrication" :modal="true" :style="{ width: '520px' }">
      <div class="mfg-form-body">
        <div class="mfg-form-grid">
          <div class="col-span-2">
            <label class="wh-label">Nomenclature (BOM) <span class="req-star">*</span></label>
            <Select
              v-model="form.bom_id"
              :options="bomOptions"
              option-label="label"
              option-value="value"
              placeholder="Sélectionner une nomenclature"
              class="w-full"
              :loading="loadingBoms"
            />
          </div>
          <div>
            <label class="wh-label">Quantité <span class="req-star">*</span></label>
            <InputNumber v-model="form.quantity" :min="1" class="w-full" />
          </div>
          <div>
            <label class="wh-label">Entrepôt</label>
            <Select
              v-model="form.warehouse_id"
              :options="warehouseOptions"
              option-label="label"
              option-value="value"
              placeholder="Sélectionner un entrepôt"
              class="w-full"
              :loading="loadingWarehouses"
            />
          </div>
          <div>
            <label class="wh-label">Date planifiée</label>
            <DatePicker v-model="form.scheduled_date" class="w-full" date-format="yy-mm-dd" />
          </div>
          <div>
            <label class="wh-label">Délai</label>
            <DatePicker v-model="form.deadline" class="w-full" date-format="yy-mm-dd" />
          </div>
          <div class="col-span-2">
            <label class="wh-label">Notes</label>
            <Textarea v-model="form.notes" class="w-full" rows="3" placeholder="Notes internes…" />
          </div>
        </div>
        <Message v-if="createError" severity="error" :closable="false">{{ createError }}</Message>
      </div>
      <template #footer>
        <button class="btn btn-secondary" @click="showCreate = false">Annuler</button>
        <button class="btn btn-primary" :disabled="saving" @click="submitCreate">
          <i class="pi pi-check" style="font-size:13px" />
          {{ saving ? 'Création…' : 'Créer l\'ordre' }}
        </button>
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { Paginator, Dialog, Select, InputNumber, DatePicker, Textarea, Message } from 'primevue'
import AppLayout from '@/Layouts/AppLayout.vue'
import GuidedTour from '@/Components/UI/GuidedTour.vue'
import { useHelpStore } from '@/stores/help'

const help = useHelpStore()

let echoChannel = null

onMounted(() => {
  if (window.Echo) {
    echoChannel = window.Echo.private('manufacturing')
      .listen('.ProductionStatusChanged', () => {
        router.reload({ only: ['orders', 'stats'] })
      })
  }
})

onUnmounted(() => {
  if (echoChannel) window.Echo?.leaveChannel('private-manufacturing')
})

const mfgTourSteps = [
  { tag: 'Manufacturing', icon: 'pi pi-cog',         title: 'Production Orders',   description: 'Each row is a manufacturing run. Orders move through draft → confirmed → in-progress → done. Click a row to see the full BOM explosion and material requirements.' },
  { tag: 'Manufacturing', icon: 'pi pi-list',         title: 'Bill of Materials',   description: 'A production order is linked to a BOM that lists every component needed. Use "Explode BOM" to preview material availability before confirming.' },
  { tag: 'Manufacturing', icon: 'pi pi-calendar',     title: 'Scheduled Date',      description: 'The scheduled date drives the MRP planning. Orders past their deadline are highlighted in red — reschedule or expedite components.' },
  { tag: 'Manufacturing', icon: 'pi pi-check-circle', title: 'Quality Checks',      description: 'When an order completes, quality check results are logged. Failed checks block automatic stock entry until a manager overrides.' },
  { tag: 'Manufacturing', icon: 'pi pi-chart-bar',    title: 'Yield Tracking',      description: 'The "Qty Produced" vs "Qty Planned" ratio shows production yield. Chronic under-yield signals a process or equipment issue.' },
]

const props = defineProps({
  orders: { type: Object, required: true },
  stats:  { type: Object, required: true },
})

const kpiList = computed(() => [
  { label: 'Total',      value: props.stats.total },
  { label: 'Brouillons', value: props.stats.draft },
  { label: 'En cours',   value: props.stats.in_progress },
  { label: 'Terminés',   value: props.stats.done },
])

const showCreate        = ref(false)
const saving            = ref(false)
const createError       = ref(null)
const loadingBoms       = ref(false)
const loadingWarehouses = ref(false)
const bomOptions        = ref([])
const warehouseOptions  = ref([])

const defaultForm = () => ({
  bom_id: null, warehouse_id: null, quantity: 1,
  scheduled_date: null, deadline: null, notes: '',
})
const form = ref(defaultForm())

const openCreate = async () => {
  form.value = defaultForm()
  createError.value = null
  showCreate.value = true
  if (!bomOptions.value.length) await fetchBoms()
  if (!warehouseOptions.value.length) await fetchWarehouses()
}

const fetchBoms = async () => {
  loadingBoms.value = true
  try {
    const res = await fetch('/api/v1/manufacturing/boms?per_page=100', { headers: { Accept: 'application/json' } })
    const json = await res.json()
    bomOptions.value = (json.data ?? []).map((b) => ({ label: b.reference ?? b.id, value: b.id }))
  } finally {
    loadingBoms.value = false
  }
}

const fetchWarehouses = async () => {
  loadingWarehouses.value = true
  try {
    const res = await fetch('/api/v1/inventory/warehouses?per_page=100', { headers: { Accept: 'application/json' } })
    const json = await res.json()
    warehouseOptions.value = (json.data ?? []).map((w) => ({ label: w.name, value: w.id }))
  } finally {
    loadingWarehouses.value = false
  }
}

const submitCreate = async () => {
  if (!form.value.bom_id || !form.value.quantity) {
    createError.value = 'La nomenclature et la quantité sont requises.'
    return
  }
  saving.value = true
  createError.value = null
  try {
    const res = await fetch('/api/v1/manufacturing/production-orders', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content },
      body: JSON.stringify({
        ...form.value,
        scheduled_date: form.value.scheduled_date ? new Date(form.value.scheduled_date).toISOString().split('T')[0] : null,
        deadline:       form.value.deadline       ? new Date(form.value.deadline).toISOString().split('T')[0]       : null,
      }),
    })
    if (!res.ok) {
      const err = await res.json()
      createError.value = err.message ?? 'Échec de la création de l\'ordre.'
      return
    }
    showCreate.value = false
    router.reload()
  } finally {
    saving.value = false
  }
}

const statusBadge = (s) => ({
  draft: 'wh-badge-slate', confirmed: 'wh-badge-blue', in_progress: 'wh-badge-amber',
  done: 'wh-badge-green', cancelled: 'wh-badge-red',
}[s] ?? 'wh-badge-slate')

const statusLabel = (s) => ({
  draft: 'Brouillon', confirmed: 'Confirmé', in_progress: 'En cours',
  done: 'Terminé', cancelled: 'Annulé',
}[s] ?? s)

const formatDate = (v) => v ? new Date(v).toLocaleDateString('fr-FR', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'

const isOverdue = (row) => {
  if (!row.deadline || ['done', 'cancelled'].includes(row.status)) return false
  return new Date(row.deadline) < new Date()
}
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; gap:16px; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; gap:8px; }
.btn { font-family:var(--font-sans); font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:background var(--dur-base) var(--ease-out); line-height:1.2; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover { background:var(--halo-700); }
.btn-primary:disabled { opacity:0.6; cursor:not-allowed; }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover { background:var(--bg-sunken); }
.wh-kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; }
@media (max-width:640px) { .wh-kpi-grid { grid-template-columns:repeat(2,1fr); } }
.wh-kpi { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); padding:16px 20px; }
.wh-kpi-label { font-size:12px; font-weight:500; color:var(--fg-3); letter-spacing:0.04em; text-transform:uppercase; margin-bottom:6px; }
.wh-kpi-num { font-size:26px; font-weight:700; color:var(--fg-1); line-height:1; }
.wh-panel { background:var(--bg-canvas); border:1px solid var(--border-subtle); border-radius:var(--r-lg); overflow:hidden; }
.wh-dt { width:100%; border-collapse:collapse; font-size:14px; }
.wh-dt thead th { text-align:left; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:var(--fg-3); font-weight:600; padding:10px 18px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sunken); }
.wh-dt thead th.num { text-align:right; }
.wh-dt-row td { padding:11px 18px; border-bottom:1px solid var(--border-subtle); }
.wh-dt-row td.num { text-align:right; }
.wh-dt-row:last-child td { border-bottom:0; }
.wh-dt-row { cursor:pointer; transition:background var(--dur-fast); }
.wh-dt-row:hover { background:var(--bg-sunken); }
.wh-badge { display:inline-flex; align-items:center; gap:5px; padding:2px 8px; border-radius:var(--r-pill); font-size:11px; font-weight:500; }
.wh-badge-dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
.wh-badge-green  { background:var(--success-bg); color:var(--success-fg); }
.wh-badge-blue   { background:var(--halo-50); color:var(--halo-700); }
.wh-badge-amber  { background:var(--warn-bg); color:var(--warn-fg); }
.wh-badge-red    { background:var(--danger-bg); color:var(--danger-fg); }
.wh-badge-slate  { background:var(--bg-sunken); color:var(--fg-2); }
.text-danger { color:var(--danger-fg); font-weight:500; }
.mfg-form-body { display:flex; flex-direction:column; gap:16px; padding:4px 0; }
.mfg-form-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.req-star { color:var(--danger-fg); }
</style>
