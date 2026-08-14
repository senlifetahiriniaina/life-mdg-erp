<template>
  <div class="space-y-4">
    <!-- Header + agent status -->
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Chat en direct</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Conversations en temps réel avec les visiteurs</p>
      </div>
      <div class="flex items-center gap-3">
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Statut :</span>
        <Select
          v-model="agentStatus"
          :options="agentStatusOptions"
          option-label="label"
          option-value="value"
          class="w-44"
        >
          <template #value="{ value }">
            <div class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full" :class="agentStatusColor(value)"></span>
              <span>{{ agentStatusOptions.find(o => o.value === value)?.label }}</span>
            </div>
          </template>
          <template #option="{ option }">
            <div class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-full" :class="agentStatusColor(option.value)"></span>
              <span>{{ option.label }}</span>
            </div>
          </template>
        </Select>
      </div>
    </div>

    <!-- Stats bar -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <Card class="border-l-4 border-blue-500">
        <template #content>
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center">
              <i class="pi pi-comments text-blue-600"></i>
            </div>
            <div>
              <p class="text-xs text-gray-500">Chats actifs</p>
              <p class="text-xl font-bold text-gray-900 dark:text-white">{{ stats.actifs }}</p>
            </div>
          </div>
        </template>
      </Card>
      <Card class="border-l-4 border-yellow-500">
        <template #content>
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-full bg-yellow-100 flex items-center justify-center">
              <i class="pi pi-clock text-yellow-600"></i>
            </div>
            <div>
              <p class="text-xs text-gray-500">Attente moyenne</p>
              <p class="text-xl font-bold text-gray-900 dark:text-white">{{ stats.attenteMin }}min</p>
            </div>
          </div>
        </template>
      </Card>
      <Card class="border-l-4 border-green-500">
        <template #content>
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-full bg-green-100 flex items-center justify-center">
              <i class="pi pi-star text-green-600"></i>
            </div>
            <div>
              <p class="text-xs text-gray-500">Satisfaction</p>
              <p class="text-xl font-bold text-gray-900 dark:text-white">{{ stats.satisfaction }}%</p>
            </div>
          </div>
        </template>
      </Card>
      <Card class="border-l-4 border-purple-500">
        <template #content>
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-full bg-purple-100 flex items-center justify-center">
              <i class="pi pi-check-circle text-purple-600"></i>
            </div>
            <div>
              <p class="text-xs text-gray-500">Fermés aujourd'hui</p>
              <p class="text-xl font-bold text-gray-900 dark:text-white">{{ stats.fermes }}</p>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <!-- Main chat interface -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-0 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden" style="height: 600px;">
      <!-- Left: Chat list -->
      <div class="border-r border-gray-200 dark:border-gray-700 flex flex-col bg-white dark:bg-gray-800">
        <!-- Search -->
        <div class="p-3 border-b border-gray-200 dark:border-gray-700">
          <InputText v-model="chatSearch" placeholder="Rechercher..." class="w-full text-sm" />
        </div>
        <!-- Filter tabs -->
        <div class="flex border-b border-gray-200 dark:border-gray-700">
          <button
            v-for="tab in chatTabs"
            :key="tab.value"
            class="flex-1 text-xs py-2 font-medium transition-colors"
            :class="activeTab === tab.value
              ? 'border-b-2 border-blue-600 text-blue-600'
              : 'text-gray-500 hover:text-gray-700'"
            @click="activeTab = tab.value"
          >
            {{ tab.label }}
            <span
              v-if="tab.count > 0"
              class="ml-1 bg-red-500 text-white rounded-full px-1.5 py-0.5 text-xs"
            >{{ tab.count }}</span>
          </button>
        </div>
        <!-- Chat list -->
        <div class="flex-1 overflow-y-auto">
          <div
            v-for="chat in filteredChats"
            :key="chat.id"
            class="p-3 border-b border-gray-100 dark:border-gray-700 cursor-pointer transition-colors"
            :class="selectedChat?.id === chat.id
              ? 'bg-blue-50 dark:bg-blue-900/20'
              : 'hover:bg-gray-50 dark:hover:bg-gray-700/50'"
            @click="selectChat(chat)"
           role="button" tabindex="0" @keydown.enter.prevent="selectChat(chat)">
            <div class="flex items-start justify-between gap-2">
              <div class="flex items-center gap-2 min-w-0">
                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                  {{ chat.visitor.charAt(0) }}
                </div>
                <div class="min-w-0">
                  <p class="text-sm font-semibold text-gray-800 dark:text-gray-200 truncate">{{ chat.visitor }}</p>
                  <p class="text-xs text-gray-400 truncate">{{ chat.page }}</p>
                </div>
              </div>
              <div class="flex flex-col items-end gap-1 flex-shrink-0">
                <span class="text-xs" :class="statusColor(chat.statut)">{{ chat.statut }}</span>
                <span class="text-xs text-gray-400">{{ chat.waitTime }}</span>
              </div>
            </div>
            <p class="text-xs text-gray-500 mt-1 truncate">{{ chat.lastMessage }}</p>
          </div>
        </div>
      </div>

      <!-- Right: Conversation panel -->
      <div class="lg:col-span-2 flex flex-col bg-gray-50 dark:bg-gray-900">
        <!-- No chat selected -->
        <div v-if="!selectedChat" class="flex-1 flex items-center justify-center text-gray-400">
          <div class="text-center">
            <i class="pi pi-comments text-5xl mb-3 block"></i>
            <p class="text-sm">Sélectionnez une conversation</p>
          </div>
        </div>

        <template v-else>
          <!-- Chat header -->
          <div class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center text-white text-sm font-bold">
                {{ selectedChat.visitor.charAt(0) }}
              </div>
              <div>
                <p class="font-semibold text-gray-800 dark:text-gray-200 text-sm">{{ selectedChat.visitor }}</p>
                <p class="text-xs text-gray-400">
                  <i class="pi pi-globe mr-1"></i>{{ selectedChat.page }} &bull; {{ selectedChat.country }}
                </p>
              </div>
            </div>
            <div class="flex gap-2">
              <Button icon="pi pi-user-plus" severity="secondary" text size="small" v-tooltip="'Réassigner'" />
              <Button icon="pi pi-ticket" severity="secondary" text size="small" v-tooltip="'Créer ticket'" />
              <Button icon="pi pi-times" severity="danger" text size="small" v-tooltip="'Fermer chat'" @click="closeChat" />
            </div>
          </div>

          <!-- Messages -->
          <div class="flex-1 overflow-y-auto p-4 space-y-3">
            <div
              v-for="msg in selectedChat.messages"
              :key="msg.id"
              class="flex gap-2"
              :class="msg.type === 'agent' ? 'flex-row-reverse' : ''"
            >
              <div
                class="w-7 h-7 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                :class="msg.type === 'agent' ? 'bg-blue-600' : 'bg-gray-400'"
              >
                {{ msg.type === 'agent' ? 'A' : selectedChat.visitor.charAt(0) }}
              </div>
              <div
                class="max-w-[70%] rounded-2xl px-3 py-2 text-sm"
                :class="msg.type === 'agent'
                  ? 'bg-blue-600 text-white rounded-tr-sm'
                  : 'bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 shadow-sm rounded-tl-sm'"
              >
                <p>{{ msg.content }}</p>
                <p class="text-xs mt-1 opacity-60 text-right">{{ msg.time }}</p>
              </div>
            </div>
          </div>

          <!-- Quick replies -->
          <div class="px-4 py-2 bg-white dark:bg-gray-800 border-t border-gray-100 dark:border-gray-700">
            <div class="flex gap-2 flex-wrap">
              <button
                v-for="qr in quickReplies"
                :key="qr"
                class="text-xs border border-gray-300 dark:border-gray-600 rounded-full px-3 py-1 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                @click="inputMessage = qr"
              >{{ qr }}</button>
            </div>
          </div>

          <!-- Input area -->
          <div class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 p-3">
            <div class="flex gap-2 items-end">
              <div class="flex-1">
                <Textarea
                  v-model="inputMessage"
                  rows="2"
                  class="w-full text-sm resize-none"
                  placeholder="Tapez votre message..."
                  @keydown.enter.exact.prevent="sendChatMessage"
                />
              </div>
              <div class="flex flex-col gap-1">
                <Button icon="pi pi-paperclip" severity="secondary" text size="small" />
                <Button icon="pi pi-send" size="small" @click="sendChatMessage" :disabled="!inputMessage.trim()" />
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Select from 'primevue/select'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'

const page = usePage()
const { isAdmin, isElevated, hasAnyRole } = useRoleAccess()
const canManage = computed(() => isElevated.value || hasAnyRole(['customer-service']))
const canCreate = computed(() => canManage.value)
const canEdit = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)


const auth = computed(() => page.props.auth)

const agentStatus = ref('disponible')
const chatSearch = ref('')
const activeTab = ref('all')
const selectedChat = ref<any>(null)
const inputMessage = ref('')

const stats = ref({ actifs: 8, attenteMin: 2, satisfaction: 94, fermes: 31 })

const agentStatusOptions = [
  { label: 'Disponible', value: 'disponible' },
  { label: 'Occupé', value: 'occupe' },
  { label: 'Absent', value: 'absent' },
]

const chatTabs = [
  { label: 'Tous', value: 'all', count: 0 },
  { label: 'En attente', value: 'attente', count: 3 },
  { label: 'En cours', value: 'encours', count: 0 },
  { label: 'Fermés', value: 'ferme', count: 0 },
]

const quickReplies = [
  'Bonjour ! Comment puis-je vous aider ?',
  'Un instant, je vérifie pour vous.',
  'Merci pour votre patience.',
  'Je vous transfère à un spécialiste.',
]

const chats = ref([
  {
    id: 1, visitor: 'Mamadou Sow', page: '/checkout', country: 'Sénégal', statut: 'En attente', waitTime: '3min',
    agent: 'Non assigné', lastMessage: 'Mon paiement Wave ne passe pas',
    messages: [
      { id: 1, type: 'client', content: 'Bonjour, mon paiement Wave ne passe pas lors du checkout.', time: '09:12' },
      { id: 2, type: 'client', content: 'Cela fait 5 minutes que j\'essaie.', time: '09:14' },
    ],
  },
  {
    id: 2, visitor: 'Aïcha Coulibaly', page: '/factures', country: 'Côte d\'Ivoire', statut: 'En cours', waitTime: '12min',
    agent: 'Ibrahim T.', lastMessage: 'Comment télécharger ma facture en PDF ?',
    messages: [
      { id: 1, type: 'client', content: 'Bonjour, comment est-ce que je peux télécharger ma facture en PDF ?', time: '09:02' },
      { id: 2, type: 'agent', content: 'Bonjour Aïcha ! Cliquez sur le bouton "Télécharger" en haut à droite de la facture.', time: '09:05' },
      { id: 3, type: 'client', content: 'Je ne vois pas ce bouton sur mon téléphone.', time: '09:08' },
      { id: 4, type: 'agent', content: 'Sur mobile, appuyez sur les 3 points (menu) en haut à droite. Le bouton "PDF" apparaît dans ce menu.', time: '09:10' },
    ],
  },
  {
    id: 3, visitor: 'Kwame Asante', page: '/stock', country: 'Ghana', statut: 'En attente', waitTime: '1min',
    agent: 'Non assigné', lastMessage: 'Stock not updating after import',
    messages: [
      { id: 1, type: 'client', content: 'Hello, my stock levels are not updating after the Excel import I did yesterday.', time: '09:18' },
    ],
  },
  {
    id: 4, visitor: 'Fatima Ould Ahmed', page: '/rh', country: 'Mauritanie', statut: 'En cours', waitTime: '8min',
    agent: 'Aïssatou B.', lastMessage: 'Je veux modifier le salaire d\'un employé',
    messages: [
      { id: 1, type: 'client', content: 'Bonjour, je dois modifier le salaire d\'un employé. Où est-ce que je peux faire ça ?', time: '09:08' },
      { id: 2, type: 'agent', content: 'Bonjour ! Allez dans RH > Employés > cliquez sur l\'employé > onglet Paie.', time: '09:11' },
    ],
  },
  {
    id: 5, visitor: 'Thierno Barry', page: '/', country: 'Guinée', statut: 'Fermé', waitTime: '—',
    agent: 'Ibrahim T.', lastMessage: 'Merci pour votre aide !',
    messages: [
      { id: 1, type: 'client', content: 'Bonjour, comment créer un nouveau client ?', time: '08:30' },
      { id: 2, type: 'agent', content: 'Allez dans CRM > Contacts > Nouveau contact.', time: '08:32' },
      { id: 3, type: 'client', content: 'Merci pour votre aide !', time: '08:34' },
    ],
  },
])

const filteredChats = computed(() => {
  let list = chats.value
  if (activeTab.value === 'attente') list = list.filter(c => c.statut === 'En attente')
  else if (activeTab.value === 'encours') list = list.filter(c => c.statut === 'En cours')
  else if (activeTab.value === 'ferme') list = list.filter(c => c.statut === 'Fermé')
  if (chatSearch.value) list = list.filter(c => c.visitor.toLowerCase().includes(chatSearch.value.toLowerCase()))
  return list
})

const agentStatusColor = (status: string) => {
  const map: Record<string, string> = { disponible: 'bg-green-500', occupe: 'bg-yellow-500', absent: 'bg-gray-400' }
  return map[status] ?? 'bg-gray-400'
}

const statusColor = (statut: string) => {
  const map: Record<string, string> = { 'En attente': 'text-orange-500 font-medium', 'En cours': 'text-blue-600 font-medium', 'Fermé': 'text-gray-400' }
  return map[statut] ?? 'text-gray-500'
}

const selectChat = (chat: any) => {
  selectedChat.value = chat
}

const sendChatMessage = () => {
  if (!inputMessage.value.trim() || !selectedChat.value) return
  selectedChat.value.messages.push({
    id: selectedChat.value.messages.length + 1,
    type: 'agent',
    content: inputMessage.value,
    time: new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }),
  })
  selectedChat.value.lastMessage = inputMessage.value
  selectedChat.value.statut = 'En cours'
  inputMessage.value = ''
}

const closeChat = () => {
  if (selectedChat.value) selectedChat.value.statut = 'Fermé'
  selectedChat.value = null
}
</script>
