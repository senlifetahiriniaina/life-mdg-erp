import { ref } from 'vue'

export function useApprovals() {
  const loading = ref(false)
  const error = ref('')

  const getAuthHeaders = () => ({
    'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]').content}`,
    'Content-Type': 'application/json'
  })

  const fetchApprovalRequests = async (params = {}) => {
    loading.value = true
    error.value = ''

    try {
      const searchParams = new URLSearchParams({
        page: params.page || 1,
        per_page: params.per_page || 15,
        ...params
      })

      const response = await fetch(`/api/v1/validation/approval-requests?${searchParams}`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) {
        throw new Error('Failed to fetch approval requests')
      }

      return await response.json()
    } catch (err) {
      error.value = err.message
      console.error('Error fetching approval requests:', err)
      return null
    } finally {
      loading.value = false
    }
  }

  const getApprovalRequest = async (id) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/validation/approval-requests/${id}`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) {
        throw new Error('Failed to fetch approval request')
      }

      return await response.json()
    } catch (err) {
      error.value = err.message
      console.error('Error fetching approval request:', err)
      return null
    } finally {
      loading.value = false
    }
  }

  const approveRequest = async (id, comments = '') => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/validation/approval-requests/${id}/approve`, {
        method: 'POST',
        headers: getAuthHeaders(),
        body: JSON.stringify({ comments })
      })

      if (!response.ok) {
        const errorData = await response.json()
        throw new Error(errorData.message || 'Failed to approve request')
      }

      return await response.json()
    } catch (err) {
      error.value = err.message
      console.error('Error approving request:', err)
      return null
    } finally {
      loading.value = false
    }
  }

  const rejectRequest = async (id, reason = '') => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/validation/approval-requests/${id}/reject`, {
        method: 'POST',
        headers: getAuthHeaders(),
        body: JSON.stringify({ reason })
      })

      if (!response.ok) {
        const errorData = await response.json()
        throw new Error(errorData.message || 'Failed to reject request')
      }

      return await response.json()
    } catch (err) {
      error.value = err.message
      console.error('Error rejecting request:', err)
      return null
    } finally {
      loading.value = false
    }
  }

  const delegateRequest = async (id, delegateTo) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/validation/approval-requests/${id}/delegate`, {
        method: 'POST',
        headers: getAuthHeaders(),
        body: JSON.stringify({ delegate_to: delegateTo })
      })

      if (!response.ok) {
        const errorData = await response.json()
        throw new Error(errorData.message || 'Failed to delegate request')
      }

      return await response.json()
    } catch (err) {
      error.value = err.message
      console.error('Error delegating request:', err)
      return null
    } finally {
      loading.value = false
    }
  }

  const getApprovalHistory = async (id) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/validation/approval-requests/${id}/history`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) {
        throw new Error('Failed to fetch approval history')
      }

      return await response.json()
    } catch (err) {
      error.value = err.message
      console.error('Error fetching approval history:', err)
      return null
    } finally {
      loading.value = false
    }
  }

  return {
    loading,
    error,
    fetchApprovalRequests,
    getApprovalRequest,
    approveRequest,
    rejectRequest,
    delegateRequest,
    getApprovalHistory
  }
}
