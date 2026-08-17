<template>
  <AppLayout>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Synchronisation E-Commerce</h1>
        <p class="text-surface-500 text-sm mt-1">Synchronisez votre stock vers votre boutique en ligne</p>
      </div>
      <Button label="Synchroniser tout le catalogue" icon="pi pi-refresh" :loading="syncingAll" @click="syncAll" />
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <Card>
        <template #content>
          <div class="text-sm text-surface-500">Dernière synchronisation</div>
          <div class="text-2xl font-bold mt-1">{{ lastSyncLabel }}</div>
        </template>
      </Card>
      <Card>
        <template #content>
          <div class="text-sm text-surface-500">Produits en attente de synchronisation</div>
          <div class="text-2xl font-bold mt-1" :class="status.pending_count > 0 ? 'text-orange-600' : 'text-green-600'">
            {{ status.pending_count ?? 0 }}
          </div>
        </template>
      </Card>
    </div>

    <p v-if="syncAllMessage" class="text-green-700 text-sm">{{ syncAllMessage }}</p>

    <Card>
      <template #header><div class="px-4 pt-4 font-semibold">Synchroniser un produit</div></template>
      <template #content>
        <div class="flex flex-wrap gap-3 items-end">
          <div class="flex flex-col gap-1">
            <label class="text-sm text-surface-600">Produit</label>
            <Select
              v-model="selectedProductId"
              :options="products"
              option-label="name"
              option-value="id"
              filter
              placeholder="Sélectionner un produit"
              class="w-72"
            />
          </div>
          <Button label="Synchroniser" icon="pi pi-sync" :loading="syncingProduct" :disabled="!selectedProductId" @click="syncProduct" />
        </div>
        <p v-if="syncProductMessage" class="text-green-700 text-sm mt-2">{{ syncProductMessage }}</p>
        <p v-if="syncProductError" class="text-red-600 text-sm mt-2">{{ syncProductError }}</p>
      </template>
    </Card>

    <DataTable :value="products" :loading="loadingProducts" stripedRows responsiveLayout="scroll">
      <template #header><div class="font-semibold">Catalogue</div></template>
      <Column field="sku" header="SKU" />
      <Column field="name" header="Produit" />
      <Column header="Statut de synchro">
        <template #body="{ data }">
          <Tag v-if="data.ecommerce_synced_at" value="Synchronisé" severity="success" />
          <Tag v-else value="Jamais synchronisé" severity="secondary" />
        </template>
      </Column>
    </DataTable>
  </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import Card from 'primevue/card'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Select from 'primevue/select'

const products = ref([])
const loadingProducts = ref(false)
const status = ref({ last_sync_at: null, pending_count: 0 })

const syncingAll = ref(false)
const syncAllMessage = ref(null)

const selectedProductId = ref(null)
const syncingProduct = ref(false)
const syncProductMessage = ref(null)
const syncProductError = ref(null)

const lastSyncLabel = computed(() => {
  if (!status.value.last_sync_at) return 'Jamais'
  return new Date(status.value.last_sync_at).toLocaleString('fr-FR')
})

const loadProducts = async () => {
  loadingProducts.value = true
  try {
    const { data } = await axios.get('/api/v1/inventory/products', { params: { per_page: 100 } })
    products.value = data.data ?? []
  } finally {
    loadingProducts.value = false
  }
}

const loadStatus = async () => {
  const { data } = await axios.get('/api/v1/inventory/sync/ecommerce/status')
  status.value = data
}

const syncAll = async () => {
  syncingAll.value = true
  syncAllMessage.value = null
  try {
    const { data } = await axios.post('/api/v1/inventory/sync/ecommerce/all')
    syncAllMessage.value = data.message
    await loadStatus()
  } finally {
    syncingAll.value = false
  }
}

const syncProduct = async () => {
  syncingProduct.value = true
  syncProductMessage.value = null
  syncProductError.value = null
  try {
    const { data } = await axios.post(`/api/v1/inventory/sync/ecommerce/product/${selectedProductId.value}`)
    syncProductMessage.value = data.message
    await Promise.all([loadProducts(), loadStatus()])
  } catch (e) {
    syncProductError.value = 'Impossible de synchroniser ce produit.'
  } finally {
    syncingProduct.value = false
  }
}

onMounted(() => {
  loadProducts()
  loadStatus()
})
</script>
