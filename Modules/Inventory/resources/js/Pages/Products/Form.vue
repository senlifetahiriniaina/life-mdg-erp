<template>
  <div class="space-y-6">
    <div class="flex items-center gap-4">
      <Link href="/inventory/products" class="text-primary-700 dark:text-primary-300 hover:underline">Products</Link>
      <span class="text-surface-400 dark:text-surface-500">/</span>
      <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">{{ isEdit ? 'Edit Product' : 'Create Product' }}</h1>
    </div>

    <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
      <form @submit.prevent="handleSubmit" class="space-y-6">
        <div class="grid grid-cols-2 gap-6">
          <div>
            <label for="sku" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">SKU *</label>
            <input id="sku"
              v-model="formData.sku"
              type="text"
              required
              :disabled="isEdit"
              class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" :aria-describedby="errors.sku ? 'sku-error' : undefined" :aria-invalid="!!errors.sku" />
            <p id="sku-error" role="alert" v-if="errors.sku" class="text-red-700 dark:text-red-300 text-sm mt-1">{{ errors.sku }}</p>
          </div>

          <div>
            <label for="name" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">Name *</label>
            <input id="name"
              v-model="formData.name"
              type="text"
              required
              class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" :aria-describedby="errors.name ? 'name-error' : undefined" :aria-invalid="!!errors.name" />
            <p id="name-error" role="alert" v-if="errors.name" class="text-red-700 dark:text-red-300 text-sm mt-1">{{ errors.name }}</p>
          </div>
        </div>

        <div>
          <label for="description" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">Description</label>
          <textarea id="description"
            v-model="formData.description"
            rows="3"
            class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          ></textarea>
        </div>

        <div class="grid grid-cols-2 gap-6">
          <div>
            <label for="label-category" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">Category</label>
            <select id="label-category" v-model="formData.category_id" class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
              <option :value="null">Select Category</option>
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                {{ cat.name }}
              </option>
            </select>
          </div>

          <div>
            <label for="unit" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">Unit</label>
            <input id="unit"
              v-model="formData.unit"
              type="text"
              placeholder="units, kg, pieces"
              class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
        </div>

        <div class="grid grid-cols-3 gap-6">
          <div>
            <label for="cost-price" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">Cost Price *</label>
            <input id="cost-price"
              v-model.number="formData.cost_price"
              type="number"
              step="0.01"
              required
              class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>

          <div>
            <label for="selling-price" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">Selling Price *</label>
            <input id="selling-price"
              v-model.number="formData.selling_price"
              type="number"
              step="0.01"
              required
              class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
            <p class="text-sm text-surface-600 dark:text-surface-400 mt-1">Margin: {{ marginPercent }}%</p>
          </div>

          <div>
            <label for="stock" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">Stock *</label>
            <input id="stock"
              v-model.number="formData.quantity_on_hand"
              type="number"
              required
              class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-6">
          <div>
            <label for="reorder-level" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">Reorder Level *</label>
            <input id="reorder-level"
              v-model.number="formData.reorder_level"
              type="number"
              required
              class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>

          <div>
            <label for="reorder-quantity" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">Reorder Quantity</label>
            <input id="reorder-quantity"
              v-model.number="formData.reorder_quantity"
              type="number"
              class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
        </div>

        <div>
          <label for="label-status" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">Status</label>
          <select id="label-status" v-model="formData.status" class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>

        <div class="flex gap-3 pt-4">
          <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
            {{ isEdit ? 'Update Product' : 'Create Product' }}
          </button>
          <Link href="/inventory/products" class="px-6 py-2 bg-gray-200 text-gray-800 dark:text-surface-100 rounded-lg hover:bg-gray-300">
            Cancel
          </Link>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useRoute } from 'vue-router'

const route = useRoute()
const categories = ref([])
const errors = ref({})
const isEdit = computed(() => !!route.params.id)

const formData = ref({
  sku: '',
  name: '',
  description: '',
  category_id: null,
  cost_price: 0,
  selling_price: 0,
  quantity_on_hand: 0,
  reorder_level: 0,
  reorder_quantity: 0,
  unit: 'units',
  status: 'active'
})

const marginPercent = computed(() => {
  if (formData.value.cost_price === 0) return 0
  return ((formData.value.selling_price - formData.value.cost_price) / formData.value.cost_price * 100).toFixed(2)
})

const getAuthHeaders = () => ({
  'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]')?.content || ''}`,
  'Content-Type': 'application/json'
})

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

const loadProduct = async () => {
  try {
    const response = await fetch(`/api/v1/inventory/products/${route.params.id}`, {
      headers: getAuthHeaders()
    })
    if (response.ok) {
      const data = await response.json()
      formData.value = data.data
    }
  } catch (error) {
    console.error('Failed to load product:', error)
  }
}

const handleSubmit = async () => {
  const method = isEdit.value ? 'PATCH' : 'POST'
  const url = isEdit.value
    ? `/api/v1/inventory/products/${route.params.id}`
    : '/api/v1/inventory/products'

  try {
    const response = await fetch(url, {
      method,
      headers: getAuthHeaders(),
      body: JSON.stringify(formData.value)
    })

    if (response.ok) {
      window.location.href = '/inventory/products'
    } else {
      const data = await response.json()
      errors.value = data.errors || {}
    }
  } catch (error) {
    console.error('Failed to save product:', error)
  }
}

onMounted(() => {
  loadCategories()
  if (isEdit.value) {
    loadProduct()
  }
})
</script>
