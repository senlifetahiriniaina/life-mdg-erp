<template>
  <AppLayout>
    <Head title="Intégrations — WideHalo ERP" />

    <div class="space-y-6">
      <!-- Page header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">
            Intégrations
          </h1>
          <p class="text-surface-500 text-sm mt-1">
            Connectez WideHalo à vos outils et services externes
          </p>
        </div>
        <Button
          icon="pi pi-sparkles"
          outlined
          @click="showAiPanel = !showAiPanel"
          title="Assistant IA"
        />
        <Button
          v-if="isAdmin"
          icon="pi pi-plus"
          label="Ajouter une connexion"
          @click="addConnector"
        />
      </div>

      <!-- Connected / Available -->
      <TabView v-model:activeIndex="activeTab">
        <!-- Connected -->
        <TabPanel header="Connecteurs actifs">
          <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 pt-4">
            <Skeleton v-for="i in 3" :key="i" height="8rem" />
          </div>
          <div v-else-if="activeConnectors.length === 0" class="text-center py-16 text-surface-400">
            <i class="pi pi-link text-5xl mb-4 block" />
            <p class="font-medium">Aucune intégration active</p>
            <p class="text-sm mt-1">Explorez le catalogue pour connecter vos outils.</p>
          </div>
          <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 pt-4">
            <div
              v-for="conn in activeConnectors"
              :key="conn.id"
              class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-4 space-y-3"
            >
              <div class="flex items-start justify-between">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-lg bg-surface-100 dark:bg-surface-700 flex items-center justify-center">
                    <i :class="['text-xl', conn.icon ?? 'pi pi-plug']" />
                  </div>
                  <div>
                    <p class="font-semibold text-surface-900 dark:text-surface-50 text-sm">{{ conn.name }}</p>
                    <p class="text-xs text-surface-400">{{ conn.category }}</p>
                  </div>
                </div>
                <Tag
                  :value="conn.status === 'connected' ? 'Connecté' : 'Erreur'"
                  :severity="conn.status === 'connected' ? 'success' : 'danger'"
                  class="text-xs"
                />
              </div>
              <p class="text-xs text-surface-400">Dernière sync : {{ conn.last_sync ? formatDate(conn.last_sync) : 'Jamais' }}</p>
              <div class="flex gap-2">
                <Button label="Configurer" outlined size="small" class="flex-1" @click="configureConnector(conn)" />
                <Button icon="pi pi-trash" outlined severity="danger" size="small" @click="disconnectConnector(conn)" />
              </div>
            </div>
          </div>
        </TabPanel>

        <!-- Catalogue -->
        <TabPanel header="Catalogue">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 pt-4">
            <div
              v-for="template in connectorTemplates"
              :key="template.id"
              class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-4 space-y-3"
            >
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center" :class="template.iconBg">
                  <i :class="['text-xl', template.icon, template.iconColor]" />
                </div>
                <div>
                  <p class="font-semibold text-surface-900 dark:text-surface-50 text-sm">{{ template.name }}</p>
                  <p class="text-xs text-surface-400">{{ template.category }}</p>
                </div>
              </div>
              <p class="text-xs text-surface-500">{{ template.description }}</p>
              <Button
                :label="isConnected(template.id) ? 'Déjà connecté' : 'Connecter'"
                :severity="isConnected(template.id) ? 'secondary' : 'primary'"
                :disabled="isConnected(template.id)"
                outlined
                size="small"
                class="w-full"
                @click="connectTemplate(template)"
              />
            </div>
          </div>
        </TabPanel>

        <!-- Webhooks -->
        <TabPanel header="Webhooks">
          <div class="pt-4 space-y-4">
            <div class="flex justify-end">
              <Button v-if="canCreate" icon="pi pi-plus" label="Ajouter un webhook" outlined size="small" @click="addWebhook" />
            </div>
            <DataTable :value="webhooks" :loading="loading" class="p-datatable-sm" striped-rows>
              <Column field="url" header="URL">
                <template #body="{ data }">
                  <span class="font-mono text-xs text-surface-700 dark:text-surface-300">{{ data.url }}</span>
                </template>
              </Column>
              <Column field="events" header="Événements">
                <template #body="{ data }">
                  <div class="flex flex-wrap gap-1">
                    <Tag v-for="ev in data.events" :key="ev" :value="ev" severity="secondary" class="text-xs" />
                  </div>
                </template>
              </Column>
              <Column field="active" header="Actif" style="width: 80px">
                <template #body="{ data }">
                  <ToggleSwitch v-model="data.active" @change="toggleWebhook(data)" />
                </template>
              </Column>
              <Column header="Actions" style="width: 80px">
                <template #body="{ data }">
                  <Button icon="pi pi-trash" outlined severity="danger" size="small" @click="deleteWebhook(data)" />
                </template>
              </Column>
              <template #empty>
                <div class="text-center py-10 text-surface-400">Aucun webhook configuré.</div>
              </template>
            </DataTable>
          </div>
        </TabPanel>
      </TabView>
      <AIAssistantPanel v-if="showAiPanel" :guidance="guidance" @close="showAiPanel = false" />
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, onMounted, computed} from 'vue'
import { Head, router, usePage} from '@inertiajs/vue3'
import Button from 'primevue/button'
import TabView from 'primevue/tabview'
import TabPanel from 'primevue/tabpanel'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Skeleton from 'primevue/skeleton'
import ToggleSwitch from 'primevue/toggleswitch'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import { useRoleAccess } from '@/composables/useRoleAccess'

const page = usePage()
const roles = computed(() => page.props.auth?.user?.roles?.map(r => r.name) || [])
const canManage = computed(() => roles.value.some(r => ['it-admin', 'admin', 'super-admin'].includes(r)))
const canCreate = computed(() => canManage.value)
const canEdit = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)


const { guidance } = useAiAssistant('Integration', 'view_dashboard')
const showAiPanel = ref(false)
const { isAdmin } = useRoleAccess()

interface Connector {
  id: number
  template_id: string
  name: string
  icon: string | null
  category: string
  status: 'connected' | 'error'
  last_sync: string | null
}

interface ConnectorTemplate {
  id: string
  name: string
  category: string
  description: string
  icon: string
  iconBg: string
  iconColor: string
}

interface Webhook {
  id: number
  url: string
  events: string[]
  active: boolean
}

const loading = ref(false)
const activeTab = ref(0)
const activeConnectors = ref<Connector[]>([])
const webhooks = ref<Webhook[]>([])

const connectorTemplates: ConnectorTemplate[] = [
  { id: 'orange_money', name: 'Orange Money', category: 'Mobile Money', description: 'Paiements mobiles UEMOA via Orange Money', icon: 'pi pi-mobile', iconBg: 'bg-orange-100', iconColor: 'text-orange-600' },
  { id: 'wave', name: 'Wave', category: 'Mobile Money', description: 'Paiements et transferts Wave Sénégal/CI', icon: 'pi pi-send', iconBg: 'bg-blue-100', iconColor: 'text-blue-600' },
  { id: 'mtn_momo', name: 'MTN MoMo', category: 'Mobile Money', description: 'Paiements MTN Mobile Money', icon: 'pi pi-mobile', iconBg: 'bg-yellow-100', iconColor: 'text-yellow-600' },
  { id: 'stripe', name: 'Stripe', category: 'Paiement carte', description: 'Paiements par carte bancaire internationale', icon: 'pi pi-credit-card', iconBg: 'bg-violet-100', iconColor: 'text-violet-600' },
  { id: 'whatsapp', name: 'WhatsApp Business', category: 'Messagerie', description: 'Notifications et support via WhatsApp', icon: 'pi pi-comment', iconBg: 'bg-green-100', iconColor: 'text-green-600' },
  { id: 'google_workspace', name: 'Google Workspace', category: 'Productivité', description: 'Synchronisation contacts, agenda et emails', icon: 'pi pi-google', iconBg: 'bg-red-100', iconColor: 'text-red-600' },
]

const isConnected = (templateId: string) =>
  activeConnectors.value.some(c => c.template_id === templateId)

const fetchConnectors = async () => {
  loading.value = true
  try {
    const [connRes, webhookRes] = await Promise.all([
      fetch('/api/v1/integration/connectors', { headers: { Accept: 'application/json' } }),
      fetch('/api/v1/integration/webhooks', { headers: { Accept: 'application/json' } }),
    ])
    const [connData, webhookData] = await Promise.all([connRes.json(), webhookRes.json()])
    activeConnectors.value = connData.data ?? []
    webhooks.value = webhookData.data ?? []
  } catch (e) { console.error(e) } finally { loading.value = false }
}

const formatDate = (d: string) => new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })

const addConnector = () => router.visit('/integration/connect')
const configureConnector = (c: Connector) => router.visit(`/integration/connectors/${c.id}/configure`)
const disconnectConnector = async (c: Connector) => {
  await fetch(`/api/v1/integration/connectors/${c.id}`, { method: 'DELETE', headers: { Accept: 'application/json' } })
  fetchConnectors()
}
const connectTemplate = (t: ConnectorTemplate) => router.visit(`/integration/connect/${t.id}`)
const addWebhook = () => router.visit('/integration/webhooks/create')
const toggleWebhook = async (w: Webhook) => {
  await fetch(`/api/v1/integration/webhooks/${w.id}`, {
    method: 'PATCH',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({ active: w.active }),
  })
}
const deleteWebhook = async (w: Webhook) => {
  await fetch(`/api/v1/integration/webhooks/${w.id}`, { method: 'DELETE', headers: { Accept: 'application/json' } })
  fetchConnectors()
}

onMounted(fetchConnectors)
</script>
