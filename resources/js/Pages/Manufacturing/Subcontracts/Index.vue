<template>
  <AppLayout>
    <Head title="Subcontracts" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Manufacturing · Subcontracts</h1>
        <p class="wh-page-subtitle">Manage external supplier subcontracts</p>
      </div>
      <Button label="New Subcontract" icon="pi pi-plus" @click="openCreate" />
    </div>

    <div class="wh-panel">
      <DataTable :value="subcontracts" :loading="loading" paginator :rows="20" dataKey="id">
        <Column field="id" header="ID" style="width:60px" />
        <Column field="supplier_name" header="Supplier" />
        <Column field="component_product" header="Component" />
        <Column field="qty" header="Qty" style="width:80px" />
        <Column field="unit_cost" header="Unit Cost" style="width:100px">
          <template #body="{ data }">{{ formatCurrency(data.unit_cost) }}</template>
        </Column>
        <Column field="total_cost" header="Total" style="width:100px">
          <template #body="{ data }">{{ formatCurrency(data.total_cost) }}</template>
        </Column>
        <Column field="expected_delivery" header="Delivery" style="width:110px" />
        <Column field="status" header="Status" style="width:110px">
          <template #body="{ data }">
            <Tag :value="data.status" :severity="statusSeverity(data.status)" />
          </template>
        </Column>
        <Column header="Actions" style="width:200px">
          <template #body="{ data }">
            <div style="display:flex;gap:6px">
              <Button
                v-if="data.status === 'draft'"
                label="Send"
                size="small"
                severity="info"
                @click="sendSubcontract(data)"
              />
              <Button
                v-if="data.status === 'sent'"
                label="Receive"
                size="small"
                severity="success"
                @click="openReceive(data)"
              />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- Create Dialog -->
    <Dialog v-model:visible="showCreate" header="New Subcontract" :modal="true" style="width:480px">
      <div style="display:flex;flex-direction:column;gap:12px">
        <div>
          <label class="wh-label">Supplier Name *</label>
          <InputText v-model="form.supplier_name" class="w-full" />
        </div>
        <div>
          <label class="wh-label">Component / Product *</label>
          <InputText v-model="form.component_product" class="w-full" />
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div>
            <label class="wh-label">Quantity *</label>
            <InputNumber v-model="form.qty" :min="0.01" :maxFractionDigits="2" class="w-full" />
          </div>
          <div>
            <label class="wh-label">Unit Cost *</label>
            <InputNumber v-model="form.unit_cost" :min="0" :maxFractionDigits="2" mode="currency" currency="USD" class="w-full" />
          </div>
        </div>
        <div>
          <label class="wh-label">Expected Delivery</label>
          <InputText v-model="form.expected_delivery" type="date" class="w-full" />
        </div>
      </div>
      <template #footer>
        <Button label="Cancel" severity="secondary" @click="showCreate = false" />
        <Button label="Create" :loading="saving" @click="saveCreate" />
      </template>
    </Dialog>

    <!-- Receive Dialog -->
    <Dialog v-model:visible="showReceive" header="Receive Subcontract" :modal="true" style="width:400px">
      <p style="margin-bottom:12px;color:var(--fg-2)">Enter the quantity received from <strong>{{ selectedSub?.supplier_name }}</strong>.</p>
      <div>
        <label class="wh-label">Quantity Received *</label>
        <InputNumber v-model="receiveQty" :min="0.01" :maxFractionDigits="2" class="w-full" />
      </div>
      <template #footer>
        <Button label="Cancel" severity="secondary" @click="showReceive = false" />
        <Button label="Confirm Receipt" severity="success" :loading="saving" @click="confirmReceive" />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, DataTable, Column, Dialog, Tag, InputText, InputNumber } from 'primevue'
import axios from 'axios'

interface Subcontract {
  id: number
  supplier_name: string
  component_product: string
  qty: string
  unit_cost: string
  total_cost: string
  status: string
  expected_delivery: string | null
  sent_at: string | null
  received_at: string | null
}

const subcontracts = ref<Subcontract[]>([])
const loading      = ref(false)
const saving       = ref(false)
const showCreate   = ref(false)
const showReceive  = ref(false)
const selectedSub  = ref<Subcontract | null>(null)
const receiveQty   = ref<number>(0)

const form = ref({
  supplier_name: '',
  component_product: '',
  qty: 1,
  unit_cost: 0,
  expected_delivery: '',
})

async function loadSubcontracts(): Promise<void> {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/manufacturing/subcontracts')
    subcontracts.value = data.data ?? data
  } finally {
    loading.value = false
  }
}

function openCreate(): void {
  form.value = { supplier_name: '', component_product: '', qty: 1, unit_cost: 0, expected_delivery: '' }
  showCreate.value = true
}

async function saveCreate(): Promise<void> {
  saving.value = true
  try {
    await axios.post('/api/v1/manufacturing/subcontracts', form.value)
    await loadSubcontracts()
    showCreate.value = false
  } finally {
    saving.value = false
  }
}

async function sendSubcontract(sub: Subcontract): Promise<void> {
  await axios.post(`/api/v1/manufacturing/subcontracts/${sub.id}/send`)
  await loadSubcontracts()
}

function openReceive(sub: Subcontract): void {
  selectedSub.value = sub
  receiveQty.value  = parseFloat(sub.qty)
  showReceive.value = true
}

async function confirmReceive(): Promise<void> {
  if (!selectedSub.value) return
  saving.value = true
  try {
    await axios.post(`/api/v1/manufacturing/subcontracts/${selectedSub.value.id}/receive`, {
      qty_received: receiveQty.value,
    })
    await loadSubcontracts()
    showReceive.value = false
  } finally {
    saving.value = false
  }
}

function statusSeverity(status: string): string {
  const map: Record<string, string> = {
    draft:     'secondary',
    sent:      'info',
    received:  'success',
    cancelled: 'danger',
  }
  return map[status] ?? 'secondary'
}

function formatCurrency(value: string | number): string {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(value))
}

onMounted(loadSubcontracts)
</script>
