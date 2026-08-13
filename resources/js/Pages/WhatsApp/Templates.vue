<template>
  <AppLayout title="WhatsApp — Templates HSM">
    <div class="space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Templates WhatsApp</h1>
          <p class="text-surface-500 dark:text-surface-400 mt-1">Messages pré-approuvés (HSM) pour les notifications sortantes</p>
        </div>
        <Button label="Nouveau template" icon="pi pi-plus" @click="createModal = true" />
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-3 gap-4">
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl p-4 shadow-sm border border-gray-100">
          <p class="text-sm text-surface-500 dark:text-surface-400">Approuvés</p>
          <p class="text-3xl font-bold text-green-700 dark:text-green-300 mt-1">{{ stats.approved }}</p>
        </div>
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl p-4 shadow-sm border border-gray-100">
          <p class="text-sm text-surface-500 dark:text-surface-400">En attente</p>
          <p class="text-3xl font-bold text-yellow-700 dark:text-yellow-300 mt-1">{{ stats.pending }}</p>
        </div>
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl p-4 shadow-sm border border-gray-100">
          <p class="text-sm text-surface-500 dark:text-surface-400">Rejetés</p>
          <p class="text-3xl font-bold text-red-700 dark:text-red-300 mt-1">{{ stats.rejected }}</p>
        </div>
      </div>

      <!-- Templates grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="tpl in templates"
          :key="tpl.id"
          class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl p-4 shadow-sm border border-gray-100 flex flex-col gap-3"
        >
          <div class="flex items-start justify-between">
            <div>
              <h3 class="font-semibold text-surface-900 dark:text-surface-50">{{ tpl.name }}</h3>
              <p class="text-xs text-surface-400 dark:text-surface-500 mt-0.5">{{ tpl.category }} · {{ tpl.language }}</p>
            </div>
            <Tag :value="tpl.status" :severity="tplSeverity(tpl.status)" />
          </div>
          <!-- Phone mockup preview -->
          <div class="bg-[#e5ddd5] rounded-lg p-3">
            <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg p-2 text-xs text-gray-800 dark:text-surface-100 leading-relaxed shadow-sm">
              {{ tpl.body }}
            </div>
          </div>
          <div class="flex gap-2 mt-auto">
            <Button icon="pi pi-copy" label="Copier" size="small" text @click="copyTemplate(tpl)" />
            <Button icon="pi pi-send" label="Envoyer test" size="small" outlined @click="testTemplate(tpl)" />
            <Button icon="pi pi-trash" size="small" text severity="danger" class="ml-auto" @click="deleteTemplate(tpl)" />
          </div>
        </div>
      </div>
    </div>

    <!-- Create Modal -->
    <Dialog v-model:visible="createModal" header="Nouveau template HSM" :style="{ width: '36rem' }" modal>
      <div class="space-y-4">
        <div>
          <label class="text-sm font-medium text-surface-700 dark:text-surface-300 block mb-1">Nom</label>
          <InputText v-model="form.name" class="w-full" placeholder="order_confirmation" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-sm font-medium text-surface-700 dark:text-surface-300 block mb-1">Catégorie</label>
            <Dropdown v-model="form.category" :options="categories" class="w-full" placeholder="Choisir" />
          </div>
          <div>
            <label class="text-sm font-medium text-surface-700 dark:text-surface-300 block mb-1">Langue</label>
            <Dropdown v-model="form.language" :options="languages" class="w-full" />
          </div>
        </div>
        <div>
          <label class="text-sm font-medium text-surface-700 dark:text-surface-300 block mb-1">Corps du message</label>
          <Textarea v-model="form.body" rows="4" class="w-full" placeholder="Bonjour {{1}}, votre commande {{2}} est confirmée..." />
          <p class="text-xs text-surface-400 dark:text-surface-500 mt-1">Utilisez {{1}}, {{2}}... pour les variables dynamiques</p>
        </div>
        <!-- Preview -->
        <div class="bg-[#e5ddd5] rounded-lg p-3">
          <p class="text-xs text-surface-500 dark:text-surface-400 mb-2">Aperçu :</p>
          <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg p-2 text-xs text-gray-800 dark:text-surface-100 shadow-sm">
            {{ form.body || 'Le message apparaîtra ici...' }}
          </div>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <Button label="Annuler" outlined @click="createModal = false" />
          <Button label="Soumettre pour approbation" severity="success" @click="submitTemplate" />
        </div>
      </div>
    </Dialog>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, Tag, Dialog, InputText, Textarea, Dropdown } from 'primevue'

const createModal = ref(false)
const form = ref({ name: '', category: '', language: 'fr', body: '' })
const categories = ['MARKETING', 'UTILITY', 'AUTHENTICATION']
const languages = ['fr', 'en', 'ar', 'pt', 'es']

const stats = ref({ approved: 12, pending: 3, rejected: 1 })

const templates = ref([
  { id: 1, name: 'order_confirmation', category: 'UTILITY', language: 'fr', status: 'APPROVED', body: 'Bonjour {{1}} ! Votre commande #{{2}} est confirmée. Montant : {{3}} €. Livraison prévue : {{4}}.' },
  { id: 2, name: 'appointment_reminder', category: 'UTILITY', language: 'fr', status: 'APPROVED', body: 'Rappel : Vous avez un rendez-vous le {{1}} à {{2}}. Répondez C pour confirmer ou A pour annuler.' },
  { id: 3, name: 'payment_receipt', category: 'UTILITY', language: 'fr', status: 'PENDING', body: 'Votre paiement de {{1}} € a bien été reçu. Référence : {{2}}. Merci pour votre confiance !' },
  { id: 4, name: 'welcome_message', category: 'MARKETING', language: 'fr', status: 'APPROVED', body: 'Bienvenue chez {{1}} ! 🎉 Profitez de {{2}}% de réduction sur votre première commande avec le code {{3}}.' },
])

const tplSeverity = (status) => ({ APPROVED: 'success', PENDING: 'warn', REJECTED: 'danger' }[status] || 'secondary')

function copyTemplate(tpl) {
  navigator.clipboard?.writeText(tpl.body)
}
function testTemplate(tpl) {}
function deleteTemplate(tpl) {
  templates.value = templates.value.filter(t => t.id !== tpl.id)
}
function submitTemplate() {
  templates.value.push({ id: Date.now(), ...form.value, status: 'PENDING' })
  stats.value.pending++
  createModal.value = false
  form.value = { name: '', category: '', language: 'fr', body: '' }
}
</script>
