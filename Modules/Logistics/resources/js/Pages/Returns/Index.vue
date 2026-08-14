<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Gestion des Retours (Reverse Logistics)</h1>
        <p class="text-surface-500 text-sm mt-1">Automatisez les retours, remboursements et reconditionnements</p>
      </div>
      <Button label="Nouveau retour" icon="pi pi-plus" @click="showCreateDialog = true" v-if="canManage" />
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <Card v-for="s in stats" :key="s.label"><template #content>
        <div class="text-2xl font-bold" :class="s.color">{{ s.value }}</div>
        <div class="text-sm text-surface-500 mt-1">{{ s.label }}</div>
      </template></Card>
    </div>

    <TabView>
      <TabPanel header="Demandes de retour">
        <DataTable :value="returns" stripedRows>
          <Column field="ref" header="Référence" />
          <Column field="orderRef" header="Commande" />
          <Column field="client" header="Client" />
          <Column field="product" header="Produit" />
          <Column field="reason" header="Motif"><template #body="{ data }"><Tag :value="data.reason" severity="secondary" size="small" /></template></Column>
          <Column field="status" header="Statut">
            <template #body="{ data }"><Tag :value="data.status" :severity="returnStatusSeverity(data.status)" size="small" /></template>
          </Column>
          <Column field="resolution" header="Résolution"><template #body="{ data }"><Tag :value="data.resolution" :severity="{ Remboursement: 'success', Échange: 'info', 'Avoir': 'warn', Réparation: 'secondary' }[data.resolution] || 'secondary'" size="small" /></template></Column>
          <Column field="createdAt" header="Date" />
          <Column header="">
            <template #body="{ data }">
              <Button icon="pi pi-cog" size="small" text @click="selectedReturn = data; showDetailDrawer = true" />
            </template>
          </Column>
        </DataTable>
      </TabPanel>

      <TabPanel header="Politique de retour">
        <div class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div v-for="policy in policies" :key="policy.category" class="p-4 border rounded-lg">
              <div class="font-medium mb-2">{{ policy.category }}</div>
              <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-surface-500">Délai retour:</span><strong>{{ policy.days }} jours</strong></div>
                <div class="flex justify-between"><span class="text-surface-500">Frais retour:</span><strong>{{ policy.cost }}</strong></div>
                <div class="flex justify-between"><span class="text-surface-500">Motifs acceptés:</span><Tag :value="policy.reasons" severity="secondary" size="small" /></div>
                <div class="flex justify-between"><span class="text-surface-500">Remboursement:</span><strong>{{ policy.refund }}</strong></div>
              </div>
            </div>
          </div>
        </div>
      </TabPanel>

      <TabPanel header="Reconditionnement">
        <DataTable :value="refurbishments" stripedRows>
          <Column field="ref" header="Retour" />
          <Column field="product" header="Produit" />
          <Column field="condition" header="État reçu">
            <template #body="{ data }"><Tag :value="data.condition" :severity="{ Neuf: 'success', 'Légères traces': 'warn', 'Endommagé': 'danger' }[data.condition]" size="small" /></template>
          </Column>
          <Column field="action" header="Action décidée" />
          <Column field="recoveredValue" header="Valeur récupérée (XOF)" />
          <Column field="status" header="Statut"><template #body="{ data }"><Tag :value="data.status" :severity="{ 'En cours': 'warn', Terminé: 'success', 'À évaluer': 'info' }[data.status]" size="small" /></template></Column>
        </DataTable>
      </TabPanel>
    </TabView>

    <Dialog v-model:visible="showCreateDialog" header="Nouveau retour" :style="{ width: '500px' }" modal>
      <div class="space-y-4">
        <div><label class="text-sm font-medium">Numéro de commande</label><InputText v-model="newReturn.orderRef" class="w-full mt-1" placeholder="ORD-2026-..." /></div>
        <div><label class="text-sm font-medium">Motif</label>
          <Select v-model="newReturn.reason" :options="['Produit défectueux','Non conforme à la description','Erreur de commande','Délai de livraison dépassé','Changement d\'avis']" class="w-full mt-1" /></div>
        <div><label class="text-sm font-medium">Résolution souhaitée</label>
          <Select v-model="newReturn.resolution" :options="['Remboursement','Échange','Avoir','Réparation']" class="w-full mt-1" /></div>
        <div><label class="text-sm font-medium">Description</label><Textarea v-model="newReturn.description" rows="3" class="w-full mt-1" /></div>
      </div>
      <template #footer>
        <Button label="Annuler" text @click="showCreateDialog = false" />
        <Button label="Créer le retour" @click="showCreateDialog = false" />
      </template>
    </Dialog>

    <Drawer v-model:visible="showDetailDrawer" :header="selectedReturn?.ref" position="right" :style="{ width: '420px' }">
      <div v-if="selectedReturn" class="space-y-4 text-sm">
        <div class="p-3 bg-surface-50 rounded space-y-1">
          <div><strong>Client:</strong> {{ selectedReturn.client }}</div>
          <div><strong>Commande:</strong> {{ selectedReturn.orderRef }}</div>
          <div><strong>Produit:</strong> {{ selectedReturn.product }}</div>
          <div><strong>Motif:</strong> {{ selectedReturn.reason }}</div>
        </div>
        <div class="space-y-2">
          <label class="text-sm font-medium">Changer le statut</label>
          <Select :modelValue="selectedReturn.status" :options="['Demande reçue','En transit','Reçu en entrepôt','Inspecté','Résolu']" class="w-full" @change="e => selectedReturn.status = e.value" />
        </div>
        <div class="space-y-2">
          <label class="text-sm font-medium">Résolution</label>
          <Select :modelValue="selectedReturn.resolution" :options="['Remboursement','Échange','Avoir','Réparation']" class="w-full" />
        </div>
        <Button label="Sauvegarder & notifier client" icon="pi pi-check" class="w-full" @click="showDetailDrawer = false" />
      </div>
    </Drawer>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Card from 'primevue/card'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import TabView from 'primevue/tabview'
import TabPanel from 'primevue/tabpanel'
import Dialog from 'primevue/dialog'
import Drawer from 'primevue/drawer'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'

const page = usePage()
const { isElevated, hasAnyRole } = useRoleAccess()
const canManage = computed(() => isElevated.value || hasAnyRole(['logistics-manager']))

const showCreateDialog = ref(false)
const showDetailDrawer = ref(false)
const selectedReturn = ref(null)
const newReturn = ref({ orderRef: '', reason: null, resolution: null, description: '' })

const stats = [
  { label: 'Retours ce mois', value: '34', color: 'text-blue-600' },
  { label: 'Taux de retour', value: '2.8%', color: 'text-orange-600' },
  { label: 'Valeur récupérée (XOF)', value: '1.2M', color: 'text-green-600' },
  { label: 'Délai moyen traitement', value: '3.2j', color: 'text-purple-600' },
]

const returns = ref([
  { ref: 'RET-2026-034', orderRef: 'ORD-4521', client: 'Amadou Diallo', product: 'Laptop Pro 15"', reason: 'Défectueux', status: 'Reçu en entrepôt', resolution: 'Échange', createdAt: '22 mai' },
  { ref: 'RET-2026-033', orderRef: 'ORD-4489', client: 'Fatou Mbaye', product: 'Souris sans fil', reason: 'Non conforme', status: 'Résolu', resolution: 'Remboursement', createdAt: '20 mai' },
  { ref: 'RET-2026-032', orderRef: 'ORD-4470', client: 'Kofi Asante', product: 'Hub USB-C', reason: 'Erreur commande', status: 'En transit', resolution: 'Avoir', createdAt: '19 mai' },
  { ref: 'RET-2026-031', orderRef: 'ORD-4455', client: 'Awa Koné', product: 'Casque Bluetooth', reason: 'Défectueux', status: 'Demande reçue', resolution: 'Réparation', createdAt: '18 mai' },
])

const policies = [
  { category: 'Électronique & Informatique', days: 14, cost: 'Gratuit si défaut', reasons: 'Défaut / Non-conforme', refund: '100% sous 5j ouvrés' },
  { category: 'Accessoires', days: 30, cost: '2 500 XOF', reasons: 'Tous motifs', refund: '100% si état original' },
  { category: 'Logiciels & Licences', days: 7, cost: 'Non remboursable', reasons: 'Défaut technique', refund: 'Avoir commercial' },
  { category: 'B2B / Grandes commandes', days: 7, cost: 'Négocié', reasons: 'Selon contrat', refund: 'Selon contrat-cadre' },
]

const refurbishments = ref([
  { ref: 'RET-2026-030', product: 'Laptop Pro 15" i7', condition: 'Légères traces', action: 'Reconditionnement Grade B', recoveredValue: '620 000', status: 'En cours' },
  { ref: 'RET-2026-028', product: 'Écran LCD 24"', condition: 'Neuf', action: 'Remis en vente (neuf)', recoveredValue: '180 000', status: 'Terminé' },
  { ref: 'RET-2026-025', product: 'Clavier mécanique', condition: 'Endommagé', action: 'Pièces détachées', recoveredValue: '15 000', status: 'Terminé' },
  { ref: 'RET-2026-034', product: 'Laptop Pro 15"', condition: 'Légères traces', action: 'À évaluer', recoveredValue: '—', status: 'À évaluer' },
])

const returnStatusSeverity = (s) => ({ 'Demande reçue': 'info', 'En transit': 'warn', 'Reçu en entrepôt': 'warn', Inspecté: 'info', Résolu: 'success' }[s] || 'secondary')
</script>
