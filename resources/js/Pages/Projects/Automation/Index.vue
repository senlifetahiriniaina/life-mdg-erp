<template>
  <AppLayout>
    <Head title="Automatisations" />

    <AIAssistantPanel v-if="guidance" :guidance="guidance" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Automatisations</h1>
        <p class="wh-page-subtitle">Règles de workflow automatiques</p>
      </div>
      <div class="page-actions">
        <Button
          label="✨ Créer règle IA"
          icon="pi pi-sparkles"
          severity="help"
          @click="openAiDialog"
        />
        <Button
          icon="pi pi-plus"
          label="Nouvelle règle"
          @click="openCreateRule"
        />
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="wh-panel" style="padding:40px;text-align:center;color:var(--fg-3)">
      <i class="pi pi-spin pi-spinner" style="font-size:20px" />
    </div>

    <!-- Empty state -->
    <div v-else-if="!rules.length" class="wh-panel" style="padding:48px;text-align:center;color:var(--fg-3)">
      <p style="font-size:40px;margin-bottom:8px">⚡</p>
      <p>Aucune règle d'automatisation. Créez votre première règle pour automatiser votre workflow.</p>
    </div>

    <!-- Rules list -->
    <div v-else class="wh-panel">
      <DataTable :value="rules" :rows="50" size="small">
        <template #empty>
          <span style="color:var(--fg-3)">Aucune règle.</span>
        </template>

        <Column field="name" header="Nom" style="min-width:180px" />

        <Column field="trigger" header="Déclencheur">
          <template #body="{ data }">
            <Tag :value="triggerLabel(data.trigger)" severity="info" />
          </template>
        </Column>

        <Column header="Conditions">
          <template #body="{ data }">
            <span v-if="!data.conditions?.length" style="color:var(--fg-3)">Aucune</span>
            <span v-else style="font-size:12px">{{ data.conditions.length }} condition(s)</span>
          </template>
        </Column>

        <Column header="Actions">
          <template #body="{ data }">
            <span style="font-size:12px">{{ data.actions?.length ?? 0 }} action(s)</span>
          </template>
        </Column>

        <Column header="Actif" style="width:80px">
          <template #body="{ data }">
            <button
              :class="['toggle-btn', data.active ? 'toggle-on' : 'toggle-off']"
              @click="toggleActive(data)"
              :title="data.active ? 'Désactiver' : 'Activer'"
            >
              <span class="toggle-knob" />
            </button>
          </template>
        </Column>

        <Column header="" style="width:100px">
          <template #body="{ data }">
            <div style="display:flex;gap:4px">
              <Button icon="pi pi-pencil" text rounded size="small" @click="editRule(data)" />
              <Button icon="pi pi-trash" text rounded size="small" severity="danger" @click="deleteRule(data)" />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- Dialog: Create / Edit Rule -->
    <Dialog
      v-model:visible="ruleDialogVisible"
      :header="editingRule ? 'Modifier la règle' : 'Nouvelle règle'"
      :style="{ width: '560px' }"
      modal
    >
      <div style="display:flex;flex-direction:column;gap:14px">
        <div>
          <label class="block text-sm font-medium mb-1">Nom de la règle *</label>
          <InputText v-model="ruleForm.name" class="w-full" placeholder="Ex : Assigner auto à la création" />
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Déclencheur *</label>
          <Dropdown
            v-model="ruleForm.trigger"
            :options="triggerOptions"
            optionLabel="label"
            optionValue="value"
            class="w-full"
            placeholder="Sélectionner un déclencheur"
          />
        </div>

        <div>
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
            <label class="block text-sm font-medium">Conditions</label>
            <Button icon="pi pi-plus" text size="small" label="Ajouter" @click="addCondition" />
          </div>
          <div v-for="(cond, idx) in ruleForm.conditions" :key="idx" style="display:flex;gap:8px;margin-bottom:6px;align-items:center">
            <InputText v-model="cond.field"    placeholder="champ"    style="flex:1" size="small" />
            <Dropdown  v-model="cond.operator" :options="operatorOptions" optionLabel="label" optionValue="value" style="width:90px" size="small" />
            <InputText v-model="cond.value"    placeholder="valeur"   style="flex:1" size="small" />
            <Button icon="pi pi-times" text rounded size="small" severity="danger" @click="removeCondition(idx)" />
          </div>
          <p v-if="!ruleForm.conditions.length" style="font-size:12px;color:var(--fg-3)">Aucune condition (règle s'applique toujours).</p>
        </div>

        <div>
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
            <label class="block text-sm font-medium">Actions *</label>
            <Button icon="pi pi-plus" text size="small" label="Ajouter" @click="addAction" />
          </div>
          <div v-for="(act, idx) in ruleForm.actions" :key="idx" style="display:flex;gap:8px;margin-bottom:6px;align-items:center">
            <Dropdown
              v-model="act.type"
              :options="actionTypeOptions"
              optionLabel="label"
              optionValue="value"
              style="width:160px"
              size="small"
            />
            <InputText v-model="act.value" placeholder="valeur" style="flex:1" size="small" />
            <Button icon="pi pi-times" text rounded size="small" severity="danger" @click="removeAction(idx)" />
          </div>
          <p v-if="!ruleForm.actions.length" style="font-size:12px;color:var(--fg-3)">Au moins 1 action requise.</p>
        </div>

        <div style="display:flex;align-items:center;gap:8px">
          <input type="checkbox" id="ruleActive" v-model="ruleForm.active" />
          <label for="ruleActive" style="font-size:14px;cursor:pointer">Activer immédiatement</label>
        </div>
      </div>

      <template #footer>
        <Button label="Annuler" text @click="ruleDialogVisible = false" />
        <Button label="Enregistrer" icon="pi pi-check" :loading="saving" :disabled="!ruleForm.actions.length" @click="saveRule" />
      </template>
    </Dialog>

    <!-- Dialog: AI Rule Generation -->
    <Dialog
      v-model:visible="aiDialogVisible"
      header="✨ Créer une règle avec l'IA"
      :style="{ width: '480px' }"
      modal
    >
      <div style="display:flex;flex-direction:column;gap:12px">
        <p style="font-size:13px;color:var(--fg-3)">
          Décrivez en langage naturel ce que vous souhaitez automatiser et l'IA génèrera la règle.
        </p>
        <Textarea
          v-model="aiPrompt"
          rows="4"
          class="w-full"
          placeholder="Ex : Quand une tâche est créée avec la priorité haute, l'assigner automatiquement à l'utilisateur 5 et changer le statut en in_progress."
        />
      </div>

      <template #footer>
        <Button label="Annuler" text @click="aiDialogVisible = false" />
        <Button label="Générer" icon="pi pi-sparkles" severity="help" :loading="aiGenerating" @click="generateAiRule" />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import { Button, DataTable, Column, Dialog, Tag, InputText, Textarea, Dropdown } from 'primevue'
import axios from 'axios'

interface Condition {
  field: string
  operator: string
  value: string
}

interface Action {
  type: string
  value: string
}

interface RuleItem {
  id: number
  name: string
  trigger: string
  conditions: Condition[]
  actions: Action[]
  active: boolean
}

const props = defineProps<{ projectId?: number | string }>()
const { guidance } = useAiAssistant('Projects', 'view_automation')

const loading      = ref(true)
const saving       = ref(false)
const aiGenerating = ref(false)

const rules              = ref<RuleItem[]>([])
const ruleDialogVisible  = ref(false)
const aiDialogVisible    = ref(false)
const editingRule        = ref<RuleItem | null>(null)
const aiPrompt           = ref('')

const ruleForm = ref<{
  name: string
  trigger: string
  conditions: Condition[]
  actions: Action[]
  active: boolean
}>({
  name:       '',
  trigger:    '',
  conditions: [],
  actions:    [],
  active:     true,
})

const triggerOptions = [
  { label: 'Tâche créée',               value: 'task_created'        },
  { label: 'Statut de tâche changé',    value: 'task_status_changed' },
  { label: 'Tâche assignée',            value: 'task_assigned'       },
  { label: 'Commentaire ajouté',        value: 'comment_added'       },
]

const operatorOptions = [
  { label: '=',        value: '='        },
  { label: '!=',       value: '!='       },
  { label: 'contient', value: 'contains' },
  { label: 'dans',     value: 'in'       },
]

const actionTypeOptions = [
  { label: 'Assigner utilisateur', value: 'assign_user'        },
  { label: 'Changer statut',       value: 'change_status'      },
  { label: 'Ajouter étiquette',    value: 'add_label'          },
  { label: 'Notifier',             value: 'send_notification'  },
]

function triggerLabel(trigger: string): string {
  return triggerOptions.find(t => t.value === trigger)?.label ?? trigger
}

async function fetchRules(): Promise<void> {
  loading.value = true
  try {
    const res = await axios.get(`/api/v1/projects/${props.projectId}/automations`)
    rules.value = res.data?.data ?? []
  } catch { rules.value = [] } finally { loading.value = false }
}

function openCreateRule(): void {
  editingRule.value = null
  ruleForm.value = { name: '', trigger: '', conditions: [], actions: [], active: true }
  ruleDialogVisible.value = true
}

function editRule(rule: RuleItem): void {
  editingRule.value = rule
  ruleForm.value = {
    name:       rule.name,
    trigger:    rule.trigger,
    conditions: rule.conditions.map(c => ({ ...c })),
    actions:    rule.actions.map(a => ({ ...a })),
    active:     rule.active,
  }
  ruleDialogVisible.value = true
}

function addCondition(): void {
  ruleForm.value.conditions.push({ field: '', operator: '=', value: '' })
}
function removeCondition(idx: number): void {
  ruleForm.value.conditions.splice(idx, 1)
}
function addAction(): void {
  ruleForm.value.actions.push({ type: 'change_status', value: '' })
}
function removeAction(idx: number): void {
  ruleForm.value.actions.splice(idx, 1)
}

async function saveRule(): Promise<void> {
  if (!ruleForm.value.name.trim() || !ruleForm.value.trigger || !ruleForm.value.actions.length) return
  saving.value = true
  try {
    const payload = {
      ...ruleForm.value,
      conditions: ruleForm.value.conditions.filter(c => c.field.trim()),
    }
    if (editingRule.value) {
      await axios.put(`/api/v1/projects/${props.projectId}/automations/${editingRule.value.id}`, payload)
    } else {
      await axios.post(`/api/v1/projects/${props.projectId}/automations`, payload)
    }
    ruleDialogVisible.value = false
    await fetchRules()
  } finally { saving.value = false }
}

async function toggleActive(rule: RuleItem): Promise<void> {
  await axios.put(`/api/v1/projects/${props.projectId}/automations/${rule.id}`, { active: !rule.active })
  await fetchRules()
}

async function deleteRule(rule: RuleItem): Promise<void> {
  if (!confirm(`Supprimer la règle "${rule.name}" ?`)) return
  await axios.delete(`/api/v1/projects/${props.projectId}/automations/${rule.id}`)
  await fetchRules()
}

function openAiDialog(): void {
  aiPrompt.value = ''
  aiDialogVisible.value = true
}

async function generateAiRule(): Promise<void> {
  if (!aiPrompt.value.trim()) return
  aiGenerating.value = true
  try {
    const res = await axios.post('/api/v1/projects/ai/estimate-task', {
      project_id: props.projectId,
      context:    `Generate an automation rule from this description: ${aiPrompt.value}`,
    })
    const suggestion = res.data?.suggestion ?? null
    if (suggestion) {
      // Pre-fill form with AI suggestion if parseable
      alert(`Suggestion IA :\n\n${suggestion}\n\nVeuillez créer la règle manuellement en utilisant ces informations.`)
    } else {
      alert('Le service IA n\'a pas pu générer de règle. Essayez une description plus précise.')
    }
    aiDialogVisible.value = false
  } catch {
    alert('Service IA indisponible.')
  } finally { aiGenerating.value = false }
}

onMounted(fetchRules)
</script>

<style scoped>
.toggle-btn {
  position: relative;
  width: 40px;
  height: 22px;
  border-radius: 11px;
  border: none;
  cursor: pointer;
  transition: background .2s;
  display: flex;
  align-items: center;
  padding: 2px;
}
.toggle-on  { background: #6366f1; }
.toggle-off { background: #d1d5db; }
.toggle-knob {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 1px 3px rgba(0,0,0,.2);
  transition: transform .2s;
}
.toggle-on  .toggle-knob { transform: translateX(18px); }
.toggle-off .toggle-knob { transform: translateX(0); }
</style>
