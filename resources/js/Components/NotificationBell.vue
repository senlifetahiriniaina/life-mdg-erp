<template>
  <div class="notification-bell" ref="bellRef">
    <!-- Bell button -->
    <button
      :class="['bell-btn', { active: isOpen }]"
      :title="`${unreadCount} notification${unreadCount !== 1 ? 's' : ''} non lue${unreadCount !== 1 ? 's' : ''}`"
      @click="toggleDropdown"
    >
      <i class="pi pi-bell" style="font-size:18px" />
      <!-- Unread badge -->
      <span v-if="unreadCount > 0" class="bell-badge">
        {{ unreadCount > 99 ? '99+' : unreadCount }}
      </span>
    </button>

    <!-- Dropdown panel -->
    <Teleport to="body">
      <div
        v-if="isOpen"
        class="notif-backdrop"
        @click="isOpen = false"
      />
      <div
        v-if="isOpen"
        class="notif-dropdown"
        :style="dropdownStyle"
      >
        <!-- Header -->
        <div class="notif-header">
          <span style="font-weight:700;font-size:14px;color:var(--fg-1)">Notifications</span>
          <button
            v-if="unreadCount > 0"
            class="mark-all-btn"
            @click="markAllRead"
          >
            Tout marquer comme lu
          </button>
        </div>

        <!-- Loading state -->
        <div v-if="loading" style="padding:32px;text-align:center">
          <i class="pi pi-spin pi-spinner" style="font-size:20px;color:var(--halo-500)" />
        </div>

        <!-- Notification list -->
        <div v-else class="notif-list">
          <div
            v-for="notif in notifications.slice(0, 10)"
            :key="notif.id"
            :class="['notif-item', { unread: !notif.is_read }]"
            @click="handleNotifClick(notif)"
          >
            <!-- Type icon -->
            <div :class="['notif-icon', `notif-icon-${notif.type}`]">
              <i :class="typeIcon(notif.type)" style="font-size:13px" />
            </div>

            <!-- Content -->
            <div class="notif-content">
              <div class="notif-title">{{ notif.title }}</div>
              <div class="notif-body">{{ truncate(notif.body, 80) }}</div>
              <div class="notif-time">{{ relativeTime(notif.created_at) }}</div>
            </div>

            <!-- Unread dot -->
            <span v-if="!notif.is_read" class="unread-dot" />
          </div>

          <!-- Empty state -->
          <div v-if="notifications.length === 0" class="notif-empty">
            <i class="pi pi-check-circle" style="font-size:28px;color:var(--fg-4);margin-bottom:8px" />
            <p>Aucune notification</p>
          </div>
        </div>

        <!-- Footer -->
        <div class="notif-footer">
          <a href="/notifications" style="color:var(--halo-600);font-size:13px;font-weight:500;text-decoration:none" @click.prevent="goToAll">
            Voir toutes les notifications
            <i class="pi pi-arrow-right" style="font-size:11px" />
          </a>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { router } from '@inertiajs/vue3'

// State
const isOpen       = ref(false)
const loading      = ref(false)
const notifications = ref([])
const unreadCount   = ref(0)
const bellRef       = ref(null)
const dropdownStyle = ref({})

let pollInterval = null

// Demo data
const DEMO_NOTIFICATIONS = [
  { id: 1, type: 'info',    title: 'Nouvelle commande reçue', body: 'Commande CMD-2026-0042 de Mamadou Diop • 450 000 XOF', is_read: false, created_at: new Date(Date.now() - 3 * 60000).toISOString(), meta: { action_url: '/sales/orders/42' } },
  { id: 2, type: 'warning', title: 'Stock critique : Tissu wax', body: 'Le stock du produit "Tissu wax bleu" est tombé sous le seuil minimal (5 rouleaux).', is_read: false, created_at: new Date(Date.now() - 18 * 60000).toISOString(), meta: { action_url: '/inventory/products/12' } },
  { id: 3, type: 'success', title: 'Paiement reçu', body: 'Facture FAC-2026-0128 • 1 200 000 XOF — Orange Money', is_read: false, created_at: new Date(Date.now() - 45 * 60000).toISOString(), meta: { action_url: '/accounting/invoices/128' } },
  { id: 4, type: 'error',   title: 'Échec d\'envoi de campagne', body: '55 messages WhatsApp n\'ont pas pu être livrés dans la campagne "Promo Ramadan".', is_read: true, created_at: new Date(Date.now() - 2 * 3600000).toISOString(), meta: { action_url: '/messaging/campaigns/1' } },
  { id: 5, type: 'info',    title: 'Congé approuvé', body: 'Votre demande de congé du 01/06 au 07/06/2026 a été approuvée par Marie Diallo.', is_read: true, created_at: new Date(Date.now() - 5 * 3600000).toISOString(), meta: { action_url: '/hr/leaves' } },
  { id: 6, type: 'warning', title: 'Approbation requise', body: 'Facture fournisseur FA-2026-0078 • 3 500 000 XOF attend votre validation.', is_read: true, created_at: new Date(Date.now() - 24 * 3600000).toISOString(), meta: { action_url: '/accounting/invoices/78' } },
]

// Actions
function toggleDropdown() {
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    positionDropdown()
    loadNotifications()
  }
}

function positionDropdown() {
  nextTick(() => {
    if (!bellRef.value) return
    const rect = bellRef.value.getBoundingClientRect()
    dropdownStyle.value = {
      position: 'fixed',
      top:   `${rect.bottom + 8}px`,
      right: `${window.innerWidth - rect.right}px`,
      zIndex: 9999,
    }
  })
}

async function loadNotifications() {
  loading.value = true
  await new Promise(r => setTimeout(r, 300))
  notifications.value = DEMO_NOTIFICATIONS
  unreadCount.value   = DEMO_NOTIFICATIONS.filter(n => !n.is_read).length
  loading.value = false
}

async function fetchUnreadCount() {
  // In production: GET /api/v1/notifications/unread-count
  unreadCount.value = DEMO_NOTIFICATIONS.filter(n => !n.is_read).length
}

async function markAllRead() {
  notifications.value.forEach(n => { n.is_read = true })
  unreadCount.value = 0
  // In production: POST /api/v1/notifications/mark-read
}

function handleNotifClick(notif) {
  notif.is_read = true
  unreadCount.value = Math.max(0, unreadCount.value - 1)
  isOpen.value = false
  const url = notif.meta?.action_url
  if (url) router.visit(url)
}

function goToAll() {
  isOpen.value = false
  router.visit('/notifications')
}

// Helpers
function typeIcon(type) {
  return { success: 'pi pi-check-circle', warning: 'pi pi-exclamation-triangle', error: 'pi pi-times-circle', info: 'pi pi-info-circle' }[type] ?? 'pi pi-bell'
}
function truncate(str, len) { return str && str.length > len ? str.slice(0, len) + '…' : str }
function relativeTime(iso) {
  if (!iso) return ''
  const diff = Date.now() - new Date(iso).getTime()
  if (diff < 60000)       return 'À l\'instant'
  if (diff < 3600000)     return `Il y a ${Math.floor(diff / 60000)} min`
  if (diff < 86400000)    return `Il y a ${Math.floor(diff / 3600000)} h`
  return new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' })
}

// Lifecycle
onMounted(() => {
  fetchUnreadCount()
  // Poll every 30 seconds
  pollInterval = setInterval(fetchUnreadCount, 30000)
})

onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval)
})
</script>

<style scoped>
.notification-bell {
  position: relative;
  display: flex;
  align-items: center;
}

/* Bell button */
.bell-btn {
  position: relative;
  background: none;
  border: none;
  cursor: pointer;
  padding: 8px;
  border-radius: 8px;
  color: var(--fg-2);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.15s, color 0.15s;
}
.bell-btn:hover { background: var(--surface-2); color: var(--fg-1) }
.bell-btn.active { background: var(--halo-50); color: var(--halo-700) }

.bell-badge {
  position: absolute;
  top: 2px;
  right: 2px;
  background: var(--red-500);
  color: #fff;
  border-radius: 10px;
  font-size: 9px;
  font-weight: 800;
  min-width: 16px;
  height: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 3px;
  border: 2px solid var(--surface-0, #fff);
  line-height: 1;
}

/* Backdrop */
.notif-backdrop {
  position: fixed;
  inset: 0;
  z-index: 9998;
}

/* Dropdown */
.notif-dropdown {
  width: 320px;
  background: var(--surface-0, #fff);
  border: 1px solid var(--border-subtle, var(--border-subtle));
  border-radius: 12px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  max-height: 480px;
}

.notif-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px;
  border-bottom: 1px solid var(--border-subtle, var(--border-subtle));
  background: var(--surface-0, #fff);
}
.mark-all-btn {
  background: none;
  border: none;
  font-size: 12px;
  color: var(--halo-600, #3949ab);
  cursor: pointer;
  font-weight: 500;
  padding: 0;
}
.mark-all-btn:hover { text-decoration: underline }

/* List */
.notif-list { overflow-y: auto; flex: 1 }
.notif-item {
  display: flex;
  gap: 10px;
  padding: 12px 16px;
  border-bottom: 1px solid var(--border-subtle, var(--border-subtle));
  cursor: pointer;
  transition: background 0.15s;
  position: relative;
}
.notif-item:hover { background: var(--surface-2, #f4f4f7) }
.notif-item.unread { background: var(--halo-50, #f0f2ff) }
.notif-item:last-child { border-bottom: none }

/* Icon */
.notif-icon {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.notif-icon-success { background: var(--success-bg); color: var(--success-fg) }
.notif-icon-warning { background: var(--warn-bg); color: var(--warn-fg) }
.notif-icon-error   { background: var(--danger-bg); color: var(--danger-fg) }
.notif-icon-info    { background: var(--halo-50); color: var(--halo-700) }

.notif-content { flex: 1; min-width: 0 }
.notif-title { font-size: 13px; font-weight: 600; color: var(--fg-1, #1a1a2e); margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis }
.notif-body  { font-size: 12px; color: var(--fg-3, #6b6b80); line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden }
.notif-time  { font-size: 11px; color: var(--fg-4, #9a9ab0); margin-top: 4px }

.unread-dot {
  width: 8px;
  height: 8px;
  background: var(--halo-600, #3949ab);
  border-radius: 50%;
  flex-shrink: 0;
  margin-top: 4px;
}

/* Empty */
.notif-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 40px 16px;
  color: var(--fg-3, #6b6b80);
  font-size: 13px;
  gap: 4px;
}

/* Footer */
.notif-footer {
  padding: 12px 16px;
  border-top: 1px solid var(--border-subtle, var(--border-subtle));
  text-align: center;
  background: var(--surface-1, var(--bg-subtle));
}
</style>
