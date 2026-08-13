<template>
  <AppLayout>
    <main class="p-6">
      <header class="mb-6">
        <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">CRM Dashboard</h1>
      </header>

      <section class="grid grid-cols-4 gap-4 mb-6" aria-labelledby="stats-title">
        <h2 id="stats-title" class="sr-only">Key Statistics</h2>
        <Card class="text-center">
          <template #content>
            <p class="text-surface-600 dark:text-surface-400 text-sm">Total Accounts</p>
            <p class="text-4xl font-bold text-primary-700 dark:text-primary-300 mt-2">{{ stats.total_accounts }}</p>
          </template>
        </Card>
        <Card class="text-center">
          <template #content>
            <p class="text-surface-600 dark:text-surface-400 text-sm">Total Contacts</p>
            <p class="text-4xl font-bold text-green-700 dark:text-green-300 mt-2">{{ stats.total_contacts }}</p>
          </template>
        </Card>
        <Card class="text-center">
          <template #content>
            <p class="text-surface-600 dark:text-surface-400 text-sm">Active Opportunities</p>
            <p class="text-4xl font-bold text-yellow-700 dark:text-yellow-300 mt-2">{{ stats.active_opportunities }}</p>
          </template>
        </Card>
        <Card class="text-center">
          <template #content>
            <p class="text-surface-600 dark:text-surface-400 text-sm">Pipeline Value</p>
            <p class="text-2xl font-bold text-surface-900 dark:text-surface-50 text-violet-700 dark:text-violet-300 mt-2">{{ formatCurrency(stats.pipeline_value) }}</p>
          </template>
        </Card>
      </section>

      <section class="grid grid-cols-2 gap-6 mb-6" aria-labelledby="pipeline-title">
        <Card>
          <template #title>Sales Pipeline</template>
          <template #content>
            <Chart type="bar" :data="pipelineData" :options="chartOptions" />
          </template>
        </Card>

        <Card>
          <template #title>Opportunity Status Distribution</template>
          <template #content>
            <Chart type="doughnut" :data="opportunityData" :options="chartOptions" />
          </template>
        </Card>
      </section>

      <section class="grid grid-cols-2 gap-6" aria-labelledby="recent-title">
        <h2 id="recent-title" class="sr-only">Recent Activities</h2>
        <Card>
          <template #title>Recent Opportunities</template>
          <template #content>
            <DataTable :value="recentOpportunities" class="p-datatable-sm">
              <Column field="name" header="Opportunity" />
              <Column field="account.name" header="Account" />
              <Column field="stage" header="Stage">
                <template #body="{ data }">
                  <Tag :value="data.stage" :severity="getStageColor(data.stage)" />
                </template>
              </Column>
              <Column field="amount" header="Amount" align="right">
                <template #body="{ data }">
                  {{ formatCurrency(data.amount) }}
                </template>
              </Column>
            </DataTable>
          </template>
        </Card>

        <Card>
          <template #title>Top Contacts</template>
          <template #content>
            <DataTable :value="topContacts" class="p-datatable-sm">
              <Column field="name" header="Contact" />
              <Column field="account.name" header="Account" />
              <Column field="interactions_count" header="Interactions">
                <template #body="{ data }">
                  <Badge :value="data.interactions_count" />
                </template>
              </Column>
              <Column field="last_contact_date" header="Last Contact">
                <template #body="{ data }">
                  {{ formatDate(data.last_contact_date) }}
                </template>
              </Column>
            </DataTable>
          </template>
        </Card>
      </section>
    </main>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Card from 'primevue/card'
import Chart from 'primevue/chart'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Badge from 'primevue/badge'

defineProps({
  stats: Object,
  recentOpportunities: Array,
  topContacts: Array,
  pipelineData: Object,
  opportunityData: Object
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom'
    }
  }
}

const getStageColor = (stage) => {
  const map = { prospecting: 'info', qualification: 'warning', proposal: 'warning', negotiation: 'warning', closed_won: 'success', closed_lost: 'danger' }
  return map[stage] || 'info'
}

const formatCurrency = (value) => {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value || 0)
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}
</script>
