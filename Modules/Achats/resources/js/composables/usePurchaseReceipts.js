import { ref } from 'vue'

export function usePurchaseReceipts() {
  const loading = ref(false)
  const error = ref('')

  const getAuthHeaders = () => ({
    'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]').content}`,
    'Content-Type': 'application/json'
  })

  const fetchReceipts = async (params = {}) => {
    loading.value = true
    error.value = ''

    try {
      const searchParams = new URLSearchParams({
        page: params.page || 1,
        per_page: params.per_page || 15,
        ...params
      })

      const response = await fetch(`/api/v1/achats/purchase-receipts?${searchParams}`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) {
        throw new Error('Failed to fetch receipts')
      }

      return await response.json()
    } catch (err) {
      error.value = err.message
      console.error('Error fetching receipts:', err)
      return null
    } finally {
      loading.value = false
    }
  }

  const getReceipt = async (id) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/achats/purchase-receipts/${id}`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) {
        throw new Error('Failed to fetch receipt')
      }

      return await response.json()
    } catch (err) {
      error.value = err.message
      console.error('Error fetching receipt:', err)
      return null
    } finally {
      loading.value = false
    }
  }

  const createReceipt = async (data) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch('/api/v1/achats/purchase-receipts', {
        method: 'POST',
        headers: getAuthHeaders(),
        body: JSON.stringify(data)
      })

      if (!response.ok) {
        const errorData = await response.json()
        throw new Error(errorData.message || 'Failed to create receipt')
      }

      return await response.json()
    } catch (err) {
      error.value = err.message
      console.error('Error creating receipt:', err)
      return null
    } finally {
      loading.value = false
    }
  }

  const updateReceipt = async (id, data) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/achats/purchase-receipts/${id}`, {
        method: 'PATCH',
        headers: getAuthHeaders(),
        body: JSON.stringify(data)
      })

      if (!response.ok) {
        const errorData = await response.json()
        throw new Error(errorData.message || 'Failed to update receipt')
      }

      return await response.json()
    } catch (err) {
      error.value = err.message
      console.error('Error updating receipt:', err)
      return null
    } finally {
      loading.value = false
    }
  }

  const getReceiptVariances = async (id) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/achats/purchase-receipts/${id}/variances`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) {
        throw new Error('Failed to fetch variances')
      }

      return await response.json()
    } catch (err) {
      error.value = err.message
      console.error('Error fetching variances:', err)
      return null
    } finally {
      loading.value = false
    }
  }

  const getQualityIssues = async (id) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/achats/purchase-receipts/${id}/quality-issues`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) {
        throw new Error('Failed to fetch quality issues')
      }

      return await response.json()
    } catch (err) {
      error.value = err.message
      console.error('Error fetching quality issues:', err)
      return null
    } finally {
      loading.value = false
    }
  }

  return {
    loading,
    error,
    fetchReceipts,
    getReceipt,
    createReceipt,
    updateReceipt,
    getReceiptVariances,
    getQualityIssues
  }
}
