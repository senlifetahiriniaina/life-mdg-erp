<template>
  <div class="bi-embed-wrapper" role="region" :aria-label="dashboard?.name || 'Dashboard'">
    <!-- Loading state -->
    <div v-if="loading" class="bi-embed-loading" aria-live="polite" aria-busy="true">
      <div class="bi-embed-spinner" aria-hidden="true"></div>
      <span class="bi-embed-loading-text">{{ t('Loading dashboard…') }}</span>
    </div>

    <!-- Error state -->
    <div v-else-if="error" class="bi-embed-error" role="alert">
      <svg class="bi-embed-error-icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />
      </svg>
      <p>{{ error }}</p>
    </div>

    <!-- Dashboard content -->
    <template v-else-if="dashboard">
      <header class="bi-embed-header">
        <h1 class="bi-embed-title">{{ dashboard.name }}</h1>
        <p v-if="dashboard.description" class="bi-embed-desc">{{ dashboard.description }}</p>
        <span class="bi-embed-badge" aria-label="Read-only embed">
          <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" width="12" height="12">
            <path d="M8 1a7 7 0 1 0 0 14A7 7 0 0 0 8 1zm0 1.5a5.5 5.5 0 1 1 0 11A5.5 5.5 0 0 1 8 2.5zM7.25 5v4.5h1.5V5h-1.5zm0 5.5V12h1.5v-1.5h-1.5z"/>
          </svg>
          {{ t('Read-only') }}
        </span>
      </header>

      <div class="bi-embed-grid">
        <article
          v-for="widget in widgets"
          :key="widget.widget_id"
          class="bi-embed-widget"
          :aria-labelledby="`widget-title-${widget.widget_id}`"
        >
          <h2 :id="`widget-title-${widget.widget_id}`" class="bi-embed-widget-title">
            {{ widget.title }}
          </h2>
          <div class="bi-embed-widget-type">{{ widget.type }}</div>
          <!-- Config preview as formatted JSON for prototype; replace with chart components in prod -->
          <pre v-if="widget.config && Object.keys(widget.config).length" class="bi-embed-widget-config">{{ JSON.stringify(widget.config, null, 2) }}</pre>
          <div v-else class="bi-embed-widget-empty">{{ t('No data available') }}</div>
        </article>
      </div>

      <footer class="bi-embed-footer">
        <span>{{ t('Powered by WideHalo BI') }}</span>
      </footer>
    </template>
  </div>
</template>

<script setup>
/**
 * EmbeddableDashboard — iframe-ready BI dashboard component.
 *
 * Usage inside an iframe:
 *   <iframe src="/embed/bi?token=<JWT>&dashboard_id=7"></iframe>
 *
 * Or mount directly in a Vue app and pass the token as a prop:
 *   <EmbeddableDashboard :embed-token="token" :dashboard-id="7" />
 *
 * postMessage API:
 *   The component emits window.postMessage events to the parent frame:
 *     { type: 'bi:ready',  height: <number> }        — emitted once loaded
 *     { type: 'bi:resize', height: <number> }        — emitted on content change
 *     { type: 'bi:error',  message: <string> }       — emitted on load failure
 */
import { ref, onMounted, onUnmounted, nextTick } from 'vue'

// ── Props ──────────────────────────────────────────────────────────────────
const props = defineProps({
  /** Signed HS256 embed JWT. If omitted, read from URL ?embed_token param. */
  embedToken: {
    type: String,
    default: null,
  },
  /** Dashboard ID. If omitted, read from URL ?dashboard_id param. */
  dashboardId: {
    type: [Number, String],
    default: null,
  },
  /** Base API URL (defaults to same origin). */
  apiBase: {
    type: String,
    default: '',
  },
  /** UI locale — determines display language of static strings. */
  locale: {
    type: String,
    default: 'fr',
  },
})

// ── State ──────────────────────────────────────────────────────────────────
const loading  = ref(true)
const error    = ref(null)
const dashboard = ref(null)
const widgets  = ref([])

// ── i18n (minimal inline) ─────────────────────────────────────────────────
const strings = {
  fr: {
    'Loading dashboard…': 'Chargement du tableau de bord…',
    'Read-only': 'Lecture seule',
    'No data available': 'Aucune donnée disponible',
    'Powered by WideHalo BI': 'Propulsé par WideHalo BI',
    'Token required': 'Jeton d\'intégration requis.',
    'Dashboard ID required': 'Identifiant de tableau de bord requis.',
  },
  en: {
    'Loading dashboard…': 'Loading dashboard…',
    'Read-only': 'Read-only',
    'No data available': 'No data available',
    'Powered by WideHalo BI': 'Powered by WideHalo BI',
    'Token required': 'Embed token required.',
    'Dashboard ID required': 'Dashboard ID required.',
  },
}

function t(key) {
  return strings[props.locale]?.[key] ?? strings.en[key] ?? key
}

// ── Helpers ────────────────────────────────────────────────────────────────
function getUrlParam(name) {
  if (typeof window === 'undefined') return null
  return new URLSearchParams(window.location.search).get(name)
}

function postMessageToParent(payload) {
  if (typeof window !== 'undefined' && window.parent !== window) {
    window.parent.postMessage(payload, '*')
  }
}

function emitResize() {
  nextTick(() => {
    const height = document.body ? document.body.scrollHeight : 0
    postMessageToParent({ type: 'bi:resize', height })
  })
}

// ── Load dashboard data ────────────────────────────────────────────────────
async function loadDashboard() {
  const token       = props.embedToken || getUrlParam('embed_token')
  const dashboardId = props.dashboardId || getUrlParam('dashboard_id')

  if (!token) {
    error.value = t('Token required')
    loading.value = false
    postMessageToParent({ type: 'bi:error', message: error.value })
    return
  }
  if (!dashboardId) {
    error.value = t('Dashboard ID required')
    loading.value = false
    postMessageToParent({ type: 'bi:error', message: error.value })
    return
  }

  try {
    const url = `${props.apiBase}/api/v1/bi/embed/dashboard/${dashboardId}?embed_token=${encodeURIComponent(token)}`
    const response = await fetch(url, {
      headers: { Accept: 'application/json' },
    })

    if (!response.ok) {
      const body = await response.json().catch(() => ({}))
      throw new Error(body.message || `HTTP ${response.status}`)
    }

    const data = await response.json()
    dashboard.value = data.dashboard
    widgets.value   = data.widgets ?? []
    loading.value   = false

    nextTick(() => {
      const height = document.body ? document.body.scrollHeight : 0
      postMessageToParent({ type: 'bi:ready', height })
      emitResize()
    })
  } catch (err) {
    error.value   = err.message
    loading.value = false
    postMessageToParent({ type: 'bi:error', message: err.message })
  }
}

// ── Lifecycle ──────────────────────────────────────────────────────────────
let resizeObserver = null

onMounted(() => {
  loadDashboard()

  // Emit resize on content changes (e.g. expanded widgets)
  if (typeof ResizeObserver !== 'undefined' && document.body) {
    resizeObserver = new ResizeObserver(emitResize)
    resizeObserver.observe(document.body)
  }
})

onUnmounted(() => {
  resizeObserver?.disconnect()
})
</script>

<style scoped>
/* Design tokens — override via CSS custom properties */
.bi-embed-wrapper {
  --bi-font: system-ui, -apple-system, 'Segoe UI', sans-serif;
  --bi-color-bg: #ffffff;
  --bi-color-surface: #f8f9fa;
  --bi-color-border: #e2e8f0;
  --bi-color-text: #1a202c;
  --bi-color-muted: #718096;
  --bi-color-brand: #3b82f6;
  --bi-color-error: #e53e3e;
  --bi-radius: 8px;
  --bi-shadow: 0 1px 3px rgba(0,0,0,.08);
  --bi-gap: 1rem;

  font-family: var(--bi-font);
  color: var(--bi-color-text);
  background: var(--bi-color-bg);
  padding: 1.25rem;
  box-sizing: border-box;
  min-height: 100px;
}

/* Loading */
.bi-embed-loading {
  display: flex;
  align-items: center;
  gap: .75rem;
  padding: 2rem;
  justify-content: center;
  color: var(--bi-color-muted);
}
.bi-embed-spinner {
  width: 24px; height: 24px;
  border: 3px solid var(--bi-color-border);
  border-top-color: var(--bi-color-brand);
  border-radius: 50%;
  animation: bi-spin .8s linear infinite;
}
@keyframes bi-spin { to { transform: rotate(360deg); } }

/* Error */
.bi-embed-error {
  display: flex;
  align-items: flex-start;
  gap: .75rem;
  padding: 1.25rem;
  background: #fff5f5;
  border: 1px solid #feb2b2;
  border-radius: var(--bi-radius);
  color: var(--bi-color-error);
}
.bi-embed-error-icon { width: 20px; height: 20px; flex-shrink: 0; margin-top: 2px; }
.bi-embed-error p { margin: 0; font-size: .9rem; }

/* Header */
.bi-embed-header {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: .5rem;
  margin-bottom: 1.25rem;
  padding-bottom: .75rem;
  border-bottom: 1px solid var(--bi-color-border);
}
.bi-embed-title {
  font-size: 1.25rem;
  font-weight: 600;
  margin: 0;
  line-height: 1.3;
  flex: 1 1 auto;
}
.bi-embed-desc {
  width: 100%;
  font-size: .85rem;
  color: var(--bi-color-muted);
  margin: .25rem 0 0;
}
.bi-embed-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: .7rem;
  padding: 2px 8px;
  border-radius: 999px;
  background: #ebf8ff;
  color: #2b6cb0;
  white-space: nowrap;
}

/* Widget grid */
.bi-embed-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 280px), 1fr));
  gap: var(--bi-gap);
}
.bi-embed-widget {
  background: var(--bi-color-surface);
  border: 1px solid var(--bi-color-border);
  border-radius: var(--bi-radius);
  box-shadow: var(--bi-shadow);
  padding: 1rem;
  overflow: hidden;
}
.bi-embed-widget-title {
  font-size: .95rem;
  font-weight: 600;
  margin: 0 0 .35rem;
  line-height: 1.3;
}
.bi-embed-widget-type {
  display: inline-block;
  font-size: .7rem;
  text-transform: uppercase;
  letter-spacing: .05em;
  color: var(--bi-color-muted);
  background: var(--bi-color-border);
  padding: 1px 6px;
  border-radius: 4px;
  margin-bottom: .5rem;
}
.bi-embed-widget-config {
  font-size: .75rem;
  background: #1a202c;
  color: #e2e8f0;
  border-radius: 4px;
  padding: .5rem;
  overflow: auto;
  max-height: 160px;
  margin: .5rem 0 0;
  white-space: pre-wrap;
  word-break: break-all;
}
.bi-embed-widget-empty {
  font-size: .8rem;
  color: var(--bi-color-muted);
  font-style: italic;
  margin-top: .5rem;
}

/* Footer */
.bi-embed-footer {
  margin-top: 1.25rem;
  padding-top: .75rem;
  border-top: 1px solid var(--bi-color-border);
  font-size: .75rem;
  color: var(--bi-color-muted);
  text-align: right;
}

/* Responsive */
@media (max-width: 480px) {
  .bi-embed-wrapper { padding: .75rem; }
  .bi-embed-grid { grid-template-columns: 1fr; }
}
</style>
