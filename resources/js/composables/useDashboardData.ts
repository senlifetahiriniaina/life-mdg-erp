import { computed } from 'vue'
import { useQuery, useQueries } from '@tanstack/vue-query'
import axios from 'axios'

/**
 * Phase 2: Request Waterfall Prevention
 *
 * Fetch all dashboard data in parallel instead of sequentially
 * Reduces LCP by ~30% by eliminating request waterfall
 *
 * Example:
 * const { contacts, activities, stats, loading } = useDashboardData()
 */
export function useDashboardData() {
  // All queries fire in parallel — no waterfall!
  const results = useQueries({
    queries: [
      {
        queryKey: ['dashboard', 'contacts'],
        queryFn: () =>
          axios.get('/api/v1/crm/contacts?limit=10&sort=-created_at')
            .then(r => r.data),
        staleTime: 5 * 60 * 1000, // 5 minutes
        retry: 2,
      },
      {
        queryKey: ['dashboard', 'recent-activities'],
        queryFn: () =>
          axios.get('/api/v1/crm/activities?limit=20&sort=-created_at')
            .then(r => r.data),
        staleTime: 5 * 60 * 1000,
        retry: 2,
      },
      {
        queryKey: ['dashboard', 'stats'],
        queryFn: () =>
          axios.get('/api/v1/dashboard/stats')
            .then(r => r.data),
        staleTime: 10 * 60 * 1000, // 10 minutes for stats
        retry: 1,
      },
      {
        queryKey: ['dashboard', 'summary'],
        queryFn: () =>
          axios.get('/api/v1/dashboard/summary')
            .then(r => r.data),
        staleTime: 10 * 60 * 1000,
        retry: 1,
      },
    ],
  })

  // useQueries() returns a single Ref wrapping the whole results tuple (not
  // one Ref per query) — destructuring `results` itself throws at runtime
  // ("results is not iterable"), it has to be `results.value` first. Every
  // derived value below is wrapped in computed() so it stays reactive as
  // the underlying queries resolve, matching how DashboardExample.vue
  // destructures this composable's return at its own <script setup> top
  // level and uses the values directly in its template (relying on Vue's
  // ref auto-unwrapping there).
  const contactsQuery = computed(() => results.value[0])
  const activitiesQuery = computed(() => results.value[1])
  const statsQuery = computed(() => results.value[2])
  const summaryQuery = computed(() => results.value[3])

  return {
    // Data
    contacts: computed(() => contactsQuery.value.data?.data || []),
    activities: computed(() => activitiesQuery.value.data?.data || []),
    stats: computed(() => statsQuery.value.data || null),
    summary: computed(() => summaryQuery.value.data || null),

    // Status
    isLoading: computed(() => results.value.some(r => r.isPending)),
    isError: computed(() => results.value.some(r => r.isError)),
    isSuccess: computed(() => results.value.every(r => r.isSuccess)),

    // Error details
    error: computed(() => results.value.find(r => r.error)?.error),

    // Refetch all queries
    refetch: async () => {
      await Promise.all(results.value.map(r => r.refetch?.()))
    },

    // Individual query status
    contactsLoading: computed(() => contactsQuery.value.isPending),
    activitiesLoading: computed(() => activitiesQuery.value.isPending),
    statsLoading: computed(() => statsQuery.value.isPending),
    summaryLoading: computed(() => summaryQuery.value.isPending),
  }
}

/**
 * Phase 2 Alternative: Single query with deduplication
 *
 * Use when you need just one set of data that multiple components use
 * TanStack Query automatically deduplicates identical queryKeys
 */
export function useDashboardContacts() {
  return useQuery({
    queryKey: ['dashboard', 'contacts'],
    queryFn: () =>
      axios.get('/api/v1/crm/contacts?limit=10&sort=-created_at')
        .then(r => r.data),
    staleTime: 5 * 60 * 1000,
    retry: 2,
  })
}

export function useDashboardActivities() {
  return useQuery({
    queryKey: ['dashboard', 'activities'],
    queryFn: () =>
      axios.get('/api/v1/crm/activities?limit=20&sort=-created_at')
        .then(r => r.data),
    staleTime: 5 * 60 * 1000,
    retry: 2,
  })
}

export function useDashboardStats() {
  return useQuery({
    queryKey: ['dashboard', 'stats'],
    queryFn: () =>
      axios.get('/api/v1/dashboard/stats')
        .then(r => r.data),
    staleTime: 10 * 60 * 1000,
    retry: 1,
  })
}
