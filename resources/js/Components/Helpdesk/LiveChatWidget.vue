<template>
  <!-- Floating chat widget button -->
  <div class="livechat-container">
    <!-- Toggle button -->
    <button
      class="livechat-toggle"
      :class="{ 'livechat-toggle-open': isOpen }"
      @click="toggleChat"
      :aria-label="isOpen ? $t('helpdesk.chat.close') : $t('helpdesk.chat.open_chat')"
      :aria-expanded="isOpen"
    >
      <i v-if="!isOpen" class="pi pi-comments" />
      <i v-else class="pi pi-times" />
      <span v-if="unreadCount > 0 && !isOpen" class="livechat-badge">{{ unreadCount }}</span>
    </button>

    <!-- Chat panel -->
    <Transition name="chat-panel">
      <div v-if="isOpen" class="livechat-panel" role="dialog" aria-modal="true" :aria-label="$t('helpdesk.chat.title')">
        <!-- Header -->
        <div class="livechat-header">
          <div style="display:flex;align-items:center;gap:8px">
            <div class="livechat-avatar"><i class="pi pi-headphones" /></div>
            <div>
              <div class="livechat-header-title">{{ $t('helpdesk.chat.title') }}</div>
              <div class="livechat-status-line">
                <span class="livechat-dot" :class="connectionClass" />
                <span style="font-size:11px;color:var(--fg-3)">{{ connectionLabel }}</span>
              </div>
            </div>
          </div>
          <button class="livechat-close-btn" @click="isOpen = false"><i class="pi pi-times" style="font-size:12px" /></button>
        </div>

        <!-- Name/email form (first time) -->
        <div v-if="!sessionId" class="livechat-intro">
          <p class="livechat-intro-text">{{ $t('helpdesk.chat.intro') }}</p>
          <div class="lc-field">
            <label class="lc-label">{{ $t('helpdesk.chat.your_name') }}</label>
            <input v-model="visitorName" class="lc-input" type="text" :placeholder="$t('helpdesk.chat.name_placeholder')" maxlength="120" />
          </div>
          <div class="lc-field">
            <label class="lc-label">{{ $t('helpdesk.chat.your_email') }}</label>
            <input v-model="visitorEmail" class="lc-input" type="email" :placeholder="$t('helpdesk.chat.email_placeholder')" maxlength="255" />
          </div>
          <button class="lc-start-btn" :disabled="starting" @click="startChat">
            <i v-if="starting" class="pi pi-spin pi-spinner" style="font-size:12px" />
            {{ $t('helpdesk.chat.start') }}
          </button>
        </div>

        <!-- Messages -->
        <div v-else class="livechat-messages" ref="messagesContainer">
          <div
            v-for="msg in messages"
            :key="msg.id"
            class="lc-message"
            :class="msg.sender_type === 'visitor' ? 'lc-message-visitor' : 'lc-message-agent'"
          >
            <div v-if="msg.type === 'system'" class="lc-system-msg">{{ msg.message }}</div>
            <template v-else>
              <div class="lc-bubble">{{ msg.message }}</div>
              <div class="lc-time">{{ formatTime(msg.created_at) }}</div>
            </template>
          </div>
          <div v-if="agentTyping" class="lc-typing">
            <span class="lc-typing-dot" /><span class="lc-typing-dot" /><span class="lc-typing-dot" />
            {{ $t('helpdesk.chat.typing') }}
          </div>
          <div v-if="queuePosition > 0" class="lc-queue-msg">
            {{ $t('helpdesk.chat.queue_position', { n: queuePosition }) }}
          </div>
        </div>

        <!-- Input area -->
        <div v-if="sessionId && sessionStatus !== 'closed'" class="livechat-input-area">
          <input
            v-model="inputMessage"
            class="lc-input"
            type="text"
            :placeholder="$t('helpdesk.chat.type_message')"
            maxlength="5000"
            @keydown.enter.prevent="sendMessage"
            :disabled="sending"
          />
          <button class="lc-send-btn" :disabled="sending || !inputMessage.trim()" @click="sendMessage">
            <i class="pi pi-send" style="font-size:14px" />
          </button>
        </div>
        <div v-if="sessionId && sessionStatus === 'closed'" class="lc-closed-msg">
          {{ $t('helpdesk.chat.session_closed') }}
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

// State
const isOpen = ref(false)
const sessionId = ref(localStorage.getItem('hd_chat_session') || null)
const visitorId = ref(localStorage.getItem('hd_visitor_id') || generateUUID())
const visitorName = ref(localStorage.getItem('hd_visitor_name') || '')
const visitorEmail = ref(localStorage.getItem('hd_visitor_email') || '')
const messages = ref([])
const inputMessage = ref('')
const starting = ref(false)
const sending = ref(false)
const sessionStatus = ref('waiting')
const queuePosition = ref(0)
const agentTyping = ref(false)
const unreadCount = ref(0)
const connectionStatus = ref('disconnected') // disconnected, connected, error
const messagesContainer = ref(null)
const lastMessageTime = ref(null)

let pollInterval = null

// Save visitor ID
localStorage.setItem('hd_visitor_id', visitorId.value)

const connectionClass = computed(() => ({
  'lc-dot-green':  connectionStatus.value === 'connected',
  'lc-dot-amber':  connectionStatus.value === 'disconnected',
  'lc-dot-red':    connectionStatus.value === 'error',
}))

const connectionLabel = computed(() => ({
  connected:    t('helpdesk.chat.connected'),
  disconnected: t('helpdesk.chat.waiting'),
  error:        t('helpdesk.chat.error'),
}[connectionStatus.value] ?? t('helpdesk.chat.waiting')))

function generateUUID() {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = Math.random() * 16 | 0
    return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16)
  })
}

function toggleChat() {
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    unreadCount.value = 0
    if (sessionId.value && !pollInterval) {
      startPolling()
    }
  } else {
    stopPolling()
  }
}

async function startChat() {
  if (starting.value) return
  starting.value = true

  try {
    const res = await fetch('/api/v1/helpdesk/chat/sessions', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        visitor_id:    visitorId.value,
        visitor_name:  visitorName.value || null,
        visitor_email: visitorEmail.value || null,
        channel:       'widget',
      }),
    })

    if (res.ok) {
      const data = await res.json()
      sessionId.value    = data.session.id
      sessionStatus.value = data.session.status
      queuePosition.value = data.queue_position

      localStorage.setItem('hd_chat_session',  String(data.session.id))
      localStorage.setItem('hd_visitor_name',  visitorName.value)
      localStorage.setItem('hd_visitor_email', visitorEmail.value)

      connectionStatus.value = 'connected'
      startPolling()
    }
  } catch {
    connectionStatus.value = 'error'
  } finally {
    starting.value = false
  }
}

async function sendMessage() {
  const text = inputMessage.value.trim()
  if (!text || sending.value) return
  sending.value = true

  try {
    await fetch(`/api/v1/helpdesk/chat/sessions/${sessionId.value}/messages`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ sender_type: 'visitor', message: text }),
    })
    inputMessage.value = ''
    await pollMessages()
  } catch {
    // silent fail
  } finally {
    sending.value = false
  }
}

async function pollMessages() {
  if (!sessionId.value) return

  try {
    const params = lastMessageTime.value ? `?since=${encodeURIComponent(lastMessageTime.value)}` : ''
    const res = await fetch(`/api/v1/helpdesk/chat/sessions/${sessionId.value}/messages${params}`, {
      headers: { Accept: 'application/json' },
    })

    if (res.ok) {
      const data = await res.json()
      connectionStatus.value = 'connected'
      sessionStatus.value    = data.session_status
      queuePosition.value    = data.queue_position

      if (data.messages && data.messages.length > 0) {
        messages.value.push(...data.messages)
        lastMessageTime.value = data.messages[data.messages.length - 1].created_at

        if (!isOpen.value) {
          const agentMsgs = data.messages.filter(m => m.sender_type === 'agent')
          unreadCount.value += agentMsgs.length
        }

        await nextTick()
        scrollToBottom()
      }
    }
  } catch {
    connectionStatus.value = 'error'
  }
}

function scrollToBottom() {
  if (messagesContainer.value) {
    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
  }
}

function startPolling() {
  if (pollInterval) return
  pollMessages()
  pollInterval = setInterval(pollMessages, 3000)
}

function stopPolling() {
  if (pollInterval) {
    clearInterval(pollInterval)
    pollInterval = null
  }
}

function formatTime(dateStr) {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

// Resume session on load
if (sessionId.value && isOpen.value) {
  startPolling()
}

onUnmounted(stopPolling)
</script>

<style scoped>
.livechat-container {
  position: fixed;
  bottom: 24px;
  right: 24px;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 12px;
}

.livechat-toggle {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: var(--halo-500, var(--halo-500));
  color: #fff;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  box-shadow: 0 4px 16px rgba(0,0,0,0.2);
  transition: background 0.2s, transform 0.15s;
  position: relative;
}
.livechat-toggle:hover { background: var(--halo-700, var(--halo-600)); transform: scale(1.06); }
.livechat-toggle-open { background: var(--fg-2, var(--fg-3)); }

.livechat-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  background: var(--danger-fg, var(--red-500));
  color: #fff;
  border-radius: 50%;
  width: 20px;
  height: 20px;
  font-size: 11px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}

.livechat-panel {
  width: 340px;
  max-height: 520px;
  border-radius: 16px;
  background: var(--bg-canvas, #fff);
  box-shadow: 0 8px 40px rgba(0,0,0,0.18);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid var(--border-subtle, var(--border-subtle));
}

.livechat-header {
  background: var(--halo-500, var(--halo-500));
  color: #fff;
  padding: 14px 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
}

.livechat-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: rgba(255,255,255,0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
}

.livechat-header-title { font-size: 14px; font-weight: 600; }
.livechat-status-line { display: flex; align-items: center; gap: 5px; margin-top: 2px; }

.livechat-close-btn {
  background: rgba(255,255,255,0.15);
  border: none;
  color: #fff;
  width: 26px;
  height: 26px;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}
.livechat-close-btn:hover { background: rgba(255,255,255,0.25); }

.livechat-intro {
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.livechat-intro-text { font-size: 13px; color: var(--fg-2, var(--fg-3)); margin: 0; }

.livechat-messages {
  flex: 1;
  overflow-y: auto;
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.lc-message { display: flex; flex-direction: column; max-width: 80%; }
.lc-message-visitor { align-self: flex-end; align-items: flex-end; }
.lc-message-agent   { align-self: flex-start; align-items: flex-start; }

.lc-bubble {
  padding: 8px 12px;
  border-radius: 12px;
  font-size: 13px;
  line-height: 1.4;
  word-break: break-word;
}
.lc-message-visitor .lc-bubble { background: var(--halo-500, var(--halo-500)); color: #fff; border-bottom-right-radius: 4px; }
.lc-message-agent   .lc-bubble { background: var(--bg-sunken, var(--bg-subtle)); color: var(--fg-1, #0f172a); border-bottom-left-radius: 4px; }

.lc-time { font-size: 10px; color: var(--fg-4, var(--fg-4)); margin-top: 2px; }

.lc-system-msg {
  text-align: center;
  font-size: 11px;
  color: var(--fg-3, var(--fg-4));
  padding: 4px 0;
  width: 100%;
}

.lc-typing {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: var(--fg-3, var(--fg-4));
}
.lc-typing-dot {
  width: 6px; height: 6px;
  border-radius: 50%;
  background: var(--fg-3, var(--fg-4));
  animation: bounce 1s infinite;
}
.lc-typing-dot:nth-child(2) { animation-delay: 0.15s; }
.lc-typing-dot:nth-child(3) { animation-delay: 0.3s; }

@keyframes bounce {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-4px); }
}

.lc-queue-msg {
  font-size: 12px;
  color: var(--fg-3, var(--fg-4));
  text-align: center;
  padding: 4px 0;
}

.livechat-input-area {
  padding: 10px;
  border-top: 1px solid var(--border-subtle, var(--border-subtle));
  display: flex;
  gap: 8px;
  flex-shrink: 0;
}

.lc-input {
  flex: 1;
  border: 1px solid var(--border-subtle, var(--border-subtle));
  border-radius: 8px;
  padding: 8px 12px;
  font-size: 13px;
  outline: none;
  font-family: var(--font-sans, inherit);
  background: var(--bg-canvas, #fff);
  color: var(--fg-1, #0f172a);
  transition: border-color 0.15s;
}
.lc-input:focus { border-color: var(--halo-400, #818cf8); }

.lc-send-btn {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: var(--halo-500, var(--halo-500));
  color: #fff;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: background 0.15s;
}
.lc-send-btn:hover:not(:disabled) { background: var(--halo-700, var(--halo-600)); }
.lc-send-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.lc-start-btn {
  background: var(--halo-500, var(--halo-500));
  color: #fff;
  border: none;
  border-radius: 8px;
  padding: 10px 18px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: background 0.15s;
}
.lc-start-btn:hover:not(:disabled) { background: var(--halo-700, var(--halo-600)); }
.lc-start-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.lc-field { display: flex; flex-direction: column; gap: 4px; }
.lc-label { font-size: 12px; font-weight: 500; color: var(--fg-2, var(--fg-3)); }

.lc-closed-msg {
  padding: 12px;
  text-align: center;
  font-size: 13px;
  color: var(--fg-3, var(--fg-4));
  border-top: 1px solid var(--border-subtle, var(--border-subtle));
  flex-shrink: 0;
}

.livechat-dot {
  width: 7px; height: 7px;
  border-radius: 50%;
}
.lc-dot-green { background: var(--green-500); }
.lc-dot-amber { background: var(--yellow-500); }
.lc-dot-red   { background: var(--red-500); }

/* Panel transition */
.chat-panel-enter-active,
.chat-panel-leave-active { transition: opacity 0.2s, transform 0.2s; }
.chat-panel-enter-from,
.chat-panel-leave-to    { opacity: 0; transform: translateY(12px) scale(0.97); }
</style>
