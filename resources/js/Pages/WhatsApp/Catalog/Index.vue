<template>
  <AppLayout>
    <Head title="WhatsApp Catalog" />

    <div class="p-6">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-surface-50 dark:text-white">Product Catalog</h1>
          <p class="text-sm text-gray-500 dark:text-surface-400 mt-1">Sync products to WhatsApp and send to customers</p>
        </div>
        <div class="flex gap-2">
          <Button label="Sync All" icon="pi pi-refresh" :loading="syncing" @click="syncAll" />
        </div>
      </div>

      <!-- KPI Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white dark:bg-surface-800 dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
          <p class="text-sm text-gray-500 dark:text-surface-400">Total Products</p>
          <p class="text-3xl font-bold text-gray-900 dark:text-surface-50 dark:text-white mt-1">{{ stats.total }}</p>
        </div>
        <div class="bg-white dark:bg-surface-800 dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
          <p class="text-sm text-gray-500 dark:text-surface-400">Synced</p>
          <p class="text-3xl font-bold text-green-600 mt-1">{{ stats.synced }}</p>
        </div>
        <div class="bg-white dark:bg-surface-800 dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700">
          <p class="text-sm text-gray-500 dark:text-surface-400">Out of Stock</p>
          <p class="text-3xl font-bold text-red-500 mt-1">{{ stats.outOfStock }}</p>
        </div>
      </div>

      <!-- Sync Progress -->
      <div v-if="syncing" class="bg-blue-50 dark:bg-surface-800 border border-blue-200 rounded-xl p-3 mb-4 flex items-center gap-3">
        <i class="pi pi-spin pi-spinner text-blue-500" />
        <span class="text-sm text-blue-700">Syncing all products to WhatsApp catalog…</span>
      </div>

      <!-- Filters -->
      <div class="flex gap-3 mb-4">
        <Select
          v-model="filterAvailability"
          :options="availabilityOptions"
          optionLabel="label"
          optionValue="value"
          placeholder="All availability"
          class="w-40"
          @change="fetchProducts()"
        />
        <Select
          v-model="filterSynced"
          :options="syncedOptions"
          optionLabel="label"
          optionValue="value"
          placeholder="All sync status"
          class="w-44"
          @change="fetchProducts()"
        />
      </div>

      <!-- Product Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <div
          v-for="product in products.data"
          :key="product.id"
          class="bg-white dark:bg-surface-800 dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden flex flex-col"
        >
          <!-- Thumbnail -->
          <div class="h-36 bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
            <img v-if="product.image_url" :src="product.image_url" :alt="product.name" class="h-full w-full object-cover" />
            <i v-else class="pi pi-box text-3xl text-gray-300" />
          </div>

          <div class="p-3 flex flex-col gap-2 flex-1">
            <p class="font-semibold text-sm text-gray-900 dark:text-surface-50 dark:text-white truncate">{{ product.name }}</p>
            <p class="text-base font-bold text-gray-700 dark:text-surface-100 dark:text-gray-200">
              {{ product.currency }} {{ Number(product.price).toFixed(2) }}
            </p>

            <div class="flex items-center gap-2 flex-wrap">
              <!-- Availability badge -->
              <span
                :class="product.availability === 'in_stock' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                class="px-2 py-0.5 rounded-full text-xs font-semibold"
              >
                {{ product.availability === 'in_stock' ? 'In Stock' : 'Out of Stock' }}
              </span>
              <!-- Sync badge -->
              <span
                :class="product.synced_at ? 'text-green-500' : 'text-gray-400'"
                class="flex items-center gap-1 text-xs"
              >
                <i :class="product.synced_at ? 'pi pi-check-circle' : 'pi pi-clock'" />
                {{ product.synced_at ? 'Synced' : 'Not synced' }}
              </span>
            </div>

            <div class="flex gap-2 mt-auto">
              <Button
                icon="pi pi-refresh"
                size="small"
                text
                v-tooltip="'Sync this product'"
                @click="syncOne(product)"
              />
              <Button
                icon="pi pi-send"
                size="small"
                text
                severity="success"
                v-tooltip="'Send to customer'"
                @click="openSend(product)"
              />
              <Button
                icon="pi pi-trash"
                size="small"
                text
                severity="danger"
                v-tooltip="'Remove from catalog'"
                @click="removeProduct(product)"
              />
            </div>
          </div>
        </div>

        <div v-if="products.data.length === 0 && !loading" class="col-span-full text-center py-16 text-gray-400">
          <i class="pi pi-box text-4xl mb-3 block" />
          <p>No products in catalog. Click "Sync All" to import from inventory.</p>
        </div>
      </div>

      <!-- Pagination -->
      <Paginator
        v-if="products.last_page > 1"
        :rows="products.per_page"
        :totalRecords="products.total"
        :first="(products.current_page - 1) * products.per_page"
        @page="onPage"
        class="mt-6"
      />
    </div>

    <!-- Send Dialog -->
    <Dialog v-model:visible="showSend" header="Send Product to Customer" modal class="w-96">
      <div v-if="sendingProduct" class="flex flex-col gap-4">
        <div class="flex items-center gap-3 bg-gray-50 dark:bg-surface-800 dark:bg-surface-800 rounded-lg p-3">
          <i class="pi pi-box text-xl text-gray-400" />
          <div>
            <p class="font-semibold text-sm">{{ sendingProduct.name }}</p>
            <p class="text-xs text-gray-500 dark:text-surface-400">{{ sendingProduct.currency }} {{ Number(sendingProduct.price).toFixed(2) }}</p>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Conversation ID</label>
          <InputText v-model="sendConvId" type="number" class="w-full" placeholder="Enter conversation ID" />
        </div>
      </div>
      <template #footer>
        <Button label="Cancel" text @click="showSend = false" />
        <Button label="Send" icon="pi pi-send" severity="success" @click="doSend" :loading="sendingLoading" />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, reactive } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

interface CatalogProduct {
  id: number
  product_id: number
  name: string
  description: string | null
  price: string
  currency: string
  image_url: string | null
  availability: string
  retailer_id: string
  synced_at: string | null
}

interface Paginated {
  data: CatalogProduct[]
  total: number
  per_page: number
  current_page: number
  last_page: number
}

const products = ref<Paginated>({ data: [], total: 0, per_page: 25, current_page: 1, last_page: 1 })
const loading  = ref(false)
const syncing  = ref(false)

const filterAvailability = ref('')
const filterSynced       = ref('')

const showSend        = ref(false)
const sendingProduct  = ref<CatalogProduct | null>(null)
const sendConvId      = ref('')
const sendingLoading  = ref(false)

const availabilityOptions = [
  { label: 'All', value: '' },
  { label: 'In Stock', value: 'in_stock' },
  { label: 'Out of Stock', value: 'out_of_stock' },
]

const syncedOptions = [
  { label: 'All', value: '' },
  { label: 'Synced', value: 'true' },
  { label: 'Not Synced', value: 'false' },
]

const stats = computed(() => ({
  total:      products.value.total,
  synced:     products.value.data.filter(p => p.synced_at).length,
  outOfStock: products.value.data.filter(p => p.availability === 'out_of_stock').length,
}))

async function fetchProducts(page = 1) {
  loading.value = true
  try {
    const res = await axios.get('/api/v1/whatsapp/catalog', {
      params: {
        page,
        availability: filterAvailability.value || undefined,
        synced: filterSynced.value || undefined,
      },
    })
    products.value = res.data
  } finally {
    loading.value = false
  }
}

async function syncAll() {
  syncing.value = true
  try {
    await axios.post('/api/v1/whatsapp/catalog/sync')
    setTimeout(() => {
      syncing.value = false
      fetchProducts()
    }, 3000)
  } catch {
    syncing.value = false
  }
}

async function syncOne(product: CatalogProduct) {
  await axios.post(`/api/v1/whatsapp/catalog/${product.id}/sync`)
  await fetchProducts()
}

async function removeProduct(product: CatalogProduct) {
  if (!confirm(`Remove "${product.name}" from catalog?`)) return
  await axios.delete(`/api/v1/whatsapp/catalog/${product.id}`)
  await fetchProducts()
}

function openSend(product: CatalogProduct) {
  sendingProduct.value = product
  sendConvId.value = ''
  showSend.value = true
}

async function doSend() {
  if (!sendingProduct.value || !sendConvId.value) return
  sendingLoading.value = true
  try {
    await axios.post('/api/v1/whatsapp/catalog/send', {
      conversation_id: Number(sendConvId.value),
      product_ids: [sendingProduct.value.id],
    })
    showSend.value = false
  } finally {
    sendingLoading.value = false
  }
}

function onPage(e: { page: number }) {
  fetchProducts(e.page + 1)
}

onMounted(() => fetchProducts())
</script>
