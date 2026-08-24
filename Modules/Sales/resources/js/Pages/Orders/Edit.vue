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

      <div v-if="!editable" class="bg-orange-50 dark:bg-orange-950/20 border border-orange-200 dark:border-orange-800 rounded-xl p-4 text-sm text-orange-700 dark:text-orange-300">
        Seules les commandes au statut « brouillon » peuvent être modifiées. Cette commande est « {{ order.status }} ».
      </div>

      <div v-else class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6 space-y-4">
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
              <option v-for="c in currencies" :key="c" :value="c">{{ c }}</option>
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

        <p class="text-xs text-surface-500">
          Les lignes de la commande ne sont pas modifiables une fois la commande créée — seules ces informations d'en-tête le sont.
        </p>

        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

        <div class="flex justify-end gap-2">
          <Link :href="`/sales/orders/${order.id}`" class="px-4 py-2 text-sm">Annuler</Link>
          <Button label="Enregistrer" :disabled="submitting" @click="submit" />
        </div>
      </div>
    </div>

    <div v-else class="text-center py-16 text-surface-400">Chargement…</div>
  </AppLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import Button from 'primevue/button'
import AppLayout from '@/Layouts/AppLayout.vue'

// Chantier 32.16 (Sales deep 14-layer audit) / Chantier 32 (volet B):
// SalesIndex.vue's "Edit" (pencil) button has always navigated to
// /sales/orders/{id}/edit, a route that never existed anywhere (confirmed
// via a real HTTP request returning 404 before this fix, found and fixed
// independently by two parallel audits). Built against the real
// PUT /api/v1/sales/orders/{id} contract (SalesController::updateOrder()
// — header fields only, no line-item editing, and draft-only, matching
// SalesOrder::isEditable()).

const props = defineProps<{ orderId: number }>()

// Chantier 32 (volet B) — MGA en tête (défaut), puis les 3 devises
// explicitement demandées par l'utilisateur.
const currencies = ['MGA', 'EUR', 'USD', 'CNY']

const order = ref<any>(null)
const error = ref('')
const submitting = ref(false)

const form = reactive({
  contact_id: null as number | null,
  account_id: null as number | null,
  currency: 'MGA',
  notes: '',
  expected_delivery_date: '',
})

// SalesOrder::isEditable() (status === 'draft') n'est pas exposée dans le
// JSON (pas dans $appends) — dérivé côté client depuis le champ `status`,
// déjà présent, plutôt que d'ajouter un accessor backend pour un seul
// booléen dérivé.
const editable = computed(() => order.value?.status === 'draft')

async function load() {
  const { data } = await axios.get(`/api/v1/sales/orders/${props.orderId}`)
  order.value = data
  form.contact_id = data.contact_id
  form.account_id = data.account_id
  form.currency = data.currency
  form.notes = data.notes ?? ''
  form.expected_delivery_date = data.expected_delivery_date?.slice(0, 10) || ''
}

async function submit() {
  submitting.value = true
  error.value = ''
  try {
    await axios.put(`/api/v1/sales/orders/${props.orderId}`, { ...form })
    router.visit(`/sales/orders/${props.orderId}`)
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Échec de la modification de la commande.'
  } finally {
    submitting.value = false
  }
}

onMounted(load)
</script>
