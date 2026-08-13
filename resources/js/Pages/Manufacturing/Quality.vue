<template>
  <AppLayout title="Contrôle qualité">
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Contrôle qualité</h1>
          <p class="text-surface-500 dark:text-surface-400 mt-1">Inspections, non-conformités et certifications</p>
        </div>
        <Button label="Nouvelle inspection" icon="pi pi-plus" @click="createModal = true" />
      </div>

      <!-- KPIs -->
      <div class="grid grid-cols-4 gap-4">
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl p-4 shadow-sm border border-gray-100">
          <p class="text-sm text-surface-500 dark:text-surface-400">Taux de conformité</p>
          <p class="text-3xl font-bold text-green-700 dark:text-green-300 mt-1">97.3%</p>
          <p class="text-xs text-green-500 mt-1">↑ +0.5% ce mois</p>
        </div>
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl p-4 shadow-sm border border-gray-100">
          <p class="text-sm text-surface-500 dark:text-surface-400">Non-conformités ouvertes</p>
          <p class="text-3xl font-bold text-red-700 dark:text-red-300 mt-1">{{ ncOpen }}</p>
        </div>
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl p-4 shadow-sm border border-gray-100">
          <p class="text-sm text-surface-500 dark:text-surface-400">Inspections ce mois</p>
          <p class="text-3xl font-bold text-primary-700 dark:text-primary-300 mt-1">142</p>
        </div>
        <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl p-4 shadow-sm border border-gray-100">
          <p class="text-sm text-surface-500 dark:text-surface-400">Temps moyen résolution NC</p>
          <p class="text-3xl font-bold text-yellow-700 dark:text-yellow-300 mt-1">3.2j</p>
        </div>
      </div>

      <!-- Tabs -->
      <TabView>
        <TabPanel header="Inspections">
          <DataTable :value="inspections" stripedRows paginator :rows="10" class="text-sm mt-2">
            <Column field="reference" header="Référence" sortable />
            <Column field="product_name" header="Produit" sortable />
            <Column field="work_order" header="OF" />
            <Column field="inspector" header="Inspecteur" />
            <Column field="type" header="Type">
              <template #body="{ data }"><Tag :value="data.type" severity="secondary" /></template>
            </Column>
            <Column field="result" header="Résultat">
              <template #body="{ data }">
                <Tag :value="data.result" :severity="data.result === 'conforme' ? 'success' : 'danger'" />
              </template>
            </Column>
            <Column field="date" header="Date" sortable />
            <Column header="">
              <template #body="{ data }">
                <Button icon="pi pi-eye" text size="small" @click="selected = data; detailModal = true" />
              </template>
            </Column>
          </DataTable>
        </TabPanel>

        <TabPanel header="Non-conformités">
          <DataTable :value="nonConformities" stripedRows paginator :rows="10" class="text-sm mt-2">
            <Column field="reference" header="Référence" />
            <Column field="product_name" header="Produit" />
            <Column field="severity" header="Sévérité">
              <template #body="{ data }">
                <Tag :value="data.severity" :severity="ncSeverity(data.severity)" />
              </template>
            </Column>
            <Column field="description" header="Description" />
            <Column field="status" header="Statut">
              <template #body="{ data }">
                <Tag :value="data.status" :severity="ncStatusSeverity(data.status)" />
              </template>
            </Column>
            <Column field="responsible" header="Responsable" />
            <Column field="due_date" header="Échéance" />
            <Column header="">
              <template #body="{ data }">
                <Button v-if="data.status === 'open'" label="Résoudre" size="small" severity="success" @click="resolveNC(data)" />
              </template>
            </Column>
          </DataTable>
        </TabPanel>
      </TabView>
    </div>

    <!-- Create inspection modal -->
    <Dialog v-model:visible="createModal" header="Nouvelle inspection" :style="{ width: '36rem' }" modal>
      <div class="space-y-4">
        <div class="grid grid-cols-2 gap-3">
          <div><label class="text-sm font-medium text-surface-700 dark:text-surface-300 block mb-1">Produit</label>
            <InputText v-model="form.product_name" class="w-full" /></div>
          <div><label class="text-sm font-medium text-surface-700 dark:text-surface-300 block mb-1">OF lié</label>
            <InputText v-model="form.work_order" class="w-full" placeholder="OF-XXX" /></div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="text-sm font-medium text-surface-700 dark:text-surface-300 block mb-1">Type</label>
            <Dropdown v-model="form.type" :options="['entrant', 'en-cours', 'final']" class="w-full" /></div>
          <div><label class="text-sm font-medium text-surface-700 dark:text-surface-300 block mb-1">Résultat</label>
            <Dropdown v-model="form.result" :options="['conforme', 'non-conforme']" class="w-full" /></div>
        </div>
        <div><label class="text-sm font-medium text-surface-700 dark:text-surface-300 block mb-1">Observations</label>
          <Textarea v-model="form.notes" rows="3" class="w-full" /></div>
        <div class="flex justify-end gap-2 pt-2">
          <Button label="Annuler" outlined @click="createModal = false" />
          <Button label="Enregistrer" severity="success" @click="save" />
        </div>
      </div>
    </Dialog>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, Tag, Dialog, InputText, Dropdown, Textarea, DataTable, Column, TabView, TabPanel } from 'primevue'
import axios from 'axios'

const createModal = ref(false)
const detailModal = ref(false)
const selected = ref(null)
const form = ref({ product_name: '', work_order: '', type: 'final', result: 'conforme', notes: '' })

const nonConformities = ref([
  { id: 1, reference: 'NC-2026-001', product_name: 'Pièce A-234', severity: 'majeure', description: 'Dimension hors tolérance', status: 'open', responsible: 'Jean D.', due_date: '10/05/2026' },
  { id: 2, reference: 'NC-2026-002', product_name: 'Produit B-456', severity: 'mineure', description: 'Finition surface insuffisante', status: 'in_progress', responsible: 'Marie L.', due_date: '08/05/2026' },
  { id: 3, reference: 'NC-2025-045', product_name: 'Composant C-789', severity: 'critique', description: 'Matière première non conforme', status: 'resolved', responsible: 'Pierre M.', due_date: '01/05/2026' },
])

const ncOpen = computed(() => nonConformities.value.filter(nc => nc.status === 'open' || nc.status === 'in_progress').length)

const inspections = ref([
  { id: 1, reference: 'INSP-2026-142', product_name: 'Pièce A-234', work_order: 'OF-142', inspector: 'Jean D.', type: 'final', result: 'conforme', date: '07/05/2026' },
  { id: 2, reference: 'INSP-2026-141', product_name: 'Produit B-456', work_order: 'OF-141', inspector: 'Marie L.', type: 'en-cours', result: 'non-conforme', date: '06/05/2026' },
  { id: 3, reference: 'INSP-2026-140', product_name: 'Composant C-789', work_order: 'OF-140', inspector: 'Pierre M.', type: 'entrant', result: 'conforme', date: '05/05/2026' },
])

const ncSeverity = (s) => ({ critique: 'danger', majeure: 'warn', mineure: 'info' }[s] || 'secondary')
const ncStatusSeverity = (s) => ({ open: 'danger', in_progress: 'warn', resolved: 'success' }[s] || 'secondary')

function resolveNC(nc) {
  nc.status = 'resolved'
  axios.patch(`/api/v1/manufacturing/non-conformities/${nc.id}`, { status: 'resolved' }).catch(() => {})
}

function save() {
  inspections.value.unshift({ id: Date.now(), reference: `INSP-2026-${inspections.value.length + 1}`, ...form.value, inspector: 'Current User', date: new Date().toLocaleDateString('fr') })
  createModal.value = false
}
</script>
