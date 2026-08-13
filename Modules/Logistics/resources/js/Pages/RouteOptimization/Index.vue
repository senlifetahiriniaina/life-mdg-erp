<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">Optimisation des Tournées de Livraison</h1>
        <p class="text-surface-500 text-sm mt-1">IA de calcul d'itinéraires optimaux — moins de km, moins de coûts, plus de livraisons</p>
      </div>
      <div class="flex gap-2">
        <Button label="Optimiser les tournées" icon="pi pi-bolt" :loading="optimizing" @click="optimize" v-if="canManage" />
      </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <Card v-for="s in stats" :key="s.label"><template #content>
        <div class="text-2xl font-bold" :class="s.color">{{ s.value }}</div>
        <div class="text-sm text-surface-500 mt-1">{{ s.label }}</div>
      </template></Card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <Card class="lg:col-span-2">
        <template #header><div class="px-4 pt-4 flex items-center justify-between">
          <span class="font-semibold">Tournées du jour</span>
          <Tag :value="'Optimisé — ' + new Date().toLocaleDateString('fr-FR')" severity="success" />
        </div></template>
        <template #content>
          <div class="space-y-3">
            <div v-for="route in routes" :key="route.id" class="border rounded-lg p-3 cursor-pointer hover:border-blue-400" @click="selectedRoute = route; showRouteDrawer = true" role="button" tabindex="0" @keydown.enter.prevent="selectedRoute = route; showRouteDrawer = true">
              <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                  <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-bold" :style="{ background: route.color }">{{ route.driver.charAt(0) }}</div>
                  <div>
                    <div class="font-medium text-sm">{{ route.driver }}</div>
                    <div class="text-xs text-surface-500">{{ route.vehicle }} · {{ route.stops }} arrêts</div>
                  </div>
                </div>
                <div class="text-right">
                  <div class="text-sm font-bold">{{ route.km }} km</div>
                  <Tag :value="route.status" :severity="{ 'En cours': 'warn', Planifiée: 'info', Terminée: 'success' }[route.status]" size="small" />
                </div>
              </div>
              <div class="flex gap-4 text-xs text-surface-500">
                <span>⏱ {{ route.duration }}</span>
                <span>📦 {{ route.packages }} colis</span>
                <span>💰 {{ route.cost }} XOF</span>
                <span class="text-green-600 font-medium">↓ {{ route.saving }} vs non-optimisé</span>
              </div>
              <ProgressBar :value="route.progress" :style="{ height: '6px', marginTop: '8px' }" />
            </div>
          </div>
        </template>
      </Card>

      <Card>
        <template #header><div class="px-4 pt-4 font-semibold">Contraintes d'optimisation</div></template>
        <template #content>
          <div class="space-y-3 text-sm">
            <div v-for="c in constraints" :key="c.label" class="flex items-center justify-between py-2 border-b border-surface-100 last:border-0">
              <span>{{ c.label }}</span>
              <div class="flex items-center gap-2">
                <span class="font-medium text-xs">{{ c.value }}</span>
                <ToggleSwitch v-model="c.enabled" :disabled="!canManage" />
              </div>
            </div>
          </div>
          <div class="mt-4 p-3 bg-green-50 border border-green-200 rounded text-sm">
            <strong>Économies cumulées (mois):</strong><br>
            2 840 km évités · 180 000 XOF · -22% CO₂
          </div>
        </template>
      </Card>
    </div>

    <Card>
      <template #header><div class="px-4 pt-4 font-semibold">Suivi temps réel — GPS livraisons</div></template>
      <template #content>
        <DataTable :value="liveTracking" stripedRows>
          <Column field="driver" header="Livreur" />
          <Column field="currentStop" header="Arrêt actuel" />
          <Column field="nextStop" header="Prochain arrêt" />
          <Column field="eta" header="ETA" />
          <Column field="completed" header="Avancement">
            <template #body="{ data }"><ProgressBar :value="Math.round(data.completed / data.total * 100)" :style="{ height: '8px', width: '120px' }" /><span class="text-xs ml-2">{{ data.completed }}/{{ data.total }}</span></template>
          </Column>
          <Column field="status" header="Statut"><template #body="{ data }"><Tag :value="data.status" :severity="{ Livré: 'success', 'En route': 'warn', Retard: 'danger' }[data.status]" size="small" /></template></Column>
        </DataTable>
      </template>
    </Card>

    <Drawer v-model:visible="showRouteDrawer" :header="'Tournée — ' + selectedRoute?.driver" position="right" :style="{ width: '450px' }">
      <div v-if="selectedRoute" class="space-y-4">
        <div class="p-3 bg-surface-50 rounded space-y-1 text-sm">
          <div><strong>Véhicule:</strong> {{ selectedRoute.vehicle }}</div>
          <div><strong>Distance:</strong> {{ selectedRoute.km }} km</div>
          <div><strong>Durée estimée:</strong> {{ selectedRoute.duration }}</div>
          <div><strong>Coût carburant:</strong> {{ selectedRoute.cost }} XOF</div>
          <div class="text-green-600"><strong>Économie vs itinéraire manuel:</strong> {{ selectedRoute.saving }}</div>
        </div>
        <div>
          <div class="text-sm font-medium mb-2">Séquence des arrêts</div>
          <div class="space-y-2">
            <div v-for="(stop, i) in stopList" :key="i" class="flex items-center gap-3 text-sm">
              <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold" :class="stop.done ? 'bg-green-500 text-white' : 'bg-surface-200'">{{ i + 1 }}</div>
              <div class="flex-1">
                <div>{{ stop.address }}</div>
                <div class="text-xs text-surface-400">{{ stop.client }} · {{ stop.packages }} colis · ETA {{ stop.eta }}</div>
              </div>
              <Tag v-if="stop.done" value="✓" severity="success" size="small" />
            </div>
          </div>
        </div>
      </div>
    </Drawer>

    <!-- ── VRP Optimizer (2-opt) ───────────────────────────────────────── -->
    <Card>
      <template #header>
        <div class="px-4 pt-4 flex items-center justify-between">
          <div>
            <span class="font-semibold">Optimiseur VRP avancé — Algorithme 2-opt</span>
            <p class="text-xs text-surface-500 mt-0.5">
              Résout le Problème de Tournées de Véhicules (VRP) avec fenêtres horaires et contraintes de capacité.
              Algorithme : voisin le plus proche → amélioration 2-opt → vérification fenêtres.
            </p>
          </div>
          <Tag value="Nouveau" severity="info" size="small" />
        </div>
      </template>
      <template #content>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Input panel -->
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium mb-1">
                Arrêts (JSON)
                <span class="text-surface-400 font-normal ml-1">— tableau d'objets, max 200</span>
              </label>
              <Textarea
                v-model="vrpStopsJson"
                :rows="8"
                class="w-full font-mono text-xs"
                placeholder='[
  { "id": "s1", "lat": 14.7167, "lng": -17.4677, "time_window_open": "09:00", "time_window_close": "12:00", "service_time_minutes": 15, "demand": 50 },
  { "id": "s2", "lat": 14.6937, "lng": -17.4441, "time_window_open": null, "time_window_close": null, "service_time_minutes": 10, "demand": 30 }
]'
              />
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">
                Véhicules (JSON)
                <span class="text-surface-400 font-normal ml-1">— capacité en kg, start_lat/lng = dépôt</span>
              </label>
              <Textarea
                v-model="vrpVehiclesJson"
                :rows="5"
                class="w-full font-mono text-xs"
                placeholder='[
  { "id": "v1", "capacity": 500, "start_lat": 14.7167, "start_lng": -17.4677, "max_stops": 20 }
]'
              />
            </div>

            <div class="p-3 bg-blue-50 border border-blue-200 rounded text-xs space-y-1">
              <div class="font-medium text-blue-800">Format attendu — champs optionnels marqués *</div>
              <div><strong>stop :</strong> id, lat, lng | time_window_open*, time_window_close* ("HH:MM"), service_time_minutes* (défaut 10), demand* (défaut 0)</div>
              <div><strong>vehicle :</strong> id, capacity, start_lat, start_lng | max_stops* (défaut 50)</div>
            </div>

            <div class="flex gap-2">
              <Button
                label="Lancer l'optimisation VRP"
                icon="pi pi-bolt"
                :loading="vrpLoading"
                :disabled="!canManage"
                @click="runVrp"
                class="flex-1"
              />
              <Button
                label="Exemple"
                icon="pi pi-file-edit"
                severity="secondary"
                outlined
                @click="loadVrpExample"
              />
              <Button
                v-if="vrpResult || vrpError"
                icon="pi pi-refresh"
                severity="secondary"
                outlined
                @click="resetVrp"
                v-tooltip="'Réinitialiser'"
              />
            </div>

            <div v-if="vrpError" class="p-3 bg-red-50 border border-red-200 rounded text-sm text-red-700">
              <strong>Erreur :</strong> {{ vrpError }}
            </div>
            <div v-if="vrpJobId && vrpStatus === 'queued'" class="p-3 bg-yellow-50 border border-yellow-200 rounded text-sm">
              <div class="font-medium">Tâche en file d'attente</div>
              <div class="text-xs text-surface-500 mt-1">Job ID : {{ vrpJobId }}</div>
              <div class="text-xs text-surface-500">Interrogation toutes les 3 s…</div>
            </div>
          </div>

          <!-- Results panel -->
          <div class="space-y-4">
            <template v-if="vrpResult">
              <!-- Summary -->
              <div class="grid grid-cols-2 gap-3">
                <div class="p-3 bg-surface-50 rounded text-center">
                  <div class="text-xl font-bold text-blue-600">{{ vrpResult.result.total_distance_km }} km</div>
                  <div class="text-xs text-surface-500">Distance totale</div>
                </div>
                <div class="p-3 bg-surface-50 rounded text-center">
                  <div class="text-xl font-bold text-purple-600">{{ vrpResult.result.solver_info.stops_served }}</div>
                  <div class="text-xs text-surface-500">Arrêts servis</div>
                </div>
                <div class="p-3 bg-surface-50 rounded text-center">
                  <div class="text-xl font-bold" :class="vrpResult.result.solver_info.stops_unserved > 0 ? 'text-red-600' : 'text-green-600'">
                    {{ vrpResult.result.solver_info.stops_unserved }}
                  </div>
                  <div class="text-xs text-surface-500">Non servis</div>
                </div>
                <div class="p-3 bg-surface-50 rounded text-center">
                  <div class="text-xl font-bold text-surface-700">{{ vrpResult.result.routes.length }}</div>
                  <div class="text-xs text-surface-500">Véhicules utilisés</div>
                </div>
              </div>

              <!-- Per-vehicle routes -->
              <div v-for="(route, ri) in vrpResult.result.routes" :key="route.vehicle_id" class="border rounded-lg overflow-hidden">
                <div class="flex items-center justify-between px-3 py-2 bg-surface-50 border-b">
                  <span class="font-medium text-sm">Véhicule {{ route.vehicle_id }}</span>
                  <div class="flex items-center gap-2">
                    <Tag
                      :value="route.feasible ? 'Faisable' : 'Violations fenêtres'"
                      :severity="route.feasible ? 'success' : 'danger'"
                      size="small"
                    />
                    <span class="text-xs text-surface-500">{{ route.total_distance_km }} km · {{ route.total_time_min }} min</span>
                  </div>
                </div>
                <div class="divide-y text-xs">
                  <div
                    v-for="(stop, si) in route.stops"
                    :key="stop.id"
                    class="flex items-center gap-3 px-3 py-2"
                  >
                    <div class="w-5 h-5 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-bold shrink-0">
                      {{ si + 1 }}
                    </div>
                    <div class="flex-1 min-w-0">
                      <span class="font-mono text-surface-700">{{ stop.id }}</span>
                      <span class="text-surface-400 ml-2">({{ stop.lat }}, {{ stop.lng }})</span>
                    </div>
                    <div v-if="stop.time_window_open || stop.time_window_close" class="text-surface-500 shrink-0">
                      {{ stop.time_window_open || '--' }} – {{ stop.time_window_close || '--' }}
                    </div>
                    <div v-if="route.arrival_times && route.arrival_times[stop.id] !== undefined" class="text-surface-400 shrink-0">
                      arr. {{ minutesToTime(route.arrival_times[stop.id]) }}
                    </div>
                  </div>
                </div>
                <div v-if="!route.feasible" class="px-3 py-2 bg-red-50 text-xs text-red-700">
                  {{ route.tw_violations }} violation(s) de fenêtre horaire — pénalité {{ route.tw_penalty_min }} min
                </div>
              </div>

              <!-- Unserved stops -->
              <div v-if="vrpResult.result.unserved.length > 0" class="border border-orange-200 rounded-lg overflow-hidden">
                <div class="px-3 py-2 bg-orange-50 font-medium text-sm text-orange-800 border-b border-orange-200">
                  Arrêts non assignés (capacité dépassée ou max_stops atteint)
                </div>
                <div class="divide-y text-xs">
                  <div v-for="stop in vrpResult.result.unserved" :key="stop.id" class="flex items-center gap-3 px-3 py-2">
                    <span class="font-mono text-surface-700">{{ stop.id }}</span>
                    <span class="text-surface-400">({{ stop.lat }}, {{ stop.lng }})</span>
                    <span v-if="stop.demand" class="text-orange-600">demande: {{ stop.demand }}</span>
                  </div>
                </div>
              </div>

              <!-- Solver info -->
              <div class="p-2 bg-surface-50 rounded text-xs text-surface-500 space-y-0.5">
                <div><strong>Algorithme :</strong> {{ vrpResult.result.solver_info.algorithm }}</div>
                <div><strong>Entrée :</strong> {{ vrpResult.result.solver_info.stops_input }} arrêts · {{ vrpResult.result.solver_info.vehicles_input }} véhicules</div>
              </div>
            </template>

            <div v-else-if="!vrpLoading" class="flex flex-col items-center justify-center py-12 text-surface-400 text-sm">
              <i class="pi pi-map text-3xl mb-3 opacity-30"></i>
              <span>Saisissez les arrêts et véhicules puis lancez l'optimisation</span>
            </div>
          </div>
        </div>
      </template>
    </Card>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'
import Card from 'primevue/card'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import ProgressBar from 'primevue/progressbar'
import Drawer from 'primevue/drawer'
import ToggleSwitch from 'primevue/toggleswitch'
import Textarea from 'primevue/textarea'

const page = usePage()
const roles = computed(() => page.props.auth?.user?.roles?.map(r => r.name) || [])
const canManage = computed(() => roles.value.some(r => ['logistics-manager', 'admin', 'super-admin'].includes(r)))

const optimizing = ref(false)
const showRouteDrawer = ref(false)
const selectedRoute = ref(null)
const optimize = async () => { optimizing.value = true; await new Promise(r => setTimeout(r, 2500)); optimizing.value = false }

// ── VRP Optimizer state ─────────────────────────────────────────────────────
const vrpStopsJson   = ref('')
const vrpVehiclesJson = ref('')
const vrpLoading     = ref(false)
const vrpStatus      = ref(null)   // 'completed' | 'queued' | null
const vrpJobId       = ref(null)
const vrpResult      = ref(null)
const vrpError       = ref(null)
let   vrpPollTimer   = null

/** Convert minutes-since-midnight to "HH:MM" display string */
const minutesToTime = (minutes) => {
  const h = Math.floor(minutes / 60).toString().padStart(2, '0')
  const m = (minutes % 60).toString().padStart(2, '0')
  return `${h}:${m}`
}

const resetVrp = () => {
  vrpResult.value  = null
  vrpError.value   = null
  vrpStatus.value  = null
  vrpJobId.value   = null
  if (vrpPollTimer) { clearInterval(vrpPollTimer); vrpPollTimer = null }
}

const loadVrpExample = () => {
  vrpStopsJson.value = JSON.stringify([
    { id: 's1', lat: 14.7167, lng: -17.4677, time_window_open: '09:00', time_window_close: '12:00', service_time_minutes: 15, demand: 50 },
    { id: 's2', lat: 14.6937, lng: -17.4441, time_window_open: null,    time_window_close: null,    service_time_minutes: 10, demand: 30 },
    { id: 's3', lat: 14.7340, lng: -17.4500, time_window_open: '10:00', time_window_close: '14:00', service_time_minutes: 20, demand: 80 },
    { id: 's4', lat: 14.6800, lng: -17.4300, time_window_open: null,    time_window_close: null,    service_time_minutes: 10, demand: 40 },
    { id: 's5', lat: 14.7500, lng: -17.3900, time_window_open: '08:00', time_window_close: '11:00', service_time_minutes: 15, demand: 60 },
  ], null, 2)
  vrpVehiclesJson.value = JSON.stringify([
    { id: 'v1', capacity: 200, start_lat: 14.7167, start_lng: -17.4677, max_stops: 10 },
    { id: 'v2', capacity: 200, start_lat: 14.7167, start_lng: -17.4677, max_stops: 10 },
  ], null, 2)
}

const pollVrpResult = (jobId) => {
  vrpPollTimer = setInterval(async () => {
    try {
      const { data } = await axios.get(`/api/v1/logistics/routes/optimize/${jobId}/result`)
      if (data.status === 'completed') {
        clearInterval(vrpPollTimer)
        vrpPollTimer   = null
        vrpStatus.value = 'completed'
        vrpResult.value = data
        vrpLoading.value = false
      }
    } catch (err) {
      clearInterval(vrpPollTimer)
      vrpPollTimer     = null
      vrpError.value   = 'Erreur lors de la récupération du résultat.'
      vrpLoading.value = false
    }
  }, 3000)
}

const runVrp = async () => {
  resetVrp()
  vrpError.value = null

  let stops, vehicles
  try {
    stops    = JSON.parse(vrpStopsJson.value || '[]')
    vehicles = JSON.parse(vrpVehiclesJson.value || '[]')
  } catch {
    vrpError.value = 'JSON invalide — vérifiez le format des arrêts ou véhicules.'
    return
  }

  if (!stops.length || !vehicles.length) {
    vrpError.value = 'Veuillez fournir au moins un arrêt et un véhicule.'
    return
  }

  vrpLoading.value = true

  try {
    const { data } = await axios.post('/api/v1/logistics/routes/optimize', { stops, vehicles })

    if (data.status === 'completed') {
      vrpStatus.value  = 'completed'
      vrpResult.value  = data
      vrpLoading.value = false
    } else if (data.status === 'queued') {
      vrpStatus.value = 'queued'
      vrpJobId.value  = data.job_id
      pollVrpResult(data.job_id)
    }
  } catch (err) {
    vrpError.value   = err?.response?.data?.message || err?.response?.data?.error || 'Erreur lors de l\'optimisation.'
    vrpLoading.value = false
  }
}

const stats = [
  { label: 'Tournées optimisées', value: '6', color: 'text-blue-600' },
  { label: 'Km économisés/jour', value: '94 km', color: 'text-green-600' },
  { label: 'Livraisons planifiées', value: '128', color: 'text-purple-600' },
  { label: 'Taux de livraison', value: '96.5%', color: 'text-orange-600' },
]

const routes = ref([
  { id: 1, driver: 'Ousmane Diallo', vehicle: 'Camion — DK-4521', stops: 12, km: '78 km', duration: '6h30', packages: 32, cost: '14 200', saving: '22 km · 12%', status: 'En cours', progress: 58, color: '#3b82f6' },
  { id: 2, driver: 'Awa Mbaye', vehicle: 'Moto — DK-1234', stops: 18, km: '42 km', duration: '4h00', packages: 18, cost: '6 800', saving: '8 km · 16%', status: 'En cours', progress: 33, color: '#8b5cf6' },
  { id: 3, driver: 'Kofi Atta', vehicle: 'Camionette — DK-7890', stops: 8, km: '55 km', duration: '5h00', packages: 24, cost: '9 500', saving: '15 km · 21%', status: 'Planifiée', progress: 0, color: '#f59e0b' },
  { id: 4, driver: 'Fatimata Traoré', vehicle: 'Moto — DK-5678', stops: 22, km: '38 km', duration: '3h30', packages: 22, cost: '5 200', saving: '6 km · 14%', status: 'Terminée', progress: 100, color: '#10b981' },
])

const constraints = ref([
  { label: 'Fenêtres horaires client', value: '8h-18h', enabled: true },
  { label: 'Capacité max véhicule', value: '500 kg', enabled: true },
  { label: 'Trafic temps réel', value: 'OpenStreetMap', enabled: true },
  { label: 'Priorité commandes urgentes', value: 'Actif', enabled: true },
  { label: 'Écologie (CO₂ min)', value: 'Option', enabled: false },
])

const liveTracking = ref([
  { driver: 'Ousmane Diallo', currentStop: '12 Av. Bourguiba, Dakar', nextStop: 'Rue 22 × 25, Fann', eta: '14:45', completed: 7, total: 12, status: 'En route' },
  { driver: 'Awa Mbaye', currentStop: 'Marché Tilène', nextStop: 'Pikine Technopole', eta: '15:10', completed: 6, total: 18, status: 'En route' },
  { driver: 'Fatimata Traoré', currentStop: 'Dépôt central', nextStop: '—', eta: '—', completed: 22, total: 22, status: 'Livré' },
])

const stopList = [
  { address: '12 Av. Bourguiba, Dakar', client: 'Groupe Sonatel', packages: 4, eta: '13:30', done: true },
  { address: 'Rue 22 × 25, Fann', client: 'Pharmacie Fann', packages: 2, eta: '14:00', done: true },
  { address: 'Marché Sandaga, Stand 142', client: 'Diallo Trading', packages: 6, eta: '14:45', done: false },
  { address: 'Bd de la Gueule Tapée', client: 'Restaurant Teranga', packages: 3, eta: '15:15', done: false },
]
</script>
