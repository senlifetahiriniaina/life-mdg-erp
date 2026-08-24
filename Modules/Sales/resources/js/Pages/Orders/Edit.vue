<template>
  <AppLayout>
    <Head title="Modifier la commande" />

    <div class="max-w-4xl mx-auto space-y-6" v-if="order">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Modifier {{ order.reference }}</h1>
          <p class="text-surface-500 text-sm mt-1">Seules les commandes en brouillon sont modifiables — les lignes ne le sont pas via cet écran</p>
        </div>
        <Link :href="`/sales/orders/${order.id}`" class="text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:text-surface-50">
          ← Retour à la commande
        </Link>
      </div>

      <div v-if="!isEditable" class="bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-xl p-4 text-sm text-orange-700 dark:text-orange-300">
        Cette commande n'est plus en brouillon et ne peut plus être modifiée.
      </div>

      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6 space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="text-sm text-surface-500 block mb-1">Contact (ID)</label>
            <input v-model.number="form.contact_id" type="number" :disabled="!isEditable" class="w-full rounded border border-surface-300 dark:border-surface-600 bg-transparent px-2 py-1.5 text-sm" />
          </div>
          <div>
            <label class="text-sm text-surface-500 block mb-1">Compte (ID)</label>
            <input v-model.number="form.account_id" type="number" :disabled="!isEditable" class="w-full rounded border border-surface-300 dark:border-surface-600 bg-transparent px-2 py-1.5 text-sm" />
          </div>
          <div>
            <label class="text-sm text-surface-500 block mb-1">Devise</label>
            <select v-model="form.currency" :disabled="!isEditable" class="w-full rounded border border-surface-300 dark:border-surface-600 bg-transparent px-2 py-1.5 text-sm">
              <option v-for="c in currencies" :key="c" :value="c">{{ c }}</option>
            </select>
          </div>
          <div>
            <label class="text-sm text-surface-500 block mb-1">Livraison prévue</label>
            <input v-model="form.expected_delivery_date" type="date" :disabled="!isEditable" class="w-full rounded border border-surface-300 dark:border-surface-600 bg-transparent px-2 py-1.5 text-sm" />
          </div>
        </div>

        <div>
          <label class="text-sm text-surface-500 block mb-1">Notes</label>
          <textarea v-model="form.notes" rows="2" :disabled="!isEditable" class="w-full rounded border border-surface-300 dark:border-surface-600 bg-transparent px-2 py-1.5 text-sm" />
        </div>
      </div>

      <p v-if="error" class="text-red-500 text-sm">{{ error }}</p>

      <div class="flex justify-end gap-3">
        <Link :href="`/sales/orders/${order.id}`" class="btn btn-secondary">Annuler</Link>
        <Button label="Enregistrer" :loading="saving" :disabled="!isEditable" @click="submit" />
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import Button from 'primevue/button'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

const props = defineProps<{ orderId: number }>()

const currencies = ['MGA', 'EUR', 'USD', 'CNY']

const order = ref<any>(null)
const form = ref({
  contact_id: null as number | null,
  account_id: null as number | null,
  currency: 'MGA',
  expected_delivery_date: '',
  notes: '',
})
const saving = ref(false)
const error = ref('')
// Chantier 32 (volet B) — SalesOrder::isEditable() (status === 'draft')
// n'est pas exposée dans le JSON (pas dans $appends) — dérivé côté client
// depuis le champ `status`, déjà présent, plutôt que d'ajouter un accessor
// backend pour un seul booléen dérivé.
const isEditable = computed(() => order.value?.status === 'draft')

const load = async () => {
  const { data } = await axios.get(`/api/v1/sales/orders/${props.orderId}`)
  order.value = data
  form.value = {
    contact_id: data.contact_id,
    account_id: data.account_id,
    currency: data.currency,
    expected_delivery_date: data.expected_delivery_date?.slice(0, 10) || '',
    notes: data.notes,
  }
}

const submit = async () => {
  saving.value = true
  error.value = ''
  try {
    await axios.put(`/api/v1/sales/orders/${props.orderId}`, form.value)
    router.visit(`/sales/orders/${props.orderId}`)
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Échec de la mise à jour.'
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>
