<template>
  <div class="space-y-6">
    <!-- Breadcrumb -->
    <nav class="flex items-center gap-2 text-sm text-gray-500" aria-label="Fil d'Ariane">
      <a href="/helpdesk/forum" class="hover:underline text-blue-600">Forums</a>
      <span aria-hidden="true">/</span>
      <a
        v-if="thread?.forum"
        :href="`/helpdesk/forum/${thread.forum.id}`"
        class="hover:underline text-blue-600"
      >{{ thread.forum.name }}</a>
      <span aria-hidden="true">/</span>
      <span class="text-gray-700 dark:text-gray-300 truncate max-w-xs">{{ thread?.title }}</span>
    </nav>

    <!-- Thread -->
    <article
      v-if="thread"
      role="article"
      :aria-label="`Sujet : ${thread.title}`"
      class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-6 space-y-4"
    >
      <div class="flex items-start gap-4">
        <!-- Vote column -->
        <div class="flex flex-col items-center gap-1 text-gray-400 flex-shrink-0">
          <button
            class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors"
            aria-label="Voter pour ce sujet"
            @click="voteThread(1)"
          >▲</button>
          <span class="text-sm font-semibold text-gray-700 dark:text-gray-300" aria-live="polite">{{ thread.upvotes ?? 0 }}</span>
          <button
            class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors"
            aria-label="Voter contre ce sujet"
            @click="voteThread(-1)"
          >▼</button>
        </div>

        <!-- Content -->
        <div class="flex-1 min-w-0">
          <div class="flex items-start justify-between gap-2 flex-wrap mb-3">
            <h1 class="text-xl font-bold text-gray-900 dark:text-white">{{ thread.title }}</h1>
            <div class="flex gap-2">
              <span
                v-if="thread.is_answered"
                class="px-2 py-0.5 rounded-full bg-green-100 text-green-700 text-xs font-medium"
                aria-label="Ce sujet a une réponse acceptée"
              >✓ Résolu</span>
              <span
                :class="statusColor(thread.status)"
                class="px-2 py-0.5 rounded-full text-xs font-medium"
              >{{ statusLabel(thread.status) }}</span>
            </div>
          </div>

          <div class="prose prose-sm dark:prose-invert max-w-none text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ thread.content }}</div>

          <div class="flex items-center gap-3 mt-4 text-xs text-gray-500 flex-wrap">
            <span>par <strong class="text-gray-700 dark:text-gray-200">{{ thread.author?.name }}</strong></span>
            <span>{{ formatDate(thread.created_at) }}</span>
            <span>{{ thread.views ?? 0 }} vue(s)</span>
            <button
              v-if="canClose"
              class="ml-auto px-3 py-1 rounded border text-xs hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-300"
              @click="closeThread"
            >Fermer le sujet</button>
          </div>
        </div>
      </div>
    </article>

    <!-- Replies -->
    <section aria-labelledby="replies-heading">
      <h2
        id="replies-heading"
        class="text-base font-semibold text-gray-700 dark:text-gray-200 mb-3"
      >
        {{ thread?.replies?.length ?? 0 }} réponse(s)
      </h2>

      <div
        v-if="thread?.replies?.length"
        role="list"
        aria-label="Réponses"
        class="space-y-4"
      >
        <article
          v-for="reply in thread.replies"
          :key="reply.id"
          role="listitem"
          :aria-label="`Réponse de ${reply.author?.name}`"
          :class="[
            'rounded-xl border p-5 bg-white dark:bg-gray-800',
            reply.is_accepted_answer
              ? 'border-green-400 bg-green-50 dark:bg-green-900/20'
              : 'border-gray-100 dark:border-gray-700'
          ]"
        >
          <!-- Accepted badge -->
          <div
            v-if="reply.is_accepted_answer"
            class="flex items-center gap-2 text-green-700 text-sm font-medium mb-3"
            role="status"
            aria-label="Réponse acceptée"
          >
            <span aria-hidden="true">✓</span> Réponse acceptée
          </div>

          <div class="flex items-start gap-4">
            <!-- Vote column -->
            <div class="flex flex-col items-center gap-1 text-gray-400 flex-shrink-0">
              <button
                class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors"
                :aria-label="`Voter pour la réponse de ${reply.author?.name}`"
                @click="voteReply(reply, 1)"
              >▲</button>
              <span
                class="text-sm font-semibold text-gray-700 dark:text-gray-300"
                aria-live="polite"
                :aria-label="`${reply.upvotes ?? 0} votes`"
              >{{ reply.upvotes ?? 0 }}</span>
              <button
                class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors"
                :aria-label="`Voter contre la réponse de ${reply.author?.name}`"
                @click="voteReply(reply, -1)"
              >▼</button>
            </div>

            <!-- Reply content -->
            <div class="flex-1 min-w-0">
              <div class="prose prose-sm dark:prose-invert max-w-none text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ reply.content }}</div>

              <div class="flex items-center gap-3 mt-4 text-xs text-gray-500 flex-wrap">
                <span>par <strong class="text-gray-700 dark:text-gray-200">{{ reply.author?.name }}</strong></span>
                <span>{{ formatDate(reply.created_at) }}</span>

                <!-- Accept answer button (thread author only) -->
                <button
                  v-if="canAcceptAnswer && !reply.is_accepted_answer"
                  class="ml-auto px-3 py-1 rounded bg-green-50 border border-green-300 text-green-700 text-xs font-medium hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-green-400"
                  :aria-label="`Marquer la réponse de ${reply.author?.name} comme acceptée`"
                  @click="acceptAnswer(reply)"
                >
                  ✓ Accepter cette réponse
                </button>
              </div>
            </div>
          </div>
        </article>
      </div>
    </section>

    <!-- Reply form -->
    <section
      v-if="thread && thread.status !== 'closed'"
      aria-labelledby="reply-form-heading"
      class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-5 space-y-4"
    >
      <h2 id="reply-form-heading" class="text-base font-semibold text-gray-700 dark:text-gray-200">Votre réponse</h2>
      <div>
        <label class="sr-only" for="reply-content">Contenu de votre réponse</label>
        <textarea
          id="reply-content"
          v-model="replyContent"
          rows="4"
          class="w-full border border-gray-200 dark:border-gray-600 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
          placeholder="Rédigez votre réponse…"
        />
      </div>
      <div class="flex justify-end">
        <button
          class="px-5 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
          :disabled="submitting || !replyContent.trim()"
          @click="submitReply"
        >
          {{ submitting ? 'Envoi…' : 'Publier la réponse' }}
        </button>
      </div>
    </section>

    <div
      v-if="thread?.status === 'closed'"
      class="rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-500 text-sm text-center py-4"
      role="status"
    >
      Ce sujet est fermé — les nouvelles réponses ne sont pas acceptées.
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'

const props = defineProps({
  threadId: { type: [Number, String], required: true },
  currentUserId: { type: [Number, String], default: null },
})

const thread = ref(null)
const replyContent = ref('')
const submitting = ref(false)

const canAcceptAnswer = computed(() =>
  thread.value && String(thread.value.author?.id) === String(props.currentUserId)
)

const canClose = computed(() =>
  thread.value && String(thread.value.author?.id) === String(props.currentUserId)
)

async function load() {
  try {
    const { data } = await axios.get(`/api/v1/helpdesk/threads/${props.threadId}`)
    thread.value = data
  } catch (e) {
    console.error(e)
  }
}

async function submitReply() {
  if (!replyContent.value.trim()) return
  submitting.value = true
  try {
    await axios.post(`/api/v1/helpdesk/threads/${props.threadId}/replies`, {
      content: replyContent.value,
    })
    replyContent.value = ''
    await load()
  } catch (e) {
    console.error(e)
  } finally {
    submitting.value = false
  }
}

async function acceptAnswer(reply) {
  try {
    await axios.post(`/api/v1/helpdesk/threads/${props.threadId}/replies/${reply.id}/accept-answer`)
    await load()
  } catch (e) {
    console.error(e)
  }
}

async function voteThread(direction) {
  try {
    const { data } = await axios.post(`/api/v1/helpdesk/threads/${props.threadId}/vote`, { direction })
    if (thread.value) thread.value.upvotes = data.upvotes
  } catch (e) {
    console.error(e)
  }
}

async function voteReply(reply, direction) {
  try {
    const { data } = await axios.post(`/api/v1/helpdesk/replies/${reply.id}/vote`, { direction })
    reply.upvotes = data.upvotes
  } catch (e) {
    console.error(e)
  }
}

async function closeThread() {
  if (!confirm('Fermer ce sujet ? Les nouvelles réponses ne seront plus acceptées.')) return
  try {
    const { data } = await axios.post(`/api/v1/helpdesk/threads/${props.threadId}/close`)
    if (thread.value) thread.value.status = data.status
  } catch (e) {
    console.error(e)
  }
}

function formatDate(date) {
  if (!date) return ''
  return new Intl.DateTimeFormat('fr', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(date))
}

function statusLabel(status) {
  return { open: 'Ouvert', closed: 'Fermé', pinned: 'Épinglé' }[status] ?? status
}

function statusColor(status) {
  return {
    open: 'bg-blue-100 text-blue-700',
    closed: 'bg-gray-100 text-gray-600',
    pinned: 'bg-yellow-100 text-yellow-700',
  }[status] ?? 'bg-gray-100 text-gray-600'
}

onMounted(load)
</script>
