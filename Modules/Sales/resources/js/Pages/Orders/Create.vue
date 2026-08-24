<template>
  <AppLayout>
    <Head title="Nouvelle commande" />

    <div class="max-w-4xl mx-auto space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Nouvelle commande</h1>
          <p class="text-surface-500 text-sm mt-1">Chantier 32 (volet B) — devise MGA/EUR/USD/CNY</p>
        </div>
        <Link href="/sales" class="text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:text-surface-50">
          ← Retour aux commandes
        </Link>
      </div>

      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6 space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="text-sm text-surface-500 block mb-1">Contact (ID)</label>
            <input v-model.number="form.contact_id" type="number" class="w-full rounded border border-surface-300 dark:border-surface-600 bg-transparent px-2 py-1.5 text-sm" />
          </div>
          <div>
            <label class="text-sm text-surface-500 block mb-1">Compte (ID)</label>
            <input v-model.number="form.account_id" type="number" class="w-full rounded border border-surface-300 dark:border-surface-600 bg-transparent px-2 py-1.5 text-sm" />
          </div>
          <div>
            <label class="text-sm text-surface-500 block mb-1">Devise</label>
            <select v-model="form.currency" class="w-full rounded border border-surface-300 dark:border-surface-600 bg-transparent px-2 py-1.5 text-sm">
              <option v-for="c in currencies" :key="c" :value="c">{{ c }}</option>
            </select>
          </div>
          <div>
            <label class="text-sm text-surface-500 block mb-1">Livraison prévue</label>
            <input v-model="form.expected_delivery_date" type="date" class="w-full rounded border border-surface-300 dark:border-surface-600 bg-transparent px-2 py-1.5 text-sm" />
          </div>
        </div>

        <div>
          <label class="text-sm text-surface-500 block mb-1">Notes</label>
          <textarea v-model="form.notes" rows="2" class="w-full rounded border border-surface-300 dark:border-surface-600 bg-transparent px-2 py-1.5 text-sm" />
        </div>
      </div>

      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50">Lignes</h3>
          <Button label="Ajouter une ligne" icon="pi pi-plus" outlined size="small" @click="addLine" />
        </div>

        <table class="w-full text-sm">
          <thead class="border-b border-surface-200 dark:border-surface-700">
            <tr>
              <th class="px-2 py-2 text-left">Description</th>
              <th class="px-2 py-2 text-right">Qté</th>
              <th class="px-2 py-2 text-right">Prix unitaire</th>
              <th class="px-2 py-2"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(line, idx) in form.lines" :key="idx" class="border-b border-surface-100 dark:border-surface-700">
              <td class="px-2 py-2">
                <input v-model="line.description" type="text" class="w-full rounded border border-surface-300 dark:border-surface-600 bg-transparent px-2 py-1 text-sm" />
              </td>
              <td class="px-2 py-2">
                <input v-model.number="line.quantity" type="number" min="0.001" step="0.001" class="w-24 rounded border border-surface-300 dark:border-surface-600 bg-transparent px-2 py-1 text-sm text-right" />
              </td>
              <td class="px-2 py-2">
                <input v-model.number="line.unit_price" type="number" min="0" step="0.01" class="w-32 rounded border border-surface-300 dark:border-surface-600 bg-transparent px-2 py-1 text-sm text-right" />
              </td>
              <td class="px-2 py-2 text-right">
                <Button icon="pi pi-trash" text severity="danger" size="small" @click="removeLine(idx)" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p v-if="error" class="text-red-500 text-sm">{{ error }}</p>

      <div class="flex justify-end gap-3">
        <Link href="/sales" class="btn btn-secondary">Annuler</Link>
        <Button label="Créer la commande" :loading="saving" :disabled="form.lines.length === 0" @click="submit" />
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import Button from 'primevue/button'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

// Chantier 32 (volet B) — MGA en tête (défaut, cohérent avec le reste de
// l'app), puis les 3 devises explicitement demandées.
const currencies = ['MGA', 'EUR', 'USD', 'CNY']

const form = ref({
  contact_id: null as number | null,
  account_id: null as number | null,
  currency: 'MGA',
  expected_delivery_date: '',
  notes: '',
  lines: [{ description: '', quantity: 1, unit_price: 0 }],
})

const saving = ref(false)
const error = ref('')

const addLine = () => form.value.lines.push({ description: '', quantity: 1, unit_price: 0 })
const removeLine = (idx: number) => form.value.lines.splice(idx, 1)

const submit = async () => {
  saving.value = true
  error.value = ''
  try {
    const { data } = await axios.post('/api/v1/sales/orders', form.value)
    router.visit(`/sales/orders/${data.id}`)
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Échec de la création de la commande.'
  } finally {
    saving.value = false
  }
}
</script>
