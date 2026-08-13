<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Slotting & Optimisation des Emplacements</h1>
        <p class="text-surface-500 text-sm mt-1">L'IA suggère les meilleurs emplacements selon la fréquence de picking, le poids et la famille produit</p>
      </div>
      <Button label="Lancer l'optimisation IA" icon="pi pi-bolt" :loading="optimizing" @click="optimize" v-if="canManage" />
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <Card v-for="s in stats" :key="s.label"><template #content>
        <div class="text-2xl font-bold" :class="s.color">{{ s.value }}</div>
        <div class="text-sm text-surface-500 mt-1">{{ s.label }}</div>
      </template></Card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <Card>
        <template #header><div class="px-4 pt-4 font-semibold">Plan de l'entrepôt — Occupation</div></template>
        <template #content>
          <div class="overflow-x-auto">
            <div class="flex gap-1 mb-2">
              <div v-for="col in 10" :key="col" class="text-xs text-center w-10 text-surface-400">{{ col }}</div>
            </div>
            <div v-for="(row, ri) in warehouseGrid" :key="ri" class="flex gap-1 mb-1 items-center">
              <span class="text-xs text-surface-400 w-6">{{ String.fromCharCode(65 + ri) }}</span>
              <div v-for="(cell, ci) in row" :key="ci" class="w-10 h-10 rounded flex items-center justify-center text-xs font-bold cursor-pointer" :class="slotClass(cell)" :title="cell.sku || 'Libre'" @click="selectedSlot = cell; showSlotDrawer = true" role="button" tabindex="0" @keydown.enter.prevent="selectedSlot = cell; showSlotDrawer = true">
                {{ cell.type === 'empty' ? '' : cell.type === 'aisle' ? '↔' : cell.sku?.substring(0,3) || '?' }}
              </div>
            </div>
          </div>
          <div class="flex gap-3 mt-3 text-xs flex-wrap">
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-green-400 inline-block"></span> Haute rotation</span>
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-blue-400 inline-block"></span> Rotation moyenne</span>
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-surface-300 inline-block"></span> Faible rotation</span>
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-red-400 inline-block"></span> Mal placé (IA)</span>
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-surface-100 border inline-block"></span> Libre</span>
          </div>
        </template>
      </Card>

      <Card>
        <template #header><div class="px-4 pt-4 font-semibold">Suggestions de déplacement IA</div></template>
        <template #content>
          <div v-if="suggestions.length === 0" class="text-center text-surface-400 py-8">Aucune suggestion — entrepôt optimisé</div>
          <div v-else class="space-y-3">
            <div v-for="sug in suggestions" :key="sug.sku" class="p-3 border rounded-lg">
              <div class="flex items-center justify-between mb-1">
                <span class="font-medium text-sm">{{ sug.product }}</span>
                <Tag :value="sug.saving" severity="success" size="small" />
              </div>
              <div class="text-xs text-surface-500 flex gap-4">
                <span>Actuel: <strong>{{ sug.from }}</strong></span>
                <span>→ Suggéré: <strong class="text-blue-600">{{ sug.to }}</strong></span>
              </div>
              <div class="text-xs text-surface-400 mt-0.5">{{ sug.reason }}</div>
              <Button label="Appliquer" size="small" text class="mt-1" @click="applySuggestion(sug)" />
            </div>
          </div>
        </template>
      </Card>
    </div>

    <Card>
      <template #header><div class="px-4 pt-4 font-semibold">Fréquence de picking — Top produits</div></template>
      <template #content>
        <DataTable :value="pickingFrequency" stripedRows>
          <Column field="rank" header="#" style="width: 50px" />
          <Column field="sku" header="SKU" />
          <Column field="product" header="Produit" />
          <Column field="currentSlot" header="Emplacement actuel" />
          <Column field="picks" header="Picks/semaine" />
          <Column field="zone" header="Zone recommandée">
            <template #body="{ data }"><Tag :value="data.zone" :severity="{ 'Zone A (Chaud)': 'success', 'Zone B (Tiède)': 'info', 'Zone C (Froid)': 'secondary' }[data.zone]" size="small" /></template>
          </Column>
          <Column field="optimal" header="Optimal ?">
            <template #body="{ data }"><Tag :value="data.optimal ? '✓ Oui' : '✗ Non'" :severity="data.optimal ? 'success' : 'warn'" size="small" /></template>
          </Column>
        </DataTable>
      </template>
    </Card>
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
import Drawer from 'primevue/drawer'

const page = usePage()
const roles = computed(() => page.props.auth?.user?.roles?.map(r => r.name) || [])
const canManage = computed(() => roles.value.some(r => ['warehouse-manager','admin','super-admin'].includes(r)))

const optimizing = ref(false)
const showSlotDrawer = ref(false)
const selectedSlot = ref(null)
const optimize = async () => { optimizing.value = true; await new Promise(r => setTimeout(r, 2000)); optimizing.value = false }

const stats = [
  { label: 'Emplacements total', value: '480', color: 'text-blue-600' },
  { label: 'Taux d\'occupation', value: '73%', color: 'text-green-600' },
  { label: 'Déplacements suggérés', value: '12', color: 'text-orange-600' },
  { label: 'Gain picking estimé', value: '-18%', color: 'text-purple-600' },
]

const makeCell = (type, sku = null, rotation = 'medium') => ({ type, sku, rotation })

const warehouseGrid = [
  [makeCell('aisle'), ...Array(8).fill(null).map((_, i) => makeCell('occupied', ['LPT','LCD','CLV','SUR','CSQ','HUB','SSD','WBC'][i], ['high','high','medium','high','medium','low','medium','low'][i])), makeCell('aisle')],
  [makeCell('aisle'), makeCell('occupied', 'KBD', 'high'), makeCell('occupied', 'MSE', 'high'), makeCell('empty'), makeCell('occupied', 'HDM', 'medium'), makeCell('occupied', 'CAB', 'low'), makeCell('empty'), makeCell('occupied', 'ADT', 'misplaced'), makeCell('empty'), makeCell('aisle')],
  [makeCell('aisle'), ...Array(8).fill(makeCell('empty')), makeCell('aisle')],
  [makeCell('aisle'), makeCell('occupied', 'RAM', 'high'), makeCell('occupied', 'CPU', 'medium'), makeCell('occupied', 'MBD', 'low'), makeCell('empty'), makeCell('occupied', 'PSU', 'medium'), makeCell('occupied', 'FAN', 'misplaced'), makeCell('empty'), makeCell('occupied', 'GFX', 'medium'), makeCell('aisle')],
  [makeCell('aisle'), ...Array(8).fill(makeCell('empty')), makeCell('aisle')],
]

const slotClass = (cell) => {
  if (!cell) return 'bg-surface-100 border'
  if (cell.type === 'aisle') return 'bg-surface-200 text-surface-500'
  if (cell.type === 'empty') return 'bg-surface-50 border border-dashed border-surface-300 text-surface-300'
  if (cell.rotation === 'high') return 'bg-green-400 text-white'
  if (cell.rotation === 'medium') return 'bg-blue-400 text-white'
  if (cell.rotation === 'low') return 'bg-surface-300 text-surface-700'
  if (cell.rotation === 'misplaced') return 'bg-red-400 text-white animate-pulse'
  return 'bg-surface-200'
}

const suggestions = ref([
  { sku: 'ADT', product: 'Adaptateur VGA-HDMI', from: 'B-7 (Zone C)', to: 'A-3 (Zone A)', reason: '142 picks/semaine — trop éloigné de l\'expédition', saving: '-4 min/pick' },
  { sku: 'FAN', product: 'Ventilateur boîtier', from: 'D-6 (Zone A)', to: 'D-9 (Zone C)', reason: '3 picks/semaine — occupe un emplacement de haute rotation', saving: '+libère slot A' },
])

const pickingFrequency = ref([
  { rank: 1, sku: 'LPT-001', product: 'Laptop Pro 15"', currentSlot: 'A-1', picks: 285, zone: 'Zone A (Chaud)', optimal: true },
  { rank: 2, sku: 'MSE-002', product: 'Souris sans fil', currentSlot: 'B-2', picks: 234, zone: 'Zone A (Chaud)', optimal: true },
  { rank: 3, sku: 'KBD-003', product: 'Clavier mécanique', currentSlot: 'B-1', picks: 198, zone: 'Zone A (Chaud)', optimal: true },
  { rank: 4, sku: 'ADT-007', product: 'Adaptateur VGA-HDMI', currentSlot: 'B-7', picks: 142, zone: 'Zone A (Chaud)', optimal: false },
  { rank: 5, sku: 'SSD-006', product: 'SSD 1To', currentSlot: 'A-7', picks: 120, zone: 'Zone A (Chaud)', optimal: true },
  { rank: 6, sku: 'CSQ-005', product: 'Casque Bluetooth', currentSlot: 'A-5', picks: 88, zone: 'Zone B (Tiède)', optimal: true },
  { rank: 7, sku: 'FAN-012', product: 'Ventilateur boîtier', currentSlot: 'D-6', picks: 3, zone: 'Zone C (Froid)', optimal: false },
])

const applySuggestion = (sug) => { suggestions.value = suggestions.value.filter(s => s.sku !== sug.sku) }
</script>
