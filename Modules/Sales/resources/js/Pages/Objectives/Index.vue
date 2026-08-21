<template>
  <AppLayout>
    <Head title="Objectifs commerciaux" />

    <div class="max-w-6xl mx-auto space-y-6">
      <div>
        <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Objectifs commerciaux</h1>
        <p class="text-surface-500 text-sm mt-1">
          L'application propose 2 à 3 cibles de chiffre d'affaires à partir de l'historique réel — modifiables puis validables.
        </p>
      </div>

      <!-- Formulaire de proposition -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-4 space-y-4">
        <h2 class="text-sm font-semibold text-surface-700 dark:text-surface-200">Proposer un nouvel objectif</h2>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
          <div>
            <label class="block text-sm font-medium mb-1">Périmètre</label>
            <select v-model="form.scope" class="w-full rounded border border-surface-300 dark:border-surface-600 bg-transparent px-3 py-2 text-sm">
              <option value="global">Toute l'équipe</option>
              <option value="rep">Un commercial</option>
              <option value="client">Un client</option>
              <option value="category">Une catégorie de produits</option>
            </select>
          </div>
          <div v-if="form.scope !== 'global'">
            <label class="block text-sm font-medium mb-1">{{ scopeRefLabel }}</label>
            <input
              v-model.number="form.scope_ref_id"
              type="number"
              :placeholder="scopeRefPlaceholder"
              class="w-full rounded border border-surface-300 dark:border-surface-600 bg-transparent px-3 py-2 text-sm"
            />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Début période</label>
            <input v-model="form.period_start" type="date" class="w-full rounded border border-surface-300 dark:border-surface-600 bg-transparent px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Fin période</label>
            <input v-model="form.period_end" type="date" class="w-full rounded border border-surface-300 dark:border-surface-600 bg-transparent px-3 py-2 text-sm" />
          </div>
          <div class="flex items-end">
            <button
              class="w-full px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 disabled:opacity-50"
              :disabled="proposing"
              @click="propose"
            >
              {{ proposing ? 'Génération…' : 'Proposer' }}
            </button>
          </div>
        </div>
      </div>

      <p v-if="feedback" class="text-sm" :class="feedbackIsError ? 'text-red-600' : 'text-green-600'">{{ feedback }}</p>

      <!-- Groupes de propositions -->
      <div v-for="group in groupedObjectives" :key="group.key" class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 overflow-hidden">
        <div class="px-4 py-3 border-b border-surface-200 dark:border-surface-700 flex items-center justify-between">
          <div>
            <span class="font-semibold text-surface-900 dark:text-surface-50">{{ scopeLabel(group.scope, group.scope_ref_id) }}</span>
            <span class="text-surface-500 text-sm ml-2">{{ group.period_start }} → {{ group.period_end }}</span>
          </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 p-4">
          <div
            v-for="obj in group.items"
            :key="obj.id"
            class="border rounded-lg p-4 space-y-2"
            :class="{
              'border-green-400 bg-green-50 dark:bg-green-950/20': obj.status === 'validated',
              'border-surface-200 dark:border-surface-700': obj.status === 'proposed',
              'opacity-50': obj.status === 'rejected',
            }"
          >
            <div class="flex items-center justify-between">
              <span class="font-medium">{{ obj.proposal_label ?? '—' }}</span>
              <span
                class="px-2 py-0.5 rounded-full text-xs"
                :class="{
                  'bg-green-100 text-green-700': obj.status === 'validated',
                  'bg-blue-100 text-blue-700': obj.status === 'proposed',
                  'bg-surface-100 text-surface-500': obj.status === 'rejected',
                }"
              >
                {{ statusLabel(obj.status) }}
              </span>
            </div>

            <div v-if="obj.status === 'proposed'">
              <input
                v-model.number="obj.target_amount"
                type="number"
                class="w-full rounded border border-surface-300 dark:border-surface-600 bg-transparent px-2 py-1 text-sm font-semibold"
              />
            </div>
            <div v-else class="text-lg font-bold">{{ fmt(obj.target_amount) }} {{ obj.currency }}</div>

            <p class="text-xs text-surface-500">{{ obj.basis }}</p>
            <p v-if="obj.growth_rate_percent !== null" class="text-xs text-surface-400">Croissance visée : {{ obj.growth_rate_percent }}%</p>

            <div v-if="obj.status === 'proposed'" class="flex gap-2 pt-1">
              <button class="text-primary-600 hover:underline text-sm" @click="saveEdit(obj)">Enregistrer</button>
              <button class="text-green-600 hover:underline text-sm" @click="validateObjective(obj)">Valider</button>
              <button class="text-red-600 hover:underline text-sm" @click="destroy(obj, group)">Supprimer</button>
            </div>
          </div>
        </div>
      </div>

      <div v-if="!groupedObjectives.length" class="text-center py-8 text-surface-400">Aucun objectif proposé pour l'instant.</div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

const objectives = ref([])
const proposing = ref(false)
const feedback = ref('')
const feedbackIsError = ref(false)

const today = new Date()
const nextMonthStart = new Date(today.getFullYear(), today.getMonth() + 1, 1)
const nextMonthEnd = new Date(today.getFullYear(), today.getMonth() + 2, 0)

const form = reactive({
  scope: 'global',
  scope_ref_id: null,
  period_start: nextMonthStart.toISOString().slice(0, 10),
  period_end: nextMonthEnd.toISOString().slice(0, 10),
})

const scopeRefLabel = computed(() => ({
  rep: 'ID du commercial',
  client: 'ID du client (contact CRM)',
  category: 'ID de la catégorie de produits',
}[form.scope] ?? 'Référence'))

const scopeRefPlaceholder = computed(() => ({
  rep: 'ex. 3',
  client: 'ex. 12',
  category: 'ex. 5',
}[form.scope] ?? ''))

function fmt(n) {
  return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(n ?? 0)
}

function statusLabel(s) {
  return { proposed: 'Proposé', validated: 'Validé', rejected: 'Rejeté' }[s] ?? s
}

function scopeLabel(scope, scopeRefId) {
  const labels = { global: "Toute l'équipe commerciale", rep: 'Commercial', client: 'Client', category: 'Catégorie de produits' }
  const base = labels[scope] ?? scope
  return scopeRefId ? `${base} #${scopeRefId}` : base
}

const groupedObjectives = computed(() => {
  const groups = {}
  for (const obj of objectives.value) {
    const key = `${obj.scope}:${obj.scope_ref_id ?? 'null'}:${obj.period_start}:${obj.period_end}`
    if (!groups[key]) {
      groups[key] = { key, scope: obj.scope, scope_ref_id: obj.scope_ref_id, period_start: obj.period_start, period_end: obj.period_end, items: [] }
    }
    groups[key].items.push(obj)
  }
  return Object.values(groups).sort((a, b) => (a.period_start < b.period_start ? 1 : -1))
})

async function load() {
  const { data } = await axios.get('/api/v1/sales/objectives')
  objectives.value = data.data || []
}

async function propose() {
  proposing.value = true
  feedback.value = ''
  try {
    await axios.post('/api/v1/sales/objectives/propose', { ...form })
    feedback.value = '3 propositions générées.'
    feedbackIsError.value = false
    await load()
  } catch (err) {
    feedback.value = err.response?.data?.message || 'Échec de la génération des propositions.'
    feedbackIsError.value = true
  } finally {
    proposing.value = false
  }
}

async function saveEdit(obj) {
  try {
    await axios.put(`/api/v1/sales/objectives/${obj.id}`, { target_amount: obj.target_amount })
    feedback.value = 'Proposition mise à jour.'
    feedbackIsError.value = false
  } catch (err) {
    feedback.value = err.response?.data?.message || 'Échec de la mise à jour.'
    feedbackIsError.value = true
  }
}

async function validateObjective(obj) {
  try {
    await axios.post(`/api/v1/sales/objectives/${obj.id}/validate`, { target_amount: obj.target_amount })
    feedback.value = 'Objectif validé.'
    feedbackIsError.value = false
    await load()
  } catch (err) {
    feedback.value = err.response?.data?.message || 'Échec de la validation.'
    feedbackIsError.value = true
  }
}

async function destroy(obj, group) {
  await axios.delete(`/api/v1/sales/objectives/${obj.id}`)
  group.items = group.items.filter((o) => o.id !== obj.id)
}

onMounted(load)
</script>
