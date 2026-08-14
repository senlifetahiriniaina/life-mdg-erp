<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Base de connaissances</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Articles, FAQ et guides — réduisez le volume de tickets</p>
      </div>
      <Button label="Nouvel article" icon="pi pi-plus" @click="showCreateDialog = true" />
    </div>

    <!-- Search bar -->
    <Card>
      <template #content>
        <div class="flex items-center gap-3 flex-wrap">
          <div class="relative flex-1 min-w-[200px]">
            <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 z-10"></i>
            <InputText
              v-model="searchQuery"
              placeholder="Rechercher un article, une question fréquente..."
              class="w-full pl-9"
            />
          </div>
          <Select
            v-model="selectedLang"
            :options="langOptions"
            option-label="label"
            option-value="value"
            placeholder="Langue"
            class="w-36"
          />
        </div>
      </template>
    </Card>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <Card class="border-l-4 border-blue-500">
        <template #content>
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
              <i class="pi pi-file-edit text-blue-600 text-lg"></i>
            </div>
            <div>
              <p class="text-xs text-gray-500">Articles publiés</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.articles }}</p>
            </div>
          </div>
        </template>
      </Card>
      <Card class="border-l-4 border-green-500">
        <template #content>
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
              <i class="pi pi-eye text-green-600 text-lg"></i>
            </div>
            <div>
              <p class="text-xs text-gray-500">Vues totales</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.vues.toLocaleString('fr-FR') }}</p>
            </div>
          </div>
        </template>
      </Card>
      <Card class="border-l-4 border-purple-500">
        <template #content>
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center">
              <i class="pi pi-chart-pie text-purple-600 text-lg"></i>
            </div>
            <div>
              <p class="text-xs text-gray-500">Résolution sans ticket</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.tauxResolution }}%</p>
            </div>
          </div>
        </template>
      </Card>
      <Card class="border-l-4 border-yellow-500">
        <template #content>
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-yellow-100 flex items-center justify-center">
              <i class="pi pi-star text-yellow-600 text-lg"></i>
            </div>
            <div>
              <p class="text-xs text-gray-500">Note moyenne</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.noteMoyenne }}/5</p>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <!-- Main layout: sidebar + articles -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
      <!-- Category sidebar -->
      <div>
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider px-1 mb-3">Catégories</p>
        <div class="space-y-1">
          <div
            v-for="cat in categories"
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
              :class="selectedCategory === cat.id
                ? 'bg-blue-500 text-white'
                : 'bg-gray-200 dark:bg-gray-600 text-gray-600 dark:text-gray-300'"
            >{{ cat.count }}</span>
          </div>
        </div>
      </div>

      <!-- Articles grid -->
      <div class="lg:col-span-3">
        <div v-if="filteredArticles.length === 0" class="text-center py-12 text-gray-400">
          <i class="pi pi-search text-4xl mb-3 block"></i>
          <p>Aucun article trouvé pour votre recherche.</p>
        </div>
        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <Card
            v-for="article in filteredArticles"
            :key="article.id"
            class="cursor-pointer hover:shadow-md transition-shadow"
            @click="openArticle(article)"
          >
            <template #content>
              <div class="space-y-3">
                <div class="flex items-start justify-between gap-2">
                  <div class="flex items-center gap-1 flex-wrap">
                    <span
                      class="text-xs px-2 py-0.5 rounded-full font-medium"
                      :style="{ backgroundColor: article.categoryColor + '20', color: article.categoryColor }"
                    >{{ article.category }}</span>
                    <Tag v-if="article.statut === 'Brouillon'" value="Brouillon" severity="secondary" class="text-xs" />
                  </div>
                  <div class="flex items-center gap-0.5 text-yellow-400 text-xs flex-shrink-0">
                    <i v-for="n in 5" :key="n" class="pi text-xs" :class="n <= Math.round(article.note) ? 'pi-star-fill' : 'pi-star'"></i>
                  </div>
                </div>
                <h3 class="font-semibold text-gray-900 dark:text-white text-sm leading-tight">{{ article.titre }}</h3>
                <p class="text-xs text-gray-500 line-clamp-2">{{ article.extrait }}</p>
                <div class="flex items-center justify-between text-xs text-gray-400 pt-1 border-t border-gray-100 dark:border-gray-700">
                  <span><i class="pi pi-eye mr-1"></i>{{ article.vues.toLocaleString('fr-FR') }} vues</span>
                  <span>{{ article.updatedAt }}</span>
                </div>
              </div>
            </template>
          </Card>
        </div>
      </div>
    </div>

    <!-- Create Article Dialog -->
    <Dialog v-model:visible="showCreateDialog" header="Nouvel article" :style="{ width: '620px' }" modal>
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Titre *</label>
          <InputText v-model="newArticle.titre" class="w-full" placeholder="Titre de l'article" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Catégorie</label>
            <Select v-model="newArticle.category" :options="categoryOptions" option-label="label" option-value="value" class="w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Statut</label>
            <Select v-model="newArticle.statut" :options="statutOptions" option-label="label" option-value="value" class="w-full" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contenu *</label>
          <Textarea v-model="newArticle.contenu" rows="8" class="w-full" placeholder="Rédigez votre article ici..." />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tags (séparés par virgule)</label>
          <InputText v-model="newArticle.tags" class="w-full" placeholder="ex: paiement, orange money, erreur" />
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" text @click="showCreateDialog = false" />
        <Button label="Enregistrer brouillon" severity="secondary" outlined @click="saveArticle('Brouillon')" />
        <Button label="Publier" icon="pi pi-check" @click="saveArticle('Publié')" />
      </template>
    </Dialog>

    <!-- Article detail Drawer -->
    <Drawer v-model:visible="showArticleDrawer" position="right" :style="{ width: '600px' }">
      <template #header>
        <span class="font-semibold text-gray-800 dark:text-gray-200">{{ selectedArticle?.titre }}</span>
      </template>
      <div v-if="selectedArticle" class="space-y-4 text-sm">
        <div class="flex items-center gap-4 text-gray-400 text-xs flex-wrap">
          <span
            class="text-xs px-2 py-0.5 rounded-full font-medium"
            :style="{ backgroundColor: selectedArticle.categoryColor + '20', color: selectedArticle.categoryColor }"
          >{{ selectedArticle.category }}</span>
          <span><i class="pi pi-eye mr-1"></i>{{ selectedArticle.vues.toLocaleString('fr-FR') }} vues</span>
          <span>Mis à jour le {{ selectedArticle.updatedAt }}</span>
          <div class="flex items-center gap-0.5 text-yellow-400">
            <i v-for="n in 5" :key="n" class="pi text-xs" :class="n <= Math.round(selectedArticle.note) ? 'pi-star-fill' : 'pi-star'"></i>
          </div>
        </div>
        <div class="text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-line border-t border-gray-100 dark:border-gray-700 pt-4">
          {{ selectedArticle.contenu }}
        </div>
        <div class="flex flex-wrap gap-1 pt-2 border-t border-gray-100 dark:border-gray-700">
          <span v-for="tag in selectedArticle.tagsList" :key="tag" class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-500 rounded-full px-2 py-0.5">{{ tag }}</span>
        </div>
        <div class="flex gap-2 pt-2">
          <Button label="Cet article est utile" icon="pi pi-thumbs-up" severity="success" outlined size="small" />
          <Button label="Pas utile" icon="pi pi-thumbs-down" severity="secondary" outlined size="small" />
        </div>
      </div>
    </Drawer>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import Drawer from 'primevue/drawer'
import Select from 'primevue/select'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'

const page = usePage()
const { isAdmin, isElevated, hasAnyRole } = useRoleAccess()
const canManage = computed(() => isElevated.value || hasAnyRole(['customer-service']))
const canCreate = computed(() => canManage.value)
const canEdit = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)


const auth = computed(() => page.props.auth)

const searchQuery = ref('')
const selectedCategory = ref<number | null>(null)
const selectedLang = ref('fr')
const showCreateDialog = ref(false)
const showArticleDrawer = ref(false)
const selectedArticle = ref<any>(null)

const stats = ref({ articles: 86, vues: 12840, tauxResolution: 67, noteMoyenne: 4.3 })

const langOptions = [
  { label: 'Français', value: 'fr' },
  { label: 'English', value: 'en' },
  { label: 'Wolof', value: 'wo' },
]

const categories = ref([
  { id: 1, label: 'Prise en main', icon: 'pi pi-play-circle', count: 18, color: '#3b82f6' },
  { id: 2, label: 'Paiements', icon: 'pi pi-credit-card', count: 22, color: '#10b981' },
  { id: 3, label: 'Facturation', icon: 'pi pi-file-pdf', count: 14, color: '#f59e0b' },
  { id: 4, label: 'Inventaire', icon: 'pi pi-box', count: 11, color: '#8b5cf6' },
  { id: 5, label: 'RH & Paie', icon: 'pi pi-users', count: 9, color: '#ef4444' },
  { id: 6, label: 'Mobile App', icon: 'pi pi-mobile', count: 12, color: '#06b6d4' },
])

const categoryOptions = categories.value.map(c => ({ label: c.label, value: c.label }))
const statutOptions = [{ label: 'Publié', value: 'Publié' }, { label: 'Brouillon', value: 'Brouillon' }]

const articles = ref([
  { id: 1, categoryId: 1, category: 'Prise en main', categoryColor: '#3b82f6', titre: 'Comment créer votre première facture', extrait: 'Ce guide vous accompagne pas à pas dans la création de votre première facture avec WideHalo ERP.', vues: 3420, note: 4.7, updatedAt: '22/05/2026', statut: 'Publié', tagsList: ['facture', 'démarrage', 'tutoriel'], contenu: 'Pour créer votre première facture :\n\n1. Allez dans Facturation > Nouvelle facture\n2. Sélectionnez le client dans la liste ou créez-en un nouveau\n3. Ajoutez les lignes de produits ou services\n4. Vérifiez la TVA applicable (18% pour le Sénégal)\n5. Cliquez sur "Valider et envoyer"\n\nVotre facture est maintenant envoyée au client par email.' },
  { id: 2, categoryId: 2, category: 'Paiements', categoryColor: '#10b981', titre: 'Activer le paiement Orange Money', extrait: 'Configurez Orange Money comme moyen de paiement pour vos clients au Sénégal, Côte d\'Ivoire et Mali.', vues: 2180, note: 4.5, updatedAt: '20/05/2026', statut: 'Publié', tagsList: ['orange money', 'mobile money', 'sénégal'], contenu: 'Pour activer Orange Money :\n\n1. Allez dans Paramètres > Paiements\n2. Activez "Orange Money" dans la section Mobile Money\n3. Entrez votre numéro marchand Orange\n4. Validez avec le code OTP reçu par SMS\n5. Effectuez un test de paiement de 1 XOF' },
  { id: 3, categoryId: 3, category: 'Facturation', categoryColor: '#f59e0b', titre: 'Gérer les avoirs et remboursements', extrait: 'Comment créer un avoir client et effectuer un remboursement sur Orange Money ou Wave.', vues: 1540, note: 4.2, updatedAt: '18/05/2026', statut: 'Publié', tagsList: ['avoir', 'remboursement', 'annulation'], contenu: 'Pour créer un avoir :\n\n1. Ouvrez la facture concernée\n2. Cliquez sur "Créer un avoir"\n3. Indiquez le motif et le montant\n4. Choisissez le mode de remboursement\n5. Validez — l\'avoir est créé automatiquement.' },
  { id: 4, categoryId: 4, category: 'Inventaire', categoryColor: '#8b5cf6', titre: 'Importer vos produits via Excel', extrait: 'Importez jusqu\'à 10 000 produits en une fois depuis un fichier Excel (.xlsx) ou CSV.', vues: 1890, note: 4.6, updatedAt: '21/05/2026', statut: 'Publié', tagsList: ['import', 'excel', 'produits', 'stock'], contenu: 'Pour importer vos produits :\n\n1. Téléchargez le modèle Excel depuis Inventaire > Import\n2. Remplissez les colonnes : code, nom, prix, stock, catégorie\n3. Sauvegardez en format .xlsx\n4. Uploadez le fichier\n5. L\'IA mappe automatiquement vos colonnes\n6. Validez et lancez l\'import.' },
  { id: 5, categoryId: 5, category: 'RH & Paie', categoryColor: '#ef4444', titre: 'Calculer les congés payés (droit sénégalais)', extrait: 'Le calcul des congés payés selon le Code du Travail sénégalais : 2,5 jours ouvrables par mois travaillé.', vues: 980, note: 4.4, updatedAt: '15/05/2026', statut: 'Publié', tagsList: ['congés', 'RH', 'sénégal', 'droit du travail'], contenu: 'Selon le Code du Travail sénégalais :\n- Droit : 2,5 jours ouvrables / mois\n- Après 12 mois : 30 jours ouvrables\n- Ancienneté > 5 ans : +1 jour / tranche de 5 ans' },
  { id: 6, categoryId: 6, category: 'Mobile App', categoryColor: '#06b6d4', titre: 'Utiliser l\'app en mode hors ligne', extrait: 'L\'application mobile WideHalo fonctionne sans connexion. Vos données sont synchronisées dès que vous retrouvez du réseau.', vues: 1320, note: 4.1, updatedAt: '19/05/2026', statut: 'Publié', tagsList: ['mobile', 'hors ligne', 'synchronisation'], contenu: 'Mode hors ligne :\n\n1. Téléchargez vos données avant de quitter une zone couverte\n2. Travaillez normalement (ventes, bons de livraison, etc.)\n3. À la reconnexion, la synchronisation démarre automatiquement\n4. Les conflits sont résolus automatiquement (dernière modification gagne)' },
])

const newArticle = ref({ titre: '', category: null as string | null, contenu: '', tags: '', statut: 'Publié' })

const filteredArticles = computed(() => {
  return articles.value.filter(a => {
    const matchSearch = !searchQuery.value ||
      a.titre.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      a.extrait.toLowerCase().includes(searchQuery.value.toLowerCase())
    const matchCat = selectedCategory.value === null || a.categoryId === selectedCategory.value
    return matchSearch && matchCat
  })
})

const openArticle = (article: any) => {
  selectedArticle.value = article
  showArticleDrawer.value = true
}

const saveArticle = (_statut: string) => {
  showCreateDialog.value = false
  newArticle.value = { titre: '', category: null, contenu: '', tags: '', statut: 'Publié' }
}
</script>
