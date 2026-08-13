<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">Products</h1>
        <p class="mt-2 text-surface-600 dark:text-surface-400">Manage your product inventory</p>
      </div>
      <Link href="/inventory/products/create" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
        + New Product
      </Link>
    </div>

    <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
      <div class="flex gap-4 mb-6">
        <input
          v-model="search"
          type="text"
          placeholder="Search by name or SKU..."
          class="flex-1 px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
        />
        <select
          v-model="filters.status"
          class="px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
        >
          <option value="">All Status</option>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
        <select
          v-model="filters.category"
          class="px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
        >
          <option value="">All Categories</option>
          <option v-for="cat in categories" :key="cat.id" :value="cat.id">
            {{ cat.name }}
          </option>
        </select>
      </div>

      <div v-if="loading" class="text-center py-8">
        <p class="text-surface-500 dark:text-surface-400">Loading products...</p>
      </div>

      <table v-else class="w-full">
        <thead class="bg-gray-50 dark:bg-surface-800 dark:bg-surface-800 border-b border-gray-200 dark:border-surface-700">
          <tr>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">SKU</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Name</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Category</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Quantity</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Price</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Status</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="products.length === 0" class="border-b border-gray-200 dark:border-surface-700">
            <td colspan="7" class="px-6 py-8 text-center text-surface-500 dark:text-surface-400">
              No products found. <Link href="/inventory/products/create" class="text-primary-700 dark:text-primary-300 hover:underline">Create one now</Link>
            </td>
          </tr>
          <tr v-for="product in products" :key="product.id" class="border-b border-gray-200 dark:border-surface-700 hover:bg-gray-50 dark:bg-surface-800 dark:bg-surface-800">
            <td class="px-6 py-4 text-sm font-medium text-surface-900 dark:text-surface-50">{{ product.sku }}</td>
            <td class="px-6 py-4 text-sm text-surface-600 dark:text-surface-400">{{ product.name }}</td>
            <td class="px-6 py-4 text-sm text-surface-600 dark:text-surface-400">{{ product.category?.name || '—' }}</td>
            <td class="px-6 py-4 text-sm">
              <span :class="[
                'px-2 py-1 rounded text-xs font-medium',
                product.is_low_stock ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'
              ]">
                {{ product.quantity_on_hand }} {{ product.unit }}
              </span>
            </td>
            <td class="px-6 py-4 text-sm text-surface-600 dark:text-surface-400">${{ formatPrice(product.selling_price) }}</td>
            <td class="px-6 py-4 text-sm">
              <span :class="[
                'px-2 py-1 rounded text-xs font-medium',
                product.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 dark:bg-surface-700 text-gray-800 dark:text-surface-100'
              ]">
                {{ product.status }}
              </span>
            </td>
            <td class="px-6 py-4 text-sm space-x-2">
              <Link :href="`/inventory/products/${product.id}`" class="text-primary-700 dark:text-primary-300 hover:underline">View</Link>
              <Link :href="`/inventory/products/${product.id}/edit`" class="text-primary-700 dark:text-primary-300 hover:underline">Edit</Link>
              <button @click="deleteProduct(product.id)" class="text-red-700 dark:text-red-300 hover:underline">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'

const products = ref([])
const categories = ref([])
const loading = ref(false)
const search = ref('')
const filters = ref({
  status: '',
  category: ''
})

const formatPrice = (price) => {
  return parseFloat(price).toFixed(2)
}

const getAuthHeaders = () => ({
  'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]')?.content || ''}`,
  'Content-Type': 'application/json'
})

const loadProducts = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams({
      per_page: 50,
      search: search.value || undefined,
      status: filters.value.status || undefined,
      category: filters.value.category || undefined
    })

    const response = await fetch(`/api/v1/inventory/products?${params}`, {
      headers: getAuthHeaders()
    })

    if (response.ok) {
      const data = await response.json()
      products.value = data.data || []
    }
  } catch (error) {
    console.error('Failed to load products:', error)
  } finally {
    loading.value = false
  }
}

const loadCategories = async () => {
  try {
    const response = await fetch('/api/v1/inventory/categories?per_page=100', {
      headers: getAuthHeaders()
    })
    if (response.ok) {
      const data = await response.json()
      categories.value = data.data || []
    }
  } catch (error) {
    console.error('Failed to load categories:', error)
  }
}

const deleteProduct = async (id) => {
  if (!confirm('Are you sure you want to delete this product?')) return

  try {
    const response = await fetch(`/api/v1/inventory/products/${id}`, {
      method: 'DELETE',
      headers: getAuthHeaders()
    })
    if (response.ok) {
      loadProducts()
    }
  } catch (error) {
    console.error('Failed to delete product:', error)
  }
}

watch([search, () => filters.value.status, () => filters.value.category], () => {
  loadProducts()
})

onMounted(() => {
  loadProducts()
  loadCategories()
})
</script>
