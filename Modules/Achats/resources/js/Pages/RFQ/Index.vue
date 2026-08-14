<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">Appels d'Offres & Enchères</h1>
        <p class="mt-2 text-surface-600 dark:text-surface-400">Gérez vos appels d'offres, recevez des soumissions et attribuez les marchés</p>
      </div>
      <div class="flex gap-2">
        <button
          @click="showAiPanel = !showAiPanel"
          class="px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg hover:bg-gray-50 dark:hover:bg-surface-700 text-surface-700 dark:text-surface-300"
          title="Assistant IA"
        >
          <i class="pi pi-sparkles" />
        </button>
        <button
          v-if="hasRole('purchasing-manager') || isAdmin"
          @click="showCreateDialog = true"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
        >
          + Créer un AO
        </button>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="bg-canvas dark:bg-surface-800 rounded-lg shadow p-5">
        <div class="text-sm text-surface-500 dark:text-surface-400">AO en cours</div>
        <div class="text-3xl font-bold text-surface-900 dark:text-surface-50 mt-1">{{ stats.inProgress }}</div>
        <div class="text-xs text-blue-600 mt-1">{{ stats.closing }} clôturent cette semaine</div>
      </div>
      <div class="bg-canvas dark:bg-surface-800 rounded-lg shadow p-5">
        <div class="text-sm text-surface-500 dark:text-surface-400">Offres reçues</div>
        <div class="text-3xl font-bold text-surface-900 dark:text-surface-50 mt-1">{{ stats.bidsReceived }}</div>
        <div class="text-xs text-green-600 mt-1">+12 cette semaine</div>
      </div>
      <div class="bg-canvas dark:bg-surface-800 rounded-lg shadow p-5">
        <div class="text-sm text-surface-500 dark:text-surface-400">AO attribués ce mois</div>
        <div class="text-3xl font-bold text-surface-900 dark:text-surface-50 mt-1">{{ stats.awarded }}</div>
        <div class="text-xs text-surface-500 mt-1">Sur {{ stats.totalThisMonth }} AO créés</div>
      </div>
      <div class="bg-canvas dark:bg-surface-800 rounded-lg shadow p-5">
        <div class="text-sm text-surface-500 dark:text-surface-400">Économies réalisées</div>
        <div class="text-3xl font-bold text-green-600 dark:text-green-400 mt-1">{{ formatXOF(stats.savings) }}</div>
        <div class="text-xs text-green-600 mt-1">vs budget initial</div>
      </div>
    </div>

    <!-- RFQ Table -->
    <div class="bg-canvas dark:bg-surface-800 rounded-lg shadow">
      <div class="p-6 border-b border-subtle">
        <div class="flex items-center gap-4">
          <input
            v-model="search"
            type="text"
            placeholder="Rechercher par référence, description..."
            class="flex-1 px-4 py-2 border border-subtle rounded-lg focus:ring-blue-500 focus:border-transparent"
          />
          <select
            v-model="filterStatus"
            class="px-4 py-2 border border-subtle rounded-lg focus:ring-blue-500"
          >
            <option value="">Tous les statuts</option>
            <option value="Ouvert">Ouvert</option>
            <option value="En évaluation">En évaluation</option>
            <option value="Attribué">Attribué</option>
            <option value="Annulé">Annulé</option>
          </select>
        </div>
      </div>

      <table class="w-full">
        <thead class="bg-surface-50 dark:bg-surface-800 border-b border-subtle">
          <tr>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Référence</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Description</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Catégorie</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Date clôture</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Soumissionnaires</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Meilleure offre</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Statut</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="rfq in filteredRFQs"
            :key="rfq.id"
            class="border-b border-subtle hover:bg-surface-50 dark:hover:bg-surface-700"
          >
            <td class="px-6 py-4 text-sm font-mono font-medium text-surface-900 dark:text-surface-50">{{ rfq.reference }}</td>
            <td class="px-6 py-4">
              <div class="font-medium text-surface-900 dark:text-surface-50 text-sm">{{ rfq.description }}</div>
            </td>
            <td class="px-6 py-4 text-sm text-surface-600 dark:text-surface-400">{{ rfq.category }}</td>
            <td class="px-6 py-4 text-sm text-surface-600 dark:text-surface-400">
              <span :class="isClosingSoon(rfq.closingDate) ? 'text-red-600 font-medium' : ''">
                {{ rfq.closingDate }}
              </span>
            </td>
            <td class="px-6 py-4 text-sm text-center">
              <span class="px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded-full text-xs font-medium">
                {{ rfq.bids.length }}
              </span>
            </td>
            <td class="px-6 py-4 text-sm font-medium text-green-700 dark:text-green-300">
              {{ rfq.bestBid ? formatXOF(rfq.bestBid) : '—' }}
            </td>
            <td class="px-6 py-4 text-sm">
              <span :class="rfqStatusClass(rfq.status)">{{ rfq.status }}</span>
            </td>
            <td class="px-6 py-4 text-sm space-x-2">
              <button
                @click="selectRFQ(rfq)"
                class="text-primary-700 dark:text-primary-300 hover:underline"
              >
                Voir offres
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Bids Panel -->
    <div
      v-if="selectedRFQ"
      class="bg-canvas dark:bg-surface-800 rounded-lg shadow"
    >
      <div class="p-6 border-b border-subtle flex items-center justify-between">
        <div>
          <h2 class="text-lg font-bold text-surface-900 dark:text-surface-50">
            Offres pour {{ selectedRFQ.reference }} — {{ selectedRFQ.description }}
          </h2>
          <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
            {{ selectedRFQ.bids.length }} soumissionnaire(s) — Clôture: {{ selectedRFQ.closingDate }}
          </p>
        </div>
        <button
          @click="selectedRFQ = null"
          class="text-surface-500 hover:text-surface-700 dark:hover:text-surface-300"
        >
          <i class="pi pi-times text-lg" />
        </button>
      </div>
      <table class="w-full">
        <thead class="bg-surface-50 dark:bg-surface-800 border-b border-subtle">
          <tr>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Fournisseur</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Montant (XOF)</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Délai livraison</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Score global</th>
            <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-surface-700 dark:text-surface-300">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="bid in selectedRFQ.bids"
            :key="bid.id"
            class="border-b border-subtle hover:bg-surface-50 dark:hover:bg-surface-700"
            :class="bid.amount === selectedRFQ.bestBid ? 'bg-green-50 dark:bg-green-900/20' : ''"
          >
            <td class="px-6 py-4">
              <div class="font-medium text-surface-900 dark:text-surface-50 text-sm">{{ bid.supplier }}</div>
              <div v-if="bid.amount === selectedRFQ.bestBid" class="text-xs text-green-600 font-medium">Meilleure offre</div>
            </td>
            <td class="px-6 py-4 text-sm font-medium text-surface-900 dark:text-surface-50">
              {{ formatXOF(bid.amount) }}
            </td>
            <td class="px-6 py-4 text-sm text-surface-600 dark:text-surface-400">{{ bid.deliveryDays }} jours</td>
            <td class="px-6 py-4 text-sm">
              <div class="flex items-center gap-2">
                <div class="flex-1 bg-surface-200 dark:bg-surface-700 rounded-full h-2 w-16">
                  <div
                    class="h-2 rounded-full"
                    :class="bid.score >= 75 ? 'bg-green-500' : bid.score >= 50 ? 'bg-yellow-500' : 'bg-red-500'"
                    :style="{ width: bid.score + '%' }"
                  />
                </div>
                <span class="font-medium text-surface-900 dark:text-surface-50">{{ bid.score }}/100</span>
              </div>
            </td>
            <td class="px-6 py-4 text-sm">
              <button
                v-if="isAdmin && selectedRFQ.status === 'En évaluation'"
                @click="awardRFQ(selectedRFQ, bid)"
                class="px-3 py-1 bg-green-600 text-white rounded-lg hover:bg-green-700 text-xs font-medium"
              >
                Attribuer
              </button>
              <span v-else-if="selectedRFQ.status === 'Attribué' && bid.awarded" class="text-xs text-green-600 font-medium">
                Attribué
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create RFQ Dialog -->
    <div
      v-if="showCreateDialog"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="showCreateDialog = false"
    >
      <div class="bg-canvas dark:bg-surface-800 rounded-xl shadow-2xl p-8 w-full max-w-lg">
        <h2 class="text-xl font-bold text-surface-900 dark:text-surface-50 mb-6">Créer un Appel d'Offres</h2>
        <div class="space-y-4">
          <div>
            <label for="description" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Description *</label>
            <input id="description"
              v-model="createForm.description"
              type="text"
              placeholder="Ex: Fourniture de matériel informatique Q3 2026"
              class="w-full px-4 py-2 border border-subtle rounded-lg focus:ring-blue-500 focus:border-transparent"
            />
          </div>
          <div>
            <label for="cat-gorie" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Catégorie *</label>
            <select id="cat-gorie"
              v-model="createForm.category"
              class="w-full px-4 py-2 border border-subtle rounded-lg focus:ring-blue-500"
            >
              <option value="">Sélectionner une catégorie</option>
              <option value="Matières premières">Matières premières</option>
              <option value="Services informatiques">Services informatiques</option>
              <option value="Logistique & Transport">Logistique & Transport</option>
              <option value="Fournitures de bureau">Fournitures de bureau</option>
              <option value="Équipements industriels">Équipements industriels</option>
              <option value="Services professionnels">Services professionnels</option>
            </select>
          </div>
          <div>
            <label for="date-limite-de-soumission" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Date limite de soumission *</label>
            <input id="date-limite-de-soumission"
              v-model="createForm.closingDate"
              type="date"
              class="w-full px-4 py-2 border border-subtle rounded-lg focus:ring-blue-500 focus:border-transparent"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">Fournisseurs invités</label>
            <div class="border border-subtle rounded-lg p-3 space-y-2 max-h-40 overflow-y-auto">
              <label
                v-for="supplier in availableSuppliers"
                :key="supplier.id"
                class="flex items-center gap-2 cursor-pointer hover:bg-surface-50 dark:hover:bg-surface-700 p-1 rounded"
              >
                <input
                  type="checkbox"
                  :value="supplier.id"
                  v-model="createForm.invitedSuppliers"
                  class="rounded"
                />
                <span class="text-sm text-surface-700 dark:text-surface-300">{{ supplier.name }}</span>
                <span class="text-xs text-surface-500 dark:text-surface-400">({{ supplier.category }})</span>
              </label>
            </div>
          </div>
        </div>
        <div class="flex gap-3 mt-6">
          <button
            @click="createRFQ"
            :disabled="creating"
            class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
          >
            {{ creating ? 'Création...' : 'Créer l\'appel d\'offres' }}
          </button>
          <button
            @click="showCreateDialog = false"
            class="px-4 py-2 border border-subtle rounded-lg hover:bg-surface-50 dark:hover:bg-surface-700 text-surface-700 dark:text-surface-300"
          >
            Annuler
          </button>
        </div>
      </div>
    </div>

    <AIAssistantPanel v-if="showAiPanel" :guidance="guidance" @close="showAiPanel = false" />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import { useRoleAccess } from '@/composables/useRoleAccess'

const page = usePage()
const canManage = computed(() => isElevated.value || hasAnyRole(['purchasing-manager']))
const canCreate = computed(() => canManage.value)
const canEdit = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)


const { guidance } = useAiAssistant('Achats', 'view_dashboard')
const showAiPanel = ref(false)
const { isAdmin, hasRole, isElevated, hasAnyRole } = useRoleAccess()

const showCreateDialog = ref(false)
const selectedRFQ = ref(null)
const search = ref('')
const filterStatus = ref('')
const creating = ref(false)

const createForm = ref({
  description: '',
  category: '',
  closingDate: '',
  invitedSuppliers: []
})

const stats = ref({
  inProgress: 6,
  closing: 2,
  bidsReceived: 34,
  awarded: 4,
  totalThisMonth: 7,
  savings: 18750000
})

const availableSuppliers = ref([
  { id: 1, name: 'Dakar Supplies SARL', category: 'Matières premières' },
  { id: 2, name: 'Abidjan Tech Services', category: 'Services informatiques' },
  { id: 3, name: 'TransAfrique Logistique', category: 'Logistique & Transport' },
  { id: 4, name: 'Accra Industrial Group', category: 'Équipements industriels' },
  { id: 5, name: 'Nairobi Consulting Ltd', category: 'Services professionnels' },
  { id: 6, name: 'BureauPlus Bamako', category: 'Fournitures de bureau' },
])

const rfqs = ref([
  {
    id: 1,
    reference: 'AO-2026-0041',
    description: 'Fourniture de matériel informatique',
    category: 'Services informatiques',
    closingDate: '30 mai 2026',
    status: 'En évaluation',
    bestBid: 12500000,
    bids: [
      { id: 1, supplier: 'Abidjan Tech Services', amount: 12500000, deliveryDays: 14, score: 91, awarded: false },
      { id: 2, supplier: 'Nairobi Consulting Ltd', amount: 13200000, deliveryDays: 10, score: 87, awarded: false },
      { id: 3, supplier: 'BureauPlus Bamako', amount: 14800000, deliveryDays: 21, score: 72, awarded: false },
    ]
  },
  {
    id: 2,
    reference: 'AO-2026-0040',
    description: 'Transport et logistique Q2',
    category: 'Logistique & Transport',
    closingDate: '25 mai 2026',
    status: 'Ouvert',
    bestBid: null,
    bids: [
      { id: 4, supplier: 'TransAfrique Logistique', amount: 8900000, deliveryDays: 3, score: 78, awarded: false },
    ]
  },
  {
    id: 3,
    reference: 'AO-2026-0038',
    description: 'Fournitures de bureau annuelles',
    category: 'Fournitures de bureau',
    closingDate: '15 mai 2026',
    status: 'Attribué',
    bestBid: 3200000,
    bids: [
      { id: 5, supplier: 'BureauPlus Bamako', amount: 3200000, deliveryDays: 7, score: 85, awarded: true },
      { id: 6, supplier: 'Dakar Supplies SARL', amount: 3850000, deliveryDays: 5, score: 79, awarded: false },
    ]
  },
  {
    id: 4,
    reference: 'AO-2026-0037',
    description: 'Équipements de production ligne B',
    category: 'Équipements industriels',
    closingDate: '10 juin 2026',
    status: 'Ouvert',
    bestBid: null,
    bids: []
  },
  {
    id: 5,
    reference: 'AO-2026-0035',
    description: 'Audit & conseil stratégique 2026',
    category: 'Services professionnels',
    closingDate: '01 juin 2026',
    status: 'Ouvert',
    bestBid: null,
    bids: [
      { id: 7, supplier: 'Nairobi Consulting Ltd', amount: 22000000, deliveryDays: 30, score: 94, awarded: false },
    ]
  },
  {
    id: 6,
    reference: 'AO-2026-0033',
    description: 'Matières premières agricoles T3',
    category: 'Matières premières',
    closingDate: '05 juin 2026',
    status: 'Ouvert',
    bestBid: null,
    bids: [
      { id: 8, supplier: 'Dakar Supplies SARL', amount: 47500000, deliveryDays: 12, score: 83, awarded: false },
      { id: 9, supplier: 'Accra Industrial Group', amount: 52000000, deliveryDays: 8, score: 77, awarded: false },
    ]
  },
])

const filteredRFQs = computed(() => {
  return rfqs.value.filter(r => {
    const matchSearch = !search.value ||
      r.reference.toLowerCase().includes(search.value.toLowerCase()) ||
      r.description.toLowerCase().includes(search.value.toLowerCase())
    const matchStatus = !filterStatus.value || r.status === filterStatus.value
    return matchSearch && matchStatus
  })
})

const formatXOF = (amount) => {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF', minimumFractionDigits: 0 }).format(amount)
}

const isClosingSoon = (dateStr) => {
  // Simple heuristic: if the date is within 7 days
  const closingDate = new Date(dateStr.split(' ').reverse().join('-'))
  const today = new Date()
  const diffDays = Math.ceil((closingDate - today) / (1000 * 60 * 60 * 24))
  return diffDays >= 0 && diffDays <= 7
}

const rfqStatusClass = (status) => {
  const classes = {
    'Ouvert': 'px-3 py-1 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200',
    'En évaluation': 'px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200',
    'Attribué': 'px-3 py-1 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200',
    'Annulé': 'px-3 py-1 rounded-full text-xs font-medium bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200',
  }
  return classes[status] || 'px-3 py-1 rounded-full text-xs font-medium bg-surface-100 dark:bg-surface-700 text-surface-800 dark:text-surface-200'
}

const selectRFQ = (rfq) => {
  selectedRFQ.value = rfq
}

const awardRFQ = async (rfq, bid) => {
  if (!confirm(`Attribuer l'AO ${rfq.reference} à ${bid.supplier} pour ${formatXOF(bid.amount)} ?`)) return
  try {
    const response = await fetch(`/api/v1/achats/rfq/${rfq.id}/award`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]').content}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ bid_id: bid.id })
    })
    if (response.ok) {
      rfq.status = 'Attribué'
      bid.awarded = true
      stats.value.awarded++
      alert(`AO ${rfq.reference} attribué à ${bid.supplier}.`)
    }
  } catch (error) {
    console.error('Erreur attribution AO:', error)
  }
}

const createRFQ = async () => {
  if (!createForm.value.description || !createForm.value.category || !createForm.value.closingDate) {
    alert('Veuillez remplir les champs obligatoires.')
    return
  }
  creating.value = true
  try {
    const response = await fetch('/api/v1/achats/rfq', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]').content}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(createForm.value)
    })
    if (response.ok) {
      const newId = rfqs.value.length + 1
      const ref = `AO-2026-00${42 + newId}`
      rfqs.value.unshift({
        id: newId,
        reference: ref,
        description: createForm.value.description,
        category: createForm.value.category,
        closingDate: createForm.value.closingDate,
        status: 'Ouvert',
        bestBid: null,
        bids: []
      })
      stats.value.inProgress++
      showCreateDialog.value = false
      createForm.value = { description: '', category: '', closingDate: '', invitedSuppliers: [] }
    }
  } catch (error) {
    console.error('Erreur création AO:', error)
  } finally {
    creating.value = false
  }
}
</script>
