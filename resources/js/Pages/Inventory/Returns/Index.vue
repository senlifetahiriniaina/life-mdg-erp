<template>
  <AppLayout title="Gestion des retours (RMA)">
    <div class="p-6">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Gestion des retours (RMA)</h1>
        <Button label="Nouvelle demande de retour" icon="pi pi-plus" @click="showCreateDialog = true" />
      </div>

      <!-- RMA DataTable -->
      <DataTable :value="rmas" :loading="loading" paginator :rows="20">
        <Column field="reference" header="Référence" />
        <Column field="customer_name" header="Client" />
        <Column field="reason" header="Raison" style="max-width: 250px" />
        <Column field="return_method" header="Méthode">
          <template #body="{ data }">
            <Tag :value="data.return_method" severity="secondary" />
          </template>
        </Column>
        <Column header="Statut (Pipeline)">
          <template #body="{ data }">
            <div class="flex gap-1 flex-wrap">
              <span
                v-for="step in pipeline"
                :key="step"
                class="px-2 py-0.5 rounded-full text-xs font-medium"
                :class="stepClass(step, data.status)"
              >{{ step }}</span>
            </div>
          </template>
        </Column>
        <Column header="Actions">
          <template #body="{ data }">
            <div class="flex gap-2">
              <Button
                v-if="data.status === 'requested'"
                label="Approuver"
                size="small"
                severity="success"
                @click="approveRma(data.id)"
              />
              <Button
                v-if="data.status === 'approved'"
                label="Réceptionner"
                size="small"
                severity="info"
                @click="receiveRma(data.id)"
              />
              <Button
                v-if="['received', 'inspected'].includes(data.status)"
                label="Inspecter"
                size="small"
                severity="warning"
                @click="openInspectDialog(data)"
              />
            </div>
          </template>
        </Column>
      </DataTable>

      <!-- Create RMA Dialog -->
      <Dialog
        v-model:visible="showCreateDialog"
        header="Nouvelle demande de retour"
        :style="{ width: '550px' }"
        modal
      >
        <div class="flex flex-col gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Client</label>
            <InputText v-model="form.customer_name" class="w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Raison du retour</label>
            <Textarea v-model="form.reason" class="w-full" rows="3" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Méthode de retour</label>
            <Dropdown
              v-model="form.return_method"
              :options="returnMethods"
              option-label="label"
              option-value="value"
              class="w-full"
            />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Articles retournés</label>
            <div class="flex gap-2 mb-2">
              <InputNumber v-model="newItem.product_id" placeholder="ID produit" :min="1" style="width: 120px" />
              <InputNumber v-model="newItem.qty" placeholder="Qté" :min="1" style="width: 80px" />
              <InputNumber v-model="newItem.price" placeholder="Prix" :min="0" :step="0.01" style="width: 100px" />
              <Button label="Ajouter" size="small" @click="addItem" />
            </div>
            <ul class="text-sm text-surface-600">
              <li v-for="(item, i) in form.items" :key="i">
                Produit #{{ item.product_id }} — {{ item.qty }} u. — {{ item.price }} €
              </li>
            </ul>
          </div>
          <div class="flex justify-end gap-2">
            <Button label="Annuler" severity="secondary" @click="showCreateDialog = false" />
            <Button label="Créer" @click="createRma" />
          </div>
        </div>
      </Dialog>

      <!-- Inspect / Refund Dialog -->
      <Dialog
        v-model:visible="showInspectDialog"
        :header="'Inspection RMA #' + (selectedRma?.id ?? '')"
        :style="{ width: '550px' }"
        modal
      >
        <div v-if="selectedRma" class="flex flex-col gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Photos (upload UI)</label>
            <div class="border-2 border-dashed border-surface-300 rounded-lg p-6 text-center text-surface-500">
              <span class="pi pi-upload text-2xl mb-2 block" />
              <p>Glissez des photos ici ou cliquez pour sélectionner</p>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Raison d'inspection</label>
            <Textarea v-model="inspectReason" class="w-full" rows="2" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Décision</label>
            <Dropdown
              v-model="inspectDecision"
              :options="returnMethods"
              option-label="label"
              option-value="value"
              class="w-full"
            />
          </div>
          <div class="flex justify-end gap-2">
            <Button label="Annuler" severity="secondary" @click="showInspectDialog = false" />
            <Button label="Traiter remboursement" severity="danger" @click="processRefund(selectedRma.id)" />
          </div>
        </div>
      </Dialog>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Button, DataTable, Column, Dialog, Tag, InputText, Textarea, Select as Dropdown, InputNumber } from 'primevue'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

interface RmaItem {
  product_id: number
  qty: number
  price: number
}

interface Rma {
  id: number
  reference: string
  customer_name: string
  reason: string
  status: string
  return_method: string
  items: RmaItem[]
}

const rmas = ref<Rma[]>([])
const loading = ref(false)
const showCreateDialog = ref(false)
const showInspectDialog = ref(false)
const selectedRma = ref<Rma | null>(null)
const inspectReason = ref('')
const inspectDecision = ref('refund')

const pipeline = ['requested', 'approved', 'received', 'inspected', 'refunded', 'closed']
const returnMethods = [
  { label: 'Remboursement', value: 'refund' },
  { label: 'Échange', value: 'exchange' },
  { label: 'Avoir', value: 'credit' },
]

const form = ref({
  customer_name: '',
  reason: '',
  return_method: 'refund',
  items: [] as RmaItem[],
})

const newItem = ref<RmaItem>({ product_id: 0, qty: 1, price: 0 })

function stepClass(step: string, currentStatus: string): string {
  const idx = pipeline.indexOf(step)
  const currentIdx = pipeline.indexOf(currentStatus)
  if (idx < currentIdx) return 'bg-green-100 text-green-700'
  if (idx === currentIdx) return 'bg-blue-100 text-blue-700 font-bold'
  return 'bg-surface-100 text-surface-400'
}

async function loadRmas() {
  loading.value = true
  try {
    const res = await axios.get('/api/v1/inventory/rmas')
    rmas.value = res.data.data ?? res.data
  } finally {
    loading.value = false
  }
}

function addItem() {
  if (newItem.value.product_id > 0 && newItem.value.qty > 0) {
    form.value.items.push({ ...newItem.value })
    newItem.value = { product_id: 0, qty: 1, price: 0 }
  }
}

async function createRma() {
  await axios.post('/api/v1/inventory/rmas', form.value)
  showCreateDialog.value = false
  form.value = { customer_name: '', reason: '', return_method: 'refund', items: [] }
  await loadRmas()
}

async function approveRma(id: number) {
  await axios.post(`/api/v1/inventory/rmas/${id}/approve`)
  await loadRmas()
}

async function receiveRma(id: number) {
  await axios.post(`/api/v1/inventory/rmas/${id}/receive`)
  await loadRmas()
}

function openInspectDialog(rma: Rma) {
  selectedRma.value = rma
  showInspectDialog.value = true
}

async function processRefund(id: number) {
  await axios.post(`/api/v1/inventory/rmas/${id}/refund`)
  showInspectDialog.value = false
  await loadRmas()
}

onMounted(loadRmas)
</script>
