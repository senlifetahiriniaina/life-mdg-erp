<template>
  <AppLayout title="Postes de charge">
    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Postes de charge</h1>
          <p class="text-surface-500 dark:text-surface-400 mt-1">Gestion des ressources de production et leur capacité</p>
        </div>
        <Button label="Nouveau poste" icon="pi pi-plus" @click="createModal = true" />
      </div>

      <!-- Capacity overview -->
      <div class="grid grid-cols-4 gap-4">
        <div v-for="wc in workCenters" :key="wc.id"
          class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl p-4 shadow-sm border border-gray-100 cursor-pointer hover:shadow-md transition-shadow"
          @click="selectedWC = wc"
        >
          <div class="flex items-center justify-between mb-2">
            <span class="font-semibold text-surface-900 dark:text-surface-50 text-sm">{{ wc.name }}</span>
            <Tag :value="wc.status" :severity="wcSeverity(wc.status)" class="text-xs" />
          </div>
          <div class="flex items-center gap-2 mb-2">
            <div class="flex-1 bg-gray-100 dark:bg-surface-700 rounded-full h-2">
              <div
                :class="['h-2 rounded-full', wc.load >= 90 ? 'bg-red-50 dark:bg-red-900/200' : wc.load >= 70 ? 'bg-yellow-50 dark:bg-yellow-900/200' : 'bg-green-50 dark:bg-green-900/200']"
                :style="{ width: wc.load + '%' }"
              />
            </div>
            <span class="text-xs text-surface-600 dark:text-surface-400 w-8 text-right">{{ wc.load }}%</span>
          </div>
          <div class="grid grid-cols-2 gap-1 text-xs text-surface-500 dark:text-surface-400">
            <span>Capacité : {{ wc.capacity_hours }}h/j</span>
            <span>Opérateurs : {{ wc.operators }}</span>
          </div>
        </div>
      </div>

      <!-- Weekly schedule -->
      <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl shadow-sm border border-gray-100 p-4">
        <h3 class="font-semibold text-surface-900 dark:text-surface-50 mb-4">Planning hebdomadaire</h3>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-200 dark:border-surface-700">
                <th class="text-left p-2 text-surface-500 dark:text-surface-400 font-medium w-36">Poste</th>
                <th v-for="day in days" :key="day" class="text-center p-2 text-surface-500 dark:text-surface-400 font-medium w-24">{{ day }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="wc in workCenters" :key="wc.id" class="border-b border-gray-100">
                <td class="p-2 font-medium text-gray-800 dark:text-surface-100">{{ wc.name }}</td>
                <td v-for="day in days" :key="day" class="p-1">
                  <div
                    v-if="getSchedule(wc.id, day)"
                    :class="['rounded text-xs p-1 text-center text-white', getSchedule(wc.id, day).color]"
                  >
                    {{ getSchedule(wc.id, day).label }}
                  </div>
                  <div v-else class="rounded text-xs p-1 text-center text-surface-300 dark:text-surface-600 bg-gray-50 dark:bg-surface-800 dark:bg-surface-800">Libre</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Create Modal -->
    <Dialog v-model:visible="createModal" header="Nouveau poste de charge" :style="{ width: '32rem' }" modal>
      <div class="space-y-4">
        <div><label class="text-sm font-medium text-surface-700 dark:text-surface-300 block mb-1">Nom</label><InputText v-model="form.name" class="w-full" /></div>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="text-sm font-medium text-surface-700 dark:text-surface-300 block mb-1">Type</label>
            <Dropdown v-model="form.type" :options="['machine', 'assembly', 'quality', 'packaging']" class="w-full" /></div>
          <div><label class="text-sm font-medium text-surface-700 dark:text-surface-300 block mb-1">Capacité (h/j)</label>
            <InputNumber v-model="form.capacity_hours" :min="1" :max="24" class="w-full" /></div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="text-sm font-medium text-surface-700 dark:text-surface-300 block mb-1">Opérateurs</label>
            <InputNumber v-model="form.operators" :min="1" class="w-full" /></div>
          <div><label class="text-sm font-medium text-surface-700 dark:text-surface-300 block mb-1">Coût/h (€)</label>
            <InputNumber v-model="form.cost_per_hour" :minFractionDigits="2" class="w-full" /></div>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <Button label="Annuler" outlined @click="createModal = false" />
          <Button label="Créer" severity="success" @click="save" />
        </div>
      </div>
    </Dialog>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, Tag, Dialog, InputText, Dropdown, InputNumber, DataTable, Column } from 'primevue'
import axios from 'axios'

const createModal = ref(false)
const selectedWC = ref(null)
const form = ref({ name: '', type: 'machine', capacity_hours: 8, operators: 1, cost_per_hour: 25 })
const days = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi']

const workCenters = ref([
  { id: 1, name: 'Découpe CNC', status: 'active', load: 85, capacity_hours: 16, operators: 2 },
  { id: 2, name: 'Soudage', status: 'active', load: 60, capacity_hours: 8, operators: 1 },
  { id: 3, name: 'Assemblage', status: 'active', load: 72, capacity_hours: 16, operators: 4 },
  { id: 4, name: 'Contrôle qualité', status: 'maintenance', load: 0, capacity_hours: 8, operators: 1 },
])

const schedule = {
  1: { Lundi: { label: 'OF-142', color: 'bg-primary-50 dark:bg-primary-900/200' }, Mardi: { label: 'OF-143', color: 'bg-primary-50 dark:bg-primary-900/200' }, Mercredi: { label: 'OF-144', color: 'bg-violet-50 dark:bg-violet-900/200' } },
  2: { Lundi: { label: 'OF-141', color: 'bg-green-50 dark:bg-green-900/200' }, Jeudi: { label: 'OF-145', color: 'bg-green-50 dark:bg-green-900/200' } },
  3: { Mardi: { label: 'OF-140', color: 'bg-amber-50 dark:bg-amber-900/200' }, Mercredi: { label: 'OF-140', color: 'bg-amber-50 dark:bg-amber-900/200' }, Vendredi: { label: 'OF-146', color: 'bg-primary-50 dark:bg-primary-900/200' } },
}

const wcSeverity = (s) => ({ active: 'success', maintenance: 'warn', inactive: 'secondary' }[s] || 'secondary')
function getSchedule(wcId, day) { return schedule[wcId]?.[day] ?? null }
function save() {
  axios.post('/api/v1/manufacturing/work-centers', form.value).catch(() => {})
  workCenters.value.push({ id: Date.now(), ...form.value, status: 'active', load: 0 })
  createModal.value = false
}
</script>
