<template>
  <AppLayout>
    <Head title="CRM Dashboard" />

    <div class="space-y-6">
      <!-- Page header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">
            CRM Dashboard
          </h1>
          <p class="text-surface-500 text-sm mt-1">
            Overview of your sales pipeline and activity
          </p>
        </div>
        <Button
          icon="pi pi-refresh"
          label="Refresh"
          outlined
          size="small"
          :loading="loading"
          @click="fetchStats"
        />
      </div>

      <!-- KPI Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-5 border border-surface-200 dark:border-surface-700 shadow-sm">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-sm text-surface-500 dark:text-surface-400 font-medium">Total Contacts</p>
              <p class="text-3xl font-bold text-surface-900 dark:text-surface-50 mt-1">
                {{ loading ? '–' : stats.total_contacts.toLocaleString() }}
              </p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center">
              <i class="pi pi-users text-blue-600 dark:text-blue-400 text-lg" />
            </div>
          </div>
        </div>

        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-5 border border-surface-200 dark:border-surface-700 shadow-sm">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-sm text-surface-500 dark:text-surface-400 font-medium">Active Leads</p>
              <p class="text-3xl font-bold text-surface-900 dark:text-surface-50 mt-1">
                {{ loading ? '–' : stats.active_leads.toLocaleString() }}
              </p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-orange-50 dark:bg-orange-900/30 flex items-center justify-center">
              <i class="pi pi-bolt text-orange-600 dark:text-orange-400 text-lg" />
            </div>
          </div>
        </div>

        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-5 border border-surface-200 dark:border-surface-700 shadow-sm">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-sm text-surface-500 dark:text-surface-400 font-medium">Open Opportunities</p>
              <p class="text-3xl font-bold text-surface-900 dark:text-surface-50 mt-1">
                {{ loading ? '–' : stats.open_opportunities.toLocaleString() }}
              </p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-violet-50 dark:bg-violet-900/30 flex items-center justify-center">
              <i class="pi pi-briefcase text-violet-600 dark:text-violet-400 text-lg" />
            </div>
          </div>
        </div>

        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-5 border border-surface-200 dark:border-surface-700 shadow-sm">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-sm text-surface-500 dark:text-surface-400 font-medium">Win Rate</p>
              <p class="text-3xl font-bold text-surface-900 dark:text-surface-50 mt-1">
                {{ loading ? '–' : `${stats.win_rate}%` }}
              </p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-green-50 dark:bg-green-900/30 flex items-center justify-center">
              <i class="pi pi-chart-line text-green-600 dark:text-green-400 text-lg" />
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Activities -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700">
        <div class="flex items-center justify-between px-5 py-4 border-b border-surface-200 dark:border-surface-700">
          <h2 class="font-semibold text-surface-900 dark:text-surface-50">Recent Activities</h2>
          <Button
            label="View all"
            text
            size="small"
            @click="goToActivities"
          />
        </div>

        <div v-if="loading" class="p-5 space-y-3">
          <Skeleton v-for="i in 4" :key="i" height="3rem" />
        </div>

        <div v-else-if="recentActivities.length === 0" class="p-10 text-center text-surface-400">
          No recent activities.
        </div>

        <div v-else class="divide-y divide-surface-100 dark:divide-surface-700">
          <div
            v-for="activity in recentActivities"
            :key="activity.id"
            class="flex items-start gap-3 px-5 py-3"
          >
            <div
              :class="[
                'w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0',
                activityIconBg(activity.type),
              ]"
            >
              <i :class="['text-white text-xs', activityIcon(activity.type)]" />
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-surface-700 dark:text-surface-200 truncate">
                {{ activity.title }}
              </p>
              <p class="text-xs text-surface-400 mt-0.5">
                {{ activity.user?.name }} · {{ formatDate(activity.due_at ?? activity.created_at) }}
              </p>
            </div>
            <Tag
              :value="activity.status"
              :severity="activityStatusSeverity(activity.status)"
              class="text-xs"
            />
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Skeleton from 'primevue/skeleton'
import AppLayout from '@/Layouts/AppLayout.vue'

interface User {
  id: number
  name: string
}

interface Activity {
  id: number
  type: string
  title: string
  status: string
  due_at: string | null
  created_at: string
  user: User | null
}

const loading = ref(false)

const stats = reactive({
  total_contacts: 0,
  active_leads: 0,
  open_opportunities: 0,
  win_rate: 0,
})

const recentActivities = ref<Activity[]>([])

const fetchStats = async () => {
  loading.value = true
  try {
    const [contactsRes, leadsRes, oppsRes, activitiesRes] = await Promise.all([
      fetch('/api/v1/crm/contacts?per_page=1', { headers: { Accept: 'application/json' } }),
      fetch('/api/v1/crm/leads?status=new&per_page=1', { headers: { Accept: 'application/json' } }),
      fetch('/api/v1/crm/opportunities?status=open&per_page=1', { headers: { Accept: 'application/json' } }),
      fetch('/api/v1/crm/activities?per_page=10', { headers: { Accept: 'application/json' } }),
    ])

    const [contacts, leads, opps, activities] = await Promise.all([
      contactsRes.json(),
      leadsRes.json(),
      oppsRes.json(),
      activitiesRes.json(),
    ])

    stats.total_contacts = contacts.total ?? 0
    stats.active_leads = leads.total ?? 0
    stats.open_opportunities = opps.total ?? 0
    recentActivities.value = activities.data ?? []

    // Compute win rate from won vs closed opportunities
    const wonRes = await fetch('/api/v1/crm/opportunities?status=won&per_page=1', {
      headers: { Accept: 'application/json' },
    })
    const lostRes = await fetch('/api/v1/crm/opportunities?status=lost&per_page=1', {
      headers: { Accept: 'application/json' },
    })
    const [won, lost] = await Promise.all([wonRes.json(), lostRes.json()])
    const totalClosed = (won.total ?? 0) + (lost.total ?? 0)
    stats.win_rate = totalClosed > 0 ? Math.round(((won.total ?? 0) / totalClosed) * 100) : 0
  } finally {
    loading.value = false
  }
}

const formatDate = (dateStr: string | null) => {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
}

const activityIcon = (type: string) => {
  const icons: Record<string, string> = {
    call: 'pi pi-phone',
    email: 'pi pi-envelope',
    meeting: 'pi pi-calendar',
    task: 'pi pi-check-circle',
    note: 'pi pi-file',
  }
  return icons[type] ?? 'pi pi-circle'
}

const activityIconBg = (type: string) => {
  const bgs: Record<string, string> = {
    call: 'bg-green-500',
    email: 'bg-blue-500',
    meeting: 'bg-violet-500',
    task: 'bg-orange-500',
    note: 'bg-surface-400',
  }
  return bgs[type] ?? 'bg-surface-400'
}

const activityStatusSeverity = (status: string) => {
  switch (status) {
    case 'done': return 'success'
    case 'cancelled': return 'danger'
    default: return 'info'
  }
}

const goToActivities = () => {
  router.visit('/crm/activities')
}

onMounted(() => {
  fetchStats()
})
</script>
