<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-4">
        <Link href="/inventory/products" class="text-primary-700 dark:text-primary-300 hover:underline">Products</Link>
        <span class="text-surface-400 dark:text-surface-500">/</span>
        <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">{{ product?.name }}</h1>
      </div>
      <div class="flex items-center gap-2">
        <Link :href="`/inventory/benchmark?product_id=${productId}`" class="px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg hover:bg-gray-50 dark:hover:bg-surface-700">
          Comparer les prix
        </Link>
        <Link href="/inventory/costing-sheets/create" class="px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg hover:bg-gray-50 dark:hover:bg-surface-700">
          Nouvelle fiche de chiffrage
        </Link>
        <Link :href="`/inventory/products/${productId}/edit`" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
          Edit Product
        </Link>
      </div>
    </div>

    <div v-if="product" class="grid grid-cols-3 gap-6">
      <!-- Product Details -->
      <div class="col-span-2 bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6 space-y-6">
        <div>
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-50 mb-4">Product Information</h2>
          <div class="grid grid-cols-2 gap-6">
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">SKU</p>
              <p class="text-lg font-medium text-surface-900 dark:text-surface-50">{{ product.sku }}</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Category</p>
              <p class="text-lg font-medium text-surface-900 dark:text-surface-50">{{ product.category?.name || '—' }}</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Unit</p>
              <p class="text-lg font-medium text-surface-900 dark:text-surface-50">{{ product.unit }}</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Status</p>
              <span :class="[
                'inline-block px-2 py-1 rounded text-sm font-medium',
                product.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 dark:bg-surface-700 text-gray-800 dark:text-surface-100'
              ]">
                {{ product.status }}
              </span>
            </div>
          </div>
        </div>

        <div v-if="product.description" class="border-t pt-6">
          <h3 class="font-semibold text-surface-900 dark:text-surface-50 mb-2">Description</h3>
          <p class="text-surface-600 dark:text-surface-400">{{ product.description }}</p>
        </div>

        <div class="border-t pt-6">
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-50 mb-4">Pricing & Margins</h2>
          <div class="grid grid-cols-3 gap-6">
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Cost Price</p>
              <p class="text-2xl font-bold text-surface-900 dark:text-surface-50">${{ formatPrice(product.cost_price) }}</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Selling Price</p>
              <p class="text-2xl font-bold text-primary-700 dark:text-primary-300">${{ formatPrice(product.selling_price) }}</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Margin</p>
              <p class="text-2xl font-bold text-green-700 dark:text-green-300">{{ product.margin_percent.toFixed(2) }}%</p>
            </div>
          </div>
        </div>

        <div class="border-t pt-6">
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-50 mb-4">Stock Information</h2>
          <div class="grid grid-cols-3 gap-6">
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Quantity on Hand</p>
              <p class="text-2xl font-bold text-surface-900 dark:text-surface-50">{{ product.quantity_on_hand }}</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Reorder Level</p>
              <p class="text-2xl font-bold text-surface-900 dark:text-surface-50">{{ product.reorder_level }}</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Status</p>
              <span :class="[
                'inline-block px-2 py-1 rounded text-sm font-medium',
                product.is_low_stock ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'
              ]">
                {{ product.is_low_stock ? 'Low Stock' : 'Adequate' }}
              </span>
            </div>
          </div>
        </div>

        <div class="border-t pt-6">
          <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-50 mb-4">Stock Movement History</h2>
          <div class="space-y-3">
            <div v-if="stockHistory.length === 0" class="text-surface-500 dark:text-surface-400 text-sm">
              No stock movements recorded
            </div>
            <div v-for="movement in stockHistory" :key="movement.id" class="flex items-center justify-between py-2 border-b border-gray-100">
              <div>
                <p class="font-medium text-surface-900 dark:text-surface-50">{{ movement.type }}</p>
                <p class="text-sm text-surface-600 dark:text-surface-400">{{ formatDate(movement.created_at) }}</p>
              </div>
              <div class="text-right">
                <p :class="[
                  'font-medium',
                  movement.quantity > 0 ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300'
                ]">
                  {{ movement.quantity > 0 ? '+' : '' }}{{ movement.quantity }}
                </p>
                <p v-if="movement.reason" class="text-sm text-surface-600 dark:text-surface-400">{{ movement.reason }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="space-y-6">
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Quick Actions</h3>
          <div class="space-y-2">
            <button class="w-full px-4 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 text-sm font-medium">
              Record Stock Movement
            </button>
            <button class="w-full px-4 py-2 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 text-sm font-medium">
              Export History
            </button>
          </div>
        </div>

        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
          <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Dates</h3>
          <div class="space-y-3">
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Created</p>
              <p class="text-sm font-medium text-surface-900 dark:text-surface-50">{{ formatDate(product.created_at) }}</p>
            </div>
            <div>
              <p class="text-sm text-surface-600 dark:text-surface-400">Last Updated</p>
              <p class="text-sm font-medium text-surface-900 dark:text-surface-50">{{ formatDate(product.updated_at) }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'

const product = ref(null)
const stockHistory = ref([])

const productId = computed(() => {
  return window.location.pathname.split('/').find((seg, idx, arr) => {
    return arr[idx - 1] === 'products' && seg && seg !== 'edit'
  })
})

const formatPrice = (price) => {
  return parseFloat(price).toFixed(2)
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const loadProduct = async () => {
  try {
    const { data } = await axios.get(`/api/v1/inventory/products/${productId.value}`)
    product.value = data.data
  } catch (error) {
    console.error('Failed to load product:', error)
  }
}

const loadStockHistory = async () => {
  try {
    const { data } = await axios.get(`/api/v1/inventory/products/${productId.value}/history`, { params: { limit: 20 } })
    stockHistory.value = data.data || []
  } catch (error) {
    console.error('Failed to load stock history:', error)
  }
}

onMounted(() => {
  loadProduct()
  loadStockHistory()
})
</script>
