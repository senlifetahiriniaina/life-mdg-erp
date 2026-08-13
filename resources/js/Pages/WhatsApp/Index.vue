<template>
  <AppLayout>
    <Head title="WhatsApp" />

    <div class="wa-shell">
      <!-- LEFT: Conversation list -->
      <div class="wa-sidebar">
        <div class="wa-sidebar-head">
          <i class="pi pi-comments" style="font-size:16px;color:#25D366" />
          <span style="font-size:14px;font-weight:600;color:var(--fg-1)">WhatsApp</span>
          <span class="wa-count-pill">{{ conversations.total }} chats</span>
        </div>

        <div class="wa-search">
          <i class="pi pi-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--fg-4);font-size:12px;pointer-events:none" />
          <input v-model="search" placeholder="Rechercher…" class="wa-search-input" />
        </div>

        <div class="wa-conv-list">
          <div v-if="filteredConversations.length === 0" class="wa-empty">
            <i class="pi pi-comments" style="font-size:32px;color:var(--fg-4);margin-bottom:8px" />
            <p>Aucune conversation</p>
          </div>
          <button
            v-for="conv in filteredConversations"
            :key="conv.id"
            class="wa-conv-item"
            :class="{ 'wa-conv-active': selectedConv?.id === conv.id }"
            @click="selectConv(conv)"
          >
            <div class="wa-conv-avatar">{{ initials(conv.contact) }}</div>
            <div style="flex:1;min-width:0">
              <div style="display:flex;align-items:center;justify-content:space-between">
                <p style="font-size:13px;font-weight:500;color:var(--fg-1);margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:160px">
                  {{ conv.contact?.name ?? conv.contact?.phone ?? 'Inconnu' }}
                </p>
                <span style="font-size:11px;color:var(--fg-4);flex-shrink:0">{{ formatRelative(conv.last_message_at) }}</span>
              </div>
              <div style="display:flex;align-items:center;gap:4px;margin-top:3px">
                <span :class="['wh-badge', convStatusClass(conv.status)]" style="font-size:10px;padding:1px 5px">{{ convStatusLabel(conv.status) }}</span>
                <span v-if="conv.is_ai_handled" style="font-size:10px;color:#8B5CF6;font-weight:600">AI</span>
                <span v-if="conv.unread_count" class="wa-unread">{{ conv.unread_count > 9 ? '9+' : conv.unread_count }}</span>
              </div>
            </div>
          </button>
        </div>
      </div>

      <!-- RIGHT: Message thread -->
      <div class="wa-main">
        <div v-if="!selectedConv" class="wa-placeholder">
          <i class="pi pi-comments" style="font-size:48px;color:var(--fg-4);margin-bottom:12px" />
          <p style="font-size:16px;font-weight:500;color:var(--fg-2)">Sélectionnez une conversation</p>
          <p style="font-size:13px;color:var(--fg-3)">Choisissez un chat à gauche pour commencer</p>
        </div>

        <template v-else>
          <div class="wa-thread-head">
            <div class="wa-conv-avatar" style="width:36px;height:36px;font-size:12px">{{ initials(selectedConv.contact) }}</div>
            <div style="flex:1">
              <p style="font-size:14px;font-weight:600;color:var(--fg-1);margin:0">{{ selectedConv.contact?.name ?? selectedConv.contact?.phone ?? 'Inconnu' }}</p>
              <p style="font-size:12px;color:var(--fg-3);margin:2px 0 0">{{ selectedConv.contact?.phone ?? '' }}</p>
            </div>
            <div style="display:flex;align-items:center;gap:8px">
              <span :class="['wh-badge', convStatusClass(selectedConv.status)]">{{ convStatusLabel(selectedConv.status) }}</span>
              <button
                v-if="selectedConv.status === 'open'"
                class="btn btn-secondary"
                style="font-size:12px;padding:5px 10px;color:var(--success-fg)"
                @click="resolveConv"
                :disabled="resolvingConv"
              >
                <i class="pi pi-check" style="font-size:12px" /> Résoudre
              </button>
            </div>
          </div>

          <div ref="messagesArea" class="wa-messages">
            <div v-if="loadingMessages" style="display:flex;justify-content:center;padding:32px">
              <i class="pi pi-spin pi-spinner" style="font-size:20px;color:var(--halo-500)" />
            </div>
            <div v-else-if="messages.length === 0" class="wa-empty" style="flex:1">
              <i class="pi pi-comment" style="font-size:28px;color:var(--fg-4);margin-bottom:8px" />
              <p>Aucun message</p>
            </div>
            <template v-else>
              <div
                v-for="msg in messages"
                :key="msg.id"
                style="display:flex"
                :style="{ justifyContent: msg.direction === 'outbound' ? 'flex-end' : 'flex-start' }"
              >
                <div class="wa-bubble" :class="msg.direction === 'outbound' ? 'wa-bubble-out' : 'wa-bubble-in'">
                  <p style="margin:0;white-space:pre-wrap;word-break:break-words">{{ msg.body }}</p>
                  <p style="margin:3px 0 0;text-align:right;font-size:10px;opacity:0.65">{{ formatTime(msg.sent_at ?? msg.created_at) }}</p>
                </div>
              </div>
            </template>
          </div>

          <div class="wa-compose">
            <Textarea v-model="replyText" rows="2" placeholder="Tapez un message…" auto-resize style="flex:1;font-size:13px" @keydown.enter.exact.prevent="sendReply" />
            <button
              class="wa-send-btn"
              :disabled="!replyText.trim() || sendingReply"
              @click="sendReply"
            >
              <i :class="sendingReply ? 'pi pi-spin pi-spinner' : 'pi pi-send'" style="font-size:14px" />
            </button>
          </div>
          <p style="padding:4px 16px 6px;font-size:11px;color:var(--fg-4)">Entrée pour envoyer · Maj+Entrée pour une nouvelle ligne</p>
        </template>
      </div>
    </div>
    <GuidedTour tour-id="whatsapp-inbox" :steps="tourSteps" />
  </AppLayout>
</template>

<script setup>
import { ref, computed, nextTick } from 'vue'
import { Head } from '@inertiajs/vue3'
import Textarea from 'primevue/textarea'
import AppLayout from '@/Layouts/AppLayout.vue'
import GuidedTour from '@/Components/UI/GuidedTour.vue'

const tourSteps = [
  { tag: 'Conversations · Étape 1 / 5', icon: 'pi-comments', title: 'Boîte de réception WhatsApp', description: 'La colonne de gauche liste toutes les conversations triées par dernière activité. Les badges indiquent les messages non lus.' },
  { tag: 'Statuts · Étape 2 / 5', icon: 'pi-tag', title: 'Statuts de conversation', description: 'Ouvert, en attente, fermé — filtrez les conversations par statut pour prioriser votre file de travail.' },
  { tag: 'AI · Étape 3 / 5', icon: 'pi-sparkles', title: 'Prise en main IA', description: 'Les conversations marquées « AI » sont gérées automatiquement par le chatbot Claude. Cliquez « Prendre la main » pour reprendre manuellement.' },
  { tag: 'Messages · Étape 4 / 5', icon: 'pi-send', title: 'Envoyer un message', description: 'Saisissez votre réponse dans le champ en bas et appuyez sur Entrée ou le bouton d\'envoi pour répondre au client.' },
  { tag: 'Assign · Étape 5 / 5', icon: 'pi-user', title: 'Assigner à un agent', description: 'Chaque conversation peut être assignée à un agent de l\'équipe pour une gestion personnalisée des cas complexes.' },
]

const props = defineProps({
  conversations: { type: Object, required: true },
})

const search           = ref('')
const selectedConv     = ref(null)
const messages         = ref([])
const loadingMessages  = ref(false)
const replyText        = ref('')
const sendingReply     = ref(false)
const resolvingConv    = ref(false)
const messagesArea     = ref(null)

const filteredConversations = computed(() => {
  if (!search.value) return props.conversations.data
  const q = search.value.toLowerCase()
  return props.conversations.data.filter((c) =>
    (c.contact?.name ?? '').toLowerCase().includes(q) ||
    (c.contact?.phone ?? '').includes(q)
  )
})

const csrf = () => document.querySelector('meta[name=csrf-token]')?.content

const selectConv = async (conv) => {
  selectedConv.value = conv
  replyText.value = ''
  messages.value = []
  loadingMessages.value = true
  try {
    const res = await fetch(`/api/v1/whatsapp/conversations/${conv.id}/messages`, { headers: { Accept: 'application/json' } })
    if (res.ok) { const json = await res.json(); messages.value = json.data ?? json ?? [] }
  } finally {
    loadingMessages.value = false
    await nextTick()
    if (messagesArea.value) messagesArea.value.scrollTop = messagesArea.value.scrollHeight
  }
}

const sendReply = async () => {
  const text = replyText.value.trim()
  if (!text || !selectedConv.value) return
  sendingReply.value = true
  try {
    const res = await fetch(`/api/v1/whatsapp/conversations/${selectedConv.value.id}/messages`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
      body: JSON.stringify({ body: text, direction: 'outbound' }),
    })
    if (res.ok) {
      messages.value.push(await res.json())
      replyText.value = ''
      await nextTick()
      if (messagesArea.value) messagesArea.value.scrollTop = messagesArea.value.scrollHeight
    }
  } finally { sendingReply.value = false }
}

const resolveConv = async () => {
  if (!selectedConv.value) return
  resolvingConv.value = true
  try {
    const res = await fetch(`/api/v1/whatsapp/conversations/${selectedConv.value.id}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
      body: JSON.stringify({ status: 'resolved' }),
    })
    if (res.ok) selectedConv.value = { ...selectedConv.value, status: 'resolved' }
  } finally { resolvingConv.value = false }
}

const initials   = (c) => (c?.name ?? c?.phone ?? '?').slice(0, 2).toUpperCase()
const convStatusClass = (s) => ({ open:'wh-badge-green', closed:'wh-badge-slate', pending:'wh-badge-amber', resolved:'wh-badge-blue' }[s] ?? 'wh-badge-slate')
const convStatusLabel = (s) => ({ open:'Ouvert', closed:'Fermé', pending:'En attente', resolved:'Résolu' }[s] ?? s)

const formatRelative = (v) => {
  if (!v) return ''
  const d = new Date(v), diffMins = Math.floor((Date.now() - d) / 60000)
  if (diffMins < 1) return 'maintenant'
  if (diffMins < 60) return `${diffMins}m`
  const h = Math.floor(diffMins / 60)
  if (h < 24) return `${h}h`
  return d.toLocaleDateString('fr-FR', { month: 'short', day: 'numeric' })
}

const formatTime = (v) => v ? new Date(v).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : ''
</script>

<style scoped>
.wa-shell { display:flex; height:calc(100vh - 100px); border:1px solid var(--border-subtle); border-radius:var(--r-lg); overflow:hidden; background:var(--bg-canvas); }
.wa-sidebar { width:300px; flex-shrink:0; border-right:1px solid var(--border-subtle); display:flex; flex-direction:column; }
.wa-sidebar-head { display:flex; align-items:center; gap:8px; padding:14px 16px; border-bottom:1px solid var(--border-subtle); }
.wa-count-pill { margin-left:auto; background:#DCFCE7; color:#15803D; border-radius:999px; padding:2px 8px; font-size:11px; font-weight:600; }
.wa-search { position:relative; padding:8px 12px; border-bottom:1px solid var(--border-subtle); }
.wa-search-input { width:100%; padding:7px 12px 7px 30px; border:1px solid var(--border-subtle); border-radius:var(--r-md); background:var(--bg-sunken); font-size:13px; color:var(--fg-1); outline:none; }
.wa-search-input:focus { border-color:var(--halo-500); box-shadow:0 0 0 3px rgba(46,91,232,0.1); }
.wa-conv-list { flex:1; overflow-y:auto; }
.wa-conv-item { width:100%; text-align:left; display:flex; align-items:flex-start; gap:10px; padding:12px 14px; border-bottom:1px solid var(--border-subtle); background:none; border-left:none; border-right:none; border-top:none; cursor:pointer; transition:background var(--dur-fast); }
.wa-conv-item:hover { background:var(--bg-sunken); }
.wa-conv-active { background:var(--halo-50); }
.wa-conv-avatar { width:38px; height:38px; border-radius:50%; background:#D1FAE5; color:#15803D; font-size:13px; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.wa-unread { margin-left:auto; min-width:18px; height:18px; background:#25D366; color:#fff; border-radius:999px; font-size:10px; font-weight:700; display:flex; align-items:center; justify-content:center; padding:0 4px; }
.wa-empty { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:40px 16px; color:var(--fg-3); font-size:13px; }
.wa-main { flex:1; display:flex; flex-direction:column; min-width:0; }
.wa-placeholder { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; }
.wa-thread-head { display:flex; align-items:center; gap:10px; padding:12px 18px; border-bottom:1px solid var(--border-subtle); }
.wa-messages { flex:1; overflow-y:auto; padding:16px; display:flex; flex-direction:column; gap:8px; background:var(--bg-sunken); }
.wa-bubble { max-width:72%; border-radius:12px; padding:8px 12px; font-size:13px; line-height:1.4; }
.wa-bubble-out { background:#25D366; color:#fff; border-bottom-right-radius:3px; }
.wa-bubble-in { background:var(--bg-canvas); color:var(--fg-1); border:1px solid var(--border-subtle); border-bottom-left-radius:3px; }
.wa-compose { display:flex; align-items:flex-end; gap:8px; padding:10px 14px; border-top:1px solid var(--border-subtle); }
.wa-send-btn { width:38px; height:38px; border-radius:50%; background:var(--halo-500); color:#fff; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:background var(--dur-base); }
.wa-send-btn:hover:not(:disabled) { background:var(--halo-700); }
.wa-send-btn:disabled { opacity:0.5; cursor:not-allowed; }
.btn { font-family:var(--font-sans); font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:background var(--dur-base); line-height:1.2; }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover:not(:disabled) { background:var(--bg-sunken); }
.btn-secondary:disabled { opacity:0.6; cursor:not-allowed; }
.wh-badge { display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:var(--r-pill); font-size:11px; font-weight:500; }
.wh-badge-green { background:var(--success-bg); color:var(--success-fg); }
.wh-badge-blue { background:var(--halo-50); color:var(--halo-700); }
.wh-badge-amber { background:var(--warn-bg); color:var(--warn-fg); }
.wh-badge-slate { background:var(--bg-sunken); color:var(--fg-2); }
</style>
