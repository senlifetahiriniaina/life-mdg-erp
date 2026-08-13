<template>
  <AppLayout>
    <Head title="Multi-location Inventory" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">POS · Multi-location Inventory</h1>
        <p class="wh-page-subtitle">Stock levels across all store locations</p>
      </div>
      <Button label="Transfer Stock" icon="pi pi-arrow-right-arrow-left" @click="showTransfer = true" />
    </div>

    <!-- Location selector -->
    <div class="wh-panel" style="margin-bottom:16px;display:flex;align-items:center;gap:16px">
      <label class="wh-label" style="margin:0">Location:</label>
      <Dropdown
        v-model="selectedLocation"
        :options="locations"
        optionLabel="label"
        optionValue="value"
        placeholder="All locations"
        style="min-width:220px"
        @change="loadStock"
      />
      <Button label="Sync" icon="pi pi-sync" severity="secondary" size="small" @click="syncLocations" />
    </div>

    <div class="wh-panel">
      <DataTable :value="stocks" :loading="loading" paginator :rows="30" dataKey="id">
        <Column field="location_name" header="Location" />
        <Column field="product_id" header="Product ID" style="width:100px" />
        <Column field="qty" header="Stock" style="width:100px" />
        <Column field="min_qty" header="Min. Qty" style="width:100px" />
        <Column header="Status" style="width:120px">
          <template #body="{ data }">
            <Tag v-if="isOutOfStock(data)" value="Rupture" severity="danger" />
            <Tag v-else-if="isLow(data)" value="Bas" severity="warning" />
            <Tag v-else value="OK" severity="success" />
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- Transfer Dialog -->
    <Dialog v-model:visible="showTransfer" header="Inter-store Transfer" :modal="true" style="width:440px">
      <div style="display:flex;flex-direction:column;gap:14px">
        <div>
          <label class="wh-label">Product ID *</label>
          <InputNumber v-model="transfer.product_id" :min="1" class="w-full" />
        </div>
        <div>
          <label class="wh-label">From Location *</label>
          <InputText v-model="transfer.from" placeholder="e.g. STORE-PARIS" class="w-full" />
        </div>
        <div>
          <label class="wh-label">To Location *</label>
          <InputText v-model="transfer.to" placeholder="e.g. STORE-LYON" class="w-full" />
        </div>
        <div>
          <label class="wh-label">Quantity *</label>
          <InputNumber v-model="transfer.qty" :min="0.01" :maxFractionDigits="2" class="w-full" />
        </div>
      </div>
      <template #footer>
        <Button label="Cancel" severity="secondary" @click="showTransfer = false" />
        <Button label="Transfer" :loading="transferring" @click="doTransfer" />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, DataTable, Column, Dialog, Tag, InputText, InputNumber, Dropdown } from 'primevue'
import axios from 'axios'

interface StockRow {
  id: number
  location_id: string
  location_name: string
  product_id: number | null
  qty: string
  min_qty: string
  is_low: boolean
  is_out: boolean
}

const stocks           = ref<StockRow[]>([])
const loading          = ref(false)
const showTransfer     = ref(false)
const transferring     = ref(false)
const selectedLocation = ref<string | null>(null)

const locations = [
  { label: 'Paris',   value: 'STORE-PARIS' },
  { label: 'Lyon',    value: 'STORE-LYON' },
  { label: 'Nice',    value: 'STORE-NICE' },
  { label: 'Marseille', value: 'STORE-MARSEILLE' },
]

const transfer = ref({ product_id: 1, from: '', to: '', qty: 1 })

async function loadStock(): Promise<void> {
  loading.value = true
  try {
    const params = selectedLocation.value ? { location_id: selectedLocation.value } : {}
    const { data } = await axios.get('/api/v1/pos/inventory', { params })
    stocks.value = data.stocks ?? data.data ?? data
  } finally {
    loading.value = false
  }
}

async function syncLocations(): Promise<void> {
  await axios.get('/api/v1/pos/inventory/sync')
  await loadStock()
}

async function doTransfer(): Promise<void> {
  transferring.value = true
  try {
    await axios.post('/api/v1/pos/inventory/transfer', {
      from_location_id: transfer.value.from,
      to_location_id:   transfer.value.to,
      product_id:       transfer.value.product_id,
      qty:              transfer.value.qty,
    })
    await loadStock()
    showTransfer.value = false
  } finally {
    transferring.value = false
  }
}

function isOutOfStock(row: StockRow): boolean {
  return parseFloat(row.qty) <= 0
}

function isLow(row: StockRow): boolean {
  const qty    = parseFloat(row.qty)
  const minQty = parseFloat(row.min_qty)
  return qty > 0 && qty <= minQty
}

onMounted(loadStock)
</script>
