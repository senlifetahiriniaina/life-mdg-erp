<template>
  <div v-if="canView" class="space-y-6">
    <!-- Header -->
    <div class="flex items-start gap-3">
      <Button icon="pi pi-arrow-left" severity="secondary" text rounded @click="goBack" />
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 flex-wrap mb-1">
          <span
            class="text-xs px-2 py-0.5 rounded-full font-medium"
            :style="{ backgroundColor: topic.categoryColor + '20', color: topic.categoryColor }"
          >{{ topic.categorie }}</span>
          <Tag v-if="topic.epingle" value="Épinglé" severity="info" class="text-xs" />
          <Tag :value="topic.statut" :severity="topicStatusSeverity(topic.statut)" class="text-xs" />
        </div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-white leading-tight">{{ topic.titre }}</h1>
        <div class="flex items-center gap-4 mt-1 text-xs text-gray-400 flex-wrap">
          <span>par <strong class="text-gray-600 dark:text-gray-300">{{ topic.auteur }}</strong></span>
          <span><i class="pi pi-calendar mr-1"></i>{{ topic.date }}</span>
          <span><i class="pi pi-eye mr-1"></i>{{ topic.vues }} vues</span>
          <span><i class="pi pi-comment mr-1"></i>{{ topic.reponses }} réponses</span>
        </div>
      </div>
      <div class="flex gap-2 flex-shrink-0">
        <Button v-if="topic.statut !== 'Résolu'" label="Marquer résolu" icon="pi pi-check-circle" severity="success" size="small" @click="markResolved" />
        <Button icon="pi pi-share-alt" severity="secondary" text size="small" />
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
      <!-- Thread -->
      <div class="lg:col-span-3 space-y-4">
        <!-- Original post -->
        <Card class="border-l-4 border-blue-500">
          <template #content>
            <div class="flex gap-4">
              <!-- Author sidebar -->
              <div class="flex flex-col items-center gap-1 flex-shrink-0 w-20">
                <div
                  class="w-12 h-12 rounded-full flex items-center justify-center text-white text-lg font-bold"
                  :style="{ backgroundColor: topic.avatarColor }"
                >{{ topic.auteur.charAt(0) }}</div>
                <p class="text-xs font-semibold text-gray-700 dark:text-gray-300 text-center">{{ topic.auteur }}</p>
                <span class="text-xs bg-blue-100 text-blue-700 rounded px-1.5 py-0.5">Auteur</span>
                <p class="text-xs text-gray-400">{{ topic.authorPosts }} posts</p>
              </div>
              <!-- Content -->
              <div class="flex-1 min-w-0">
                <div class="text-xs text-gray-400 mb-3 flex items-center justify-between">
                  <span><i class="pi pi-calendar mr-1"></i>{{ topic.date }}</span>
                  <div class="flex gap-2">
                    <button class="flex items-center gap-1 text-gray-400 hover:text-blue-600 transition-colors">
                      <i class="pi pi-heart"></i>
                      <span>{{ topic.likes }}</span>
                    </button>
                  </div>
                </div>
                <div class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-line">{{ topic.contenu }}</div>
                <div class="flex flex-wrap gap-1 mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                  <span v-for="tag in topic.tags" :key="tag" class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-500 rounded-full px-2 py-0.5">{{ tag }}</span>
                </div>
              </div>
            </div>
          </template>
        </Card>

        <!-- Replies -->
        <div class="space-y-3">
          <p class="text-sm font-semibold text-gray-500 uppercase tracking-wider">{{ replies.length }} réponses</p>
          <Card
            v-for="reply in replies"
            :key="reply.id"
            :class="reply.isSolution ? 'border-l-4 border-green-500 bg-green-50 dark:bg-green-900/10' : ''"
          >
            <template #content>
              <div class="flex gap-4">
                <!-- Author sidebar -->
                <div class="flex flex-col items-center gap-1 flex-shrink-0 w-20">
                  <div
                    class="w-10 h-10 rounded-full flex items-center justify-center text-white text-sm font-bold"
                    :style="{ backgroundColor: reply.avatarColor }"
                  >{{ reply.auteur.charAt(0) }}</div>
                  <p class="text-xs font-semibold text-gray-700 dark:text-gray-300 text-center">{{ reply.auteur }}</p>
                  <span v-if="reply.isStaff" class="text-xs bg-purple-100 text-purple-700 rounded px-1.5 py-0.5">Staff</span>
                  <p class="text-xs text-gray-400">{{ reply.posts }} posts</p>
                </div>
                <!-- Content -->
                <div class="flex-1 min-w-0">
                  <div class="text-xs text-gray-400 mb-3 flex items-center justify-between flex-wrap gap-2">
                    <div class="flex items-center gap-2">
                      <span><i class="pi pi-calendar mr-1"></i>{{ reply.date }}</span>
                      <span v-if="reply.isSolution" class="flex items-center gap-1 text-green-600 font-semibold">
                        <i class="pi pi-check-circle"></i> Solution acceptée
                      </span>
                    </div>
                    <div class="flex gap-3">
                      <button class="flex items-center gap-1 text-gray-400 hover:text-blue-600 transition-colors text-xs" @click="likeReply(reply)">
                        <i class="pi pi-thumbs-up"></i>
                        <span>{{ reply.likes }}</span>
                      </button>
                      <button
                        v-if="!reply.isSolution && canMarkSolution"
                        class="flex items-center gap-1 text-gray-400 hover:text-green-600 transition-colors text-xs"
                        @click="markAsSolution(reply)"
                      >
                        <i class="pi pi-check-circle"></i>
                        <span>Solution</span>
                      </button>
                      <button class="flex items-center gap-1 text-gray-400 hover:text-orange-500 transition-colors text-xs">
                        <i class="pi pi-reply"></i>
                        <span>Citer</span>
                      </button>
                    </div>
                  </div>
                  <div
                    v-if="reply.quote"
                    class="border-l-4 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 rounded px-3 py-2 mb-3 text-xs text-gray-500 italic"
                  >
                    <p class="font-semibold text-gray-600 dark:text-gray-400 mb-1">{{ reply.quoteAuthor }} a écrit :</p>
                    {{ reply.quote }}
                  </div>
                  <div class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-line">{{ reply.contenu }}</div>
                </div>
              </div>
            </template>
          </Card>
        </div>

        <!-- Reply box -->
        <Card>
          <template #header>
            <div class="px-5 pt-5 pb-2 border-b border-gray-100 dark:border-gray-700">
              <span class="font-semibold text-gray-700 dark:text-gray-200">Votre réponse</span>
            </div>
          </template>
          <template #content>
            <div class="space-y-3">
              <Textarea
                v-model="replyText"
                rows="5"
                class="w-full"
                placeholder="Rédigez votre réponse... Soyez précis et constructif pour aider la communauté."
              />
              <div class="flex items-center justify-between flex-wrap gap-2">
                <p class="text-xs text-gray-400">Markdown supporté. Soyez respectueux et bienveillant.</p>
                <Button
                  label="Publier la réponse"
                  icon="pi pi-send"
                  @click="postReply"
                  :disabled="!replyText.trim()"
                />
              </div>
            </div>
          </template>
        </Card>
      </div>

      <!-- Sidebar -->
      <div class="space-y-4">
        <!-- Topic info -->
        <Card>
          <template #header>
            <div class="px-5 pt-5 pb-2 border-b border-gray-100 dark:border-gray-700">
              <span class="font-semibold text-gray-700 dark:text-gray-200">Infos du sujet</span>
            </div>
          </template>
          <template #content>
            <div class="space-y-3 text-sm">
              <div class="flex justify-between">
                <span class="text-gray-500">Statut</span>
                <Tag :value="topic.statut" :severity="topicStatusSeverity(topic.statut)" class="text-xs" />
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">Catégorie</span>
                <span class="font-medium text-gray-800 dark:text-gray-200 text-xs">{{ topic.categorie }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">Créé le</span>
                <span class="text-gray-600 dark:text-gray-400 text-xs">{{ topic.date }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">Vues</span>
                <span class="text-gray-600 dark:text-gray-400">{{ topic.vues }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">Réponses</span>
                <span class="text-gray-600 dark:text-gray-400">{{ topic.reponses }}</span>
              </div>
            </div>
          </template>
        </Card>

        <!-- Related topics -->
        <Card>
          <template #header>
            <div class="px-5 pt-5 pb-2 border-b border-gray-100 dark:border-gray-700">
              <span class="font-semibold text-gray-700 dark:text-gray-200">Sujets similaires</span>
            </div>
          </template>
          <template #content>
            <div class="space-y-2">
              <div
                v-for="related in relatedTopics"
                :key="related.id"
                class="group cursor-pointer"
                @click="window.location.href = `/helpdesk/forum/${related.id}`"
               role="button" tabindex="0" @keydown.enter.prevent="window.location.href = `/helpdesk/forum/${related.id}`">
                <p class="text-xs font-medium text-gray-700 dark:text-gray-300 group-hover:text-blue-600 transition-colors leading-tight">{{ related.titre }}</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ related.reponses }} réponses</p>
              </div>
            </div>
          </template>
        </Card>

        <!-- Actions -->
        <Card>
          <template #content>
            <div class="space-y-2">
              <Button label="S'abonner au sujet" icon="pi pi-bell" severity="secondary" outlined class="w-full" size="small" />
              <Button label="Signaler" icon="pi pi-flag" severity="danger" text class="w-full" size="small" />
            </div>
          </template>
        </Card>
      </div>
    </div>

    <!-- AI Assistant -->
    <AiAssistantPanel v-if="guidance" :guidance="guidance" />
  </div>
  <div v-else class="flex items-center justify-center h-48">
    <Message severity="warn">Vous n'avez pas accès à cette section.</Message>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Textarea from 'primevue/textarea'
import StrategicContext from '@/Components/StrategicContext.vue'
import { useStrategicLink } from '@/composables/useStrategicLink'
import AiAssistantPanel from '@/Components/AI/AiAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import { useRbac } from '@/composables/useRbac'

const { guidance } = useAiAssistant('Helpdesk', 'view_forum')
const { isAdmin, canManage, canView } = useRbac('Helpdesk')

const page = usePage()
const auth = computed(() => page.props.auth)
const canMarkSolution = computed(() => true)

const replyText = ref('')

const topic = ref({
  id: 2,
  titre: 'Comment configurer Wave comme méthode de paiement principale ?',
  categorie: 'Support général',
  categoryColor: '#3b82f6',
  statut: 'Résolu',
  epingle: false,
  auteur: 'Mamadou Diallo',
  avatarColor: '#3b82f6',
  authorPosts: 14,
  date: '22/05/2026 à 10:34',
  vues: 312,
  reponses: 8,
  likes: 7,
  tags: ['wave pay', 'paiement', 'webhook', 'configuration'],
  contenu: `Bonjour à tous,

J'essaie de configurer Wave comme méthode de paiement principale pour notre boutique en ligne. J'ai suivi la documentation officielle mais les webhooks ne reçoivent pas les confirmations de paiement après un achat réussi.

Voici ce que j'ai fait :
1. Activé Wave dans Paramètres > Paiements
2. Entré notre numéro marchand Wave
3. Configuré l'URL de webhook : https://mondomaine.com/api/v1/webhooks/wave

Le problème : quand un client paie avec Wave, la commande reste en statut "En attente de paiement" même si Wave confirme le paiement côté client.

Est-ce que quelqu'un a eu ce problème et sait comment le résoudre ?

Merci d'avance !`,
})

const replies = ref([
  {
    id: 1, auteur: 'Ibrahim Traoré', avatarColor: '#8b5cf6', posts: 89, isStaff: true, isSolution: false,
    date: '22/05/2026 à 11:15', likes: 3, quote: null, quoteAuthor: null,
    contenu: `Bonjour Mamadou,

Ce problème est courant lors de la configuration initiale de Wave. Voici quelques points à vérifier :

1. Assurez-vous que votre URL webhook est accessible publiquement (pas en localhost)
2. Vérifiez que vous avez bien enregistré la clé secrète Wave dans vos paramètres WideHalo
3. Dans votre compte marchand Wave, confirmez que l'URL webhook est bien enregistrée

Quelle est votre environnement ? Production ou sandbox ?`,
  },
  {
    id: 2, auteur: 'Mamadou Diallo', avatarColor: '#3b82f6', posts: 14, isStaff: false, isSolution: false,
    date: '22/05/2026 à 11:42', likes: 1,
    quote: 'Quelle est votre environnement ? Production ou sandbox ?',
    quoteAuthor: 'Ibrahim Traoré',
    contenu: `C'est en production. La clé secrète est bien configurée. L'URL est accessible publiquement, j'ai testé avec un outil en ligne.`,
  },
  {
    id: 3, auteur: 'Seydou Ouédraogo', avatarColor: '#10b981', posts: 156, isStaff: true, isSolution: true,
    date: '22/05/2026 à 14:08', likes: 12, quote: null, quoteAuthor: null,
    contenu: `J'ai trouvé le problème ! Dans la version actuelle de WideHalo (2.7.x), il faut ajouter manuellement le préfixe "/api/v1" à l'URL webhook Wave dans la console marchand Wave.

URL correcte : https://mondomaine.com/api/v1/webhooks/wave/callback

Notez bien le "/callback" à la fin — c'est un endpoint spécifique différent de l'URL de base.

Ensuite, dans vos paramètres WideHalo, cochez "Vérification signature HMAC Wave" et entrez votre clé HMAC disponible dans votre espace marchand Wave.

Ce bug est corrigé dans la v2.8 qui sortira la semaine prochaine.`,
  },
  {
    id: 4, auteur: 'Mamadou Diallo', avatarColor: '#3b82f6', posts: 14, isStaff: false, isSolution: false,
    date: '22/05/2026 à 14:55', likes: 4, quote: null, quoteAuthor: null,
    contenu: `Merci Seydou, ça fonctionne parfaitement maintenant ! Le "/callback" faisait toute la différence. Je marque ce sujet comme résolu. Merci à toute l'équipe !`,
  },
])

const relatedTopics = ref([
  { id: 10, titre: 'Orange Money : erreur ERR_MOMOC02 lors du paiement', reponses: 5 },
  { id: 11, titre: 'Configurer MTN MoMo pour la Côte d\'Ivoire', reponses: 3 },
  { id: 12, titre: 'Webhook : format des événements de paiement', reponses: 7 },
])

const topicStatusSeverity = (s: string) => {
  const map: Record<string, string> = { Résolu: 'success', Ouvert: 'warn', Épinglé: 'info' }
  return map[s] ?? 'secondary'
}

const likeReply = (reply: any) => { reply.likes++ }

const markAsSolution = (reply: any) => {
  replies.value.forEach(r => { r.isSolution = false })
  reply.isSolution = true
  topic.value.statut = 'Résolu'
}

const markResolved = () => { topic.value.statut = 'Résolu' }

const goBack = () => { window.history.back() }

const postReply = () => {
  if (!replyText.value.trim()) return
  replies.value.push({
    id: replies.value.length + 1,
    auteur: 'Vous',
    avatarColor: '#6366f1',
    posts: 1,
    isStaff: false,
    isSolution: false,
    date: new Date().toLocaleString('fr-FR'),
    likes: 0,
    quote: null,
    quoteAuthor: null,
    contenu: replyText.value,
  })
  topic.value.reponses++
  replyText.value = ''
}
</script>
