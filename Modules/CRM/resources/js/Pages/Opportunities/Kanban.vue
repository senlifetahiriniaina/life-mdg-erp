<template>
  <AppLayout>
    <Head title="Opportunities – Kanban" />

    <!-- Chantier 32.15 (CRM deep 14-layer audit): this real, routed page never
         called useAiAssistant() at all before this fix. -->
    <AIAssistantPanel v-if="showAiPanel" :guidance="guidance" @close="showAiPanel = false" />

    <div class="space-y-4">
      <!-- Page header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">
            Opportunities
          </h1>
          <p class="text-surface-500 text-sm mt-1">Drag cards between stages to update status</p>
        </div>
        <div class="flex gap-2">
          <Select
            v-model="selectedPipelineId"
            :options="pipelines"
            option-label="name"
            option-value="id"
            placeholder="Select pipeline"
            class="w-52"
            @change="fetchBoard"
          />
          <Button icon="pi pi-plus" label="New Opportunity" @click="showCreate = true" />
        </div>
      </div>

      <!-- Loading skeleton -->
      <div v-if="loading" class="flex gap-4 overflow-x-auto pb-4">
        <div
          v-for="i in 5"
          :key="i"
          class="flex-shrink-0 w-72 bg-surface-100 dark:bg-surface-800 rounded-xl p-4 space-y-3"
        >
          <Skeleton height="1.5rem" class="mb-2" />
          <Skeleton v-for="j in 3" :key="j" height="5rem" class="rounded-lg" />
        </div>
      </div>

      <!-- Kanban board -->
      <div v-else class="flex gap-4 overflow-x-auto pb-4">
        <div
          v-for="column in board"
          :key="column.stage"
          class="flex-shrink-0 w-72"
        >
          <!-- Column header -->
          <div class="flex items-center justify-between mb-3 px-1">
            <div class="flex items-center gap-2">
              <span class="font-semibold text-surface-800 dark:text-surface-100 capitalize">
                {{ column.stage }}
              </span>
              <span class="text-xs bg-surface-200 dark:bg-surface-700 text-surface-600 dark:text-surface-300 px-2 py-0.5 rounded-full font-medium">
                {{ column.opportunities.length }}
              </span>
            </div>
            <span class="text-xs text-surface-400">{{ column.probability }}%</span>
          </div>

          <!-- Drop zone -->
          <div
            class="min-h-20 rounded-xl bg-surface-100 dark:bg-surface-800/60 p-2 space-y-2 transition-colors"
            :class="{ 'ring-2 ring-primary-400 bg-primary-50 dark:bg-primary-900/20': dragOverStage === column.stage }"
            @dragover.prevent="dragOverStage = column.stage"
            @dragleave="dragOverStage = null"
            @drop.prevent="onDrop(column.stage)"
          >
            <!-- Opportunity cards -->
            <div
              v-for="opp in column.opportunities"
              :key="opp.id"
              class="bg-surface-0 dark:bg-surface-800 rounded-lg p-3 border border-surface-200 dark:border-surface-700 shadow-sm cursor-grab active:cursor-grabbing select-none"
              draggable="true"
              @dragstart="onDragStart(opp, column.stage)"
              @dragend="dragOverStage = null"
            >
              <p class="font-medium text-surface-900 dark:text-surface-50 text-sm leading-snug mb-1">
                {{ opp.name }}
              </p>
              <p v-if="opp.account" class="text-xs text-surface-500 dark:text-surface-400 mb-2">
                {{ opp.account.name }}
              </p>
              <div class="flex items-center justify-between">
                <span class="text-sm font-semibold text-primary-600 dark:text-primary-400">
                  {{ formatCurrency(opp.amount, opp.currency) }}
                </span>
                <span class="text-xs text-surface-400 bg-surface-100 dark:bg-surface-700 px-1.5 py-0.5 rounded">
                  {{ opp.probability }}%
                </span>
              </div>
            </div>

            <div
              v-if="column.opportunities.length === 0"
              class="text-center py-6 text-surface-300 dark:text-surface-600 text-sm"
            >
              Drop here
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create Opportunity Dialog -->
    <Dialog
      v-model:visible="showCreate"
      header="New Opportunity"
      modal
      class="w-full max-w-xl"
    >
      <OpportunityCreateForm
        :pipelines="pipelines"
        :selected-pipeline-id="selectedPipelineId"
        @saved="onOpportunitySaved"
        @cancel="showCreate = false"
      />
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import Button from 'primevue/button'
import Select from 'primevue/select'
import Dialog from 'primevue/dialog'
import Skeleton from 'primevue/skeleton'
import AppLayout from '@/Layouts/AppLayout.vue'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import OpportunityCreateForm from './CreateForm.vue'

interface Account {
  id: number
  name: string
}

interface Opportunity {
  id: number
  name: string
  amount: number
  currency: string
  probability: number
  account: Account | null
  stage: string
}

interface KanbanColumn {
  stage: string
  order: number
  probability: number
  opportunities: Opportunity[]
}

interface Pipeline {
  id: number
  name: string
  is_default: boolean
}

// Chantier 32.15 (CRM deep 14-layer audit): this real, routed page never
// called useAiAssistant() at all before this fix.
const { guidance } = useAiAssistant('CRM', 'manage_opportunities_kanban')
const showAiPanel = ref(true)

const loading = ref(false)
const board = ref<KanbanColumn[]>([])
const pipelines = ref<Pipeline[]>([])
const selectedPipelineId = ref<number | null>(null)
const showCreate = ref(false)
const dragOverStage = ref<string | null>(null)

let draggingOpp: Opportunity | null = null
let draggingFromStage: string | null = null

const formatCurrency = (amount: number, currency = 'USD') => {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(amount)
}

// Chantier 32.15: missing-CSRF-token fetch() bug — see resources/js/Pages/CRM/Contacts/
// Form.vue's comment (same app-wide bug pattern, this instance on the stage drag-and-drop).
function getCsrf(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? ''
}

const fetchPipelines = async () => {
  const response = await fetch('/api/v1/crm/pipelines?per_page=50', {
    headers: { Accept: 'application/json' },
  })
  const data = await response.json()
  pipelines.value = data.data ?? []
  const defaultPipeline = pipelines.value.find(p => p.is_default) ?? pipelines.value[0]
  if (defaultPipeline) {
    selectedPipelineId.value = defaultPipeline.id
  }
}

const fetchBoard = async () => {
  if (!selectedPipelineId.value) return
  loading.value = true
  try {
    const response = await fetch(
      `/api/v1/crm/opportunities/kanban?pipeline_id=${selectedPipelineId.value}`,
      { headers: { Accept: 'application/json' } },
    )
    board.value = await response.json()
  } finally {
    loading.value = false
  }
}

const onDragStart = (opp: Opportunity, stage: string) => {
  draggingOpp = opp
  draggingFromStage = stage
}

const onDrop = async (targetStage: string) => {
  dragOverStage.value = null

  if (!draggingOpp || draggingFromStage === targetStage) return

  const opp = draggingOpp

  // Optimistic update
  const fromCol = board.value.find(c => c.stage === draggingFromStage)
  const toCol = board.value.find(c => c.stage === targetStage)

  if (fromCol && toCol) {
    fromCol.opportunities = fromCol.opportunities.filter(o => o.id !== opp.id)
    toCol.opportunities.push({ ...opp, stage: targetStage })
  }

  draggingOpp = null
  draggingFromStage = null

  await fetch(`/api/v1/crm/opportunities/${opp.id}`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-CSRF-TOKEN': getCsrf(),
    },
    body: JSON.stringify({ stage: targetStage }),
  })
}

const onOpportunitySaved = () => {
  showCreate.value = false
  fetchBoard()
}

onMounted(async () => {
  await fetchPipelines()
  await fetchBoard()
})
</script>
