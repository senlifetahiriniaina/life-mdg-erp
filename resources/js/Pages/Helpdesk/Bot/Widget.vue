<template>
  <!-- Standalone embeddable widget — no AppLayout -->
  <div class="bot-widget">
    <div class="bot-header">
      <div class="flex items-center gap-2">
        <div class="bot-avatar">🤖</div>
        <div>
          <p class="text-sm font-semibold">Assistant IA</p>
          <p class="text-xs text-gray-400">Comment puis-je vous aider ?</p>
        </div>
      </div>
    </div>

    <div class="bot-body">
      <!-- Messages -->
      <div class="bot-messages" ref="messagesEl">
        <div v-if="messages.length === 0" class="bot-empty">
          <p class="text-sm text-gray-400 text-center">Posez votre question ci-dessous</p>
        </div>
        <div v-for="(msg, i) in messages" :key="i" :class="['bot-msg', `bot-msg--${msg.role}`]">
          <p class="text-sm">{{ msg.text }}</p>

          <!-- Article suggestions -->
          <div v-if="msg.articles && msg.articles.length > 0" class="article-suggestions">
            <p class="text-xs text-gray-500 dark:text-surface-400 mb-2">Articles suggérés :</p>
            <a
              v-for="article in msg.articles"
              :key="article.id"
              :href="`/helpdesk/kb/${article.slug}`"
              target="_blank"
              class="article-link"
            >
              <i class="pi pi-file-o text-blue-500" />
              <span>{{ article.title }}</span>
            </a>
          </div>

          <!-- Helpful question -->
          <div v-if="msg.showHelpful" class="helpful-q">
            <p class="text-xs text-gray-500 dark:text-surface-400 mb-2">Cela a-t-il répondu à votre question ?</p>
            <div class="flex gap-2">
              <button class="btn-helpful yes" @click="deflect(true, msg)">
                <i class="pi pi-check" /> Oui
              </button>
              <button class="btn-helpful no" @click="deflect(false, msg)">
                <i class="pi pi-times" /> Non
              </button>
            </div>
          </div>

          <!-- Create ticket CTA -->
          <div v-if="msg.showTicketCta">
            <a href="/helpdesk/tickets/new" class="btn-create-ticket">
              <i class="pi pi-ticket" />
              Créer un ticket
            </a>
          </div>
        </div>

        <div v-if="loading" class="bot-msg bot-msg--bot">
          <div class="typing-dots">
            <span /><span /><span />
          </div>
        </div>
      </div>

      <!-- Input -->
      <div class="bot-input-row">
        <input
          v-model="question"
          class="bot-input"
          placeholder="Votre question..."
          @keydown.enter="ask"
          :disabled="loading"
        />
        <button class="bot-send" @click="ask" :disabled="loading || !question.trim()">
          <i class="pi pi-send" />
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, nextTick } from 'vue'
import axios from 'axios'

const question = ref('')
const loading = ref(false)
const messagesEl = ref<HTMLElement | null>(null)
const sessionId = ref(Math.random().toString(36).slice(2))

interface Message {
  role: 'user' | 'bot'
  text: string
  articles?: any[]
  showHelpful?: boolean
  showTicketCta?: boolean
  articleId?: number | null
  questionText?: string
}

const messages = ref<Message[]>([])

async function ask() {
  if (!question.value.trim() || loading.value) return

  const q = question.value.trim()
  question.value = ''
  messages.value.push({ role: 'user', text: q })

  loading.value = true
  try {
    const { data } = await axios.post('/api/v1/helpdesk/bot/ask', {
      question: q,
      session_id: sessionId.value,
    })

    const botMsg: Message = {
      role: 'bot',
      text: data.articles?.length > 0
        ? `J'ai trouvé ${data.articles.length} article(s) qui pourraient vous aider :`
        : "Je n'ai pas trouvé d'article correspondant. Je peux vous aider à créer un ticket.",
      articles: data.articles ?? [],
      showHelpful: data.articles?.length > 0,
      showTicketCta: data.articles?.length === 0,
      articleId: data.articles?.[0]?.id ?? null,
      questionText: q,
    }
    messages.value.push(botMsg)
  } finally {
    loading.value = false
    await nextTick()
    if (messagesEl.value) {
      messagesEl.value.scrollTop = messagesEl.value.scrollHeight
    }
  }
}

async function deflect(helped: boolean, msg: Message) {
  msg.showHelpful = false
  if (!helped) {
    msg.showTicketCta = true
  }

  await axios.post('/api/v1/helpdesk/bot/deflect', {
    question:           msg.questionText ?? '',
    matched_article_id: msg.articleId ?? null,
    deflected:          helped,
    ticket_created:     false,
    session_id:         sessionId.value,
  }).catch(() => {})
}
</script>

<style scoped>
.bot-widget {
  width: 360px;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  overflow: hidden;
  background: white;
  box-shadow: 0 4px 24px rgba(0,0,0,0.10);
  font-family: inherit;
}
.bot-header {
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: white;
  padding: 12px 16px;
}
.bot-avatar { font-size: 24px; }
.bot-body { display: flex; flex-direction: column; height: 400px; }
.bot-messages { flex: 1; overflow-y: auto; padding: 12px; display: flex; flex-direction: column; gap: 10px; }
.bot-empty { display: flex; align-items: center; justify-content: center; height: 100%; }
.bot-msg { max-width: 85%; padding: 8px 12px; border-radius: 12px; }
.bot-msg--user { align-self: flex-end; background: #6366f1; color: white; border-bottom-right-radius: 4px; }
.bot-msg--bot { align-self: flex-start; background: #f3f4f6; border-bottom-left-radius: 4px; }
.article-suggestions { margin-top: 8px; display: flex; flex-direction: column; gap: 6px; }
.article-link { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #3b82f6; text-decoration: none; padding: 4px 6px; background: white; border-radius: 6px; }
.article-link:hover { background: #eff6ff; }
.helpful-q { margin-top: 8px; }
.btn-helpful { font-size: 12px; padding: 4px 10px; border-radius: 6px; border: none; cursor: pointer; }
.btn-helpful.yes { background: #d1fae5; color: #065f46; }
.btn-helpful.no { background: #fee2e2; color: #991b1b; }
.btn-create-ticket { display: inline-flex; align-items: center; gap: 6px; margin-top: 8px; font-size: 12px; padding: 6px 12px; background: #6366f1; color: white; border-radius: 8px; text-decoration: none; }
.bot-input-row { display: flex; gap: 8px; padding: 10px 12px; border-top: 1px solid #e5e7eb; }
.bot-input { flex: 1; border: 1px solid #d1d5db; border-radius: 8px; padding: 6px 10px; font-size: 13px; outline: none; }
.bot-input:focus { border-color: #6366f1; }
.bot-send { width: 34px; height: 34px; border-radius: 8px; background: #6366f1; color: white; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.bot-send:disabled { opacity: 0.5; cursor: not-allowed; }
.typing-dots { display: flex; gap: 4px; }
.typing-dots span { width: 6px; height: 6px; border-radius: 50%; background: #9ca3af; animation: bounce 1.2s infinite; }
.typing-dots span:nth-child(2) { animation-delay: 0.2s; }
.typing-dots span:nth-child(3) { animation-delay: 0.4s; }
@keyframes bounce { 0%, 80%, 100% { transform: scale(0.8); opacity: 0.5; } 40% { transform: scale(1.1); opacity: 1; } }
</style>
