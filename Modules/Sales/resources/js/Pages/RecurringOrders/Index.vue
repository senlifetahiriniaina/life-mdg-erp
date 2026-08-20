<template>
  <AppLayout>
    <Head title="Commandes récurrentes" />

    <div class="max-w-6xl mx-auto space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Commandes récurrentes</h1>
          <p class="text-surface-500 text-sm mt-1">
            Un client répétitif n'a pas besoin de ressaisir sa commande à chaque fois — un modèle génère automatiquement une vraie commande à échéance.
          </p>
        </div>
        <button
          class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700"
          @click="openCreateModal"
        >
          + Nouveau modèle
        </button>
      </div>

      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 overflow-hidden">
        <table class="w-full text-sm">
          <thead class="border-b border-surface-200 dark:border-surface-700">
            <tr>
              <th class="px-3 py-2 text-left">Référence</th>
              <th class="px-3 py-2 text-left">Nom</th>
              <th class="px-3 py-2 text-left">Périodicité</th>
              <th class="px-3 py-2 text-left">Prochaine échéance</th>
              <th class="px-3 py-2 text-left">Dernière génération</th>
              <th class="px-3 py-2 text-left">Statut</th>
              <th class="px-3 py-2 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in templates" :key="t.id" class="border-b border-surface-100 dark:border-surface-700">
              <td class="px-3 py-2">{{ t.reference }}</td>
              <td class="px-3 py-2">{{ t.name }}</td>
              <td class="px-3 py-2">{{ recurrenceLabel(t.recurrence) }}</td>
              <td class="px-3 py-2">{{ t.next_run_at }}</td>
              <td class="px-3 py-2">{{ t.last_run_at ?? '—' }}</td>
              <td class="px-3 py-2">
                <span
                  class="px-2 py-0.5 rounded-full text-xs"
                  :class="t.is_active ? 'bg-green-100 text-green-700' : 'bg-surface-100 text-surface-500'"
                >
                  {{ t.is_active ? 'Actif' : 'Inactif' }}
                </span>
              </td>
              <td class="px-3 py-2 text-right space-x-2">
                <button class="text-primary-600 hover:underline" :disabled="pending === t.id" @click="runNow(t)">
                  Générer maintenant
                </button>
                <button class="text-red-600 hover:underline" @click="destroy(t)">Supprimer</button>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="!templates.length" class="text-center py-8 text-surface-400">Aucun modèle de commande récurrente.</div>
      </div>

      <p v-if="feedback" class="text-sm" :class="feedbackIsError ? 'text-red-600' : 'text-green-600'">{{ feedback }}</p>
    </div>

    <!-- Create modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" @click.self="showModal = false">
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-6 w-full max-w-lg space-y-4">
        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50">Nouveau modèle de commande récurrente</h3>

        <div>
          <label class="block text-sm font-medium mb-1">Nom</label>
          <input v-model="form.name" type="text" class="w-full rounded border border-surface-300 dark:border-surface-600 bg-transparent px-3 py-2 text-sm" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium mb-1">Périodicité</label>
            <select v-model="form.recurrence" class="w-full rounded border border-surface-300 dark:border-surface-600 bg-transparent px-3 py-2 text-sm">
              <option value="weekly">Hebdomadaire</option>
              <option value="monthly">Mensuelle</option>
              <option value="quarterly">Trimestrielle</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Première échéance</label>
            <input v-model="form.next_run_at" type="date" class="w-full rounded border border-surface-300 dark:border-surface-600 bg-transparent px-3 py-2 text-sm" />
          </div>
        </div>

        <div>
          <div class="flex items-center justify-between mb-2">
            <label class="text-sm font-medium">Lignes</label>
            <button class="text-primary-600 text-sm hover:underline" @click="addLine">+ Ajouter une ligne</button>
          </div>
          <div v-for="(line, i) in form.lines" :key="i" class="grid grid-cols-12 gap-2 mb-2">
            <input v-model="line.description" type="text" placeholder="Description" class="col-span-6 rounded border border-surface-300 dark:border-surface-600 bg-transparent px-2 py-1 text-sm" />
            <input v-model.number="line.quantity" type="number" placeholder="Qté" min="0.01" step="0.01" class="col-span-2 rounded border border-surface-300 dark:border-surface-600 bg-transparent px-2 py-1 text-sm" />
            <input v-model.number="line.unit_price" type="number" placeholder="P.U." min="0" step="0.01" class="col-span-3 rounded border border-surface-300 dark:border-surface-600 bg-transparent px-2 py-1 text-sm" />
            <button class="col-span-1 text-red-600" @click="form.lines.splice(i, 1)">✕</button>
          </div>
        </div>

        <div class="flex justify-end gap-2">
          <button class="px-4 py-2 text-sm" @click="showModal = false">Annuler</button>
          <button class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700" @click="submit">Créer</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

const templates = ref([])
const showModal = ref(false)
const pending = ref(null)
const feedback = ref('')
const feedbackIsError = ref(false)

const form = reactive({
  name: '',
  recurrence: 'monthly',
  next_run_at: new Date().toISOString().slice(0, 10),
  lines: [{ description: '', quantity: 1, unit_price: 0 }],
})

function recurrenceLabel(r) {
  return { weekly: 'Hebdomadaire', monthly: 'Mensuelle', quarterly: 'Trimestrielle' }[r] ?? r
}

async function load() {
  const { data } = await axios.get('/api/v1/sales/recurring-order-templates', { params: { per_page: 50 } })
  templates.value = data.data || []
}

function openCreateModal() {
  form.name = ''
  form.recurrence = 'monthly'
  form.next_run_at = new Date().toISOString().slice(0, 10)
  form.lines = [{ description: '', quantity: 1, unit_price: 0 }]
  showModal.value = true
}

function addLine() {
  form.lines.push({ description: '', quantity: 1, unit_price: 0 })
}

async function submit() {
  try {
    await axios.post('/api/v1/sales/recurring-order-templates', { ...form })
    showModal.value = false
    feedback.value = 'Modèle créé.'
    feedbackIsError.value = false
    await load()
  } catch (err) {
    feedback.value = err.response?.data?.message || 'Échec de la création.'
    feedbackIsError.value = true
  }
}

async function runNow(template) {
  pending.value = template.id
  try {
    const { data } = await axios.post(`/api/v1/sales/recurring-order-templates/${template.id}/run`)
    feedback.value = `Commande ${data.data.reference} générée.`
    feedbackIsError.value = false
    await load()
  } catch (err) {
    feedback.value = err.response?.data?.message || 'Échec de la génération.'
    feedbackIsError.value = true
  } finally {
    pending.value = null
  }
}

async function destroy(template) {
  await axios.delete(`/api/v1/sales/recurring-order-templates/${template.id}`)
  await load()
}

onMounted(load)
</script>
