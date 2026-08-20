<template>
  <AppLayout>
    <Head title="Commandes de production" />

    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">
            Commandes de production
          </h1>
          <p class="text-surface-500 text-sm mt-1">
            Suivi simplifié d'un article entre "matières réunies" et "livré", en passant par la sous-traitance.
          </p>
        </div>
        <Button icon="pi pi-plus" label="Nouvelle commande" @click="openCreateModal" />
      </div>

      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-4 flex gap-3 items-center">
        <Select
          v-model="statusFilter"
          :options="statusOptions"
          option-label="label"
          option-value="value"
          placeholder="Tous statuts"
          show-clear
          class="w-56"
          @change="load"
        />
      </div>

      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 overflow-hidden">
        <DataTable :value="orders" :loading="loading" striped-rows class="p-datatable-sm">
          <Column field="reference" header="Référence" />
          <Column header="Fiche de chiffrage">
            <template #body="{ data }">{{ data.costing_sheet?.reference ?? '—' }}</template>
          </Column>
          <Column header="Sous-traitant">
            <template #body="{ data }">{{ data.subcontractor?.name ?? '—' }}</template>
          </Column>
          <Column field="quantity" header="Qté" />
          <Column header="Livraison prévue">
            <template #body="{ data }">{{ data.expected_delivery_at ?? '—' }}</template>
          </Column>
          <Column header="Statut">
            <template #body="{ data }">
              <Tag :value="statusLabel(data.status)" :severity="statusSeverity(data.status)" />
            </template>
          </Column>
          <Column header="Actions">
            <template #body="{ data }">
              <div class="flex gap-2 items-center">
                <Button
                  icon="pi pi-sitemap"
                  text
                  size="small"
                  title="Traçabilité"
                  @click="router.visit(`/inventory/production-orders/${data.id}/trace`)"
                />
                <Button
                  v-if="nextStatus(data.status)"
                  size="small"
                  :label="`→ ${statusLabel(nextStatus(data.status))}`"
                  @click="advance(data)"
                />
                <Button
                  v-if="!isTerminal(data.status)"
                  icon="pi pi-times"
                  text
                  size="small"
                  severity="danger"
                  title="Annuler"
                  @click="cancel(data)"
                />
                <Button icon="pi pi-trash" text size="small" severity="danger" @click="confirmDelete(data)" />
              </div>
            </template>
          </Column>
          <template #empty>
            <div class="text-center py-8 text-surface-400">Aucune commande de production pour le moment.</div>
          </template>
        </DataTable>
      </div>
    </div>

    <Dialog v-model:visible="showModal" header="Nouvelle commande de production" modal class="w-full max-w-lg">
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">Quantité</label>
          <InputNumber v-model="form.quantity" class="w-full" :min="1" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Fiche de chiffrage (optionnel)</label>
          <Select
            v-model="form.costing_sheet_id"
            :options="costingSheets"
            option-label="reference"
            option-value="id"
            show-clear
            placeholder="Aucune"
            class="w-full"
          />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Sous-traitant (optionnel)</label>
          <Select
            v-model="form.subcontractor_supplier_id"
            :options="suppliers"
            option-label="name"
            option-value="id"
            show-clear
            placeholder="Aucun"
            class="w-full"
          />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Livraison prévue (optionnel)</label>
          <input v-model="form.expected_delivery_at" type="date" class="w-full rounded border border-surface-300 dark:border-surface-600 bg-transparent px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Notes</label>
          <Textarea v-model="form.notes" class="w-full" rows="3" />
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" text @click="showModal = false" />
        <Button label="Créer" @click="submit" />
      </template>
    </Dialog>

    <ConfirmDialog />
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import Select from 'primevue/select'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import ConfirmDialog from 'primevue/confirmdialog'
import AppLayout from '@/Layouts/AppLayout.vue'

const orders = ref([])
const costingSheets = ref([])
const suppliers = ref([])
const loading = ref(false)
const statusFilter = ref(null)
const showModal = ref(false)
const confirmDialog = useConfirm()
const toast = useToast()

const form = reactive({
  quantity: 1,
  costing_sheet_id: null,
  subcontractor_supplier_id: null,
  expected_delivery_at: null,
  notes: '',
})

const PIPELINE = ['draft', 'materials_ready', 'in_subcontracting', 'quality_check', 'ready_for_delivery', 'delivered']

const statusOptions = [
  { label: 'Brouillon', value: 'draft' },
  { label: 'Matières réunies', value: 'materials_ready' },
  { label: 'En sous-traitance', value: 'in_subcontracting' },
  { label: 'Contrôle qualité', value: 'quality_check' },
  { label: 'Prêt à livrer', value: 'ready_for_delivery' },
  { label: 'Livré', value: 'delivered' },
  { label: 'Annulé', value: 'cancelled' },
]

function statusLabel(status) {
  return statusOptions.find((o) => o.value === status)?.label ?? status
}
function statusSeverity(status) {
  return {
    draft: 'secondary',
    materials_ready: 'info',
    in_subcontracting: 'warn',
    quality_check: 'warn',
    ready_for_delivery: 'info',
    delivered: 'success',
    cancelled: 'contrast',
  }[status] ?? 'secondary'
}
function isTerminal(status) {
  return status === 'delivered' || status === 'cancelled'
}
function nextStatus(status) {
  const idx = PIPELINE.indexOf(status)
  return idx >= 0 && idx < PIPELINE.length - 1 ? PIPELINE[idx + 1] : null
}

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/inventory/production-orders', {
      params: { status: statusFilter.value || undefined, per_page: 50 },
    })
    orders.value = data.data || []
  } finally {
    loading.value = false
  }
}

async function loadPickers() {
  try {
    const [sheetsRes, suppliersRes] = await Promise.all([
      axios.get('/api/v1/inventory/costing-sheets', { params: { per_page: 100 } }),
      axios.get('/api/v1/achats/suppliers', { params: { per_page: 100 } }),
    ])
    costingSheets.value = sheetsRes.data.data || []
    suppliers.value = suppliersRes.data.data || []
  } catch {
    // Pickers are optional convenience — a failure here shouldn't block the page.
  }
}

function openCreateModal() {
  form.quantity = 1
  form.costing_sheet_id = null
  form.subcontractor_supplier_id = null
  form.expected_delivery_at = null
  form.notes = ''
  showModal.value = true
}

async function submit() {
  try {
    await axios.post('/api/v1/inventory/production-orders', { ...form })
    showModal.value = false
    toast.add({ severity: 'success', summary: 'Commande créée', life: 3000 })
    await load()
  } catch {
    toast.add({ severity: 'error', summary: 'Échec de la création', life: 3000 })
  }
}

async function advance(order) {
  try {
    await axios.post(`/api/v1/inventory/production-orders/${order.id}/transition`, { status: nextStatus(order.status) })
    await load()
  } catch (err) {
    toast.add({ severity: 'error', summary: err.response?.data?.message || 'Transition refusée', life: 4000 })
  }
}

async function cancel(order) {
  confirmDialog.require({
    message: `Annuler la commande "${order.reference}" ?`,
    header: 'Confirmation',
    icon: 'pi pi-exclamation-triangle',
    accept: async () => {
      await axios.post(`/api/v1/inventory/production-orders/${order.id}/transition`, { status: 'cancelled' })
      await load()
    },
  })
}

function confirmDelete(order) {
  confirmDialog.require({
    message: `Supprimer la commande "${order.reference}" ?`,
    header: 'Confirmation',
    icon: 'pi pi-exclamation-triangle',
    accept: async () => {
      await axios.delete(`/api/v1/inventory/production-orders/${order.id}`)
      await load()
    },
  })
}

onMounted(() => {
  load()
  loadPickers()
})
</script>
