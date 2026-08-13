import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useSupplierStore = defineStore('supplier', () => {
  const suppliers = ref([])
  const selectedSupplier = ref(null)
  const loading = ref(false)
  const error = ref('')
  const pagination = ref(null)

  const getAuthHeaders = () => ({
    'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]')?.content || ''}`,
    'Content-Type': 'application/json'
  })

  const supplierCount = computed(() => suppliers.value.length)

  const activeSuppliers = computed(() => suppliers.value.filter(s => s.is_active))

  const fetchSuppliers = async (params = {}) => {
    loading.value = true
    error.value = ''

    try {
      const searchParams = new URLSearchParams({
        page: params.page || 1,
        per_page: params.per_page || 50,
        ...params
      })

      const response = await fetch(`/api/v1/achats/suppliers?${searchParams}`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) throw new Error('Failed to fetch suppliers')

      const data = await response.json()
      suppliers.value = data.data
      pagination.value = data.meta
      return data
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const getSupplier = async (id) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/achats/suppliers/${id}`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) throw new Error('Failed to fetch supplier')

      const data = await response.json()
      selectedSupplier.value = data
      return data
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const createSupplier = async (supplierData) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch('/api/v1/achats/suppliers', {
        method: 'POST',
        headers: getAuthHeaders(),
        body: JSON.stringify(supplierData)
      })

      if (!response.ok) throw new Error('Failed to create supplier')

      const data = await response.json()
      suppliers.value.push(data)
      return data
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const updateSupplier = async (id, supplierData) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/achats/suppliers/${id}`, {
        method: 'PATCH',
        headers: getAuthHeaders(),
        body: JSON.stringify(supplierData)
      })

      if (!response.ok) throw new Error('Failed to update supplier')

      const data = await response.json()
      const index = suppliers.value.findIndex(s => s.id === id)
      if (index > -1) {
        suppliers.value[index] = data
      }
      if (selectedSupplier.value?.id === id) {
        selectedSupplier.value = data
      }
      return data
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const deleteSupplier = async (id) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/achats/suppliers/${id}`, {
        method: 'DELETE',
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) throw new Error('Failed to delete supplier')

      suppliers.value = suppliers.value.filter(s => s.id !== id)
      if (selectedSupplier.value?.id === id) {
        selectedSupplier.value = null
      }
      return true
    } catch (err) {
      error.value = err.message
      return false
    } finally {
      loading.value = false
    }
  }

  const clearSelected = () => {
    selectedSupplier.value = null
  }

  const clearError = () => {
    error.value = ''
  }

  return {
    suppliers,
    selectedSupplier,
    loading,
    error,
    pagination,
    supplierCount,
    activeSuppliers,
    fetchSuppliers,
    getSupplier,
    createSupplier,
    updateSupplier,
    deleteSupplier,
    clearSelected,
    clearError
  }
})
