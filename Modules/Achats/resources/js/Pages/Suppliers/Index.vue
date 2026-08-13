<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">Suppliers</h1>
        <p class="mt-2 text-surface-600 dark:text-surface-400">Manage your supplier database and performance metrics</p>
      </div>
      <Link href="/suppliers/create" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
        + New Supplier
      </Link>
    </div>

    <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow">
      <div class="p-6 border-b border-gray-200 dark:border-surface-700">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="relative">
            <input
              v-model="search"
              type="text"
              placeholder="Search by name, code, or email..."
              class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
            />
          </div>
          <select
            v-model="filters.isActive"
            class="px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500"
          >
            <option value="">All Suppliers</option>
            <option value="true">Active</option>
            <option value="false">Inactive</option>
          </select>
          <button
            @click="loadSuppliers"
            class="px-4 py-2 bg-gray-200 text-surface-700 dark:text-surface-300 rounded-lg hover:bg-gray-300"
          >
            Search
          </button>
        </div>
      </div>

      <table class="w-full">
        <thead class="bg-gray-50 dark:bg-surface-800 dark:bg-surface-800 border-b border-gray-200 dark:border-surface-700">
          <tr>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Code</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Name</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Contact</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Email</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Currency</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Status</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!loading && suppliers.length === 0" class="border-b border-gray-200 dark:border-surface-700">
            <td colspan="7" class="px-6 py-8 text-center text-surface-500 dark:text-surface-400">
              No suppliers found. <Link href="/suppliers/create" class="text-primary-700 dark:text-primary-300 hover:underline">Create one now</Link>
            </td>
          </tr>
          <tr v-for="supplier in suppliers" :key="supplier.id" class="border-b border-gray-200 dark:border-surface-700 hover:bg-gray-50 dark:bg-surface-800 dark:bg-surface-800">
            <td class="px-6 py-4 text-sm font-mono text-surface-600 dark:text-surface-400">{{ supplier.code }}</td>
            <td class="px-6 py-4 text-sm font-medium text-surface-900 dark:text-surface-50">{{ supplier.name }}</td>
            <td class="px-6 py-4 text-sm text-surface-600 dark:text-surface-400">{{ supplier.contact_person }}</td>
            <td class="px-6 py-4 text-sm text-surface-600 dark:text-surface-400">{{ supplier.email }}</td>
            <td class="px-6 py-4 text-sm text-surface-600 dark:text-surface-400">{{ supplier.currency }}</td>
            <td class="px-6 py-4 text-sm">
              <span
                :class="[
                  'px-3 py-1 rounded-full text-xs font-medium',
                  supplier.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 dark:bg-surface-700 text-gray-800 dark:text-surface-100'
                ]"
              >
                {{ supplier.is_active ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td class="px-6 py-4 text-sm space-x-2">
              <Link :href="`/suppliers/${supplier.id}`" class="text-primary-700 dark:text-primary-300 hover:underline">
                View
              </Link>
              <Link :href="`/suppliers/${supplier.id}/edit`" class="text-primary-700 dark:text-primary-300 hover:underline">
                Edit
              </Link>
              <button
                @click="deleteSupplier(supplier.id)"
                class="text-red-700 dark:text-red-300 hover:underline"
              >
                Delete
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="pagination" class="flex items-center justify-between">
      <div class="text-sm text-surface-600 dark:text-surface-400">
        Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} suppliers
      </div>
      <div class="space-x-2">
        <button
          v-if="pagination.current_page > 1"
          @click="currentPage = pagination.current_page - 1; loadSuppliers()"
          class="px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg hover:bg-gray-50 dark:bg-surface-800 dark:bg-surface-800"
        >
          Previous
        </button>
        <button
          v-if="pagination.current_page < pagination.last_page"
          @click="currentPage = pagination.current_page + 1; loadSuppliers()"
          class="px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg hover:bg-gray-50 dark:bg-surface-800 dark:bg-surface-800"
        >
          Next
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'

const suppliers = ref([])
const loading = ref(false)
const pagination = ref(null)
const search = ref('')
const currentPage = ref(1)
const filters = ref({
  isActive: ''
})

const loadSuppliers = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page: currentPage.value,
      per_page: 15,
      search: search.value,
    })
    if (filters.isActive) {
      params.append('is_active', filters.isActive)
    }

    const response = await fetch(`/api/v1/achats/suppliers?${params}`, {
      headers: {
        'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]').content}`
      }
    })
    const data = await response.json()
    suppliers.value = data.data
    pagination.value = data.meta
  } catch (error) {
    console.error('Failed to load suppliers:', error)
  } finally {
    loading.value = false
  }
}

const deleteSupplier = async (id) => {
  if (!confirm('Are you sure you want to delete this supplier?')) return

  try {
    const response = await fetch(`/api/v1/achats/suppliers/${id}`, {
      method: 'DELETE',
      headers: {
        'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]').content}`
      }
    })
    if (response.ok) {
      loadSuppliers()
    }
  } catch (error) {
    console.error('Failed to delete supplier:', error)
  }
}

onMounted(() => {
  loadSuppliers()
})
</script>
