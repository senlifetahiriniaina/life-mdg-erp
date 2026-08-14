<template>
  <div class="space-y-6">
    <!-- Welcome header -->
    <div class="bg-gradient-to-r from-blue-600 to-blue-800 rounded-2xl p-6 text-white">
      <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
          <p class="text-blue-200 text-sm">Bienvenue sur votre espace client</p>
          <h1 class="text-2xl font-bold mt-1">Bonjour, {{ customer.prenom }} !</h1>
          <p class="text-blue-200 text-sm mt-1">{{ customer.entreprise }} &bull; Client depuis {{ customer.depuis }}</p>
        </div>
        <div class="flex items-center gap-3">
          <div class="text-center">
            <p class="text-3xl font-bold">{{ portalStats.ticketsOuverts }}</p>
            <p class="text-blue-200 text-xs">Tickets ouverts</p>
          </div>
          <div class="w-px h-10 bg-blue-400"></div>
          <div class="text-center">
            <p class="text-3xl font-bold">{{ portalStats.ticketsResolus }}</p>
            <p class="text-blue-200 text-xs">Résolus</p>
          </div>
          <div class="w-px h-10 bg-blue-400"></div>
          <div class="text-center">
            <p class="text-3xl font-bold">{{ portalStats.csatMoyen }}</p>
            <p class="text-blue-200 text-xs">CSAT moyen</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick actions -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <button
        class="flex flex-col items-center gap-2 p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-blue-400 hover:shadow-md transition-all group"
        @click="showNewTicketDialog = true"
      >
        <div class="w-12 h-12 rounded-full bg-blue-100 group-hover:bg-blue-600 flex items-center justify-center transition-colors">
          <i class="pi pi-plus text-blue-600 group-hover:text-white text-lg transition-colors"></i>
        </div>
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Nouveau ticket</span>
      </button>
      <button
        class="flex flex-col items-center gap-2 p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-green-400 hover:shadow-md transition-all group"
        @click="openChat"
      >
        <div class="w-12 h-12 rounded-full bg-green-100 group-hover:bg-green-600 flex items-center justify-center transition-colors">
          <i class="pi pi-comments text-green-600 group-hover:text-white text-lg transition-colors"></i>
        </div>
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Chat en direct</span>
      </button>
      <button
        class="flex flex-col items-center gap-2 p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-purple-400 hover:shadow-md transition-all group"
        @click="selectedSection = 'kb'"
      >
        <div class="w-12 h-12 rounded-full bg-purple-100 group-hover:bg-purple-600 flex items-center justify-center transition-colors">
          <i class="pi pi-book text-purple-600 group-hover:text-white text-lg transition-colors"></i>
        </div>
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Base de connaissances</span>
      </button>
      <button
        class="flex flex-col items-center gap-2 p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-orange-400 hover:shadow-md transition-all group"
        @click="window.location.href = '/helpdesk/forum'"
      >
        <div class="w-12 h-12 rounded-full bg-orange-100 group-hover:bg-orange-600 flex items-center justify-center transition-colors">
          <i class="pi pi-users text-orange-600 group-hover:text-white text-lg transition-colors"></i>
        </div>
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Forum communautaire</span>
      </button>
    </div>

    <!-- My tickets -->
    <Card>
      <template #header>
        <div class="px-5 pt-5 pb-2 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
          <span class="font-semibold text-gray-700 dark:text-gray-200">Mes tickets de support</span>
          <div class="flex gap-2">
            <Select v-model="ticketFilter" :options="ticketFilterOptions" option-label="label" option-value="value" class="w-44 text-sm" />
            <Button v-if="canCreate" label="Nouveau ticket" icon="pi pi-plus" size="small" @click="showNewTicketDialog = true" />
          </div>
        </div>
      </template>
      <template #content>
        <DataTable
          :value="filteredMyTickets"
          :paginator="true"
          :rows="8"
          responsive-layout="scroll"
          row-hover
          class="text-sm"
        >
          <Column field="id" header="#" style="width: 80px">
            <template #body="{ data }">
              <span class="font-mono text-xs text-blue-600 font-semibold">#{{ data.id }}</span>
            </template>
          </Column>
          <Column field="sujet" header="Sujet">
            <template #body="{ data }">
              <span class="font-medium text-gray-800 dark:text-gray-200">{{ data.sujet }}</span>
            </template>
          </Column>
          <Column field="statut" header="Statut" style="width: 160px">
            <template #body="{ data }">
              <Tag :value="data.statut" :severity="statutSeverity(data.statut)" class="text-xs" />
            </template>
          </Column>
          <Column field="canal" header="Canal" style="width: 110px">
            <template #body="{ data }">
              <div class="flex items-center gap-1 text-gray-500">
                <i :class="canalIcon(data.canal)" class="text-sm"></i>
                <span class="text-xs">{{ data.canal }}</span>
              </div>
            </template>
          </Column>
          <Column field="derniereMaj" header="Dernière mise à jour" style="width: 160px">
            <template #body="{ data }">
              <span class="text-xs text-gray-500">{{ data.derniereMaj }}</span>
            </template>
          </Column>
          <Column header="Actions" style="width: 130px">
            <template #body="{ data }">
              <div class="flex gap-1">
                <Button icon="pi pi-eye" size="small" severity="secondary" text v-tooltip="'Voir'" @click="viewTicket(data)" />
                <Button
                  v-if="data.statut !== 'Résolu' && data.statut !== 'Fermé'"
                  icon="pi pi-reply"
                  size="small"
                  severity="info"
                  text
                  v-tooltip="'Répondre'"
                  @click="replyToTicket(data)"
                />
              </div>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- Knowledge base categories -->
    <div v-if="selectedSection === 'kb' || true">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Trouvez votre réponse</h2>
        <div class="relative">
          <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 z-10"></i>
          <InputText v-model="kbSearch" placeholder="Rechercher un article..." class="pl-9 w-64" />
        </div>
      </div>
      <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
        <div
          v-for="cat in kbCategories"
          :key="cat.id"
          class="group flex items-center gap-4 p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 cursor-pointer hover:border-blue-400 hover:shadow-md transition-all"
          @click="openKbCategory(cat)"
         role="button" tabindex="0" @keydown.enter.prevent="openKbCategory(cat)">
          <div
            class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0 group-hover:scale-110 transition-transform"
            :style="{ backgroundColor: cat.color + '20' }"
          >
            <i :class="cat.icon" class="text-xl" :style="{ color: cat.color }"></i>
          </div>
          <div>
            <p class="font-semibold text-gray-800 dark:text-gray-200 text-sm">{{ cat.label }}</p>
            <p class="text-xs text-gray-400">{{ cat.articles }} articles</p>
          </div>
          <i class="pi pi-chevron-right text-gray-300 ml-auto group-hover:text-blue-500 transition-colors"></i>
        </div>
      </div>
    </div>

    <!-- Chat widget (floating) -->
    <div class="fixed bottom-6 right-6 z-50">
      <div v-if="chatOpen" class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border border-gray-200 dark:border-gray-700 w-80 mb-3">
        <div class="bg-blue-600 text-white p-4 rounded-t-2xl flex items-center justify-between">
          <div class="flex items-center gap-2">
            <div class="w-2 h-2 rounded-full bg-green-400"></div>
            <span class="font-medium text-sm">Support WideHalo</span>
          </div>
          <button @click="chatOpen = false" class="text-blue-200 hover:text-white transition-colors">
            <i class="pi pi-times"></i>
          </button>
        </div>
        <div class="p-4 h-48 overflow-y-auto space-y-2">
          <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3 text-sm text-gray-700 dark:text-gray-300">
            Bonjour {{ customer.prenom }} ! Comment puis-je vous aider aujourd'hui ?
          </div>
        </div>
        <div class="p-3 border-t border-gray-100 dark:border-gray-700 flex gap-2">
          <InputText v-model="chatMessage" placeholder="Votre message..." class="flex-1 text-sm" @keydown.enter="sendChatMsg" />
          <Button icon="pi pi-send" size="small" @click="sendChatMsg" />
        </div>
      </div>
      <Button
        :icon="chatOpen ? 'pi pi-times' : 'pi pi-comments'"
        rounded
        :severity="chatOpen ? 'secondary' : 'primary'"
        class="w-14 h-14 shadow-lg"
        @click="chatOpen = !chatOpen"
      />
    </div>

    <!-- New Ticket Dialog -->
    <Dialog v-model:visible="showNewTicketDialog" header="Soumettre une demande" :style="{ width: '540px' }" modal>
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sujet *</label>
          <InputText v-model="newTicket.sujet" class="w-full" placeholder="Résumez votre problème en une phrase" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description *</label>
          <Textarea v-model="newTicket.description" rows="5" class="w-full" placeholder="Décrivez votre problème en détail. Plus vous donnez d'informations, plus nous pourrons vous aider rapidement." />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Priorité</label>
            <Select v-model="newTicket.priorite" :options="prioriteOptions" option-label="label" option-value="value" class="w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Canal préféré</label>
            <Select v-model="newTicket.canal" :options="canalOptions" option-label="label" option-value="value" class="w-full" />
          </div>
        </div>
        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3 text-xs text-blue-700 dark:text-blue-300 flex items-start gap-2">
          <i class="pi pi-info-circle mt-0.5"></i>
          <span>Temps de réponse moyen : <strong>4h</strong> (priorité normale). Pour les urgences, utilisez le chat en direct.</span>
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" text @click="showNewTicketDialog = false" />
        <Button label="Soumettre ma demande" icon="pi pi-send" @click="submitTicket" />
      </template>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Card from 'primevue/card'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
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

const showNewTicketDialog = ref(false)
const selectedSection = ref('tickets')
const chatOpen = ref(false)
const chatMessage = ref('')
const kbSearch = ref('')
const ticketFilter = ref('all')

const customer = ref({
  prenom: 'Fatou',
  nom: 'Koné',
  entreprise: 'Grand Marché SARL — Dakar',
  depuis: 'janvier 2025',
})

const portalStats = ref({ ticketsOuverts: 2, ticketsResolus: 18, csatMoyen: '4.6/5' })

const ticketFilterOptions = [
  { label: 'Tous les tickets', value: 'all' },
  { label: 'Ouverts', value: 'ouvert' },
  { label: 'Résolus', value: 'resolu' },
]

const newTicket = ref({ sujet: '', description: '', priorite: 'Normale', canal: 'Email' })

const prioriteOptions = [
  { label: 'Normale', value: 'Normale' },
  { label: 'Haute', value: 'Haute' },
  { label: 'Critique', value: 'Critique' },
]

const canalOptions = [
  { label: 'Email', value: 'Email' },
  { label: 'Chat', value: 'Chat' },
  { label: 'WhatsApp', value: 'WhatsApp' },
]

const myTickets = ref([
  { id: '1041', sujet: 'Erreur lors du paiement Orange Money', statut: 'En cours', canal: 'WhatsApp', derniereMaj: 'Aujourd\'hui à 11:42' },
  { id: '1033', sujet: 'Langue arabe mal affichée sur PDF', statut: 'En attente client', canal: 'Email', derniereMaj: 'Hier à 09:15' },
  { id: '1028', sujet: 'Comment exporter mes données comptables ?', statut: 'Résolu', canal: 'Chat', derniereMaj: '20/05/2026' },
  { id: '1019', sujet: 'Ajouter un second utilisateur à mon compte', statut: 'Résolu', canal: 'Email', derniereMaj: '15/05/2026' },
  { id: '1008', sujet: 'Importation des contacts depuis Excel', statut: 'Résolu', canal: 'Email', derniereMaj: '10/05/2026' },
  { id: '1003', sujet: 'Problème d\'impression des factures', statut: 'Fermé', canal: 'Téléphone', derniereMaj: '02/05/2026' },
])

const filteredMyTickets = computed(() => {
  if (ticketFilter.value === 'ouvert') return myTickets.value.filter(t => !['Résolu', 'Fermé'].includes(t.statut))
  if (ticketFilter.value === 'resolu') return myTickets.value.filter(t => ['Résolu', 'Fermé'].includes(t.statut))
  return myTickets.value
})

const kbCategories = ref([
  { id: 1, label: 'Prise en main', icon: 'pi pi-play-circle', articles: 18, color: '#3b82f6' },
  { id: 2, label: 'Paiements & Mobile Money', icon: 'pi pi-credit-card', articles: 22, color: '#10b981' },
  { id: 3, label: 'Facturation & OHADA', icon: 'pi pi-file-pdf', articles: 14, color: '#f59e0b' },
  { id: 4, label: 'Inventaire & Stock', icon: 'pi pi-box', articles: 11, color: '#8b5cf6' },
  { id: 5, label: 'Application mobile', icon: 'pi pi-mobile', articles: 12, color: '#06b6d4' },
  { id: 6, label: 'Compte & Sécurité', icon: 'pi pi-shield', articles: 8, color: '#ef4444' },
])

const statutSeverity = (s: string) => {
  const map: Record<string, string> = { Nouveau: 'info', 'En cours': 'warn', 'En attente client': 'secondary', Résolu: 'success', Fermé: 'secondary' }
  return map[s] ?? 'info'
}

const canalIcon = (canal: string) => {
  const map: Record<string, string> = { Email: 'pi pi-envelope', Chat: 'pi pi-comments', Téléphone: 'pi pi-phone', WhatsApp: 'pi pi-whatsapp' }
  return map[canal] ?? 'pi pi-inbox'
}

const viewTicket = (ticket: any) => {
  window.location.href = `/helpdesk/portal/tickets/${ticket.id}`
}

const replyToTicket = (ticket: any) => {
  window.location.href = `/helpdesk/portal/tickets/${ticket.id}#reply`
}

const openChat = () => { chatOpen.value = true }

const openKbCategory = (_cat: any) => {
  window.location.href = `/helpdesk/knowledge-base`
}

const sendChatMsg = () => { chatMessage.value = '' }

const submitTicket = () => {
  showNewTicketDialog.value = false
  newTicket.value = { sujet: '', description: '', priorite: 'Normale', canal: 'Email' }
}
</script>
