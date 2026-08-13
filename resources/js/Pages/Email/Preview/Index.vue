<template>
  <AppLayout>
    <Head :title="`Preview — ${template.name}`" />

    <div class="preview-layout">
      <!-- Left: client selector -->
      <aside class="client-panel">
        <p class="panel-title">Email Clients</p>
        <button
          v-for="client in clients"
          :key="client.id"
          :class="['client-btn', selectedClient === client.id ? 'active' : '']"
          @click="selectClient(client.id)"
        >
          <span class="client-icon">{{ client.icon }}</span>
          <span>{{ client.label }}</span>
        </button>
      </aside>

      <!-- Center: iframe preview -->
      <main class="preview-main">
        <div class="preview-toolbar">
          <div class="device-toggle">
            <button :class="['toggle-btn', !mobileView ? 'active' : '']" @click="mobileView = false">
              <i class="pi pi-desktop" /> Desktop
            </button>
            <button :class="['toggle-btn', mobileView ? 'active' : '']" @click="mobileView = true">
              <i class="pi pi-mobile" /> Mobile
            </button>
          </div>
          <span style="font-size:13px;color:var(--fg-3)">{{ mobileView ? '375px' : '600px' }} wide</span>
        </div>

        <div class="preview-frame-wrapper">
          <div v-if="loadingPreview" class="preview-loading">
            <i class="pi pi-spin pi-spinner" style="font-size:28px;color:var(--fg-3)" />
          </div>
          <iframe
            v-else
            :srcdoc="previewHtml"
            :style="{ width: mobileView ? '375px' : '600px', height: '600px', border: 'none', background: '#fff' }"
            sandbox="allow-same-origin"
          />
        </div>
      </main>

      <!-- Right panel: spam score + test email -->
      <aside class="spam-panel">
        <p class="panel-title">Spam Analysis</p>

        <div v-if="spamResult" class="spam-gauge-wrap">
          <!-- Score gauge -->
          <div class="spam-gauge">
            <div
              class="spam-gauge-bar"
              :style="{
                background: spamResult.score < 3 ? '#22c55e' : spamResult.score < 7 ? '#f59e0b' : '#ef4444',
                width: (spamResult.score / 10 * 100) + '%',
              }"
            />
          </div>
          <div class="spam-score-label" :class="spamResult.score < 3 ? 'green' : spamResult.score < 7 ? 'yellow' : 'red'">
            {{ spamResult.score }} / 10
            <span>{{ spamResult.score < 3 ? 'Good' : spamResult.score < 7 ? 'Fair' : 'Poor' }}</span>
          </div>

          <div v-if="spamResult.issues.length" class="spam-issues">
            <p class="panel-title" style="margin-bottom:8px">Issues</p>
            <div v-for="issue in spamResult.issues" :key="issue" class="spam-issue">
              <i class="pi pi-exclamation-triangle" style="color:#f59e0b;font-size:12px" />
              {{ issue }}
            </div>
          </div>
          <div v-else class="spam-ok">
            <i class="pi pi-check-circle" style="color:#22c55e" /> All checks passed
          </div>
        </div>
        <div v-else-if="loadingSpam" style="color:var(--fg-3);font-size:13px">
          <i class="pi pi-spin pi-spinner" /> Checking spam score…
        </div>
        <button v-else class="btn btn-outline" style="width:100%" @click="loadSpamScore">
          Check spam score
        </button>

        <hr style="margin:20px 0;border-color:var(--border-1,#e5e7eb)" />

        <p class="panel-title">Send Test Email</p>
        <div class="test-email-form">
          <input v-model="testEmail" class="wh-input" type="email" placeholder="recipient@example.com" />
          <button class="btn btn-primary" style="width:100%;margin-top:8px" :disabled="sendingTest" @click="sendTest">
            {{ sendingTest ? 'Sending…' : 'Send test' }}
          </button>
          <p v-if="testSent" style="color:#22c55e;font-size:13px;margin-top:6px">
            <i class="pi pi-check" /> Test email sent!
          </p>
        </div>
      </aside>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

interface Template {
  id: number
  name: string
  subject?: string
}

interface SpamResult {
  score: number
  issues: string[]
}

const props = defineProps<{ template: Template }>()

const clients = [
  { id: 'gmail',          label: 'Gmail',          icon: '📧' },
  { id: 'outlook',        label: 'Outlook',         icon: '📨' },
  { id: 'apple_mail',     label: 'Apple Mail',      icon: '🍎' },
  { id: 'yahoo',          label: 'Yahoo Mail',      icon: '💌' },
  { id: 'thunderbird',    label: 'Thunderbird',     icon: '⚡' },
  { id: 'mobile_ios',     label: 'iOS Mail',        icon: '📱' },
  { id: 'mobile_android', label: 'Android Gmail',   icon: '🤖' },
]

const selectedClient = ref('gmail')
const mobileView = ref(false)
const previewHtml = ref('')
const loadingPreview = ref(false)
const spamResult = ref<SpamResult | null>(null)
const loadingSpam = ref(false)
const testEmail = ref('')
const sendingTest = ref(false)
const testSent = ref(false)

async function selectClient(clientId: string): Promise<void> {
  selectedClient.value = clientId
  await loadPreview()
}

async function loadPreview(): Promise<void> {
  loadingPreview.value = true
  try {
    const res = await axios.post(`/api/v1/email/templates/${props.template.id}/preview`, {
      client: selectedClient.value,
    })
    previewHtml.value = res.data.html
  } finally {
    loadingPreview.value = false
  }
}

async function loadSpamScore(): Promise<void> {
  loadingSpam.value = true
  try {
    const res = await axios.get(`/api/v1/email/templates/${props.template.id}/spam-score`)
    spamResult.value = res.data
  } finally {
    loadingSpam.value = false
  }
}

async function sendTest(): Promise<void> {
  sendingTest.value = true
  try {
    await axios.post(`/api/v1/email/templates/${props.template.id}/send-test`, { email: testEmail.value })
    testSent.value = true
    setTimeout(() => { testSent.value = false }, 3000)
  } finally {
    sendingTest.value = false
  }
}

onMounted(async () => {
  await Promise.all([loadPreview(), loadSpamScore()])
})
</script>

<style scoped>
.preview-layout {
  display: grid;
  grid-template-columns: 180px 1fr 240px;
  height: calc(100vh - 64px);
  overflow: hidden;
}

.client-panel {
  border-right: 1px solid var(--border-1, #e5e7eb);
  padding: 16px;
  overflow-y: auto;
  background: var(--surface-1, #f9fafb);
}
.panel-title {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--fg-3, #6b7280);
  letter-spacing: .06em;
  margin: 0 0 10px;
}
.client-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 8px 10px;
  border-radius: 6px;
  border: 1px solid transparent;
  background: transparent;
  cursor: pointer;
  font-size: 13px;
  text-align: left;
  margin-bottom: 4px;
}
.client-btn:hover { background: var(--surface-2, #f3f4f6); }
.client-btn.active { background: var(--primary-50, #eff6ff); border-color: var(--primary-300, #93c5fd); color: var(--primary-700, #1d4ed8); font-weight: 600; }
.client-icon { font-size: 16px; }

.preview-main {
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: var(--surface-2, #f3f4f6);
}
.preview-toolbar {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 10px 16px;
  border-bottom: 1px solid var(--border-1, #e5e7eb);
  background: #fff;
}
.device-toggle { display: flex; border: 1px solid var(--border-1, #d1d5db); border-radius: 6px; overflow: hidden; }
.toggle-btn {
  padding: 6px 12px;
  border: none;
  background: transparent;
  cursor: pointer;
  font-size: 13px;
  display: flex;
  align-items: center;
  gap: 5px;
}
.toggle-btn.active { background: var(--primary-600, #2563eb); color: #fff; }

.preview-frame-wrapper {
  flex: 1;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 24px;
  overflow: auto;
}
.preview-loading { padding: 60px; }

.spam-panel {
  border-left: 1px solid var(--border-1, #e5e7eb);
  padding: 16px;
  overflow-y: auto;
  background: var(--surface-0, #fff);
}

.spam-gauge-wrap { margin-bottom: 16px; }
.spam-gauge {
  height: 8px;
  background: #e5e7eb;
  border-radius: 4px;
  margin-bottom: 8px;
  overflow: hidden;
}
.spam-gauge-bar { height: 100%; border-radius: 4px; transition: width .3s; }
.spam-score-label {
  font-size: 22px;
  font-weight: 700;
  display: flex;
  align-items: baseline;
  gap: 8px;
}
.spam-score-label span { font-size: 13px; font-weight: 400; }
.spam-score-label.green { color: #22c55e; }
.spam-score-label.yellow { color: #f59e0b; }
.spam-score-label.red { color: #ef4444; }

.spam-issues { margin-top: 12px; }
.spam-issue {
  display: flex;
  align-items: flex-start;
  gap: 6px;
  font-size: 12px;
  color: var(--fg-2, #374151);
  padding: 4px 0;
  line-height: 1.4;
}
.spam-ok { font-size: 13px; color: #22c55e; margin-top: 8px; display: flex; align-items: center; gap: 6px; }

.wh-input { width: 100%; border: 1px solid var(--border-1, #d1d5db); border-radius: 6px; padding: 6px 10px; font-size: 13px; box-sizing: border-box; }
.btn { padding: 7px 14px; border-radius: 6px; font-size: 14px; border: none; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 6px; }
.btn-primary { background: var(--primary-600, #2563eb); color: #fff; }
.btn-primary:disabled { opacity: .6; cursor: default; }
.btn-outline { background: transparent; border: 1px solid var(--border-1, #d1d5db); color: var(--fg-2, #374151); }
</style>
