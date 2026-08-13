<template>
  <AppLayout>
    <Head title="Vendor Portal" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Marketplace · Vendor Portal</h1>
        <p class="wh-page-subtitle">Manage marketplace vendors</p>
      </div>
    </div>

    <div class="wh-panel">
      <DataTable :value="vendors" :loading="loading" paginator :rows="20" dataKey="id" selectionMode="single">
        <Column field="name" header="Vendor Name" />
        <Column field="slug" header="Slug" style="width:180px" />
        <Column field="commission_pct" header="Commission %" style="width:120px">
          <template #body="{ data }">{{ data.commission_pct }}%</template>
        </Column>
        <Column field="vendor_products_count" header="Products" style="width:90px" />
        <Column field="status" header="Status" style="width:120px">
          <template #body="{ data }">
            <Tag :value="data.status" :severity="statusSeverity(data.status)" />
          </template>
        </Column>
        <Column header="Actions" style="width:200px">
          <template #body="{ data }">
            <div style="display:flex;gap:6px">
              <Button
                v-if="data.status === 'pending'"
                label="Approve"
                size="small"
                severity="success"
                @click="approve(data)"
              />
              <Button
                v-if="data.status === 'active'"
                label="Suspend"
                size="small"
                severity="danger"
                @click="suspend(data)"
              />
              <Button icon="pi pi-eye" text size="small" @click="openDetail(data)" />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- Detail Dialog -->
    <Dialog v-model:visible="showDetail" :header="selectedVendor?.name ?? 'Vendor'" :modal="true" style="width:600px">
      <div v-if="selectedVendor">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px">
          <div>
            <div class="wh-label">Status</div>
            <Tag :value="selectedVendor.status" :severity="statusSeverity(selectedVendor.status)" />
          </div>
          <div>
            <div class="wh-label">Commission</div>
            <div>{{ selectedVendor.commission_pct }}%</div>
          </div>
          <div style="grid-column:1/-1">
            <div class="wh-label">Description</div>
            <div style="color:var(--fg-2)">{{ selectedVendor.description ?? '—' }}</div>
          </div>
          <div>
            <div class="wh-label">IBAN</div>
            <div>{{ selectedVendor.bank_iban ?? '—' }}</div>
          </div>
          <div>
            <div class="wh-label">Approved At</div>
            <div>{{ selectedVendor.approved_at ?? '—' }}</div>
          </div>
        </div>

        <h4 style="font-size:13px;font-weight:600;margin-bottom:8px">Associated Products</h4>
        <DataTable :value="selectedVendor.vendor_products ?? []" :rows="5" paginator dataKey="id">
          <Column field="product_id" header="Product ID" style="width:100px" />
          <Column field="vendor_price" header="Vendor Price">
            <template #body="{ data }">{{ formatCurrency(data.vendor_price) }}</template>
          </Column>
          <Column field="vendor_stock" header="Stock" style="width:80px" />
          <Column field="active" header="Active" style="width:80px">
            <template #body="{ data }">
              <Tag :value="data.active ? 'Yes' : 'No'" :severity="data.active ? 'success' : 'secondary'" />
            </template>
          </Column>
        </DataTable>
      </div>
      <template #footer>
        <Button label="Close" severity="secondary" @click="showDetail = false" />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, DataTable, Column, Dialog, Tag } from 'primevue'
import axios from 'axios'

interface VendorProduct {
  id: number
  product_id: number
  vendor_price: string
  vendor_stock: number
  active: boolean
}

interface VendorPortal {
  id: number
  name: string
  slug: string
  description: string | null
  status: string
  commission_pct: string
  bank_iban: string | null
  approved_at: string | null
  vendor_products_count: number
  vendor_products?: VendorProduct[]
}

const vendors        = ref<VendorPortal[]>([])
const loading        = ref(false)
const showDetail     = ref(false)
const selectedVendor = ref<VendorPortal | null>(null)

async function load(): Promise<void> {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/ecommerce/vendor-portal')
    vendors.value = data.data ?? data
  } finally {
    loading.value = false
  }
}

async function approve(vendor: VendorPortal): Promise<void> {
  await axios.post(`/api/v1/ecommerce/vendor-portal/${vendor.id}/approve`)
  await load()
}

async function suspend(vendor: VendorPortal): Promise<void> {
  await axios.post(`/api/v1/ecommerce/vendor-portal/${vendor.id}/suspend`)
  await load()
}

async function openDetail(vendor: VendorPortal): Promise<void> {
  const { data } = await axios.get(`/api/v1/ecommerce/vendor-portal/${vendor.id}`)
  selectedVendor.value = data
  showDetail.value     = true
}

function statusSeverity(status: string): string {
  const map: Record<string, string> = {
    pending:   'warning',
    active:    'success',
    suspended: 'danger',
  }
  return map[status] ?? 'secondary'
}

function formatCurrency(value: string | number): string {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(value))
}

onMounted(load)
</script>
