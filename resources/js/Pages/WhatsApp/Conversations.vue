<template>
  <AppLayout title="WhatsApp — Conversations">
    <div class="flex h-[calc(100vh-4rem)] overflow-hidden bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-xl shadow">

      <!-- Sidebar: conversation list -->
      <div class="w-80 flex flex-col border-r border-gray-200 dark:border-surface-700">
        <!-- Header -->
        <div class="p-4 border-b border-gray-200 dark:border-surface-700">
          <div class="flex items-center justify-between mb-3">
            <span class="text-lg font-semibold text-surface-900 dark:text-surface-50">Conversations</span>
            <Button icon="pi pi-filter" size="small" text />
          </div>
          <IconField>
            <InputIcon class="pi pi-search" />
            <InputText v-model="search" placeholder="Rechercher..." size="small" class="w-full" />
          </IconField>
        </div>

        <!-- Filters -->
        <div class="flex gap-1 p-2 border-b border-gray-100">
          <Button
            v-for="f in filters"
            :key="f.value"
            :label="f.label"
            size="small"
            :outlined="activeFilter !== f.value"
            @click="activeFilter = f.value"
            class="flex-1 text-xs"
          />
        </div>

        <!-- List -->
        <div class="flex-1 overflow-y-auto">
          <div
            v-for="conv in filteredConversations"
            :key="conv.id"
            @click="selectConversation(conv)"
            :class="[
              'flex items-start gap-3 p-3 cursor-pointer hover:bg-gray-50 dark:bg-surface-800 dark:bg-surface-800 border-b border-gray-100',
              selectedId === conv.id ? 'bg-green-50 dark:bg-green-900/20 border-l-4 border-l-green-500' : ''
            ]"
          >
            <Avatar :label="conv.contact_name[0]" class="bg-green-50 dark:bg-green-900/200 text-white shrink-0" />
            <div class="flex-1 min-w-0">
              <div class="flex justify-between items-center">
                <span class="font-medium text-sm text-surface-900 dark:text-surface-50 truncate">{{ conv.contact_name }}</span>
                <span class="text-xs text-surface-400 dark:text-surface-500 shrink-0 ml-1">{{ conv.last_message_at }}</span>
              </div>
              <p class="text-xs text-surface-500 dark:text-surface-400 truncate mt-0.5">{{ conv.last_message }}</p>
              <div class="flex items-center gap-1 mt-1">
                <Tag v-if="conv.unread_count > 0" :value="conv.unread_count" severity="success" class="text-xs" />
                <Tag :value="conv.status" :severity="statusSeverity(conv.status)" class="text-xs" />
              </div>
            </div>
          </div>
          <div v-if="filteredConversations.length === 0" class="p-6 text-center text-surface-400 dark:text-surface-500 text-sm">
            Aucune conversation
          </div>
        </div>
      </div>

      <!-- Main chat area -->
      <div class="flex-1 flex flex-col" v-if="selected">
        <!-- Chat header -->
        <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-surface-700 bg-gray-50 dark:bg-surface-800 dark:bg-surface-800">
          <div class="flex items-center gap-3">
            <Avatar :label="selected.contact_name[0]" class="bg-green-50 dark:bg-green-900/200 text-white" />
            <div>
              <p class="font-semibold text-surface-900 dark:text-surface-50">{{ selected.contact_name }}</p>
              <p class="text-xs text-surface-500 dark:text-surface-400">{{ selected.contact_phone }}</p>
            </div>
          </div>
          <div class="flex gap-2">
            <Button icon="pi pi-ticket" label="Créer ticket" size="small" outlined @click="createTicket" />
            <Button icon="pi pi-user-edit" label="Assigner" size="small" outlined @click="assignModal = true" />
            <Button icon="pi pi-check-circle" label="Résoudre" size="small" severity="success" @click="resolve" />
          </div>
        </div>

        <!-- Messages -->
        <div ref="messagesEl" class="flex-1 overflow-y-auto p-4 space-y-3 bg-[#e5ddd5]">
          <div
            v-for="msg in messages"
            :key="msg.id"
            :class="['flex', msg.direction === 'outbound' ? 'justify-end' : 'justify-start']"
          >
            <div
              :class="[
                'max-w-sm rounded-lg px-3 py-2 shadow-sm text-sm',
                msg.direction === 'outbound'
                  ? 'bg-[#dcf8c6] text-gray-800'
                  : 'bg-white dark:bg-surface-800 dark:bg-surface-800 text-gray-800 dark:text-surface-100'
              ]"
            >
              <p class="leading-relaxed">{{ msg.content }}</p>
              <div class="flex items-center justify-end gap-1 mt-1">
                <span class="text-xs text-surface-400 dark:text-surface-500">{{ msg.time }}</span>
                <i v-if="msg.direction === 'outbound'" class="pi pi-check-circle text-xs text-blue-400" />
              </div>
            </div>
          </div>
        </div>

        <!-- Quick replies -->
        <div class="px-4 py-2 bg-white dark:bg-surface-800 dark:bg-surface-800 border-t border-gray-100 flex gap-2 flex-wrap">
          <Button
            v-for="qr in quickReplies"
            :key="qr"
            :label="qr"
            size="small"
            outlined
            @click="messageInput = qr"
            class="text-xs"
          />
        </div>

        <!-- Input -->
        <div class="p-3 border-t border-gray-200 dark:border-surface-700 bg-white dark:bg-surface-800 dark:bg-surface-800 flex items-end gap-2">
          <Button icon="pi pi-paperclip" text rounded />
          <Button icon="pi pi-image" text rounded />
          <Textarea
            v-model="messageInput"
            rows="1"
            autoResize
            placeholder="Tapez un message..."
            class="flex-1"
            @keydown.enter.exact.prevent="sendMessage"
          />
          <Button icon="pi pi-send" rounded severity="success" @click="sendMessage" :disabled="!messageInput.trim()" />
        </div>
      </div>

      <!-- Empty state -->
      <div v-else class="flex-1 flex items-center justify-center bg-gray-50 dark:bg-surface-800 dark:bg-surface-800">
        <div class="text-center text-surface-400 dark:text-surface-500">
          <i class="pi pi-comments text-6xl mb-4 block opacity-30" />
          <p class="text-lg">Sélectionnez une conversation</p>
        </div>
      </div>
    </div>

    <!-- Assign Modal -->
    <Dialog v-model:visible="assignModal" header="Assigner la conversation" :style="{ width: '24rem' }" modal>
      <div class="space-y-3">
        <div>
          <label class="text-sm font-medium text-surface-700 dark:text-surface-300 block mb-1">Agent</label>
          <Dropdown v-model="assignee" :options="agents" optionLabel="name" optionValue="id" class="w-full" placeholder="Choisir un agent" />
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <Button label="Annuler" outlined @click="assignModal = false" />
          <Button label="Assigner" severity="success" @click="assign" />
        </div>
      </div>
    </Dialog>

    <!-- Opt-in / Opt-out stats panel -->
    <div class="optin-panel">
      <h3 class="font-semibold text-surface-700 dark:text-surface-300 mb-3">
        <i class="pi pi-user-plus text-green-500 mr-1" />
        Opt-in / Opt-out
      </h3>
      <div v-if="optinStats" class="optin-stats-grid">
        <div class="optin-stat">
          <p class="text-2xl font-bold text-green-700 dark:text-green-300">{{ optinStats.opted_in }}</p>
          <p class="text-xs text-surface-400 dark:text-surface-500">Abonnés actifs</p>
        </div>
        <div class="optin-stat">
          <p class="text-2xl font-bold text-red-500">{{ optinStats.opted_out }}</p>
          <p class="text-xs text-surface-400 dark:text-surface-500">Désabonnés</p>
        </div>
        <div class="optin-stat">
          <p class="text-2xl font-bold text-primary-700 dark:text-primary-300">{{ optinStats.opt_in_rate }}%</p>
          <p class="text-xs text-surface-400 dark:text-surface-500">Taux opt-in</p>
        </div>
      </div>
      <p v-else class="text-sm text-surface-400 dark:text-surface-500">Chargement des stats...</p>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, Avatar, Tag, InputText, Textarea, Dialog, Dropdown, IconField, InputIcon } from 'primevue'
import axios from 'axios'

const optinStats = ref(null)

async function loadOptinStats() {
  try {
    const { data } = await axios.get('/api/v1/whatsapp/optins/stats')
    optinStats.value = data
  } catch (_) { /* ignore */ }
}

let echoChannel: any = null

onMounted(() => {
  loadOptinStats()
  if (window.Echo) {
    echoChannel = window.Echo.private('whatsapp')
      .listen('.WaMessageReceived', (data: any) => {
        // Prepend or update the conversation in the list
        const idx = conversations.value.findIndex((c: any) => c.id === data.conversation_id)
        if (idx !== -1) {
          conversations.value[idx] = {
            ...conversations.value[idx],
            last_message: data.content,
            last_message_at: data.time,
            unread_count: (conversations.value[idx].unread_count ?? 0) + 1,
          }
          // Move to top
          const updated = conversations.value.splice(idx, 1)[0]
          conversations.value.unshift(updated)
        }
      })
  }
})

onUnmounted(() => {
  if (echoChannel) window.Echo?.leaveChannel('private-whatsapp')
})

const props = defineProps({ conversations: Array, agents: { type: Array, default: () => [] } })

const search = ref('')
const activeFilter = ref('all')
const selectedId = ref(null)
const selected = ref(null)
const messages = ref([])
const messageInput = ref('')
const assignModal = ref(false)
const assignee = ref(null)
const messagesEl = ref(null)

const filters = [
  { label: 'Tous', value: 'all' },
  { label: 'Ouverts', value: 'open' },
  { label: 'En attente', value: 'pending' },
]

const quickReplies = [
  'Bonjour, comment puis-je vous aider ?',
  'Je vérifie et reviens vers vous.',
  'Votre demande a bien été prise en compte.',
]

const conversations = ref(props.conversations ?? [
  { id: 1, contact_name: 'Marie Dupont', contact_phone: '+33612345678', last_message: 'Bonjour, je voudrais savoir...', last_message_at: '10:32', status: 'open', unread_count: 2 },
  { id: 2, contact_name: 'Ahmed Benali', contact_phone: '+33698765432', last_message: 'Merci pour votre réponse', last_message_at: '09:15', status: 'pending', unread_count: 0 },
  { id: 3, contact_name: 'Sophie Martin', contact_phone: '+33611223344', last_message: 'Ma commande est arrivée', last_message_at: 'Hier', status: 'resolved', unread_count: 0 },
])

const filteredConversations = computed(() => {
  let list = conversations.value
  if (activeFilter.value !== 'all') list = list.filter(c => c.status === activeFilter.value)
  if (search.value) list = list.filter(c => c.contact_name.toLowerCase().includes(search.value.toLowerCase()))
  return list
})

const statusSeverity = (status) => ({ open: 'success', pending: 'warn', resolved: 'secondary' }[status] || 'secondary')

function selectConversation(conv) {
  selectedId.value = conv.id
  selected.value = conv
  loadMessages(conv.id)
}

async function loadMessages(id) {
  messages.value = [
    { id: 1, content: 'Bonjour, je voudrais des informations sur ma commande #1234', direction: 'inbound', time: '10:30' },
    { id: 2, content: 'Bonjour ! Je consulte ça immédiatement.', direction: 'outbound', time: '10:31' },
    { id: 3, content: 'Votre commande est en cours de livraison, elle arrivera demain.', direction: 'outbound', time: '10:32' },
  ]
  await nextTick()
  if (messagesEl.value) messagesEl.value.scrollTop = messagesEl.value.scrollHeight
}

async function sendMessage() {
  if (!messageInput.value.trim() || !selected.value) return
  const content = messageInput.value.trim()
  messageInput.value = ''
  messages.value.push({ id: Date.now(), content, direction: 'outbound', time: new Date().toLocaleTimeString('fr', { hour: '2-digit', minute: '2-digit' }) })
  await nextTick()
  if (messagesEl.value) messagesEl.value.scrollTop = messagesEl.value.scrollHeight
  try {
    await axios.post(`/api/v1/whatsapp/conversations/${selected.value.id}/messages`, { content })
  } catch {}
}

function createTicket() {
  router.visit(`/helpdesk/tickets/create?wa_conversation=${selected.value?.id}`)
}

function resolve() {
  if (!selected.value) return
  selected.value.status = 'resolved'
  axios.patch(`/api/v1/whatsapp/conversations/${selected.value.id}`, { status: 'resolved' }).catch(() => {})
}

function assign() {
  if (!selected.value || !assignee.value) return
  axios.patch(`/api/v1/whatsapp/conversations/${selected.value.id}`, { assignee_id: assignee.value })
    .catch(() => {})
  assignModal.value = false
}
</script>
