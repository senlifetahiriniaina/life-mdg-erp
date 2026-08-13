import { ref } from 'vue'

export function useInventory() {
  const loading = ref(false)
  const error = ref('')

  const getAuthHeaders = () => ({
    'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]')?.content || ''}`,
    'Content-Type': 'application/json'
  })

  const fetchProducts = async (params = {}) => {
    loading.value = true
    error.value = ''

    try {
      const searchParams = new URLSearchParams({
        page: params.page || 1,
        per_page: params.per_page || 15,
        ...params
      })

      const response = await fetch(`/api/v1/inventory/products?${searchParams}`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) throw new Error('Failed to fetch products')

      return await response.json()
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const getProduct = async (id) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/inventory/products/${id}`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) throw new Error('Failed to fetch product')

      return await response.json()
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const createProduct = async (productData) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch('/api/v1/inventory/products', {
        method: 'POST',
        headers: getAuthHeaders(),
        body: JSON.stringify(productData)
      })

      if (!response.ok) {
        const errorData = await response.json()
        throw new Error(errorData.message || 'Failed to create product')
      }

      return await response.json()
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const updateProduct = async (id, productData) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/inventory/products/${id}`, {
        method: 'PATCH',
        headers: getAuthHeaders(),
        body: JSON.stringify(productData)
      })

      if (!response.ok) throw new Error('Failed to update product')

      return await response.json()
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const deleteProduct = async (id) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/inventory/products/${id}`, {
        method: 'DELETE',
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) throw new Error('Failed to delete product')

      return true
    } catch (err) {
      error.value = err.message
      return false
    } finally {
      loading.value = false
    }
  }

  const getLowStockProducts = async () => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch('/api/v1/inventory/products/low-stock', {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) throw new Error('Failed to fetch low stock products')

      return await response.json()
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const getInventoryMetrics = async () => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch('/api/v1/inventory/products/metrics', {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) throw new Error('Failed to fetch metrics')

      return await response.json()
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const getInventoryValuation = async () => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch('/api/v1/inventory/products/valuation', {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) throw new Error('Failed to fetch valuation')

      return await response.json()
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const recordStockMovement = async (movementData) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch('/api/v1/inventory/stock-movements', {
        method: 'POST',
        headers: getAuthHeaders(),
        body: JSON.stringify(movementData)
      })

      if (!response.ok) throw new Error('Failed to record stock movement')

      return await response.json()
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const getStockHistory = async (productId, limit = 50) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/inventory/products/${productId}/history?limit=${limit}`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) throw new Error('Failed to fetch stock history')

      return await response.json()
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const fetchCategories = async (params = {}) => {
    loading.value = true
    error.value = ''

    try {
      const searchParams = new URLSearchParams({
        page: params.page || 1,
        per_page: params.per_page || 100,
        ...params
      })

      const response = await fetch(`/api/v1/inventory/categories?${searchParams}`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) throw new Error('Failed to fetch categories')

      return await response.json()
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const fetchWarehouses = async (params = {}) => {
    loading.value = true
    error.value = ''

    try {
      const searchParams = new URLSearchParams({
        page: params.page || 1,
        per_page: params.per_page || 50,
        ...params
      })

      const response = await fetch(`/api/v1/inventory/warehouses?${searchParams}`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) throw new Error('Failed to fetch warehouses')

      return await response.json()
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  return {
    loading,
    error,
    fetchProducts,
    getProduct,
    createProduct,
    updateProduct,
    deleteProduct,
    getLowStockProducts,
    getInventoryMetrics,
    getInventoryValuation,
    recordStockMovement,
    getStockHistory,
    fetchCategories,
    fetchWarehouses
  }
}
