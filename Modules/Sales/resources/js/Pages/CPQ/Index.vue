<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">CPQ — Configurateur Prix & Devis</h1>
        <p class="text-surface-500 text-sm mt-1">Configurez, tariez et envoyez vos devis avec signature électronique</p>
      </div>
      <Button label="Créer un devis" icon="pi pi-plus" @click="showCreateDialog = true" v-if="canCreate" />
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <Card v-for="s in stats" :key="s.label"><template #content>
        <div class="text-2xl font-bold" :class="s.color">{{ s.value }}</div>
        <div class="text-sm text-surface-500 mt-1">{{ s.label }}</div>
      </template></Card>
    </div>

    <Card>
      <template #header><div class="px-4 pt-4 font-semibold">Devis en cours</div></template>
      <template #content>
        <DataTable :value="quotes" stripedRows responsiveLayout="scroll">
          <Column field="ref" header="Référence" />
          <Column field="client" header="Client" />
          <Column field="lines" header="Lignes" />
          <Column field="amount" header="Montant HT (XOF)" />
          <Column field="discount" header="Remise %">
            <template #body="{ data }"><span :class="data.discount > 20 ? 'text-orange-500 font-bold' : ''">{{ data.discount }}%</span></template>
          </Column>
          <Column field="status" header="Statut">
            <template #body="{ data }"><Tag :value="data.status" :severity="statusSeverity(data.status)" /></template>
          </Column>
          <Column field="expires" header="Expiration" />
          <Column header="Actions">
            <template #body="{ data }">
              <Button icon="pi pi-eye" size="small" text class="mr-1" @click="viewQuote(data)" />
              <Button v-if="data.status === 'Brouillon'" icon="pi pi-send" size="small" text severity="success" @click="sendQuote(data)" />
              <Button v-if="data.status === 'Envoyé'" icon="pi pi-pen-to-square" size="small" text severity="warning" />
              <Button v-if="canDelete" icon="pi pi-trash" size="small" text severity="danger" />
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <Dialog v-model:visible="showCreateDialog" header="Créer un devis CPQ" :style="{ width: '700px' }" modal>
      <div class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div><label class="text-sm font-medium">Client</label>
            <Select v-model="form.client" :options="clients" optionLabel="name" placeholder="Sélectionner" class="w-full mt-1" /></div>
          <div><label class="text-sm font-medium">Date d'expiration</label>
            <DatePicker v-model="form.expires" class="w-full mt-1" /></div>
        </div>
        <div>
          <div class="flex items-center justify-between mb-2">
            <label class="text-sm font-medium">Lignes produits</label>
            <Button label="Ajouter une ligne" icon="pi pi-plus" size="small" text @click="addLine" />
          </div>
          <DataTable :value="form.lines" editMode="cell">
            <Column field="product" header="Produit"><template #body="{ data, index }">
              <Select v-model="form.lines[index].product" :options="products" optionLabel="name" class="w-full" />
            </template></Column>
            <Column field="qty" header="Qté" style="width:80px"><template #body="{ data, index }">
              <InputNumber v-model="form.lines[index].qty" :min="1" class="w-full" /></template></Column>
            <Column field="price" header="Prix unit. (XOF)"><template #body="{ data }">{{ data.price?.toLocaleString() }}</template></Column>
            <Column field="discount" header="Remise %"><template #body="{ data, index }">
              <InputNumber v-model="form.lines[index].discount" :min="0" :max="100" suffix="%" class="w-full" /></template></Column>
            <Column field="subtotal" header="Sous-total"><template #body="{ data }">{{ ((data.price || 0) * (data.qty || 1) * (1 - (data.discount || 0) / 100)).toLocaleString() }} XOF</template></Column>
          </DataTable>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div><label class="text-sm font-medium">Remise globale %</label>
            <InputNumber v-model="form.globalDiscount" :min="0" :max="50" suffix="%" class="w-full mt-1" /></div>
        </div>
        <div v-if="form.globalDiscount > 20" class="p-3 bg-orange-50 border border-orange-200 rounded text-orange-700 text-sm">
          ⚠️ Remise > 20% — approbation manager requise avant envoi
        </div>
        <div class="bg-surface-50 p-3 rounded text-sm space-y-1">
          <div class="flex justify-between"><span>Total HT:</span><strong>{{ totalHT.toLocaleString() }} XOF</strong></div>
          <div class="flex justify-between"><span>TVA 18%:</span><span>{{ (totalHT * 0.18).toLocaleString() }} XOF</span></div>
          <div class="flex justify-between text-base font-bold"><span>Total TTC:</span><span>{{ (totalHT * 1.18).toLocaleString() }} XOF</span></div>
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" text @click="showCreateDialog = false" />
        <Button label="Enregistrer brouillon" icon="pi pi-save" outlined @click="showCreateDialog = false" />
        <Button label="Envoyer pour signature" icon="pi pi-send" @click="showCreateDialog = false" />
      </template>
    </Dialog>

    <Dialog v-model:visible="showQuoteDetail" :header="'Devis — ' + selectedQuote?.ref" :style="{ width: '500px' }" modal>
      <div v-if="selectedQuote" class="space-y-3">
        <div class="flex items-center gap-2"><Tag :value="selectedQuote.status" :severity="statusSeverity(selectedQuote.status)" /></div>
        <div v-if="selectedQuote.status === 'Envoyé'" class="p-3 bg-blue-50 border border-blue-200 rounded text-sm">
          📧 Envoyé le {{ selectedQuote.sentDate }} — En attente de signature électronique
          <Button label="Renvoyer" size="small" text class="ml-2" />
        </div>
        <div v-if="selectedQuote.status === 'Signé'" class="p-3 bg-green-50 border border-green-200 rounded text-sm">
          ✅ Signé le {{ selectedQuote.signedDate }} — IP: 41.82.xxx.xxx
        </div>
        <div v-if="selectedQuote.discount > 20 && selectedQuote.status === 'En révision'" class="p-3 bg-orange-50 border border-orange-200 rounded text-sm">
          ⏳ En attente d'approbation manager (remise {{ selectedQuote.discount }}%)
          <Button v-if="canApprove" label="Approuver" size="small" class="ml-2" severity="success" />
        </div>
      </div>
    </Dialog>

    <AIAssistantPanel v-if="guidance" :guidance="guidance" />
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
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import InputNumber from 'primevue/inputnumber'
import DatePicker from 'primevue/datepicker'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'
import { useRoleAccess } from '@/composables/useRoleAccess'

const page = usePage()
const { isAdmin, isElevated, hasAnyRole } = useRoleAccess()
const canCreate = computed(() => isElevated.value || hasAnyRole(['sales-rep', 'sales-manager']))
const canApprove = computed(() => isElevated.value)
const canDelete = computed(() => isAdmin.value)

const { guidance } = useAiAssistant('Sales', 'create_order')

const showCreateDialog = ref(false)
const showQuoteDetail = ref(false)
const selectedQuote = ref(null)

const stats = [
  { label: 'Devis créés ce mois', value: '34', color: 'text-blue-600' },
  { label: "Taux d'acceptation", value: '62%', color: 'text-green-600' },
  { label: 'Valeur pipeline (XOF)', value: '47.2M', color: 'text-purple-600' },
  { label: 'Délai moyen signature', value: '3.2j', color: 'text-orange-600' },
]

const quotes = ref([
  { ref: 'DEV-2026-089', client: 'Groupe Sonatel', lines: 4, amount: '12 450 000', discount: 0, status: 'Signé', expires: '2026-06-30', sentDate: '2026-05-10', signedDate: '2026-05-12' },
  { ref: 'DEV-2026-090', client: 'Orange CI', lines: 7, amount: '28 900 000', discount: 22, status: 'En révision', expires: '2026-06-15', sentDate: null },
  { ref: 'DEV-2026-091', client: 'MTN Cameroun', lines: 3, amount: '8 200 000', discount: 5, status: 'Envoyé', expires: '2026-06-20', sentDate: '2026-05-18' },
  { ref: 'DEV-2026-092', client: 'Ecobank Togo', lines: 2, amount: '4 500 000', discount: 0, status: 'Brouillon', expires: '2026-07-01' },
  { ref: 'DEV-2026-085', client: 'Air Côte d\'Ivoire', lines: 5, amount: '19 800 000', discount: 10, status: 'Refusé', expires: '2026-05-01' },
  { ref: 'DEV-2026-083', client: 'BCEAO', lines: 6, amount: '35 600 000', discount: 8, status: 'Signé', expires: '2026-05-15', signedDate: '2026-04-28' },
])

const clients = [{ name: 'Groupe Sonatel' }, { name: 'Orange CI' }, { name: 'MTN Cameroun' }, { name: 'Ecobank Togo' }]
const products = [
  { name: 'Module CRM', price: 500000 },
  { name: 'Module Comptabilité', price: 750000 },
  { name: 'Module RH', price: 600000 },
  { name: 'Licence utilisateur annuelle', price: 120000 },
]

const form = ref({ client: null, expires: null, globalDiscount: 0, lines: [{ product: null, qty: 1, price: 500000, discount: 0 }] })

const totalHT = computed(() => {
  const lineTotal = form.value.lines.reduce((sum, l) => sum + (l.price || 0) * (l.qty || 1) * (1 - (l.discount || 0) / 100), 0)
  return Math.round(lineTotal * (1 - (form.value.globalDiscount || 0) / 100))
})

const addLine = () => form.value.lines.push({ product: null, qty: 1, price: 500000, discount: 0 })

const statusSeverity = (s) => ({ Signé: 'success', Envoyé: 'info', Brouillon: 'secondary', 'En révision': 'warn', Refusé: 'danger', Expiré: 'danger' }[s] || 'secondary')

const viewQuote = (q) => { selectedQuote.value = q; showQuoteDetail.value = true }
const sendQuote = (q) => { q.status = 'Envoyé'; q.sentDate = new Date().toLocaleDateString('fr-FR') }
</script>
