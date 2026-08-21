<template>
  <AppLayout>
    <Head title="Déduplication Contacts & Comptes" />

    <div class="space-y-6">
      <!-- Page header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">
            Déduplication Contacts & Comptes
          </h1>
          <p class="text-surface-500 text-sm mt-1">
            Détectez et fusionnez automatiquement les doublons dans votre CRM
          </p>
        </div>
        <Button
          icon="pi pi-search"
          label="Détecter les doublons"
          @click="runDetection"
          :loading="detecting"
        />
      </div>

      <!-- KPI Stats -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-5 border border-surface-200 dark:border-surface-700 shadow-sm">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-sm text-surface-500 dark:text-surface-400 font-medium">Doublons détectés</p>
              <p class="text-3xl font-bold text-orange-600 mt-1">{{ stats.detected }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-orange-50 dark:bg-orange-900/30 flex items-center justify-center">
              <i class="pi pi-copy text-orange-600 dark:text-orange-400 text-lg" />
            </div>
          </div>
        </div>

        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-5 border border-surface-200 dark:border-surface-700 shadow-sm">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-sm text-surface-500 dark:text-surface-400 font-medium">Fusions ce mois</p>
              <p class="text-3xl font-bold text-surface-900 dark:text-surface-50 mt-1">{{ stats.mergedThisMonth }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-green-50 dark:bg-green-900/30 flex items-center justify-center">
              <i class="pi pi-link text-green-600 dark:text-green-400 text-lg" />
            </div>
          </div>
        </div>

        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-5 border border-surface-200 dark:border-surface-700 shadow-sm">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-sm text-surface-500 dark:text-surface-400 font-medium">Contacts nettoyés</p>
              <p class="text-3xl font-bold text-surface-900 dark:text-surface-50 mt-1">{{ stats.cleaned }}</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center">
              <i class="pi pi-check-circle text-blue-600 dark:text-blue-400 text-lg" />
            </div>
          </div>
        </div>

        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-5 border border-surface-200 dark:border-surface-700 shadow-sm">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-sm text-surface-500 dark:text-surface-400 font-medium">Gain BD</p>
              <p class="text-3xl font-bold text-green-600 mt-1">{{ stats.dbGain }}%</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-purple-50 dark:bg-purple-900/30 flex items-center justify-center">
              <i class="pi pi-database text-purple-600 dark:text-purple-400 text-lg" />
            </div>
          </div>
        </div>
      </div>

      <!-- Duplicate Pairs Table -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-surface-200 dark:border-surface-700">
          <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-50">
            Paires de doublons en attente
          </h2>
        </div>

        <DataTable
          :value="duplicatePairs"
          :loading="detecting"
          striped-rows
          class="p-datatable-sm"
        >
          <Column header="Contact A" style="width: 22%">
            <template #body="{ data }">
              <div>
                <p class="font-medium text-surface-900 dark:text-surface-50">{{ data.contactA.name }}</p>
                <p class="text-xs text-surface-400">{{ data.contactA.email }}</p>
              </div>
            </template>
          </Column>

          <Column header="Contact B" style="width: 22%">
            <template #body="{ data }">
              <div>
                <p class="font-medium text-surface-900 dark:text-surface-50">{{ data.contactB.name }}</p>
                <p class="text-xs text-surface-400">{{ data.contactB.email }}</p>
              </div>
            </template>
          </Column>

          <Column field="similarity" header="Score similitude" sortable style="width: 14%">
            <template #body="{ data }">
              <div class="flex items-center gap-2">
                <span class="font-bold text-sm">{{ data.similarity }}%</span>
                <div class="flex-1 bg-surface-200 dark:bg-surface-600 rounded-full h-2 w-12">
                  <div
                    class="h-2 rounded-full bg-orange-500"
                    :style="{ width: data.similarity + '%' }"
                  />
                </div>
              </div>
            </template>
          </Column>

          <Column field="reason" header="Raison" style="width: 18%">
            <template #body="{ data }">
              <div class="flex flex-wrap gap-1">
                <Tag
                  v-for="reason in data.reasons"
                  :key="reason"
                  :value="reason"
                  severity="warn"
                  class="text-xs"
                />
              </div>
            </template>
          </Column>

          <Column header="Actions" style="width: 24%">
            <template #body="{ data }">
              <div class="flex gap-2">
                <Button
                  icon="pi pi-link"
                  label="Fusionner"
                  size="small"
                  severity="warning"
                  @click="openMergeDialog(data)"
                />
                <Button
                  icon="pi pi-times"
                  label="Ignorer"
                  size="small"
                  severity="secondary"
                  outlined
                  @click="ignorePair(data)"
                />
              </div>
            </template>
          </Column>

          <template #empty>
            <div class="text-center py-12 text-surface-400">
              Aucun doublon détecté. Cliquez sur "Détecter les doublons" pour lancer l'analyse.
            </div>
          </template>
        </DataTable>
      </div>
    </div>

    <!-- Merge Dialog -->
    <Dialog
      v-model:visible="showMergeDialog"
      header="Fusionner les contacts"
      modal
      class="w-full max-w-3xl"
    >
      <div v-if="selectedPair" class="space-y-4">
        <p class="text-surface-500 text-sm">
          Sélectionnez les champs à conserver pour chaque contact. Le contact résultant combinera les informations choisies.
        </p>

        <div class="grid grid-cols-3 gap-4">
          <!-- Header -->
          <div class="font-semibold text-surface-700 dark:text-surface-200">Champ</div>
          <div class="font-semibold text-primary-600">
            {{ selectedPair.contactA.name }}
            <Tag value="A" severity="info" class="ml-2" />
          </div>
          <div class="font-semibold text-orange-600">
            {{ selectedPair.contactB.name }}
            <Tag value="B" severity="warn" class="ml-2" />
          </div>

          <!-- Rows -->
          <template v-for="field in mergeFields" :key="field.key">
            <div class="text-sm text-surface-600 dark:text-surface-300 self-center">{{ field.label }}</div>
            <div
              class="p-3 rounded-lg border-2 cursor-pointer transition-colors text-sm"
              :class="mergeChoices[field.key] === 'A'
                ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
                : 'border-surface-200 dark:border-surface-600 hover:border-primary-300'"
              @click="mergeChoices[field.key] = 'A'"
             role="button" tabindex="0" @keydown.enter.prevent="mergeChoices[field.key] = 'A'">
              {{ selectedPair.contactA[field.key] || '—' }}
            </div>
            <div
              class="p-3 rounded-lg border-2 cursor-pointer transition-colors text-sm"
              :class="mergeChoices[field.key] === 'B'
                ? 'border-orange-500 bg-orange-50 dark:bg-orange-900/20'
                : 'border-surface-200 dark:border-surface-600 hover:border-orange-300'"
              @click="mergeChoices[field.key] = 'B'"
             role="button" tabindex="0" @keydown.enter.prevent="mergeChoices[field.key] = 'B'">
              {{ selectedPair.contactB[field.key] || '—' }}
            </div>
          </template>
        </div>
      </div>

      <template #footer>
        <Button label="Annuler" severity="secondary" outlined @click="showMergeDialog = false" />
        <Button label="Confirmer la fusion" icon="pi pi-check" @click="confirmMerge" :loading="merging" />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed} from 'vue'
import { Head, usePage} from '@inertiajs/vue3'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useRoleAccess } from '@/composables/useRoleAccess'

// Chantier 32.15: useRoleAccess() was called with no import anywhere in this file — a
// guaranteed `ReferenceError: useRoleAccess is not defined` on mount. Currently latent (this
// page has no web route, per the module's own documented, deliberate "gap, not built" decision
// — see CLAUDE.md's Chantier 19 CRM entry), but fixed regardless since it would crash the
// instant a future route ever pointed at it.
const page = usePage()
const { isAdmin, isElevated, hasAnyRole } = useRoleAccess()
const canManage = computed(() => isElevated.value || hasAnyRole(['sales-manager']))
const canCreate = computed(() => canManage.value)
const canEdit = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)


interface ContactRecord {
  id: number
  name: string
  email: string
  phone: string
  company: string
}

interface DuplicatePair {
  id: number
  contactA: ContactRecord
  contactB: ContactRecord
  similarity: number
  reasons: string[]
}

const detecting = ref(false)
const merging = ref(false)
const showMergeDialog = ref(false)
const selectedPair = ref<DuplicatePair | null>(null)

const stats = ref({
  detected: 5,
  mergedThisMonth: 18,
  cleaned: 43,
  dbGain: 12,
})

const duplicatePairs = ref<DuplicatePair[]>([
  {
    id: 1,
    contactA: { id: 101, name: 'Aminata Diallo', email: 'a.diallo@techdk.sn', phone: '+221 77 100 0001', company: 'Tech Dakar' },
    contactB: { id: 102, name: 'A. Diallo', email: 'aminata.diallo@techdk.sn', phone: '+221 77 100 0001', company: 'Tech Dakar SARL' },
    similarity: 96,
    reasons: ['email', 'téléphone'],
  },
  {
    id: 2,
    contactA: { id: 103, name: 'Kofi Mensah', email: 'kofi@ghanalink.com', phone: '+233 24 200 0002', company: 'GhanaLink' },
    contactB: { id: 104, name: 'Kofi K. Mensah', email: 'k.mensah@ghanalink.com', phone: '+233 24 200 0002', company: 'GhanaLink Ltd' },
    similarity: 91,
    reasons: ['téléphone', 'nom'],
  },
  {
    id: 3,
    contactA: { id: 105, name: 'Fatou Ndiaye', email: 'fndiaye@abidjan.ci', phone: '+225 07 300 0003', company: 'Abidjan Négoce' },
    contactB: { id: 106, name: 'Fatou Ndiaye', email: 'fatou.ndiaye@abidjan.ci', phone: '', company: 'Abidjan Négoce SA' },
    similarity: 88,
    reasons: ['nom'],
  },
  {
    id: 4,
    contactA: { id: 107, name: 'Jean-Pierre Mbeki', email: 'jp.mbeki@congo-trading.cd', phone: '+243 81 400 0004', company: 'Congo Trading' },
    contactB: { id: 108, name: 'Jean Pierre Mbeki', email: 'jmbeki@congo-trading.cd', phone: '+243 81 400 0004', company: 'Congo Trading SA' },
    similarity: 85,
    reasons: ['nom', 'téléphone'],
  },
  {
    id: 5,
    contactA: { id: 109, name: 'Laila Benali', email: 'laila@marocexport.ma', phone: '+212 6 500 0005', company: 'Maroc Export' },
    contactB: { id: 110, name: 'L. Benali', email: 'lbenali@marocexport.ma', phone: '+212 6 500 0005', company: 'Maroc Export SARL' },
    similarity: 82,
    reasons: ['email', 'téléphone'],
  },
])

const mergeFields = [
  { key: 'name', label: 'Nom complet' },
  { key: 'email', label: 'Email' },
  { key: 'phone', label: 'Téléphone' },
  { key: 'company', label: 'Entreprise' },
]

const mergeChoices = reactive<Record<string, 'A' | 'B'>>({
  name: 'A',
  email: 'A',
  phone: 'A',
  company: 'A',
})

const runDetection = async () => {
  detecting.value = true
  await new Promise(resolve => setTimeout(resolve, 1500))
  detecting.value = false
}

const openMergeDialog = (pair: DuplicatePair) => {
  selectedPair.value = pair
  mergeFields.forEach(f => { mergeChoices[f.key] = 'A' })
  showMergeDialog.value = true
}

const ignorePair = (pair: DuplicatePair) => {
  duplicatePairs.value = duplicatePairs.value.filter(p => p.id !== pair.id)
  stats.value.detected = duplicatePairs.value.length
}

const confirmMerge = async () => {
  merging.value = true
  await new Promise(resolve => setTimeout(resolve, 800))
  if (selectedPair.value) {
    duplicatePairs.value = duplicatePairs.value.filter(p => p.id !== selectedPair.value!.id)
    stats.value.detected = duplicatePairs.value.length
    stats.value.mergedThisMonth += 1
    stats.value.cleaned += 1
  }
  merging.value = false
  showMergeDialog.value = false
}
</script>
