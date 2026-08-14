<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Forum communautaire</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Échanges, entraide et discussions entre utilisateurs WideHalo</p>
      </div>
      <Button v-if="canCreate" label="Nouveau sujet" icon="pi pi-plus" @click="showNewTopicDialog = true" />
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <Card class="border-l-4 border-blue-500">
        <template #content>
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
              <i class="pi pi-comments text-blue-600 text-lg"></i>
            </div>
            <div>
              <p class="text-xs text-gray-500">Sujets actifs</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.sujets }}</p>
            </div>
          </div>
        </template>
      </Card>
      <Card class="border-l-4 border-green-500">
        <template #content>
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
              <i class="pi pi-users text-green-600 text-lg"></i>
            </div>
            <div>
              <p class="text-xs text-gray-500">Membres</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.membres.toLocaleString('fr-FR') }}</p>
            </div>
          </div>
        </template>
      </Card>
      <Card class="border-l-4 border-purple-500">
        <template #content>
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center">
              <i class="pi pi-pencil text-purple-600 text-lg"></i>
            </div>
            <div>
              <p class="text-xs text-gray-500">Posts ce mois</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.posts }}</p>
            </div>
          </div>
        </template>
      </Card>
      <Card class="border-l-4 border-yellow-500">
        <template #content>
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-yellow-100 flex items-center justify-center">
              <i class="pi pi-check-circle text-yellow-600 text-lg"></i>
            </div>
            <div>
              <p class="text-xs text-gray-500">Résolution communautaire</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.tauxResolution }}%</p>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <!-- Search -->
    <Card>
      <template #content>
        <div class="flex gap-3 flex-wrap">
          <div class="relative flex-1 min-w-[200px]">
            <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 z-10"></i>
            <InputText v-model="searchQuery" placeholder="Rechercher dans le forum..." class="w-full pl-9" />
          </div>
          <Select v-model="sortBy" :options="sortOptions" option-label="label" option-value="value" class="w-44" />
        </div>
      </template>
    </Card>

    <!-- Main layout -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
      <!-- Category sidebar -->
      <div>
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider px-1 mb-3">Catégories</p>
        <div class="space-y-1">
          <div
            class="flex items-center justify-between px-3 py-2 rounded-lg cursor-pointer transition-colors"
            :class="selectedCategory === null
              ? 'bg-blue-600 text-white'
              : 'hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300'"
            @click="selectedCategory = null"
           role="button" tabindex="0" @keydown.enter.prevent="selectedCategory = null">
            <div class="flex items-center gap-2">
              <i class="pi pi-th-large text-sm"></i>
              <span class="text-sm font-medium">Toutes</span>
            </div>
            <span class="text-xs rounded-full px-2 py-0.5" :class="selectedCategory === null ? 'bg-blue-500 text-white' : 'bg-gray-200 dark:bg-gray-600 text-gray-600'">
              {{ topics.length }}
            </span>
          </div>
          <div
            v-for="cat in forumCategories"
            :key="cat.id"
            class="flex items-center justify-between px-3 py-2 rounded-lg cursor-pointer transition-colors"
            :class="selectedCategory === cat.id
              ? 'bg-blue-600 text-white'
              : 'hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300'"
            @click="selectedCategory = selectedCategory === cat.id ? null : cat.id"
           role="button" tabindex="0" @keydown.enter.prevent="selectedCategory = selectedCategory === cat.id ? null : cat.id">
            <div class="flex items-center gap-2">
              <i :class="cat.icon" class="text-sm"></i>
              <span class="text-sm font-medium">{{ cat.label }}</span>
            </div>
            <span
              class="text-xs rounded-full px-2 py-0.5"
              :class="selectedCategory === cat.id ? 'bg-blue-500 text-white' : 'bg-gray-200 dark:bg-gray-600 text-gray-600 dark:text-gray-300'"
            >{{ cat.count }}</span>
          </div>
        </div>

        <!-- Trending tags -->
        <div class="mt-6">
          <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider px-1 mb-3">Tags tendance</p>
          <div class="flex flex-wrap gap-1 px-1">
            <span
              v-for="tag in trendingTags"
              :key="tag"
              class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-full px-2 py-1 cursor-pointer hover:bg-blue-100 hover:text-blue-700 transition-colors"
            >{{ tag }}</span>
          </div>
        </div>
      </div>

      <!-- Topics list -->
      <div class="lg:col-span-3 space-y-3">
        <div v-if="filteredTopics.length === 0" class="text-center py-12 text-gray-400">
          <i class="pi pi-comments text-4xl mb-3 block"></i>
          <p>Aucun sujet trouvé.</p>
        </div>
        <Card
          v-for="topic in filteredTopics"
          :key="topic.id"
          class="cursor-pointer hover:shadow-md transition-shadow"
          @click="goToTopic(topic)"
        >
          <template #content>
            <div class="flex items-start gap-4">
              <!-- Author avatar -->
              <div
                class="w-10 h-10 rounded-full flex items-center justify-center text-white text-sm font-bold flex-shrink-0"
                :style="{ backgroundColor: topic.avatarColor }"
              >{{ topic.auteur.charAt(0) }}</div>

              <!-- Topic content -->
              <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-2 flex-wrap">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span v-if="topic.epingle" class="text-xs bg-orange-100 text-orange-600 rounded px-1.5 py-0.5 flex items-center gap-1">
                      <i class="pi pi-bookmark-fill text-xs"></i> Épinglé
                    </span>
                    <h3 class="font-semibold text-gray-900 dark:text-white text-sm leading-tight">{{ topic.titre }}</h3>
                  </div>
                  <Tag :value="topic.statut" :severity="topicStatusSeverity(topic.statut)" class="text-xs flex-shrink-0" />
                </div>
                <p class="text-xs text-gray-500 mt-1">{{ topic.extrait }}</p>
                <div class="flex items-center gap-4 mt-2 text-xs text-gray-400 flex-wrap">
                  <span class="flex items-center gap-1">
                    <span
                      class="px-1.5 py-0.5 rounded text-xs font-medium"
                      :style="{ backgroundColor: getCatColor(topic.categorieId) + '20', color: getCatColor(topic.categorieId) }"
                    >{{ topic.categorie }}</span>
                  </span>
                  <span>par <strong class="text-gray-600 dark:text-gray-300">{{ topic.auteur }}</strong></span>
                  <span><i class="pi pi-comment mr-1"></i>{{ topic.reponses }} réponses</span>
                  <span><i class="pi pi-eye mr-1"></i>{{ topic.vues }} vues</span>
                  <span><i class="pi pi-clock mr-1"></i>{{ topic.dernierMessage }}</span>
                </div>
              </div>
            </div>
          </template>
        </Card>
      </div>
    </div>

    <!-- New Topic Dialog -->
    <Dialog v-model:visible="showNewTopicDialog" header="Nouveau sujet" :style="{ width: '580px' }" modal>
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Titre du sujet *</label>
          <InputText v-model="newTopic.titre" class="w-full" placeholder="Résumez votre question ou discussion..." />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Catégorie *</label>
          <Select v-model="newTopic.categorie" :options="categoryOptions" option-label="label" option-value="value" class="w-full" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Message *</label>
          <Textarea v-model="newTopic.contenu" rows="6" class="w-full" placeholder="Décrivez votre sujet en détail. Donnez le plus de contexte possible pour obtenir de meilleures réponses." />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tags (optionnels)</label>
          <InputText v-model="newTopic.tags" class="w-full" placeholder="ex: facturation, orange money, sénégal" />
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" text @click="showNewTopicDialog = false" />
        <Button label="Publier le sujet" icon="pi pi-send" @click="publishTopic" />
      </template>
    </Dialog>

    <!-- AI Assistant -->
    <AiAssistantPanel v-if="guidance" :guidance="guidance" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import AiAssistantPanel from '@/Components/AI/AiAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'

const { guidance } = useAiAssistant('CRM', 'view_dashboard')

const page = usePage()
const { isAdmin, isElevated, hasAnyRole } = useRoleAccess()
const canManage = computed(() => isElevated.value || hasAnyRole(['customer-service']))
const canCreate = computed(() => canManage.value)
const canEdit = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)


const auth = computed(() => page.props.auth)

const searchQuery = ref('')
const selectedCategory = ref<number | null>(null)
const sortBy = ref('recent')
const showNewTopicDialog = ref(false)

const stats = ref({ sujets: 234, membres: 1840, posts: 892, tauxResolution: 74 })

const sortOptions = [
  { label: 'Plus récents', value: 'recent' },
  { label: 'Plus actifs', value: 'actif' },
  { label: 'Non résolus', value: 'ouvert' },
]

const forumCategories = ref([
  { id: 1, label: 'Support général', icon: 'pi pi-life-ring', count: 87, color: '#3b82f6' },
  { id: 2, label: 'Bugs signalés', icon: 'pi pi-bug', count: 42, color: '#ef4444' },
  { id: 3, label: 'Fonctionnalités', icon: 'pi pi-lightbulb', count: 65, color: '#f59e0b' },
  { id: 4, label: 'Annonces', icon: 'pi pi-megaphone', count: 18, color: '#10b981' },
  { id: 5, label: 'Intégrations', icon: 'pi pi-link', count: 22, color: '#8b5cf6' },
])

const categoryOptions = forumCategories.value.map(c => ({ label: c.label, value: c.label }))

const catColorMap: Record<number, string> = {
  1: '#3b82f6', 2: '#ef4444', 3: '#f59e0b', 4: '#10b981', 5: '#8b5cf6',
}

const getCatColor = (id: number) => catColorMap[id] ?? '#6b7280'

const trendingTags = ['Orange Money', 'OHADA', 'import Excel', 'facture', 'wave pay', 'mobile', 'webhook', 'permission']

const topics = ref([
  { id: 1, categorieId: 4, categorie: 'Annonces', titre: 'WideHalo ERP v2.8 — Notes de version (mai 2026)', extrait: 'La version 2.8 apporte le support Wave Business API, l\'amélioration des rapports OHADA et la synchronisation mobile hors ligne améliorée.', auteur: 'Équipe WideHalo', avatarColor: '#10b981', reponses: 24, vues: 1840, dernierMessage: 'il y a 2h', statut: 'Épinglé', epingle: true },
  { id: 2, categorieId: 1, categorie: 'Support général', titre: 'Comment configurer Wave comme méthode de paiement principale ?', extrait: 'J\'ai essayé de suivre la documentation mais le webhook ne reçoit pas les confirmations de paiement.', auteur: 'Mamadou Diallo', avatarColor: '#3b82f6', reponses: 8, vues: 312, dernierMessage: 'il y a 4h', statut: 'Résolu', epingle: false },
  { id: 3, categorieId: 2, categorie: 'Bugs signalés', titre: 'Bug : rapport OHADA vide pour les sociétés CEMAC', extrait: 'Le rapport Plan Comptable OHADA ne génère pas les classes 1-8 correctement pour les comptes CEMAC (XAF).', auteur: 'Kouamé Assi', avatarColor: '#ef4444', reponses: 5, vues: 187, dernierMessage: 'il y a 6h', statut: 'Ouvert', epingle: false },
  { id: 4, categorieId: 3, categorie: 'Fonctionnalités', titre: 'Suggestion : intégration M-Pesa pour le Kenya', extrait: 'Beaucoup d\'entreprises kenyanes utilisent M-Pesa. Serait-il possible d\'ajouter le support natif ?', auteur: 'Aisha Kamau', avatarColor: '#8b5cf6', reponses: 17, vues: 543, dernierMessage: 'il y a 1j', statut: 'Ouvert', epingle: false },
  { id: 5, categorieId: 1, categorie: 'Support général', titre: 'Importer des fournisseurs depuis un fichier CSV avec accents', extrait: 'Mon fichier CSV contient des noms avec des caractères spéciaux (é, è, à). L\'import les remplace par des caractères bizarres.', auteur: 'Fatima Ouédraogo', avatarColor: '#f59e0b', reponses: 3, vues: 98, dernierMessage: 'il y a 1j', statut: 'Résolu', epingle: false },
  { id: 6, categorieId: 5, categorie: 'Intégrations', titre: 'Webhook Zapier : format des événements de vente', extrait: 'Quel est le format JSON envoyé par WideHalo lors d\'un événement "commande confirmée" ? Je voudrais l\'intégrer à Zapier.', auteur: 'Pierre Tabi', avatarColor: '#06b6d4', reponses: 6, vues: 224, dernierMessage: 'il y a 2j', statut: 'Ouvert', epingle: false },
  { id: 7, categorieId: 2, categorie: 'Bugs signalés', titre: 'L\'application mobile se déconnecte après 5 min d\'inactivité', extrait: 'Sur Android 14, l\'app se déconnecte toute seule même si le mode "Se souvenir de moi" est activé.', auteur: 'Oumar Sanogo', avatarColor: '#ec4899', reponses: 12, vues: 401, dernierMessage: 'il y a 3j', statut: 'Ouvert', epingle: false },
])

const newTopic = ref({ titre: '', categorie: null as string | null, contenu: '', tags: '' })

const filteredTopics = computed(() => {
  let list = topics.value
  if (selectedCategory.value !== null) list = list.filter(t => t.categorieId === selectedCategory.value)
  if (searchQuery.value) list = list.filter(t =>
    t.titre.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
    t.extrait.toLowerCase().includes(searchQuery.value.toLowerCase())
  )
  if (sortBy.value === 'actif') list = [...list].sort((a, b) => b.reponses - a.reponses)
  else if (sortBy.value === 'ouvert') list = list.filter(t => t.statut === 'Ouvert')
  // Pinned always first
  return [...list.filter(t => t.epingle), ...list.filter(t => !t.epingle)]
})

const topicStatusSeverity = (s: string) => {
  const map: Record<string, string> = { Résolu: 'success', Ouvert: 'warn', Épinglé: 'info' }
  return map[s] ?? 'secondary'
}

const goToTopic = (topic: any) => {
  window.location.href = `/helpdesk/forum/${topic.id}`
}

const publishTopic = () => {
  showNewTopicDialog.value = false
  newTopic.value = { titre: '', categorie: null, contenu: '', tags: '' }
}
</script>
