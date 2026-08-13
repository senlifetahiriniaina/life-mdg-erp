<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Automatisation Robotique des Processus (RPA)</h1>
        <p class="text-surface-500 text-sm mt-1">Automatisez les tâches répétitives : saisies, rapports, transferts de données entre systèmes</p>
      </div>
      <Button label="Nouveau bot" icon="pi pi-plus" @click="showCreateDialog = true" v-if="canManage" />
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <Card v-for="s in stats" :key="s.label"><template #content>
        <div class="text-2xl font-bold" :class="s.color">{{ s.value }}</div>
        <div class="text-sm text-surface-500 mt-1">{{ s.label }}</div>
      </template></Card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <Card class="lg:col-span-2">
        <template #header><div class="px-4 pt-4 font-semibold">Bots RPA</div></template>
        <template #content>
          <div class="space-y-3">
            <div v-for="bot in bots" :key="bot.id" class="p-4 border rounded-lg">
              <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                  <span class="text-xl">🤖</span>
                  <div>
                    <div class="font-medium">{{ bot.name }}</div>
                    <div class="text-xs text-surface-500">{{ bot.description }}</div>
                  </div>
                </div>
                <div class="flex items-center gap-2">
                  <Tag :value="bot.status" :severity="{ 'En cours': 'warn', 'Actif': 'success', 'Erreur': 'danger', 'Désactivé': 'secondary' }[bot.status]" />
                  <ToggleSwitch :modelValue="bot.enabled" @change="bot.enabled = !bot.enabled; bot.status = bot.enabled ? 'Actif' : 'Désactivé'" />
                </div>
              </div>
              <div class="grid grid-cols-4 gap-2 text-xs text-center">
                <div class="bg-surface-50 rounded p-2"><div class="text-surface-400">Exécutions</div><div class="font-bold">{{ bot.runs }}</div></div>
                <div class="bg-surface-50 rounded p-2"><div class="text-surface-400">Succès</div><div class="font-bold text-green-600">{{ bot.success }}%</div></div>
                <div class="bg-surface-50 rounded p-2"><div class="text-surface-400">Temps économisé</div><div class="font-bold text-blue-600">{{ bot.timeSaved }}</div></div>
                <div class="bg-surface-50 rounded p-2"><div class="text-surface-400">Prochain</div><div class="font-bold">{{ bot.nextRun }}</div></div>
              </div>
            </div>
          </div>
        </template>
      </Card>

      <Card>
        <template #header><div class="px-4 pt-4 font-semibold">Journal d'exécution</div></template>
        <template #content>
          <div class="space-y-2 text-xs">
            <div v-for="log in executionLogs" :key="log.id" class="flex items-start gap-2 py-2 border-b border-surface-50">
              <span class="w-2 h-2 rounded-full mt-1 flex-shrink-0" :class="log.ok ? 'bg-green-400' : 'bg-red-400'"></span>
              <div>
                <div class="font-medium">{{ log.bot }}</div>
                <div class="text-surface-400">{{ log.ts }} · {{ log.duration }}</div>
                <div v-if="!log.ok" class="text-red-500 mt-0.5">{{ log.error }}</div>
              </div>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <Dialog v-model:visible="showCreateDialog" header="Créer un bot RPA" :style="{ width: '500px' }" modal>
      <div class="space-y-4">
        <div><label class="text-sm font-medium">Nom du bot</label><InputText v-model="newBot.name" class="w-full mt-1" /></div>
        <div><label class="text-sm font-medium">Type de tâche</label>
          <Select v-model="newBot.type" :options="['Extraction de données (scraping)', 'Synchronisation entre systèmes', 'Génération de rapport', 'Saisie automatique', 'Validation de données', 'Envoi d\'emails automatiques']" class="w-full mt-1" /></div>
        <div><label class="text-sm font-medium">Déclencheur</label>
          <Select v-model="newBot.trigger" :options="['Cron (planifié)', 'Événement (webhook)', 'Manuel', 'Condition de données']" class="w-full mt-1" /></div>
        <div v-if="newBot.trigger === 'Cron (planifié)'">
          <label class="text-sm font-medium">Planification (cron)</label>
          <InputText v-model="newBot.cron" class="w-full mt-1" placeholder="0 8 * * 1-5 (lun-ven à 8h)" />
        </div>
        <div><label class="text-sm font-medium">Description</label><Textarea v-model="newBot.description" rows="2" class="w-full mt-1" /></div>
      </div>
      <template #footer>
        <Button label="Annuler" text @click="showCreateDialog = false" />
        <Button label="Créer" @click="showCreateDialog = false" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'

const page = usePage()
const roles = computed(() => page.props.auth?.user?.roles?.map(r => r.name) || [])
const canManage = computed(() => roles.value.some(r => ['workflow-admin','admin','super-admin'].includes(r)))

const showCreateDialog = ref(false)
const newBot = ref({ name: '', type: null, trigger: 'Cron (planifié)', cron: '', description: '' })

const stats = [
  { label: 'Bots actifs', value: '8', color: 'text-green-600' },
  { label: 'Exécutions/jour', value: '142', color: 'text-blue-600' },
  { label: 'Heures économisées/mois', value: '284h', color: 'text-purple-600' },
  { label: 'Taux de succès', value: '97.2%', color: 'text-orange-600' },
]

const bots = ref([
  { id: 1, name: 'Rapport CA quotidien', description: 'Génère et envoie le rapport commercial chaque matin à 7h', status: 'Actif', enabled: true, runs: 124, success: 99, timeSaved: '2h/j', nextRun: 'Demain 7h' },
  { id: 2, name: 'Sync stock Jumia', description: 'Synchronise les stocks WideHalo → Jumia marketplace toutes les 30 min', status: 'En cours', enabled: true, runs: 2880, success: 98.2, timeSaved: '8h/j', nextRun: 'dans 12 min' },
  { id: 3, name: 'Relances factures impayées', description: 'Envoie des relances automatiques aux clients en retard (J+8, J+15, J+30)', status: 'Actif', enabled: true, runs: 89, success: 100, timeSaved: '3h/sem', nextRun: 'Lundi 9h' },
  { id: 4, name: 'Import relevés bancaires', description: 'Récupère et importe les relevés Ecobank/UBA chaque nuit', status: 'Erreur', enabled: true, runs: 180, success: 94.4, timeSaved: '1h/j', nextRun: 'Demain 2h' },
  { id: 5, name: 'Archivage documents 3 ans', description: 'Archive automatiquement les documents > 3 ans vers stockage froid', status: 'Désactivé', enabled: false, runs: 4, success: 100, timeSaved: '4h/trim', nextRun: '—' },
])

const executionLogs = ref([
  { id: 1, bot: 'Sync stock Jumia', ts: '14:30:00', duration: '8s', ok: true },
  { id: 2, bot: 'Rapport CA quotidien', ts: '07:00:01', duration: '45s', ok: true },
  { id: 3, bot: 'Import relevés bancaires', ts: '02:00:05', duration: '—', ok: false, error: 'Connexion API Ecobank timeout' },
  { id: 4, bot: 'Sync stock Jumia', ts: '14:00:00', duration: '9s', ok: true },
  { id: 5, bot: 'Relances factures', ts: '09:00:02', duration: '1m 12s', ok: true },
])
</script>
