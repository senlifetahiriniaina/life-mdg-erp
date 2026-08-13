<template>
  <div class="fixed right-0 top-0 h-full w-96 bg-surface-0 dark:bg-surface-800 border-l border-surface-200 dark:border-surface-700 z-50 flex flex-col shadow-xl">

    <div aria-live="polite" aria-atomic="true" class="sr-only" ref="liveRef">{{ liveMessage }}</div>

    <!-- Header -->
    <div class="flex items-center justify-between p-4 border-b border-surface-200 dark:border-surface-700">
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 bg-violet-600 rounded-lg flex items-center justify-center">
          <i class="pi pi-sparkles text-white text-sm" />
        </div>
        <div>
          <h3 class="font-semibold text-sm">{{ $t('ai.assistant') }}</h3>
          <p class="text-xs text-surface-500">Powered by Claude</p>
        </div>
      </div>
      <Button icon="pi pi-times" text rounded size="small" @click="$emit('close')" />
    </div>

    <!-- Messages -->
    <div ref="messagesContainer" class="flex-1 overflow-y-auto p-4 space-y-4">
      <!-- Welcome message -->
      <div v-if="messages.length === 0" class="text-center py-8">
        <div class="w-16 h-16 bg-violet-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
          <i class="pi pi-sparkles text-violet-600 text-2xl" />
        </div>
        <p class="text-sm text-surface-500">{{ $t('ai.ask_placeholder') }}</p>
        <!-- Quick suggestions -->
        <div class="mt-4 space-y-2">
          <button
            v-for="suggestion in quickSuggestions"
            :key="suggestion"
            @click="sendMessage(suggestion)"
            class="w-full text-left px-3 py-2 bg-surface-100 dark:bg-surface-700 rounded-lg text-xs hover:bg-surface-200 transition-colors"
          >
            {{ suggestion }}
          </button>
        </div>
      </div>

      <!-- Chat messages -->
      <div
        v-for="(msg, index) in messages"
        :key="index"
        :class="['flex', msg.role === 'user' ? 'justify-end' : 'justify-start']"
      >
        <div
          :class="[
            'max-w-[85%] px-4 py-2.5 rounded-2xl text-sm',
            msg.role === 'user'
              ? 'bg-primary-600 text-white rounded-br-none'
              : 'bg-surface-100 dark:bg-surface-700 rounded-bl-none'
          ]"
        >
          <div v-if="msg.role === 'assistant'" class="whitespace-pre-wrap" v-html="safeFormat(msg.content)" />
          <span v-else>{{ msg.content }}</span>
        </div>
      </div>

      <!-- Typing indicator -->
      <div v-if="isLoading" class="flex justify-start">
        <div class="bg-surface-100 dark:bg-surface-700 px-4 py-3 rounded-2xl rounded-bl-none">
          <span class="flex gap-1">
            <span v-for="i in 3" :key="i" class="w-2 h-2 bg-surface-400 rounded-full animate-bounce" :style="{ animationDelay: `${i * 0.15}s` }" />
          </span>
        </div>
      </div>
    </div>

    <!-- Input -->
    <div class="p-4 border-t border-surface-200 dark:border-surface-700">
      <div class="flex gap-2">
        <Textarea
          v-model="inputMessage"
          :placeholder="$t('ai.ask_placeholder')"
          class="flex-1 resize-none text-sm"
          rows="2"
          auto-resize
          @keydown.enter.prevent="handleEnter"
        />
        <Button
          icon="pi pi-send"
          :loading="isLoading"
          :disabled="!inputMessage.trim()"
          @click="sendMessage()"
          class="self-end"
        />
      </div>
      <p class="text-xs text-surface-400 mt-1">Shift+Enter for new line</p>
    </div>
  </div>
</template>

<script setup>
import { ref, nextTick, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import Textarea from 'primevue/textarea'
import axios from 'axios'

const props = defineProps({
  guidance: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['close'])
const { locale } = useI18n()
const page = usePage()

const messages = ref([])
const inputMessage = ref('')
const isLoading = ref(false)
const messagesContainer = ref(null)
const liveRef = ref(null)
const liveMessage = ref('')

watch(() => props.guidance, (guidance) => {
  if (guidance?.what_to_do) {
    liveMessage.value = `Assistance IA disponible : ${guidance.what_to_do}`
  }
})

const quickSuggestions = [
  'Show me today\'s sales summary',
  'Which products are running low on stock?',
  'What are the top 5 overdue invoices?',
  'Give me an HR overview for this month',
]

const sendMessage = async (text = null) => {
  const question = text || inputMessage.value.trim()
  if (!question) return

  messages.value.push({ role: 'user', content: question })
  inputMessage.value = ''
  isLoading.value = true

  await scrollToBottom()

  try {
    const response = await axios.post('/api/v1/ai/chat', {
      question,
      module: page.props.currentModule || null,
    })

    messages.value.push({ role: 'assistant', content: response.data.answer })
  } catch (error) {
    messages.value.push({
      role: 'assistant',
      content: 'Sorry, I encountered an error. Please try again.',
    })
  } finally {
    isLoading.value = false
    await scrollToBottom()
  }
}

const handleEnter = (e) => {
  if (!e.shiftKey) sendMessage()
}

// Escape raw HTML first, then apply safe inline markdown (bold/italic/newline only)
const safeFormat = (text) => {
  const escaped = text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
  return escaped
    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
    .replace(/\*(.*?)\*/g, '<em>$1</em>')
    .replace(/\n/g, '<br>')
}

const scrollToBottom = async () => {
  await nextTick()
  if (messagesContainer.value) {
    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
  }
}
</script>
