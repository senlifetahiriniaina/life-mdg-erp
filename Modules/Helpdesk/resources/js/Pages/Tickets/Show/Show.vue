<template>
  <div v-if="canView" class="space-y-6">
    <!-- Header -->
    <div class="flex items-start justify-between gap-4">
      <div class="flex items-start gap-3">
        <Button icon="pi pi-arrow-left" severity="secondary" text rounded @click="goBack" />
        <div>
          <div class="flex items-center gap-2 flex-wrap">
            <span class="font-mono text-sm text-blue-600 font-semibold">#{{ ticket.ticket_number || ticket.id }}</span>
            <h1 class="text-xl font-bold text-gray-900 dark:text-white">{{ ticket.subject }}</h1>
          </div>
          <div class="flex items-center gap-2 mt-1 flex-wrap">
            <Tag :value="statusLabel(ticket.status)" :severity="statusSeverity(ticket.status)" />
            <Tag :value="priorityLabel(ticket.priority)" :severity="prioritySeverity(ticket.priority)" />
            <span v-if="ticket.sla_due_at" class="text-xs text-gray-500">SLA :</span>
            <span v-if="ticket.sla_due_at" class="text-xs font-medium" :class="slaColor">
              <i class="pi pi-clock mr-1"></i>{{ slaRemainingLabel }}
            </span>
          </div>
        </div>
      </div>
      <div class="flex gap-2 flex-shrink-0 flex-wrap justify-end">
        <Button label="Répondre" icon="pi pi-reply" size="small" @click="focusReply" />
        <Button v-if="ticket.status !== 'resolved' && ticket.status !== 'closed'" label="Résoudre" icon="pi pi-check" size="small" severity="success" :loading="actionPending" @click="resolveTicket" />
        <Button v-if="ticket.status !== 'closed'" label="Escalader" icon="pi pi-arrow-up" size="small" severity="warn" @click="showEscalateDialog = true" />
        <Button label="Réassigner" icon="pi pi-user-edit" size="small" severity="secondary" outlined @click="showReassignDialog = true" />
      </div>
    </div>

    <!-- Ticket lifecycle -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
      <WorkflowStepper :steps="lifecycleSteps" :current-step="ticket.status" :show-actions="false" :show-details="false" />
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
              <p v-if="!messages.length" class="text-sm text-gray-400 text-center py-4">Aucun message pour l'instant.</p>
              <div
                v-for="msg in messages"
                :key="msg.id"
                class="flex gap-3"
                :class="isAgentComment(msg) ? 'flex-row-reverse' : ''"
              >
                <!-- Avatar -->
                <div
                  class="w-9 h-9 rounded-full flex items-center justify-center text-white text-sm font-bold flex-shrink-0"
                  :class="isAgentComment(msg) ? 'bg-blue-600' : 'bg-green-600'"
                >
                  {{ (msg.user?.name || '?').charAt(0) }}
                </div>
                <!-- Bubble -->
                <div class="max-w-[75%]">
                  <div class="flex items-center gap-2 mb-1" :class="isAgentComment(msg) ? 'flex-row-reverse' : ''">
                    <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">{{ msg.user?.name || 'Inconnu' }}</span>
                    <span class="text-xs text-gray-400">{{ formatTime(msg.created_at) }}</span>
                    <span v-if="isAgentComment(msg)" class="text-xs bg-blue-100 text-blue-700 rounded px-1">Agent</span>
                  </div>
                  <div
                    class="rounded-2xl px-4 py-3 text-sm leading-relaxed"
                    :class="isAgentComment(msg)
                      ? 'bg-blue-600 text-white rounded-tr-sm'
                      : 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-100 rounded-tl-sm'"
                  >
                    {{ msg.content }}
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
              <p v-if="!internalNotes.length" class="text-sm text-gray-400">Aucune note interne.</p>
              <div
                v-for="note in internalNotes"
                :key="note.id"
                class="bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-lg p-3"
              >
                <div class="flex items-center justify-between mb-1">
                  <span class="text-xs font-semibold text-orange-700 dark:text-orange-300">{{ note.user?.name || 'Inconnu' }}</span>
                  <span class="text-xs text-gray-400">{{ formatTime(note.created_at) }}</span>
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
                  <Button icon="pi pi-bolt" severity="secondary" text size="small" label="Réponse rapide" @click="showQuickReply = !showQuickReply" />
                </div>
                <div class="flex gap-2">
                  <Button label="Note interne" icon="pi pi-lock" severity="warn" outlined size="small" :disabled="commentPending" @click="addInternalNote" />
                  <Button label="Envoyer" icon="pi pi-send" :disabled="!replyText.trim() || commentPending" @click="sendReply" />
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
              <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
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
                <span class="text-gray-500">Demandeur</span>
                <div class="text-right">
                  <p class="font-medium text-gray-800 dark:text-gray-200">{{ ticket.reporter?.name || '—' }}</p>
                  <p class="text-xs text-gray-400">{{ ticket.reporter?.email || '' }}</p>
                </div>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-gray-500">Canal</span>
                <div class="flex items-center gap-1">
                  <i :class="channelIcon(ticket.channel)" class="text-gray-500"></i>
                  <span class="font-medium text-gray-800 dark:text-gray-200">{{ ticket.channel || '—' }}</span>
                </div>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-gray-500">Agent assigné</span>
                <span class="font-medium text-gray-800 dark:text-gray-200">{{ ticket.assignee?.name || 'Non assigné' }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-gray-500">Équipe</span>
                <span class="font-medium text-gray-800 dark:text-gray-200">{{ ticket.team?.name || '—' }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="text-gray-500">Créé le</span>
                <span class="text-gray-700 dark:text-gray-300">{{ formatDate(ticket.created_at) }}</span>
              </div>
              <div v-if="ticket.description">
                <span class="text-gray-500 block mb-1">Description</span>
                <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ ticket.description }}</p>
              </div>
            </div>
          </template>
        </Card>

        <!-- SLA Status -->
        <Card v-if="ticket.sla_due_at">
          <template #header>
            <div class="px-5 pt-5 pb-2 border-b border-gray-100 dark:border-gray-700">
              <span class="font-semibold text-gray-700 dark:text-gray-200">SLA</span>
            </div>
          </template>
          <template #content>
            <div class="space-y-3">
              <div>
                <div class="flex justify-between text-xs mb-1">
                  <span class="text-gray-500">Échéance</span>
                  <span :class="slaColor">{{ slaRemainingLabel }}</span>
                </div>
                <ProgressBar :value="slaProgress" :class="slaProgressClass" style="height: 6px" />
              </div>
              <p v-if="ticket.sla_breached" class="text-xs text-red-600 font-medium">
                <i class="pi pi-exclamation-triangle mr-1"></i>SLA dépassé
              </p>
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
              <Button v-if="ticket.status !== 'resolved' && ticket.status !== 'closed'" label="Marquer résolu" icon="pi pi-check-circle" severity="success" class="w-full" :loading="actionPending" @click="resolveTicket" />
              <Button v-if="ticket.status === 'resolved'" label="Clôturer" icon="pi pi-lock" severity="secondary" class="w-full" :loading="actionPending" @click="closeTicket" />
            </div>
          </template>
        </Card>
      </div>
    </div>

    <!-- Escalate Dialog -->
    <Dialog v-model:visible="showEscalateDialog" header="Escalader le ticket" :style="{ width: '440px' }" modal>
      <div class="space-y-4">
        <p class="text-sm text-gray-600 dark:text-gray-400">
          Escalader marque le ticket en priorité urgente et le signale comme ayant dépassé son SLA.
        </p>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Raison de l'escalade</label>
          <Textarea v-model="escaladeRaison" rows="3" class="w-full" placeholder="Pourquoi ce ticket doit être escaladé (enregistré comme note interne)..." />
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" text @click="showEscalateDialog = false" />
        <Button label="Escalader" icon="pi pi-arrow-up" severity="warn" :loading="actionPending" @click="escalateTicket" />
      </template>
    </Dialog>

    <!-- Reassign Dialog -->
    <Dialog v-model:visible="showReassignDialog" header="Réassigner le ticket" :style="{ width: '400px' }" modal @show="loadTeamMembers">
      <div class="space-y-3">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nouvel agent</label>
        <Select
          v-model="reassignAgentId"
          :options="teamMembers"
          option-label="name"
          option-value="id"
          :loading="loadingMembers"
          placeholder="Sélectionner un agent"
          class="w-full"
        />
        <p v-if="!loadingMembers && !teamMembers.length" class="text-xs text-gray-400">
          Aucun membre dans l'équipe assignée à ce ticket.
        </p>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" text @click="showReassignDialog = false" />
        <Button label="Réassigner" icon="pi pi-user-edit" :loading="actionPending" :disabled="!reassignAgentId" @click="doReassign" />
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
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import ProgressBar from 'primevue/progressbar'
import Message from 'primevue/message'
import WorkflowStepper from '@/Components/UI/WorkflowStepper.vue'
import AiAssistantPanel from '@/Components/AI/AiAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import { useRbac } from '@/composables/useRbac'

const props = defineProps({
  ticket: { type: Object, required: true },
})

const { guidance } = useAiAssistant('CRM', 'view_dashboard')
const { canView } = useRbac('Helpdesk')

const canSeeInternalNotes = computed(() => true) // agents only in real RBAC

const lifecycleSteps = [
  { key: 'open', label: 'Ouvert', icon: 'pi pi-inbox' },
  { key: 'in_progress', label: 'En cours', icon: 'pi pi-spin pi-cog' },
  { key: 'resolved', label: 'Résolu', icon: 'pi pi-check' },
  { key: 'closed', label: 'Clôturé', icon: 'pi pi-lock' },
]

const messages = computed(() => (props.ticket.comments || []).filter(c => !c.is_internal))
const internalNotes = computed(() => (props.ticket.comments || []).filter(c => c.is_internal))
const isAgentComment = (comment) => comment.user_id && comment.user_id !== props.ticket.reporter_id

const replyText = ref('')
const showQuickReply = ref(false)
const showEscalateDialog = ref(false)
const showReassignDialog = ref(false)
const reassignAgentId = ref(null)
const teamMembers = ref([])
const loadingMembers = ref(false)
const escaladeRaison = ref('')
const error = ref('')
const actionPending = ref(false)
const commentPending = ref(false)

const quickReplies = [
  'Merci pour votre patience, nous traitons votre demande.',
  'Pouvez-vous nous fournir plus de détails ?',
  'Le problème a été identifié et est en cours de résolution.',
  'Votre ticket a été transmis à notre équipe technique.',
]

const slaProgress = computed(() => {
  if (!props.ticket.sla_due_at) return 0
  if (props.ticket.sla_breached) return 100
  const remainingMs = new Date(props.ticket.sla_due_at).getTime() - Date.now()
  const hoursLeft = remainingMs / (1000 * 60 * 60)
  if (hoursLeft < 1) return 90
  if (hoursLeft < 2) return 70
  if (hoursLeft < 4) return 45
  return 20
})

const slaProgressClass = computed(() => {
  if (slaProgress.value >= 80) return '[&_.p-progressbar-value]:bg-red-500'
  if (slaProgress.value >= 60) return '[&_.p-progressbar-value]:bg-orange-400'
  return '[&_.p-progressbar-value]:bg-green-500'
})

const slaColor = computed(() => {
  if (props.ticket.sla_breached) return 'text-red-600 font-bold'
  if (slaProgress.value >= 80) return 'text-red-500'
  if (slaProgress.value >= 60) return 'text-orange-500'
  return 'text-green-600'
})

const slaRemainingLabel = computed(() => {
  if (!props.ticket.sla_due_at) return '—'
  if (props.ticket.sla_breached) return 'Dépassé'
  const remainingMs = new Date(props.ticket.sla_due_at).getTime() - Date.now()
  if (remainingMs <= 0) return 'Dépassé'
  const hours = Math.floor(remainingMs / (1000 * 60 * 60))
  const minutes = Math.floor((remainingMs % (1000 * 60 * 60)) / (1000 * 60))
  return `${hours}h ${minutes}min`
})

const statusLabel = (s) => ({ open: 'Ouvert', in_progress: 'En cours', resolved: 'Résolu', closed: 'Clôturé' })[s] ?? (s || '—')
const statusSeverity = (s) => ({ open: 'info', in_progress: 'warn', resolved: 'success', closed: 'secondary' })[s] ?? 'info'
const priorityLabel = (p) => ({ low: 'Basse', medium: 'Normale', high: 'Haute', urgent: 'Critique' })[p] ?? (p || '—')
const prioritySeverity = (p) => ({ urgent: 'danger', high: 'warn', medium: 'info', low: 'secondary' })[p] ?? 'info'

const channelIcon = (channel) => ({
  email: 'pi pi-envelope', whatsapp: 'pi pi-whatsapp', phone: 'pi pi-phone', web: 'pi pi-globe',
})[channel] ?? 'pi pi-inbox'

const formatDate = (date) => date
  ? new Date(date).toLocaleDateString('fr-FR', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
  : '—'

const formatTime = (date) => date
  ? new Date(date).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
  : ''

const goBack = () => { window.history.back() }

const focusReply = () => {
  document.querySelector('textarea')?.focus()
}

const postComment = async (isInternal) => {
  if (!replyText.value.trim()) return
  commentPending.value = true
  error.value = ''
  try {
    await axios.post(`/api/v1/helpdesk/tickets/${props.ticket.id}/comments`, {
      content: replyText.value,
      is_internal: isInternal,
    })
    replyText.value = ''
    router.reload({ only: ['ticket'] })
  } catch (err) {
    error.value = err.response?.data?.message || "Échec de l'envoi."
  } finally {
    commentPending.value = false
  }
}

const sendReply = () => postComment(false)
const addInternalNote = () => postComment(true)

const resolveTicket = async () => {
  actionPending.value = true
  error.value = ''
  try {
    await axios.post(`/api/v1/helpdesk/tickets/${props.ticket.id}/resolve`)
    router.reload({ only: ['ticket'] })
  } catch (err) {
    error.value = err.response?.data?.message || 'Échec de la résolution.'
  } finally {
    actionPending.value = false
  }
}

const closeTicket = async () => {
  actionPending.value = true
  error.value = ''
  try {
    await axios.post(`/api/v1/helpdesk/tickets/${props.ticket.id}/close`)
    router.reload({ only: ['ticket'] })
  } catch (err) {
    error.value = err.response?.data?.message || 'Échec de la clôture.'
  } finally {
    actionPending.value = false
  }
}

const escalateTicket = async () => {
  actionPending.value = true
  error.value = ''
  try {
    if (escaladeRaison.value.trim()) {
      await axios.post(`/api/v1/helpdesk/tickets/${props.ticket.id}/comments`, {
        content: `Escalade : ${escaladeRaison.value}`,
        is_internal: true,
      })
    }
    await axios.post(`/api/v1/helpdesk/tickets/${props.ticket.id}/escalate`)
    showEscalateDialog.value = false
    escaladeRaison.value = ''
    router.reload({ only: ['ticket'] })
  } catch (err) {
    error.value = err.response?.data?.message || "Échec de l'escalade."
  } finally {
    actionPending.value = false
  }
}

const loadTeamMembers = async () => {
  const teamId = props.ticket.team?.id
  if (!teamId) {
    teamMembers.value = []
    return
  }
  loadingMembers.value = true
  try {
    const { data } = await axios.get(`/api/v1/helpdesk/teams/${teamId}`)
    teamMembers.value = data.members || []
  } catch {
    teamMembers.value = []
  } finally {
    loadingMembers.value = false
  }
}

const doReassign = async () => {
  if (!reassignAgentId.value) return
  actionPending.value = true
  error.value = ''
  try {
    await axios.post(`/api/v1/helpdesk/tickets/${props.ticket.id}/assign`, {
      assignee_id: reassignAgentId.value,
    })
    showReassignDialog.value = false
    reassignAgentId.value = null
    router.reload({ only: ['ticket'] })
  } catch (err) {
    error.value = err.response?.data?.message || 'Échec de la réassignation.'
  } finally {
    actionPending.value = false
  }
}
</script>
