import { ref } from 'vue'

export function useSuppliers() {
  const loading = ref(false)
  const error = ref('')

  const getAuthHeaders = () => ({
    'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]').content}`,
    'Content-Type': 'application/json'
  })

  const fetchSuppliers = async (params = {}) => {
    loading.value = true
    error.value = ''

    try {
      const searchParams = new URLSearchParams({
        page: params.page || 1,
        per_page: params.per_page || 15,
        ...params
      })

      const response = await fetch(`/api/v1/achats/suppliers?${searchParams}`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) {
        throw new Error('Failed to fetch suppliers')
      }

      return await response.json()
    } catch (err) {
      error.value = err.message
      console.error('Error fetching suppliers:', err)
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

      if (!response.ok) {
        throw new Error('Failed to fetch supplier')
      }

      return await response.json()
    } catch (err) {
      error.value = err.message
      console.error('Error fetching supplier:', err)
      return null
    } finally {
      loading.value = false
    }
  }

  const createSupplier = async (data) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch('/api/v1/achats/suppliers', {
        method: 'POST',
        headers: getAuthHeaders(),
        body: JSON.stringify(data)
      })

      if (!response.ok) {
        const errorData = await response.json()
        throw new Error(errorData.message || 'Failed to create supplier')
      }

      return await response.json()
    } catch (err) {
      error.value = err.message
      console.error('Error creating supplier:', err)
      return null
    } finally {
      loading.value = false
    }
  }

  const updateSupplier = async (id, data) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/achats/suppliers/${id}`, {
        method: 'PATCH',
        headers: getAuthHeaders(),
        body: JSON.stringify(data)
      })

      if (!response.ok) {
        const errorData = await response.json()
        throw new Error(errorData.message || 'Failed to update supplier')
      }

      return await response.json()
    } catch (err) {
      error.value = err.message
      console.error('Error updating supplier:', err)
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

      if (!response.ok) {
        throw new Error('Failed to delete supplier')
      }

      return true
    } catch (err) {
      error.value = err.message
      console.error('Error deleting supplier:', err)
      return false
    } finally {
      loading.value = false
    }
  }

  const getSupplierMetrics = async (id) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/achats/suppliers/${id}/performance`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) {
        throw new Error('Failed to fetch supplier metrics')
      }

      return await response.json()
    } catch (err) {
      error.value = err.message
      console.error('Error fetching supplier metrics:', err)
      return null
    } finally {
      loading.value = false
    }
  }

  const getSupplierQuotes = async (id) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/achats/suppliers/${id}/quotes`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) {
        throw new Error('Failed to fetch supplier quotes')
      }

      return await response.json()
    } catch (err) {
      error.value = err.message
      console.error('Error fetching supplier quotes:', err)
      return null
    } finally {
      loading.value = false
    }
  }

  return {
    loading,
    error,
    fetchSuppliers,
    getSupplier,
    createSupplier,
    updateSupplier,
    deleteSupplier,
    getSupplierMetrics,
    getSupplierQuotes
  }
}
