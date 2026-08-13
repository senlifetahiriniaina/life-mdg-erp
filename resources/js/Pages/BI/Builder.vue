<template>
  <AppLayout>
    <Head :title="dashboardId ? `Builder · ${formName}` : 'Nouveau Dashboard'" />

    <!-- Top bar -->
    <div class="builder-topbar">
      <div style="display:flex;align-items:center;gap:10px">
        <a href="/bi" class="back-link"><i class="pi pi-arrow-left" style="font-size:13px" /></a>
        <div>
          <input v-model="formName" class="builder-title-input" placeholder="Nom du dashboard…" />
          <p v-if="lastSaved" class="builder-saved-hint">
            <i class="pi pi-check-circle" style="font-size:11px" /> Sauvegardé {{ lastSaved }}
          </p>
        </div>
      </div>
      <div class="builder-topbar__actions">
        <button class="btn btn-secondary" @click="previewMode = !previewMode">
          <i :class="previewMode ? 'pi pi-pencil' : 'pi pi-eye'" style="font-size:13px" />
          {{ previewMode ? 'Éditer' : 'Aperçu' }}
        </button>
        <button class="btn btn-secondary" @click="clearCanvas" :disabled="!canvasWidgets.length">
          <i class="pi pi-trash" style="font-size:13px" />
          Vider
        </button>
        <button class="btn btn-primary" @click="save" :disabled="saving || !formName.trim()">
          <i :class="saving ? 'pi pi-spin pi-spinner' : 'pi pi-save'" style="font-size:13px" />
          {{ saving ? 'Sauvegarde…' : 'Sauvegarder' }}
        </button>
      </div>
    </div>

    <div class="builder-layout">
      <!-- LEFT: Widget palette -->
      <aside class="builder-sidebar builder-sidebar--left" v-show="!previewMode">
        <div class="sidebar-section-label">Widgets disponibles</div>

        <div class="palette">
          <div
            v-for="wt in widgetTypes"
            :key="wt.type"
            class="palette-item"
            draggable="true"
            @dragstart="onPaletteDragStart($event, wt)"
          >
            <div class="palette-item__icon" :style="{ background: wt.bg, color: wt.color }">
              <i :class="wt.icon" />
            </div>
            <div>
              <p class="palette-item__name">{{ wt.label }}</p>
              <p class="palette-item__desc">{{ wt.desc }}</p>
            </div>
            <i class="pi pi-grip-vertical palette-item__grip" />
          </div>
        </div>

        <div class="sidebar-section-label" style="margin-top:20px">Avancé</div>
        <div class="palette">
          <div
            v-for="wt in advancedWidgetTypes"
            :key="wt.type"
            class="palette-item"
            draggable="true"
            @dragstart="onPaletteDragStart($event, wt)"
          >
            <div class="palette-item__icon" :style="{ background: wt.bg, color: wt.color }">
              <i :class="wt.icon" />
            </div>
            <div>
              <p class="palette-item__name">{{ wt.label }}</p>
              <p class="palette-item__desc">{{ wt.desc }}</p>
            </div>
            <i class="pi pi-grip-vertical palette-item__grip" />
          </div>
        </div>

        <div class="sidebar-section-label" style="margin-top:20px">Sources de données</div>
        <div class="palette">
          <div
            v-for="ds in dataSources"
            :key="ds.id"
            class="ds-item"
          >
            <i class="pi pi-database" style="font-size:13px;color:var(--fg-3)" />
            <span class="ds-item__name">{{ ds.name }}</span>
            <span class="ds-item__type">{{ ds.type }}</span>
          </div>
          <div v-if="!dataSources.length" class="ds-empty">
            <a href="/bi/data-sources" style="font-size:12px;color:var(--halo-600)">Connecter une source</a>
          </div>
        </div>
      </aside>

      <!-- CENTER: Canvas -->
      <main
        class="builder-canvas"
        :class="{ 'builder-canvas--preview': previewMode, 'builder-canvas--dragging': isDraggingOver }"
        @dragover.prevent="onCanvasDragOver"
        @dragleave="onCanvasDragLeave"
        @drop.prevent="onCanvasDrop"
      >
        <!-- Drop zone hint -->
        <div v-if="!canvasWidgets.length && !previewMode" class="canvas-empty">
          <i class="pi pi-arrows-alt" style="font-size:36px;color:var(--halo-300);margin-bottom:12px" />
          <p style="font-size:15px;font-weight:500;color:var(--fg-2);margin:0 0 6px">Glissez un widget ici</p>
          <p style="font-size:13px;color:var(--fg-3);margin:0">Sélectionnez un type dans la palette de gauche et déposez-le sur le canvas.</p>
        </div>

        <!-- Placed widgets grid -->
        <div v-else class="canvas-grid">
          <div
            v-for="(widget, idx) in canvasWidgets"
            :key="widget._key"
            class="canvas-widget"
            :class="{
              'canvas-widget--selected': selectedWidget && selectedWidget._key === widget._key,
              'canvas-widget--preview': previewMode,
            }"
            :style="canvasWidgetStyle(widget)"
            @click="!previewMode && selectWidget(widget)"
            draggable="true"
            @dragstart="onWidgetDragStart($event, idx)"
            @dragover.prevent="onWidgetDragOver($event, idx)"
            @drop.prevent="onWidgetDrop($event, idx)"
          >
            <!-- Widget header -->
            <div class="canvas-widget__head" v-if="!previewMode">
              <i :class="widgetIcon(widget.type)" style="font-size:13px;color:var(--halo-500)" />
              <span class="canvas-widget__title">{{ widget.title }}</span>
              <div class="canvas-widget__tools" @click.stop>
                <button class="wh-icon-btn" title="Monter" @click="moveWidget(idx, -1)" :disabled="idx === 0">
                  <i class="pi pi-chevron-up" style="font-size:11px" />
                </button>
                <button class="wh-icon-btn" title="Descendre" @click="moveWidget(idx, 1)" :disabled="idx === canvasWidgets.length - 1">
                  <i class="pi pi-chevron-down" style="font-size:11px" />
                </button>
                <button class="wh-icon-btn wh-icon-btn--danger" title="Supprimer" @click="removeWidget(idx)">
                  <i class="pi pi-times" style="font-size:11px" />
                </button>
              </div>
            </div>

            <!-- Widget body preview -->
            <div class="canvas-widget__body">
              <WidgetPreview :widget="widget" />
            </div>

            <!-- Resize handle -->
            <div
              v-if="!previewMode"
              class="resize-handle resize-handle--e"
              @mousedown.stop="startResize($event, idx, 'w')"
              title="Redimensionner largeur"
            />
            <div
              v-if="!previewMode"
              class="resize-handle resize-handle--s"
              @mousedown.stop="startResize($event, idx, 'h')"
              title="Redimensionner hauteur"
            />
          </div>
        </div>

        <!-- Drag-over overlay -->
        <div v-if="isDraggingOver && !previewMode" class="canvas-drop-overlay">
          <i class="pi pi-plus-circle" style="font-size:24px" />
          <span>Déposer ici</span>
        </div>
      </main>

      <!-- RIGHT: Properties panel -->
      <aside class="builder-sidebar builder-sidebar--right" v-show="!previewMode">
        <template v-if="selectedWidget">
          <div class="sidebar-section-label">Propriétés du widget</div>

          <!-- Title -->
          <div class="prop-group">
            <label class="prop-label">Titre</label>
            <input v-model="selectedWidget.title" class="prop-input" placeholder="Titre…" />
          </div>

          <!-- Type (read-only) -->
          <div class="prop-group">
            <label class="prop-label">Type</label>
            <div class="prop-badge">
              <i :class="widgetIcon(selectedWidget.type)" style="font-size:12px" />
              {{ widgetTypeLabel(selectedWidget.type) }}
            </div>
          </div>

          <!-- Width (columns) -->
          <div class="prop-group">
            <label class="prop-label">Largeur (colonnes 1–12)</label>
            <div class="prop-range-row">
              <input
                type="range" min="1" max="12" step="1"
                v-model.number="selectedWidget.w"
                class="prop-range"
              />
              <span class="prop-range-val">{{ selectedWidget.w }}</span>
            </div>
          </div>

          <!-- Height (rows) -->
          <div class="prop-group">
            <label class="prop-label">Hauteur</label>
            <div class="prop-range-row">
              <input
                type="range" min="1" max="4" step="1"
                v-model.number="selectedWidget.h"
                class="prop-range"
              />
              <span class="prop-range-val">{{ selectedWidget.h }}x</span>
            </div>
          </div>

          <!-- Data source -->
          <div class="prop-group">
            <label class="prop-label">Source de données</label>
            <select v-model="selectedWidget.dataSource" class="prop-select">
              <option value="">— Aucune —</option>
              <option v-for="ds in dataSources" :key="ds.id" :value="ds.id">{{ ds.name }}</option>
              <option value="internal">Données internes ERP</option>
            </select>
          </div>

          <!-- Metric (for KPI / gauge) -->
          <div v-if="['kpi_card', 'metric_gauge'].includes(selectedWidget.type)" class="prop-group">
            <label class="prop-label">Métrique</label>
            <select v-model="selectedWidget.config.metric" class="prop-select">
              <option value="">— Sélectionner —</option>
              <option v-for="m in availableMetrics" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
          </div>

          <!-- Unit -->
          <div v-if="['kpi_card', 'metric_gauge'].includes(selectedWidget.type)" class="prop-group">
            <label class="prop-label">Unité</label>
            <input v-model="selectedWidget.config.unit" class="prop-input" placeholder="ex. €, %, items…" />
          </div>

          <!-- Alert threshold -->
          <div v-if="selectedWidget.type === 'kpi_card'" class="prop-group">
            <label class="prop-label">Seuil d'alerte</label>
            <input v-model.number="selectedWidget.config.threshold" type="number" class="prop-input" placeholder="ex. 1000" />
          </div>

          <!-- Period -->
          <div class="prop-group">
            <label class="prop-label">Période</label>
            <select v-model="selectedWidget.config.period" class="prop-select">
              <option value="today">Aujourd'hui</option>
              <option value="week">Cette semaine</option>
              <option value="month">Ce mois</option>
              <option value="quarter">Ce trimestre</option>
              <option value="year">Cette année</option>
              <option value="custom">Personnalisée</option>
            </select>
          </div>

          <!-- Colors (for charts) -->
          <div v-if="isChartWidget(selectedWidget.type)" class="prop-group">
            <label class="prop-label">Couleur principale</label>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
              <button
                v-for="c in colorPresets"
                :key="c"
                class="color-swatch"
                :class="{ 'color-swatch--active': selectedWidget.config.primaryColor === c }"
                :style="{ background: c }"
                @click="selectedWidget.config.primaryColor = c"
              />
            </div>
          </div>

          <!-- Description -->
          <div class="prop-group">
            <label class="prop-label">Description (info-bulle)</label>
            <textarea v-model="selectedWidget.description" class="prop-textarea" rows="2" placeholder="Description optionnelle…" />
          </div>

          <button class="btn btn-secondary prop-delete-btn" @click="removeWidgetByKey(selectedWidget._key)">
            <i class="pi pi-trash" style="font-size:13px" />
            Supprimer ce widget
          </button>
        </template>

        <div v-else class="sidebar-empty">
          <i class="pi pi-cursor" style="font-size:28px;color:var(--fg-4,var(--fg-3));margin-bottom:10px" />
          <p style="font-size:13px;color:var(--fg-3);text-align:center;margin:0">
            Cliquez sur un widget pour modifier ses propriétés.
          </p>
        </div>
      </aside>
    </div>

    <!-- Save success toast -->
    <Transition name="toast">
      <div v-if="toastMsg" class="save-toast">
        <i class="pi pi-check-circle" style="font-size:16px;color:var(--success-fg)" />
        {{ toastMsg }}
      </div>
    </Transition>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, defineComponent, h } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import KpiCard from '@/Components/BI/KpiCard.vue'
import ChartWidget from '@/Components/BI/ChartWidget.vue'
import DataTableWidget from '@/Components/BI/DataTableWidget.vue'
import axios from 'axios'

// ── Inline WidgetPreview component ────────────────────────────────────────────
const WidgetPreview = defineComponent({
  name: 'WidgetPreview',
  props: { widget: { type: Object, required: true } },
  setup(props) {
    return () => {
      const w = props.widget
      const h_ = w.h ?? 1
      const minH = h_ === 1 ? 100 : h_ === 2 ? 180 : 260

      if (w.type === 'kpi_card') {
        return h(KpiCard, {
          label: w.title,
          value: w._preview?.value ?? '—',
          unit: w.config?.unit,
          trend: w._preview?.trend,
          format: w.config?.format ?? 'raw',
        })
      }

      if (['bar_chart', 'line_chart', 'area_chart', 'pie_chart'].includes(w.type)) {
        const typeMap = { bar_chart: 'bar', line_chart: 'line', area_chart: 'area', pie_chart: 'pie' }
        const mockSeries = ['pie_chart'].includes(w.type)
          ? [44, 55, 30, 20]
          : [{ name: w.title, data: [30, 42, 28, 55, 48, 62, 50] }]
        return h(ChartWidget, {
          type: typeMap[w.type],
          series: mockSeries,
          height: minH,
          options: {
            xaxis: { categories: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul'] },
            colors: w.config?.primaryColor ? [w.config.primaryColor] : undefined,
          },
        })
      }

      if (w.type === 'metric_gauge') {
        return h(ChartWidget, {
          type: 'radialBar',
          series: [w._preview?.percentage ?? 72],
          height: minH,
          options: {
            chart: { toolbar: { show: false }, background: 'transparent' },
            colors: w.config?.primaryColor ? [w.config.primaryColor] : ['#2E5BE8'],
            plotOptions: {
              radialBar: {
                hollow: { size: '55%' },
                dataLabels: { value: { fontSize: '18px', fontWeight: 700, formatter: v => `${v}%` } },
              },
            },
          },
        })
      }

      if (w.type === 'data_table') {
        return h(DataTableWidget, {
          columns: [{ field: 'name', header: 'Nom' }, { field: 'value', header: 'Valeur' }],
          rows: [{ name: 'Ligne 1', value: 'Données…' }, { name: 'Ligne 2', value: 'Données…' }],
          pageSize: 3,
          searchable: false,
        })
      }

      // ── Advanced chart types ────────────────────────────────────────────────
      if (w.type === 'heatmap') {
        return h(ChartWidget, {
          type: 'heatmap',
          height: minH,
          series: [
            { name: 'Matin', data: [{ x: 'L', y: 30 }, { x: 'M', y: 55 }, { x: 'Me', y: 41 }, { x: 'J', y: 67 }, { x: 'V', y: 22 }] },
            { name: 'Soir', data: [{ x: 'L', y: 13 }, { x: 'M', y: 32 }, { x: 'Me', y: 58 }, { x: 'J', y: 47 }, { x: 'V', y: 71 }] },
          ],
          options: {
            chart: { toolbar: { show: false } },
            colors: [w.config?.primaryColor ?? '#2E5BE8'],
            dataLabels: { enabled: false },
            legend: { show: false },
          },
        })
      }

      if (w.type === 'treemap') {
        return h(ChartWidget, {
          type: 'treemap',
          height: minH,
          series: [{
            data: [
              { x: 'France', y: 420 }, { x: 'Allemagne', y: 310 },
              { x: 'Espagne', y: 185 }, { x: 'Italie', y: 145 }, { x: 'Autres', y: 95 },
            ],
          }],
          options: {
            chart: { toolbar: { show: false } },
            dataLabels: { enabled: true },
            legend: { show: false },
          },
        })
      }

      if (w.type === 'scatter') {
        return h(ChartWidget, {
          type: 'scatter',
          height: minH,
          series: [{
            name: 'Clients',
            data: [[10, 1200], [25, 3400], [40, 5600], [15, 2100], [60, 8900], [30, 4200]],
          }],
          options: {
            chart: { toolbar: { show: false } },
            colors: [w.config?.primaryColor ?? '#7c3aed'],
            dataLabels: { enabled: false },
            xaxis: { title: { text: 'Volume clients' } },
            yaxis: { title: { text: 'Montant (€)' } },
          },
        })
      }

      if (w.type === 'radar') {
        return h(ChartWidget, {
          type: 'radar',
          height: minH,
          series: [
            { name: 'Équipe A', data: [80, 65, 90, 75, 70, 85] },
            { name: 'Équipe B', data: [70, 72, 78, 80, 65, 75] },
          ],
          options: {
            chart: { toolbar: { show: false } },
            colors: [w.config?.primaryColor ?? '#2E5BE8', '#16a34a'],
            xaxis: { categories: ['Ventes', 'RH', 'Support', 'Prod.', 'Finance', 'IT'] },
            yaxis: { show: false },
            dataLabels: { enabled: false },
          },
        })
      }

      // Fallback placeholder
      return h('div', {
        style: `display:flex;align-items:center;justify-content:center;height:${minH}px;
                color:var(--fg-3);font-size:13px;gap:8px`,
      }, [
        h('i', { class: 'pi pi-th-large' }),
        w.title,
      ])
    }
  },
})

// ── Props ──────────────────────────────────────────────────────────────────────
const props = defineProps({
  dashboardId:  { type: [Number, String], default: null },
  dashboardName:{ type: String, default: '' },
  existingWidgets: { type: Array, default: () => [] },
  dataSources:  { type: Array, default: () => [] },
})

// ── State ──────────────────────────────────────────────────────────────────────
const formName     = ref(props.dashboardName || 'Nouveau Dashboard')
const canvasWidgets = ref(
  props.existingWidgets.map(w => ({
    ...w,
    _key: `w-${Math.random().toString(36).slice(2)}`,
    config: w.config ?? {},
    w: w.w ?? 6,
    h: w.h ?? 1,
  }))
)
const selectedWidget = ref(null)
const saving         = ref(false)
const previewMode    = ref(false)
const isDraggingOver = ref(false)
const lastSaved      = ref('')
const toastMsg       = ref('')

// Drag state
const draggingPaletteType = ref(null)
const draggingWidgetIdx   = ref(null)
let _widgetCounter = 0

// ── Widget type definitions ────────────────────────────────────────────────────
const widgetTypes = [
  { type: 'kpi_card',     label: 'KPI Card',        desc: 'Valeur + tendance + sparkline', icon: 'pi pi-gauge',      bg: '#eff6ff', color: '#1d4ed8' },
  { type: 'bar_chart',    label: 'Graphique Barres', desc: 'Comparaison par catégorie',     icon: 'pi pi-chart-bar',  bg: '#f0fdf4', color: '#16a34a' },
  { type: 'line_chart',   label: 'Graphique Ligne',  desc: 'Tendance dans le temps',        icon: 'pi pi-chart-line', bg: '#faf5ff', color: '#7c3aed' },
  { type: 'area_chart',   label: 'Graphique Zone',   desc: 'Volume dans le temps',          icon: 'pi pi-chart-line', bg: '#fff7ed', color: '#c2410c' },
  { type: 'pie_chart',    label: 'Graphique Camembert', desc: 'Répartition en secteurs',   icon: 'pi pi-chart-pie',  bg: '#fef9c3', color: '#b45309' },
  { type: 'metric_gauge', label: 'Jauge Métrique',   desc: 'Indicateur circulaire %',       icon: 'pi pi-circle',     bg: '#fdf2f8', color: '#be185d' },
  { type: 'data_table',   label: 'Tableau de données', desc: 'Données tabulaires filtrables', icon: 'pi pi-table',   bg: '#f0f9ff', color: '#0369a1' },
]

const advancedWidgetTypes = [
  { type: 'heatmap',  label: 'Heatmap',      desc: 'Intensité matricielle temps/jour',     icon: 'pi pi-th-large',   bg: '#eff6ff', color: '#1d4ed8' },
  { type: 'treemap',  label: 'Treemap',      desc: 'Répartition hiérarchique par surface', icon: 'pi pi-sitemap',    bg: '#f0fdf4', color: '#15803d' },
  { type: 'scatter',  label: 'Scatter Plot', desc: 'Corrélation entre deux variables',     icon: 'pi pi-circle',     bg: '#faf5ff', color: '#7c3aed' },
  { type: 'radar',    label: 'Radar Chart',  desc: 'Comparaison multi-axes KPI',           icon: 'pi pi-chart-pie',  bg: '#f0f9ff', color: '#0369a1' },
]

// Combined list used for icon/label lookups
const allWidgetTypes = [...widgetTypes, ...advancedWidgetTypes]

const availableMetrics = [
  { value: 'revenue',         label: 'Chiffre d\'affaires' },
  { value: 'orders_count',    label: 'Nombre de commandes' },
  { value: 'customers_count', label: 'Nombre de clients' },
  { value: 'leads_count',     label: 'Leads actifs' },
  { value: 'tickets_open',    label: 'Tickets ouverts' },
  { value: 'headcount',       label: 'Effectif' },
  { value: 'inventory_low',   label: 'Articles en stock faible' },
  { value: 'invoices_overdue',label: 'Factures en retard' },
]

const colorPresets = ['#2E5BE8', '#16a34a', '#c2410c', '#7c3aed', '#0369a1', '#b45309', '#be185d', '#6b7280']

// ── Computed ──────────────────────────────────────────────────────────────────
const widgetTypeLabelMap = computed(() =>
  Object.fromEntries(allWidgetTypes.map(w => [w.type, w.label]))
)

// ── Methods ───────────────────────────────────────────────────────────────────
function widgetIcon(type) {
  return allWidgetTypes.find(w => w.type === type)?.icon ?? 'pi pi-th-large'
}

function widgetTypeLabel(type) {
  return widgetTypeLabelMap.value[type] ?? type
}

function isChartWidget(type) {
  return ['bar_chart', 'line_chart', 'area_chart', 'pie_chart', 'metric_gauge', 'heatmap', 'treemap', 'scatter', 'radar'].includes(type)
}

function canvasWidgetStyle(widget) {
  const cols = Math.min(widget.w ?? 6, 12)
  const hVal = widget.h ?? 1
  const minH = hVal === 1 ? 150 : hVal === 2 ? 280 : hVal === 3 ? 380 : 480
  return {
    gridColumn: `span ${cols}`,
    minHeight:  `${minH}px`,
  }
}

function selectWidget(widget) {
  selectedWidget.value = widget
}

function removeWidget(idx) {
  if (selectedWidget.value && selectedWidget.value._key === canvasWidgets.value[idx]._key) {
    selectedWidget.value = null
  }
  canvasWidgets.value.splice(idx, 1)
}

function removeWidgetByKey(key) {
  const idx = canvasWidgets.value.findIndex(w => w._key === key)
  if (idx !== -1) removeWidget(idx)
}

function moveWidget(idx, dir) {
  const target = idx + dir
  if (target < 0 || target >= canvasWidgets.value.length) return
  const arr = [...canvasWidgets.value];
  [arr[idx], arr[target]] = [arr[target], arr[idx]]
  canvasWidgets.value = arr
}

function clearCanvas() {
  if (confirm('Vider le canvas ? Tous les widgets seront supprimés.')) {
    canvasWidgets.value = []
    selectedWidget.value = null
  }
}

// ── Drag from palette ─────────────────────────────────────────────────────────
function onPaletteDragStart(evt, widgetType) {
  draggingPaletteType.value = widgetType
  draggingWidgetIdx.value   = null
  evt.dataTransfer.effectAllowed = 'copy'
  evt.dataTransfer.setData('text/plain', widgetType.type)
}

// ── Drag reorder inside canvas ────────────────────────────────────────────────
function onWidgetDragStart(evt, idx) {
  draggingWidgetIdx.value   = idx
  draggingPaletteType.value = null
  evt.dataTransfer.effectAllowed = 'move'
  evt.dataTransfer.setData('text/plain', String(idx))
  evt.stopPropagation()
}

function onWidgetDragOver(evt, idx) {
  evt.preventDefault()
  evt.dataTransfer.dropEffect = draggingWidgetIdx.value !== null ? 'move' : 'copy'
}

function onWidgetDrop(evt, targetIdx) {
  evt.stopPropagation()
  if (draggingWidgetIdx.value !== null && draggingWidgetIdx.value !== targetIdx) {
    const arr = [...canvasWidgets.value]
    const [moved] = arr.splice(draggingWidgetIdx.value, 1)
    arr.splice(targetIdx, 0, moved)
    canvasWidgets.value = arr
  } else if (draggingPaletteType.value) {
    addWidget(draggingPaletteType.value, targetIdx)
  }
  draggingWidgetIdx.value   = null
  draggingPaletteType.value = null
  isDraggingOver.value      = false
}

// ── Drag onto canvas empty area ───────────────────────────────────────────────
function onCanvasDragOver(evt) {
  evt.preventDefault()
  isDraggingOver.value = true
}

function onCanvasDragLeave(evt) {
  // Only hide when leaving the canvas completely
  if (!evt.currentTarget.contains(evt.relatedTarget)) {
    isDraggingOver.value = false
  }
}

function onCanvasDrop(evt) {
  isDraggingOver.value = false
  if (draggingPaletteType.value) {
    addWidget(draggingPaletteType.value)
    draggingPaletteType.value = null
  }
  draggingWidgetIdx.value = null
}

function addWidget(widgetType, insertIdx = null) {
  _widgetCounter++
  const newWidget = reactive({
    _key:        `w-${Date.now()}-${_widgetCounter}`,
    type:        widgetType.type,
    title:       widgetType.label,
    description: '',
    w:           widgetType.type === 'kpi_card' ? 3 : widgetType.type === 'data_table' ? 12 : 6,
    h:           widgetType.type === 'data_table' ? 2 : 1,
    dataSource:  '',
    config:      {
      metric:       '',
      unit:         '',
      period:       'month',
      primaryColor: colorPresets[0],
      threshold:    null,
      format:       'raw',
    },
    _preview:    {},
  })
  if (insertIdx !== null) {
    canvasWidgets.value.splice(insertIdx, 0, newWidget)
  } else {
    canvasWidgets.value.push(newWidget)
  }
  selectedWidget.value = newWidget
}

// ── Resize ────────────────────────────────────────────────────────────────────
function startResize(evt, idx, axis) {
  const widget    = canvasWidgets.value[idx]
  const startX    = evt.clientX
  const startY    = evt.clientY
  const startW    = widget.w
  const startH    = widget.h
  const colW      = (evt.currentTarget.closest('.canvas-grid')?.offsetWidth ?? 800) / 12

  function onMove(e) {
    if (axis === 'w') {
      const delta = Math.round((e.clientX - startX) / colW)
      widget.w = Math.max(1, Math.min(12, startW + delta))
    } else {
      const delta = Math.round((e.clientY - startY) / 120)
      widget.h = Math.max(1, Math.min(4, startH + delta))
    }
  }

  function onUp() {
    window.removeEventListener('mousemove', onMove)
    window.removeEventListener('mouseup', onUp)
  }

  window.addEventListener('mousemove', onMove)
  window.addEventListener('mouseup', onUp)
}

// ── Save ──────────────────────────────────────────────────────────────────────
async function save() {
  if (!formName.value.trim()) return
  saving.value = true
  try {
    const payload = {
      name:    formName.value.trim(),
      widgets: canvasWidgets.value.map(({ _key, _preview, ...w }) => w),
    }

    let res
    if (props.dashboardId) {
      res = await axios.put(`/api/v1/bi/dashboards/${props.dashboardId}`, payload)
      // Save widgets separately
      await axios.post(`/api/v1/bi/dashboards/${props.dashboardId}/widgets`, {
        widgets: payload.widgets,
      })
    } else {
      res = await axios.post('/api/v1/bi/dashboards', payload)
      if (res.data?.data?.id) {
        await axios.post(`/api/v1/bi/dashboards/${res.data.data.id}/widgets`, {
          widgets: payload.widgets,
        })
      }
    }

    const now   = new Date()
    lastSaved.value = now.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
    showToast('Dashboard sauvegardé avec succès')
  } catch (e) {
    showToast(e.response?.data?.message ?? 'Erreur lors de la sauvegarde')
  } finally {
    saving.value = false
  }
}

function showToast(msg) {
  toastMsg.value = msg
  setTimeout(() => { toastMsg.value = '' }, 3500)
}
</script>

<style scoped>
/* ── Layout ─────────────────────────────────────────────── */
.builder-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 0 16px;
  margin-bottom: 0;
  gap: 12px;
  flex-wrap: wrap;
}
.builder-topbar__actions { display: flex; gap: 8px; align-items: center; }
.builder-title-input {
  font-family: var(--font-display);
  font-size: 22px;
  font-weight: 600;
  color: var(--fg-1);
  border: none;
  background: transparent;
  outline: none;
  border-bottom: 2px solid transparent;
  padding: 2px 4px;
  min-width: 240px;
  transition: border-color var(--dur-fast);
}
.builder-title-input:focus { border-bottom-color: var(--halo-400); }
.builder-saved-hint { font-size: 11px; color: var(--success-fg); margin: 3px 0 0 4px; display: flex; align-items: center; gap: 4px; }

.builder-layout {
  display: grid;
  grid-template-columns: 240px 1fr 260px;
  gap: 14px;
  min-height: calc(100vh - 180px);
}
@media (max-width: 1100px) {
  .builder-layout { grid-template-columns: 200px 1fr; }
  .builder-sidebar--right { display: none !important; }
}
@media (max-width: 768px) {
  .builder-layout { grid-template-columns: 1fr; }
  .builder-sidebar--left, .builder-sidebar--right { display: none !important; }
}

/* ── Sidebars ────────────────────────────────────────────── */
.builder-sidebar {
  background: var(--bg-canvas);
  border: 1px solid var(--border-subtle);
  border-radius: var(--r-lg);
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  height: fit-content;
  position: sticky;
  top: 16px;
}
.sidebar-section-label {
  font-size: 10px;
  font-weight: 700;
  color: var(--fg-3);
  text-transform: uppercase;
  letter-spacing: 0.07em;
  margin-bottom: 4px;
}
.sidebar-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 32px 12px;
  gap: 8px;
}

/* ── Palette ─────────────────────────────────────────────── */
.palette { display: flex; flex-direction: column; gap: 4px; }
.palette-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  border-radius: var(--r-md);
  border: 1px solid var(--border-subtle);
  background: var(--bg-canvas);
  cursor: grab;
  transition: all var(--dur-fast);
  user-select: none;
}
.palette-item:hover { border-color: var(--halo-300); background: var(--halo-50, #eff4ff); }
.palette-item:active { cursor: grabbing; }
.palette-item__icon {
  width: 32px; height: 32px; border-radius: var(--r-sm);
  display: flex; align-items: center; justify-content: center;
  font-size: 15px; flex-shrink: 0;
}
.palette-item__name { font-size: 12px; font-weight: 600; color: var(--fg-1); margin: 0; }
.palette-item__desc { font-size: 10px; color: var(--fg-3); margin: 1px 0 0; }
.palette-item__grip { margin-left: auto; font-size: 13px; color: var(--fg-4, var(--fg-3)); flex-shrink: 0; }

/* Data sources */
.ds-item { display: flex; align-items: center; gap: 8px; padding: 6px 8px; border-radius: var(--r-sm); background: var(--bg-sunken); }
.ds-item__name { font-size: 12px; color: var(--fg-1); flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ds-item__type { font-size: 10px; color: var(--fg-3); text-transform: capitalize; flex-shrink: 0; }
.ds-empty { padding: 8px 0; text-align: center; }

/* ── Canvas ──────────────────────────────────────────────── */
.builder-canvas {
  background: var(--bg-sunken);
  border: 2px dashed var(--border-subtle);
  border-radius: var(--r-lg);
  padding: 16px;
  min-height: 500px;
  position: relative;
  transition: border-color var(--dur-fast), background var(--dur-fast);
}
.builder-canvas--dragging {
  border-color: var(--halo-400);
  background: var(--halo-50, #eff4ff);
}
.builder-canvas--preview {
  border-style: solid;
  border-color: var(--border-subtle);
  background: var(--bg-canvas);
}
.canvas-empty {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  min-height: 400px; text-align: center; pointer-events: none;
}
.canvas-grid {
  display: grid;
  grid-template-columns: repeat(12, 1fr);
  gap: 12px;
  align-items: start;
}
@media (max-width: 600px) {
  .canvas-grid { grid-template-columns: 1fr; }
}
.canvas-drop-overlay {
  position: absolute; inset: 0; border-radius: var(--r-lg);
  background: rgba(46, 91, 232, 0.08);
  display: flex; align-items: center; justify-content: center;
  gap: 10px; font-size: 15px; font-weight: 600; color: var(--halo-600);
  pointer-events: none;
}

/* ── Canvas widgets ──────────────────────────────────────── */
.canvas-widget {
  background: var(--bg-canvas);
  border: 2px solid var(--border-subtle);
  border-radius: var(--r-lg);
  overflow: hidden;
  cursor: pointer;
  transition: border-color var(--dur-fast), box-shadow var(--dur-fast);
  position: relative;
  display: flex;
  flex-direction: column;
}
.canvas-widget:hover { border-color: var(--halo-300); }
.canvas-widget--selected { border-color: var(--halo-500) !important; box-shadow: 0 0 0 3px rgba(46,91,232,0.15); }
.canvas-widget--preview { cursor: default; border-style: solid; }
.canvas-widget__head {
  display: flex; align-items: center; gap: 8px;
  padding: 9px 12px; border-bottom: 1px solid var(--border-subtle);
  background: var(--bg-sunken); flex-shrink: 0;
}
.canvas-widget__title { font-size: 12px; font-weight: 600; color: var(--fg-1); flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.canvas-widget__tools { display: flex; gap: 2px; flex-shrink: 0; }
.canvas-widget__body { padding: 12px; flex: 1; }

/* Resize handles */
.resize-handle {
  position: absolute;
  background: var(--halo-400);
  opacity: 0;
  transition: opacity var(--dur-fast);
  border-radius: 2px;
}
.canvas-widget:hover .resize-handle { opacity: 0.7; }
.canvas-widget--selected .resize-handle { opacity: 1; }
.resize-handle--e {
  right: 0; top: 30%; width: 6px; height: 40%;
  cursor: ew-resize; border-radius: 3px 0 0 3px;
}
.resize-handle--s {
  bottom: 0; left: 30%; height: 6px; width: 40%;
  cursor: ns-resize; border-radius: 3px 3px 0 0;
}

/* ── Properties panel ────────────────────────────────────── */
.prop-group { display: flex; flex-direction: column; gap: 4px; margin-bottom: 12px; }
.prop-label { font-size: 11px; font-weight: 600; color: var(--fg-2); }
.prop-input, .prop-select, .prop-textarea {
  font-family: var(--font-sans); font-size: 13px; color: var(--fg-1);
  background: var(--bg-sunken); border: 1px solid var(--border-subtle);
  border-radius: var(--r-sm); padding: 6px 9px; outline: none; width: 100%;
  transition: border-color var(--dur-fast);
}
.prop-input:focus, .prop-select:focus, .prop-textarea:focus { border-color: var(--halo-400); background: var(--bg-canvas); }
.prop-textarea { resize: vertical; }
.prop-range-row { display: flex; align-items: center; gap: 8px; }
.prop-range { flex: 1; accent-color: var(--halo-500); }
.prop-range-val { font-size: 12px; font-weight: 600; color: var(--fg-1); min-width: 24px; text-align: right; }
.prop-badge {
  display: inline-flex; align-items: center; gap: 6px; font-size: 12px;
  color: var(--fg-2); background: var(--bg-sunken); border-radius: var(--r-sm);
  padding: 5px 9px; border: 1px solid var(--border-subtle);
}
.prop-delete-btn {
  margin-top: 8px; width: 100%; justify-content: center;
  border-color: var(--danger-fg, #dc2626) !important;
  color: var(--danger-fg, #dc2626) !important;
}
.prop-delete-btn:hover { background: var(--danger-bg, #fef2f2) !important; }

/* Color swatches */
.color-swatch {
  width: 22px; height: 22px; border-radius: 50%; border: 2px solid transparent;
  cursor: pointer; transition: transform var(--dur-fast), border-color var(--dur-fast);
}
.color-swatch:hover { transform: scale(1.15); }
.color-swatch--active { border-color: var(--fg-1); transform: scale(1.1); }

/* ── Buttons ─────────────────────────────────────────────── */
.back-link {
  display: inline-flex; align-items: center; justify-content: center;
  width: 32px; height: 32px; border: 1px solid var(--border-subtle);
  border-radius: var(--r-md); background: var(--bg-canvas); color: var(--fg-2);
  text-decoration: none; transition: all var(--dur-fast); flex-shrink: 0;
}
.back-link:hover { background: var(--bg-sunken); color: var(--fg-1); }
.btn {
  font-family: var(--font-sans); font-weight: 500; font-size: 13px;
  padding: 8px 14px; border-radius: var(--r-md); border: 1px solid transparent;
  cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
  transition: background var(--dur-base); line-height: 1.2;
}
.btn-primary { background: var(--halo-500); color: #fff; }
.btn-primary:hover { background: var(--halo-700); }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-secondary { background: var(--bg-canvas); color: var(--fg-1); border-color: var(--border-subtle); }
.btn-secondary:hover { background: var(--bg-sunken); }
.btn-secondary:disabled { opacity: 0.4; cursor: not-allowed; }
.wh-icon-btn {
  width: 24px; height: 24px; border-radius: var(--r-sm);
  border: none; background: transparent; color: var(--fg-3); cursor: pointer;
  display: inline-flex; align-items: center; justify-content: center;
  transition: all var(--dur-fast);
}
.wh-icon-btn:hover { background: var(--bg-canvas); color: var(--fg-1); }
.wh-icon-btn--danger:hover { background: var(--danger-bg); color: var(--danger-fg); }
.wh-icon-btn:disabled { opacity: 0.3; cursor: not-allowed; }

/* ── Toast ───────────────────────────────────────────────── */
.save-toast {
  position: fixed; bottom: 24px; right: 24px; z-index: 9999;
  display: flex; align-items: center; gap: 10px;
  background: var(--bg-canvas); border: 1px solid var(--border-subtle);
  border-radius: var(--r-lg); padding: 12px 18px;
  font-size: 13px; color: var(--fg-1);
  box-shadow: 0 4px 20px rgba(0,0,0,0.12);
}
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from { transform: translateY(16px); opacity: 0; }
.toast-leave-to   { transform: translateY(8px);  opacity: 0; }
</style>
