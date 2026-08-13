<template>
  <div v-if="canView" class="space-y-6">
    <!-- Header -->
    <div class="flex items-start justify-between gap-4">
      <div class="flex items-start gap-3">
        <Button icon="pi pi-arrow-left" severity="secondary" text rounded @click="goBack" />
        <div>
          <div class="flex items-center gap-2 flex-wrap">
            <span class="font-mono text-sm text-blue-600 font-semibold">#{{ ticket.id }}</span>
            <h1 class="text-xl font-bold text-gray-900 dark:text-white">{{ ticket.titre }}</h1>
          </div>
          <div class="flex items-center gap-2 mt-1 flex-wrap">
            <Tag :value="ticket.statut" :severity="statutSeverity(ticket.statut)" />
            <Tag :value="ticket.priorite" :severity="prioriteSeverity(ticket.priorite)" />
            <span class="text-xs text-gray-500">SLA :</span>
            <span class="text-xs font-medium" :class="slaColor(ticket.slaRemaining)">
              <i class="pi pi-clock mr-1"></i>{{ ticket.slaRemaining }}
            </span>
          </div>
        </div>
      </div>
      <div class="flex gap-2 flex-shrink-0 flex-wrap justify-end">
        <Button label="Répondre" icon="pi pi-reply" size="small" @click="focusReply" />
        <Button label="Résoudre" icon="pi pi-check" size="small" severity="success" @click="resolveTicket" />
        <Button label="Escalader" icon="pi pi-arrow-up" size="small" severity="warn" @click="showEscalateDialog = true" />
        <Button label="Réassigner" icon="pi pi-user-edit" size="small" severity="secondary" outlined @click="showReassignDialog = true" />
      </div>
    </div>

    <!-- Main layout: conversation + metadata -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- LEFT: Conversation thread -->
      <div class="lg:col-span-2 space-y-4">
        <Card>
          <template #header>
            <div class="px-5 pt-5 pb-2 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
              <span class="font-semibold text-gray-700 dark:text-gray-200">Conversation</span>
              <span class="text-xs text-gray-400">{{ messages.length }} messages</span>
            </div>
          </template>
          <template #content>
            <div class="space-y-5 max-h-[520px] overflow-y-auto pr-1">
              <div
                v-for="msg in messages"
                :key="msg.id"
                class="flex gap-3"
                :class="msg.type === 'agent' ? 'flex-row-reverse' : ''"
              >
                <!-- Avatar -->
                <div
                  class="w-9 h-9 rounded-full flex items-center justify-center text-white text-sm font-bold flex-shrink-0"
                  :class="msg.type === 'agent' ? 'bg-blue-600' : 'bg-green-600'"
                >
                  {{ msg.author.charAt(0) }}
                </div>
                <!-- Bubble -->
                <div class="max-w-[75%]">
                  <div class="flex items-center gap-2 mb-1" :class="msg.type === 'agent' ? 'flex-row-reverse' : ''">
                    <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">{{ msg.author }}</span>
                    <span class="text-xs text-gray-400">{{ msg.time }}</span>
                    <span v-if="msg.type === 'agent'" class="text-xs bg-blue-100 text-blue-700 rounded px-1">Agent</span>
                  </div>
                  <div
                    class="rounded-2xl px-4 py-3 text-sm leading-relaxed"
                    :class="msg.type === 'agent'
                      ? 'bg-blue-600 text-white rounded-tr-sm'
                      : 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-100 rounded-tl-sm'"
                  >
                    {{ msg.content }}
                  </div>
                  <div v-if="msg.attachment" class="mt-1 flex items-center gap-1 text-xs text-blue-600 cursor-pointer hover:underline" :class="msg.type === 'agent' ? 'justify-end' : ''">
                    <i class="pi pi-paperclip"></i>
                    <span>{{ msg.attachment }}</span>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </Card>

        <!-- Internal Notes -->
        <Card v-if="canSeeInternalNotes">
          <template #header>
            <div class="px-5 pt-5 pb-2 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2">
              <i class="pi pi-lock text-orange-500 text-sm"></i>
              <span class="font-semibold text-orange-600 dark:text-orange-400">Notes internes (agents uniquement)</span>
            </div>
          </template>
          <template #content>
            <div class="space-y-3">
              <div
                v-for="note in internalNotes"
                :key="note.id"
                class="bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-lg p-3"
              >
                <div class="flex items-center justify-between mb-1">
                  <span class="text-xs font-semibold text-orange-700 dark:text-orange-300">{{ note.author }}</span>
                  <span class="text-xs text-gray-400">{{ note.time }}</span>
                </div>
                <p class="text-sm text-gray-700 dark:text-gray-300">{{ note.content }}</p>
              </div>
            </div>
          </template>
        </Card>

        <!-- Reply Box -->
        <Card ref="replyBox">
          <template #header>
            <div class="px-5 pt-5 pb-2 border-b border-gray-100 dark:border-gray-700">
              <span class="font-semibold text-gray-700 dark:text-gray-200">Répondre au client</span>
            </div>
          </template>
          <template #content>
            <div class="space-y-3">
              <Textarea
                v-model="replyText"
                rows="4"
                class="w-full"
                placeholder="Tapez votre réponse ici..."
                auto-resize
              />
              <div class="flex items-center justify-between flex-wrap gap-2">
                <div class="flex gap-2">
                  <Button icon="pi pi-paperclip" severity="secondary" text size="small" label="Pièce jointe" />
                  <Button icon="pi pi-bolt" severity="secondary" text size="small" label="Réponse rapide" @click="showQuickReply = !showQuickReply" />
                </div>
                <div class="flex gap-2">
                  <Button label="Note interne" icon="pi pi-lock" severity="warn" outlined size="small" @click="addInternalNote" />
                  <Button label="Envoyer" icon="pi pi-send" @click="sendReply" :disabled="!replyText.trim()" />
                </div>
              </div>
              <!-- Quick replies -->
              <div v-if="showQuickReply" class="flex flex-wrap gap-2 pt-2 border-t border-gray-100 dark:border-gray-700">
                <Button
                  v-for="qr in quickReplies"
                  :key="qr"
                  :label="qr"
                  size="small"
                  severity="secondary"
                  text
                  class="text-xs"
                  @click="replyText = qr"
                />
              </div>
            </div>
          </template>
        </Card>
      </div>

      <!-- RIGHT: Ticket metadata -->
      <div class="space-y-4">
        <!-- Ticket info -->
        <Card>
          <template #header>
            <div class="px-5 pt-5 pb-2 border-b border-gray-100 dark:border-gray-700">
              <span class="font-semibold text-gray-700 dark:text-gray-200">Informations</span>
            </div>
          </template>
          <template #content>
            <div class="space-y-3 text-sm">
              <div class="flex items-start justify-between">
                <span class="text-gray-500">Client</span>
                <div class="text-right">
                  <p class="font-medium text-gray-800 dark:text-gray-200">{{ ticket.client }}</p>
                  <p class="text-xs text-gray-400">{{ ticket.clientEmail }}</p>
                </div>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-gray-500">Canal</span>
                <div class="flex items-center gap-1">
                  <i :class="canalIcon(ticket.canal)" class="text-gray-500"></i>
                  <span class="font-medium text-gray-800 dark:text-gray-200">{{ ticket.canal }}</span>
                </div>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-gray-500">Agent assigné</span>
                <span class="font-medium text-gray-800 dark:text-gray-200">{{ ticket.agent }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-gray-500">Catégorie</span>
                <span class="font-medium text-gray-800 dark:text-gray-200">{{ ticket.categorie }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-gray-500">Créé le</span>
                <span class="text-gray-700 dark:text-gray-300">{{ ticket.createdAt }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-gray-500">Dernière activité</span>
                <span class="text-gray-700 dark:text-gray-300">{{ ticket.lastActivity }}</span>
              </div>
              <div>
                <span class="text-gray-500 block mb-1">Tags</span>
                <div class="flex flex-wrap gap-1">
                  <span
                    v-for="tag in ticket.tags"
                    :key="tag"
                    class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-full px-2 py-0.5"
                  >{{ tag }}</span>
                </div>
              </div>
            </div>
          </template>
        </Card>

        <!-- SLA Status -->
        <Card>
          <template #header>
            <div class="px-5 pt-5 pb-2 border-b border-gray-100 dark:border-gray-700">
              <span class="font-semibold text-gray-700 dark:text-gray-200">SLA</span>
            </div>
          </template>
          <template #content>
            <div class="space-y-3">
              <div>
                <div class="flex justify-between text-xs mb-1">
                  <span class="text-gray-500">Temps de réponse</span>
                  <span :class="slaColor(ticket.slaRemaining)">{{ ticket.slaRemaining }}</span>
                </div>
                <ProgressBar :value="slaProgress" :class="slaProgressClass" style="height: 6px" />
              </div>
              <div>
                <div class="flex justify-between text-xs mb-1">
                  <span class="text-gray-500">Résolution</span>
                  <span class="text-orange-500">16h restantes</span>
                </div>
                <ProgressBar :value="45" class="[&_.p-progressbar-value]:bg-orange-400" style="height: 6px" />
              </div>
            </div>
          </template>
        </Card>

        <!-- Quick actions -->
        <Card>
          <template #header>
            <div class="px-5 pt-5 pb-2 border-b border-gray-100 dark:border-gray-700">
              <span class="font-semibold text-gray-700 dark:text-gray-200">Actions rapides</span>
            </div>
          </template>
          <template #content>
            <div class="space-y-2">
              <Button label="Marquer résolu" icon="pi pi-check-circle" severity="success" class="w-full" @click="resolveTicket" />
              <Button label="Mettre en attente" icon="pi pi-pause" severity="warn" outlined class="w-full" @click="pauseTicket" />
              <Button label="Fusionner ticket" icon="pi pi-sitemap" severity="secondary" outlined class="w-full" />
              <Button label="Voir historique client" icon="pi pi-history" severity="secondary" text class="w-full" />
            </div>
          </template>
        </Card>
      </div>
    </div>

    <!-- Escalate Dialog -->
    <Dialog v-model:visible="showEscalateDialog" header="Escalader le ticket" :style="{ width: '440px' }" modal>
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Escalader vers</label>
          <Select v-model="escalateTo" :options="escaladeAgents" option-label="label" option-value="value" class="w-full" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Raison de l'escalade</label>
          <Textarea v-model="escaladeRaison" rows="3" class="w-full" placeholder="Pourquoi ce ticket doit être escaladé..." />
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" text @click="showEscalateDialog = false" />
        <Button label="Escalader" icon="pi pi-arrow-up" severity="warn" @click="escalateTicket" />
      </template>
    </Dialog>

    <!-- Reassign Dialog -->
    <Dialog v-model:visible="showReassignDialog" header="Réassigner le ticket" :style="{ width: '400px' }" modal>
      <div class="space-y-3">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nouvel agent</label>
        <Select v-model="reassignAgent" :options="agentOptions" option-label="label" option-value="value" class="w-full" />
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" text @click="showReassignDialog = false" />
        <Button label="Réassigner" icon="pi pi-user-edit" @click="doReassign" />
      </template>
    </Dialog>

    <!-- AI Assistant -->
    <AiAssistantPanel v-if="guidance" :guidance="guidance" />
  </div>
  <div v-else class="flex items-center justify-center h-48">
    <Message severity="warn">Vous n'avez pas accès à cette section.</Message>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import ProgressBar from 'primevue/progressbar'
import StrategicContext from '@/Components/StrategicContext.vue'
import { useStrategicLink } from '@/composables/useStrategicLink'
import AiAssistantPanel from '@/Components/AI/AiAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import { useRbac } from '@/composables/useRbac'

const { guidance } = useAiAssistant('CRM', 'view_dashboard')
const { isAdmin, canManage, canView } = useRbac('Helpdesk')

const page = usePage()
const auth = computed(() => page.props.auth)
const canSeeInternalNotes = computed(() => true) // agents only in real RBAC

const ticket = ref({
  id: '1041',
  titre: 'Erreur lors du paiement Orange Money',
  statut: 'En cours',
  priorite: 'Haute',
  slaRemaining: '3h 05min',
  client: 'Fatou Koné',
  clientEmail: 'fatou.kone@grandmarche.sn',
  canal: 'WhatsApp',
  agent: 'Ibrahim Traoré',
  categorie: 'Paiement / Mobile Money',
  createdAt: '24/05/2026 à 09:14',
  lastActivity: '24/05/2026 à 11:42',
  tags: ['Orange Money', 'Paiement', 'Mobile'],
})

const messages = ref([
  { id: 1, type: 'client', author: 'Fatou Koné', time: '09:14', content: 'Bonjour, je n\'arrive pas à finaliser mon paiement via Orange Money. La transaction échoue avec le code ERR_MOMOC02. Pouvez-vous m\'aider ? Merci.', attachment: null },
  { id: 2, type: 'agent', author: 'Ibrahim Traoré', time: '09:31', content: 'Bonjour Fatou, je suis désolé pour ce désagrément. Ce code d\'erreur correspond à un plafond de transaction journalier dépassé. Pouvez-vous vérifier votre solde et votre plafond dans l\'application Orange Money ?', attachment: null },
  { id: 3, type: 'client', author: 'Fatou Koné', time: '09:48', content: 'J\'ai vérifié, mon solde est de 75 000 XOF et le plafond n\'est pas atteint. Le problème persiste toujours.', attachment: 'screenshot_erreur.png' },
  { id: 4, type: 'agent', author: 'Ibrahim Traoré', time: '10:15', content: 'Merci pour la capture d\'écran. J\'escalade ce problème à notre équipe technique. En attendant, pouvez-vous essayer avec Wave comme mode de paiement alternatif ?', attachment: null },
  { id: 5, type: 'client', author: 'Fatou Koné', time: '11:42', content: 'Wave fonctionne, j\'ai pu régler ma commande. Mais le problème Orange Money persiste sur tous mes appareils.', attachment: null },
])

const internalNotes = ref([
  { id: 1, author: 'Ibrahim Traoré', time: '10:20', content: 'BUG possible côté gateway Orange Money Sénégal. Contacter partenaire API. Ticket technique ouvert #TEC-0089.' },
  { id: 2, author: 'Seydou Ouédraogo (Superviseur)', time: '11:00', content: 'Confirmé : incident Orange Money API détecté depuis 08h30. Mise à jour Orange prévue à 14h. Informer la cliente.' },
])

const replyText = ref('')
const showQuickReply = ref(false)
const showEscalateDialog = ref(false)
const showReassignDialog = ref(false)
const escalateTo = ref(null)
const escaladeRaison = ref('')
const reassignAgent = ref(null)

const quickReplies = [
  'Merci pour votre patience, nous traitons votre demande.',
  'Pouvez-vous nous fournir plus de détails ?',
  'Le problème a été identifié et est en cours de résolution.',
  'Votre ticket a été transmis à notre équipe technique.',
]

const agentOptions = [
  { label: 'Aïssatou Bâ', value: 'Aïssatou Bâ' },
  { label: 'Ibrahim Traoré', value: 'Ibrahim Traoré' },
  { label: 'Seydou Ouédraogo', value: 'Seydou Ouédraogo' },
]

const escaladeAgents = [
  { label: 'Seydou Ouédraogo — Superviseur', value: 'Seydou Ouédraogo' },
  { label: 'Aminata Diallo — Responsable technique', value: 'Aminata Diallo' },
]

const slaProgress = computed(() => {
  const sla = ticket.value.slaRemaining
  if (sla === '—') return 100
  const h = parseFloat(sla)
  if (h < 1) return 90
  if (h < 2) return 70
  if (h < 4) return 45
  return 20
})

const slaProgressClass = computed(() => {
  if (slaProgress.value >= 80) return '[&_.p-progressbar-value]:bg-red-500'
  if (slaProgress.value >= 60) return '[&_.p-progressbar-value]:bg-orange-400'
  return '[&_.p-progressbar-value]:bg-green-500'
})

const statutSeverity = (s: string) => {
  const map: Record<string, string> = { Nouveau: 'info', 'En cours': 'warn', 'En attente client': 'secondary', Résolu: 'success', Fermé: 'secondary' }
  return map[s] ?? 'info'
}

const prioriteSeverity = (p: string) => {
  const map: Record<string, string> = { Critique: 'danger', Haute: 'warn', Normale: 'info', Basse: 'secondary' }
  return map[p] ?? 'info'
}

const slaColor = (sla: string) => {
  if (sla === '—') return 'text-gray-400'
  const h = parseFloat(sla)
  if (h < 1) return 'text-red-600 font-bold'
  if (h < 2) return 'text-red-500'
  if (h < 4) return 'text-orange-500'
  return 'text-green-600'
}

const canalIcon = (canal: string) => {
  const map: Record<string, string> = { Email: 'pi pi-envelope', Chat: 'pi pi-comments', Téléphone: 'pi pi-phone', WhatsApp: 'pi pi-whatsapp' }
  return map[canal] ?? 'pi pi-inbox'
}

const goBack = () => { window.history.back() }

const focusReply = () => {
  document.querySelector('textarea')?.focus()
}

const sendReply = () => {
  if (!replyText.value.trim()) return
  messages.value.push({
    id: messages.value.length + 1,
    type: 'agent',
    author: 'Ibrahim Traoré',
    time: new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }),
    content: replyText.value,
    attachment: null,
  })
  replyText.value = ''
}

const addInternalNote = () => {
  if (!replyText.value.trim()) return
  internalNotes.value.push({
    id: internalNotes.value.length + 1,
    author: 'Ibrahim Traoré',
    time: new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }),
    content: replyText.value,
  })
  replyText.value = ''
}

const resolveTicket = () => {
  ticket.value.statut = 'Résolu'
  ticket.value.slaRemaining = '—'
}

const pauseTicket = () => {
  ticket.value.statut = 'En attente client'
}

const escalateTicket = () => {
  showEscalateDialog.value = false
  ticket.value.agent = escalateTo.value ?? ticket.value.agent
  escalateTo.value = null
  escaladeRaison.value = ''
}

const doReassign = () => {
  if (reassignAgent.value) ticket.value.agent = reassignAgent.value
  showReassignDialog.value = false
  reassignAgent.value = null
}
</script>
