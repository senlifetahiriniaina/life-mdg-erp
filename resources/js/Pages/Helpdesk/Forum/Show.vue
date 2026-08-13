<template>
  <AppLayout :title="`Forum — ${post?.title ?? ''}`">
    <div class="page-head">
      <div class="flex items-center gap-3">
        <Button icon="pi pi-arrow-left" text @click="$inertia.visit('/helpdesk/forum')" />
        <div>
          <h1 class="wh-page-title">{{ post?.title }}</h1>
          <div class="flex gap-2 mt-1">
            <Tag :value="post?.category" severity="secondary" />
            <Tag v-if="post?.status === 'answered'" value="Résolu" severity="success" />
            <Tag v-else :value="post?.status" severity="secondary" />
          </div>
        </div>
      </div>
    </div>

    <!-- Post content -->
    <div v-if="post" class="wh-panel p-6 mb-6">
      <div class="flex gap-4">
        <!-- Votes -->
        <div class="flex flex-col items-center gap-1 min-w-[40px]">
          <button class="vote-btn" @click="votePost('up')">▲</button>
          <span class="text-lg font-bold">{{ post.votes }}</span>
          <button class="vote-btn" @click="votePost('down')">▼</button>
        </div>
        <!-- Content -->
        <div class="flex-1">
          <p class="text-gray-700 dark:text-surface-100 dark:text-surface-100 whitespace-pre-wrap">{{ post.content }}</p>
          <p class="text-xs text-gray-400 mt-3">par {{ post.author?.name }} · {{ formatDate(post.created_at) }}</p>
        </div>
      </div>
    </div>

    <!-- Replies -->
    <div class="space-y-4 mb-6">
      <h3 class="font-semibold text-gray-700 dark:text-surface-100 dark:text-surface-100">{{ replies.length }} réponse(s)</h3>
      <div
        v-for="reply in replies"
        :key="reply.id"
        :class="['wh-panel p-4', { 'border-2 border-green-400': reply.is_accepted }]"
      >
        <div class="flex gap-4">
          <!-- Vote + accept -->
          <div class="flex flex-col items-center gap-1 min-w-[40px]">
            <button class="vote-btn" @click="voteReply(reply, 'up')">▲</button>
            <span class="font-semibold">{{ reply.votes }}</span>
            <button class="vote-btn" @click="voteReply(reply, 'down')">▼</button>
            <button
              v-if="!reply.is_accepted"
              class="vote-btn mt-1 text-green-600"
              title="Accepter cette réponse"
              @click="acceptAnswer(reply)"
            >✓</button>
            <span v-else class="text-green-600 font-bold text-xs mt-1">✓</span>
          </div>
          <!-- Content -->
          <div class="flex-1">
            <p v-if="reply.is_accepted" class="text-xs text-green-600 font-semibold mb-1">✓ Meilleure réponse</p>
            <p class="text-gray-700 dark:text-surface-100 dark:text-surface-100 whitespace-pre-wrap">{{ reply.content }}</p>
            <p class="text-xs text-gray-400 mt-2">par {{ reply.author?.name }} · {{ formatDate(reply.created_at) }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- New reply form -->
    <div class="wh-panel p-6">
      <h3 class="font-semibold mb-4">Votre réponse</h3>
      <Textarea v-model="replyContent" rows="5" class="w-full mb-3" placeholder="Partagez votre solution..." />
      <Button label="Publier la réponse" icon="pi pi-send" :loading="savingReply" @click="submitReply" />
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Button, Tag, Textarea } from 'primevue'
import axios from 'axios'

const props = defineProps<{ id: number }>()

const post = ref<any>(null)
const replies = ref<any[]>([])
const replyContent = ref('')
const savingReply = ref(false)

async function load() {
  const { data } = await axios.get(`/api/v1/helpdesk/forum/posts/${props.id}`)
  post.value = data
  replies.value = data.replies ?? []
}

async function submitReply() {
  if (!replyContent.value.trim()) return
  savingReply.value = true
  try {
    const { data } = await axios.post(`/api/v1/helpdesk/forum/posts/${props.id}/replies`, {
      content: replyContent.value,
    })
    replies.value.push(data)
    replyContent.value = ''
  } finally {
    savingReply.value = false
  }
}

async function acceptAnswer(reply: any) {
  await axios.post(`/api/v1/helpdesk/forum/posts/${props.id}/accept-answer/${reply.id}`)
  replies.value.forEach((r: any) => (r.is_accepted = false))
  reply.is_accepted = true
  if (post.value) post.value.status = 'answered'
}

async function votePost(direction: 'up' | 'down') {
  const { data } = await axios.post(`/api/v1/helpdesk/forum/posts/${props.id}/vote`, { direction })
  if (post.value) post.value.votes = data.votes
}

async function voteReply(reply: any, direction: 'up' | 'down') {
  const { data } = await axios.post(`/api/v1/helpdesk/forum/posts/${props.id}/replies/${reply.id}/vote`, { direction })
  reply.votes = data.votes
}

function formatDate(date: string): string {
  return new Date(date).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' })
}

onMounted(load)
</script>

<style scoped>
.vote-btn {
  background: none;
  border: 1px solid #e5e7eb;
  border-radius: 4px;
  width: 28px;
  height: 24px;
  font-size: 12px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}
.vote-btn:hover { background: #f3f4f6; }
</style>
