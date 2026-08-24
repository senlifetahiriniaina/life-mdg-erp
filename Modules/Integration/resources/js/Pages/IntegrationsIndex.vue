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
          v-if="canCreate"
          icon="pi pi-plus"
          label="Ajouter une connexion"
          @click="openCreateModal"
        />
      </div>

      <!-- Connected / Catalogue / Federation -->
      <Tabs v-model:value="activeTab">
        <TabList>
          <Tab value="0">Connecteurs actifs</Tab>
          <Tab value="1">Catalogue</Tab>
          <Tab v-if="isAdmin" value="2">Fédération (WHB)</Tab>
        </TabList>
        <TabPanels>
        <!-- Connected generic webhook connectors -->
        <TabPanel value="0">
          <div v-if="loadingConnectors" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 pt-4">
            <Skeleton v-for="i in 3" :key="i" height="8rem" />
          </div>
          <div v-else-if="connectors.length === 0" class="text-center py-16 text-surface-400">
            <i class="pi pi-link text-5xl mb-4 block" />
            <p class="font-medium">Aucune intégration active</p>
            <p class="text-sm mt-1">Créez un connecteur webhook générique, ou explorez le catalogue Africa First ci-dessous.</p>
          </div>
          <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 pt-4">
            <div
              v-for="conn in connectors"
              :key="conn.id"
              class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-4 space-y-3"
            >
              <div class="flex items-start justify-between">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-lg bg-surface-100 dark:bg-surface-700 flex items-center justify-center">
                    <i class="pi pi-plug text-xl" />
                  </div>
                  <div>
                    <p class="font-semibold text-surface-900 dark:text-surface-50 text-sm">{{ conn.name }}</p>
                    <p class="text-xs text-surface-400">{{ conn.provider_type }}</p>
                  </div>
                </div>
                <Tag :value="statusLabel(conn.status)" :severity="statusSeverity(conn.status)" class="text-xs" />
              </div>
              <p class="text-xs text-surface-400">
                Dernière sync : {{ conn.last_sync_at ? formatDate(conn.last_sync_at) : 'Jamais' }}
              </p>
              <p v-if="conn.error_message" class="text-xs text-red-500">{{ conn.error_message }}</p>
              <div class="flex gap-2">
                <Button label="Configurer" outlined size="small" class="flex-1" @click="openDetailModal(conn)" />
                <Button
                  v-if="canDelete"
                  icon="pi pi-trash"
                  outlined
                  severity="danger"
                  size="small"
                  @click="confirmDisconnect(conn)"
                />
              </div>
            </div>
          </div>
        </TabPanel>

        <!-- Africa First mobile-money / e-commerce / business-tools catalogue -->
        <TabPanel value="1">
          <div v-if="loadingExternal" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 pt-4">
            <Skeleton v-for="i in 3" :key="i" height="8rem" />
          </div>
          <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 pt-4">
            <div
              v-for="item in externalIntegrations"
              :key="item.key"
              class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-4 space-y-3"
            >
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-surface-100 dark:bg-surface-700 flex items-center justify-center">
                  <i class="pi pi-globe text-xl" />
                </div>
                <div class="flex-1">
                  <p class="font-semibold text-surface-900 dark:text-surface-50 text-sm">{{ item.name }}</p>
                  <p class="text-xs text-surface-400">{{ categoryLabel(item.category) }}</p>
                </div>
                <Tag :value="statusLabel(item.status)" :severity="statusSeverity(item.status)" class="text-xs" />
              </div>
              <p class="text-xs text-surface-500">{{ item.description }}</p>
              <p v-if="item.countries?.length" class="text-xs text-surface-400">
                Pays : {{ item.countries.join(', ') }}
              </p>
              <div class="flex gap-2 flex-wrap">
                <Button
                  v-if="item.status === 'disconnected' || item.status === 'error'"
                  :label="item.status === 'error' ? 'Reconnecter' : 'Connecter'"
                  outlined
                  size="small"
                  class="flex-1"
                  :disabled="!canCreate"
                  @click="openConnectModal(item)"
                />
                <template v-else>
                  <Button label="Tester" outlined size="small" @click="testExternal(item)" />
                  <Button
                    v-if="canDelete"
                    icon="pi pi-times"
                    outlined
                    severity="danger"
                    size="small"
                    @click="disconnectExternal(item)"
                  />
                </template>
              </div>
            </div>
          </div>
        </TabPanel>

        <!-- WideHalo Bridge (WHB) federation partners — admin/super-admin only -->
        <TabPanel v-if="isAdmin" value="2">
          <p class="text-sm text-surface-500 pt-4">
            Échange de données inter-entreprises (factures, commandes, contacts…) avec un
            partenaire WideHalo/Life MDG, sur ce même serveur ou un serveur distant.
          </p>
          <div class="mt-4">
            <Button label="Voir les connexions fédérées" outlined @click="router.visit('/integration')" />
          </div>
        </TabPanel>
        </TabPanels>
      </Tabs>
      <AIAssistantPanel v-if="showAiPanel" :guidance="guidance" @close="showAiPanel = false" />
    </div>

    <!-- Create generic connector modal -->
    <Dialog v-model:visible="showCreateModal" header="Nouveau connecteur webhook" modal :style="{ width: '28rem' }">
      <div class="space-y-3">
        <div>
          <label class="text-sm font-medium block mb-1">Nom</label>
          <InputText v-model="createForm.name" class="w-full" />
          <small v-if="createErrors.name" class="text-red-500">{{ createErrors.name }}</small>
        </div>
        <div>
          <label class="text-sm font-medium block mb-1">Type</label>
          <Dropdown v-model="createForm.provider_type" :options="providerTypes" class="w-full" />
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" text @click="showCreateModal = false" />
        <Button label="Créer" :loading="submittingCreate" @click="submitCreate" />
      </template>
    </Dialog>

    <!-- Connector detail / webhook management modal -->
    <Dialog v-model:visible="showDetailModal" header="Configuration du connecteur" modal :style="{ width: '36rem' }">
      <div v-if="detailConnector" class="space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <p class="font-semibold">{{ detailConnector.name }}</p>
            <p class="text-xs text-surface-400">{{ detailConnector.provider_type }}</p>
          </div>
          <div class="flex gap-2">
            <Tag :value="statusLabel(detailConnector.status)" :severity="statusSeverity(detailConnector.status)" />
            <Button v-if="detailConnector.status === 'inactive'" label="Activer" size="small" @click="activateDetail" />
          </div>
        </div>

        <div>
          <p class="text-sm font-medium mb-2">Webhooks</p>
          <ul v-if="detailWebhooks.length" class="space-y-1 text-sm">
            <li v-for="wh in detailWebhooks" :key="wh.id" class="flex items-center justify-between bg-surface-50 dark:bg-surface-900 rounded px-2 py-1">
              <span class="truncate">{{ wh.method }} {{ wh.url }}</span>
              <Tag :value="wh.is_active ? 'actif' : 'inactif'" :severity="wh.is_active ? 'success' : 'secondary'" class="text-xs" />
            </li>
          </ul>
          <p v-else class="text-xs text-surface-400">Aucun webhook configuré.</p>

          <div class="flex gap-2 mt-2">
            <InputText v-model="newWebhookUrl" placeholder="https://..." class="flex-1" />
            <Button label="Ajouter" size="small" @click="addWebhook" />
          </div>
        </div>

        <div>
          <p class="text-sm font-medium mb-2">Test de dispatch</p>
          <div class="flex gap-2">
            <Button label="Envoyer un test" size="small" outlined @click="dispatchTest" :loading="dispatching" />
          </div>
          <p v-if="dispatchResult" class="text-xs mt-1" :class="dispatchResult.status === 'success' ? 'text-green-600' : 'text-red-500'">
            {{ dispatchResult.status }} — {{ dispatchResult.records_processed }} ok / {{ dispatchResult.records_failed }} échoué(s)
          </p>
        </div>
      </div>
      <template #footer>
        <Button label="Fermer" text @click="showDetailModal = false" />
      </template>
    </Dialog>

    <!-- External integration credentials modal -->
    <Dialog v-model:visible="showConnectModal" header="Connecter" modal :style="{ width: '28rem' }">
      <div v-if="connectTarget" class="space-y-3">
        <p class="text-sm text-surface-500">{{ connectTarget.name }} — {{ connectTarget.description }}</p>
        <div v-for="field in connectTarget.credentials" :key="field">
          <label class="text-sm font-medium block mb-1 capitalize">{{ field.replaceAll('_', ' ') }}</label>
          <InputText v-model="connectCredentials[field]" class="w-full" />
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" text @click="showConnectModal = false" />
        <Button label="Connecter" :loading="submittingConnect" @click="submitConnect" />
      </template>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { useConfirm } from 'primevue/useconfirm'
import Button from 'primevue/button'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import Tag from 'primevue/tag'
import Skeleton from 'primevue/skeleton'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/select'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import { useRoleAccess } from '@/composables/useRoleAccess'

const confirm = useConfirm()
const { isAdmin, isElevated } = useRoleAccess()
const canManage = computed(() => isElevated.value)
const canCreate = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)

const { guidance } = useAiAssistant('Integration', 'view_dashboard')
const showAiPanel = ref(false)
const activeTab = ref('0')

// ---------------------------------------------------------------------------
// CSRF helper — this page uses raw fetch() throughout, which (unlike axios)
// does not auto-attach the XSRF token; matches the fix pattern already
// established for every other fetch()-based page in this app (Chantier 19
// Lot 4/5's Categories/Index.vue, Shipments/Index.vue, etc.).
// ---------------------------------------------------------------------------
function getCsrf(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? ''
}

// ---------------------------------------------------------------------------
// Generic webhook connectors (IntegrationController — connectors/*)
// ---------------------------------------------------------------------------

interface Connector {
  id: number
  name: string
  provider_type: string
  status: 'active' | 'inactive' | 'error'
  last_sync_at: string | null
  error_message: string | null
}

const connectors = ref<Connector[]>([])
const loadingConnectors = ref(false)

const fetchConnectors = async () => {
  loadingConnectors.value = true
  try {
    const res = await fetch('/api/v1/integration/connectors', { headers: { Accept: 'application/json' } })
    const data = await res.json()
    connectors.value = data.data ?? []
  } catch (e) {
    console.error(e)
  } finally {
    loadingConnectors.value = false
  }
}

function statusLabel(status: string): string {
  return { active: 'Actif', connected: 'Actif', inactive: 'Inactif', disconnected: 'Non connecté', error: 'Erreur' }[status] ?? status
}
function statusSeverity(status: string): string {
  return { active: 'success', connected: 'success', inactive: 'secondary', disconnected: 'secondary', error: 'danger' }[status] ?? 'info'
}
const formatDate = (d: string) => new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })

// -- Create --
const showCreateModal = ref(false)
const submittingCreate = ref(false)
const providerTypes = ['webhook', 'oauth2', 'api_key', 'basic_auth', 'custom']
const createForm = reactive({ name: '', provider_type: 'webhook' })
const createErrors = reactive<Record<string, string>>({})

const openCreateModal = () => {
  createForm.name = ''
  createForm.provider_type = 'webhook'
  Object.keys(createErrors).forEach(k => delete createErrors[k])
  showCreateModal.value = true
}

const submitCreate = async () => {
  submittingCreate.value = true
  Object.keys(createErrors).forEach(k => delete createErrors[k])
  try {
    const res = await fetch('/api/v1/integration/connectors', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': getCsrf() },
      body: JSON.stringify(createForm),
    })
    if (res.status === 422) {
      const body = await res.json()
      Object.assign(createErrors, Object.fromEntries(Object.entries(body.errors ?? {}).map(([k, v]) => [k, (v as string[])[0]])))
      return
    }
    showCreateModal.value = false
    fetchConnectors()
  } finally {
    submittingCreate.value = false
  }
}

const confirmDisconnect = (conn: Connector) => {
  confirm.require({
    message: `Supprimer le connecteur "${conn.name}" ?`,
    header: 'Supprimer',
    icon: 'pi pi-exclamation-triangle',
    rejectClass: 'p-button-text',
    acceptClass: 'p-button-danger',
    accept: async () => {
      await fetch(`/api/v1/integration/connectors/${conn.id}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrf() },
      })
      fetchConnectors()
    },
  })
}

// -- Detail / webhooks / dispatch --
const showDetailModal = ref(false)
const detailConnector = ref<Connector | null>(null)
const detailWebhooks = ref<{ id: number; url: string; method: string; is_active: boolean }[]>([])
const newWebhookUrl = ref('')
const dispatching = ref(false)
const dispatchResult = ref<{ status: string; records_processed: number; records_failed: number } | null>(null)

const openDetailModal = async (conn: Connector) => {
  detailConnector.value = conn
  dispatchResult.value = null
  newWebhookUrl.value = ''
  showDetailModal.value = true
  const res = await fetch(`/api/v1/integration/connectors/${conn.id}`, { headers: { Accept: 'application/json' } })
  const data = await res.json()
  detailWebhooks.value = data.webhook_endpoints ?? []
}

const activateDetail = async () => {
  if (!detailConnector.value) return
  const res = await fetch(`/api/v1/integration/connectors/${detailConnector.value.id}/activate`, {
    method: 'POST',
    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrf() },
  })
  detailConnector.value = await res.json()
  fetchConnectors()
}

const addWebhook = async () => {
  if (!detailConnector.value || !newWebhookUrl.value) return
  await fetch(`/api/v1/integration/connectors/${detailConnector.value.id}/webhook`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': getCsrf() },
    body: JSON.stringify({ url: newWebhookUrl.value, method: 'POST' }),
  })
  newWebhookUrl.value = ''
  const res = await fetch(`/api/v1/integration/connectors/${detailConnector.value.id}`, { headers: { Accept: 'application/json' } })
  const data = await res.json()
  detailWebhooks.value = data.webhook_endpoints ?? []
}

const dispatchTest = async () => {
  if (!detailConnector.value) return
  dispatching.value = true
  try {
    const res = await fetch(`/api/v1/integration/connectors/${detailConnector.value.id}/dispatch`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': getCsrf() },
      body: JSON.stringify({ payload: { event: 'test.ping', sent_at: new Date().toISOString() } }),
    })
    dispatchResult.value = await res.json()
  } finally {
    dispatching.value = false
  }
}

// ---------------------------------------------------------------------------
// External integrations (ExternalIntegrationController — external/* —
// IntegrationManager's Africa First mobile-money/e-commerce/business-tools
// registry, activated by Chantier 32.6)
// ---------------------------------------------------------------------------

interface ExternalIntegration {
  key: string
  name: string
  category: string
  description: string
  countries: string[]
  credentials: string[]
  status: 'connected' | 'disconnected' | 'error'
}

const externalIntegrations = ref<ExternalIntegration[]>([])
const loadingExternal = ref(false)

const fetchExternal = async () => {
  loadingExternal.value = true
  try {
    const res = await fetch('/api/v1/integration/external', { headers: { Accept: 'application/json' } })
    const data = await res.json()
    externalIntegrations.value = data.data ?? []
  } catch (e) {
    console.error(e)
  } finally {
    loadingExternal.value = false
  }
}

function categoryLabel(category: string): string {
  return {
    payment_africa: 'Mobile Money (Africa First)',
    ecommerce: 'E-commerce',
    business: 'Outils métier',
    automation: 'Automatisation',
  }[category] ?? category
}

const showConnectModal = ref(false)
const submittingConnect = ref(false)
const connectTarget = ref<ExternalIntegration | null>(null)
const connectCredentials = reactive<Record<string, string>>({})

const openConnectModal = (item: ExternalIntegration) => {
  connectTarget.value = item
  Object.keys(connectCredentials).forEach(k => delete connectCredentials[k])
  item.credentials.forEach(field => { connectCredentials[field] = '' })
  showConnectModal.value = true
}

const submitConnect = async () => {
  if (!connectTarget.value) return
  submittingConnect.value = true
  try {
    await fetch(`/api/v1/integration/external/${connectTarget.value.key}/connect`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': getCsrf() },
      body: JSON.stringify({ credentials: { ...connectCredentials } }),
    })
    showConnectModal.value = false
    fetchExternal()
  } finally {
    submittingConnect.value = false
  }
}

const testExternal = async (item: ExternalIntegration) => {
  const res = await fetch(`/api/v1/integration/external/${item.key}/test`, {
    method: 'POST',
    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrf() },
  })
  const body = await res.json()
  alert(body.success ? 'Connexion OK.' : (body.error ?? 'Échec du test de connexion.'))
  fetchExternal()
}

const disconnectExternal = async (item: ExternalIntegration) => {
  await fetch(`/api/v1/integration/external/${item.key}/disconnect`, {
    method: 'POST',
    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrf() },
  })
  fetchExternal()
}

onMounted(() => {
  fetchConnectors()
  fetchExternal()
})
</script>
