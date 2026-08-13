import { ref } from 'vue'
import axios from 'axios'

const api = axios.create({
  baseURL: '/api/v1/timesheets',
  headers: {
    'Authorization': `Bearer ${localStorage.getItem('token')}`,
    'Accept': 'application/json',
  },
})

export interface TimesheetEntry {
  id: number
  employee_id: number
  entry_date: string
  hours_worked: number
  description: string
  status: 'draft' | 'submitted' | 'approved' | 'rejected'
  task_id?: number
  submitted_at?: string
  approved_at?: string
  notes?: string
}

export interface TimeAllocation {
  id: number
  entry_id: number
  project_id: number
  cost_center_id?: number
  task_id?: number
  hours: number
  hourly_rate: number
  cost_amount: number
  is_billable: boolean
}

export interface TimeTrackingProject {
  id: number
  name: string
  code: string
  description?: string
  budget_hours: number
  hours_tracked: number
  remaining_hours: number
  percentage_used: number
  is_over_budget: boolean
  status: 'active' | 'paused' | 'completed' | 'archived'
  start_date: string
  end_date: string
}

export function useTimesheets() {
  const loading = ref(false)
  const error = ref<string | null>(null)

  // ─────────────────────────────────────────────────────────────────────────
  // Timesheet Entries
  // ─────────────────────────────────────────────────────────────────────────

  const getAllEntries = async (params?: any) => {
    try {
      loading.value = true
      const response = await api.get('/entries', { params })
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to fetch entries'
      throw err
    } finally {
      loading.value = false
    }
  }

  const getEntry = async (id: number) => {
    try {
      loading.value = true
      const response = await api.get(`/entries/${id}`)
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to fetch entry'
      throw err
    } finally {
      loading.value = false
    }
  }

  const createEntry = async (data: Partial<TimesheetEntry>) => {
    try {
      loading.value = true
      const response = await api.post('/entries', data)
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to create entry'
      throw err
    } finally {
      loading.value = false
    }
  }

  const updateEntry = async (id: number, data: Partial<TimesheetEntry>) => {
    try {
      loading.value = true
      const response = await api.patch(`/entries/${id}`, data)
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to update entry'
      throw err
    } finally {
      loading.value = false
    }
  }

  const deleteEntry = async (id: number) => {
    try {
      loading.value = true
      await api.delete(`/entries/${id}`)
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to delete entry'
      throw err
    } finally {
      loading.value = false
    }
  }

  const submitEntry = async (id: number) => {
    try {
      loading.value = true
      const response = await api.post(`/entries/${id}/submit`)
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to submit entry'
      throw err
    } finally {
      loading.value = false
    }
  }

  const approveEntry = async (id: number, notes?: string) => {
    try {
      loading.value = true
      const response = await api.post(`/entries/${id}/approve`, { notes })
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to approve entry'
      throw err
    } finally {
      loading.value = false
    }
  }

  const rejectEntry = async (id: number, notes?: string) => {
    try {
      loading.value = true
      const response = await api.post(`/entries/${id}/reject`, { notes })
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to reject entry'
      throw err
    } finally {
      loading.value = false
    }
  }

  const getEmployeeEntries = async (employeeId: number, fromDate?: string, toDate?: string) => {
    try {
      loading.value = true
      const response = await api.get(`/entries/employee/${employeeId}`, {
        params: { from_date: fromDate, to_date: toDate },
      })
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to fetch employee entries'
      throw err
    } finally {
      loading.value = false
    }
  }

  const getPendingApprovals = async () => {
    try {
      loading.value = true
      const response = await api.get('/entries/pending/approvals')
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to fetch pending approvals'
      throw err
    } finally {
      loading.value = false
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Time Allocations
  // ─────────────────────────────────────────────────────────────────────────

  const getAllAllocations = async (params?: any) => {
    try {
      loading.value = true
      const response = await api.get('/allocations', { params })
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to fetch allocations'
      throw err
    } finally {
      loading.value = false
    }
  }

  const getAllocation = async (id: number) => {
    try {
      loading.value = true
      const response = await api.get(`/allocations/${id}`)
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to fetch allocation'
      throw err
    } finally {
      loading.value = false
    }
  }

  const createAllocation = async (data: any) => {
    try {
      loading.value = true
      const response = await api.post('/allocations', data)
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to create allocation'
      throw err
    } finally {
      loading.value = false
    }
  }

  const updateAllocation = async (id: number, data: Partial<TimeAllocation>) => {
    try {
      loading.value = true
      const response = await api.patch(`/allocations/${id}`, data)
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to update allocation'
      throw err
    } finally {
      loading.value = false
    }
  }

  const deleteAllocation = async (id: number) => {
    try {
      loading.value = true
      await api.delete(`/allocations/${id}`)
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to delete allocation'
      throw err
    } finally {
      loading.value = false
    }
  }

  const allocateTime = async (entryId: number, allocations: any[]) => {
    try {
      loading.value = true
      const response = await api.post(`/entries/${entryId}/allocate`, { allocations })
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to allocate time'
      throw err
    } finally {
      loading.value = false
    }
  }

  const getAllocationsByProject = async (projectId: number, fromDate?: string, toDate?: string) => {
    try {
      loading.value = true
      const response = await api.get(`/allocations/project/${projectId}`, {
        params: { from_date: fromDate, to_date: toDate },
      })
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to fetch project allocations'
      throw err
    } finally {
      loading.value = false
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Tracking Projects
  // ─────────────────────────────────────────────────────────────────────────

  const getAllProjects = async (params?: any) => {
    try {
      loading.value = true
      const response = await api.get('/projects', { params })
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to fetch projects'
      throw err
    } finally {
      loading.value = false
    }
  }

  const getProject = async (id: number) => {
    try {
      loading.value = true
      const response = await api.get(`/projects/${id}`)
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to fetch project'
      throw err
    } finally {
      loading.value = false
    }
  }

  const createProject = async (data: Partial<TimeTrackingProject>) => {
    try {
      loading.value = true
      const response = await api.post('/projects', data)
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to create project'
      throw err
    } finally {
      loading.value = false
    }
  }

  const updateProject = async (id: number, data: Partial<TimeTrackingProject>) => {
    try {
      loading.value = true
      const response = await api.patch(`/projects/${id}`, data)
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to update project'
      throw err
    } finally {
      loading.value = false
    }
  }

  const deleteProject = async (id: number) => {
    try {
      loading.value = true
      await api.delete(`/projects/${id}`)
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to delete project'
      throw err
    } finally {
      loading.value = false
    }
  }

  const getProjectTimesheets = async (projectId: number, fromDate?: string, toDate?: string) => {
    try {
      loading.value = true
      const response = await api.get(`/projects/${projectId}/timesheets`, {
        params: { from_date: fromDate, to_date: toDate },
      })
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to fetch project timesheets'
      throw err
    } finally {
      loading.value = false
    }
  }

  const getProjectMetrics = async (projectId: number) => {
    try {
      loading.value = true
      const response = await api.get(`/projects/${projectId}/metrics`)
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to fetch project metrics'
      throw err
    } finally {
      loading.value = false
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Metrics
  // ─────────────────────────────────────────────────────────────────────────

  const getEmployeeMetrics = async (employeeId: number, month: string) => {
    try {
      loading.value = true
      const response = await api.get(`/metrics/employee/${employeeId}/month/${month}`)
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to fetch employee metrics'
      throw err
    } finally {
      loading.value = false
    }
  }

  const getSummaryMetrics = async (params?: any) => {
    try {
      loading.value = true
      const response = await api.get('/metrics/summary', { params })
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.message || 'Failed to fetch summary metrics'
      throw err
    } finally {
      loading.value = false
    }
  }

  return {
    loading,
    error,
    // Timesheet Entries
    getAllEntries,
    getEntry,
    createEntry,
    updateEntry,
    deleteEntry,
    submitEntry,
    approveEntry,
    rejectEntry,
    getEmployeeEntries,
    getPendingApprovals,
    // Time Allocations
    getAllAllocations,
    getAllocation,
    createAllocation,
    updateAllocation,
    deleteAllocation,
    allocateTime,
    getAllocationsByProject,
    // Tracking Projects
    getAllProjects,
    getProject,
    createProject,
    updateProject,
    deleteProject,
    getProjectTimesheets,
    getProjectMetrics,
    // Metrics
    getEmployeeMetrics,
    getSummaryMetrics,
  }
}
