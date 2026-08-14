<template>
  <div class="space-y-3">
    <div class="flex items-center justify-between">
      <h4 class="text-sm font-semibold text-surface-700 dark:text-surface-300">{{ title }}</h4>
      <Button v-if="!loaded" label="Charger" size="small" text :loading="loading" @click="load" />
    </div>

    <div v-if="loading" class="text-sm text-surface-400">Chargement…</div>

    <div v-else-if="loaded" class="space-y-2">
      <div
        v-for="rule in rules"
        :key="rule.id"
        class="flex items-center gap-3 p-2 border rounded-lg border-surface-200 dark:border-surface-700"
      >
        <span class="text-xs font-mono text-surface-400 w-10">{{ rule.condition_operator }}</span>
        <InputNumber
          v-model="rule.condition_value"
          class="flex-1"
          :min-fraction-digits="0"
          :max-fraction-digits="0"
          @blur="save(rule)"
        />
        <span class="text-xs text-surface-400">{{ currency }}</span>
        <i v-if="savingId === rule.id" class="pi pi-spin pi-spinner text-surface-400" />
        <i v-else-if="savedId === rule.id" class="pi pi-check text-green-500" />
      </div>
      <p class="text-xs text-surface-400">
        Palier {{ rules.length }} niveau(x). Modifiez un montant puis quittez le champ pour enregistrer.
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'

const props = defineProps<{
  module: 'achats' | 'accounting'
  title: string
  currency?: string
}>()

interface Rule {
  id: number
  workflow_id: number
  rule_order: number
  condition_operator: string
  condition_value: string | number
}

const loading = ref(false)
const loaded = ref(false)
const workflowId = ref<number | null>(null)
const rules = ref<Rule[]>([])
const savingId = ref<number | null>(null)
const savedId = ref<number | null>(null)

const load = async () => {
  loading.value = true
  try {
    const res = await fetch(`/api/v1/setup/wizard/thresholds/${props.module}`, { headers: { Accept: 'application/json' } })
    if (!res.ok) return
    const json = await res.json()
    workflowId.value = json.data.id
    rules.value = (json.data.rules ?? []).sort((a: Rule, b: Rule) => a.rule_order - b.rule_order)
    loaded.value = true
  } finally {
    loading.value = false
  }
}

const patchRuleValue = (ruleId: number, value: string | number) =>
  fetch(`/api/v1/validation/approval-workflows/${workflowId.value}/rules/${ruleId}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ condition_value: String(value) }),
  })

const save = async (rule: Rule) => {
  if (!workflowId.value) return
  savingId.value = rule.id
  savedId.value = null
  try {
    await patchRuleValue(rule.id, rule.condition_value)

    // Each tier stores its own condition_value independently, but adjacent
    // tiers share a boundary number (e.g. Achats' "<5000" / ">=5000" pair):
    // rule i's own value is also rule (i+1)'s lower bound. Editing only
    // rule i left the pair mismatched — a gap or overlap between tiers.
    const idx = rules.value.findIndex((r) => r.id === rule.id)
    const next = rules.value[idx + 1]
    if (next) {
      next.condition_value = rule.condition_value
      await patchRuleValue(next.id, next.condition_value)
    }

    savedId.value = rule.id
  } finally {
    savingId.value = null
  }
}
</script>
