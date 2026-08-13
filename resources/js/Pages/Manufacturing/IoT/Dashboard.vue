<template>
  <AppLayout>
    <Head :title="$t('manufacturing.iot.title')" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">{{ $t('manufacturing.iot.title') }}</h1>
        <p class="wh-page-subtitle">{{ devices.length }} {{ $t('manufacturing.iot.devices_count') }}</p>
      </div>
    </div>

    <!-- Alert: offline devices -->
    <div v-if="offlineDevices.length" class="alert alert-warning">
      <i class="pi pi-exclamation-triangle" />
      {{ offlineDevices.length }} {{ $t('manufacturing.iot.devices_offline') }}:
      {{ offlineDevices.map(d => d.name).join(', ') }}
    </div>

    <!-- Device grid -->
    <div class="device-grid">
      <div v-for="device in devices" :key="device.id" class="device-card" @click="selectDevice(device)">
        <div class="device-header">
          <span class="status-led" :class="device.status === 'online' ? 'led-green' : (device.status === 'error' ? 'led-red' : 'led-grey')" />
          <span class="device-name">{{ device.name }}</span>
          <span class="badge badge-muted device-type">{{ device.type }}</span>
        </div>
        <div class="device-meta">
          <span class="text-muted">{{ device.workcenter?.name ?? $t('manufacturing.iot.no_workcenter') }}</span>
          <span class="device-last-seen">{{ formatLastSeen(device.last_seen_at) }}</span>
        </div>
        <div v-if="device.status === 'offline'" class="device-offline-badge">
          {{ $t('manufacturing.iot.offline') }}
        </div>
      </div>
    </div>

    <!-- Device detail drawer with chart -->
    <Drawer v-model:visible="drawerVisible" position="right" style="width:600px">
      <template #header>
        <div class="flex items-center gap-2">
          <span class="status-led" :class="selectedDevice?.status === 'online' ? 'led-green' : 'led-grey'" />
          <span class="font-semibold">{{ selectedDevice?.name }}</span>
        </div>
      </template>
      <div v-if="selectedDevice" class="drawer-body">
        <div class="detail-grid mb-4">
          <div class="detail-row">
            <span class="detail-label">{{ $t('manufacturing.iot.device_id') }}</span>
            <code>{{ selectedDevice.device_id }}</code>
          </div>
          <div class="detail-row">
            <span class="detail-label">{{ $t('manufacturing.iot.type') }}</span>
            <span>{{ selectedDevice.type }}</span>
          </div>
          <div class="detail-row">
            <span class="detail-label">{{ $t('manufacturing.iot.last_seen') }}</span>
            <span>{{ formatLastSeen(selectedDevice.last_seen_at) }}</span>
          </div>
        </div>

        <!-- Metric selector + chart -->
        <div class="chart-controls">
          <InputText v-model="metricName" :placeholder="$t('manufacturing.iot.metric_placeholder')" class="filter-input" style="flex:1" />
          <button class="btn btn-primary" @click="loadMetrics">
            <i class="pi pi-chart-line" /> {{ $t('manufacturing.iot.load_chart') }}
          </button>
        </div>

        <div v-if="loadingMetrics" class="text-muted mt-2">{{ $t('common.loading') }}…</div>
        <div v-else-if="chartSeries[0]?.data?.length" class="mt-4">
          <apexchart
            type="line"
            height="250"
            :options="chartOptions"
            :series="chartSeries"
          />
        </div>
        <div v-else-if="metricLoaded" class="text-muted mt-2">{{ $t('common.no_results') }}</div>
      </div>
    </Drawer>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Drawer, InputText } from 'primevue'
import axios from 'axios'

interface IoTDevice {
  id: number
  name: string
  device_id: string
  type: string
  status: string
  last_seen_at: string | null
  workcenter: { id: number; name: string } | null
  metadata: Record<string, unknown> | null
}

const props = defineProps<{ devices: IoTDevice[] }>()

const offlineDevices = computed(() => {
  return props.devices.filter(d => {
    if (d.status !== 'online') return false
    if (!d.last_seen_at) return true
    const mins = (Date.now() - new Date(d.last_seen_at).getTime()) / 60000
    return mins > 5
  })
})

const drawerVisible = ref(false)
const selectedDevice = ref<IoTDevice | null>(null)
const metricName = ref('')
const loadingMetrics = ref(false)
const metricLoaded = ref(false)

const chartSeries = ref<{ name: string; data: [number, number][] }[]>([{ name: '', data: [] }])
const chartOptions = ref({
  chart: { type: 'line', toolbar: { show: false } },
  xaxis: { type: 'datetime', labels: { datetimeUTC: false } },
  stroke: { curve: 'smooth', width: 2 },
  tooltip: { x: { format: 'HH:mm dd MMM' } },
})

function selectDevice(device: IoTDevice) {
  selectedDevice.value = device
  drawerVisible.value = true
  metricName.value = ''
  chartSeries.value = [{ name: '', data: [] }]
  metricLoaded.value = false
}

async function loadMetrics() {
  if (!selectedDevice.value || !metricName.value) return
  loadingMetrics.value = true
  metricLoaded.value = false
  try {
    const { data } = await axios.get(`/api/v1/manufacturing/iot/devices/${selectedDevice.value.id}/metrics`, {
      params: { metric: metricName.value, hours: 24 },
    })
    const series = (data.data as { recorded_at: string; value: number }[]).map(r => [
      new Date(r.recorded_at).getTime(),
      Number(r.value),
    ] as [number, number])
    chartSeries.value = [{ name: metricName.value, data: series }]
    metricLoaded.value = true
  } finally {
    loadingMetrics.value = false
  }
}

function formatLastSeen(dt: string | null): string {
  if (!dt) return '—'
  const d = new Date(dt)
  const mins = Math.floor((Date.now() - d.getTime()) / 60000)
  if (mins < 1) return 'just now'
  if (mins < 60) return `${mins}m ago`
  return d.toLocaleString()
}
</script>

<style scoped>
.device-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; margin-top: 16px; }
.device-card {
  background: var(--surface-1);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 16px;
  cursor: pointer;
  transition: box-shadow 0.15s;
}
.device-card:hover { box-shadow: 0 2px 12px rgba(0,0,0,.12); }
.device-header { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.device-name { font-weight: 600; flex: 1; }
.device-type { font-size: 11px; }
.device-meta { display: flex; justify-content: space-between; font-size: 12px; color: var(--fg-2); }
.device-last-seen { font-size: 11px; }
.device-offline-badge { margin-top: 8px; background: #fee2e2; color: #dc2626; border-radius: 6px; padding: 2px 8px; font-size: 12px; display: inline-block; }

.status-led { display: inline-block; width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.led-green { background: #22c55e; box-shadow: 0 0 6px #22c55e; }
.led-red { background: #ef4444; box-shadow: 0 0 6px #ef4444; }
.led-grey { background: #9ca3af; }

.detail-grid { display: flex; flex-direction: column; gap: 8px; }
.detail-row { display: flex; gap: 12px; }
.detail-label { min-width: 110px; color: var(--fg-2); font-size: 13px; }

.chart-controls { display: flex; gap: 8px; margin-top: 8px; }
.alert { border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; }
.alert-warning { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
</style>
