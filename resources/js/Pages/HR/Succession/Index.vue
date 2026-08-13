<template>
  <AppLayout>
    <Head title="Succession Planning" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Succession Planning</h1>
        <p class="wh-page-subtitle">Critical positions and candidate pipeline</p>
      </div>
      <div class="page-actions">
        <Button label="Add Position" icon="pi pi-plus" @click="showCreatePlan = true" />
      </div>
    </div>

    <!-- Critical positions table -->
    <div class="wh-panel">
      <DataTable :value="plans" :loading="loading" stripedRows expandedRowIcon="pi pi-chevron-down">
        <Column field="position" header="Position" />
        <Column field="department" header="Department" />
        <Column field="criticality" header="Criticality">
          <template #body="{ data }">
            <Tag :value="data.criticality" :severity="criticalitySeverity(data.criticality)" />
          </template>
        </Column>
        <Column header="Incumbent">
          <template #body="{ data }">
            <span v-if="data.incumbent">
              {{ data.incumbent.first_name }} {{ data.incumbent.last_name }}
            </span>
            <span v-else style="color:var(--fg-3)">—</span>
          </template>
        </Column>
        <Column header="Candidates">
          <template #body="{ data }">
            <span>{{ data.candidates?.length ?? 0 }}</span>
          </template>
        </Column>
        <Column header="Actions">
          <template #body="{ data }">
            <Button
              label="View Candidates"
              size="small"
              severity="secondary"
              @click="openCandidates(data)"
            />
            <Button
              label="AI Suggest"
              size="small"
              icon="pi pi-sparkles"
              severity="info"
              class="ml-2"
              @click="aiSuggest(data)"
            />
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- Candidates Dialog -->
    <Dialog v-model:visible="showCandidates" :header="`Candidates — ${selectedPlan?.position}`" modal style="width:640px">
      <div v-if="selectedPlan">
        <div style="display:flex;justify-content:flex-end;margin-bottom:10px">
          <Button label="Add Candidate" size="small" @click="showAddCandidate = true" />
        </div>
        <DataTable :value="selectedPlan.candidates" stripedRows>
          <Column header="Employee">
            <template #body="{ data }">
              {{ data.employee?.first_name }} {{ data.employee?.last_name }}
            </template>
          </Column>
          <Column field="readiness" header="Readiness">
            <template #body="{ data }">
              <Tag :value="readinessLabel(data.readiness)" :severity="readinessSeverity(data.readiness)" />
            </template>
          </Column>
          <Column field="potential" header="Potential">
            <template #body="{ data }">
              <Tag :value="data.potential" :severity="potentialSeverity(data.potential)" />
            </template>
          </Column>
          <Column field="notes" header="Notes" />
        </DataTable>
      </div>
      <template #footer>
        <Button label="Close" severity="secondary" @click="showCandidates = false" />
      </template>
    </Dialog>

    <!-- Add Candidate Dialog -->
    <Dialog v-model:visible="showAddCandidate" header="Add Candidate" modal style="width:440px">
      <div class="flex flex-col gap-3 mt-2">
        <div>
          <label class="block mb-1 text-sm font-medium">Employee ID</label>
          <InputNumber v-model="candidateForm.employee_id" class="w-full" />
        </div>
        <div>
          <label class="block mb-1 text-sm font-medium">Readiness</label>
          <Dropdown v-model="candidateForm.readiness" :options="readinessOptions" optionLabel="label" optionValue="value" class="w-full" />
        </div>
        <div>
          <label class="block mb-1 text-sm font-medium">Potential</label>
          <Dropdown v-model="candidateForm.potential" :options="potentialOptions" optionLabel="label" optionValue="value" class="w-full" />
        </div>
        <div>
          <label class="block mb-1 text-sm font-medium">Notes</label>
          <Textarea v-model="candidateForm.notes" rows="2" class="w-full" />
        </div>
      </div>
      <template #footer>
        <Button label="Cancel" severity="secondary" @click="showAddCandidate = false" />
        <Button label="Add" @click="addCandidate" />
      </template>
    </Dialog>

    <!-- Create Plan Dialog -->
    <Dialog v-model:visible="showCreatePlan" header="New Critical Position" modal style="width:480px">
      <div class="flex flex-col gap-3 mt-2">
        <div>
          <label class="block mb-1 text-sm font-medium">Position Title</label>
          <InputText v-model="planForm.position" class="w-full" />
        </div>
        <div>
          <label class="block mb-1 text-sm font-medium">Department</label>
          <InputText v-model="planForm.department" class="w-full" />
        </div>
        <div>
          <label class="block mb-1 text-sm font-medium">Criticality</label>
          <Dropdown v-model="planForm.criticality" :options="criticalityOptions" optionLabel="label" optionValue="value" class="w-full" />
        </div>
      </div>
      <template #footer>
        <Button label="Cancel" severity="secondary" @click="showCreatePlan = false" />
        <Button label="Create" @click="createPlan" />
      </template>
    </Dialog>

    <!-- AI Suggest Dialog -->
    <Dialog v-model:visible="showAiDialog" header="AI Candidate Suggestions" modal style="width:560px">
      <div v-if="loadingAi" class="text-center py-6">
        <i class="pi pi-spin pi-spinner" style="font-size:2rem" />
        <p class="mt-2 text-gray-500 dark:text-surface-400">Identifying candidates...</p>
      </div>
      <div v-else-if="aiSuggestions" class="whitespace-pre-line text-sm mt-2">{{ aiSuggestions }}</div>
      <template #footer>
        <Button label="Close" severity="secondary" @click="showAiDialog = false" />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, DataTable, Column, Dialog, Tag, InputText, Textarea, Dropdown, InputNumber } from 'primevue'
import axios from 'axios'

const plans            = ref<any[]>([])
const loading          = ref(false)
const showCreatePlan   = ref(false)
const showCandidates   = ref(false)
const showAddCandidate = ref(false)
const showAiDialog     = ref(false)
const loadingAi        = ref(false)
const aiSuggestions    = ref('')
const selectedPlan     = ref<any>(null)

const planForm = ref({ position: '', department: '', criticality: 'high' })
const candidateForm = ref({ employee_id: null as number | null, readiness: 'ready_now', potential: 'high', notes: '' })

const criticalityOptions = [
  { label: 'Low',      value: 'low' },
  { label: 'Medium',   value: 'medium' },
  { label: 'High',     value: 'high' },
  { label: 'Critical', value: 'critical' },
]
const readinessOptions = [
  { label: 'Ready Now',   value: 'ready_now' },
  { label: '1-2 Years',   value: '1_2_years' },
  { label: '3-5 Years',   value: '3_5_years' },
]
const potentialOptions = [
  { label: 'High',   value: 'high' },
  { label: 'Medium', value: 'medium' },
  { label: 'Low',    value: 'low' },
]

const criticalitySeverity = (c: string) => ({ low: 'secondary', medium: 'info', high: 'warning', critical: 'danger' }[c] ?? 'secondary')
const readinessSeverity   = (r: string) => ({ ready_now: 'success', '1_2_years': 'warning', '3_5_years': 'secondary' }[r] ?? 'secondary')
const potentialSeverity   = (p: string) => ({ high: 'success', medium: 'warning', low: 'danger' }[p] ?? 'secondary')
const readinessLabel      = (r: string) => ({ ready_now: 'Ready Now', '1_2_years': '1-2 Years', '3_5_years': '3-5 Years' }[r] ?? r)

const fetchPlans = async () => {
  loading.value = true
  try {
    const res = await axios.get('/api/v1/hr/succession-v2')
    plans.value = res.data
  } finally {
    loading.value = false
  }
}

const openCandidates = (plan: any) => {
  selectedPlan.value = plan
  showCandidates.value = true
}

const addCandidate = async () => {
  if (!selectedPlan.value || !candidateForm.value.employee_id) return
  await axios.post(`/api/v1/hr/succession-v2/${selectedPlan.value.id}/candidates`, candidateForm.value)
  showAddCandidate.value = false
  candidateForm.value = { employee_id: null, readiness: 'ready_now', potential: 'high', notes: '' }
  await fetchPlans()
  selectedPlan.value = plans.value.find((p) => p.id === selectedPlan.value.id) ?? null
}

const createPlan = async () => {
  await axios.post('/api/v1/hr/succession-v2', planForm.value)
  showCreatePlan.value = false
  planForm.value = { position: '', department: '', criticality: 'high' }
  fetchPlans()
}

const aiSuggest = async (plan: any) => {
  showAiDialog.value = true
  loadingAi.value = true
  aiSuggestions.value = ''
  try {
    const res = await axios.post(`/api/v1/hr/succession-v2/${plan.id}/suggest`)
    aiSuggestions.value = res.data.suggestions ?? ''
  } finally {
    loadingAi.value = false
  }
}

onMounted(fetchPlans)
</script>
