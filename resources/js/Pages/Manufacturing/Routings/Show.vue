<template>
  <AppLayout>
    <Head :title="`Gamme · ${routing.name}`" />

    <div class="flex gap-6 p-6 min-h-screen">
      <!-- Main content -->
      <div class="flex-1 min-w-0">
        <!-- Header -->
        <div class="flex items-start justify-between mb-6">
          <div>
            <div class="flex items-center gap-3 mb-1">
              <a href="/manufacturing/routings" class="text-gray-400 hover:text-gray-600 dark:text-surface-300 dark:text-surface-300">
                <i class="pi pi-arrow-left text-sm" />
              </a>
              <h1 class="text-xl font-bold text-gray-900 dark:text-surface-50 dark:text-surface-50">{{ routing.name }}</h1>
              <span
                :class="routing.status === 'active'
                  ? 'bg-green-100 text-green-700'
                  : 'bg-gray-100 dark:bg-surface-700 text-gray-600 dark:text-surface-300 dark:text-surface-300'"
                class="text-xs font-medium px-2 py-0.5 rounded-full"
              >
                {{ routing.status === 'active' ? 'Actif' : 'Inactif' }}
              </span>
            </div>
            <p class="text-sm text-gray-500 dark:text-surface-400 ml-9">Code : <span class="font-mono font-medium">{{ routing.code }}</span></p>
          </div>

          <!-- Summary badges -->
          <div class="flex gap-2">
            <div class="flex items-center gap-1.5 bg-blue-50 dark:bg-surface-800 text-blue-700 text-sm font-medium px-3 py-1.5 rounded-lg">
              <i class="pi pi-clock text-sm" />
              {{ formatLeadTime(summary.total_lead_time_h) }}
            </div>
            <div class="flex items-center gap-1.5 bg-green-50 dark:bg-surface-800 text-green-700 text-sm font-medium px-3 py-1.5 rounded-lg">
              <i class="pi pi-euro text-sm" />
              {{ formatCurrency(summary.estimated_cost) }}
            </div>
            <button
              @click="showAddDialog = true"
              class="flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-3 py-1.5 rounded-lg transition-colors"
            >
              <i class="pi pi-plus text-sm" />
              Ajouter opération
            </button>
          </div>
        </div>

        <!-- Operations list -->
        <div class="space-y-2">
          <div v-if="operations.length === 0" class="text-center py-12 text-gray-400">
            <i class="pi pi-list text-3xl mb-2 block" />
            <p class="text-sm">Aucune opération. Ajoutez la première.</p>
          </div>

          <div
            v-for="(op, idx) in operations"
            :key="op.id"
            class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl border border-gray-100 shadow-sm"
            :draggable="true"
            @dragstart="onDragStart(idx)"
            @dragover.prevent="onDragOver(idx)"
            @drop.prevent="onDrop(idx)"
            :class="{ 'opacity-50': dragIndex === idx }"
          >
            <div class="p-4">
              <div class="flex items-start gap-3">
                <!-- Drag handle -->
                <button class="mt-1 text-gray-300 hover:text-gray-500 dark:text-surface-400 cursor-grab active:cursor-grabbing">
                  <i class="pi pi-bars text-sm" />
                </button>

                <!-- Sequence badge -->
                <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 text-xs font-bold flex items-center justify-center shrink-0 mt-0.5">
                  {{ op.sequence }}
                </div>

                <!-- Main info -->
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between gap-2 mb-1">
                    <h3 class="font-semibold text-gray-800 dark:text-surface-100 text-sm">{{ op.name }}</h3>
                    <div class="flex items-center gap-1">
                      <button
                        @click="editOp(op)"
                        class="text-gray-400 hover:text-blue-600 p-1 rounded transition-colors"
                        title="Modifier"
                      >
                        <i class="pi pi-pencil text-xs" />
                      </button>
                      <button
                        @click="deleteOp(op)"
                        class="text-gray-400 hover:text-red-600 p-1 rounded transition-colors"
                        title="Supprimer"
                      >
                        <i class="pi pi-trash text-xs" />
                      </button>
                    </div>
                  </div>

                  <div class="flex flex-wrap gap-3 text-xs text-gray-500 dark:text-surface-400">
                    <span v-if="op.work_center" class="flex items-center gap-1">
                      <i class="pi pi-building text-gray-400" />
                      {{ op.work_center.name }}
                    </span>
                    <span class="flex items-center gap-1">
                      <i class="pi pi-sliders-h text-gray-400" />
                      Réglage : {{ op.setup_time_minutes }} min
                    </span>
                    <span class="flex items-center gap-1">
                      <i class="pi pi-cog text-gray-400" />
                      Durée : {{ op.duration_minutes }} min
                    </span>
                    <span class="flex items-center gap-1">
                      <i class="pi pi-euro text-gray-400" />
                      {{ op.cost_per_hour }} €/h
                    </span>
                    <span
                      v-if="!op.active"
                      class="bg-gray-100 dark:bg-surface-700 text-gray-500 px-1.5 py-0.5 rounded"
                    >Inactif</span>
                  </div>
                </div>
              </div>

              <!-- Resources -->
              <div v-if="op.resources && op.resources.length" class="mt-3 ml-11 flex flex-wrap gap-2">
                <div
                  v-for="res in op.resources"
                  :key="res.id"
                  class="flex items-center gap-1.5 text-xs px-2 py-1 rounded-full border"
                  :class="resourceTypeClass(res.resource_type)"
                >
                  <i :class="resourceTypeIcon(res.resource_type)" class="text-xs" />
                  {{ res.name }} × {{ res.quantity }} {{ res.unit }}
                  <span v-if="res.required" class="text-red-400">*</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right panel: Summary + Gantt -->
      <aside class="w-72 shrink-0">
        <div class="sticky top-6 space-y-4">
          <!-- Summary card -->
          <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl border border-gray-100 shadow-sm p-4">
            <h2 class="font-semibold text-gray-800 dark:text-surface-100 text-sm mb-3">Résumé de la gamme</h2>
            <div class="space-y-2">
              <div class="flex justify-between text-sm">
                <span class="text-gray-500 dark:text-surface-400">Opérations</span>
                <span class="font-medium">{{ summary.operations_count ?? operations.length }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-gray-500 dark:text-surface-400">Durée totale</span>
                <span class="font-medium text-blue-700">{{ formatLeadTime(summary.total_lead_time_h) }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-gray-500 dark:text-surface-400">Coût MO estimé</span>
                <span class="font-medium text-green-700">{{ formatCurrency(summary.estimated_cost) }} €</span>
              </div>
            </div>
          </div>

          <!-- Gantt simplifié -->
          <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl border border-gray-100 shadow-sm p-4">
            <h2 class="font-semibold text-gray-800 dark:text-surface-100 text-sm mb-3">Diagramme Gantt</h2>
            <div v-if="operations.length === 0" class="text-xs text-gray-400 text-center py-4">
              Aucune opération
            </div>
            <div v-else class="space-y-2">
              <div
                v-for="op in operations"
                :key="`gantt-${op.id}`"
                class="space-y-1"
              >
                <div class="text-xs text-gray-500 dark:text-surface-400 truncate">{{ op.name }}</div>
                <div class="flex gap-1 h-5">
                  <!-- Setup bar (gray) -->
                  <div
                    v-if="op.setup_time_minutes > 0"
                    class="h-full rounded-sm bg-gray-300 flex items-center justify-center"
                    :style="{ width: `${ganttWidth(op.setup_time_minutes)}%`, minWidth: '4px' }"
                    :title="`Réglage: ${op.setup_time_minutes} min`"
                  >
                    <span v-if="ganttWidth(op.setup_time_minutes) > 12" class="text-xs text-gray-600 dark:text-surface-300 dark:text-surface-300 font-medium leading-none">
                      {{ op.setup_time_minutes }}'
                    </span>
                  </div>
                  <!-- Operation bar (blue) -->
                  <div
                    class="h-full rounded-sm bg-blue-50 dark:bg-surface-8000 flex items-center justify-center"
                    :style="{ width: `${ganttWidth(op.duration_minutes)}%`, minWidth: '4px' }"
                    :title="`Opération: ${op.duration_minutes} min`"
                  >
                    <span v-if="ganttWidth(op.duration_minutes) > 15" class="text-xs text-white font-medium leading-none">
                      {{ op.duration_minutes }}'
                    </span>
                  </div>
                </div>
              </div>

              <!-- Scale legend -->
              <div class="flex justify-between text-xs text-gray-400 mt-2 pt-2 border-t border-gray-100">
                <span>0</span>
                <span class="flex items-center gap-2">
                  <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-gray-300 inline-block" /> Réglage</span>
                  <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-sm bg-blue-50 dark:bg-surface-8000 inline-block" /> Opération</span>
                </span>
                <span>{{ totalMinutes }}'</span>
              </div>
            </div>
          </div>
        </div>
      </aside>
    </div>

    <!-- Add/Edit operation Dialog -->
    <Dialog
      v-model:visible="showAddDialog"
      :header="editingOp ? 'Modifier l\'opération' : 'Ajouter une opération'"
      modal
      :style="{ width: '560px' }"
      @hide="resetForm"
    >
      <div class="space-y-4 py-2">
        <div class="grid grid-cols-2 gap-3">
          <div class="col-span-2">
            <label class="text-sm font-medium text-gray-700 dark:text-surface-100 dark:text-surface-100 block mb-1">Nom *</label>
            <InputText v-model="form.name" class="w-full" placeholder="ex: Soudure TIG" />
          </div>
          <div>
            <label class="text-sm font-medium text-gray-700 dark:text-surface-100 dark:text-surface-100 block mb-1">Séquence</label>
            <InputNumber v-model="form.sequence" class="w-full" :min="1" :step="10" />
          </div>
          <div>
            <label class="text-sm font-medium text-gray-700 dark:text-surface-100 dark:text-surface-100 block mb-1">Poste de charge</label>
            <Dropdown
              v-model="form.work_center_id"
              :options="workcenters"
              option-label="name"
              option-value="id"
              class="w-full"
              placeholder="Sélectionner…"
              show-clear
            />
          </div>
          <div>
            <label class="text-sm font-medium text-gray-700 dark:text-surface-100 dark:text-surface-100 block mb-1">Durée réglage (min)</label>
            <InputNumber v-model="form.setup_time_minutes" class="w-full" :min="0" />
          </div>
          <div>
            <label class="text-sm font-medium text-gray-700 dark:text-surface-100 dark:text-surface-100 block mb-1">Durée opération (min) *</label>
            <InputNumber v-model="form.duration_minutes" class="w-full" :min="1" />
          </div>
          <div>
            <label class="text-sm font-medium text-gray-700 dark:text-surface-100 dark:text-surface-100 block mb-1">Coût/h (€)</label>
            <InputNumber v-model="form.cost_per_hour" class="w-full" :min="0" :max-fraction-digits="2" />
          </div>
        </div>

        <div>
          <label class="text-sm font-medium text-gray-700 dark:text-surface-100 dark:text-surface-100 block mb-1">Description</label>
          <Textarea v-model="form.description" class="w-full" rows="2" placeholder="Détails…" />
        </div>

        <!-- Resources section -->
        <div>
          <div class="flex items-center justify-between mb-2">
            <label class="text-sm font-semibold text-gray-700 dark:text-surface-100 dark:text-surface-100">Ressources requises</label>
            <button
              type="button"
              @click="addResource"
              class="text-xs text-blue-600 hover:text-blue-700 flex items-center gap-1"
            >
              <i class="pi pi-plus text-xs" /> Ajouter
            </button>
          </div>
          <div class="space-y-2">
            <div
              v-for="(res, ri) in form.resources"
              :key="ri"
              class="flex gap-2 items-center"
            >
              <Dropdown
                v-model="res.resource_type"
                :options="resourceTypes"
                option-label="label"
                option-value="value"
                class="w-28 shrink-0"
              />
              <InputText v-model="res.name" class="flex-1" placeholder="Nom" />
              <InputNumber v-model="res.quantity" class="w-16 shrink-0" :min="0.01" :max-fraction-digits="2" />
              <InputText v-model="res.unit" class="w-16 shrink-0" placeholder="unité" />
              <button type="button" @click="removeResource(ri)" class="text-gray-400 hover:text-red-500">
                <i class="pi pi-times text-xs" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2">
          <Button label="Annuler" severity="secondary" text @click="showAddDialog = false" />
          <Button
            :label="editingOp ? 'Mettre à jour' : 'Ajouter'"
            :loading="saving"
            :disabled="!form.name || !form.duration_minutes"
            @click="saveOperation"
          />
        </div>
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'
import { Button, Dialog, Dropdown, InputNumber, InputText, Textarea } from 'primevue'

// ── Props ──────────────────────────────────────────────────────────────────────
const props = defineProps({
  routing:    { type: Object, required: true },
  workcenters:{ type: Array, default: () => [] },
})

// ── State ──────────────────────────────────────────────────────────────────────
const operations   = ref(props.routing.operations ?? [])
const summary      = ref({
  total_lead_time_h: 0,
  estimated_cost: '0.00',
  operations_count: 0,
})
const showAddDialog = ref(false)
const saving        = ref(false)
const editingOp     = ref(null)
const dragIndex     = ref(null)
const dragOverIndex = ref(null)

const defaultForm = () => ({
  name:               '',
  sequence:           null,
  work_center_id:     null,
  setup_time_minutes: 0,
  duration_minutes:   60,
  cost_per_hour:      0,
  description:        '',
  resources:          [],
})

const form = ref(defaultForm())

const resourceTypes = [
  { label: 'Humain', value: 'human' },
  { label: 'Machine', value: 'machine' },
  { label: 'Outil', value: 'tool' },
]

// ── Computed ───────────────────────────────────────────────────────────────────
const totalMinutes = computed(() =>
  operations.value.reduce((acc, op) => acc + (op.duration_minutes ?? 0) + (op.setup_time_minutes ?? 0), 0)
)

// ── Helpers ────────────────────────────────────────────────────────────────────
function formatLeadTime(hours) {
  if (!hours) return '0h00'
  const h = Math.floor(hours)
  const m = Math.round((hours - h) * 60)
  return `${h}h${String(m).padStart(2, '0')}`
}

function formatCurrency(val) {
  return parseFloat(val || 0).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function ganttWidth(minutes) {
  if (!totalMinutes.value) return 0
  return Math.max(2, Math.round((minutes / totalMinutes.value) * 96))
}

function resourceTypeClass(type) {
  const map = {
    human:   'bg-blue-50 border-blue-200 text-blue-700',
    machine: 'bg-orange-50 border-orange-200 text-orange-700',
    tool:    'bg-gray-50 border-gray-200 text-gray-700',
  }
  return map[type] ?? 'bg-gray-50 border-gray-200 text-gray-600'
}

function resourceTypeIcon(type) {
  const map = { human: 'pi pi-user', machine: 'pi pi-cog', tool: 'pi pi-wrench' }
  return map[type] ?? 'pi pi-circle'
}

// ── Fetch summary ──────────────────────────────────────────────────────────────
async function fetchSummary() {
  try {
    const { data } = await axios.get(`/api/v1/manufacturing/routings/${props.routing.id}/summary`)
    summary.value = data
  } catch {}
}

fetchSummary()

// ── Dialog helpers ─────────────────────────────────────────────────────────────
function resetForm() {
  form.value   = defaultForm()
  editingOp.value = null
}

function editOp(op) {
  editingOp.value = op
  form.value = {
    name:               op.name,
    sequence:           op.sequence,
    work_center_id:     op.work_center_id,
    setup_time_minutes: op.setup_time_minutes,
    duration_minutes:   op.duration_minutes,
    cost_per_hour:      parseFloat(op.cost_per_hour),
    description:        op.description ?? '',
    resources:          (op.resources ?? []).map(r => ({ ...r })),
  }
  showAddDialog.value = true
}

function addResource() {
  form.value.resources.push({ resource_type: 'human', name: '', quantity: 1, unit: 'unit' })
}

function removeResource(idx) {
  form.value.resources.splice(idx, 1)
}

// ── CRUD operations ────────────────────────────────────────────────────────────
async function saveOperation() {
  if (!form.value.name || !form.value.duration_minutes) return
  saving.value = true
  try {
    const payload = { ...form.value }
    let updated

    if (editingOp.value) {
      const { data } = await axios.put(
        `/api/v1/manufacturing/routings/${props.routing.id}/operations/${editingOp.value.id}`,
        payload,
      )
      updated = data
      const idx = operations.value.findIndex(o => o.id === updated.id)
      if (idx !== -1) operations.value[idx] = updated
    } else {
      const { data } = await axios.post(
        `/api/v1/manufacturing/routings/${props.routing.id}/operations`,
        payload,
      )
      updated = data
      operations.value.push(updated)
      operations.value.sort((a, b) => a.sequence - b.sequence)
    }

    showAddDialog.value = false
    await fetchSummary()
  } catch (err) {
    console.error(err)
  } finally {
    saving.value = false
  }
}

async function deleteOp(op) {
  if (!confirm(`Supprimer l'opération "${op.name}" ?`)) return
  try {
    await axios.delete(`/api/v1/manufacturing/routings/${props.routing.id}/operations/${op.id}`)
    operations.value = operations.value.filter(o => o.id !== op.id)
    await fetchSummary()
  } catch (err) {
    console.error(err)
  }
}

// ── Drag-and-drop reorder ──────────────────────────────────────────────────────
function onDragStart(idx) {
  dragIndex.value = idx
}

function onDragOver(idx) {
  dragOverIndex.value = idx
}

async function onDrop(targetIdx) {
  const fromIdx = dragIndex.value
  if (fromIdx === null || fromIdx === targetIdx) {
    dragIndex.value = null
    dragOverIndex.value = null
    return
  }

  const items = [...operations.value]
  const [moved] = items.splice(fromIdx, 1)
  items.splice(targetIdx, 0, moved)
  operations.value = items
  dragIndex.value = null
  dragOverIndex.value = null

  const orderedIds = items.map(o => o.id)
  try {
    await axios.post(`/api/v1/manufacturing/routings/${props.routing.id}/operations/reorder`, { ordered_ids: orderedIds })
    // Update sequence numbers locally
    operations.value.forEach((op, i) => { op.sequence = (i + 1) * 10 })
    await fetchSummary()
  } catch (err) {
    console.error(err)
  }
}
</script>
