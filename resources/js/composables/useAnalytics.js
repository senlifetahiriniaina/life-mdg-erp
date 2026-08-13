import { ref } from 'vue'

export function useAnalytics() {
  const loading = ref(false)
  const error = ref('')

  const getAuthHeaders = () => ({
    'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]')?.content || ''}`,
    'Content-Type': 'application/json'
  })

  const getProcurementMetrics = async (dateRange = '30d') => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/analytics/procurement?range=${dateRange}`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) throw new Error('Failed to fetch procurement metrics')

      return await response.json()
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const getApprovalMetrics = async (dateRange = '30d') => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/analytics/approvals?range=${dateRange}`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) throw new Error('Failed to fetch approval metrics')

      return await response.json()
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const getQualityMetrics = async (dateRange = '30d') => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/analytics/quality?range=${dateRange}`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) throw new Error('Failed to fetch quality metrics')

      return await response.json()
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const getSupplierMetrics = async (supplierId) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/analytics/suppliers/${supplierId}`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) throw new Error('Failed to fetch supplier metrics')

      return await response.json()
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const getApprovalMetricsByRole = async (dateRange = '30d') => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/analytics/approvals-by-role?range=${dateRange}`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) throw new Error('Failed to fetch role metrics')

      return await response.json()
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const getSpendTrend = async (period = 'monthly', limit = 12) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/analytics/spend-trend?period=${period}&limit=${limit}`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) throw new Error('Failed to fetch spend trend')

      return await response.json()
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const getSLACompliance = async (dateRange = '30d') => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/analytics/sla-compliance?range=${dateRange}`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) throw new Error('Failed to fetch SLA compliance')

      return await response.json()
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const exportAnalytics = async (format = 'pdf', section = 'all') => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/analytics/export?format=${format}&section=${section}`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) throw new Error('Failed to export analytics')

      return response
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
    getProcurementMetrics,
    getApprovalMetrics,
    getQualityMetrics,
    getSupplierMetrics,
    getApprovalMetricsByRole,
    getSpendTrend,
    getSLACompliance,
    exportAnalytics
  }
}
