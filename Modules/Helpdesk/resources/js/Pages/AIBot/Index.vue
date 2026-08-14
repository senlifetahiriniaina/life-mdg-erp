<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Chatbot IA & Réponses Automatiques</h1>
        <p class="text-surface-500 text-sm mt-1">Configurez votre assistant virtuel et suivez ses performances</p>
      </div>
      <div class="flex items-center gap-3">
        <span class="text-sm text-surface-500">Bot actif</span>
        <ToggleSwitch v-model="botEnabled" />
      </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <Card v-for="s in stats" :key="s.label"><template #content>
        <div class="text-2xl font-bold" :class="s.color">{{ s.value }}</div>
        <div class="text-sm text-surface-500 mt-1">{{ s.label }}</div>
      </template></Card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <Card>
        <template #header><div class="px-4 pt-4 font-semibold">Configuration du chatbot</div></template>
        <template #content>
          <div class="space-y-4">
            <div><label class="text-sm font-medium">Message de bienvenue</label>
              <Textarea v-model="botConfig.welcome" rows="2" class="w-full mt-1" /></div>
            <div><label class="text-sm font-medium">Message de repli (si bot incertain)</label>
              <Textarea v-model="botConfig.fallback" rows="2" class="w-full mt-1" /></div>
            <div><label class="text-sm font-medium">Seuil de confiance pour escalade (%)</label>
              <Slider v-model="botConfig.threshold" :min="40" :max="90" class="mt-3" />
              <div class="text-right text-sm text-surface-500 mt-1">{{ botConfig.threshold }}% — En dessous: transfert à un agent humain</div>
            </div>
            <div class="space-y-2">
              <label class="text-sm font-medium">Sources de la base de connaissances</label>
              <div v-for="kb in kbSources" :key="kb.name" class="flex items-center justify-between py-2 border-b border-surface-100">
                <div class="text-sm">{{ kb.name }}<span class="text-surface-400 ml-2">({{ kb.articles }} articles)</span></div>
                <ToggleSwitch v-model="kb.active" />
              </div>
            </div>
            <Button label="Sauvegarder la configuration" icon="pi pi-save" class="w-full" />
          </div>
        </template>
      </Card>

      <Card>
        <template #header><div class="px-4 pt-4 font-semibold">Performance (7 derniers jours)</div></template>
        <template #content>
          <DataTable :value="dailyPerf" size="small" stripedRows>
            <Column field="date" header="Date" />
            <Column field="conversations" header="Conversations" />
            <Column field="resolved" header="Résolues bot" />
            <Column field="deflection" header="Déviation %"><template #body="{ data }"><span :class="data.deflection >= 60 ? 'text-green-600 font-semibold' : 'text-orange-500'">{{ data.deflection }}%</span></template></Column>
            <Column field="csat" header="CSAT" />
          </DataTable>
        </template>
      </Card>
    </div>

    <Card>
      <template #header><div class="px-4 pt-4 font-semibold">Conversations récentes</div></template>
      <template #content>
        <DataTable :value="conversations" stripedRows selectionMode="single" v-model:selection="selectedConv" @row-select="showConvDialog = true">
          <Column field="client" header="Client" />
          <Column field="topic" header="Sujet détecté" />
          <Column field="confidence" header="Confiance %"><template #body="{ data }"><span :class="data.confidence >= 70 ? 'text-green-600' : 'text-orange-500'">{{ data.confidence }}%</span></template></Column>
          <Column field="resolved" header="Résolu bot"><template #body="{ data }"><Tag :value="data.resolved ? 'Oui' : 'Non'" :severity="data.resolved ? 'success' : 'warn'" size="small" /></template></Column>
          <Column field="escalated" header="Escaladé"><template #body="{ data }"><Tag v-if="data.escalated" value="Oui" severity="danger" size="small" /><span v-else class="text-surface-400 text-sm">—</span></template></Column>
          <Column field="duration" header="Durée" />
        </DataTable>
      </template>
    </Card>

    <Dialog v-model:visible="showConvDialog" :header="'Conversation — ' + selectedConv?.client" :style="{ width: '500px' }" modal>
      <div v-if="selectedConv" class="space-y-3">
        <div v-for="msg in selectedConv.messages || []" :key="msg.text" class="flex" :class="msg.from === 'bot' ? 'justify-start' : 'justify-end'">
          <div class="max-w-xs px-3 py-2 rounded-lg text-sm" :class="msg.from === 'bot' ? 'bg-blue-50 text-blue-800' : 'bg-surface-100'">
            <div class="text-xs text-surface-400 mb-1">{{ msg.from === 'bot' ? '🤖 Bot' : '👤 Client' }}</div>
            {{ msg.text }}
          </div>
        </div>
        <div v-if="selectedConv.escalated" class="p-2 bg-orange-50 border border-orange-200 rounded text-sm text-orange-700">
          🔄 Escaladé à l'agent {{ selectedConv.agent }} à {{ selectedConv.escalatedAt }}
        </div>
      </div>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed} from 'vue'
import { usePage } from '@inertiajs/vue3'
import Card from 'primevue/card'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import Textarea from 'primevue/textarea'
import Slider from 'primevue/slider'
import ToggleSwitch from 'primevue/toggleswitch'

const page = usePage()
const { isAdmin, isElevated, hasAnyRole } = useRoleAccess()
const canManage = computed(() => isElevated.value || hasAnyRole(['customer-service']))
const canCreate = computed(() => canManage.value)
const canEdit = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)


const botEnabled = ref(true)
const showConvDialog = ref(false)
const selectedConv = ref(null)

const botConfig = ref({ welcome: 'Bonjour ! Je suis l\'assistant WideHalo. Comment puis-je vous aider ?', fallback: 'Je ne suis pas sûr de comprendre. Je vous mets en relation avec un agent.', threshold: 65 })

const stats = [
  { label: 'Tickets auto-résolus', value: '68%', color: 'text-green-600' },
  { label: 'Conversations bot (7j)', value: '247', color: 'text-blue-600' },
  { label: 'Taux de déviation', value: '61%', color: 'text-purple-600' },
  { label: 'Score CSAT bot', value: '4.2/5', color: 'text-orange-600' },
]

const kbSources = ref([
  { name: 'Guide utilisateur WideHalo', articles: 142, active: true },
  { name: 'FAQ Facturation', articles: 34, active: true },
  { name: 'Procédures de retour', articles: 18, active: true },
])

const dailyPerf = [
  { date: '18 mai', conversations: 32, resolved: 21, deflection: 66, csat: '4.3/5' },
  { date: '19 mai', conversations: 28, resolved: 17, deflection: 61, csat: '4.1/5' },
  { date: '20 mai', conversations: 35, resolved: 25, deflection: 71, csat: '4.5/5' },
  { date: '21 mai', conversations: 41, resolved: 24, deflection: 59, csat: '3.9/5' },
  { date: '22 mai', conversations: 29, resolved: 18, deflection: 62, csat: '4.2/5' },
  { date: '23 mai', conversations: 38, resolved: 23, deflection: 61, csat: '4.0/5' },
  { date: '24 mai', conversations: 44, resolved: 31, deflection: 70, csat: '4.4/5' },
]

const conversations = ref([
  { client: 'Amadou Diallo', topic: 'Statut de livraison', confidence: 92, resolved: true, escalated: false, duration: '1m 20s', messages: [{ from: 'client', text: 'Où en est ma commande #4521?' }, { from: 'bot', text: 'Votre commande #4521 est en transit. Livraison estimée le 26 mai.' }, { from: 'client', text: 'Merci !' }] },
  { client: 'Fatou Mbaye', topic: 'Remboursement', confidence: 58, resolved: false, escalated: true, agent: 'Sophie M.', escalatedAt: '14:32', duration: '4m 15s', messages: [{ from: 'client', text: 'Je veux un remboursement pour ma commande.' }, { from: 'bot', text: 'Je vais vous transférer à un agent spécialisé.' }] },
  { client: 'Kofi Asante', topic: 'Réinitialisation mot de passe', confidence: 95, resolved: true, escalated: false, duration: '0m 45s' },
  { client: 'Moussa Traoré', topic: 'Changement d\'adresse', confidence: 88, resolved: true, escalated: false, duration: '2m 10s' },
  { client: 'Awa Koné', topic: 'Produit défectueux', confidence: 45, resolved: false, escalated: true, agent: 'Jean P.', escalatedAt: '11:05', duration: '6m 30s' },
])
</script>
