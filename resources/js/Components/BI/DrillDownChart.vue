<template>
  <div class="wh-drilldown-chart">
    <!-- Breadcrumb -->
    <div v-if="breadcrumbs.length > 0" style="display:flex;align-items:center;gap:6px;margin-bottom:12px;font-size:13px">
      <button
        class="btn btn-ghost btn-sm"
        @click="resetDrill"
        style="padding:2px 8px"
      >
        <i class="pi pi-home" style="font-size:11px" />
      </button>
      <template v-for="(crumb, idx) in breadcrumbs" :key="idx">
        <i class="pi pi-chevron-right" style="font-size:10px;color:var(--fg-3)" />
        <button
          class="btn btn-ghost btn-sm"
          style="padding:2px 8px"
          @click="drillBack(idx)"
        >
          {{ crumb.label }}
        </button>
      </template>
    </div>

    <!-- Chart area -->
    <div v-if="loading" style="padding:40px;text-align:center;color:var(--fg-3)">
      <i class="pi pi-spin pi-spinner" style="font-size:24px" />
    </div>

    <div v-else-if="currentData.length > 0">
      <!-- Available dimensions -->
      <div v-if="dimensions.length > 0" style="display:flex;gap:6px;margin-bottom:10px;flex-wrap:wrap">
        <span style="font-size:12px;color:var(--fg-3);align-self:center">Drill par :</span>
        <button
          v-for="dim in dimensions"
          :key="dim"
          class="wh-badge wh-badge-info"
          style="cursor:pointer;border:none;background:var(--halo-100);color:var(--halo-700);padding:3px 10px;border-radius:12px;font-size:12px"
          @click="activeDimension = dim"
          :style="activeDimension === dim ? 'background:var(--halo-500);color:#fff' : ''"
        >
          {{ dim }}
        </button>
      </div>

      <!-- Simple bar chart using CSS bars -->
      <div style="overflow-x:auto">
        <table class="wh-dt" style="font-size:12px">
          <thead>
            <tr>
              <th v-for="col in columns" :key="col">{{ col }}</th>
              <th v-if="activeDimension">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(row, idx) in currentData"
              :key="idx"
              class="wh-dt-row"
              style="cursor:pointer"
              @click="activeDimension ? drillInto(row) : undefined"
            >
              <td v-for="col in columns" :key="col">{{ row[col] ?? '' }}</td>
              <td v-if="activeDimension">
                <button class="wh-badge wh-badge-info" style="border:none;cursor:pointer" @click.stop="drillInto(row)">
                  Drill <i class="pi pi-arrow-right" style="font-size:10px" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-else style="padding:32px;text-align:center;color:var(--fg-3);font-size:13px">
      Aucune donnée disponible.
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import axios from 'axios'

interface DrillRow {
  [key: string]: unknown
}

interface DrillResult {
  data: DrillRow[]
  dimension: string | null
  value: unknown
  total: number
}

interface Breadcrumb {
  label: string
  dimension: string
  value: unknown
  data: DrillRow[]
}

const props = defineProps<{
  widgetId: number
  initialData?: DrillRow[]
}>()

const emit = defineEmits<{
  drill: [dimension: string, value: unknown]
}>()

const loading = ref(false)
const currentData = ref<DrillRow[]>(props.initialData ?? [])
const dimensions = ref<string[]>([])
const activeDimension = ref<string | null>(null)
const breadcrumbs = ref<Breadcrumb[]>([])

const columns = computed(() => {
  if (currentData.value.length === 0) return []
  return Object.keys(currentData.value[0])
})

async function loadDimensions() {
  try {
    const { data } = await axios.post(`/api/v1/bi/widgets/${props.widgetId}/drill`, {})
    dimensions.value = data.dimensions ?? []
    if (currentData.value.length === 0) {
      currentData.value = data.result?.data ?? []
    }
  } catch {
    // noop
  }
}

async function drillInto(row: DrillRow) {
  if (!activeDimension.value) return

  const value = row[activeDimension.value]

  breadcrumbs.value.push({
    label: `${activeDimension.value}: ${String(value)}`,
    dimension: activeDimension.value,
    value,
    data: [...currentData.value],
  })

  loading.value = true
  emit('drill', activeDimension.value, value)

  try {
    const { data } = await axios.post(`/api/v1/bi/widgets/${props.widgetId}/drill`, {
      dimension: activeDimension.value,
      value,
    })
    const result: DrillResult = data.result
    currentData.value = result.data
  } catch {
    currentData.value = []
  } finally {
    loading.value = false
  }
}

function drillBack(idx: number) {
  const crumb = breadcrumbs.value[idx]
  if (!crumb) return
  currentData.value = crumb.data
  breadcrumbs.value = breadcrumbs.value.slice(0, idx)
}

function resetDrill() {
  if (breadcrumbs.value.length === 0) return
  currentData.value = breadcrumbs.value[0]?.data ?? []
  breadcrumbs.value = []
}

onMounted(loadDimensions)
</script>
