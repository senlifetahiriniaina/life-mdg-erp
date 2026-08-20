<template>
  <AppLayout>
    <Head title="Messagerie" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Messagerie</h1>
        <p class="wh-page-subtitle">{{ conversations.length }} conversation{{ conversations.length !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="showNewConversation = true">
          <i class="pi pi-plus" style="font-size: 12px" /> Nouvelle conversation
        </button>
      </div>
    </div>

    <div class="msg-page">
      <!-- Conversation list -->
      <div class="wh-panel msg-panel-list">
        <div v-if="loadingList" style="padding:32px;text-align:center">
          <i class="pi pi-spin pi-spinner" style="font-size:20px" />
        </div>
        <div v-else-if="conversations.length === 0" class="empty-state">
          <i class="pi pi-comments" style="font-size:28px;color:var(--fg-4);margin-bottom:8px" />
          <p>Aucune conversation</p>
        </div>
        <div v-else class="msg-conv-list">
          <div
            v-for="conv in conversations"
            :key="conv.id"
            :class="['msg-conv-item', { active: conv.id === activeConversationId, unread: conv.unread_count > 0 }]"
            @click="selectConversation(conv.id)"
          >
            <div class="msg-conv-avatar">
              <i v-if="conv.type === 'group'" class="pi pi-users" style="font-size:14px" />
              <span v-else>{{ initials(otherParticipant(conv)) }}</span>
            </div>
            <div class="msg-conv-content">
              <div class="msg-conv-title">{{ conv.name || otherParticipant(conv)?.name || 'Conversation' }}</div>
              <div class="msg-conv-preview">{{ truncate(conv.last_message?.body, 40) || 'Aucun message' }}</div>
            </div>
            <span v-if="conv.unread_count > 0" class="msg-conv-badge">{{ conv.unread_count }}</span>
          </div>
        </div>
      </div>

      <!-- Active thread -->
      <div class="wh-panel msg-panel-thread">
        <template v-if="activeConversationId">
          <div class="msg-thread-header">
            <strong>{{ activeConversation?.name || otherParticipant(activeConversation)?.name || 'Conversation' }}</strong>
          </div>

          <div ref="threadBody" class="msg-thread-body">
            <div v-if="loadingMessages" style="padding:32px;text-align:center">
              <i class="pi pi-spin pi-spinner" style="font-size:20px" />
            </div>
            <div v-else v-for="msg in messages" :key="msg.id" :class="['msg-bubble', msg.sender_id === currentUserId ? 'mine' : 'theirs']">
              <div class="msg-bubble-sender" v-if="msg.sender_id !== currentUserId">{{ msg.sender?.name }}</div>
              <div class="msg-bubble-body">{{ msg.body }}</div>
              <div class="msg-bubble-time">{{ formatTime(msg.created_at) }}</div>
            </div>
          </div>

          <form class="msg-thread-input" @submit.prevent="sendMessage">
            <input v-model="newMessage" type="text" placeholder="Écrire un message…" maxlength="5000" />
            <button type="submit" class="btn btn-primary" :disabled="!newMessage.trim() || sending">
              <i class="pi pi-send" style="font-size: 12px" />
            </button>
          </form>
        </template>
        <div v-else class="empty-state" style="height:100%;display:flex;flex-direction:column;justify-content:center">
          <i class="pi pi-comments" style="font-size:32px;color:var(--fg-4);margin-bottom:8px" />
          <p>Sélectionnez une conversation</p>
        </div>
      </div>
    </div>

    <!-- New conversation modal -->
    <div v-if="showNewConversation" class="msg-overlay" @click.self="showNewConversation = false">
      <div class="msg-modal">
        <div class="msg-modal-header">
          <h3>Nouvelle conversation</h3>
          <button class="msg-modal-close" @click="showNewConversation = false"><i class="pi pi-times" /></button>
        </div>
        <div class="msg-modal-body">
          <label class="wh-label">Destinataire(s)</label>
          <select v-model="selectedUserIds" multiple class="wh-input" style="height: 140px">
            <option v-for="u in availableUsers" :key="u.id" :value="u.id">{{ u.name }} ({{ u.email }})</option>
          </select>
          <p v-if="selectedUserIds.length > 1" class="msg-modal-hint">
            {{ selectedUserIds.length }} destinataires sélectionnés — une conversation de groupe sera créée.
          </p>
        </div>
        <div class="msg-modal-actions">
          <button class="btn btn-secondary" @click="showNewConversation = false">Annuler</button>
          <button class="btn btn-primary" :disabled="selectedUserIds.length === 0" @click="createConversation">
            Démarrer
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

const currentUserId = usePage().props.auth?.user?.id

const conversations = ref([])
const loadingList = ref(false)
const activeConversationId = ref(null)
const messages = ref([])
const loadingMessages = ref(false)
const newMessage = ref('')
const sending = ref(false)
const threadBody = ref(null)

const showNewConversation = ref(false)
const availableUsers = ref([])
const selectedUserIds = ref([])

const activeConversation = computed(() =>
  conversations.value.find((c) => c.id === activeConversationId.value)
)

function otherParticipant(conv) {
  if (!conv) return null
  return (conv.users || []).find((u) => u.id !== currentUserId)
}

function initials(user) {
  const name = user?.name ?? ''
  return name.split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase() || '?'
}

function truncate(str, len) {
  return str && str.length > len ? str.slice(0, len) + '…' : str
}

function formatTime(iso) {
  if (!iso) return ''
  return new Date(iso).toLocaleString('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
}

async function loadConversations() {
  loadingList.value = true
  try {
    const { data } = await axios.get('/api/v1/messaging/conversations')
    conversations.value = data.data || []
  } finally {
    loadingList.value = false
  }
}

async function selectConversation(id) {
  activeConversationId.value = id
  loadingMessages.value = true
  try {
    const { data } = await axios.get(`/api/v1/messaging/conversations/${id}/messages`)
    messages.value = data.data || []
    const row = conversations.value.find((c) => c.id === id)
    if (row) row.unread_count = 0
    await nextTick()
    scrollToBottom()
    subscribeToActiveConversation()
  } finally {
    loadingMessages.value = false
  }
}

function scrollToBottom() {
  if (threadBody.value) threadBody.value.scrollTop = threadBody.value.scrollHeight
}

async function sendMessage() {
  if (!newMessage.value.trim() || !activeConversationId.value) return
  sending.value = true
  try {
    const { data } = await axios.post(`/api/v1/messaging/conversations/${activeConversationId.value}/messages`, {
      body: newMessage.value,
    })
    messages.value.push(data.data)
    newMessage.value = ''
    await nextTick()
    scrollToBottom()
  } finally {
    sending.value = false
  }
}

async function openNewConversationModal() {
  if (availableUsers.value.length === 0) {
    try {
      const { data } = await axios.get('/api/v1/messaging/users')
      availableUsers.value = data.data || []
    } catch {
      availableUsers.value = []
    }
  }
}

async function createConversation() {
  const { data } = await axios.post('/api/v1/messaging/conversations', {
    user_ids: selectedUserIds.value,
  })
  showNewConversation.value = false
  selectedUserIds.value = []
  await loadConversations()
  selectConversation(data.data.id)
}

let echoChannel = null

function subscribeToActiveConversation() {
  if (echoChannel && window.Echo) {
    window.Echo.leave(`conversation.${echoChannel}`)
    echoChannel = null
  }
  if (window.Echo && activeConversationId.value) {
    echoChannel = activeConversationId.value
    window.Echo.private(`conversation.${activeConversationId.value}`)
      .listen('.message.sent', (e) => {
        if (e.message?.sender_id !== currentUserId) {
          messages.value.push(e.message)
          nextTick(scrollToBottom)
        }
      })
  }
}

watch(showNewConversation, (open) => {
  if (open) openNewConversationModal()
})

onMounted(async () => {
  await loadConversations()

  const params = new URLSearchParams(window.location.search)
  const preselect = params.get('conversation')
  if (preselect) selectConversation(Number(preselect))
})

onUnmounted(() => {
  if (echoChannel && window.Echo) window.Echo.leave(`conversation.${echoChannel}`)
})
</script>

<style scoped>
.msg-page {
  display: grid;
  grid-template-columns: 320px 1fr;
  gap: 16px;
  height: calc(100vh - 220px);
  min-height: 480px;
}
.msg-panel-list { overflow-y: auto; padding: 0; }
.msg-panel-thread { display: flex; flex-direction: column; padding: 0; overflow: hidden; }

.msg-conv-list { display: flex; flex-direction: column; }
.msg-conv-item {
  display: flex;
  gap: 10px;
  padding: 12px 16px;
  border-bottom: 1px solid var(--border-subtle, var(--border-subtle));
  cursor: pointer;
  align-items: center;
}
.msg-conv-item:hover { background: var(--surface-2, #f4f4f7) }
.msg-conv-item.active { background: var(--halo-50, #f0f2ff) }
.msg-conv-item.unread .msg-conv-title { font-weight: 700 }
.msg-conv-avatar {
  width: 36px; height: 36px; border-radius: 50%;
  background: var(--halo-100, #e0e4ff); color: var(--halo-700, #3949ab);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; font-size: 12px; font-weight: 700;
}
.msg-conv-content { flex: 1; min-width: 0 }
.msg-conv-title { font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis }
.msg-conv-preview { font-size: 12px; color: var(--fg-3, #6b6b80); white-space: nowrap; overflow: hidden; text-overflow: ellipsis }
.msg-conv-badge {
  background: var(--halo-600, #3949ab); color: #fff; border-radius: 10px;
  font-size: 10px; font-weight: 800; min-width: 18px; height: 18px;
  display: flex; align-items: center; justify-content: center; padding: 0 5px;
}

.msg-thread-header { padding: 14px 20px; border-bottom: 1px solid var(--border-subtle, var(--border-subtle)); }
.msg-thread-body { flex: 1; overflow-y: auto; padding: 16px 20px; display: flex; flex-direction: column; gap: 10px; }
.msg-bubble { max-width: 70%; }
.msg-bubble.mine { align-self: flex-end; text-align: right; }
.msg-bubble.theirs { align-self: flex-start; }
.msg-bubble-sender { font-size: 11px; color: var(--fg-3, #6b6b80); margin-bottom: 2px; }
.msg-bubble-body {
  display: inline-block; padding: 8px 12px; border-radius: 12px; font-size: 14px;
  background: var(--surface-2, #f4f4f7);
}
.msg-bubble.mine .msg-bubble-body { background: var(--halo-600, #3949ab); color: #fff; }
.msg-bubble-time { font-size: 10px; color: var(--fg-4, #9a9ab0); margin-top: 2px; }

.msg-thread-input {
  display: flex; gap: 8px; padding: 12px 16px;
  border-top: 1px solid var(--border-subtle, var(--border-subtle));
}
.msg-thread-input input {
  flex: 1; border: 1px solid var(--border-1, #d1d5db); border-radius: 20px;
  padding: 8px 16px; font-size: 14px; background: var(--bg-2, #fff); color: inherit;
}

.msg-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.45);
  display: flex; align-items: center; justify-content: center; z-index: 1000;
}
.msg-modal {
  background: var(--bg-1, #fff); color: var(--fg-1, #111); border-radius: 12px;
  width: min(420px, 92vw); box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}
.msg-modal-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 16px 20px; border-bottom: 1px solid var(--border-1, #e5e7eb);
}
.msg-modal-header h3 { margin: 0; font-size: 16px; }
.msg-modal-close { background: none; border: none; cursor: pointer; color: var(--fg-3, #6b7280); }
.msg-modal-body { padding: 16px 20px; }
.msg-modal-hint { font-size: 12px; color: var(--fg-3, #6b6b80); margin-top: 6px; }
.msg-modal-actions { display: flex; justify-content: flex-end; gap: 8px; padding: 12px 20px 20px; }
</style>
