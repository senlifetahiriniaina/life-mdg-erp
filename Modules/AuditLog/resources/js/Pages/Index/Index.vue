<template>
  <div class="space-y-6">
    <!-- En-tête -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Journal d'audit</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          Traçabilité complète des actions — conformité RGPD &amp; sécurité
        </p>
      </div>
      <div class="flex gap-2">
        <Button
          icon="pi pi-download"
          label="Exporter CSV"
          severity="secondary"
          outlined
          @click="exportLogs('csv')"
        />
        <Button
          icon="pi pi-file-pdf"
          label="Exporter PDF"
          severity="secondary"
          outlined
          @click="exportLogs('pdf')"
        />
      </div>
    </div>

    <!-- Statistiques -->
    <AIAssistantPanel v-if="guidance" :guidance="guidance" class="mb-4" />

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <Card v-for="stat in stats" :key="stat.label">
        <template #content>
          <div class="flex items-center gap-3">
            <div :class="['w-10 h-10 rounded-full flex items-center justify-center', stat.bg]">
              <i :class="['pi', stat.icon, stat.color, 'text-lg']"></i>
            </div>
            <div>
              <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stat.value }}</div>
              <div class="text-xs text-gray-500 dark:text-gray-400">{{ stat.label }}</div>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <!-- Filtres -->
    <Card>
      <template #content>
        <div class="flex flex-wrap gap-3 items-end">
          <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 dark:text-gray-400">Date début</label>
            <InputText v-model="filters.dateFrom" type="date" class="w-40" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 dark:text-gray-400">Date fin</label>
            <InputText v-model="filters.dateTo" type="date" class="w-40" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 dark:text-gray-400">Utilisateur</label>
            <Select
              v-model="filters.user"
              :options="userOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Tous"
              class="w-44"
            />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 dark:text-gray-400">Module</label>
            <Select
              v-model="filters.module"
              :options="moduleOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Tous"
              class="w-44"
            />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 dark:text-gray-400">Type d'action</label>
            <Select
              v-model="filters.action"
              :options="actionOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Toutes"
              class="w-44"
            />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 dark:text-gray-400">Criticité</label>
            <Select
              v-model="filters.criticality"
              :options="criticalityOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Toutes"
              class="w-40"
            />
          </div>
          <Button label="Réinitialiser" severity="secondary" text size="small" @click="resetFilters" />
        </div>
      </template>
    </Card>

    <!-- DataTable journal d'audit -->
    <Card>
      <template #header>
        <div class="px-4 pt-4 flex items-center justify-between">
          <h2 class="text-base font-semibold text-gray-900 dark:text-white">Événements d'audit</h2>
          <span class="text-sm text-gray-500">{{ filteredLogs.length }} entrées</span>
        </div>
      </template>
      <template #content>
        <DataTable
          :value="filteredLogs"
          paginator
          :rows="15"
          dataKey="id"
          class="p-datatable-sm"
          rowHover
          @row-click="(e: any) => openEventDrawer(e.data)"
          :rowClass="(data: AuditEntry) => data.criticality === 'Critique' ? 'bg-red-50 dark:bg-red-900/10' : ''"
        >
          <Column field="timestamp" header="Horodatage" sortable style="min-width: 160px">
            <template #body="{ data }">
              <span class="text-xs font-mono text-gray-600 dark:text-gray-400">{{ data.timestamp }}</span>
            </template>
          </Column>
          <Column field="user" header="Utilisateur" sortable>
            <template #body="{ data }">
              <div class="flex items-center gap-2">
                <div class="w-6 h-6 rounded-full bg-blue-500 flex items-center justify-center text-white text-xs font-medium">
                  {{ data.user.charAt(0) }}
                </div>
                <span class="text-sm">{{ data.user }}</span>
              </div>
            </template>
          </Column>
          <Column field="ip" header="IP" style="min-width: 120px">
            <template #body="{ data }">
              <span class="text-xs font-mono text-gray-500">{{ data.ip }}</span>
            </template>
          </Column>
          <Column field="module" header="Module" sortable />
          <Column field="action" header="Action" sortable>
            <template #body="{ data }">
              <span :class="['text-xs font-semibold font-mono px-1.5 py-0.5 rounded', actionColor(data.action)]">
                {{ data.action }}
              </span>
            </template>
          </Column>
          <Column field="resource" header="Ressource affectée" sortable>
            <template #body="{ data }">
              <span class="text-sm text-gray-700 dark:text-gray-300">{{ data.resource }}</span>
            </template>
          </Column>
          <Column field="criticality" header="Criticité" sortable>
            <template #body="{ data }">
              <Tag
                :value="data.criticality"
                :severity="criticalitySeverity(data.criticality)"
              />
            </template>
          </Column>
          <Column style="width: 60px">
            <template #body="{ data }">
              <Button icon="pi pi-eye" rounded text size="small" @click.stop="openEventDrawer(data)" />
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- Section conformité RGPD -->
    <Card class="border-l-4 border-blue-500">
      <template #header>
        <div class="px-4 pt-4 flex items-center gap-2">
          <i class="pi pi-shield text-blue-500 text-lg"></i>
          <h2 class="text-base font-semibold text-gray-900 dark:text-white">Conformité RGPD &amp; Protection des données</h2>
        </div>
      </template>
      <template #content>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div class="space-y-2">
            <div class="flex items-center gap-2">
              <i class="pi pi-calendar text-blue-500"></i>
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Durée de conservation</span>
            </div>
            <div class="text-2xl font-bold text-blue-600">5 ans</div>
            <p class="text-xs text-gray-500">Conformément à l'article 30 du RGPD et à la PDPL sénégalaise. Suppression automatique après échéance.</p>
            <div class="flex items-center gap-1">
              <i class="pi pi-check-circle text-green-500 text-xs"></i>
              <span class="text-xs text-green-600">Politique active</span>
            </div>
          </div>
          <div class="space-y-2">
            <div class="flex items-center gap-2">
              <i class="pi pi-users text-purple-500"></i>
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Demandes d'accès (DSAR)</span>
            </div>
            <div class="space-y-2">
              <div
                v-for="dsar in dsarRequests"
                :key="dsar.id"
                class="flex items-center justify-between text-xs p-2 rounded-lg bg-gray-50 dark:bg-gray-800"
              >
                <div>
                  <span class="font-medium text-gray-800 dark:text-gray-200">{{ dsar.subject }}</span>
                  <div class="text-gray-500">{{ dsar.type }} · {{ dsar.date }}</div>
                </div>
                <Tag :value="dsar.status" :severity="dsarSeverity(dsar.status)" class="text-xs" />
              </div>
            </div>
          </div>
          <div class="space-y-2">
            <div class="flex items-center gap-2">
              <i class="pi pi-chart-bar text-green-500"></i>
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Statistiques de rétention</span>
            </div>
            <div class="space-y-3">
              <div>
                <div class="flex justify-between text-xs mb-1">
                  <span class="text-gray-500">Logs conservés</span>
                  <span class="font-medium text-gray-800 dark:text-gray-200">247 830 entrées</span>
                </div>
                <ProgressBar :value="62" :showValue="false" style="height: 6px" />
                <div class="text-xs text-gray-400 mt-0.5">62% de la capacité</div>
              </div>
              <div>
                <div class="flex justify-between text-xs mb-1">
                  <span class="text-gray-500">Prochaine purge</span>
                  <span class="font-medium text-gray-800 dark:text-gray-200">Jan 2029</span>
                </div>
              </div>
              <Button label="Voir le registre RGPD complet" severity="secondary" text size="small" icon="pi pi-external-link" />
            </div>
          </div>
        </div>
      </template>
    </Card>

    <!-- Drawer détail événement -->
    <Drawer
      v-model:visible="showEventDrawer"
      position="right"
      :style="{ width: '520px' }"
      header="Détail de l'événement"
    >
      <div v-if="selectedEvent" class="space-y-5">
        <!-- Infos générales -->
        <div class="flex items-center justify-between">
          <Tag :value="selectedEvent.criticality" :severity="criticalitySeverity(selectedEvent.criticality)" class="text-sm" />
          <span class="text-xs font-mono text-gray-500">{{ selectedEvent.timestamp }}</span>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3">
            <div class="text-xs text-gray-500 mb-1">Utilisateur</div>
            <div class="font-medium text-gray-900 dark:text-white text-sm">{{ selectedEvent.user }}</div>
            <div class="text-xs text-gray-400">{{ selectedEvent.userRole }}</div>
          </div>
          <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3">
            <div class="text-xs text-gray-500 mb-1">Adresse IP</div>
            <div class="font-mono text-sm text-gray-900 dark:text-white">{{ selectedEvent.ip }}</div>
            <div class="text-xs text-gray-400">{{ selectedEvent.location }}</div>
          </div>
          <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3">
            <div class="text-xs text-gray-500 mb-1">Module</div>
            <div class="font-medium text-gray-900 dark:text-white text-sm">{{ selectedEvent.module }}</div>
          </div>
          <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3">
            <div class="text-xs text-gray-500 mb-1">Action</div>
            <span :class="['text-xs font-semibold font-mono px-2 py-1 rounded', actionColor(selectedEvent.action)]">
              {{ selectedEvent.action }}
            </span>
          </div>
        </div>

        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3">
          <div class="text-xs text-gray-500 mb-1">Ressource affectée</div>
          <div class="font-medium text-gray-900 dark:text-white text-sm">{{ selectedEvent.resource }}</div>
          <div class="text-xs text-gray-400 mt-1">ID : {{ selectedEvent.resourceId }}</div>
        </div>

        <!-- Diff ancien/nouveau -->
        <div v-if="selectedEvent.oldValue || selectedEvent.newValue">
          <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Modifications</h4>
          <div class="grid grid-cols-2 gap-2">
            <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-3">
              <div class="text-xs font-medium text-red-600 dark:text-red-400 mb-2">Ancienne valeur</div>
              <pre class="text-xs text-red-700 dark:text-red-300 whitespace-pre-wrap break-all">{{ selectedEvent.oldValue ?? '—' }}</pre>
            </div>
            <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-3">
              <div class="text-xs font-medium text-green-600 dark:text-green-400 mb-2">Nouvelle valeur</div>
              <pre class="text-xs text-green-700 dark:text-green-300 whitespace-pre-wrap break-all">{{ selectedEvent.newValue ?? '—' }}</pre>
            </div>
          </div>
        </div>

        <!-- User agent & géolocalisation -->
        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 space-y-1.5">
          <div class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-2">Informations techniques</div>
          <div class="flex justify-between text-xs">
            <span class="text-gray-500">User-Agent</span>
            <span class="text-gray-700 dark:text-gray-300 truncate max-w-[250px]" :title="selectedEvent.userAgent">{{ selectedEvent.userAgent }}</span>
          </div>
          <div class="flex justify-between text-xs">
            <span class="text-gray-500">Géolocalisation</span>
            <span class="text-gray-700 dark:text-gray-300">{{ selectedEvent.location }}</span>
          </div>
          <div class="flex justify-between text-xs">
            <span class="text-gray-500">Session ID</span>
            <span class="font-mono text-gray-500">{{ selectedEvent.sessionId }}</span>
          </div>
        </div>
      </div>
    </Drawer>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import { usePage } from '@inertiajs/vue3'
import Card from 'primevue/card'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Drawer from 'primevue/drawer'
import Select from 'primevue/select'
import InputText from 'primevue/inputtext'
import ProgressBar from 'primevue/progressbar'

const page = usePage()
const roles = computed(() => page.props.auth?.user?.roles?.map(r => r.name) || [])
const isAdmin = computed(() => roles.value.some(r => ['admin', 'super-admin'].includes(r)))
const canManage = computed(() => roles.value.some(r => ['compliance-officer', 'it-admin', 'admin', 'super-admin'].includes(r)))
const canCreate = computed(() => canManage.value)
const canEdit = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)


usePage()

const showEventDrawer = ref(false)
const selectedEvent = ref<AuditEntry | null>(null)

interface AuditEntry {
  id: number
  timestamp: string
  user: string
  userRole: string
  ip: string
  location: string
  module: string
  action: string
  resource: string
  resourceId: string
  criticality: string
  oldValue: string | null
  newValue: string | null
  userAgent: string
  sessionId: string
}

const filters = ref({
  dateFrom: '',
  dateTo: '',
  user: null as string | null,
  module: null as string | null,
  action: null as string | null,
  criticality: null as string | null,
})

const userOptions = [
  { label: 'Tous les utilisateurs', value: null },
  { label: 'Amara Diallo', value: 'Amara Diallo' },
  { label: 'Fatou Ndiaye', value: 'Fatou Ndiaye' },
  { label: 'Kofi Mensah', value: 'Kofi Mensah' },
  { label: 'Admin Système', value: 'Admin Système' },
]

const moduleOptions = [
  { label: 'Tous les modules', value: null },
  { label: 'Accounting', value: 'Accounting' },
  { label: 'CRM', value: 'CRM' },
  { label: 'HR', value: 'HR' },
  { label: 'Inventory', value: 'Inventory' },
  { label: 'Security', value: 'Security' },
  { label: 'Settings', value: 'Settings' },
]

const actionOptions = [
  { label: 'Toutes les actions', value: null },
  { label: 'CREATE', value: 'CREATE' },
  { label: 'UPDATE', value: 'UPDATE' },
  { label: 'DELETE', value: 'DELETE' },
  { label: 'LOGIN', value: 'LOGIN' },
  { label: 'LOGOUT', value: 'LOGOUT' },
  { label: 'EXPORT', value: 'EXPORT' },
  { label: 'PERMISSION_CHANGE', value: 'PERMISSION_CHANGE' },
]

const criticalityOptions = [
  { label: 'Toutes', value: null },
  { label: 'Info', value: 'Info' },
  { label: 'Attention', value: 'Attention' },
  { label: 'Critique', value: 'Critique' },
]

const auditLogs = ref<AuditEntry[]>([
  { id: 1, timestamp: '2026-05-24 08:12:33', user: 'Amara Diallo', userRole: 'Chef de projet', ip: '41.82.14.5', location: 'Dakar, Sénégal', module: 'Accounting', action: 'CREATE', resource: 'Facture #FAC-2026-0542', resourceId: 'FAC-2026-0542', criticality: 'Info', oldValue: null, newValue: '{"montant": 2500000, "client": "SATG", "statut": "brouillon"}', userAgent: 'Mozilla/5.0 Chrome/124 Linux', sessionId: 'sess_9f2k1a' },
  { id: 2, timestamp: '2026-05-24 08:45:11', user: 'Fatou Ndiaye', userRole: 'Développeur', ip: '196.203.22.91', location: 'Abidjan, Côte d\'Ivoire', module: 'HR', action: 'UPDATE', resource: 'Employé Kofi Mensah', resourceId: 'EMP-0034', criticality: 'Attention', oldValue: '{"salaire": 850000}', newValue: '{"salaire": 920000}', userAgent: 'Mozilla/5.0 Safari/17 macOS', sessionId: 'sess_7h3m2b' },
  { id: 3, timestamp: '2026-05-24 09:01:55', user: 'Admin Système', userRole: 'Administrateur', ip: '192.168.1.1', location: 'Serveur local', module: 'Security', action: 'PERMISSION_CHANGE', resource: 'Rôle: comptable', resourceId: 'ROLE-comptable', criticality: 'Critique', oldValue: '{"permissions": ["read_invoice"]}', newValue: '{"permissions": ["read_invoice", "delete_invoice"]}', userAgent: 'CLI/artisan v12', sessionId: 'sess_sys01' },
  { id: 4, timestamp: '2026-05-24 09:18:42', user: 'Kofi Mensah', userRole: 'Architecte', ip: '154.68.102.3', location: 'Accra, Ghana', module: 'CRM', action: 'DELETE', resource: 'Contact Dupliquer #CRM-0881', resourceId: 'CRM-0881', criticality: 'Attention', oldValue: '{"nom": "Awa Balde (doublon)", "email": "awa@test.com"}', newValue: null, userAgent: 'Mozilla/5.0 Firefox/125 Windows', sessionId: 'sess_4k9n5c' },
  { id: 5, timestamp: '2026-05-24 09:32:17', user: 'Amara Diallo', userRole: 'Chef de projet', ip: '41.82.14.5', location: 'Dakar, Sénégal', module: 'Accounting', action: 'EXPORT', resource: 'Rapport SYSCOHADA Q1 2026', resourceId: 'RPT-SYSC-Q1-2026', criticality: 'Attention', oldValue: null, newValue: null, userAgent: 'Mozilla/5.0 Chrome/124 Linux', sessionId: 'sess_9f2k1a' },
  { id: 6, timestamp: '2026-05-24 10:04:08', user: 'Fatou Ndiaye', userRole: 'Développeur', ip: '196.203.22.91', location: 'Abidjan, Côte d\'Ivoire', module: 'Inventory', action: 'CREATE', resource: 'Produit Tissus Bazin 10m', resourceId: 'PRD-4421', criticality: 'Info', oldValue: null, newValue: '{"sku": "TIS-BAZIN-10M", "stock": 500, "prix": 18500}', userAgent: 'Mozilla/5.0 Safari/17 macOS', sessionId: 'sess_7h3m2b' },
  { id: 7, timestamp: '2026-05-24 10:22:51', user: 'Admin Système', userRole: 'Administrateur', ip: '192.168.1.1', location: 'Serveur local', module: 'Settings', action: 'UPDATE', resource: 'Configuration SMTP', resourceId: 'CFG-smtp', criticality: 'Critique', oldValue: '{"host": "smtp.old.com"}', newValue: '{"host": "smtp.sendgrid.net"}', userAgent: 'CLI/artisan v12', sessionId: 'sess_sys01' },
  { id: 8, timestamp: '2026-05-24 11:15:00', user: 'Kofi Mensah', userRole: 'Architecte', ip: '154.68.102.3', location: 'Accra, Ghana', module: 'Security', action: 'LOGIN', resource: 'Session utilisateur', resourceId: 'AUTH-kofi', criticality: 'Info', oldValue: null, newValue: null, userAgent: 'Mozilla/5.0 Firefox/125 Windows', sessionId: 'sess_8p1q6d' },
  { id: 9, timestamp: '2026-05-24 12:40:33', user: 'Fatou Ndiaye', userRole: 'Développeur', ip: '196.203.22.91', location: 'Abidjan, Côte d\'Ivoire', module: 'Security', action: 'LOGOUT', resource: 'Session utilisateur', resourceId: 'AUTH-fatou', criticality: 'Info', oldValue: null, newValue: null, userAgent: 'Mozilla/5.0 Safari/17 macOS', sessionId: 'sess_7h3m2b' },
  { id: 10, timestamp: '2026-05-24 13:55:14', user: 'Amara Diallo', userRole: 'Chef de projet', ip: '41.82.14.5', location: 'Dakar, Sénégal', module: 'HR', action: 'UPDATE', resource: 'Fiche paie Mai 2026 — Bintou Keïta', resourceId: 'PAY-BINT-202605', criticality: 'Critique', oldValue: '{"net_a_payer": 780000}', newValue: '{"net_a_payer": 820000}', userAgent: 'Mozilla/5.0 Chrome/124 Linux', sessionId: 'sess_9f2k1a' },
])

const dsarRequests = [
  { id: 1, subject: 'Mariama Balde', type: 'Accès données', date: '22 Mai 2026', status: 'En cours' },
  { id: 2, subject: 'Cheikh Diop', type: 'Suppression', date: '18 Mai 2026', status: 'Traité' },
  { id: 3, subject: 'Awa Sow', type: 'Portabilité', date: '15 Mai 2026', status: 'Traité' },
]

const stats = computed(() => {
  const today = '2026-05-24'
  const todayEvents = auditLogs.value.filter(l => l.timestamp.startsWith(today)).length
  const activeUsers = [...new Set(auditLogs.value.filter(l => l.timestamp.startsWith(today)).map(l => l.user))].length
  const criticalActions = auditLogs.value.filter(l => l.criticality === 'Critique').length
  const securityAlerts = auditLogs.value.filter(l => l.criticality === 'Critique' && ['PERMISSION_CHANGE', 'DELETE'].includes(l.action)).length
  return [
    { label: 'Événements aujourd\'hui', value: todayEvents, icon: 'pi-list', color: 'text-blue-600', bg: 'bg-blue-50 dark:bg-blue-900/20' },
    { label: 'Utilisateurs actifs', value: activeUsers, icon: 'pi-users', color: 'text-green-600', bg: 'bg-green-50 dark:bg-green-900/20' },
    { label: 'Actions critiques', value: criticalActions, icon: 'pi-exclamation-triangle', color: 'text-orange-500', bg: 'bg-orange-50 dark:bg-orange-900/20' },
    { label: 'Alertes sécurité', value: securityAlerts, icon: 'pi-shield', color: 'text-red-500', bg: 'bg-red-50 dark:bg-red-900/20' },
  ]
})

const filteredLogs = computed(() => {
  return auditLogs.value.filter(l => {
    if (filters.value.dateFrom && l.timestamp < filters.value.dateFrom) return false
    if (filters.value.dateTo && l.timestamp > filters.value.dateTo + ' 23:59:59') return false
    if (filters.value.user && l.user !== filters.value.user) return false
    if (filters.value.module && l.module !== filters.value.module) return false
    if (filters.value.action && l.action !== filters.value.action) return false
    if (filters.value.criticality && l.criticality !== filters.value.criticality) return false
    return true
  })
})

function criticalitySeverity(criticality: string): string {
  const map: Record<string, string> = {
    'Info': 'secondary',
    'Attention': 'warn',
    'Critique': 'danger',
  }
  return map[criticality] ?? 'secondary'
}

function actionColor(action: string): string {
  const map: Record<string, string> = {
    CREATE: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    UPDATE: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    DELETE: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    LOGIN: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    LOGOUT: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    EXPORT: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
    PERMISSION_CHANGE: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
  }
  return map[action] ?? 'bg-gray-100 text-gray-600'
}

function dsarSeverity(status: string): string {
  const map: Record<string, string> = { 'Traité': 'success', 'En cours': 'warn', 'Rejeté': 'danger' }
  return map[status] ?? 'secondary'
}

function openEventDrawer(event: AuditEntry) {
  selectedEvent.value = event
  showEventDrawer.value = true
}

function resetFilters() {
  filters.value = { dateFrom: '', dateTo: '', user: null, module: null, action: null, criticality: null }
}

function exportLogs(format: 'csv' | 'pdf') {
  alert(`Export ${format.toUpperCase()} de ${filteredLogs.value.length} entrées avec les filtres actifs — fonctionnalité en cours d'intégration.`)
}
const { guidance } = useAiAssistant('AuditLog', 'view_audit_log')
</script>
