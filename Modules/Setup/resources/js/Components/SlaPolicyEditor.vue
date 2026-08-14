<template>
  <div class="space-y-3">
    <div class="flex items-center justify-between">
      <h4 class="text-sm font-semibold text-surface-700 dark:text-surface-300">SLA Helpdesk par défaut</h4>
      <Button v-if="!loaded" label="Charger" size="small" text :loading="loading" @click="load" />
    </div>

    <div v-if="loading" class="text-sm text-surface-400">Chargement…</div>

    <div v-else-if="loaded" class="space-y-2">
      <div
        v-for="policy in policies"
        :key="policy.id"
        class="p-3 border rounded-lg border-surface-200 dark:border-surface-700 space-y-2"
      >
        <div class="flex items-center justify-between">
          <span class="text-sm font-medium text-surface-900 dark:text-surface-50">
            {{ policy.name }}
            <Tag v-if="policy.is_default" value="Par défaut" severity="info" class="ml-2" />
          </span>
          <i v-if="savingId === policy.id" class="pi pi-spin pi-spinner text-surface-400" />
          <i v-else-if="savedId === policy.id" class="pi pi-check text-green-500" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs text-surface-400 mb-1">1ère réponse (min)</label>
            <InputNumber v-model="policy.response_time_minutes" class="w-full" :min="1" @blur="save(policy)" />
          </div>
          <div>
            <label class="block text-xs text-surface-400 mb-1">Résolution (min)</label>
            <InputNumber v-model="policy.resolution_time_minutes" class="w-full" :min="1" @blur="save(policy)" />
          </div>
        </div>
      </div>
      <p class="text-xs text-surface-400">
        4 paliers ({{ policies.length }}) créés automatiquement au premier chargement — un seul est marqué "par défaut"
        et sera appliqué à tout ticket créé sans SLA explicite.
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import Tag from 'primevue/tag'

interface SlaPolicy {
  id: number
  name: string
  priority: string
  response_time_minutes: number
  resolution_time_minutes: number
  is_default: boolean
}

const loading = ref(false)
const loaded = ref(false)
const policies = ref<SlaPolicy[]>([])
const savingId = ref<number | null>(null)
const savedId = ref<number | null>(null)

const load = async () => {
  loading.value = true
  try {
    const res = await fetch('/api/v1/helpdesk/sla/policies', { headers: { Accept: 'application/json' } })
    if (!res.ok) return
    policies.value = await res.json()
    loaded.value = true
  } finally {
    loading.value = false
  }
}

const save = async (policy: SlaPolicy) => {
  savingId.value = policy.id
  savedId.value = null
  try {
    await fetch(`/api/v1/helpdesk/sla/policies/${policy.id}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        response_time_minutes: policy.response_time_minutes,
        resolution_time_minutes: policy.resolution_time_minutes,
      }),
    })
    savedId.value = policy.id
  } finally {
    savingId.value = null
  }
}
</script>
