<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Synchronisation Multi-Marketplaces</h1>
        <p class="text-surface-500 text-sm mt-1">Gérez votre stock sur tous vos canaux de vente en temps réel</p>
      </div>
      <Button label="Connecter un marketplace" icon="pi pi-plus" @click="showConnectDialog = true" />
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <Card v-for="s in stats" :key="s.label"><template #content>
        <div class="text-2xl font-bold" :class="s.color">{{ s.value }}</div>
        <div class="text-sm text-surface-500 mt-1">{{ s.label }}</div>
      </template></Card>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <Card v-for="ch in channels" :key="ch.name">
        <template #content>
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
              <div class="w-10 h-10 rounded-lg flex items-center justify-center font-bold text-white" :style="{ background: ch.color }">{{ ch.initials }}</div>
              <div><div class="font-semibold">{{ ch.name }}</div><div class="text-xs text-surface-500">{{ ch.type }}</div></div>
            </div>
            <Tag :value="ch.status" :severity="ch.status === 'Connecté' ? 'success' : ch.status === 'En erreur' ? 'danger' : 'secondary'" />
          </div>
          <div class="text-sm space-y-1 mb-3">
            <div class="flex justify-between"><span class="text-surface-500">Produits synchro:</span><strong>{{ ch.products }}</strong></div>
            <div class="flex justify-between"><span class="text-surface-500">Dernière sync:</span><span>{{ ch.lastSync }}</span></div>
          </div>
          <div class="flex gap-2">
            <Button :label="syncing[ch.name] ? 'Sync...' : 'Sync maintenant'" size="small" outlined :loading="syncing[ch.name]" @click="syncChannel(ch)" class="flex-1" />
            <Button icon="pi pi-cog" size="small" text />
          </div>
        </template>
      </Card>
    </div>

    <Card v-if="conflicts.length > 0" class="border-l-4 border-red-400">
      <template #header><div class="px-4 pt-4 font-semibold text-red-700">⚠️ Conflits de stock ({{ conflicts.length }})</div></template>
      <template #content>
        <DataTable :value="conflicts" stripedRows size="small">
          <Column field="product" header="Produit" />
          <Column field="marketplace" header="Marketplace" />
          <Column field="stockWH" header="Stock WideHalo" />
          <Column field="stockMP" header="Stock Marketplace" />
          <Column field="diff" header="Écart"><template #body="{ data }"><span class="text-red-500 font-bold">{{ data.diff }}</span></template></Column>
          <Column header="Résolution">
            <template #body="{ data }">
              <Select v-model="data.resolution" :options="['Garder WideHalo','Garder Marketplace','Calculer la moyenne']" class="w-44 text-sm" />
            </template>
          </Column>
          <Column header=""><template #body><Button label="Résoudre" size="small" /></template></Column>
        </DataTable>
      </template>
    </Card>

    <Card>
      <template #header><div class="px-4 pt-4 font-semibold">Journal des synchronisations</div></template>
      <template #content>
        <DataTable :value="syncLogs" size="small" stripedRows>
          <Column field="datetime" header="Date/Heure" />
          <Column field="channel" header="Canal" />
          <Column field="type" header="Type" />
          <Column field="products" header="Produits traités" />
          <Column field="status" header="Statut"><template #body="{ data }"><Tag :value="data.status" :severity="data.status === 'Succès' ? 'success' : 'danger'" size="small" /></template></Column>
          <Column field="duration" header="Durée" />
        </DataTable>
      </template>
    </Card>

    <Dialog v-model:visible="showConnectDialog" header="Connecter un marketplace" :style="{ width: '450px' }" modal>
      <div class="space-y-4">
        <div><label class="text-sm font-medium">Plateforme</label>
          <Select v-model="connectForm.platform" :options="['Shopify','Amazon','eBay','Jumia','Cdiscount','WooCommerce','PrestaShop']" placeholder="Sélectionner" class="w-full mt-1" /></div>
        <div><label class="text-sm font-medium">Clé API</label>
          <InputText v-model="connectForm.apiKey" class="w-full mt-1" placeholder="sk_live_..." /></div>
        <div><label class="text-sm font-medium">URL de la boutique</label>
          <InputText v-model="connectForm.shopUrl" class="w-full mt-1" placeholder="ma-boutique.myshopify.com" /></div>
        <div><label class="text-sm font-medium">Direction de synchronisation</label>
          <Select v-model="connectForm.direction" :options="['Bidirectionnel','Stock seulement','Prix seulement','Tout (stock + prix + commandes)']" class="w-full mt-1" /></div>
      </div>
      <template #footer>
        <Button label="Annuler" text @click="showConnectDialog = false" />
        <Button label="Connecter et tester" @click="showConnectDialog = false" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed} from 'vue'
import { usePage } from '@inertiajs/vue3'
import Card from 'primevue/card'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import InputText from 'primevue/inputtext'

const page = usePage()
const roles = computed(() => page.props.auth?.user?.roles?.map(r => r.name) || [])
const isAdmin = computed(() => roles.value.some(r => ['admin', 'super-admin'].includes(r)))
const canManage = computed(() => roles.value.some(r => ['inventory-manager', 'admin', 'super-admin'].includes(r)))
const canCreate = computed(() => canManage.value)
const canEdit = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)


const showConnectDialog = ref(false)
const syncing = ref({})
const connectForm = ref({ platform: null, apiKey: '', shopUrl: '', direction: 'Bidirectionnel' })

const stats = [
  { label: 'Canaux connectés', value: '5', color: 'text-blue-600' },
  { label: 'Produits synchronisés', value: '1 247', color: 'text-green-600' },
  { label: 'Conflits en cours', value: '3', color: 'text-red-500' },
  { label: 'Dernière sync globale', value: 'il y a 23min', color: 'text-surface-500' },
]

const channels = ref([
  { name: 'Shopify', type: 'E-Commerce', initials: 'SH', color: '#96bf48', status: 'Connecté', products: 842, lastSync: 'il y a 23min' },
  { name: 'Amazon', type: 'Marketplace', initials: 'AM', color: '#ff9900', status: 'Connecté', products: 312, lastSync: 'il y a 1h' },
  { name: 'Jumia', type: 'Marketplace Afrique', initials: 'JU', color: '#f68b1e', status: 'Connecté', products: 156, lastSync: 'il y a 45min' },
  { name: 'eBay', type: 'Marketplace', initials: 'EB', color: '#e53238', status: 'En erreur', products: 0, lastSync: 'Erreur API' },
  { name: 'Cdiscount', type: 'Marketplace FR', initials: 'CD', color: '#e0001b', status: 'Déconnecté', products: 0, lastSync: 'Jamais' },
])

const conflicts = ref([
  { product: 'Écran LCD 24"', marketplace: 'Shopify', stockWH: 12, stockMP: 8, diff: -4, resolution: 'Garder WideHalo' },
  { product: 'Laptop Pro 15"', marketplace: 'Amazon', stockWH: 24, stockMP: 31, diff: +7, resolution: null },
  { product: 'Souris optique', marketplace: 'Jumia', stockWH: 67, stockMP: 52, diff: -15, resolution: null },
])

const syncLogs = ref([
  { datetime: '2026-05-24 14:22', channel: 'Shopify', type: 'Stock + Prix', products: 842, status: 'Succès', duration: '12s' },
  { datetime: '2026-05-24 13:45', channel: 'Amazon', type: 'Stock', products: 312, status: 'Succès', duration: '8s' },
  { datetime: '2026-05-24 13:30', channel: 'eBay', type: 'Stock', products: 0, status: 'Erreur', duration: '3s' },
  { datetime: '2026-05-24 13:15', channel: 'Jumia', type: 'Stock + Prix', products: 156, status: 'Succès', duration: '9s' },
])

const syncChannel = async (ch) => {
  syncing.value[ch.name] = true
  await new Promise(r => setTimeout(r, 2000))
  ch.lastSync = 'il y a quelques secondes'
  syncing.value[ch.name] = false
}
</script>
