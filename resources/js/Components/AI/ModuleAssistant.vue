<template>
  <!-- Bouton flottant -->
  <div class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-3">
    <!-- Suggestions rapides (visibles au survol si panel fermé) -->
    <div v-if="!open && suggestions.length" class="flex flex-col gap-1 items-end">
      <button
        v-for="s in suggestions"
        :key="s.label"
        class="bg-canvas shadow-lg rounded-full px-3 py-1.5 text-xs font-medium text-halo-600 border border-halo-100 hover:bg-halo-50 transition-all"
        @click="sendQuick(s.label)"
      >{{ s.label }}</button>
    </div>

    <!-- Bouton principal -->
    <button
      @click="open = !open"
      class="w-14 h-14 rounded-full shadow-xl flex items-center justify-center text-white text-xl transition-all"
      :class="open ? 'bg-slate-600 rotate-45' : 'bg-gradient-to-br from-halo-600 to-amber-500'"
      :title="open ? 'Fermer' : 'Assistant IA'"
    >
      {{ open ? '✕' : '✨' }}
    </button>

    <!-- Panel chat -->
    <Transition name="slide-up">
      <div v-if="open"
        class="absolute bottom-16 right-0 w-80 bg-canvas rounded-2xl shadow-2xl border border-subtle flex flex-col overflow-hidden"
        style="height: 420px"
      >
        <!-- Header -->
        <div class="bg-gradient-to-r from-halo-600 to-amber-500 px-4 py-3 flex items-center gap-2">
          <span class="text-lg">✨</span>
          <div>
            <p class="text-white font-semibold text-sm">Assistant IA — {{ module }}</p>
            <p class="text-amber-100 text-xs">Posez une question ou demandez une action</p>
          </div>
        </div>

        <!-- Messages -->
        <div ref="messagesEl" class="flex-1 overflow-y-auto p-3 space-y-2 bg-sunken">
          <div v-if="!messages.length" class="text-center text-fg-4 text-xs mt-8">
            <p class="text-2xl mb-2">💬</p>
            <p>Commencez à écrire ou choisissez une suggestion</p>
          </div>

          <div v-for="(msg, i) in messages" :key="i"
            :class="['flex', msg.role === 'user' ? 'justify-end' : 'justify-start']"
          >
            <div :class="[
              'max-w-[85%] rounded-2xl px-3 py-2 text-sm',
              msg.role === 'user'
                ? 'bg-halo-600 text-white rounded-br-sm'
                : 'bg-canvas shadow-sm text-fg-1 rounded-bl-sm border border-subtle'
            ]">
              {{ msg.content }}
            </div>
          </div>

          <!-- Actions suggérées -->
          <div v-if="lastActions.length" class="flex flex-wrap gap-1 mt-1">
            <button v-for="a in lastActions" :key="a.label"
              class="text-xs bg-halo-50 text-halo-600 px-2 py-1 rounded-full border border-halo-100 hover:bg-halo-100"
              @click="navigateTo(a.route)"
            >{{ a.label }}</button>
          </div>

          <!-- Typing indicator -->
          <div v-if="loading" class="flex justify-start">
            <div class="bg-canvas shadow-sm border border-subtle rounded-2xl rounded-bl-sm px-3 py-2">
              <span class="inline-flex gap-1">
                <span class="w-1.5 h-1.5 bg-fg-4 rounded-full animate-bounce" style="animation-delay:0s"/>
                <span class="w-1.5 h-1.5 bg-fg-4 rounded-full animate-bounce" style="animation-delay:.15s"/>
                <span class="w-1.5 h-1.5 bg-fg-4 rounded-full animate-bounce" style="animation-delay:.3s"/>
              </span>
            </div>
          </div>
        </div>

        <!-- Input -->
        <div class="p-2 border-t border-subtle bg-canvas flex gap-2">
          <input
            v-model="input"
            @keydown.enter="send"
            :disabled="loading"
            placeholder="Écrivez votre message..."
            class="flex-1 text-sm rounded-xl border border-subtle bg-sunken text-fg-1 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-halo-300"
          />
          <button @click="send" :disabled="loading || !input.trim()"
            class="w-9 h-9 rounded-xl bg-halo-600 text-white flex items-center justify-center disabled:opacity-40 hover:bg-halo-700"
          >→</button>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { ref, nextTick, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

const props = defineProps<{
  module: string
  context?: Record<string, unknown>
  quickSuggestions?: string[]
}>()

interface Message { role: 'user' | 'assistant'; content: string }
interface Action  { label: string; route: string }

const open        = ref(false)
const input       = ref('')
const loading     = ref(false)
const messages    = ref<Message[]>([])
const lastActions = ref<Action[]>([])
const messagesEl  = ref<HTMLElement | null>(null)

const suggestions = ref<Action[]>([])

onMounted(() => {
  // Suggestions rapides contextuelles selon le module
  const moduleDefaults: Record<string, string[]> = {
    CRM:           ['Analyser le pipeline', 'Contacts inactifs', 'Opportunités à relancer'],
    HR:            ['Absences du jour', 'Demandes en attente', 'Postes ouverts'],
    Helpdesk:      ['Tickets SLA dépassé', 'Score CSAT semaine', 'Tickets non assignés'],
    Projects:      ['Sprint en cours', 'Tâches en retard', 'Vélocité équipe'],
    Accounting:    ['Factures impayées', 'Trésorerie du mois', 'Relances à envoyer'],
    Inventory:     ['Stock critique', 'Expéditions en retard', 'Ruptures prévues'],
    Manufacturing: ['OF en retard', 'Postes surchargés', 'Qualité dernière semaine'],
    BI:            ['Résumé des KPIs', 'Tendance du mois', 'Anomalies détectées'],
    Email:         ["Taux d'ouverture", 'Campagne à planifier', 'Désabonnements récents'],
    Documents:     ['Documents à signer', 'Versions récentes', 'Approbations en attente'],
    WhatsApp:      ['Messages non lus', 'Conversations non assignées', 'Paiements en attente'],
    POS:           ['Ventes du jour', 'Terminaux hors ligne', 'Stock caisse bas'],
    Ecommerce:     ['Commandes à traiter', 'Paniers abandonnés', 'Avis récents'],
  }
  const sug = props.quickSuggestions ?? moduleDefaults[props.module] ?? []
  suggestions.value = sug.map(l => ({ label: l, route: '' }))
})

async function send() {
  const msg = input.value.trim()
  if (!msg || loading.value) return
  messages.value.push({ role: 'user', content: msg })
  input.value = ''
  loading.value = true
  lastActions.value = []
  await scrollDown()

  try {
    const { data } = await axios.post('/api/v1/ai/chat', {
      message: msg,
      module:  props.module,
      context: props.context ?? {},
    })
    messages.value.push({ role: 'assistant', content: data.reply })
    lastActions.value = data.actions ?? []
  } catch {
    messages.value.push({ role: 'assistant', content: "Désolé, je ne peux pas répondre pour l'instant." })
  } finally {
    loading.value = false
    await scrollDown()
  }
}

async function sendQuick(label: string) {
  open.value = true
  input.value = label
  await nextTick()
  send()
}

function navigateTo(route: string) {
  if (route) router.visit(route)
}

async function scrollDown() {
  await nextTick()
  if (messagesEl.value) messagesEl.value.scrollTop = messagesEl.value.scrollHeight
}
</script>

<style scoped>
.slide-up-enter-active, .slide-up-leave-active { transition: all 0.25s cubic-bezier(.4,0,.2,1); }
.slide-up-enter-from, .slide-up-leave-to { opacity: 0; transform: translateY(16px) scale(.97); }
</style>
