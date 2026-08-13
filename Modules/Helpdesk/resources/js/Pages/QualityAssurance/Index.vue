<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Assurance Qualité — Scoring des Interactions</h1>
        <p class="text-surface-500 text-sm mt-1">100% des interactions scorées automatiquement par l'IA</p>
      </div>
      <Button label="Configurer les critères" icon="pi pi-cog" outlined v-if="canManage" @click="showCriteriaDialog = true" />
    </div>

    <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg text-blue-700 text-sm">
      🤖 L'IA analyse automatiquement chaque interaction selon 5 critères pondérés — aucune sélection manuelle nécessaire.
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <Card v-for="s in stats" :key="s.label"><template #content>
        <div class="text-2xl font-bold" :class="s.color">{{ s.value }}</div>
        <div class="text-sm text-surface-500 mt-1">{{ s.label }}</div>
      </template></Card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <Card>
        <template #header><div class="px-4 pt-4 font-semibold">Classement agents (ce mois)</div></template>
        <template #content>
          <DataTable :value="agentLeaderboard" size="small" stripedRows>
            <Column header="#"><template #body="{ index }"><span class="font-bold" :class="index === 0 ? 'text-yellow-500' : index === 1 ? 'text-gray-400' : index === 2 ? 'text-orange-500' : ''">{{ index + 1 }}</span></template></Column>
            <Column field="name" header="Agent" />
            <Column field="avgScore" header="Score moyen">
              <template #body="{ data }"><span :class="scoreClass(data.avgScore)">{{ data.avgScore }}/100</span></template>
            </Column>
            <Column field="tickets" header="Tickets" />
            <Column field="below" header="Sous seuil %" />
            <Column field="trend" header="Tendance" />
          </DataTable>
        </template>
      </Card>

      <Card>
        <template #header><div class="px-4 pt-4 font-semibold">Critères de scoring</div></template>
        <template #content>
          <div v-for="c in criteria" :key="c.name" class="flex items-center justify-between py-2 border-b border-surface-100 last:border-0">
            <span class="text-sm font-medium">{{ c.name }}</span>
            <div class="flex items-center gap-2">
              <ProgressBar :value="c.weight" :style="{ height: '6px', width: '80px' }" />
              <span class="text-sm w-8 text-right">{{ c.weight }}%</span>
            </div>
          </div>
          <div class="flex justify-between text-xs text-surface-400 mt-3 pt-2 border-t">
            <span>Total: 100%</span><span>Pondération automatique</span>
          </div>
        </template>
      </Card>
    </div>

    <Card>
      <template #header><div class="px-4 pt-4 font-semibold">Interactions scorées</div></template>
      <template #content>
        <DataTable :value="interactions" stripedRows selectionMode="single" v-model:selection="selectedInteraction" @row-select="showDetailDrawer = true">
          <Column field="ticketId" header="Ticket" />
          <Column field="agent" header="Agent" />
          <Column field="client" header="Client" />
          <Column field="score" header="Score /100">
            <template #body="{ data }"><Tag :value="data.score + '/100'" :severity="data.score >= 85 ? 'success' : data.score >= 70 ? 'warn' : 'danger'" /></template>
          </Column>
          <Column field="status" header="Statut"><template #body="{ data }"><Tag :value="data.status" severity="secondary" size="small" /></template></Column>
          <Column field="date" header="Date" />
          <Column header="Actions">
            <template #body="{ data }">
              <Button v-if="canManage && data.status === 'Auto-scoré'" label="Valider" size="small" text severity="success" @click.stop="data.status = 'Validé'" />
              <Button v-if="canManage" label="Coacher" size="small" text @click.stop />
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <Drawer v-model:visible="showDetailDrawer" position="right" :style="{ width: '500px' }" :header="'Score — Ticket ' + selectedInteraction?.ticketId">
      <div v-if="selectedInteraction" class="space-y-4">
        <div class="text-3xl font-bold text-center" :class="scoreClass(selectedInteraction.score)">{{ selectedInteraction.score }}/100</div>
        <div v-for="c in scoreBreakdown" :key="c.criterion" class="py-2 border-b border-surface-100 last:border-0">
          <div class="flex justify-between mb-1">
            <span class="text-sm font-medium">{{ c.criterion }}</span>
            <span :class="scoreClass(c.score)" class="text-sm font-bold">{{ c.score }}/100</span>
          </div>
          <ProgressBar :value="c.score" :style="{ height: '6px' }" />
          <div class="text-xs text-surface-500 mt-1 italic">{{ c.comment }}</div>
        </div>
      </div>
    </Drawer>

    <Dialog v-model:visible="showCriteriaDialog" header="Configurer les critères de scoring" :style="{ width: '450px' }" modal>
      <div class="space-y-3">
        <div v-for="c in criteria" :key="c.name" class="flex items-center gap-3">
          <span class="text-sm w-48">{{ c.name }}</span>
          <Slider v-model="c.weight" :min="5" :max="50" class="flex-1" />
          <span class="text-sm w-8">{{ c.weight }}%</span>
        </div>
        <div class="text-sm text-surface-500">Total: {{ criteria.reduce((s, c) => s + c.weight, 0) }}% (doit égaler 100%)</div>
      </div>
      <template #footer>
        <Button label="Annuler" text @click="showCriteriaDialog = false" />
        <Button label="Sauvegarder" @click="showCriteriaDialog = false" />
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
import Drawer from 'primevue/drawer'
import Dialog from 'primevue/dialog'
import ProgressBar from 'primevue/progressbar'
import Slider from 'primevue/slider'

const page = usePage()
const roles = computed(() => page.props.auth?.user?.roles?.map(r => r.name) || [])
const canManage = computed(() => roles.value.some(r => ['admin','super-admin','support-manager'].includes(r)))

const showDetailDrawer = ref(false)
const showCriteriaDialog = ref(false)
const selectedInteraction = ref(null)

const stats = [
  { label: 'Interactions scorées', value: '1 284', color: 'text-blue-600' },
  { label: 'Score moyen équipe', value: '78/100', color: 'text-green-600' },
  { label: 'Sous seuil (<70)', value: '187', color: 'text-orange-600' },
  { label: 'Agents en alerte', value: '2', color: 'text-red-500' },
]

const criteria = ref([
  { name: 'Politesse & professionnalisme', weight: 25 },
  { name: 'Résolution au 1er contact', weight: 30 },
  { name: 'Respect des SLAs', weight: 20 },
  { name: 'Précision de la réponse', weight: 15 },
  { name: 'Empathie client', weight: 10 },
])

const agentLeaderboard = ref([
  { name: 'Sophie Martin', avgScore: 92, tickets: 124, below: '3%', trend: '↑' },
  { name: 'Jean-Paul Koffi', avgScore: 87, tickets: 98, below: '8%', trend: '→' },
  { name: 'Aminata Diallo', avgScore: 84, tickets: 142, below: '11%', trend: '↑' },
  { name: 'Oumar Ba', avgScore: 76, tickets: 87, below: '18%', trend: '↘' },
  { name: 'Cécile Dupont', avgScore: 61, tickets: 103, below: '34%', trend: '↓' },
])

const interactions = ref([
  { ticketId: '#TK-5821', agent: 'Sophie Martin', client: 'Amadou Diallo', score: 94, status: 'Validé', date: '24 mai' },
  { ticketId: '#TK-5820', agent: 'Oumar Ba', client: 'Fatou Mbaye', score: 68, status: 'Auto-scoré', date: '24 mai' },
  { ticketId: '#TK-5819', agent: 'Jean-Paul Koffi', client: 'Kofi Asante', score: 88, status: 'Auto-scoré', date: '23 mai' },
  { ticketId: '#TK-5815', agent: 'Cécile Dupont', client: 'Moussa Traoré', score: 55, status: 'Contesté', date: '23 mai' },
  { ticketId: '#TK-5810', agent: 'Aminata Diallo', client: 'Awa Koné', score: 82, status: 'Validé', date: '22 mai' },
])

const scoreBreakdown = [
  { criterion: 'Politesse & professionnalisme', score: 95, comment: 'Ton courtois tout au long de l\'échange' },
  { criterion: 'Résolution au 1er contact', score: 90, comment: 'Problème résolu sans transfert' },
  { criterion: 'Respect des SLAs', score: 100, comment: 'Réponse en 8 minutes (SLA: 30min)' },
  { criterion: 'Précision de la réponse', score: 88, comment: 'Information correcte, légèrement incomplète' },
  { criterion: 'Empathie client', score: 92, comment: 'Bonne reformulation du problème du client' },
]

const scoreClass = (s) => s >= 85 ? 'text-green-600' : s >= 70 ? 'text-orange-500' : 'text-red-500'
</script>
