<template>
  <AppLayout>
    <div class="page-head">
      <div>
        <h1 class="wh-page-title">Paramètres</h1>
        <p class="wh-page-subtitle">Configurez vos préférences.</p>
      </div>
    </div>

    <div class="wh-panel">
      <Tabs v-model:value="activeTab">
        <TabList style="padding:0 18px;border-bottom:1px solid var(--border-subtle)">
          <Tab value="general">Général</Tab>
          <Tab value="notifications">Notifications</Tab>
          <Tab value="modules">Modules</Tab>
        </TabList>

        <TabPanels>
          <!-- General Tab -->
          <TabPanel value="general">
            <div style="padding:24px;display:flex;flex-direction:column;gap:20px">
              <div style="max-width:280px;display:flex;flex-direction:column;gap:6px">
                <label style="font-size:13px;font-weight:500;color:var(--fg-2)">Langue</label>
                <Select
                  v-model="selectedLocale"
                  :options="locales"
                  option-label="label"
                  option-value="value"
                  style="width:100%"
                  @change="onLocaleChange"
                />
              </div>
            </div>
          </TabPanel>

          <!-- Notifications Tab -->
          <TabPanel value="notifications">
            <div style="padding:8px 0">
              <div class="settings-row" v-for="n in notifRows" :key="n.key">
                <div>
                  <p style="font-size:14px;font-weight:500;color:var(--fg-1);margin:0">{{ n.label }}</p>
                  <p style="font-size:13px;color:var(--fg-3);margin:2px 0 0">{{ n.desc }}</p>
                </div>
                <ToggleSwitch v-model="notifications[n.key]" />
              </div>
            </div>
          </TabPanel>

          <!-- Modules Tab -->
          <TabPanel value="modules">
            <div style="padding:24px">
              <p style="font-size:13px;color:var(--fg-3);margin:0 0 16px">Modules actifs pour votre compte.</p>
              <div v-if="enabledModules.length > 0" style="display:flex;flex-direction:column;gap:8px">
                <div
                  v-for="mod in enabledModules"
                  :key="mod"
                  class="module-row"
                >
                  <i class="pi pi-check-circle" style="color:var(--success-fg,#065F46);font-size:14px" />
                  <span style="font-weight:500;color:var(--fg-1)">{{ mod }}</span>
                  <span class="mod-active-pill">Actif</span>
                </div>
              </div>
              <div v-else style="text-align:center;padding:32px;color:var(--fg-3)">
                <i class="pi pi-inbox" style="font-size:32px;display:block;margin-bottom:12px" />
                <p style="font-size:13px;margin:0">Aucun module configuré.</p>
              </div>
            </div>
          </TabPanel>
        </TabPanels>
      </Tabs>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'

const page = usePage()
const activeTab = ref('general')
const enabledModules = computed(() => (page.props.enabledModules as string[]) || [])

const locales = [
  { value: 'en', label: 'Anglais (EN)' },
  { value: 'fr', label: 'Français (FR)' },
  { value: 'pt', label: 'Portugais (PT)' },
  { value: 'es', label: 'Espagnol (ES)' },
]
const selectedLocale = ref('fr')

type NotificationKey = 'email' | 'push' | 'marketing' | 'digest'

const notifications = ref<Record<NotificationKey, boolean>>({ email: true, push: true, marketing: false, digest: true })

const notifRows: Array<{ key: NotificationKey; label: string; desc: string }> = [
  { key: 'email',     label: 'Notifications email',    desc: 'Recevoir des notifications par email' },
  { key: 'push',      label: 'Notifications push',     desc: 'Recevoir des notifications in-app' },
  { key: 'marketing', label: 'Emails marketing',       desc: 'Recevoir les mises à jour et annonces' },
  { key: 'digest',    label: 'Récapitulatif hebdo',    desc: 'Recevoir un résumé hebdomadaire' },
]

const onLocaleChange = ({ value }: { value: string }) => {
  router.patch('/profile', { locale: value })
}
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; gap:16px; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:28px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:14px; color:var(--fg-2); }
.settings-row { display:flex; align-items:center; justify-content:space-between; padding:16px 24px; border-bottom:1px solid var(--border-subtle); gap:16px; }
.settings-row:last-child { border-bottom:0; }
.module-row { display:flex; align-items:center; gap:10px; padding:12px 16px; background:var(--bg-sunken); border:1px solid var(--border-subtle); border-radius:var(--r-md); }
.mod-active-pill { margin-left:auto; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--success-fg,#065F46); }
</style>
