<template>
  <AppLayout>
    <Head title="Notifications" />

    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Notifications</h1>
        <p class="wh-page-subtitle">{{ pagination.total }} notification{{ pagination.total !== 1 ? 's' : '' }}</p>
      </div>
      <div class="page-actions">
        <button v-if="unreadCount > 0" class="btn btn-secondary" @click="markAllRead">
          Tout marquer comme lu
        </button>
      </div>
    </div>

    <div class="wh-panel">
      <div v-if="loading" style="padding:32px;text-align:center">
        <i class="pi pi-spin pi-spinner" style="font-size:20px" />
      </div>

      <div v-else-if="notifications.length === 0" class="empty-state">
        <i class="pi pi-check-circle" style="font-size:28px;color:var(--fg-4);margin-bottom:8px" />
        <p>Aucune notification</p>
      </div>

      <table v-else class="wh-table">
        <tbody>
          <tr
            v-for="notif in notifications"
            :key="notif.id"
            :class="{ unread: !notif.is_read }"
            style="cursor:pointer"
            @click="handleClick(notif)"
          >
            <td style="width:32px">
              <span v-if="!notif.is_read" class="unread-dot" />
            </td>
            <td>
              <div style="font-weight:600">{{ notif.title }}</div>
              <div style="font-size:13px;color:var(--fg-3)">{{ notif.body }}</div>
            </td>
            <td style="white-space:nowrap;text-align:right;font-size:12px;color:var(--fg-4)">
              {{ new Date(notif.created_at).toLocaleString('fr-FR') }}
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="pagination.last_page > 1" class="pagination-row">
        <button class="btn btn-secondary" :disabled="page <= 1" @click="page--; load()">Précédent</button>
        <span>Page {{ page }} / {{ pagination.last_page }}</span>
        <button class="btn btn-secondary" :disabled="page >= pagination.last_page" @click="page++; load()">Suivant</button>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

const notifications = ref([])
const loading = ref(false)
const page = ref(1)
const pagination = ref({ total: 0, last_page: 1 })
const unreadCount = ref(0)

function mapNotification(row) {
  const data = typeof row.data === 'string' ? JSON.parse(row.data) : (row.data || {})
  return {
    id: row.id,
    title: data.title || '',
    body: data.body || '',
    is_read: !!row.read_at,
    created_at: row.created_at,
    meta: data.meta || {},
  }
}

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/notifications', { params: { page: page.value, per_page: 20 } })
    notifications.value = (data.data || []).map(mapNotification)
    pagination.value = { total: data.total ?? 0, last_page: data.last_page ?? 1 }
    unreadCount.value = notifications.value.filter(n => !n.is_read).length
  } finally {
    loading.value = false
  }
}

async function markAllRead() {
  await axios.post('/api/v1/notifications/read-all')
  notifications.value.forEach(n => { n.is_read = true })
  unreadCount.value = 0
}

async function handleClick(notif) {
  if (!notif.is_read) {
    await axios.post(`/api/v1/notifications/${notif.id}/read`)
    notif.is_read = true
    unreadCount.value = Math.max(0, unreadCount.value - 1)
  }
  if (notif.meta?.action_url) router.visit(notif.meta.action_url)
}

onMounted(load)
</script>

<style scoped>
.unread { background: var(--halo-50, #f0f2ff); }
.unread-dot { display: inline-block; width: 8px; height: 8px; background: var(--halo-600, #3949ab); border-radius: 50%; }
.empty-state { display: flex; flex-direction: column; align-items: center; padding: 48px 16px; color: var(--fg-3); }
.pagination-row { display: flex; align-items: center; justify-content: center; gap: 16px; padding: 16px; }
</style>
