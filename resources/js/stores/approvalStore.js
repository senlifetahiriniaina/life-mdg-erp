import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useApprovalStore = defineStore('approval', () => {
  const approvals = ref([])
  const selectedApproval = ref(null)
  const loading = ref(false)
  const error = ref('')
  const pagination = ref(null)
  const stats = ref({
    total: 0,
    awaiting_my_action: 0,
    overdue: 0,
    approved: 0
  })

  const getAuthHeaders = () => ({
    'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]')?.content || ''}`,
    'Content-Type': 'application/json'
  })

  const approvalCount = computed(() => approvals.value.length)

  const pendingApprovals = computed(() => approvals.value.filter(a => a.status === 'pending'))

  const myActions = computed(() => approvals.value.filter(a => a.awaiting_my_action))

  const overdue = computed(() => stats.value.overdue || 0)

  const approvalRate = computed(() => {
    if (stats.value.total === 0) return 0
    return Math.round((stats.value.approved / stats.value.total) * 100)
  })

  const fetchApprovals = async (params = {}) => {
    loading.value = true
    error.value = ''

    try {
      const searchParams = new URLSearchParams({
        page: params.page || 1,
        per_page: params.per_page || 50,
        ...params
      })

      const response = await fetch(`/api/v1/validation/approval-requests?${searchParams}`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) throw new Error('Failed to fetch approvals')

      const data = await response.json()
      approvals.value = data.data
      pagination.value = data.meta
      stats.value = data.stats || stats.value
      return data
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const getApproval = async (id) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/validation/approval-requests/${id}`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) throw new Error('Failed to fetch approval')

      const data = await response.json()
      selectedApproval.value = data
      return data
    } catch (err) {
      error.value = err.message
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

      if (!response.ok) throw new Error('Failed to approve request')

      const data = await response.json()
      const index = approvals.value.findIndex(a => a.id === id)
      if (index > -1) {
        approvals.value[index] = data
      }
      if (selectedApproval.value?.id === id) {
        selectedApproval.value = data
      }
      stats.value.approved++
      return data
    } catch (err) {
      error.value = err.message
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

      if (!response.ok) throw new Error('Failed to reject request')

      const data = await response.json()
      const index = approvals.value.findIndex(a => a.id === id)
      if (index > -1) {
        approvals.value[index] = data
      }
      if (selectedApproval.value?.id === id) {
        selectedApproval.value = data
      }
      return data
    } catch (err) {
      error.value = err.message
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

      if (!response.ok) throw new Error('Failed to delegate request')

      const data = await response.json()
      const index = approvals.value.findIndex(a => a.id === id)
      if (index > -1) {
        approvals.value[index] = data
      }
      if (selectedApproval.value?.id === id) {
        selectedApproval.value = data
      }
      return data
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const refreshStats = async () => {
    try {
      const response = await fetch('/api/v1/validation/approval-requests?per_page=1', {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (response.ok) {
        const data = await response.json()
        stats.value = data.stats || stats.value
      }
    } catch (err) {
      console.error('Failed to refresh stats:', err)
    }
  }

  const clearSelected = () => {
    selectedApproval.value = null
  }

  const clearError = () => {
    error.value = ''
  }

  return {
    approvals,
    selectedApproval,
    loading,
    error,
    pagination,
    stats,
    approvalCount,
    pendingApprovals,
    myActions,
    overdue,
    approvalRate,
    fetchApprovals,
    getApproval,
    approveRequest,
    rejectRequest,
    delegateRequest,
    refreshStats,
    clearSelected,
    clearError
  }
})
