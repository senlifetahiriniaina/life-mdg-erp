<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Tickets de support</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Gestion des demandes clients — file de support</p>
      </div>
      <div class="flex gap-2">
        <Button
          :icon="viewMode === 'kanban' ? 'pi pi-list' : 'pi pi-th-large'"
          :label="viewMode === 'kanban' ? 'Vue liste' : 'Vue kanban'"
          severity="secondary"
          outlined
          @click="viewMode = viewMode === 'kanban' ? 'list' : 'kanban'"
        />
        <Button v-if="canCreate" label="Nouveau ticket" icon="pi pi-plus" @click="showCreateDialog = true" />
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <Card class="border-l-4 border-red-500">
        <template #content>
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
              <i class="pi pi-ticket text-red-600 text-lg"></i>
            </div>
            <div>
              <p class="text-xs text-gray-500">Tickets ouverts</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.ouverts }}</p>
            </div>
          </div>
        </template>
      </Card>
      <Card class="border-l-4 border-yellow-500">
        <template #content>
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-yellow-100 flex items-center justify-center">
              <i class="pi pi-clock text-yellow-600 text-lg"></i>
            </div>
            <div>
              <p class="text-xs text-gray-500">En attente</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.enAttente }}</p>
            </div>
          </div>
        </template>
      </Card>
      <Card class="border-l-4 border-green-500">
        <template #content>
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
              <i class="pi pi-check-circle text-green-600 text-lg"></i>
            </div>
            <div>
              <p class="text-xs text-gray-500">Résolus ce mois</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.resolus }}</p>
            </div>
          </div>
        </template>
      </Card>
      <Card class="border-l-4 border-blue-500">
        <template #content>
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
              <i class="pi pi-chart-line text-blue-600 text-lg"></i>
            </div>
            <div>
              <p class="text-xs text-gray-500">Temps moyen résolution</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.tempsMoyen }}</p>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <!-- Filters -->
    <Card>
      <template #content>
        <div class="flex flex-wrap gap-3 items-center">
          <InputText v-model="filters.search" placeholder="Rechercher un ticket..." class="w-64" />
          <Select
            v-model="filters.priorite"
            :options="prioriteOptions"
            option-label="label"
            option-value="value"
            placeholder="Priorité"
            class="w-40"
            show-clear
          />
          <Select
            v-model="filters.statut"
            :options="statutOptions"
            option-label="label"
            option-value="value"
            placeholder="Statut"
            class="w-44"
            show-clear
          />
          <Select
            v-model="filters.agent"
            :options="agentOptions"
            option-label="label"
            option-value="value"
            placeholder="Agent"
            class="w-44"
            show-clear
          />
          <Button label="Réinitialiser" severity="secondary" text @click="resetFilters" />
        </div>
      </template>
    </Card>

    <!-- KANBAN VIEW -->
    <div v-if="viewMode === 'kanban'" class="overflow-x-auto pb-4">
      <div class="flex gap-4 min-w-max">
        <div
          v-for="col in kanbanColumns"
          :key="col.key"
          class="w-72 flex-shrink-0"
        >
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
              <span class="w-3 h-3 rounded-full" :class="col.color"></span>
              <span class="font-semibold text-sm text-gray-700 dark:text-gray-300">{{ col.label }}</span>
            </div>
            <span class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 rounded-full px-2 py-0.5">
              {{ ticketsByStatus(col.key).length }}
            </span>
          </div>
          <div class="space-y-2">
            <Card
              v-for="ticket in ticketsByStatus(col.key)"
              :key="ticket.id"
              class="cursor-pointer hover:shadow-md transition-shadow"
              @click="goToTicket(ticket)"
            >
              <template #content>
                <div class="space-y-2">
                  <div class="flex items-start justify-between gap-2">
                    <p class="text-sm font-medium text-gray-900 dark:text-white leading-tight">{{ ticket.titre }}</p>
                    <Tag :value="ticket.priorite" :severity="prioriteSeverity(ticket.priorite)" class="text-xs flex-shrink-0" />
                  </div>
                  <p class="text-xs text-gray-500">{{ ticket.client }}</p>
                  <div class="flex items-center justify-between">
                    <span class="text-xs" :class="slaColor(ticket.slaRemaining)">
                      <i class="pi pi-clock mr-1"></i>{{ ticket.slaRemaining }}
                    </span>
                    <span class="text-xs text-gray-500">{{ ticket.agent }}</span>
                  </div>
                  <div class="flex items-center gap-1">
                    <i :class="canalIcon(ticket.canal)" class="text-gray-400 text-xs"></i>
                    <span class="text-xs text-gray-400">{{ ticket.canal }}</span>
                  </div>
                </div>
              </template>
            </Card>
            <div
              v-if="ticketsByStatus(col.key).length === 0"
              class="text-center text-xs text-gray-400 py-6 border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-lg"
            >
              Aucun ticket
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- LIST VIEW -->
    <Card v-else>
      <template #content>
        <DataTable
          :value="filteredTickets"
          :paginator="true"
          :rows="15"
          paginator-template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown"
          :rows-per-page-options="[10, 15, 25]"
          responsive-layout="scroll"
          row-hover
          class="text-sm"
        >
          <Column field="id" header="#" style="width: 80px">
            <template #body="{ data }">
              <span class="font-mono text-xs text-blue-600 font-semibold">#{{ data.id }}</span>
            </template>
          </Column>
          <Column field="titre" header="Sujet">
            <template #body="{ data }">
              <span class="font-medium text-gray-900 dark:text-white cursor-pointer hover:text-blue-600" @click="goToTicket(data)">
                {{ data.titre }}
              </span>
            </template>
          </Column>
          <Column field="client" header="Client" />
          <Column field="priorite" header="Priorité" style="width: 120px">
            <template #body="{ data }">
              <Tag :value="data.priorite" :severity="prioriteSeverity(data.priorite)" />
            </template>
          </Column>
          <Column field="statut" header="Statut" style="width: 160px">
            <template #body="{ data }">
              <Tag :value="data.statut" :severity="statutSeverity(data.statut)" />
            </template>
          </Column>
          <Column field="agent" header="Agent" />
          <Column field="canal" header="Canal" style="width: 120px">
            <template #body="{ data }">
              <div class="flex items-center gap-1">
                <i :class="canalIcon(data.canal)" class="text-gray-500 text-sm"></i>
                <span>{{ data.canal }}</span>
              </div>
            </template>
          </Column>
          <Column field="slaRemaining" header="SLA" style="width: 120px">
            <template #body="{ data }">
              <span class="text-sm font-medium" :class="slaColor(data.slaRemaining)">{{ data.slaRemaining }}</span>
            </template>
          </Column>
          <Column field="createdAt" header="Créé le" style="width: 120px">
            <template #body="{ data }">
              <span class="text-xs text-gray-500">{{ data.createdAt }}</span>
            </template>
          </Column>
          <Column header="Actions" style="width: 100px">
            <template #body="{ data }">
              <Button icon="pi pi-eye" size="small" severity="secondary" text @click="goToTicket(data)" />
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- Create Ticket Dialog -->
    <Dialog v-model:visible="showCreateDialog" header="Nouveau ticket de support" :style="{ width: '560px' }" modal>
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sujet *</label>
          <InputText v-model="newTicket.sujet" class="w-full" placeholder="Résumé du problème" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description *</label>
          <Textarea v-model="newTicket.description" rows="4" class="w-full" placeholder="Décrivez le problème en détail..." />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Priorité</label>
            <Select v-model="newTicket.priorite" :options="prioriteOptions" option-label="label" option-value="value" class="w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Canal</label>
            <Select v-model="newTicket.canal" :options="canalOptions" option-label="label" option-value="value" class="w-full" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Client</label>
            <Select v-model="newTicket.client" :options="clientOptions" option-label="label" option-value="value" class="w-full" placeholder="Sélectionner..." />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Agent assigné</label>
            <Select v-model="newTicket.agent" :options="agentOptions" option-label="label" option-value="value" class="w-full" placeholder="Sélectionner..." />
          </div>
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" text @click="showCreateDialog = false" />
        <Button label="Créer le ticket" icon="pi pi-check" @click="createTicket" />
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
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
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

const viewMode = ref<'kanban' | 'list'>('kanban')
const showCreateDialog = ref(false)

const stats = ref({
  ouverts: 47,
  enAttente: 12,
  resolus: 134,
  tempsMoyen: '4h 22min',
})

const kanbanColumns = [
  { key: 'Nouveau', label: 'Nouveau', color: 'bg-blue-500' },
  { key: 'En cours', label: 'En cours', color: 'bg-yellow-500' },
  { key: 'En attente client', label: 'En attente client', color: 'bg-orange-500' },
  { key: 'Résolu', label: 'Résolu', color: 'bg-green-500' },
  { key: 'Fermé', label: 'Fermé', color: 'bg-gray-400' },
]

const tickets = ref([
  { id: '1042', titre: 'Impossible de se connecter au portail', client: 'Mamadou Diallo — Dakar Textile', priorite: 'Critique', statut: 'Nouveau', agent: 'Aïssatou Bâ', canal: 'Email', slaRemaining: '1h 20min', createdAt: '24/05/2026' },
  { id: '1041', titre: 'Erreur lors du paiement Orange Money', client: 'Fatou Koné — Grand Marché SARL', priorite: 'Haute', statut: 'En cours', agent: 'Ibrahim Traoré', canal: 'WhatsApp', slaRemaining: '3h 05min', createdAt: '24/05/2026' },
  { id: '1040', titre: 'Rapport mensuel vide pour avril', client: 'Awa Ndiaye — Abidjan Commerce', priorite: 'Normale', statut: 'En attente client', agent: 'Aïssatou Bâ', canal: 'Chat', slaRemaining: '6h 40min', createdAt: '23/05/2026' },
  { id: '1039', titre: 'Facture mal formatée (TVA manquante)', client: 'Kouamé Assi — Bouaké Pro', priorite: 'Haute', statut: 'En cours', agent: 'Seydou Ouédraogo', canal: 'Téléphone', slaRemaining: '2h 15min', createdAt: '23/05/2026' },
  { id: '1038', titre: 'Import Excel échoue sur > 500 lignes', client: 'Mireille Tonga — Lomé Négoce', priorite: 'Normale', statut: 'Nouveau', agent: 'Ibrahim Traoré', canal: 'Email', slaRemaining: '8h 00min', createdAt: '23/05/2026' },
  { id: '1037', titre: 'Synchronisation mobile hors ligne', client: 'Oumar Sanogo — Bamako Tech', priorite: 'Basse', statut: 'Résolu', agent: 'Seydou Ouédraogo', canal: 'Chat', slaRemaining: '—', createdAt: '22/05/2026' },
  { id: '1036', titre: 'MoMo MTN ne passe pas au checkout', client: 'Aminata Camara — Conakry Import', priorite: 'Critique', statut: 'En cours', agent: 'Aïssatou Bâ', canal: 'WhatsApp', slaRemaining: '0h 45min', createdAt: '22/05/2026' },
  { id: '1035', titre: 'Accès refusé aux paramètres RH', client: 'Koffi Mensah — Accra Business', priorite: 'Normale', statut: 'Fermé', agent: 'Ibrahim Traoré', canal: 'Email', slaRemaining: '—', createdAt: '21/05/2026' },
  { id: '1034', titre: 'Doublon de commande après panne réseau', client: 'Sali Ba — Ziguinchor Agro', priorite: 'Haute', statut: 'Résolu', agent: 'Seydou Ouédraogo', canal: 'Téléphone', slaRemaining: '—', createdAt: '21/05/2026' },
  { id: '1033', titre: 'Langue arabe mal affichée sur PDF', client: 'Rachid Khaldi — Casablanca Trade', priorite: 'Basse', statut: 'En attente client', agent: 'Aïssatou Bâ', canal: 'Email', slaRemaining: '12h 00min', createdAt: '20/05/2026' },
])

const filters = ref({ search: '', priorite: null, statut: null, agent: null })

const prioriteOptions = [
  { label: 'Critique', value: 'Critique' },
  { label: 'Haute', value: 'Haute' },
  { label: 'Normale', value: 'Normale' },
  { label: 'Basse', value: 'Basse' },
]

const statutOptions = [
  { label: 'Nouveau', value: 'Nouveau' },
  { label: 'En cours', value: 'En cours' },
  { label: 'En attente client', value: 'En attente client' },
  { label: 'Résolu', value: 'Résolu' },
  { label: 'Fermé', value: 'Fermé' },
]

const agentOptions = [
  { label: 'Aïssatou Bâ', value: 'Aïssatou Bâ' },
  { label: 'Ibrahim Traoré', value: 'Ibrahim Traoré' },
  { label: 'Seydou Ouédraogo', value: 'Seydou Ouédraogo' },
]

const clientOptions = [
  { label: 'Mamadou Diallo — Dakar Textile', value: 'Mamadou Diallo — Dakar Textile' },
  { label: 'Fatou Koné — Grand Marché SARL', value: 'Fatou Koné — Grand Marché SARL' },
  { label: 'Awa Ndiaye — Abidjan Commerce', value: 'Awa Ndiaye — Abidjan Commerce' },
]

const canalOptions = [
  { label: 'Email', value: 'Email' },
  { label: 'Chat', value: 'Chat' },
  { label: 'Téléphone', value: 'Téléphone' },
  { label: 'WhatsApp', value: 'WhatsApp' },
]

const newTicket = ref({ sujet: '', description: '', priorite: 'Normale', canal: 'Email', client: null, agent: null })

const filteredTickets = computed(() => {
  return tickets.value.filter(t => {
    const matchSearch = !filters.value.search || t.titre.toLowerCase().includes(filters.value.search.toLowerCase()) || t.client.toLowerCase().includes(filters.value.search.toLowerCase())
    const matchPriorite = !filters.value.priorite || t.priorite === filters.value.priorite
    const matchStatut = !filters.value.statut || t.statut === filters.value.statut
    const matchAgent = !filters.value.agent || t.agent === filters.value.agent
    return matchSearch && matchPriorite && matchStatut && matchAgent
  })
})

const ticketsByStatus = (status: string) => filteredTickets.value.filter(t => t.statut === status)

const prioriteSeverity = (p: string) => {
  const map: Record<string, string> = { Critique: 'danger', Haute: 'warn', Normale: 'info', Basse: 'secondary' }
  return map[p] ?? 'info'
}

const statutSeverity = (s: string) => {
  const map: Record<string, string> = { Nouveau: 'info', 'En cours': 'warn', 'En attente client': 'secondary', Résolu: 'success', Fermé: 'secondary' }
  return map[s] ?? 'info'
}

const slaColor = (sla: string) => {
  if (sla === '—') return 'text-gray-400'
  const h = parseInt(sla)
  if (h === 0) return 'text-red-600 font-bold'
  if (h < 2) return 'text-red-500'
  if (h < 4) return 'text-orange-500'
  return 'text-green-600'
}

const canalIcon = (canal: string) => {
  const map: Record<string, string> = { Email: 'pi pi-envelope', Chat: 'pi pi-comments', Téléphone: 'pi pi-phone', WhatsApp: 'pi pi-whatsapp' }
  return map[canal] ?? 'pi pi-inbox'
}

const goToTicket = (ticket: any) => {
  window.location.href = `/helpdesk/tickets/${ticket.id}`
}

const resetFilters = () => {
  filters.value = { search: '', priorite: null, statut: null, agent: null }
}

const createTicket = () => {
  showCreateDialog.value = false
  newTicket.value = { sujet: '', description: '', priorite: 'Normale', canal: 'Email', client: null, agent: null }
}
</script>
