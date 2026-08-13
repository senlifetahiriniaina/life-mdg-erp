import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useTimesheets } from '../composables/useTimesheets'

export const useTimesheetsStore = defineStore('timesheets', () => {
  const timesheets = useTimesheets()

  // ─────────────────────────────────────────────────────────────────────────
  // State
  // ─────────────────────────────────────────────────────────────────────────

  const entries = ref<any[]>([])
  const allocations = ref<any[]>([])
  const projects = ref<any[]>([])
  const pendingApprovals = ref<any[]>([])
  const metrics = ref<any>(null)

  const entriesLoading = ref(false)
  const allocationsLoading = ref(false)
  const projectsLoading = ref(false)
  const approvalsLoading = ref(false)
  const metricsLoading = ref(false)

  const lastEntriesSync = ref<Date | null>(null)
  const lastProjectsSync = ref<Date | null>(null)
  const lastApprovalsSync = ref<Date | null>(null)

  const CACHE_DURATION = 5 * 60 * 1000 // 5 minutes

  // ─────────────────────────────────────────────────────────────────────────
  // Computed
  // ─────────────────────────────────────────────────────────────────────────

  const draftEntries = computed(() => entries.value.filter(e => e.status === 'draft'))
  const submittedEntries = computed(() => entries.value.filter(e => e.status === 'submitted'))
  const approvedEntries = computed(() => entries.value.filter(e => e.status === 'approved'))
  const rejectedEntries = computed(() => entries.value.filter(e => e.status === 'rejected'))

  const activeProjects = computed(() => projects.value.filter(p => p.status === 'active'))
  const overBudgetProjects = computed(() => projects.value.filter(p => p.is_over_budget))

  const totalHours = computed(() => entries.value.reduce((sum, e) => sum + e.hours_worked, 0))
  const approvedHours = computed(() => approvedEntries.value.reduce((sum, e) => sum + e.hours_worked, 0))
  const totalCost = computed(() => allocations.value.reduce((sum, a) => sum + (a.cost_amount || 0), 0))

  // ─────────────────────────────────────────────────────────────────────────
  // Entries
  // ─────────────────────────────────────────────────────────────────────────

  const loadEntries = async (force = false) => {
    const now = new Date()
    if (!force && lastEntriesSync.value && now.getTime() - lastEntriesSync.value.getTime() < CACHE_DURATION) {
      return entries.value
    }

    try {
      entriesLoading.value = true
      const response = await timesheets.getAllEntries()
      entries.value = response.data || response
      lastEntriesSync.value = new Date()
      return entries.value
    } finally {
      entriesLoading.value = false
    }
  }

  const addEntry = async (data: any) => {
    const response = await timesheets.createEntry(data)
    const newEntry = response.data || response
    entries.value.push(newEntry)
    return newEntry
  }

  const updateEntryInStore = async (id: number, data: any) => {
    const response = await timesheets.updateEntry(id, data)
    const updated = response.data || response
    const index = entries.value.findIndex(e => e.id === id)
    if (index !== -1) {
      entries.value[index] = updated
    }
    return updated
  }

  const deleteEntryFromStore = async (id: number) => {
    await timesheets.deleteEntry(id)
    entries.value = entries.value.filter(e => e.id !== id)
  }

  const submitEntryInStore = async (id: number) => {
    const response = await timesheets.submitEntry(id)
    const updated = response.data || response
    const index = entries.value.findIndex(e => e.id === id)
    if (index !== -1) {
      entries.value[index] = updated
    }
    return updated
  }

  const approveEntryInStore = async (id: number, notes?: string) => {
    const response = await timesheets.approveEntry(id, notes)
    const updated = response.data || response
    const index = entries.value.findIndex(e => e.id === id)
    if (index !== -1) {
      entries.value[index] = updated
    }
    return updated
  }

  const rejectEntryInStore = async (id: number, notes?: string) => {
    const response = await timesheets.rejectEntry(id, notes)
    const updated = response.data || response
    const index = entries.value.findIndex(e => e.id === id)
    if (index !== -1) {
      entries.value[index] = updated
    }
    return updated
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Projects
  // ─────────────────────────────────────────────────────────────────────────

  const loadProjects = async (force = false) => {
    const now = new Date()
    if (!force && lastProjectsSync.value && now.getTime() - lastProjectsSync.value.getTime() < CACHE_DURATION) {
      return projects.value
    }

    try {
      projectsLoading.value = true
      const response = await timesheets.getAllProjects()
      projects.value = response.data || response
      lastProjectsSync.value = new Date()
      return projects.value
    } finally {
      projectsLoading.value = false
    }
  }

  const addProject = async (data: any) => {
    const response = await timesheets.createProject(data)
    const newProject = response.data || response
    projects.value.push(newProject)
    return newProject
  }

  const updateProjectInStore = async (id: number, data: any) => {
    const response = await timesheets.updateProject(id, data)
    const updated = response.data || response
    const index = projects.value.findIndex(p => p.id === id)
    if (index !== -1) {
      projects.value[index] = updated
    }
    return updated
  }

  const deleteProjectFromStore = async (id: number) => {
    await timesheets.deleteProject(id)
    projects.value = projects.value.filter(p => p.id !== id)
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Allocations
  // ─────────────────────────────────────────────────────────────────────────

  const loadAllocations = async (params?: any) => {
    try {
      allocationsLoading.value = true
      const response = await timesheets.getAllAllocations(params)
      allocations.value = response.data || response
      return allocations.value
    } finally {
      allocationsLoading.value = false
    }
  }

  const addAllocations = async (entryId: number, allocData: any[]) => {
    const response = await timesheets.allocateTime(entryId, allocData)
    const newAllocations = Array.isArray(response.data) ? response.data : (response.data?.data || [response])
    allocations.value.push(...newAllocations)
    return newAllocations
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Approvals
  // ─────────────────────────────────────────────────────────────────────────

  const loadPendingApprovals = async (force = false) => {
    const now = new Date()
    if (!force && lastApprovalsSync.value && now.getTime() - lastApprovalsSync.value.getTime() < CACHE_DURATION) {
      return pendingApprovals.value
    }

    try {
      approvalsLoading.value = true
      const response = await timesheets.getPendingApprovals()
      pendingApprovals.value = response.data || response
      lastApprovalsSync.value = new Date()
      return pendingApprovals.value
    } finally {
      approvalsLoading.value = false
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Metrics
  // ─────────────────────────────────────────────────────────────────────────

  const loadMetrics = async (params?: any) => {
    try {
      metricsLoading.value = true
      const response = await timesheets.getSummaryMetrics(params)
      metrics.value = response.data || response
      return metrics.value
    } finally {
      metricsLoading.value = false
    }
  }

  // ─────────────────────────────────────────────────────────────────────────
  // Cache Management
  // ─────────────────────────────────────────────────────────────────────────

  const invalidateCache = () => {
    lastEntriesSync.value = null
    lastProjectsSync.value = null
    lastApprovalsSync.value = null
  }

  const clearAll = () => {
    entries.value = []
    allocations.value = []
    projects.value = []
    pendingApprovals.value = []
    metrics.value = null
    invalidateCache()
  }

  return {
    // State
    entries,
    allocations,
    projects,
    pendingApprovals,
    metrics,
    entriesLoading,
    allocationsLoading,
    projectsLoading,
    approvalsLoading,
    metricsLoading,

    // Computed
    draftEntries,
    submittedEntries,
    approvedEntries,
    rejectedEntries,
    activeProjects,
    overBudgetProjects,
    totalHours,
    approvedHours,
    totalCost,

    // Methods - Entries
    loadEntries,
    addEntry,
    updateEntryInStore,
    deleteEntryFromStore,
    submitEntryInStore,
    approveEntryInStore,
    rejectEntryInStore,

    // Methods - Projects
    loadProjects,
    addProject,
    updateProjectInStore,
    deleteProjectFromStore,

    // Methods - Allocations
    loadAllocations,
    addAllocations,

    // Methods - Approvals
    loadPendingApprovals,

    // Methods - Metrics
    loadMetrics,

    // Methods - Cache
    invalidateCache,
    clearAll,
  }
})
