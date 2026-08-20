<template>
  <div class="msg-launcher" ref="launcherRef">
    <button
      :class="['msg-btn', { active: isOpen }]"
      :title="`${unreadTotal} message${unreadTotal !== 1 ? 's' : ''} non lu${unreadTotal !== 1 ? 's' : ''}`"
      @click="toggleDropdown"
    >
      <i class="pi pi-comments" style="font-size: 16px" />
      <span v-if="unreadTotal > 0" class="msg-badge">{{ unreadTotal > 99 ? '99+' : unreadTotal }}</span>
    </button>

    <Teleport to="body">
      <div v-if="isOpen" class="msg-backdrop" @click="isOpen = false" />
      <div v-if="isOpen" class="msg-dropdown" :style="dropdownStyle">
        <div class="msg-header">
          <span style="font-weight:700;font-size:14px;color:var(--fg-1)">Messagerie</span>
          <a href="/messaging" style="color:var(--halo-600);font-size:12px;font-weight:500;text-decoration:none" @click.prevent="goToAll">
            Ouvrir <i class="pi pi-arrow-right" style="font-size:10px" />
          </a>
        </div>

        <div v-if="loading" style="padding:32px;text-align:center">
          <i class="pi pi-spin pi-spinner" style="font-size:20px;color:var(--halo-500)" />
        </div>

        <div v-else class="msg-list">
          <div
            v-for="conv in conversations.slice(0, 8)"
            :key="conv.id"
            :class="['msg-item', { unread: conv.unread_count > 0 }]"
            @click="openConversation(conv)"
          >
            <div class="msg-avatar">
              <i class="pi pi-users" v-if="conv.type === 'group'" style="font-size:14px" />
              <span v-else>{{ initials(otherParticipant(conv)) }}</span>
            </div>
            <div class="msg-content">
              <div class="msg-title">{{ conv.name || otherParticipant(conv)?.name || 'Conversation' }}</div>
              <div class="msg-preview">{{ truncate(conv.last_message?.body, 60) || 'Aucun message' }}</div>
            </div>
            <span v-if="conv.unread_count > 0" class="msg-unread-dot" />
          </div>

          <div v-if="conversations.length === 0" class="msg-empty">
            <i class="pi pi-comments" style="font-size:28px;color:var(--fg-4);margin-bottom:8px" />
            <p>Aucune conversation</p>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import axios from 'axios'

const isOpen = ref(false)
const loading = ref(false)
const conversations = ref([])
const launcherRef = ref(null)
const dropdownStyle = ref({})

let pollInterval = null

const userId = usePage().props.auth?.user?.id

const unreadTotal = ref(0)

function computeUnreadTotal() {
  unreadTotal.value = conversations.value.reduce((sum, c) => sum + (c.unread_count || 0), 0)
}

function otherParticipant(conv) {
  return (conv.users || []).find((u) => u.id !== userId)
}

function initials(user) {
  const name = user?.name ?? ''
  return name.split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase() || '?'
}

function truncate(str, len) {
  return str && str.length > len ? str.slice(0, len) + '…' : str
}

function toggleDropdown() {
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    positionDropdown()
    loadConversations()
  }
}

function positionDropdown() {
  nextTick(() => {
    if (!launcherRef.value) return
    const rect = launcherRef.value.getBoundingClientRect()
    dropdownStyle.value = {
      position: 'fixed',
      top: `${rect.bottom + 8}px`,
      right: `${window.innerWidth - rect.right}px`,
      zIndex: 9999,
    }
  })
}

async function loadConversations() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/messaging/conversations')
    conversations.value = data.data || []
    computeUnreadTotal()
  } catch {
    conversations.value = []
  } finally {
    loading.value = false
  }
}

function openConversation(conv) {
  isOpen.value = false
  router.visit(`/messaging?conversation=${conv.id}`)
}

function goToAll() {
  isOpen.value = false
  router.visit('/messaging')
}

let echoChannels = []

onMounted(() => {
  loadConversations()
  // Poll every 30 seconds as a fallback in case Echo/Reverb isn't configured,
  // matching NotificationBell.vue's own established convention.
  pollInterval = setInterval(loadConversations, 30000)

  if (window.Echo && userId) {
    // Subscribe to each known conversation once loaded, so a new message
    // bumps the unread badge without a manual refresh.
    axios.get('/api/v1/messaging/conversations').then(({ data }) => {
      (data.data || []).forEach((conv) => {
        const channel = window.Echo.private(`conversation.${conv.id}`)
          .listen('.message.sent', () => {
            loadConversations()
          })
        echoChannels.push(conv.id)
      })
    })
  }
})

onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval)
  if (window.Echo) {
    echoChannels.forEach((id) => window.Echo.leave(`conversation.${id}`))
  }
})
</script>

<style scoped>
.msg-launcher { position: relative; display: flex; align-items: center; }
.msg-btn {
  position: relative;
  background: none;
  border: none;
  cursor: pointer;
  padding: 8px;
  border-radius: 8px;
  color: var(--fg-2);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.15s, color 0.15s;
}
.msg-btn:hover { background: var(--surface-2); color: var(--fg-1) }
.msg-btn.active { background: var(--halo-50); color: var(--halo-700) }
.msg-badge {
  position: absolute;
  top: 2px;
  right: 2px;
  background: var(--red-500);
  color: #fff;
  border-radius: 10px;
  font-size: 9px;
  font-weight: 800;
  min-width: 16px;
  height: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 3px;
  border: 2px solid var(--surface-0, #fff);
  line-height: 1;
}
.msg-backdrop { position: fixed; inset: 0; z-index: 9998; }
.msg-dropdown {
  width: 320px;
  background: var(--surface-0, #fff);
  border: 1px solid var(--border-subtle, var(--border-subtle));
  border-radius: 12px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  max-height: 480px;
}
.msg-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px;
  border-bottom: 1px solid var(--border-subtle, var(--border-subtle));
}
.msg-list { overflow-y: auto; flex: 1 }
.msg-item {
  display: flex;
  gap: 10px;
  padding: 12px 16px;
  border-bottom: 1px solid var(--border-subtle, var(--border-subtle));
  cursor: pointer;
  transition: background 0.15s;
}
.msg-item:hover { background: var(--surface-2, #f4f4f7) }
.msg-item.unread { background: var(--halo-50, #f0f2ff) }
.msg-item:last-child { border-bottom: none }
.msg-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--halo-100, #e0e4ff);
  color: var(--halo-700, #3949ab);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: 11px;
  font-weight: 700;
}
.msg-content { flex: 1; min-width: 0 }
.msg-title { font-size: 13px; font-weight: 600; color: var(--fg-1, #1a1a2e); margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis }
.msg-preview { font-size: 12px; color: var(--fg-3, #6b6b80); white-space: nowrap; overflow: hidden; text-overflow: ellipsis }
.msg-unread-dot { width: 8px; height: 8px; background: var(--halo-600, #3949ab); border-radius: 50%; flex-shrink: 0; margin-top: 4px; }
.msg-empty { display: flex; flex-direction: column; align-items: center; padding: 40px 16px; color: var(--fg-3, #6b6b80); font-size: 13px; gap: 4px; }
</style>
