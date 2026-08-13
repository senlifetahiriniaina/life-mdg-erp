import { ref } from 'vue'

export function useWorkflows() {
  const loading = ref(false)
  const error = ref('')

  const getAuthHeaders = () => ({
    'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]')?.content || ''}`,
    'Content-Type': 'application/json'
  })

  const fetchWorkflows = async (params = {}) => {
    loading.value = true
    error.value = ''

    try {
      const searchParams = new URLSearchParams({
        page: params.page || 1,
        per_page: params.per_page || 50,
        ...params
      })

      const response = await fetch(`/api/v1/validation/workflows?${searchParams}`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) throw new Error('Failed to fetch workflows')

      return await response.json()
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const getWorkflow = async (id) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/validation/workflows/${id}`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) throw new Error('Failed to fetch workflow')

      return await response.json()
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const createWorkflow = async (workflowData) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch('/api/v1/validation/workflows', {
        method: 'POST',
        headers: getAuthHeaders(),
        body: JSON.stringify(workflowData)
      })

      if (!response.ok) {
        const errorData = await response.json()
        throw new Error(errorData.message || 'Failed to create workflow')
      }

      return await response.json()
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const updateWorkflow = async (id, workflowData) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/validation/workflows/${id}`, {
        method: 'PATCH',
        headers: getAuthHeaders(),
        body: JSON.stringify(workflowData)
      })

      if (!response.ok) throw new Error('Failed to update workflow')

      return await response.json()
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const deleteWorkflow = async (id) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/validation/workflows/${id}`, {
        method: 'DELETE',
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) throw new Error('Failed to delete workflow')

      return true
    } catch (err) {
      error.value = err.message
      return false
    } finally {
      loading.value = false
    }
  }

  const publishWorkflow = async (id) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/validation/workflows/${id}/publish`, {
        method: 'POST',
        headers: getAuthHeaders()
      })

      if (!response.ok) throw new Error('Failed to publish workflow')

      return await response.json()
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const validateWorkflow = async (workflowData) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch('/api/v1/validation/workflows/validate', {
        method: 'POST',
        headers: getAuthHeaders(),
        body: JSON.stringify(workflowData)
      })

      if (!response.ok) throw new Error('Workflow validation failed')

      return await response.json()
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const getWorkflowTemplate = async (templateName) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/validation/workflows/templates/${templateName}`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) throw new Error('Failed to fetch template')

      return await response.json()
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const cloneWorkflow = async (id) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/validation/workflows/${id}/clone`, {
        method: 'POST',
        headers: getAuthHeaders()
      })

      if (!response.ok) throw new Error('Failed to clone workflow')

      return await response.json()
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  const getWorkflowStats = async (id) => {
    loading.value = true
    error.value = ''

    try {
      const response = await fetch(`/api/v1/validation/workflows/${id}/stats`, {
        headers: { 'Authorization': getAuthHeaders().Authorization }
      })

      if (!response.ok) throw new Error('Failed to fetch stats')

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
    fetchWorkflows,
    getWorkflow,
    createWorkflow,
    updateWorkflow,
    deleteWorkflow,
    publishWorkflow,
    validateWorkflow,
    getWorkflowTemplate,
    cloneWorkflow,
    getWorkflowStats
  }
}
