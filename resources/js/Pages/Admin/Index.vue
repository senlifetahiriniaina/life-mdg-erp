<template>
  <AppLayout>
    <div class="p-6 space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50 text-surface-900 dark:text-surface-0">
            Administration
          </h1>
          <p class="text-surface-500 mt-1">Vue d'ensemble du panneau administrateur</p>
        </div>
      </div>

      <!-- KPI Cards -->
      <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-4 shadow-sm border border-surface-200 dark:border-surface-700">
          <div class="text-surface-500 text-sm mb-1">Utilisateurs</div>
          <div class="text-2xl font-bold text-surface-900 dark:text-surface-50 text-primary-600">{{ stats.total_users }}</div>
        </div>
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-4 shadow-sm border border-surface-200 dark:border-surface-700">
          <div class="text-surface-500 text-sm mb-1">Serveurs actifs</div>
          <div class="text-2xl font-bold text-surface-900 dark:text-surface-50 text-green-600">{{ stats.active_servers }}</div>
        </div>
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-4 shadow-sm border border-surface-200 dark:border-surface-700">
          <div class="text-surface-500 text-sm mb-1">Dernière sauvegarde</div>
          <div class="text-sm font-semibold text-surface-700 dark:text-surface-200">
            {{ stats.last_backup_at ? formatDate(stats.last_backup_at) : 'Aucune' }}
          </div>
        </div>
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-4 shadow-sm border border-surface-200 dark:border-surface-700">
          <div class="text-surface-500 text-sm mb-1">Stockage utilisé</div>
          <div class="text-2xl font-bold text-surface-900 dark:text-surface-50 text-blue-600">{{ formatBytes(stats.storage_used_bytes) }}</div>
        </div>
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl p-4 shadow-sm border border-surface-200 dark:border-surface-700">
          <div class="text-surface-500 text-sm mb-1">Alertes audit</div>
          <div class="text-2xl font-bold text-surface-900 dark:text-surface-50" :class="stats.pending_audit_alerts > 0 ? 'text-red-600' : 'text-green-600'">
            {{ stats.pending_audit_alerts }}
          </div>
        </div>
      </div>

      <!-- Navigation tiles -->
      <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
        <Link
          v-for="tile in tiles"
          :key="tile.href"
          :href="tile.href"
          class="bg-surface-0 dark:bg-surface-800 rounded-xl p-6 shadow-sm border border-surface-200 dark:border-surface-700 hover:border-primary-400 hover:shadow-md transition-all cursor-pointer group"
        >
          <div class="text-3xl mb-3">{{ tile.icon }}</div>
          <div class="font-semibold text-surface-900 dark:text-surface-0 group-hover:text-primary-600">
            {{ tile.title }}
          </div>
          <div class="text-surface-500 text-sm mt-1">{{ tile.description }}</div>
        </Link>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineProps({
  stats: {
    type: Object,
    default: () => ({
      total_users: 0,
      active_servers: 0,
      last_backup_at: null,
      storage_used_bytes: 0,
      pending_audit_alerts: 0,
    }),
  },
})

const tiles = [
  {
    icon: '🖥️',
    title: 'Serveurs & Infrastructure',
    description: 'Gérez vos serveurs cloud (GCP, AWS, Azure...)',
    href: '/admin/servers',
  },
  {
    icon: '💾',
    title: 'Sauvegardes',
    description: 'Déclenchez et planifiez vos sauvegardes',
    href: '/admin/backups',
  },
  {
    icon: '👥',
    title: 'Utilisateurs & Rôles',
    description: 'Gérez les accès et les permissions',
    href: '/admin/users',
  },
  {
    icon: '📋',
    title: 'Logs d\'audit',
    description: 'Tracez toutes les actions sensibles',
    href: '/admin/audit',
  },
  {
    icon: '🔐',
    title: 'Sécurité',
    description: 'Politiques 2FA, sessions actives, API keys',
    href: '/admin/audit',
  },
  {
    icon: '💳',
    title: 'Facturation',
    description: 'Plans, abonnements et factures',
    href: '/admin/audit',
  },
  {
    icon: '📦',
    title: 'Sandboxes',
    description: 'Clones de tenant jetables pour tester en isolation',
    href: '/admin/sandboxes',
  },
  {
    icon: '🔄',
    title: 'Échanges inter-tenant',
    description: 'Partage de données entre tenants avec validation bilatérale',
    href: '/admin/exchanges',
  },
]

const formatDate = (iso) => {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const formatBytes = (bytes) => {
  if (!bytes) return '0 B'
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(1024))
  return `${(bytes / Math.pow(1024, i)).toFixed(1)} ${sizes[i]}`
}
</script>
