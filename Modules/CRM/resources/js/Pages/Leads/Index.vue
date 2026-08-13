<template>
  <AppLayout>
    <Head title="Lead Management" />

    <div class="space-y-6">
      <!-- Page header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">
            Lead Management
          </h1>
          <p class="text-surface-500 text-sm mt-1">
            Score, qualify, and convert leads to contacts
          </p>
        </div>
        <div class="flex gap-2">
          <Button
            icon="pi pi-plus"
            label="Import Leads"
            severity="secondary"
            @click="showImportDialog = true"
          />
          <Button
            icon="pi pi-plus"
            label="New Lead"
            @click="showNewLeadDialog = true"
          />
        </div>
      </div>

      <!-- Lead Scoring Rules Panel -->
      <Accordion value="scoring-rules">
        <AccordionTab header="Lead Scoring Rules" value="scoring-rules">
          <div class="space-y-4">
            <div v-for="rule in scoringRules" :key="rule.id" class="flex items-center justify-between p-3 bg-surface-50 dark:bg-surface-700/30 rounded-lg">
              <div>
                <p class="font-medium text-surface-900 dark:text-surface-50">{{ rule.name }}</p>
                <p class="text-xs text-surface-500 mt-1">{{ rule.description }}</p>
              </div>
              <div class="flex items-center gap-2">
                <Tag :value="rule.points + ' pts'" severity="info" />
              </div>
            </div>
          </div>
        </AccordionTab>
      </Accordion>

      <!-- Filters -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-4 flex items-center gap-4 flex-wrap">
        <Select
          v-model="selectedStatus"
          :options="statusOptions"
          option-label="label"
          option-value="value"
          placeholder="All Status"
          show-clear
          class="w-48"
          @change="applyFilters"
        />
        <InputText
          v-model="searchQuery"
          placeholder="Search leads..."
          class="flex-1 min-w-48"
          @input="applyFilters"
        />
      </div>

      <!-- Lead Statistics -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
          <p class="text-surface-500 text-sm">Total Leads</p>
          <p class="text-3xl font-bold text-surface-900 dark:text-surface-50 mt-2">{{ leads.length }}</p>
        </div>
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
          <p class="text-surface-500 text-sm">Conversion Rate</p>
          <p class="text-3xl font-bold text-green-600 mt-2">{{ conversionRate }}%</p>
        </div>
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
          <p class="text-surface-500 text-sm">Avg Lead Score</p>
          <p class="text-3xl font-bold text-surface-900 dark:text-surface-50 mt-2">{{ avgLeadScore }}</p>
        </div>
      </div>

      <!-- Leads Table -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 overflow-hidden">
        <DataTable
          :value="filteredLeads"
          :loading="loading"
          striped-rows
          class="p-datatable-sm"
          paginator
          :rows="20"
        >
          <Column field="name" header="Name" sortable>
            <template #body="{ data }">
              <div class="font-medium">{{ data.name }}</div>
              <p class="text-xs text-surface-500">{{ data.email }}</p>
            </template>
          </Column>
          <Column field="company" header="Company">
            <template #body="{ data }">
              {{ data.company ?? '—' }}
            </template>
          </Column>
          <Column field="score" header="Score">
            <template #body="{ data }">
              <div class="flex items-center gap-2">
                <ProgressBar :value="data.score" :show-value="false" style="height: 8px; width: 80px" />
                <span class="font-medium">{{ data.score }}</span>
              </div>
            </template>
          </Column>
          <Column field="status" header="Status">
            <template #body="{ data }">
              <Tag :value="data.status" :severity="getStatusSeverity(data.status)" />
            </template>
          </Column>
          <Column header="Actions">
            <template #body="{ data }">
              <div class="flex gap-1">
                <Button
                  v-if="data.status === 'qualified'"
                  icon="pi pi-arrow-right"
                  outlined
                  size="small"
                  @click="convertToContact(data)"
                />
                <Button icon="pi pi-trash" outlined severity="danger" size="small" @click="deleteLead(data)" />
              </div>
            </template>
          </Column>
        </DataTable>
      </div>
    </div>

    <!-- New Lead Dialog -->
    <Dialog v-model:visible="showNewLeadDialog" header="New Lead" modal class="w-full max-w-2xl">
      <form @submit.prevent="saveLead" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">First Name</label>
            <InputText v-model="newLead.first_name" placeholder="First name" class="w-full" required />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Last Name</label>
            <InputText v-model="newLead.last_name" placeholder="Last name" class="w-full" required />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Email</label>
            <InputText v-model="newLead.email" type="email" placeholder="email@example.com" class="w-full" required />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Company</label>
            <InputText v-model="newLead.company" placeholder="Company" class="w-full" />
          </div>
        </div>
        <div class="flex justify-end gap-2 pt-4">
          <Button label="Cancel" severity="secondary" @click="showNewLeadDialog = false" />
          <Button label="Create" icon="pi pi-check" :loading="saving" />
        </div>
      </form>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import Button from 'primevue/button'
import Select from 'primevue/select'
import InputText from 'primevue/inputtext'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'
import ProgressBar from 'primevue/progressbar'
import Accordion from 'primevue/accordion'
import AccordionTab from 'primevue/accordiontab'
import AppLayout from '@/Layouts/AppLayout.vue'

interface Lead {
  id: number
  name: string
  email: string
  company: string | null
  score: number
  status: string
}

const loading = ref(false)
const saving = ref(false)
const selectedStatus = ref<string | null>(null)
const searchQuery = ref('')
const showNewLeadDialog = ref(false)

const leads = ref<Lead[]>([])
const scoringRules = ref([
  { id: 1, name: 'Email Engagement', description: 'Opens >3 emails', points: 15 },
  { id: 2, name: 'Content Download', description: 'Downloaded resource', points: 20 },
  { id: 3, name: 'Demo Request', description: 'Requested demo', points: 30 },
])

const statusOptions = [
  { label: 'New', value: 'new' },
  { label: 'Contacted', value: 'contacted' },
  { label: 'Qualified', value: 'qualified' },
]

const newLead = ref({
  first_name: '',
  last_name: '',
  email: '',
  company: '',
})

const filteredLeads = computed(() =>
  leads.value.filter(lead => {
    const matchesStatus = !selectedStatus.value || lead.status === selectedStatus.value
    const matchesSearch = !searchQuery.value || 
      lead.name.toLowerCase().includes(searchQuery.value.toLowerCase())
    return matchesStatus && matchesSearch
  })
)

const conversionRate = computed(() => {
  const converted = leads.value.filter(l => l.status === 'qualified').length
  return leads.value.length > 0 ? Math.round((converted / leads.value.length) * 100) : 0
})

const avgLeadScore = computed(() => {
  const sum = leads.value.reduce((acc, lead) => acc + lead.score, 0)
  return leads.value.length > 0 ? Math.round(sum / leads.value.length) : 0
})

const getStatusSeverity = (status: string): string => {
  return status === 'qualified' ? 'success' : status === 'contacted' ? 'warning' : 'info'
}

const applyFilters = () => {
  loadLeads()
}

const loadLeads = async () => {
  loading.value = true
  try {
    const response = await fetch('/api/v1/crm/leads', {
      headers: { Accept: 'application/json' },
    })
    const data = await response.json()
    leads.value = data.data || []
  } catch (error) {
    console.error('Failed to load leads:', error)
  } finally {
    loading.value = false
  }
}

const saveLead = async () => {
  saving.value = true
  try {
    const response = await fetch('/api/v1/crm/leads', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(newLead.value),
    })
    if (response.ok) {
      showNewLeadDialog.value = false
      newLead.value = { first_name: '', last_name: '', email: '', company: '' }
      await loadLeads()
    }
  } catch (error) {
    console.error('Error creating lead:', error)
  } finally {
    saving.value = false
  }
}

const deleteLead = async (lead: Lead) => {
  if (!confirm(`Delete lead ${lead.name}?`)) return
  try {
    await fetch(`/api/v1/crm/leads/${lead.id}`, { method: 'DELETE' })
    await loadLeads()
  } catch (error) {
    console.error('Error deleting lead:', error)
  }
}

const convertToContact = async (lead: Lead) => {
  try {
    await fetch(`/api/v1/crm/leads/${lead.id}/convert`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
    })
    await loadLeads()
  } catch (error) {
    console.error('Error converting lead:', error)
  }
}

onMounted(() => {
  loadLeads()
})
</script>
