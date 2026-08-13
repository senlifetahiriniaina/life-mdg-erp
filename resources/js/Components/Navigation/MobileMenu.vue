<template>
  <div class="mobile-menu-wrapper">
    <!-- Menu Toggle Button -->
    <button
      class="menu-toggle"
      :aria-expanded="isOpen"
      aria-label="Toggle navigation menu"
      @click="isOpen = !isOpen"
    >
      <i :class="['pi', isOpen ? 'pi-times' : 'pi-bars']"></i>
    </button>

    <!-- Mobile Menu Overlay -->
    <transition name="menu-overlay">
      <div v-if="isOpen" class="menu-overlay" @click="isOpen = false"></div>
    </transition>

    <!-- Mobile Menu Panel -->
    <transition name="menu-slide">
      <nav v-if="isOpen" class="mobile-menu" role="navigation" aria-label="Mobile navigation">
        <div class="menu-header">
          <h2 class="menu-title">{{ title }}</h2>
          <button
            class="menu-close"
            aria-label="Close menu"
            @click="isOpen = false"
          >
            <i class="pi pi-times"></i>
          </button>
        </div>

        <ul class="menu-items">
          <li v-for="item in items" :key="item.id" class="menu-item">
            <a
              :href="item.href"
              class="menu-link"
              :aria-current="item.active ? 'page' : undefined"
              @click="item.click && item.click()"
            >
              <i v-if="item.icon" :class="['menu-icon', item.icon]"></i>
              <span class="menu-label">{{ item.label }}</span>
              <i v-if="item.badge" class="menu-badge">{{ item.badge }}</i>
            </a>
          </li>
        </ul>

        <div v-if="$slots.footer" class="menu-footer">
          <slot name="footer" />
        </div>
      </nav>
    </transition>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'

interface MenuItem {
  id: string | number
  label: string
  href: string
  icon?: string
  badge?: string | number
  active?: boolean
  click?: () => void
}

interface Props {
  items: MenuItem[]
  title?: string
}

withDefaults(defineProps<Props>(), {
  title: 'Menu',
})

const isOpen = ref(false)
</script>

<style scoped>
.mobile-menu-wrapper {
  position: relative;
}

.menu-toggle {
  display: none;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  padding: 0;
  background: none;
  border: none;
  cursor: pointer;
  color: var(--fg-1);
  font-size: 1.25rem;
  transition: opacity 0.2s;
}

.menu-toggle:hover {
  opacity: 0.7;
}

.menu-toggle:focus-visible {
  outline: 2px solid var(--halo-500);
  outline-offset: 2px;
  border-radius: 4px;
}

@media (max-width: 768px) {
  .menu-toggle {
    display: flex;
  }
}

.menu-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
  z-index: 999;
}

.menu-overlay-enter-active,
.menu-overlay-leave-active {
  transition: opacity 0.3s ease;
}

.menu-overlay-enter-from,
.menu-overlay-leave-to {
  opacity: 0;
}

.mobile-menu {
  position: fixed;
  top: 0;
  left: 0;
  bottom: 0;
  width: 280px;
  max-width: 100%;
  background: white;
  z-index: 1000;
  display: flex;
  flex-direction: column;
  box-shadow: 2px 0 8px rgba(0, 0, 0, 0.15);
}

.menu-slide-enter-active,
.menu-slide-leave-active {
  transition: transform 0.3s ease;
}

.menu-slide-enter-from,
.menu-slide-leave-to {
  transform: translateX(-100%);
}

.menu-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem;
  border-bottom: 1px solid var(--border-subtle);
}

.menu-title {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--fg-1);
}

.menu-close {
  width: 40px;
  height: 40px;
  padding: 0;
  background: none;
  border: none;
  cursor: pointer;
  color: var(--fg-3);
  font-size: 1.25rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 4px;
  transition: background-color 0.2s;
}

.menu-close:hover {
  background-color: var(--bg-subtle);
}

.menu-close:focus-visible {
  outline: 2px solid var(--halo-500);
  outline-offset: 2px;
}

.menu-items {
  flex: 1;
  list-style: none;
  margin: 0;
  padding: 0;
  overflow-y: auto;
}

.menu-item {
  border-bottom: 1px solid var(--bg-subtle);
}

.menu-link {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 1rem;
  color: var(--fg-1);
  text-decoration: none;
  transition: background-color 0.2s;
  min-height: 44px;
}

.menu-link:hover {
  background-color: var(--bg-subtle);
}

.menu-link[aria-current='page'] {
  background-color: var(--halo-50);
  color: var(--halo-500);
  font-weight: 500;
}

.menu-link:focus-visible {
  outline: 2px solid var(--halo-500);
  outline-offset: -2px;
}

.menu-icon {
  font-size: 1.25rem;
  flex-shrink: 0;
}

.menu-label {
  flex: 1;
}

.menu-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 24px;
  height: 24px;
  padding: 0 6px;
  background-color: var(--red-500);
  color: white;
  font-size: 0.75rem;
  font-weight: 600;
  border-radius: 12px;
  flex-shrink: 0;
}

.menu-footer {
  padding: 1rem;
  border-top: 1px solid var(--border-subtle);
}
</style>
