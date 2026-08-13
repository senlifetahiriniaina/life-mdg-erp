<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-3 flex-wrap">
      <a
        href="/helpdesk/forum"
        class="text-blue-600 hover:underline text-sm flex items-center gap-1"
        aria-label="Retour à la liste des forums"
      >
        <span aria-hidden="true">←</span> Forums
      </a>
      <span class="text-gray-400">/</span>
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ forum?.name }}</h1>
      <span
        v-if="forum?.category"
        class="ml-auto px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-700"
      >{{ forum.category }}</span>
    </div>

    <p v-if="forum?.description" class="text-sm text-gray-500 dark:text-gray-400">{{ forum.description }}</p>

    <!-- Actions bar -->
    <div class="flex items-center gap-3 flex-wrap">
      <button
        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
        aria-label="Créer un nouveau sujet dans ce forum"
        @click="showNewThread = true"
      >
        <span aria-hidden="true">+</span> Nouveau sujet
      </button>

      <!-- Status filter -->
      <select
        v-model="statusFilter"
        class="text-sm border border-gray-200 rounded-lg px-3 py-2 dark:bg-gray-800 dark:border-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
        aria-label="Filtrer par statut"
        @change="loadThreads"
      >
        <option value="">Tous les statuts</option>
        <option value="open">Ouverts</option>
        <option value="closed">Fermés</option>
        <option value="pinned">Épinglés</option>
      </select>
    </div>

    <!-- Thread list -->
    <div
      v-if="threads.length"
      role="list"
      aria-label="Sujets du forum"
      class="divide-y divide-gray-100 dark:divide-gray-700 rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden"
    >
      <article
        v-for="thread in threads"
        :key="thread.id"
        role="listitem"
        class="flex items-start gap-4 p-4 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors"
        :aria-label="`Sujet : ${thread.title}`"
      >
        <!-- Status badge -->
        <div class="mt-0.5 flex-shrink-0">
          <span
            v-if="thread.status === 'pinned'"
            class="inline-block w-7 h-7 rounded-full bg-yellow-100 text-yellow-600 flex items-center justify-center text-xs"
            aria-label="Épinglé"
            title="Épinglé"
          >📌</span>
          <span
            v-else-if="thread.is_answered"
            class="inline-block w-7 h-7 rounded-full bg-green-100 text-green-600 flex items-center justify-center text-xs"
            aria-label="Répondu"
            title="Répondu"
          >✓</span>
          <span
            v-else-if="thread.status === 'closed'"
            class="inline-block w-7 h-7 rounded-full bg-gray-100 text-gray-400 flex items-center justify-center text-xs"
            aria-label="Fermé"
            title="Fermé"
          >🔒</span>
          <span
            v-else
            class="inline-block w-7 h-7 rounded-full bg-blue-100 text-blue-500 flex items-center justify-center text-xs"
            aria-label="Ouvert"
            title="Ouvert"
          >💬</span>
        </div>

        <!-- Main content -->
        <div class="flex-1 min-w-0">
          <a
            :href="`/helpdesk/forum/threads/${thread.id}`"
            class="font-semibold text-gray-900 dark:text-white hover:text-blue-600 truncate block"
          >{{ thread.title }}</a>
          <p class="text-xs text-gray-500 mt-1">
            par <strong>{{ thread.author?.name }}</strong>
            · {{ formatDate(thread.created_at) }}
          </p>
        </div>

        <!-- Stats -->
        <div class="text-right flex-shrink-0 text-xs text-gray-500 space-y-1">
          <p><span class="font-semibold text-gray-700 dark:text-gray-200">{{ thread.replies_count ?? 0 }}</span> réponses</p>
          <p>{{ thread.views ?? 0 }} vues</p>
          <p>{{ thread.upvotes ?? 0 }} votes</p>
        </div>
      </article>
    </div>

    <div v-else class="text-center py-12 text-gray-400">
      <p class="text-lg">Aucun sujet pour le moment.</p>
      <p class="text-sm mt-1">Soyez le premier à poser une question !</p>
    </div>

    <!-- Pagination -->
    <div v-if="pagination.last_page > 1" class="flex justify-center gap-2 mt-4" role="navigation" aria-label="Pagination">
      <button
        v-for="page in pagination.last_page"
        :key="page"
        :class="[
          'px-3 py-1 rounded text-sm',
          page === pagination.current_page
            ? 'bg-blue-600 text-white'
            : 'bg-gray-100 dark:bg-gray-700 hover:bg-gray-200'
        ]"
        :aria-current="page === pagination.current_page ? 'page' : undefined"
        :aria-label="`Page ${page}`"
        @click="goToPage(page)"
      >
        {{ page }}
      </button>
    </div>

    <!-- New Thread Dialog -->
    <div
      v-if="showNewThread"
      role="dialog"
      aria-modal="true"
      aria-labelledby="new-thread-title"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      @keydown.esc="showNewThread = false"
    >
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-lg p-6 space-y-4">
        <h2 id="new-thread-title" class="text-lg font-bold text-gray-900 dark:text-white">Nouveau sujet</h2>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1" for="thread-title">Titre</label>
          <input
            id="thread-title"
            v-model="newThread.title"
            type="text"
            class="w-full border border-gray-200 dark:border-gray-600 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
            placeholder="Votre question en une ligne…"
            maxlength="255"
            required
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1" for="thread-content">Description</label>
          <textarea
            id="thread-content"
            v-model="newThread.content"
            rows="5"
            class="w-full border border-gray-200 dark:border-gray-600 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
            placeholder="Décrivez votre problème ou question en détail…"
            required
          />
        </div>
        <div class="flex justify-end gap-3">
          <button
            class="px-4 py-2 rounded-lg border text-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-300"
            @click="showNewThread = false"
          >
            Annuler
          </button>
          <button
            class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
            :disabled="submitting || !newThread.title || !newThread.content"
            @click="submitThread"
          >
            {{ submitting ? 'Envoi…' : 'Publier le sujet' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({
  forumId: { type: [Number, String], required: true },
})

const forum = ref(null)
const threads = ref([])
const pagination = ref({ current_page: 1, last_page: 1 })
const statusFilter = ref('')
const showNewThread = ref(false)
const submitting = ref(false)
const newThread = ref({ title: '', content: '' })

async function loadForum() {
  try {
    const { data } = await axios.get(`/api/v1/helpdesk/forums/${props.forumId}`)
    forum.value = data
  } catch (e) {
    console.error(e)
  }
}

async function loadThreads(page = 1) {
  try {
    const params = { page }
    if (statusFilter.value) params.status = statusFilter.value
    const { data } = await axios.get(`/api/v1/helpdesk/forums/${props.forumId}/threads`, { params })
    threads.value = data.data ?? data
    if (data.meta) {
      pagination.value = { current_page: data.meta.current_page, last_page: data.meta.last_page }
    }
  } catch (e) {
    console.error(e)
  }
}

async function submitThread() {
  submitting.value = true
  try {
    await axios.post(`/api/v1/helpdesk/forums/${props.forumId}/threads`, newThread.value)
    showNewThread.value = false
    newThread.value = { title: '', content: '' }
    await loadThreads()
  } catch (e) {
    console.error(e)
  } finally {
    submitting.value = false
  }
}

function goToPage(page) {
  loadThreads(page)
}

function formatDate(date) {
  if (!date) return ''
  return new Intl.DateTimeFormat('fr', { dateStyle: 'medium' }).format(new Date(date))
}

onMounted(async () => {
  await loadForum()
  await loadThreads()
})
</script>
