<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Messages Proactifs & Campagnes In-App</h1>
        <p class="text-surface-500 text-sm mt-1">Anticipez les problèmes et guidez vos utilisateurs (Intercom-style)</p>
      </div>
      <Button label="Créer une règle" icon="pi pi-plus" @click="showRuleDialog = true" v-if="canManage" />
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <Card v-for="s in stats" :key="s.label"><template #content>
        <div class="text-2xl font-bold" :class="s.color">{{ s.value }}</div>
        <div class="text-sm text-surface-500 mt-1">{{ s.label }}</div>
      </template></Card>
    </div>

    <Card>
      <template #header><div class="px-4 pt-4 font-semibold">Règles de déclenchement</div></template>
      <template #content>
        <DataTable :value="rules" stripedRows>
          <Column field="name" header="Nom de la règle" />
          <Column field="trigger" header="Déclencheur" />
          <Column field="segment" header="Segment cible" />
          <Column field="channel" header="Canal"><template #body="{ data }"><Tag :value="data.channel" severity="secondary" size="small" /></template></Column>
          <Column field="sent" header="Envois (30j)" />
          <Column field="openRate" header="Taux ouverture" />
          <Column field="active" header="Active">
            <template #body="{ data }"><ToggleSwitch v-model="data.active" /></template>
          </Column>
          <Column header=""><template #body><Button icon="pi pi-pencil" size="small" text /><Button icon="pi pi-trash" size="small" text severity="danger" v-if="canManage" /></template></Column>
        </DataTable>
      </template>
    </Card>

    <Card>
      <template #header><div class="px-4 pt-4 font-semibold">Campagnes in-app</div></template>
      <template #content>
        <DataTable :value="campaigns" stripedRows>
          <Column field="name" header="Campagne" />
          <Column field="target" header="Cible" />
          <Column field="sent" header="Envois" />
          <Column field="opened" header="Ouvertures %" />
          <Column field="replied" header="Réponses" />
          <Column field="status" header="Statut"><template #body="{ data }"><Tag :value="data.status" :severity="{ Active: 'success', Brouillon: 'secondary', Terminée: 'info' }[data.status]" /></template></Column>
        </DataTable>
      </template>
    </Card>

    <Dialog v-model:visible="showRuleDialog" header="Créer une règle proactive" :style="{ width: '550px' }" modal>
      <div class="space-y-4">
        <div><label class="text-sm font-medium">Nom</label><InputText v-model="ruleForm.name" class="w-full mt-1" /></div>
        <div><label class="text-sm font-medium">Déclencheur</label>
          <Select v-model="ruleForm.trigger" :options="['Page visitée','Temps sur page > X sec','Action effectuée','Erreur rencontrée','Premier login','Inactivité 30 jours']" class="w-full mt-1" /></div>
        <div><label class="text-sm font-medium">Message</label><Textarea v-model="ruleForm.message" rows="3" class="w-full mt-1" placeholder="Bonjour ! Besoin d'aide avec cette fonctionnalité ?" /></div>
        <div class="grid grid-cols-2 gap-4">
          <div><label class="text-sm font-medium">Délai d'affichage (sec)</label><InputNumber v-model="ruleForm.delay" :min="0" :max="300" class="w-full mt-1" /></div>
          <div><label class="text-sm font-medium">Segment cible</label>
            <Select v-model="ruleForm.segment" :options="['Tous les utilisateurs','Nouveaux (<7j)','Plan Premium','Inactifs','Admins']" class="w-full mt-1" /></div>
        </div>
        <div><label class="text-sm font-medium">Canal</label>
          <Select v-model="ruleForm.channel" :options="['In-app uniquement','Email uniquement','In-app + Email']" class="w-full mt-1" /></div>
      </div>
      <template #footer>
        <Button label="Annuler" text @click="showRuleDialog = false" />
        <Button label="Créer la règle" @click="showRuleDialog = false" />
      </template>
    </Dialog>
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
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import InputNumber from 'primevue/inputnumber'
import ToggleSwitch from 'primevue/toggleswitch'

const page = usePage()
const { isElevated, hasAnyRole } = useRoleAccess()
const canManage = computed(() => isElevated.value || hasAnyRole(['customer-service']))

const showRuleDialog = ref(false)
const ruleForm = ref({ name: '', trigger: null, message: '', delay: 5, segment: 'Tous les utilisateurs', channel: 'In-app uniquement' })

const stats = [
  { label: 'Règles actives', value: '4', color: 'text-blue-600' },
  { label: 'Messages envoyés (30j)', value: '1 842', color: 'text-green-600' },
  { label: "Taux d'ouverture", value: '34%', color: 'text-purple-600' },
  { label: 'Tickets évités (estimé)', value: '127', color: 'text-orange-600' },
]

const rules = ref([
  { name: 'Aide à la première facture', trigger: 'Page /accounting/invoices/create visitée', segment: 'Nouveaux (<7j)', channel: 'In-app', sent: 234, openRate: '41%', active: true },
  { name: 'Relance inactifs', trigger: 'Inactivité 30 jours', segment: 'Tous', channel: 'Email', sent: 89, openRate: '28%', active: true },
  { name: 'Guide import données', trigger: 'Page /setup/import visitée', segment: 'Tous', channel: 'In-app + Email', sent: 412, openRate: '52%', active: true },
  { name: 'Upsell Enterprise', trigger: 'Temps sur /pricing > 60s', segment: 'Plan Pro', channel: 'In-app', sent: 67, openRate: '38%', active: false },
])

const campaigns = ref([
  { name: 'Onboarding nouveaux clients Mai 2026', target: 'Inscrits après le 01/05', sent: 42, opened: '67%', replied: 8, status: 'Active' },
  { name: 'Migration vers la v3.0', target: 'Tous les utilisateurs', sent: 1247, opened: '45%', replied: 34, status: 'Terminée' },
  { name: 'Webinaire OHADA - Juin 2026', target: 'Modules Comptabilité', sent: 0, opened: '—', replied: 0, status: 'Brouillon' },
])
</script>
