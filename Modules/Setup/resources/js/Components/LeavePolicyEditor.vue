<template>
  <div class="space-y-3">
    <div class="flex items-center justify-between">
      <h4 class="text-sm font-semibold text-surface-700 dark:text-surface-300">Politiques de congés (RH)</h4>
      <Button v-if="!loaded" label="Charger" size="small" text :loading="loading" @click="load" />
    </div>

    <div v-if="loading" class="text-sm text-surface-400">Chargement…</div>

    <div v-else-if="loaded" class="space-y-2">
      <div
        v-for="type in leaveTypes"
        :key="type.id"
        class="p-3 border rounded-lg border-surface-200 dark:border-surface-700 space-y-2"
      >
        <div class="flex items-center justify-between">
          <span class="text-sm font-medium text-surface-900 dark:text-surface-50">{{ type.name }} ({{ type.code }})</span>
          <i v-if="savingId === type.id" class="pi pi-spin pi-spinner text-surface-400" />
          <i v-else-if="savedId === type.id" class="pi pi-check text-green-500" />
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 items-end">
          <div>
            <label class="block text-xs text-surface-400 mb-1">Jours/an</label>
            <InputNumber v-model="type.days_per_year" class="w-full" :min="0" @blur="save(type)" />
          </div>
          <div>
            <label class="block text-xs text-surface-400 mb-1">Niveaux d'approbation</label>
            <InputNumber v-model="type.approval_levels" class="w-full" :min="1" :max="5" @blur="save(type)" />
          </div>
          <div>
            <label class="block text-xs text-surface-400 mb-1">Report max (jours)</label>
            <InputNumber v-model="type.max_carry_forward_days" class="w-full" :min="0" @blur="save(type)" />
          </div>
          <div class="flex items-center gap-4 pb-2">
            <div class="flex items-center gap-2">
              <Checkbox v-model="type.is_paid" :binary="true" :input-id="`paid-${type.id}`" @change="save(type)" />
              <label :for="`paid-${type.id}`" class="text-xs">Rémunéré</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox v-model="type.carry_forward" :binary="true" :input-id="`carry-${type.id}`" @change="save(type)" />
              <label :for="`carry-${type.id}`" class="text-xs">Report autorisé</label>
            </div>
          </div>
        </div>
      </div>
      <p v-if="!leaveTypes.length" class="text-xs text-surface-400">
        Aucun type de congé configuré pour l'instant — les types se créent automatiquement à la première demande.
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import InputNumber from 'primevue/inputnumber'

interface LeaveType {
  id: number
  name: string
  code: string
  days_per_year: number
  is_paid: boolean
  carry_forward: boolean
  max_carry_forward_days: number | null
  approval_levels: number
}

const loading = ref(false)
const loaded = ref(false)
const leaveTypes = ref<LeaveType[]>([])
const savingId = ref<number | null>(null)
const savedId = ref<number | null>(null)

const load = async () => {
  loading.value = true
  try {
    const res = await fetch('/api/v1/hr/leave-types?per_page=50', { headers: { Accept: 'application/json' } })
    if (!res.ok) return
    const json = await res.json()
    leaveTypes.value = json.data ?? []
    loaded.value = true
  } finally {
    loading.value = false
  }
}

const save = async (type: LeaveType) => {
  savingId.value = type.id
  savedId.value = null
  try {
    await fetch(`/api/v1/hr/leave-types/${type.id}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        days_per_year: type.days_per_year,
        is_paid: type.is_paid,
        carry_forward: type.carry_forward,
        max_carry_forward_days: type.max_carry_forward_days,
        approval_levels: type.approval_levels,
      }),
    })
    savedId.value = type.id
  } finally {
    savingId.value = null
  }
}
</script>
