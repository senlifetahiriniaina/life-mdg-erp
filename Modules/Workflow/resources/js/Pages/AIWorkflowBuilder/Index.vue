<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Constructeur de Workflow IA (No-Code)</h1>
        <p class="text-surface-500 text-sm mt-1">Décrivez votre processus en français, l'IA construit le workflow automatiquement</p>
      </div>
      <div class="flex gap-2">
        <Button label="Nouveau workflow" icon="pi pi-plus" @click="showCreateDialog = true" v-if="canManage" />
      </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <Card v-for="s in stats" :key="s.label"><template #content>
        <div class="text-2xl font-bold" :class="s.color">{{ s.value }}</div>
        <div class="text-sm text-surface-500 mt-1">{{ s.label }}</div>
      </template></Card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <Card>
        <template #header><div class="px-4 pt-4 font-semibold">Workflows actifs</div></template>
        <template #content>
          <div v-for="wf in workflows" :key="wf.id" class="flex items-center justify-between py-3 border-b border-surface-100 last:border-0 cursor-pointer hover:bg-surface-50 px-2 rounded" @click="selectedWorkflow = wf" role="button" tabindex="0" @keydown.enter.prevent="selectedWorkflow = wf">
            <div>
              <div class="font-medium text-sm">{{ wf.name }}</div>
              <div class="text-xs text-surface-400">{{ wf.trigger }} · {{ wf.runs }} exécutions</div>
            </div>
            <div class="flex items-center gap-2">
              <Tag :value="wf.status" :severity="wf.status === 'Actif' ? 'success' : 'secondary'" size="small" />
              <ToggleSwitch :modelValue="wf.status === 'Actif'" @change="wf.status = wf.status === 'Actif' ? 'Inactif' : 'Actif'" />
            </div>
          </div>
        </template>
      </Card>

      <Card class="lg:col-span-2">
        <template #header>
          <div class="px-4 pt-4 flex items-center justify-between">
            <span class="font-semibold">{{ selectedWorkflow ? selectedWorkflow.name + ' — Visualisation' : 'Sélectionnez un workflow' }}</span>
            <div v-if="selectedWorkflow" class="flex gap-2">
              <Button label="Tester" icon="pi pi-play" size="small" outlined />
              <Button label="Activer" icon="pi pi-check" size="small" @click="selectedWorkflow.status = 'Actif'" />
            </div>
          </div>
        </template>
        <template #content>
          <div v-if="!selectedWorkflow" class="text-center text-surface-400 py-12">Sélectionnez un workflow pour le visualiser</div>
          <div v-else class="space-y-2">
            <div v-for="(step, idx) in selectedWorkflow.steps" :key="idx" class="flex gap-3">
              <div class="flex flex-col items-center">
                <div class="w-9 h-9 rounded-full flex items-center justify-center text-white text-sm font-bold" :class="stepBgClass(step.type)">
                  {{ stepIcon(step.type) }}
                </div>
                <div v-if="idx < selectedWorkflow.steps.length - 1" class="w-0.5 h-6 bg-surface-300 mt-1"></div>
              </div>
              <div class="flex-1 pb-3">
                <div class="flex items-center gap-2 mb-1">
                  <span class="text-xs font-bold uppercase" :class="'text-' + stepTextColor(step.type)">{{ step.type }}</span>
                </div>
                <div class="p-3 bg-surface-50 rounded border text-sm">{{ step.content }}</div>
                <div v-if="step.condition" class="mt-1 text-xs text-orange-600 font-medium">Condition: {{ step.condition }}</div>
              </div>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <Dialog v-model:visible="showCreateDialog" header="Créer un workflow IA" :style="{ width: '550px' }" modal>
      <div class="space-y-4">
        <div class="p-3 bg-blue-50 border border-blue-200 rounded text-sm">
          💡 <strong>Mode IA:</strong> Décrivez votre processus en français, l'IA propose les étapes automatiquement.
        </div>
        <div><label class="text-sm font-medium">Décrivez votre workflow</label>
          <Textarea v-model="newWf.description" rows="4" class="w-full mt-1" placeholder="Ex: Quand une nouvelle commande est reçue, vérifier le stock, notifier l'entrepôt, et envoyer une confirmation au client par WhatsApp et email..." /></div>
        <Button label="Générer le workflow avec l'IA" icon="pi pi-sparkles" class="w-full" :loading="generatingWf" @click="generateWorkflow" />
        <div v-if="generatedSteps.length > 0">
          <label class="text-sm font-medium">Workflow généré ({{ generatedSteps.length }} étapes)</label>
          <div class="mt-2 space-y-2">
            <div v-for="(step, i) in generatedSteps" :key="i" class="p-2 bg-surface-50 rounded border text-sm flex items-start gap-2">
              <div class="w-5 h-5 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0" :class="stepBgClass(step.type)">{{ i + 1 }}</div>
              <div><span class="font-medium text-xs uppercase" :class="'text-' + stepTextColor(step.type)">{{ step.type }}</span><br>{{ step.content }}</div>
            </div>
          </div>
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" text @click="showCreateDialog = false; generatedSteps = []" />
        <Button v-if="generatedSteps.length > 0" label="Créer ce workflow" icon="pi pi-check" @click="createWorkflow" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import Textarea from 'primevue/textarea'
import ToggleSwitch from 'primevue/toggleswitch'

const page = usePage()
const roles = computed(() => page.props.auth?.user?.roles?.map(r => r.name) || [])
const canManage = computed(() => roles.value.some(r => ['workflow-admin','admin','super-admin'].includes(r)))

const showCreateDialog = ref(false)
const selectedWorkflow = ref(null)
const generatingWf = ref(false)
const generatedSteps = ref([])
const newWf = ref({ description: '' })

const stats = [
  { label: 'Workflows actifs', value: '12', color: 'text-green-600' },
  { label: 'Exécutions/jour', value: '384', color: 'text-blue-600' },
  { label: 'Taux de succès', value: '98.8%', color: 'text-purple-600' },
  { label: 'Temps moyen', value: '1.4s', color: 'text-orange-600' },
]

const workflows = ref([
  {
    id: 1, name: 'Validation commande B2B', trigger: 'Nouvelle commande > 500k XOF', status: 'Actif', runs: 284,
    steps: [
      { type: 'Déclencheur', content: 'Commande B2B reçue avec montant > 500 000 XOF' },
      { type: 'Condition', content: 'Client KYC vérifié ?', condition: 'Oui → Étape 3 / Non → Notification compliance' },
      { type: 'Approbation', content: 'Notifier Directeur Commercial pour validation' },
      { type: 'Délai', content: 'Attendre approbation (max 4h)' },
      { type: 'Action', content: 'Confirmer la commande + Créer BL entrepôt + Email client' },
    ]
  },
  {
    id: 2, name: 'Onboarding nouveau client', trigger: 'Création compte client', status: 'Actif', runs: 142,
    steps: [
      { type: 'Déclencheur', content: 'Nouveau compte client créé dans le CRM' },
      { type: 'Action', content: 'Email de bienvenue + accès portail client' },
      { type: 'Délai', content: 'Attendre 24h' },
      { type: 'Condition', content: '1ère connexion effectuée ?', condition: 'Non → Relance SMS' },
      { type: 'Action', content: 'Assigner un commercial référent + Créer opportunité CRM' },
    ]
  },
  {
    id: 3, name: 'Alerte rupture stock', trigger: 'Stock < seuil minimum', status: 'Actif', runs: 890,
    steps: [
      { type: 'Déclencheur', content: 'Quantité stock < seuil de réappro' },
      { type: 'Action', content: 'Créer demande d\'achat automatique (DA)' },
      { type: 'Action', content: 'Notifier responsable achats par email + WhatsApp' },
      { type: 'Condition', content: 'Fournisseur préféré disponible ?', condition: 'Non → Rechercher alternatif' },
      { type: 'Action', content: 'Créer et envoyer bon de commande fournisseur' },
    ]
  },
])

const stepBgClass = (type) => ({ Déclencheur: 'bg-purple-500', Condition: 'bg-orange-500', Action: 'bg-blue-500', Délai: 'bg-gray-500', Approbation: 'bg-green-600', Webhook: 'bg-red-500' }[type] || 'bg-surface-400')
const stepTextColor = (type) => ({ Déclencheur: 'purple-600', Condition: 'orange-600', Action: 'blue-600', Délai: 'gray-600', Approbation: 'green-700', Webhook: 'red-600' }[type] || 'surface-600')
const stepIcon = (type) => ({ Déclencheur: '⚡', Condition: '?', Action: '▶', Délai: '⏱', Approbation: '✓', Webhook: '🔗' }[type] || '•')

const generateWorkflow = async () => {
  generatingWf.value = true
  await new Promise(r => setTimeout(r, 2000))
  generatedSteps.value = [
    { type: 'Déclencheur', content: 'Nouvelle commande reçue dans le système' },
    { type: 'Condition', content: 'Vérifier disponibilité stock pour tous les articles' },
    { type: 'Action', content: 'Notifier l\'entrepôt avec liste de préparation' },
    { type: 'Action', content: 'Envoyer confirmation client par email + WhatsApp' },
    { type: 'Délai', content: 'Attendre confirmation préparation entrepôt' },
    { type: 'Webhook', content: 'Mettre à jour le statut commande → En préparation' },
  ]
  generatingWf.value = false
}

const createWorkflow = () => {
  workflows.value.push({ id: workflows.value.length + 1, name: 'Nouveau workflow', trigger: 'Personnalisé', status: 'Brouillon', runs: 0, steps: generatedSteps.value })
  generatedSteps.value = []
  showCreateDialog.value = false
}
</script>
