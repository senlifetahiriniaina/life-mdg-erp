<template>
  <AppLayout title="Cross-Docking">
    <div class="p-6">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Cross-Docking</h1>
        <Button label="Planifier opération" icon="pi pi-plus" @click="showCreateDialog = true" />
      </div>

      <!-- Planned Operations Table -->
      <h2 class="text-lg font-semibold mb-3">Opérations planifiées</h2>
      <DataTable :value="operations" :loading="loading" paginator :rows="20" class="mb-8">
        <Column field="id" header="#" style="width: 60px" />
        <Column header="Produit">
          <template #body="{ data }">{{ data.product?.name ?? data.product_id }}</template>
        </Column>
        <Column field="qty" header="Quantité" />
        <Column header="Expédition entrante">
          <template #body="{ data }">{{ data.inbound_shipment_id ?? '—' }}</template>
        </Column>
        <Column header="Commande sortante">
          <template #body="{ data }">{{ data.outbound_order_id ?? '—' }}</template>
        </Column>
        <Column header="Statut">
          <template #body="{ data }">
            <Tag
              :value="data.status"
              :severity="statusSeverity(data.status)"
            />
          </template>
        </Column>
        <Column header="Actions">
          <template #body="{ data }">
            <Button
              v-if="data.status === 'planned'"
              label="Exécuter"
              size="small"
              severity="success"
              @click="executeOp(data.id)"
            />
          </template>
        </Column>
      </DataTable>

      <!-- AI Suggestions Section -->
      <div class="flex items-center gap-3 mb-3">
        <h2 class="text-lg font-semibold">Suggestions de cross-dock</h2>
        <Button
          label="Optimiser cross-dock IA"
          icon="pi pi-sparkles"
          severity="secondary"
          size="small"
          @click="loadSuggestions"
        />
      </div>
      <DataTable :value="suggestions" :loading="loadingSuggestions" class="mb-6">
        <Column field="product_name" header="Produit" />
        <Column field="inbound_qty" header="Qté reçue" />
        <Column field="suggestion" header="Recommandation" />
        <Column header="Action">
          <template #body="{ data }">
            <Button
              label="Cross-dock"
              size="small"
              @click="openCrossdockFromSuggestion(data)"
            />
          </template>
        </Column>
      </DataTable>

      <!-- Create Dialog -->
      <Dialog
        v-model:visible="showCreateDialog"
        header="Planifier une opération cross-dock"
        :style="{ width: '500px' }"
        modal
      >
        <div class="flex flex-col gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">ID Produit</label>
            <InputNumber v-model="form.product_id" class="w-full" :min="1" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Quantité</label>
            <InputNumber v-model="form.qty" class="w-full" :min="0.01" :step="0.01" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">ID Expédition entrante</label>
            <InputNumber v-model="form.inbound_shipment_id" class="w-full" :min="1" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">ID Commande sortante</label>
            <InputNumber v-model="form.outbound_order_id" class="w-full" :min="1" />
          </div>
          <div class="flex justify-end gap-2">
            <Button label="Annuler" severity="secondary" @click="showCreateDialog = false" />
            <Button label="Planifier" @click="createOp" />
          </div>
        </div>
      </Dialog>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Button, DataTable, Column, Dialog, Tag, InputNumber } from 'primevue'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

interface CrossdockOp {
  id: number
  product_id: number
  product?: { name: string }
  qty: string
  status: string
  inbound_shipment_id: number | null
  outbound_order_id: number | null
}

interface Suggestion {
  product_id: number
  product_name: string
  inbound_qty: number
  suggestion: string
}

const operations = ref<CrossdockOp[]>([])
const suggestions = ref<Suggestion[]>([])
const loading = ref(false)
const loadingSuggestions = ref(false)
const showCreateDialog = ref(false)
const form = ref({ product_id: null as number | null, qty: null as number | null, inbound_shipment_id: null as number | null, outbound_order_id: null as number | null })

async function loadOperations() {
  loading.value = true
  try {
    const res = await axios.get('/api/v1/inventory/crossdock')
    operations.value = res.data.data ?? res.data
  } finally {
    loading.value = false
  }
}

async function loadSuggestions() {
  loadingSuggestions.value = true
  try {
    const res = await axios.get('/api/v1/inventory/crossdock/suggestions')
    suggestions.value = res.data
  } finally {
    loadingSuggestions.value = false
  }
}

function statusSeverity(status: string) {
  return { planned: 'info', executed: 'success', cancelled: 'danger' }[status] ?? 'secondary'
}

async function executeOp(id: number) {
  await axios.post(`/api/v1/inventory/crossdock/${id}/execute`)
  await loadOperations()
}

async function createOp() {
  await axios.post('/api/v1/inventory/crossdock', form.value)
  showCreateDialog.value = false
  form.value = { product_id: null, qty: null, inbound_shipment_id: null, outbound_order_id: null }
  await loadOperations()
}

function openCrossdockFromSuggestion(suggestion: Suggestion) {
  form.value.product_id = suggestion.product_id
  form.value.qty = suggestion.inbound_qty
  showCreateDialog.value = true
}

onMounted(loadOperations)
</script>
