import { ref } from 'vue'

export function usePurchaseOrders() {
  const loading = ref(false)
  const error = ref('')

  const getAuthHeaders = () => ({
    'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]').content}`,
    'Content-Type': 'application/json'
  })

  const fetchPurchaseOrders = async (params = {}) => {
    loading.value = true
    error.value = ''

    try {
      const searchParams = new URLSearchParams({
        page: params.page || 1,
        per_page: params.per_page || 15,
        ...params
      })

      const response = await fetch(`/api/v1/achats/purchase-orders?${searchParams}`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) {
        throw new Error('Failed to fetch purchase orders')
      }

      return await response.json()
    } catch (err) {
      error.value = err.message
      console.error('Error fetching purchase orders:', err)
      return null
    } finally {
      loading.value = false
    }
  }

  const getPurchaseOrder = async (id) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/achats/purchase-orders/${id}`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) {
        throw new Error('Failed to fetch purchase order')
      }

      return await response.json()
    } catch (err) {
      error.value = err.message
      console.error('Error fetching purchase order:', err)
      return null
    } finally {
      loading.value = false
    }
  }

  const createPurchaseOrder = async (data) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch('/api/v1/achats/purchase-orders', {
        method: 'POST',
        headers: getAuthHeaders(),
        body: JSON.stringify(data)
      })

      if (!response.ok) {
        const errorData = await response.json()
        throw new Error(errorData.message || 'Failed to create purchase order')
      }

      return await response.json()
    } catch (err) {
      error.value = err.message
      console.error('Error creating purchase order:', err)
      return null
    } finally {
      loading.value = false
    }
  }

  const updatePurchaseOrder = async (id, data) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/achats/purchase-orders/${id}`, {
        method: 'PATCH',
        headers: getAuthHeaders(),
        body: JSON.stringify(data)
      })

      if (!response.ok) {
        const errorData = await response.json()
        throw new Error(errorData.message || 'Failed to update purchase order')
      }

      return await response.json()
    } catch (err) {
      error.value = err.message
      console.error('Error updating purchase order:', err)
      return null
    } finally {
      loading.value = false
    }
  }

  const deletePurchaseOrder = async (id) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/achats/purchase-orders/${id}`, {
        method: 'DELETE',
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) {
        throw new Error('Failed to delete purchase order')
      }

      return true
    } catch (err) {
      error.value = err.message
      console.error('Error deleting purchase order:', err)
      return false
    } finally {
      loading.value = false
    }
  }

  const submitPurchaseOrder = async (id) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/achats/purchase-orders/${id}/submit`, {
        method: 'POST',
        headers: getAuthHeaders()
      })

      if (!response.ok) {
        throw new Error('Failed to submit purchase order')
      }

      return await response.json()
    } catch (err) {
      error.value = err.message
      console.error('Error submitting purchase order:', err)
      return null
    } finally {
      loading.value = false
    }
  }

  const approvePurchaseOrder = async (id) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/achats/purchase-orders/${id}/approve`, {
        method: 'POST',
        headers: getAuthHeaders()
      })

      if (!response.ok) {
        throw new Error('Failed to approve purchase order')
      }

      return await response.json()
    } catch (err) {
      error.value = err.message
      console.error('Error approving purchase order:', err)
      return null
    } finally {
      loading.value = false
    }
  }

  const exportPO = async (id, format = 'json') => {
    try {
      const response = await fetch(`/api/v1/achats/purchase-orders/${id}/export-${format}`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) {
        throw new Error(`Failed to export PO as ${format.toUpperCase()}`)
      }

      return response
    } catch (err) {
      error.value = err.message
      console.error(`Error exporting PO:`, err)
      return null
    }
  }

  return {
    loading,
    error,
    fetchPurchaseOrders,
    getPurchaseOrder,
    createPurchaseOrder,
    updatePurchaseOrder,
    deletePurchaseOrder,
    submitPurchaseOrder,
    approvePurchaseOrder,
    exportPO
  }
}
