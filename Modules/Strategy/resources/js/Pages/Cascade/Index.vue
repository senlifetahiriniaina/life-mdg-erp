<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import axios from 'axios'
import { useAiAssistant } from '@/composables/useAiAssistant'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'

// ─── AI guidance ──────────────────────────────────────────────────────────────
const { guidance } = useAiAssistant('Strategy', 'view_cascade_map')

// ─── State ─────────────────────────────────────────────────────────────────────
const loading    = ref(true)
const nodes      = ref([])    // recursive tree
const stats      = ref({ total: 0, on_track: 0, at_risk: 0, behind: 0 })
const error      = ref(null)

const selectedNode   = ref(null)
const panelOpen      = ref(false)
const focusedNodeId  = ref(null)   // keyboard navigation

// SVG pan / zoom
const svgRef   = ref(null)
const viewBox  = ref({ x: 0, y: 0, w: 1200, h: 800 })
const isPanning = ref(false)
const panStart  = ref({ x: 0, y: 0 })
const ZOOM_FACTOR = 0.1

// ─── Fetch data ────────────────────────────────────────────────────────────────
onMounted(async () => {
  try {
    const { data } = await axios.get('/api/v1/strategy/cascade')
    nodes.value  = data.data?.nodes  ?? []
    stats.value  = data.data?.stats  ?? { total: 0, on_track: 0, at_risk: 0, behind: 0 }
  } catch (e) {
    error.value = e?.response?.data?.message ?? 'Erreur de chargement'
  } finally {
    loading.value = false
  }
})

// ─── Flatten tree for keyboard navigation ─────────────────────────────────────
function flattenTree(nodeList) {
  const result = []
  const traverse = (ns) => {
    ns.forEach(n => {
      result.push(n)
      if (n.children?.length) traverse(n.children)
    })
  }
  traverse(nodeList)
  return result
}

const flatNodes = computed(() => flattenTree(nodes.value))

// ─── Node interaction ──────────────────────────────────────────────────────────
function selectNode(node) {
  selectedNode.value  = node
  panelOpen.value     = true
  focusedNodeId.value = node.id
}

function closePanel() {
  panelOpen.value   = false
  selectedNode.value = null
}

// ─── Keyboard navigation ───────────────────────────────────────────────────────
function handleKeydown(event) {
  if (!flatNodes.value.length) return

  const currentIdx = flatNodes.value.findIndex(n => n.id === focusedNodeId.value)
  let nextIdx = currentIdx

  if (event.key === 'ArrowDown')  { nextIdx = Math.min(currentIdx + 1, flatNodes.value.length - 1); event.preventDefault() }
  if (event.key === 'ArrowUp')    { nextIdx = Math.max(currentIdx - 1, 0); event.preventDefault() }
  if (event.key === 'ArrowRight') { nextIdx = Math.min(currentIdx + 1, flatNodes.value.length - 1); event.preventDefault() }
  if (event.key === 'ArrowLeft')  { nextIdx = Math.max(currentIdx - 1, 0); event.preventDefault() }
  if (event.key === 'Escape') { closePanel(); return }
  if (event.key === 'Enter' && currentIdx >= 0) { selectNode(flatNodes.value[currentIdx]); return }

  if (nextIdx !== currentIdx) {
    focusedNodeId.value = flatNodes.value[nextIdx].id
    nextTick(() => {
      document.getElementById(`node-${flatNodes.value[nextIdx].id}`)?.focus()
    })
  }
}

onMounted(() => { window.addEventListener('keydown', handleKeydown) })
onBeforeUnmount(() => { window.removeEventListener('keydown', handleKeydown) })

// ─── SVG layout helpers ────────────────────────────────────────────────────────
// Layout constants
const NODE_W     = 180
const NODE_H     = 64
const H_GAP      = 40    // horizontal gap between siblings
const V_GAP      = 100   // vertical gap between levels

/**
 * Compute (x, y, width) for every node using a bottom-up subtree-width pass,
 * then a top-down position pass.
 */
function layoutTree(nodeList, startX = 0, depth = 0) {
  // Each node gets: _x, _y, _subtreeW
  const layout = []

  let cursor = startX
  for (const node of nodeList) {
    const childLayout = node.children?.length
      ? layoutTree(node.children, cursor, depth + 1)
      : []

    const subtreeW = childLayout.length
      ? childLayout.reduce((acc, c) => acc + c._subtreeW + H_GAP, 0) - H_GAP
      : NODE_W

    const x = childLayout.length
      ? childLayout[0]._x + (subtreeW - NODE_W) / 2
      : cursor

    const positioned = { ...node, _x: x, _y: depth * (NODE_H + V_GAP), _subtreeW: subtreeW, children: childLayout }
    layout.push(positioned)

    cursor += subtreeW + H_GAP
  }

  return layout
}

const layoutNodes = computed(() => layoutTree(nodes.value))

/** Collect all edges from layoutNodes for SVG line rendering */
function collectEdges(nodeList) {
  const edges = []
  const traverse = (ns) => {
    ns.forEach(parent => {
      parent.children?.forEach(child => {
        edges.push({
          x1: parent._x + NODE_W / 2,
          y1: parent._y + NODE_H,
          x2: child._x  + NODE_W / 2,
          y2: child._y,
        })
        traverse(parent.children)
      })
    })
  }
  traverse(ns)
  return edges
}

const allEdges = computed(() => {
  const edges = []
  const traverse = (ns) => {
    ns.forEach(parent => {
      parent.children?.forEach(child => {
        edges.push({
          x1: parent._x + NODE_W / 2,
          y1: parent._y + NODE_H,
          x2: child._x  + NODE_W / 2,
          y2: child._y,
        })
        traverse(parent.children)
      })
    })
  }
  traverse(layoutNodes.value)
  return edges
})

/** Flatten layout nodes for SVG rect rendering */
function flattenLayout(nodeList) {
  const result = []
  const traverse = (ns) => {
    ns.forEach(n => {
      result.push(n)
      if (n.children?.length) traverse(n.children)
    })
  }
  traverse(nodeList)
  return result
}

const allLayoutNodes = computed(() => flattenLayout(layoutNodes.value))

/** Derive viewBox that fits the full tree */
const computedViewBox = computed(() => {
  if (!allLayoutNodes.value.length) return '0 0 1200 400'
  const maxX = Math.max(...allLayoutNodes.value.map(n => n._x + NODE_W)) + 40
  const maxY = Math.max(...allLayoutNodes.value.map(n => n._y + NODE_H)) + 40
  return `${viewBox.value.x} ${viewBox.value.y} ${Math.max(maxX, viewBox.value.w)} ${Math.max(maxY, viewBox.value.h)}`
})

// ─── Pan & Zoom ────────────────────────────────────────────────────────────────
function onWheel(e) {
  e.preventDefault()
  const delta = e.deltaY > 0 ? 1 + ZOOM_FACTOR : 1 - ZOOM_FACTOR
  viewBox.value = {
    ...viewBox.value,
    w: viewBox.value.w * delta,
    h: viewBox.value.h * delta,
  }
}

function onMousedown(e) {
  if (e.button !== 0) return
  isPanning.value = true
  panStart.value  = { x: e.clientX, y: e.clientY }
}

function onMousemove(e) {
  if (!isPanning.value) return
  const svgEl  = svgRef.value
  const ratio  = viewBox.value.w / svgEl.clientWidth
  const dx     = (e.clientX - panStart.value.x) * ratio
  const dy     = (e.clientY - panStart.value.y) * ratio
  viewBox.value = { ...viewBox.value, x: viewBox.value.x - dx, y: viewBox.value.y - dy }
  panStart.value = { x: e.clientX, y: e.clientY }
}

function onMouseup() { isPanning.value = false }

// ─── RAG colour helpers ────────────────────────────────────────────────────────
const ragFill   = { green: '#dcfce7', amber: '#fef3c7', red: '#fee2e2', default: '#f3f4f6' }
const ragStroke = { green: '#16a34a', amber: '#d97706', red: '#dc2626', default: '#9ca3af' }
const ragText   = { green: '#166534', amber: '#92400e', red: '#991b1b', default: '#374151' }

function nodeFill(rag)   { return ragFill[rag]   ?? ragFill.default }
function nodeStroke(rag) { return ragStroke[rag]  ?? ragStroke.default }
function nodeText(rag)   { return ragText[rag]    ?? ragText.default }

const levelLabels = { company: 'Entreprise', department: 'Département', team: 'Équipe', individual: 'Individuel' }

// ─── Status badge helpers ──────────────────────────────────────────────────────
const statusLabel = { on_track: 'En cours', at_risk: 'À risque', behind: 'En retard', not_started: 'Non démarré' }
const statusClass = {
  on_track:    'bg-green-100 text-green-800',
  at_risk:     'bg-yellow-100 text-yellow-800',
  behind:      'bg-red-100 text-red-800',
  not_started: 'bg-gray-100 text-gray-600',
}
const ratioStatusClass = { green: 'bg-green-100 text-green-800', amber: 'bg-yellow-100 text-yellow-800', red: 'bg-red-100 text-red-800' }
</script>

<template>
  <div class="p-6 space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Carte de Cascade d'Alignement</h1>
        <p class="text-sm text-gray-500 mt-1">Visualisation hiérarchique des objectifs OKR et leur alignement stratégique</p>
      </div>
      <button
        v-if="panelOpen"
        class="text-sm text-gray-500 hover:text-gray-700 underline"
        @click="closePanel"
      >
        Fermer le panneau
      </button>
    </div>

    <!-- AI guidance -->
    <AIAssistantPanel v-if="guidance" :guidance="guidance" />

    <!-- Stats bar -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
      <div class="bg-white rounded-lg shadow p-4 border-l-4 border-gray-300">
        <p class="text-xs text-gray-500 uppercase tracking-wide">Total</p>
        <p class="text-2xl font-bold text-gray-800">{{ stats.total }}</p>
      </div>
      <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
        <p class="text-xs text-gray-500 uppercase tracking-wide">En cours</p>
        <p class="text-2xl font-bold text-green-700">{{ stats.on_track }}</p>
      </div>
      <div class="bg-white rounded-lg shadow p-4 border-l-4 border-yellow-500">
        <p class="text-xs text-gray-500 uppercase tracking-wide">À risque</p>
        <p class="text-2xl font-bold text-yellow-700">{{ stats.at_risk }}</p>
      </div>
      <div class="bg-white rounded-lg shadow p-4 border-l-4 border-red-500">
        <p class="text-xs text-gray-500 uppercase tracking-wide">En retard</p>
        <p class="text-2xl font-bold text-red-700">{{ stats.behind }}</p>
      </div>
    </div>

    <!-- Error state -->
    <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-lg" role="alert">
      <p class="font-medium">Erreur de chargement</p>
      <p class="text-sm mt-1">{{ error }}</p>
    </div>

    <!-- Loading skeleton -->
    <div v-else-if="loading" class="space-y-4" aria-busy="true" aria-label="Chargement de la carte de cascade">
      <div class="bg-white rounded-lg shadow p-6">
        <div class="animate-pulse space-y-4">
          <div class="flex justify-center">
            <div class="h-16 w-48 bg-gray-200 rounded-lg" />
          </div>
          <div class="flex justify-center gap-8">
            <div class="h-16 w-40 bg-gray-200 rounded-lg" />
            <div class="h-16 w-40 bg-gray-200 rounded-lg" />
          </div>
          <div class="flex justify-center gap-4">
            <div v-for="i in 4" :key="i" class="h-16 w-32 bg-gray-200 rounded-lg" />
          </div>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else-if="!nodes.length" class="bg-white rounded-lg shadow p-12 text-center">
      <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
              d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
      </svg>
      <h3 class="mt-4 text-lg font-medium text-gray-900">Aucun objectif trouvé</h3>
      <p class="mt-2 text-sm text-gray-500">Créez des objectifs stratégiques pour afficher la carte de cascade.</p>
    </div>

    <!-- Main content: SVG + Side panel -->
    <div v-else class="flex gap-6">

      <!-- SVG cascade map -->
      <div
        class="flex-1 bg-white rounded-lg shadow overflow-auto"
        style="min-height: 480px;"
      >
        <!-- Legend -->
        <div class="flex items-center gap-4 px-4 pt-3 pb-1 border-b text-xs text-gray-600 flex-wrap">
          <span class="font-medium">Légende :</span>
          <span class="flex items-center gap-1">
            <span class="inline-block w-3 h-3 rounded-sm" style="background:#dcfce7;border:1px solid #16a34a" />
            En cours
          </span>
          <span class="flex items-center gap-1">
            <span class="inline-block w-3 h-3 rounded-sm" style="background:#fef3c7;border:1px solid #d97706" />
            À risque
          </span>
          <span class="flex items-center gap-1">
            <span class="inline-block w-3 h-3 rounded-sm" style="background:#fee2e2;border:1px solid #dc2626" />
            En retard
          </span>
          <span class="text-gray-400 ml-2">Molette = zoom · Cliquer-glisser = déplacer · Clic sur nœud = détail</span>
        </div>

        <!-- Accessible tree list (screen readers) -->
        <ul role="tree" class="sr-only" aria-label="Arbre des objectifs stratégiques">
          <template v-for="node in flatNodes" :key="node.id">
            <li
              :id="`node-${node.id}`"
              role="treeitem"
              :aria-label="`${node.title}, niveau ${levelLabels[node.level] ?? node.level}, progression ${node.progress}%, statut ${statusLabel[node.status] ?? node.status}`"
              :aria-expanded="node.children?.length ? 'true' : undefined"
              tabindex="0"
              @click="selectNode(node)"
              @keydown.enter="selectNode(node)"
            >
              {{ node.title }}
            </li>
          </template>
        </ul>

        <!-- SVG org-chart -->
        <svg
          ref="svgRef"
          :viewBox="computedViewBox"
          class="w-full"
          style="min-height: 440px; cursor: grab;"
          :style="isPanning ? 'cursor: grabbing' : ''"
          aria-hidden="true"
          @wheel.prevent="onWheel"
          @mousedown="onMousedown"
          @mousemove="onMousemove"
          @mouseup="onMouseup"
          @mouseleave="onMouseup"
        >
          <!-- Edges (parent → child connector lines) -->
          <g class="edges">
            <path
              v-for="(edge, i) in allEdges"
              :key="`edge-${i}`"
              :d="`M${edge.x1},${edge.y1} C${edge.x1},${edge.y1 + 40} ${edge.x2},${edge.y2 - 40} ${edge.x2},${edge.y2}`"
              fill="none"
              stroke="#d1d5db"
              stroke-width="1.5"
            />
          </g>

          <!-- Nodes -->
          <g class="nodes">
            <g
              v-for="node in allLayoutNodes"
              :key="`n-${node.id}`"
              :transform="`translate(${node._x},${node._y})`"
              class="cursor-pointer"
              @click="selectNode(node)"
            >
              <!-- Background rect with RAG colour -->
              <rect
                :width="NODE_W"
                :height="NODE_H"
                rx="8"
                :fill="nodeFill(node.rag)"
                :stroke="node.id === focusedNodeId ? nodeStroke(node.rag) : '#e5e7eb'"
                :stroke-width="node.id === focusedNodeId ? 2.5 : 1"
              />

              <!-- Level badge (small pill) -->
              <rect
                x="8" y="6"
                width="64" height="14"
                rx="7"
                :fill="nodeStroke(node.rag)"
                opacity="0.15"
              />
              <text
                x="40" y="16"
                text-anchor="middle"
                font-size="8"
                :fill="nodeStroke(node.rag)"
                font-weight="600"
                style="text-transform: uppercase"
              >{{ (levelLabels[node.level] ?? node.level).slice(0, 10) }}</text>

              <!-- Title (max 2 lines, truncated) -->
              <foreignObject x="8" y="22" :width="NODE_W - 16" height="28">
                <div
                  xmlns="http://www.w3.org/1999/xhtml"
                  style="font-size:11px; font-weight:600; color:inherit; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; line-height:1.3"
                  :style="`color:${nodeText(node.rag)}`"
                >
                  {{ node.title }}
                </div>
              </foreignObject>

              <!-- Progress bar at bottom -->
              <rect
                x="8" :y="NODE_H - 10"
                :width="NODE_W - 16" height="4"
                rx="2"
                fill="#e5e7eb"
              />
              <rect
                x="8" :y="NODE_H - 10"
                :width="Math.max(0, Math.min(1, node.progress / 100)) * (NODE_W - 16)" height="4"
                rx="2"
                :fill="nodeStroke(node.rag)"
              />
            </g>
          </g>
        </svg>
      </div>

      <!-- Side panel (slides in when a node is selected) -->
      <transition name="slide-panel">
        <div
          v-if="panelOpen && selectedNode"
          class="w-80 bg-white rounded-lg shadow border border-gray-200 p-5 flex-shrink-0"
          role="complementary"
          :aria-label="`Détail : ${selectedNode.title}`"
        >
          <!-- Header -->
          <div class="flex items-start justify-between mb-4">
            <div>
              <span
                class="inline-block text-xs font-semibold px-2 py-0.5 rounded-full mb-2"
                :class="statusClass[selectedNode.status] ?? 'bg-gray-100 text-gray-600'"
              >
                {{ statusLabel[selectedNode.status] ?? selectedNode.status }}
              </span>
              <h2 class="text-base font-semibold text-gray-900 leading-tight">{{ selectedNode.title }}</h2>
            </div>
            <button
              class="ml-2 text-gray-400 hover:text-gray-600 flex-shrink-0"
              aria-label="Fermer le panneau de détail"
              @click="closePanel"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Level badge -->
          <p class="text-xs text-gray-500 mb-3">
            Niveau :
            <span class="font-medium text-gray-700">{{ levelLabels[selectedNode.level] ?? selectedNode.level }}</span>
          </p>

          <!-- Progress bar -->
          <div class="mb-4">
            <div class="flex justify-between text-xs mb-1">
              <span class="text-gray-600 font-medium">Progression</span>
              <span class="font-bold" :style="`color:${nodeStroke(selectedNode.rag)}`">{{ selectedNode.progress?.toFixed(0) ?? 0 }}%</span>
            </div>
            <div class="w-full bg-gray-100 rounded-full h-3">
              <div
                class="h-3 rounded-full transition-all duration-300"
                :style="`width:${Math.max(0, Math.min(100, selectedNode.progress ?? 0))}%; background-color:${nodeStroke(selectedNode.rag)}`"
              />
            </div>
          </div>

          <!-- Linked ratios -->
          <div v-if="selectedNode.linked_ratios?.length">
            <h3 class="text-sm font-semibold text-gray-700 mb-2">Ratios liés</h3>
            <div class="space-y-2">
              <div
                v-for="(ratio, i) in selectedNode.linked_ratios"
                :key="i"
                class="flex items-center justify-between bg-gray-50 rounded-md px-3 py-2"
              >
                <div>
                  <p class="text-xs font-medium text-gray-700">{{ ratio.name }}</p>
                  <p class="text-xs text-gray-500">{{ ratio.value ?? '—' }} {{ ratio.unit }}</p>
                </div>
                <span
                  class="text-xs px-2 py-0.5 rounded-full font-semibold"
                  :class="ratioStatusClass[ratio.status] ?? 'bg-gray-100 text-gray-600'"
                >
                  {{ ratio.status === 'green' ? '✓' : ratio.status === 'amber' ? '~' : '!' }}
                </span>
              </div>
            </div>
          </div>
          <div v-else class="text-xs text-gray-400 italic">Aucun ratio lié à cet objectif.</div>

          <!-- Children count -->
          <div v-if="selectedNode.children?.length" class="mt-4 text-xs text-gray-500">
            <span class="font-medium">{{ selectedNode.children.length }}</span> sous-objectif(s)
          </div>
        </div>
      </transition>
    </div>
  </div>
</template>

<style scoped>
:root {
  --color-primary: #4f46e5;
  --color-success: #16a34a;
  --color-warning: #d97706;
  --color-danger:  #dc2626;
}

.slide-panel-enter-active,
.slide-panel-leave-active {
  transition: transform 0.25s ease, opacity 0.25s ease;
}
.slide-panel-enter-from,
.slide-panel-leave-to {
  transform: translateX(24px);
  opacity: 0;
}
</style>
