<template>
  <AppLayout title="Wave Picking">
    <div class="p-6">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Wave Picking</h1>
        <Button label="Créer vague" icon="pi pi-plus" @click="showCreateDialog = true" />
      </div>

      <!-- Wave List -->
      <DataTable
        :value="waves"
        :loading="loading"
        paginator
        :rows="20"
        class="mb-6"
      >
        <Column field="id" header="#" style="width: 60px" />
        <Column header="Statut">
          <template #body="{ data }">
            <Tag
              :value="data.status"
              :severity="statusSeverity(data.status)"
            />
          </template>
        </Column>
        <Column header="Picker">
          <template #body="{ data }">
            {{ data.picker?.name ?? '—' }}
          </template>
        </Column>
        <Column header="Progression">
          <template #body="{ data }">
            <div class="flex items-center gap-2">
              <div class="w-32 bg-surface-200 rounded-full h-2">
                <div
                  class="bg-primary-500 h-2 rounded-full"
                  :style="{ width: progressPercent(data) + '%' }"
                />
              </div>
              <span class="text-sm text-surface-600">{{ progressText(data) }}</span>
            </div>
          </template>
        </Column>
        <Column header="Commandes">
          <template #body="{ data }">
            {{ data.order_ids?.length ?? 0 }} commande(s)
          </template>
        </Column>
        <Column header="Actions">
          <template #body="{ data }">
            <div class="flex gap-2">
              <Button
                v-if="data.status === 'open'"
                label="Démarrer"
                size="small"
                severity="success"
                @click="startWave(data.id)"
              />
              <Button
                v-if="data.status === 'in_progress'"
                label="Terminer"
                size="small"
                severity="warning"
                @click="completeWave(data.id)"
              />
              <Button
                label="Détail"
                size="small"
                severity="secondary"
                @click="openWaveDetail(data)"
              />
            </div>
          </template>
        </Column>
      </DataTable>

      <!-- Wave Detail Dialog -->
      <Dialog
        v-model:visible="showDetailDialog"
        :header="'Vague #' + (selectedWave?.id ?? '')"
        :style="{ width: '700px' }"
        modal
      >
        <div v-if="selectedWave">
          <div class="flex items-center gap-3 mb-4">
            <Tag :value="selectedWave.status" :severity="statusSeverity(selectedWave.status)" />
            <span class="text-surface-600">Picker : {{ selectedWave.picker?.name ?? 'Non assigné' }}</span>
          </div>
          <DataTable :value="selectedWave.lines ?? []" class="mb-4">
            <Column field="id" header="#" style="width: 50px" />
            <Column header="Produit">
              <template #body="{ data }">{{ data.product?.name ?? data.product_id }}</template>
            </Column>
            <Column field="warehouse_location" header="Emplacement" />
            <Column header="Demandé / Prélevé">
              <template #body="{ data }">
                {{ data.qty_picked }} / {{ data.qty_requested }}
              </template>
            </Column>
            <Column header="Statut">
              <template #body="{ data }">
                <Tag :value="data.status" :severity="lineStatusSeverity(data.status)" />
              </template>
            </Column>
            <Column header="Confirmer">
              <template #body="{ data }">
                <div v-if="data.status === 'pending'" class="flex gap-2 items-center">
                  <InputNumber
                    v-model="pickQty[data.id]"
                    :min="0"
                    :max="Number(data.qty_requested)"
                    :step="1"
                    style="width: 80px"
                    size="small"
                  />
                  <Button
                    label="OK"
                    size="small"
                    @click="confirmPick(selectedWave.id, data.id, pickQty[data.id] ?? 0)"
                  />
                </div>
              </template>
            </Column>
          </DataTable>
        </div>
      </Dialog>

      <!-- Create Wave Dialog -->
      <Dialog
        v-model:visible="showCreateDialog"
        header="Créer une vague de picking"
        :style="{ width: '500px' }"
        modal
      >
        <div class="flex flex-col gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">IDs de commandes (séparés par virgules)</label>
            <InputText v-model="newWaveOrderIds" class="w-full" placeholder="101,102,103" />
          </div>
          <div class="flex justify-end gap-2">
            <Button label="Annuler" severity="secondary" @click="showCreateDialog = false" />
            <Button label="Créer" @click="createWave" />
          </div>
        </div>
      </Dialog>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Button, DataTable, Column, Dialog, Tag, InputText, InputNumber } from 'primevue'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

interface PickLine {
  id: number
  product_id: number
  product?: { name: string }
  warehouse_location: string | null
  qty_requested: string
  qty_picked: string
  status: string
}

interface Wave {
  id: number
  status: string
  picker?: { name: string } | null
  order_ids: number[]
  lines?: PickLine[]
  started_at?: string | null
  completed_at?: string | null
}

const waves = ref<Wave[]>([])
const loading = ref(false)
const showCreateDialog = ref(false)
const showDetailDialog = ref(false)
const selectedWave = ref<Wave | null>(null)
const newWaveOrderIds = ref('')
const pickQty = ref<Record<number, number>>({})

async function loadWaves() {
  loading.value = true
  try {
    const res = await axios.get('/api/v1/inventory/waves')
    waves.value = res.data.data ?? res.data
  } finally {
    loading.value = false
  }
}

function statusSeverity(status: string) {
  return { open: 'info', in_progress: 'warning', completed: 'success' }[status] ?? 'secondary'
}

function lineStatusSeverity(status: string) {
  return { pending: 'secondary', picked: 'success', short: 'danger' }[status] ?? 'secondary'
}

function progressPercent(wave: Wave): number {
  const lines = wave.lines ?? []
  if (!lines.length) return 0
  const done = lines.filter(l => l.status !== 'pending').length
  return Math.round((done / lines.length) * 100)
}

function progressText(wave: Wave): string {
  const lines = wave.lines ?? []
  if (!lines.length) return '0 / 0'
  const done = lines.filter(l => l.status !== 'pending').length
  return `${done} / ${lines.length}`
}

async function startWave(id: number) {
  await axios.post(`/api/v1/inventory/waves/${id}/start`)
  await loadWaves()
}

async function completeWave(id: number) {
  await axios.post(`/api/v1/inventory/waves/${id}/complete`)
  await loadWaves()
}

async function openWaveDetail(wave: Wave) {
  const res = await axios.get(`/api/v1/inventory/waves/${wave.id}`)
  selectedWave.value = res.data
  showDetailDialog.value = true
}

async function confirmPick(waveId: number, lineId: number, qty: number) {
  await axios.post(`/api/v1/inventory/waves/${waveId}/lines/${lineId}/pick`, { qty })
  const res = await axios.get(`/api/v1/inventory/waves/${waveId}`)
  selectedWave.value = res.data
}

async function createWave() {
  const ids = newWaveOrderIds.value.split(',').map(s => parseInt(s.trim())).filter(n => !isNaN(n))
  await axios.post('/api/v1/inventory/waves', { order_ids: ids })
  showCreateDialog.value = false
  newWaveOrderIds.value = ''
  await loadWaves()
}

onMounted(loadWaves)
</script>
