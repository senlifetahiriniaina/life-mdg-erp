<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Gestion de la Main-d'œuvre — WMS</h1>
        <p class="text-surface-500 text-sm mt-1">Planification des équipes entrepôt, productivité et affectation des tâches</p>
      </div>
      <Button label="Planifier l'équipe" icon="pi pi-calendar" @click="showScheduleDialog = true" v-if="canManage" />
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <Card v-for="s in stats" :key="s.label"><template #content>
        <div class="text-2xl font-bold" :class="s.color">{{ s.value }}</div>
        <div class="text-sm text-surface-500 mt-1">{{ s.label }}</div>
      </template></Card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <Card class="lg:col-span-2">
        <template #header><div class="px-4 pt-4 font-semibold">Agents entrepôt — Aujourd'hui</div></template>
        <template #content>
          <DataTable :value="workers" stripedRows>
            <Column field="name" header="Agent" />
            <Column field="zone" header="Zone affectée">
              <template #body="{ data }"><Tag :value="data.zone" severity="secondary" size="small" /></template>
            </Column>
            <Column field="shift" header="Équipe" />
            <Column field="tasksCompleted" header="Tâches/Heure">
              <template #body="{ data }">
                <div class="flex items-center gap-2">
                  <span class="font-bold" :class="data.tasksCompleted >= data.target ? 'text-green-600' : 'text-orange-500'">{{ data.tasksCompleted }}</span>
                  <span class="text-xs text-surface-400">/ {{ data.target }} cible</span>
                </div>
              </template>
            </Column>
            <Column field="productivity" header="Productivité">
              <template #body="{ data }"><ProgressBar :value="data.productivity" :style="{ height: '8px', width: '100px' }" /></template>
            </Column>
            <Column field="status" header="Statut">
              <template #body="{ data }"><Tag :value="data.status" :severity="{ 'En activité': 'success', Pause: 'warn', 'Hors zone': 'danger', Repos: 'secondary' }[data.status]" size="small" /></template>
            </Column>
          </DataTable>
        </template>
      </Card>

      <Card>
        <template #header><div class="px-4 pt-4 font-semibold">File de tâches en attente</div></template>
        <template #content>
          <div class="space-y-2">
            <div v-for="task in pendingTasks" :key="task.id" class="p-2 border rounded flex items-center justify-between">
              <div>
                <div class="text-sm font-medium">{{ task.type }}</div>
                <div class="text-xs text-surface-400">{{ task.detail }}</div>
              </div>
              <div class="flex items-center gap-1">
                <Tag :value="task.priority" :severity="{ Urgent: 'danger', Normal: 'secondary' }[task.priority]" size="small" />
                <Button label="Affecter" size="small" text @click="assignTask(task)" />
              </div>
            </div>
            <div class="text-xs text-surface-400 text-center mt-2">{{ pendingTasks.length }} tâches en attente d'affectation</div>
          </div>
        </template>
      </Card>
    </div>

    <Card>
      <template #header><div class="px-4 pt-4 font-semibold">Productivité par zone — Aujourd'hui</div></template>
      <template #content>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
          <div v-for="zone in zones" :key="zone.name" class="p-3 border rounded-lg text-center">
            <div class="text-sm font-medium mb-1">{{ zone.name }}</div>
            <div class="text-2xl font-bold" :class="zone.pct >= 90 ? 'text-green-600' : zone.pct >= 70 ? 'text-orange-500' : 'text-red-600'">{{ zone.pct }}%</div>
            <div class="text-xs text-surface-400">{{ zone.agents }} agents · {{ zone.tasks }} tâches</div>
          </div>
        </div>
      </template>
    </Card>

    <Dialog v-model:visible="showScheduleDialog" header="Planifier l'équipe" :style="{ width: '500px' }" modal>
      <div class="space-y-4">
        <div class="grid grid-cols-2 gap-3">
          <div><label class="text-sm font-medium">Date</label><InputText v-model="schedForm.date" class="w-full mt-1" placeholder="YYYY-MM-DD" /></div>
          <div><label class="text-sm font-medium">Équipe</label>
            <Select v-model="schedForm.shift" :options="['Matin (6h-14h)', 'Après-midi (14h-22h)', 'Nuit (22h-6h)']" class="w-full mt-1" /></div>
        </div>
        <div><label class="text-sm font-medium">Agents requis par zone</label>
          <div class="space-y-2 mt-2">
            <div v-for="z in ['Réception', 'Stockage', 'Préparation', 'Expédition', 'Retours']" :key="z" class="flex items-center justify-between">
              <span class="text-sm">{{ z }}</span>
              <InputNumber :modelValue="2" :min="0" :max="20" class="w-24" />
            </div>
          </div>
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" text @click="showScheduleDialog = false" />
        <Button label="Valider le planning" @click="showScheduleDialog = false" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Card from 'primevue/card'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import ProgressBar from 'primevue/progressbar'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'

const page = usePage()
const { isElevated, hasAnyRole } = useRoleAccess()
const canManage = computed(() => isElevated.value || hasAnyRole(['warehouse-operator']))

const showScheduleDialog = ref(false)
const schedForm = ref({ date: '', shift: null })

const stats = [
  { label: 'Agents présents', value: '18/22', color: 'text-blue-600' },
  { label: 'Productivité globale', value: '87%', color: 'text-green-600' },
  { label: 'Tâches complétées', value: '342', color: 'text-purple-600' },
  { label: 'Tâches en attente', value: '28', color: 'text-orange-600' },
]

const workers = ref([
  { name: 'Mamadou Sow', zone: 'Préparation A', shift: 'Matin', tasksCompleted: 24, target: 20, productivity: 92, status: 'En activité' },
  { name: 'Aïssatou Bah', zone: 'Réception', shift: 'Matin', tasksCompleted: 18, target: 20, productivity: 85, status: 'En activité' },
  { name: 'Lamine Touré', zone: 'Expédition', shift: 'Matin', tasksCompleted: 15, target: 20, productivity: 72, status: 'Pause' },
  { name: 'Ndéye Faye', zone: 'Stockage B', shift: 'Matin', tasksCompleted: 22, target: 20, productivity: 95, status: 'En activité' },
  { name: 'Ibrahima Cissé', zone: 'Retours', shift: 'Matin', tasksCompleted: 8, target: 15, productivity: 54, status: 'Hors zone' },
  { name: 'Rokhaya Dieng', zone: 'Préparation B', shift: 'Matin', tasksCompleted: 20, target: 20, productivity: 88, status: 'En activité' },
])

const pendingTasks = ref([
  { id: 1, type: 'Préparation commande', detail: 'ORD-4525 — 8 lignes · Zone A', priority: 'Urgent' },
  { id: 2, type: 'Rangement réception', detail: 'BL-2026-089 — 24 colis', priority: 'Normal' },
  { id: 3, type: 'Inventaire zone C', detail: 'Cycle count programmé', priority: 'Normal' },
  { id: 4, type: 'Préparation retour', detail: 'RET-2026-034 — renvoi fournisseur', priority: 'Urgent' },
])

const zones = [
  { name: 'Réception', pct: 85, agents: 3, tasks: 45 },
  { name: 'Stockage A', pct: 92, agents: 4, tasks: 88 },
  { name: 'Stockage B', pct: 78, agents: 3, tasks: 62 },
  { name: 'Préparation', pct: 94, agents: 6, tasks: 124 },
  { name: 'Expédition', pct: 88, agents: 2, tasks: 38 },
]

const assignTask = (task) => { pendingTasks.value = pendingTasks.value.filter(t => t.id !== task.id) }
</script>
