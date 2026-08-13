<template>
  <AppLayout>
    <Head :title="$t('helpdesk.chat.agent_dashboard')" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">{{ $t('helpdesk.chat.title') }}</h1>
        <p class="wh-page-subtitle">{{ $t('helpdesk.chat.agent_subtitle') }}</p>
      </div>
      <div class="page-actions">
        <span class="badge-waiting">
          <i class="pi pi-clock" style="font-size:12px" />
          {{ waitingCount }} {{ $t('helpdesk.chat.waiting') }}
        </span>
        <span class="badge-active">
          <i class="pi pi-comments" style="font-size:12px" />
          {{ activeCount }} {{ $t('helpdesk.chat.active') }}
        </span>
        <button class="btn btn-primary" :disabled="waitingSessions.length === 0" @click="takeNext">
          <i class="pi pi-arrow-right" style="font-size:13px" />
          {{ $t('helpdesk.chat.take_next') }}
        </button>
      </div>
    </div>

    <div class="chat-layout">
      <!-- Left panel: queues -->
      <div class="chat-sidebar">
        <!-- Waiting -->
        <div class="sidebar-section">
          <div class="sidebar-section-title">
            <i class="pi pi-clock" style="font-size:12px" />
            {{ $t('helpdesk.chat.waiting') }}
            <span class="count-badge">{{ waitingSessions.length }}</span>
          </div>
          <div
            v-for="session in waitingSessions"
            :key="session.id"
            class="session-item"
            :class="{ 'session-item-active': selectedSession?.id === session.id }"
            @click="selectSession(session)"
          >
            <div class="session-visitor">{{ session.visitor_name || session.visitor_id.slice(0, 8) }}</div>
            <div class="session-meta">
              <span class="session-channel">{{ session.channel }}</span>
              <span class="session-time">{{ formatRelative(session.started_at) }}</span>
            </div>
          </div>
          <div v-if="waitingSessions.length === 0" class="empty-state">{{ $t('helpdesk.chat.no_waiting') }}</div>
        </div>

        <!-- Active -->
        <div class="sidebar-section">
          <div class="sidebar-section-title">
            <i class="pi pi-comments" style="font-size:12px" />
            {{ $t('helpdesk.chat.active') }}
            <span class="count-badge count-badge-green">{{ activeSessions.length }}</span>
          </div>
          <div
            v-for="session in activeSessions"
            :key="session.id"
            class="session-item"
            :class="{ 'session-item-active': selectedSession?.id === session.id }"
            @click="selectSession(session)"
          >
            <div class="session-visitor">{{ session.visitor_name || session.visitor_id.slice(0, 8) }}</div>
            <div class="session-meta">
              <span class="session-agent">{{ session.assigned_agent?.name }}</span>
              <span class="session-time">{{ formatRelative(session.started_at) }}</span>
            </div>
          </div>
          <div v-if="activeSessions.length === 0" class="empty-state">{{ $t('helpdesk.chat.no_active') }}</div>
        </div>
      </div>

      <!-- Center: Chat window -->
      <div class="chat-main">
        <template v-if="selectedSession">
          <!-- Chat header -->
          <div class="chat-window-header">
            <div>
              <div class="chat-window-name">{{ selectedSession.visitor_name || selectedSession.visitor_id.slice(0, 12) }}</div>
              <div style="font-size:12px;color:var(--fg-3)">{{ selectedSession.visitor_email || '—' }}</div>
            </div>
            <div style="display:flex;gap:8px">
              <button class="btn btn-secondary" @click="convertToTicket" :disabled="converting">
                <i class="pi pi-ticket" style="font-size:12px" />
                {{ $t('helpdesk.chat.convert_ticket') }}
              </button>
              <button class="btn btn-secondary" style="color:var(--danger-fg)" @click="closeSession">
                {{ $t('helpdesk.chat.close_session') }}
              </button>
            </div>
          </div>

          <!-- Messages -->
          <div class="chat-messages" ref="messagesEl">
            <div
              v-for="msg in currentMessages"
              :key="msg.id"
              class="msg"
              :class="msg.sender_type === 'agent' ? 'msg-agent' : msg.sender_type === 'visitor' ? 'msg-visitor' : 'msg-system'"
            >
              <div v-if="msg.type === 'system'" class="msg-system-text">{{ msg.message }}</div>
              <div v-else class="msg-bubble">{{ msg.message }}</div>
              <div class="msg-time">{{ formatTime(msg.created_at) }}</div>
            </div>
            <div v-if="currentMessages.length === 0" style="text-align:center;color:var(--fg-3);font-size:13px;padding:40px 0">
              {{ $t('helpdesk.chat.no_messages') }}
            </div>
          </div>

          <!-- Reply -->
          <div class="chat-reply-area">
            <textarea
              v-model="replyText"
              class="reply-input"
              :placeholder="$t('helpdesk.chat.type_reply')"
              rows="2"
              @keydown.ctrl.enter.prevent="sendReply"
              :disabled="selectedSession.status === 'closed'"
            />
            <button class="btn btn-primary" :disabled="!replyText.trim() || replying || selectedSession.status === 'closed'" @click="sendReply">
              <i v-if="replying" class="pi pi-spin pi-spinner" style="font-size:12px" />
              <i v-else class="pi pi-send" style="font-size:12px" />
              {{ $t('helpdesk.chat.send') }}
            </button>
          </div>
        </template>

        <div v-else class="chat-empty">
          <i class="pi pi-comments" style="font-size:48px;color:var(--fg-4)" />
          <p>{{ $t('helpdesk.chat.select_session') }}</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, watch, nextTick, onUnmounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import AppLayout from '@/Layouts/AppLayout.vue'

const { t } = useI18n()

const props = defineProps({
  waitingSessions: { type: Array, default: () => [] },
  activeSessions:  { type: Array, default: () => [] },
})

const waitingSessions = ref([...props.waitingSessions])
const activeSessions  = ref([...props.activeSessions])
const selectedSession = ref(null)
const currentMessages = ref([])
const replyText       = ref('')
const replying        = ref(false)
const converting      = ref(false)
const messagesEl      = ref(null)

let pollInterval = null

const waitingCount = computed(() => waitingSessions.value.length)
const activeCount  = computed(() => activeSessions.value.length)

async function fetchQueue() {
  try {
    const res = await fetch('/api/v1/helpdesk/chat/queue', {
      headers: { Accept: 'application/json' },
    })
    if (res.ok) {
      const data = await res.json()
      waitingSessions.value = data.waiting
      activeSessions.value  = data.active
    }
  } catch { /* silent */ }
}

async function selectSession(session) {
  selectedSession.value = session
  currentMessages.value = []
  await fetchMessages()
}

async function fetchMessages() {
  if (!selectedSession.value) return
  try {
    const res = await fetch(`/api/v1/helpdesk/chat/sessions/${selectedSession.value.id}/messages`, {
      headers: { Accept: 'application/json' },
    })
    if (res.ok) {
      const data = await res.json()
      currentMessages.value = data.messages
      selectedSession.value.status = data.session_status
      await nextTick()
      scrollToBottom()
    }
  } catch { /* silent */ }
}

async function takeNext() {
  if (waitingSessions.value.length === 0) return
  const session = waitingSessions.value[0]
  try {
    const res = await fetch(`/api/v1/helpdesk/chat/sessions/${session.id}/assign`, {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    })
    if (res.ok) {
      await fetchQueue()
      await selectSession(session)
    }
  } catch { /* silent */ }
}

async function sendReply() {
  const text = replyText.value.trim()
  if (!text || replying.value || !selectedSession.value) return
  replying.value = true
  try {
    await fetch(`/api/v1/helpdesk/chat/sessions/${selectedSession.value.id}/messages`, {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ sender_type: 'agent', message: text }),
    })
    replyText.value = ''
    await fetchMessages()
  } finally {
    replying.value = false
  }
}

async function convertToTicket() {
  if (!selectedSession.value || converting.value) return
  converting.value = true
  try {
    const res = await fetch(`/api/v1/helpdesk/chat/sessions/${selectedSession.value.id}/convert-to-ticket`, {
      method: 'POST',
      headers: { Accept: 'application/json' },
    })
    if (res.ok) {
      await fetchQueue()
      selectedSession.value = null
    }
  } finally {
    converting.value = false
  }
}

async function closeSession() {
  if (!selectedSession.value) return
  await fetch(`/api/v1/helpdesk/chat/sessions/${selectedSession.value.id}/close`, {
    method: 'POST',
    headers: { Accept: 'application/json' },
  })
  selectedSession.value = null
  await fetchQueue()
}

function scrollToBottom() {
  if (messagesEl.value) {
    messagesEl.value.scrollTop = messagesEl.value.scrollHeight
  }
}

function formatRelative(dateStr) {
  if (!dateStr) return ''
  const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000)
  if (diff < 60) return `${diff}s`
  if (diff < 3600) return `${Math.floor(diff / 60)}m`
  return `${Math.floor(diff / 3600)}h`
}

function formatTime(dateStr) {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

// Start polling
pollInterval = setInterval(async () => {
  await fetchQueue()
  if (selectedSession.value) await fetchMessages()
}, 5000)

onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval)
})
</script>

<style scoped>
.page-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; gap:16px; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.page-actions { display:flex; align-items:center; gap:8px; }
.btn { font-weight:500; font-size:13px; padding:7px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover:not(:disabled) { background:var(--halo-700); }
.btn-primary:disabled { opacity:0.5; cursor:not-allowed; }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover:not(:disabled) { background:var(--bg-sunken); }

.badge-waiting, .badge-active {
  display: inline-flex; align-items:center; gap:5px;
  padding: 5px 10px; border-radius: var(--r-pill);
  font-size: 12px; font-weight: 500;
}
.badge-waiting { background: var(--warn-bg); color: var(--warn-fg); }
.badge-active  { background: var(--success-bg); color: var(--success-fg); }

.chat-layout {
  display: grid;
  grid-template-columns: 260px 1fr;
  gap: 16px;
  height: calc(100vh - 180px);
  min-height: 500px;
}

.chat-sidebar {
  background: var(--bg-canvas);
  border: 1px solid var(--border-subtle);
  border-radius: var(--r-lg);
  overflow-y: auto;
  display: flex;
  flex-direction: column;
}

.sidebar-section { padding: 12px; border-bottom: 1px solid var(--border-subtle); }
.sidebar-section:last-child { border-bottom: 0; flex: 1; }

.sidebar-section-title {
  font-size: 11px; font-weight: 600; text-transform: uppercase;
  letter-spacing: 0.06em; color: var(--fg-3);
  margin-bottom: 8px;
  display: flex; align-items: center; gap: 6px;
}

.count-badge {
  margin-left: auto;
  background: var(--warn-bg); color: var(--warn-fg);
  border-radius: var(--r-pill); padding: 1px 7px; font-size: 11px;
}
.count-badge-green { background: var(--success-bg); color: var(--success-fg); }

.session-item {
  padding: 9px 10px; border-radius: var(--r-md); cursor: pointer;
  transition: background 0.1s;
}
.session-item:hover { background: var(--bg-sunken); }
.session-item-active { background: var(--halo-50); }

.session-visitor { font-size: 13px; font-weight: 500; color: var(--fg-1); }
.session-meta { display: flex; gap: 8px; margin-top: 2px; }
.session-channel, .session-agent { font-size: 11px; color: var(--fg-3); }
.session-time { font-size: 11px; color: var(--fg-4); margin-left: auto; }

.empty-state { font-size: 12px; color: var(--fg-4); padding: 8px 0; text-align: center; }

.chat-main {
  background: var(--bg-canvas);
  border: 1px solid var(--border-subtle);
  border-radius: var(--r-lg);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.chat-empty {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  color: var(--fg-3);
  font-size: 14px;
}

.chat-window-header {
  padding: 14px 18px;
  border-bottom: 1px solid var(--border-subtle);
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
}
.chat-window-name { font-size: 15px; font-weight: 600; color: var(--fg-1); }

.chat-messages {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.msg { display: flex; flex-direction: column; max-width: 70%; }
.msg-visitor { align-self: flex-start; align-items: flex-start; }
.msg-agent   { align-self: flex-end; align-items: flex-end; }
.msg-system  { align-self: center; align-items: center; }

.msg-bubble {
  padding: 9px 14px;
  border-radius: 12px;
  font-size: 13px;
  line-height: 1.5;
}
.msg-visitor .msg-bubble { background: var(--bg-sunken); color: var(--fg-1); border-bottom-left-radius: 4px; }
.msg-agent   .msg-bubble { background: var(--halo-500); color: #fff; border-bottom-right-radius: 4px; }
.msg-time { font-size: 11px; color: var(--fg-4); margin-top: 3px; }
.msg-system-text { font-size: 12px; color: var(--fg-3); padding: 3px 10px; background: var(--bg-sunken); border-radius: var(--r-pill); }

.chat-reply-area {
  padding: 12px;
  border-top: 1px solid var(--border-subtle);
  display: flex;
  gap: 10px;
  align-items: flex-end;
  flex-shrink: 0;
}

.reply-input {
  flex: 1;
  border: 1px solid var(--border-subtle);
  border-radius: var(--r-md);
  padding: 8px 12px;
  font-size: 13px;
  font-family: var(--font-sans, inherit);
  resize: none;
  background: var(--bg-canvas);
  color: var(--fg-1);
  outline: none;
  transition: border-color 0.15s;
}
.reply-input:focus { border-color: var(--halo-400); }
</style>
