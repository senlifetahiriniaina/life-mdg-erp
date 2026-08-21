<template>
  <AppLayout>
    <Head title="Nouvelle commande" />

    <div class="max-w-4xl mx-auto space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Nouvelle commande</h1>
        <Link href="/sales" class="text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:text-surface-50">
          ← Retour aux commandes
        </Link>
      </div>

      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">ID du contact (CRM)</label>
            <input v-model.number="form.contact_id" type="number" min="1"
                   class="w-full rounded border border-surface-300 dark:border-surface-600 bg-transparent px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">ID du compte (CRM)</label>
            <input v-model.number="form.account_id" type="number" min="1"
                   class="w-full rounded border border-surface-300 dark:border-surface-600 bg-transparent px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Devise</label>
            <select v-model="form.currency" class="w-full rounded border border-surface-300 dark:border-surface-600 bg-transparent px-3 py-2 text-sm">
              <option value="MGA">MGA</option>
              <option value="XOF">XOF</option>
              <option value="EUR">EUR</option>
              <option value="USD">USD</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Livraison prévue</label>
            <input v-model="form.expected_delivery_date" type="date"
                   class="w-full rounded border border-surface-300 dark:border-surface-600 bg-transparent px-3 py-2 text-sm" />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Notes</label>
          <textarea v-model="form.notes" rows="2"
                    class="w-full rounded border border-surface-300 dark:border-surface-600 bg-transparent px-3 py-2 text-sm"></textarea>
        </div>

        <div>
          <div class="flex items-center justify-between mb-2">
            <label class="text-sm font-medium">Lignes</label>
            <button class="text-primary-600 text-sm hover:underline" @click="addLine">+ Ajouter une ligne</button>
          </div>
          <div v-for="(line, i) in form.lines" :key="i" class="grid grid-cols-12 gap-2 mb-2">
            <input v-model="line.description" type="text" placeholder="Description"
                   class="col-span-5 rounded border border-surface-300 dark:border-surface-600 bg-transparent px-2 py-1 text-sm" />
            <input v-model.number="line.quantity" type="number" placeholder="Qté" min="0.001" step="0.001"
                   class="col-span-2 rounded border border-surface-300 dark:border-surface-600 bg-transparent px-2 py-1 text-sm" />
            <input v-model.number="line.unit_price" type="number" placeholder="P.U." min="0" step="0.01"
                   class="col-span-2 rounded border border-surface-300 dark:border-surface-600 bg-transparent px-2 py-1 text-sm" />
            <input v-model.number="line.discount_percent" type="number" placeholder="Remise %" min="0" max="100" step="0.01"
                   class="col-span-2 rounded border border-surface-300 dark:border-surface-600 bg-transparent px-2 py-1 text-sm" />
            <button class="col-span-1 text-red-600" @click="form.lines.splice(i, 1)">✕</button>
          </div>
        </div>

        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

        <div class="flex justify-end gap-2">
          <Link href="/sales" class="px-4 py-2 text-sm">Annuler</Link>
          <Button label="Créer la commande" :disabled="submitting" @click="submit" />
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import Button from 'primevue/button'
import AppLayout from '@/Layouts/AppLayout.vue'

// Chantier 32.16 (Sales deep 14-layer audit): SalesIndex.vue's
// "Nouvelle commande" button has always navigated to /sales/orders/create,
// a route that never existed anywhere (a real, previously-undocumented
// dead-link bug — confirmed via a real HTTP request returning 404 before
// this fix). Built for real against the actual POST /api/v1/sales/orders
// contract (StoreOrderRequest-equivalent validation in SalesController::
// storeOrder()).

const error = ref('')
const submitting = ref(false)

const form = reactive({
  contact_id: null as number | null,
  account_id: null as number | null,
  currency: 'MGA',
  notes: '',
  expected_delivery_date: '',
  lines: [{ description: '', quantity: 1, unit_price: 0, discount_percent: 0 }],
})

function addLine() {
  form.lines.push({ description: '', quantity: 1, unit_price: 0, discount_percent: 0 })
}

async function submit() {
  submitting.value = true
  error.value = ''
  try {
    const { data } = await axios.post('/api/v1/sales/orders', { ...form })
    router.visit(`/sales/orders/${data.id}`)
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Échec de la création de la commande.'
  } finally {
    submitting.value = false
  }
}
</script>
