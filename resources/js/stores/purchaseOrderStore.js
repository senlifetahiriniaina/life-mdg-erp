import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const usePurchaseOrderStore = defineStore('purchaseOrder', () => {
  const purchaseOrders = ref([])
  const selectedPO = ref(null)
  const loading = ref(false)
  const error = ref('')
  const pagination = ref(null)
  const filters = ref({
    status: '',
    supplier_id: '',
    date_from: '',
    date_to: ''
  })

  const getAuthHeaders = () => ({
    'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]')?.content || ''}`,
    'Content-Type': 'application/json'
  })

  const poCount = computed(() => purchaseOrders.value.length)

  const draftPOs = computed(() => purchaseOrders.value.filter(po => po.status === 'draft'))

  const approvedPOs = computed(() => purchaseOrders.value.filter(po => po.status === 'approved'))

  const totalValue = computed(() => purchaseOrders.value.reduce((sum, po) => sum + (po.total || 0), 0))

  const fetchPOs = async (params = {}) => {
    loading.value = true
    error.value = ''

    try {
      const searchParams = new URLSearchParams({
        page: params.page || 1,
        per_page: params.per_page || 50,
        ...filters.value,
        ...params
      })

      const response = await fetch(`/api/v1/achats/purchase-orders?${searchParams}`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) throw new Error('Failed to fetch POs')

      const data = await response.json()
      purchaseOrders.value = data.data
      pagination.value = data.meta
      return data
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const getPO = async (id) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/achats/purchase-orders/${id}`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) throw new Error('Failed to fetch PO')

      const data = await response.json()
      selectedPO.value = data
      return data
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const createPO = async (poData) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch('/api/v1/achats/purchase-orders', {
        method: 'POST',
        headers: getAuthHeaders(),
        body: JSON.stringify(poData)
      })

      if (!response.ok) throw new Error('Failed to create PO')

      const data = await response.json()
      purchaseOrders.value.push(data)
      return data
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const updatePO = async (id, poData) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/achats/purchase-orders/${id}`, {
        method: 'PATCH',
        headers: getAuthHeaders(),
        body: JSON.stringify(poData)
      })

      if (!response.ok) throw new Error('Failed to update PO')

      const data = await response.json()
      const index = purchaseOrders.value.findIndex(po => po.id === id)
      if (index > -1) {
        purchaseOrders.value[index] = data
      }
      if (selectedPO.value?.id === id) {
        selectedPO.value = data
      }
      return data
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const submitPO = async (id) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/achats/purchase-orders/${id}/submit`, {
        method: 'POST',
        headers: getAuthHeaders()
      })

      if (!response.ok) throw new Error('Failed to submit PO')

      const data = await response.json()
      const index = purchaseOrders.value.findIndex(po => po.id === id)
      if (index > -1) {
        purchaseOrders.value[index] = data
      }
      if (selectedPO.value?.id === id) {
        selectedPO.value = data
      }
      return data
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const approvePO = async (id) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/achats/purchase-orders/${id}/approve`, {
        method: 'POST',
        headers: getAuthHeaders()
      })

      if (!response.ok) throw new Error('Failed to approve PO')

      const data = await response.json()
      const index = purchaseOrders.value.findIndex(po => po.id === id)
      if (index > -1) {
        purchaseOrders.value[index] = data
      }
      if (selectedPO.value?.id === id) {
        selectedPO.value = data
      }
      return data
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const deletePO = async (id) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/achats/purchase-orders/${id}`, {
        method: 'DELETE',
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) throw new Error('Failed to delete PO')

      purchaseOrders.value = purchaseOrders.value.filter(po => po.id !== id)
      if (selectedPO.value?.id === id) {
        selectedPO.value = null
      }
      return true
    } catch (err) {
      error.value = err.message
      return false
    } finally {
      loading.value = false
    }
  }

  const setFilters = (newFilters) => {
    filters.value = { ...filters.value, ...newFilters }
  }

  const clearFilters = () => {
    filters.value = {
      status: '',
      supplier_id: '',
      date_from: '',
      date_to: ''
    }
  }

  const clearSelected = () => {
    selectedPO.value = null
  }

  const clearError = () => {
    error.value = ''
  }

  return {
    purchaseOrders,
    selectedPO,
    loading,
    error,
    pagination,
    filters,
    poCount,
    draftPOs,
    approvedPOs,
    totalValue,
    fetchPOs,
    getPO,
    createPO,
    updatePO,
    submitPO,
    approvePO,
    deletePO,
    setFilters,
    clearFilters,
    clearSelected,
    clearError
  }
})
