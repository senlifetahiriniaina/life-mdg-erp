<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Gestion des escalades</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Alertes SLA, règles d'escalade automatique et tickets escaladés</p>
      </div>
      <div class="flex gap-2">
        <Button label="Configurer règles SLA" icon="pi pi-cog" severity="secondary" outlined @click="showSlaConfig = true" />
        <Button label="Escalader manuellement" icon="pi pi-arrow-up" @click="showManualEscalade = true" />
      </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <Card class="border-l-4 border-red-500">
        <template #content>
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
              <i class="pi pi-arrow-up text-red-600 text-lg"></i>
            </div>
            <div>
              <p class="text-xs text-gray-500">Escaladés ce mois</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.escalades }}</p>
            </div>
          </div>
        </template>
      </Card>
      <Card class="border-l-4 border-orange-500">
        <template #content>
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center">
              <i class="pi pi-exclamation-triangle text-orange-600 text-lg"></i>
            </div>
            <div>
              <p class="text-xs text-gray-500">Violations SLA</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.slaBreaches }}</p>
            </div>
          </div>
        </template>
      </Card>
      <Card class="border-l-4 border-yellow-500">
        <template #content>
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-yellow-100 flex items-center justify-center">
              <i class="pi pi-percentage text-yellow-600 text-lg"></i>
            </div>
            <div>
              <p class="text-xs text-gray-500">Taux d'escalade</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.tauxEscalade }}%</p>
            </div>
          </div>
        </template>
      </Card>
      <Card class="border-l-4 border-blue-500">
        <template #content>
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
              <i class="pi pi-clock text-blue-600 text-lg"></i>
            </div>
            <div>
              <p class="text-xs text-gray-500">Délai moyen avant escalade</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.delaiMoyen }}</p>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <!-- Active SLA alerts -->
    <Card>
      <template #header>
        <div class="px-5 pt-5 pb-2 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2">
          <i class="pi pi-bell text-red-500"></i>
          <span class="font-semibold text-red-600">Alertes SLA actives</span>
          <span class="bg-red-100 text-red-600 text-xs rounded-full px-2 py-0.5 ml-1">{{ slaAlerts.length }}</span>
        </div>
      </template>
      <template #content>
        <div v-if="slaAlerts.length === 0" class="text-center py-6 text-gray-400 text-sm">
          Aucune alerte SLA active. Excellent !
        </div>
        <div v-else class="space-y-2">
          <div
            v-for="alert in slaAlerts"
            :key="alert.id"
            class="flex items-center justify-between gap-3 p-3 rounded-lg border"
            :class="alert.critical ? 'border-red-300 bg-red-50 dark:bg-red-900/10' : 'border-orange-200 bg-orange-50 dark:bg-orange-900/10'"
          >
            <div class="flex items-center gap-3">
              <i class="pi pi-exclamation-circle" :class="alert.critical ? 'text-red-600' : 'text-orange-500'"></i>
              <div>
                <p class="text-sm font-medium text-gray-800 dark:text-gray-200">
                  <span class="font-mono text-blue-600">#{{ alert.ticketId }}</span>
                  {{ alert.titre }}
                </p>
                <p class="text-xs text-gray-500">{{ alert.client }} • Agent : {{ alert.agent }}</p>
              </div>
            </div>
            <div class="text-right flex-shrink-0">
              <p class="text-sm font-bold" :class="alert.critical ? 'text-red-600' : 'text-orange-500'">
                {{ alert.remaining }}
              </p>
              <p class="text-xs text-gray-400">restant</p>
            </div>
            <Button icon="pi pi-arrow-up" label="Escalader" severity="danger" size="small" outlined />
          </div>
        </div>
      </template>
    </Card>

    <!-- Escalated tickets DataTable -->
    <Card>
      <template #header>
        <div class="px-5 pt-5 pb-2 border-b border-gray-100 dark:border-gray-700">
          <span class="font-semibold text-gray-700 dark:text-gray-200">Tickets escaladés</span>
        </div>
      </template>
      <template #content>
        <DataTable
          :value="escalatedTickets"
          :paginator="true"
          :rows="10"
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
              <span class="font-medium text-gray-800 dark:text-gray-200">{{ data.titre }}</span>
            </template>
          </Column>
          <Column field="client" header="Client" />
          <Column field="agentInitial" header="Agent initial" style="width: 150px" />
          <Column field="agentEscalade" header="Escaladé vers" style="width: 150px">
            <template #body="{ data }">
              <span class="font-medium text-blue-600">{{ data.agentEscalade }}</span>
            </template>
          </Column>
          <Column field="raison" header="Raison" style="width: 180px">
            <template #body="{ data }">
              <span class="text-xs text-orange-600">{{ data.raison }}</span>
            </template>
          </Column>
          <Column field="tempsEcoule" header="Temps écoulé" style="width: 130px">
            <template #body="{ data }">
              <span class="text-sm font-medium text-red-500">{{ data.tempsEcoule }}</span>
            </template>
          </Column>
          <Column field="statut" header="Statut" style="width: 120px">
            <template #body="{ data }">
              <Tag :value="data.statut" :severity="data.statut === 'Résolu' ? 'success' : 'warn'" />
            </template>
          </Column>
          <Column header="Actions" style="width: 80px">
            <template #body="{ data }">
              <Button icon="pi pi-eye" size="small" severity="secondary" text />
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- SLA Rules configuration -->
    <Card>
      <template #header>
        <div class="px-5 pt-5 pb-2 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
          <span class="font-semibold text-gray-700 dark:text-gray-200">Règles SLA & escalade automatique</span>
          <Button label="Modifier" icon="pi pi-pencil" severity="secondary" text size="small" @click="showSlaConfig = true" />
        </div>
      </template>
      <template #content>
        <div class="space-y-4">
          <div
            v-for="rule in slaRules"
            :key="rule.priority"
            class="flex items-center justify-between gap-4 p-4 rounded-lg border border-gray-200 dark:border-gray-700"
          >
            <div class="flex items-center gap-3">
              <Tag :value="rule.priority" :severity="rule.severity" />
              <div>
                <p class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ rule.priority }}</p>
                <p class="text-xs text-gray-500">
                  Réponse : <strong>{{ rule.reponseSLA }}</strong> &bull;
                  Résolution : <strong>{{ rule.resolutionSLA }}</strong>
                </p>
              </div>
            </div>
            <div class="flex items-center gap-4">
              <div class="text-right">
                <p class="text-xs text-gray-500 mb-1">Escalade automatique</p>
                <ToggleSwitch v-model="rule.autoEscalade" />
              </div>
              <div class="text-right min-w-[120px]">
                <p class="text-xs text-gray-500">Escalader vers</p>
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ rule.escaladeTo }}</p>
              </div>
            </div>
          </div>
        </div>
      </template>
    </Card>

    <!-- SLA Config Dialog -->
    <Dialog v-model:visible="showSlaConfig" header="Configuration SLA" :style="{ width: '560px' }" modal>
      <div class="space-y-4">
        <p class="text-sm text-gray-500">Définissez les délais SLA et les règles d'escalade pour chaque niveau de priorité.</p>
        <div v-for="rule in slaRules" :key="rule.priority" class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg space-y-3">
          <div class="flex items-center gap-2">
            <Tag :value="rule.priority" :severity="rule.severity" />
            <span class="font-medium text-gray-700 dark:text-gray-300">{{ rule.priority }}</span>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs text-gray-500 mb-1">Délai de réponse</label>
              <InputText v-model="rule.reponseSLA" class="w-full text-sm" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">Délai de résolution</label>
              <InputText v-model="rule.resolutionSLA" class="w-full text-sm" />
            </div>
          </div>
          <div class="flex items-center gap-3">
            <ToggleSwitch v-model="rule.autoEscalade" />
            <span class="text-sm text-gray-600 dark:text-gray-400">Escalade automatique à l'expiration</span>
          </div>
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" text @click="showSlaConfig = false" />
        <Button label="Enregistrer" icon="pi pi-check" @click="showSlaConfig = false" />
      </template>
    </Dialog>

    <!-- Manual escalation Dialog -->
    <Dialog v-model:visible="showManualEscalade" header="Escalade manuelle" :style="{ width: '480px' }" modal>
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ticket à escalader</label>
          <Select v-model="manualTicket" :options="ticketOptions" option-label="label" option-value="value" class="w-full" placeholder="Sélectionner un ticket..." />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Escalader vers</label>
          <Select v-model="manualAgent" :options="supervisorOptions" option-label="label" option-value="value" class="w-full" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Raison</label>
          <Textarea v-model="manualRaison" rows="3" class="w-full" placeholder="Expliquez pourquoi ce ticket doit être escaladé..." />
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" severity="secondary" text @click="showManualEscalade = false" />
        <Button label="Escalader" icon="pi pi-arrow-up" severity="warn" @click="doManualEscalade" />
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
import ToggleSwitch from 'primevue/toggleswitch'

const page = usePage()
const { isAdmin, isElevated, hasAnyRole } = useRoleAccess()
const canManage = computed(() => isElevated.value || hasAnyRole(['customer-service']))
const canCreate = computed(() => canManage.value)
const canEdit = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)


const auth = computed(() => page.props.auth)

const showSlaConfig = ref(false)
const showManualEscalade = ref(false)
const manualTicket = ref(null)
const manualAgent = ref(null)
const manualRaison = ref('')

const stats = ref({ escalades: 18, slaBreaches: 7, tauxEscalade: '5.4', delaiMoyen: '6h 12min' })

const slaAlerts = ref([
  { id: 1, ticketId: '1042', titre: 'Impossible de se connecter au portail', client: 'Mamadou Diallo', agent: 'Aïssatou Bâ', remaining: '0h 45min', critical: true },
  { id: 2, ticketId: '1036', titre: 'MoMo MTN ne passe pas au checkout', client: 'Aminata Camara', agent: 'Aïssatou Bâ', remaining: '1h 10min', critical: true },
  { id: 3, ticketId: '1039', titre: 'Facture mal formatée (TVA manquante)', client: 'Kouamé Assi', agent: 'Seydou Ouédraogo', remaining: '2h 15min', critical: false },
])

const escalatedTickets = ref([
  { id: '1030', titre: 'API webhook Stripe échoue', client: 'Sidy Diop SA', agentInitial: 'Ibrahim Traoré', agentEscalade: 'Seydou Ouédraogo (Tech)', raison: 'Dépassement SLA Critique', tempsEcoule: '2h 34min', statut: 'En cours' },
  { id: '1022', titre: 'Données corrompues après import masse', client: 'Grand Marché SARL', agentInitial: 'Aïssatou Bâ', agentEscalade: 'Directeur technique', raison: 'Perte de données potentielle', tempsEcoule: '5h 00min', statut: 'En cours' },
  { id: '1018', titre: 'Double facturation Orange Money', client: 'Fatou Koné', agentInitial: 'Seydou Ouédraogo', agentEscalade: 'Responsable financier', raison: 'Impact financier client', tempsEcoule: '1j 2h', statut: 'Résolu' },
  { id: '1010', titre: 'Accès portail impossible pour tout un compte', client: 'Abidjan Commerce', agentInitial: 'Ibrahim Traoré', agentEscalade: 'Seydou Ouédraogo (Tech)', raison: 'Dépassement SLA Haute', tempsEcoule: '3h 15min', statut: 'Résolu' },
  { id: '1005', titre: 'Rapports OHADA incorrects', client: 'Lomé Négoce', agentInitial: 'Aïssatou Bâ', agentEscalade: 'Expert comptable', raison: 'Risque conformité OHADA', tempsEcoule: '4h 45min', statut: 'Résolu' },
])

const slaRules = ref([
  { priority: 'Critique', severity: 'danger', reponseSLA: '1 heure', resolutionSLA: '4 heures', autoEscalade: true, escaladeTo: 'Directeur support' },
  { priority: 'Haute', severity: 'warn', reponseSLA: '4 heures', resolutionSLA: '24 heures', autoEscalade: true, escaladeTo: 'Superviseur N2' },
  { priority: 'Normale', severity: 'info', reponseSLA: '8 heures', resolutionSLA: '72 heures', autoEscalade: false, escaladeTo: 'Agent senior' },
  { priority: 'Basse', severity: 'secondary', reponseSLA: '24 heures', resolutionSLA: '5 jours', autoEscalade: false, escaladeTo: 'Agent senior' },
])

const ticketOptions = [
  { label: '#1042 — Impossible de se connecter au portail', value: '1042' },
  { label: '#1041 — Erreur paiement Orange Money', value: '1041' },
  { label: '#1039 — Facture mal formatée', value: '1039' },
]

const supervisorOptions = [
  { label: 'Seydou Ouédraogo — Superviseur', value: 'Seydou Ouédraogo' },
  { label: 'Aminata Diallo — Responsable technique', value: 'Aminata Diallo' },
  { label: 'Directeur du support', value: 'Directeur support' },
]

const doManualEscalade = () => {
  showManualEscalade.value = false
  manualTicket.value = null
  manualAgent.value = null
  manualRaison.value = ''
}
</script>
