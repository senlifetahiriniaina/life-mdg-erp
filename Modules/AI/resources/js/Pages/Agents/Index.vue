<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Agents IA Autonomes</h1>
        <p class="text-surface-500 text-sm mt-1">Des agents Claude qui agissent de manière autonome sur vos données et processus métier</p>
      </div>
      <Button label="Déployer un agent" icon="pi pi-plus" @click="showCreateDialog = true" v-if="canManage" />
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <Card v-for="s in stats" :key="s.label"><template #content>
        <div class="text-2xl font-bold" :class="s.color">{{ s.value }}</div>
        <div class="text-sm text-surface-500 mt-1">{{ s.label }}</div>
      </template></Card>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <Card v-for="agent in agents" :key="agent.id" class="cursor-pointer hover:shadow-md" @click="selectedAgent = agent; showLogsDrawer = true">
        <template #header>
          <div class="px-4 pt-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="text-2xl">{{ agent.icon }}</span>
              <div>
                <div class="font-semibold">{{ agent.name }}</div>
                <div class="text-xs text-surface-400">{{ agent.model }}</div>
              </div>
            </div>
            <div class="flex items-center gap-1">
              <Tag :value="agent.status" :severity="{ 'En cours': 'warn', 'Inactif': 'secondary', 'Erreur': 'danger', 'Standby': 'info' }[agent.status]" size="small" />
              <ToggleSwitch :modelValue="agent.enabled" @change="agent.enabled = !agent.enabled" />
            </div>
          </div>
        </template>
        <template #content>
          <p class="text-sm text-surface-600 mb-3">{{ agent.description }}</p>
          <div class="grid grid-cols-3 gap-2 text-xs text-center">
            <div class="bg-surface-50 rounded p-2"><div class="text-surface-400">Tâches</div><div class="font-bold">{{ agent.tasks }}</div></div>
            <div class="bg-surface-50 rounded p-2"><div class="text-surface-400">Succès</div><div class="font-bold text-green-600">{{ agent.successRate }}%</div></div>
            <div class="bg-surface-50 rounded p-2"><div class="text-surface-400">Tokens</div><div class="font-bold">{{ agent.tokens }}</div></div>
          </div>
          <div class="mt-2 flex flex-wrap gap-1">
            <Tag v-for="cap in agent.capabilities" :key="cap" :value="cap" severity="secondary" size="small" />
          </div>
        </template>
      </Card>
    </div>

    <Dialog v-model:visible="showCreateDialog" header="Déployer un agent IA" :style="{ width: '550px' }" modal>
      <div class="space-y-4">
        <div><label class="text-sm font-medium">Nom de l'agent</label><InputText v-model="newAgent.name" class="w-full mt-1" /></div>
        <div><label class="text-sm font-medium">Domaine métier</label>
          <Select v-model="newAgent.domain" :options="['CRM & Ventes', 'Comptabilité', 'RH & Paie', 'Stocks & Logistique', 'Support client', 'Marketing', 'Finance & Reporting']" class="w-full mt-1" /></div>
        <div><label class="text-sm font-medium">Objectif de l'agent</label>
          <Textarea v-model="newAgent.objective" rows="3" class="w-full mt-1" placeholder="Décrivez ce que l'agent doit accomplir de manière autonome..." /></div>
        <div><label class="text-sm font-medium">Modèle Claude</label>
          <Select v-model="newAgent.model" :options="['claude-sonnet-4-6 (recommandé)', 'claude-opus-4-7 (plus puissant)', 'claude-haiku-4-5 (plus rapide)']" class="w-full mt-1" /></div>
        <div>
          <label class="text-sm font-medium">Outils disponibles</label>
          <div class="mt-2 grid grid-cols-2 gap-2">
            <div v-for="tool in availableTools" :key="tool" class="flex items-center gap-2">
              <ToggleSwitch v-model="newAgent.tools[tool]" />
              <span class="text-xs">{{ tool }}</span>
            </div>
          </div>
        </div>
        <div class="p-3 bg-yellow-50 border border-yellow-200 rounded text-xs text-yellow-700">
          ⚠️ Les agents autonomes peuvent créer, modifier et envoyer des données. Définissez des limites claires dans l'objectif.
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" text @click="showCreateDialog = false" />
        <Button label="Déployer l'agent" icon="pi pi-send" @click="showCreateDialog = false" />
      </template>
    </Dialog>

    <Drawer v-model:visible="showLogsDrawer" :header="selectedAgent?.name + ' — Activité récente'" position="right" :style="{ width: '500px' }">
      <div v-if="selectedAgent" class="space-y-4 text-sm">
        <div class="p-3 bg-surface-50 rounded space-y-1">
          <div><strong>Modèle:</strong> {{ selectedAgent.model }}</div>
          <div><strong>Tâches réalisées:</strong> {{ selectedAgent.tasks }}</div>
          <div><strong>Taux de succès:</strong> {{ selectedAgent.successRate }}%</div>
        </div>
        <div>
          <div class="font-medium mb-2">Journal des actions</div>
          <div class="space-y-3">
            <div v-for="log in agentLogs" :key="log.id" class="p-3 border rounded">
              <div class="flex justify-between text-xs text-surface-400 mb-1"><span>{{ log.ts }}</span><Tag :value="log.status" :severity="{ Succès: 'success', Erreur: 'danger', 'En cours': 'warn' }[log.status]" size="small" /></div>
              <div class="font-medium text-xs mb-1">{{ log.task }}</div>
              <div class="text-xs text-surface-500">{{ log.detail }}</div>
            </div>
          </div>
        </div>
      </div>
    </Drawer>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import Drawer from 'primevue/drawer'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'

const page = usePage()
const { isElevated } = useRoleAccess()
const canManage = computed(() => isElevated.value)

const showCreateDialog = ref(false)
const showLogsDrawer = ref(false)
const selectedAgent = ref(null)
const availableTools = ['Lire/écrire CRM', 'Lire/écrire Inventaire', 'Créer des rapports', 'Envoyer emails', 'Envoyer WhatsApp', 'Créer des tâches', 'Lire Comptabilité', 'Accès Helpdesk']
const newAgent = ref({ name: '', domain: null, objective: '', model: 'claude-sonnet-4-6 (recommandé)', tools: {} })

const stats = [
  { label: 'Agents déployés', value: '6', color: 'text-blue-600' },
  { label: 'Tâches autonomes/jour', value: '284', color: 'text-green-600' },
  { label: 'Tokens utilisés (mois)', value: '12.4M', color: 'text-purple-600' },
  { label: 'Taux de succès global', value: '94.8%', color: 'text-orange-600' },
]

const agents = ref([
  { id: 1, name: 'Agent Qualification CRM', icon: '🎯', model: 'claude-sonnet-4-6', status: 'Standby', enabled: true, tasks: 1420, successRate: 96.2, tokens: '2.1M', description: 'Analyse les nouveaux leads, enrichit les données, score et assigne automatiquement aux bons commerciaux.', capabilities: ['Lire CRM', 'Écrire CRM', 'Email'] },
  { id: 2, name: 'Agent Comptabilité OHADA', icon: '📊', model: 'claude-sonnet-4-6', status: 'En cours', enabled: true, tasks: 845, successRate: 98.1, tokens: '3.8M', description: 'Catégorise les transactions, détecte les anomalies et prépare les rapports SYSCOHADA automatiquement.', capabilities: ['Lire Compta', 'Écrire Compta', 'Rapports'] },
  { id: 3, name: 'Agent Support Tier-1', icon: '💬', model: 'claude-haiku-4-5', status: 'En cours', enabled: true, tasks: 3280, successRate: 88.4, tokens: '5.2M', description: 'Répond aux questions fréquentes du Helpdesk, crée des tickets, escalade les cas complexes.', capabilities: ['Helpdesk', 'Email', 'WhatsApp'] },
  { id: 4, name: 'Agent Prévision Stocks', icon: '📦', model: 'claude-sonnet-4-6', status: 'Standby', enabled: true, tasks: 284, successRate: 91.5, tokens: '0.8M', description: 'Analyse les tendances de vente et génère automatiquement les recommandations de réapprovisionnement.', capabilities: ['Lire Inventaire', 'Créer DA', 'Rapports'] },
  { id: 5, name: 'Agent Relance Commerciale', icon: '📧', model: 'claude-sonnet-4-6', status: 'Inactif', enabled: false, tasks: 142, successRate: 72.5, tokens: '0.4M', description: 'Envoie des emails personnalisés aux leads dormants basés sur leur historique d\'engagement.', capabilities: ['Email', 'Lire CRM'] },
  { id: 6, name: 'Agent Audit Qualité', icon: '✅', model: 'claude-opus-4-7', status: 'Erreur', enabled: true, tasks: 38, successRate: 84.2, tokens: '1.1M', description: 'Analyse les processus opérationnels et identifie les écarts ISO 9001 avec recommandations.', capabilities: ['Lire Qualité', 'Créer CAPA', 'Rapports'] },
])

const agentLogs = [
  { id: 1, ts: '14:30:05', task: 'Qualification lead — Fatimata Traoré (MTN)', detail: 'Score: 78/100. Assigné à: Pierre Dubois. Email de prise de contact envoyé.', status: 'Succès' },
  { id: 2, ts: '14:15:22', task: 'Enrichissement données — 12 nouveaux leads', detail: 'Entreprises identifiées, effectifs récupérés via LinkedIn API. Secteurs classifiés.', status: 'Succès' },
  { id: 3, ts: '14:02:18', task: 'Catégorisation 45 transactions bancaires', detail: 'Transactions OFX importées. 42/45 catégorisées automatiquement. 3 nécessitent validation humaine.', status: 'En cours' },
]
</script>
