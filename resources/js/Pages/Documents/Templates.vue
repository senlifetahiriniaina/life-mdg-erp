<template>
  <AppLayout title="Templates de documents">
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50 text-surface-900 dark:text-surface-50">Templates de documents</h1>
          <p class="text-surface-500 dark:text-surface-400 mt-1">Modèles réutilisables pour devis, contrats et factures</p>
        </div>
        <Button label="Nouveau template" icon="pi pi-plus" @click="createModal = true" />
      </div>

      <!-- Category tabs -->
      <div class="flex gap-2 flex-wrap">
        <Button
          v-for="cat in categories"
          :key="cat"
          :label="cat"
          size="small"
          :outlined="activeCategory !== cat"
          @click="activeCategory = cat"
        />
      </div>

      <!-- Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="tpl in filteredTemplates"
          :key="tpl.id"
          class="bg-surface-0 dark:bg-surface-800 rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow group"
        >
          <!-- Preview thumbnail -->
          <div class="h-40 bg-gradient-to-br from-gray-50 to-gray-100 flex items-center justify-center border-b border-gray-100 relative">
            <div class="w-28 h-36 bg-surface-0 dark:bg-surface-800 shadow-lg rounded p-3 text-surface-400 dark:text-surface-500">
              <div class="h-2 bg-gray-200 rounded mb-2 w-3/4" />
              <div class="h-1.5 bg-surface-100 dark:bg-surface-800 rounded mb-1 w-full" />
              <div class="h-1.5 bg-surface-100 dark:bg-surface-800 rounded mb-1 w-5/6" />
              <div class="h-1.5 bg-surface-100 dark:bg-surface-800 rounded mb-3 w-4/6" />
              <div class="h-6 bg-primary-50 dark:bg-primary-900/20 rounded mb-2" />
              <div class="space-y-1">
                <div class="h-1 bg-surface-100 dark:bg-surface-800 rounded w-full" />
                <div class="h-1 bg-surface-100 dark:bg-surface-800 rounded w-full" />
                <div class="h-1 bg-surface-100 dark:bg-surface-800 rounded w-2/3" />
              </div>
            </div>
            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors flex items-center justify-center opacity-0 group-hover:opacity-100 gap-2">
              <Button icon="pi pi-eye" rounded size="small" severity="secondary" @click="preview(tpl)" />
              <Button icon="pi pi-copy" rounded size="small" @click="duplicate(tpl)" />
            </div>
          </div>
          <div class="p-4">
            <div class="flex items-start justify-between mb-1">
              <h3 class="font-semibold text-surface-900 dark:text-surface-50 text-sm">{{ tpl.name }}</h3>
              <Tag :value="tpl.category" severity="secondary" class="text-xs" />
            </div>
            <p class="text-xs text-surface-500 dark:text-surface-400 mb-3">{{ tpl.description }}</p>
            <div class="flex gap-2">
              <Button label="Utiliser" size="small" class="flex-1" @click="use(tpl)" />
              <Button icon="pi pi-pencil" size="small" outlined @click="edit(tpl)" />
              <Button icon="pi pi-trash" size="small" text severity="danger" @click="remove(tpl)" />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create Modal -->
    <Dialog v-model:visible="createModal" header="Nouveau template" :style="{ width: '32rem' }" modal>
      <div class="space-y-4">
        <div><label class="text-sm font-medium text-surface-900 dark:text-surface-50 text-surface-700 dark:text-surface-300 block mb-1">Nom</label><InputText v-model="form.name" class="w-full" /></div>
        <div>
          <label class="text-sm font-medium text-surface-900 dark:text-surface-50 text-surface-700 dark:text-surface-300 block mb-1">Catégorie</label>
          <Dropdown v-model="form.category" :options="['Contrat', 'Devis', 'Facture', 'NDA', 'Bon de commande', 'Autre']" class="w-full" />
        </div>
        <div><label class="text-sm font-medium text-surface-900 dark:text-surface-50 text-surface-700 dark:text-surface-300 block mb-1">Description</label><InputText v-model="form.description" class="w-full" /></div>
        <div class="flex justify-end gap-2 pt-2">
          <Button label="Annuler" outlined @click="createModal = false" />
          <Button label="Créer et éditer" severity="success" @click="createAndEdit" />
        </div>
      </div>
    </Dialog>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, Tag, Dialog, InputText, Dropdown } from 'primevue'
import axios from 'axios'

const createModal = ref(false)
const activeCategory = ref('Tous')
const form = ref({ name: '', category: 'Contrat', description: '' })
const categories = ['Tous', 'Contrat', 'Devis', 'Facture', 'NDA', 'Bon de commande']

const templates = ref([
  { id: 1, name: 'Contrat de prestation standard', category: 'Contrat', description: 'Contrat de services avec clauses standards', usage: 45 },
  { id: 2, name: 'Devis commercial', category: 'Devis', description: 'Modèle de devis avec tableau produits/services', usage: 128 },
  { id: 3, name: 'Facture proforma', category: 'Facture', description: 'Facture avant livraison avec mentions légales', usage: 67 },
  { id: 4, name: 'NDA bilatéral', category: 'NDA', description: 'Accord de confidentialité mutuel', usage: 23 },
  { id: 5, name: 'Bon de commande fournisseur', category: 'Bon de commande', description: 'PO standardisé pour achats fournisseurs', usage: 89 },
  { id: 6, name: 'Contrat de travail CDI', category: 'Contrat', description: 'Contrat de travail à durée indéterminée', usage: 12 },
])

const filteredTemplates = computed(() => {
  if (activeCategory.value === 'Tous') return templates.value
  return templates.value.filter(t => t.category === activeCategory.value)
})

function preview(tpl) { router.visit(`/documents/templates/${tpl.id}/preview`) }
function edit(tpl) { router.visit(`/documents/templates/${tpl.id}/edit`) }
function use(tpl) { router.visit(`/documents/create?template=${tpl.id}`) }
function duplicate(tpl) {
  axios.post(`/api/v1/documents/templates/${tpl.id}/duplicate`)
    .then(r => templates.value.push(r.data?.data ?? { ...tpl, id: Date.now(), name: tpl.name + ' (copie)' }))
    .catch(() => { templates.value.push({ ...tpl, id: Date.now(), name: tpl.name + ' (copie)' }) })
}
function remove(tpl) {
  templates.value = templates.value.filter(t => t.id !== tpl.id)
  axios.delete(`/api/v1/documents/templates/${tpl.id}`).catch(() => {})
}
function createAndEdit() {
  axios.post('/api/v1/documents/templates', form.value)
    .then(r => router.visit(`/documents/templates/${r.data?.data?.id ?? 'new'}/edit`))
    .catch(() => { createModal.value = false })
}
</script>
