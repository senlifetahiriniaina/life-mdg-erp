<template>
  <div ref="menuRef" class="relative">
    <button class="wh-icon-btn" style="width: auto; padding: 0 4px; gap: 6px" aria-haspopup="true" :aria-expanded="open" :aria-label="user?.name || 'User menu'" @click="open = !open">
      <div class="wh-user-avatar">{{ initials }}</div>
    </button>

    <Transition
      enter-active-class="transition ease-out duration-100"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition ease-in duration-75"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-if="open"
        class="wh-user-menu"
        role="menu"
      >
        <div class="wh-user-menu-header">
          <p class="wh-user-menu-name">{{ user?.name }}</p>
          <p class="wh-user-menu-email">{{ user?.email }}</p>
        </div>

        <Link href="/profile" class="wh-user-menu-item" role="menuitem" @click="open = false">
          <i class="pi pi-user" />
          {{ t('nav.profile') }}
        </Link>
        <Link href="/settings" class="wh-user-menu-item" role="menuitem" @click="open = false">
          <i class="pi pi-cog" />
          {{ t('nav.settings') }}
        </Link>

        <div style="border-top: 1px solid var(--border-subtle); margin-top: 4px; padding-top: 4px">
          <button class="wh-user-menu-item wh-user-menu-danger" role="menuitem" @click="logout">
            <i class="pi pi-sign-out" />
            {{ t('auth.logout') }}
          </button>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { onClickOutside } from '@vueuse/core'

const { t } = useI18n()
const page = usePage()
const open = ref(false)
const menuRef = ref<HTMLElement | null>(null)

onClickOutside(menuRef, () => { open.value = false })

const user = computed(() => page.props.auth?.user as { name: string; email: string } | null)
const initials = computed(() => {
  const name = user.value?.name ?? ''
  return name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase() || 'WH'
})

function logout() {
  open.value = false
  router.post('/logout')
}
</script>

<style scoped>
.wh-user-avatar {
  width: 28px; height: 28px; border-radius: 999px;
  background: linear-gradient(135deg, var(--halo-500), var(--halo-700));
  color: #fff; font-size: 11px; font-weight: 600;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; flex-shrink: 0;
}
.wh-user-menu {
  position: absolute; right: 0; top: calc(100% + 6px);
  width: 220px;
  background: var(--bg-canvas); border: 1px solid var(--border-subtle);
  border-radius: var(--r-lg); box-shadow: var(--shadow-lg);
  z-index: var(--z-overlay); overflow: hidden;
  transform-origin: top right;
}
.wh-user-menu-header {
  padding: 12px 14px 10px;
  border-bottom: 1px solid var(--border-subtle);
}
.wh-user-menu-name { margin: 0; font-size: 13px; font-weight: 600; color: var(--fg-1); }
.wh-user-menu-email { margin: 2px 0 0; font-size: 12px; color: var(--fg-3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wh-user-menu-item {
  display: flex; align-items: center; gap: 8px;
  padding: 8px 14px; font-size: 13px; color: var(--fg-2);
  cursor: pointer; text-decoration: none !important;
  transition: background var(--dur-fast); width: 100%;
  background: transparent; border: 0; text-align: left;
}
.wh-user-menu-item:hover { background: var(--bg-sunken); color: var(--fg-1); }
.wh-user-menu-danger { color: var(--danger-fg); }
.wh-user-menu-danger:hover { background: var(--danger-bg); }
</style>
