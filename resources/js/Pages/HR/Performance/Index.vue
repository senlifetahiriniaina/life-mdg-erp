<template>
  <AppLayout>
    <Head title="Performance Management" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Performance Management</h1>
        <p class="wh-page-subtitle">Cycles, appraisals and goal tracking</p>
      </div>
      <div class="page-actions">
        <Button label="New Cycle" icon="pi pi-plus" @click="showCreateCycle = true" />
      </div>
    </div>

    <!-- Tabs -->
    <div style="display:flex;gap:4px;margin-bottom:16px">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        :class="['btn', activeTab === tab.key ? 'btn-primary' : 'btn-secondary']"
        @click="activeTab = tab.key"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- Cycles Tab -->
    <div v-if="activeTab === 'cycles'">
      <div class="wh-panel">
        <DataTable :value="cycles" :loading="loadingCycles" stripedRows>
          <Column field="name" header="Name" />
          <Column field="type" header="Type">
            <template #body="{ data }">
              <Tag :value="data.type" :severity="typeSeverity(data.type)" />
            </template>
          </Column>
          <Column field="start_date" header="Start" />
          <Column field="end_date" header="End" />
          <Column field="status" header="Status">
            <template #body="{ data }">
              <Tag :value="data.status" :severity="statusSeverity(data.status)" />
            </template>
          </Column>
          <Column header="Actions">
            <template #body="{ data }">
              <Button
                v-if="data.status === 'draft'"
                label="Launch"
                size="small"
                severity="success"
                @click="launchCycle(data)"
              />
              <Button
                label="Appraisals"
                size="small"
                severity="secondary"
                class="ml-2"
                @click="viewAppraisals(data)"
              />
            </template>
          </Column>
        </DataTable>
      </div>
    </div>

    <!-- My Appraisals Tab -->
    <div v-if="activeTab === 'mine'">
      <div class="wh-panel">
        <DataTable :value="myAppraisals" :loading="loadingAppraisals" stripedRows>
          <Column field="cycle.name" header="Cycle" />
          <Column field="status" header="Status">
            <template #body="{ data }">
              <Tag :value="data.status" :severity="appraisalStatusSeverity(data.status)" />
            </template>
          </Column>
          <Column field="overall_score" header="Score" />
          <Column header="Actions">
            <template #body="{ data }">
              <Button
                v-if="data.status === 'pending' || data.status === 'self_review'"
                label="Self Review"
                size="small"
                @click="openSelfReview(data)"
              />
              <Button
                label="AI Suggestions"
                size="small"
                severity="secondary"
                icon="pi pi-sparkles"
                class="ml-2"
                @click="getAiSuggestions(data)"
              />
            </template>
          </Column>
        </DataTable>
      </div>
    </div>

    <!-- Team Tab -->
    <div v-if="activeTab === 'team'">
      <div class="wh-panel">
        <DataTable :value="teamAppraisals" :loading="loadingTeam" stripedRows>
          <Column field="employee.first_name" header="Employee" />
          <Column field="cycle.name" header="Cycle" />
          <Column field="status" header="Status">
            <template #body="{ data }">
              <Tag :value="data.status" :severity="appraisalStatusSeverity(data.status)" />
            </template>
          </Column>
          <Column field="overall_score" header="Score" />
          <Column header="Actions">
            <template #body="{ data }">
              <Button
                v-if="data.status === 'self_review'"
                label="Manager Review"
                size="small"
                severity="warning"
                @click="openManagerReview(data)"
              />
            </template>
          </Column>
        </DataTable>
      </div>
    </div>

    <!-- Create Cycle Dialog -->
    <Dialog v-model:visible="showCreateCycle" header="New Performance Cycle" modal style="width:480px">
      <div class="flex flex-col gap-3 mt-2">
        <div>
          <label class="block mb-1 text-sm font-medium">Name</label>
          <InputText v-model="cycleForm.name" class="w-full" />
        </div>
        <div>
          <label class="block mb-1 text-sm font-medium">Type</label>
          <Dropdown v-model="cycleForm.type" :options="cycleTypes" optionLabel="label" optionValue="value" class="w-full" />
        </div>
        <div class="flex gap-2">
          <div class="flex-1">
            <label class="block mb-1 text-sm font-medium">Start Date</label>
            <InputText v-model="cycleForm.start_date" type="date" class="w-full" />
          </div>
          <div class="flex-1">
            <label class="block mb-1 text-sm font-medium">End Date</label>
            <InputText v-model="cycleForm.end_date" type="date" class="w-full" />
          </div>
        </div>
      </div>
      <template #footer>
        <Button label="Cancel" severity="secondary" @click="showCreateCycle = false" />
        <Button label="Create" @click="createCycle" />
      </template>
    </Dialog>

    <!-- Self Review Dialog -->
    <Dialog v-model:visible="showSelfReview" header="Self Review" modal style="width:600px">
      <div v-if="selectedAppraisal" class="flex flex-col gap-4 mt-2">
        <div>
          <label class="block mb-1 text-sm font-medium">Strengths</label>
          <Textarea v-model="selfReviewForm.strengths" rows="3" class="w-full" />
        </div>
        <div>
          <label class="block mb-1 text-sm font-medium">Areas for Improvement</label>
          <Textarea v-model="selfReviewForm.improvements" rows="3" class="w-full" />
        </div>
        <div>
          <label class="block mb-1 text-sm font-medium">Goals for Next Period</label>
          <Textarea v-model="selfReviewForm.goals_next" rows="3" class="w-full" />
        </div>
        <div v-if="appraisalGoals.length">
          <p class="font-semibold mb-2">Rate your goals (1–5):</p>
          <div v-for="goal in appraisalGoals" :key="goal.id" class="mb-3 border rounded p-3">
            <p class="font-medium">{{ goal.title }}</p>
            <div class="flex items-center gap-3 mt-1">
              <InputNumber v-model="goal._score" :min="1" :max="5" :step="0.5" showButtons class="w-24" />
              <Dropdown
                v-model="goal._status"
                :options="goalStatuses"
                optionLabel="label"
                optionValue="value"
                class="flex-1"
              />
            </div>
          </div>
        </div>
      </div>
      <template #footer>
        <Button label="Cancel" severity="secondary" @click="showSelfReview = false" />
        <Button label="Submit Self Review" @click="submitSelfReview" />
      </template>
    </Dialog>

    <!-- AI Suggestions Dialog -->
    <Dialog v-model:visible="showAiSuggestions" header="AI Suggestions" modal style="width:560px">
      <div v-if="loadingAi" class="text-center py-6">
        <i class="pi pi-spin pi-spinner" style="font-size:2rem" />
        <p class="mt-2 text-gray-500 dark:text-surface-400">Generating suggestions...</p>
      </div>
      <div v-else-if="aiSuggestions" class="prose max-w-none mt-2 whitespace-pre-line text-sm">
        {{ aiSuggestions }}
      </div>
      <template #footer>
        <Button label="Close" severity="secondary" @click="showAiSuggestions = false" />
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

const activeTab = ref('cycles')
const tabs = [
  { key: 'cycles', label: 'Cycles' },
  { key: 'mine',   label: 'My Appraisals' },
  { key: 'team',   label: 'Team' },
]

const cycles            = ref<any[]>([])
const myAppraisals      = ref<any[]>([])
const teamAppraisals    = ref<any[]>([])
const loadingCycles     = ref(false)
const loadingAppraisals = ref(false)
const loadingTeam       = ref(false)
const showCreateCycle   = ref(false)
const showSelfReview    = ref(false)
const showAiSuggestions = ref(false)
const loadingAi         = ref(false)
const aiSuggestions     = ref('')
const selectedAppraisal = ref<any>(null)
const appraisalGoals    = ref<any[]>([])

const cycleForm = ref({ name: '', type: 'annual', start_date: '', end_date: '' })
const selfReviewForm = ref({ strengths: '', improvements: '', goals_next: '' })

const cycleTypes = [
  { label: 'Annual',      value: 'annual' },
  { label: 'Semi-Annual', value: 'semi_annual' },
  { label: 'Quarterly',   value: 'quarterly' },
]

const goalStatuses = [
  { label: 'Pending',       value: 'pending' },
  { label: 'In Progress',   value: 'in_progress' },
  { label: 'Achieved',      value: 'achieved' },
  { label: 'Not Achieved',  value: 'not_achieved' },
]

const statusSeverity = (s: string) => ({ draft: 'secondary', active: 'success', closed: 'danger' }[s] ?? 'info')
const typeSeverity   = (t: string) => ({ annual: 'info', semi_annual: 'warning', quarterly: 'success' }[t] ?? 'secondary')
const appraisalStatusSeverity = (s: string) => ({
  pending:        'secondary',
  self_review:    'warning',
  manager_review: 'info',
  completed:      'success',
}[s] ?? 'secondary')

const fetchCycles = async () => {
  loadingCycles.value = true
  try {
    const res = await axios.get('/api/v1/hr/perf-cycles')
    cycles.value = res.data.data ?? res.data
  } finally {
    loadingCycles.value = false
  }
}

const fetchMyAppraisals = async () => {
  loadingAppraisals.value = true
  try {
    const res = await axios.get('/api/v1/hr/perf-appraisals')
    myAppraisals.value = res.data.data ?? res.data
  } finally {
    loadingAppraisals.value = false
  }
}

const fetchTeamAppraisals = async () => {
  loadingTeam.value = true
  try {
    const res = await axios.get('/api/v1/hr/perf-appraisals')
    teamAppraisals.value = res.data.data ?? res.data
  } finally {
    loadingTeam.value = false
  }
}

const createCycle = async () => {
  await axios.post('/api/v1/hr/perf-cycles', cycleForm.value)
  showCreateCycle.value = false
  cycleForm.value = { name: '', type: 'annual', start_date: '', end_date: '' }
  fetchCycles()
}

const launchCycle = async (cycle: any) => {
  await axios.post(`/api/v1/hr/perf-cycles/${cycle.id}/launch`)
  fetchCycles()
}

const viewAppraisals = (cycle: any) => {
  activeTab.value = 'team'
  fetchTeamAppraisals()
}

const openSelfReview = async (appraisal: any) => {
  selectedAppraisal.value = appraisal
  selfReviewForm.value = { strengths: appraisal.strengths ?? '', improvements: appraisal.improvements ?? '', goals_next: appraisal.goals_next ?? '' }
  const res = await axios.get(`/api/v1/hr/perf-appraisals/${appraisal.id}`)
  appraisalGoals.value = (res.data.goals ?? []).map((g: any) => ({ ...g, _score: g.score ?? 3, _status: g.status ?? 'pending' }))
  showSelfReview.value = true
}

const submitSelfReview = async () => {
  if (!selectedAppraisal.value) return
  await axios.post(`/api/v1/hr/perf-appraisals/${selectedAppraisal.value.id}/self-review`, {
    ...selfReviewForm.value,
    goals: appraisalGoals.value.map((g) => ({ id: g.id, score: g._score, status: g._status })),
  })
  showSelfReview.value = false
  fetchMyAppraisals()
}

const openManagerReview = (appraisal: any) => {
  selectedAppraisal.value = appraisal
}

const getAiSuggestions = async (appraisal: any) => {
  showAiSuggestions.value = true
  loadingAi.value = true
  aiSuggestions.value = ''
  try {
    const res = await axios.post(`/api/v1/hr/perf-appraisals/${appraisal.id}/ai-suggestions`)
    aiSuggestions.value = res.data.suggestions ?? ''
  } finally {
    loadingAi.value = false
  }
}

onMounted(() => {
  fetchCycles()
  fetchMyAppraisals()
})
</script>
