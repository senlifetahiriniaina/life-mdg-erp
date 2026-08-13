import { ref } from 'vue'

export function useRFQs() {
  const loading = ref(false)
  const error = ref('')

  const getAuthHeaders = () => ({
    'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]').content}`,
    'Content-Type': 'application/json'
  })

  const fetchRFQs = async (params = {}) => {
    loading.value = true
    error.value = ''

    try {
      const searchParams = new URLSearchParams({
        page: params.page || 1,
        per_page: params.per_page || 15,
        ...params
      })

      const response = await fetch(`/api/v1/achats/rfqs?${searchParams}`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) {
        throw new Error('Failed to fetch RFQs')
      }

      return await response.json()
    } catch (err) {
      error.value = err.message
      console.error('Error fetching RFQs:', err)
      return null
    } finally {
      loading.value = false
    }
  }

  const getRFQ = async (id) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/achats/rfqs/${id}`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) {
        throw new Error('Failed to fetch RFQ')
      }

      return await response.json()
    } catch (err) {
      error.value = err.message
      console.error('Error fetching RFQ:', err)
      return null
    } finally {
      loading.value = false
    }
  }

  const createRFQ = async (data) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch('/api/v1/achats/rfqs', {
        method: 'POST',
        headers: getAuthHeaders(),
        body: JSON.stringify(data)
      })

      if (!response.ok) {
        const errorData = await response.json()
        throw new Error(errorData.message || 'Failed to create RFQ')
      }

      return await response.json()
    } catch (err) {
      error.value = err.message
      console.error('Error creating RFQ:', err)
      return null
    } finally {
      loading.value = false
    }
  }

  const updateRFQ = async (id, data) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/achats/rfqs/${id}`, {
        method: 'PATCH',
        headers: getAuthHeaders(),
        body: JSON.stringify(data)
      })

      if (!response.ok) {
        const errorData = await response.json()
        throw new Error(errorData.message || 'Failed to update RFQ')
      }

      return await response.json()
    } catch (err) {
      error.value = err.message
      console.error('Error updating RFQ:', err)
      return null
    } finally {
      loading.value = false
    }
  }

  const issueRFQ = async (id) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/achats/rfqs/${id}/issue`, {
        method: 'POST',
        headers: getAuthHeaders()
      })

      if (!response.ok) {
        throw new Error('Failed to issue RFQ')
      }

      return await response.json()
    } catch (err) {
      error.value = err.message
      console.error('Error issuing RFQ:', err)
      return null
    } finally {
      loading.value = false
    }
  }

  const acceptQuote = async (rfqId, quoteId) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/achats/rfqs/${rfqId}/accept-quote`, {
        method: 'POST',
        headers: getAuthHeaders(),
        body: JSON.stringify({ quote_id: quoteId })
      })

      if (!response.ok) {
        throw new Error('Failed to accept quote')
      }

      return await response.json()
    } catch (err) {
      error.value = err.message
      console.error('Error accepting quote:', err)
      return null
    } finally {
      loading.value = false
    }
  }

  const rejectQuote = async (rfqId, quoteId) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/achats/rfqs/${rfqId}/reject-quote`, {
        method: 'POST',
        headers: getAuthHeaders(),
        body: JSON.stringify({ quote_id: quoteId })
      })

      if (!response.ok) {
        throw new Error('Failed to reject quote')
      }

      return await response.json()
    } catch (err) {
      error.value = err.message
      console.error('Error rejecting quote:', err)
      return null
    } finally {
      loading.value = false
    }
  }

  const closeRFQ = async (id) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/achats/rfqs/${id}/close`, {
        method: 'POST',
        headers: getAuthHeaders()
      })

      if (!response.ok) {
        throw new Error('Failed to close RFQ')
      }

      return await response.json()
    } catch (err) {
      error.value = err.message
      console.error('Error closing RFQ:', err)
      return null
    } finally {
      loading.value = false
    }
  }

  return {
    loading,
    error,
    fetchRFQs,
    getRFQ,
    createRFQ,
    updateRFQ,
    issueRFQ,
    acceptQuote,
    rejectQuote,
    closeRFQ
  }
}
