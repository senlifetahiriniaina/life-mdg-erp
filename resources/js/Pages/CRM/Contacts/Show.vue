<template>
  <AppLayout>
    <Head :title="contact.full_name" />

    <!-- Header -->
    <div class="page-head">
      <div style="display:flex;align-items:center;gap:14px">
        <button class="btn btn-icon" @click="$inertia.visit(route('crm.contacts.index'))">
          <i class="pi pi-arrow-left" style="font-size:14px" />
        </button>
        <div class="contact-avatar">{{ initials }}</div>
        <div>
          <h1 class="wh-page-title">{{ contact.full_name }}</h1>
          <p class="wh-page-subtitle">{{ contact.email }}</p>
        </div>
      </div>
      <div class="page-actions">
        <button class="btn btn-secondary" @click="openEditModal">
          <i class="pi pi-pencil" style="font-size:13px" /> Modifier
        </button>
        <a v-if="contact.email" :href="`mailto:${contact.email}`" class="btn btn-primary">
          <i class="pi pi-envelope" style="font-size:13px" /> Envoyer un email
        </a>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:280px 1fr;gap:16px">
      <!-- Details panel -->
      <div class="wh-panel" style="padding:20px">
        <h3 class="panel-section-title">Informations</h3>
        <div style="display:flex;flex-direction:column;gap:14px;margin-top:12px">
          <div v-for="field in fields" :key="field.label" style="display:flex;gap:10px;align-items:flex-start">
            <i :class="field.icon" style="font-size:13px;color:var(--fg-4);margin-top:2px;width:14px;flex-shrink:0" />
            <div>
              <p style="font-size:11px;color:var(--fg-3);text-transform:uppercase;letter-spacing:0.06em;margin:0">{{ field.label }}</p>
              <p style="font-size:13px;font-weight:500;color:var(--fg-1);margin:2px 0 0;word-break:break-all">{{ field.value || '—' }}</p>
            </div>
          </div>
        </div>
        <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border-subtle)">
          <span :class="['wh-badge', statusClass]">
            <span class="wh-badge-dot" />{{ statusLabel }}
          </span>
        </div>
      </div>

      <!-- Activity timeline -->
      <div class="wh-panel">
        <div class="wh-panel-head">
          <h3>Activité</h3>
          <button class="btn btn-secondary" style="font-size:12px;padding:5px 10px">
            <i class="pi pi-plus" style="font-size:12px" /> Enregistrer
          </button>
        </div>
        <div>
          <div v-if="activities.length === 0" style="text-align:center;padding:40px 18px;color:var(--fg-3);font-size:13px">
            <i class="pi pi-clock" style="font-size:28px;display:block;margin-bottom:8px" />
            Aucune activité.
          </div>
          <div
            v-for="activity in activities"
            :key="activity.id"
            class="activity-row"
          >
            <div class="activity-ic">
              <i class="pi pi-circle-fill" style="font-size:8px;color:var(--halo-600,#2E5BE8)" />
            </div>
            <div style="flex:1;min-width:0;padding-bottom:16px;border-bottom:1px solid var(--border-subtle)">
              <p style="font-size:13px;color:var(--fg-1);margin:0">{{ activity.description }}</p>
              <p style="font-size:11px;color:var(--fg-4);margin:4px 0 0">{{ activity.created_at }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Modal -->
    <Dialog v-model:visible="showEditModal" header="Modifier le contact" modal :style="{ width: '640px' }">
      <ContactForm :contact="contact" :accounts="[]" @saved="onSaved" @cancel="showEditModal = false" />
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import Dialog from 'primevue/dialog'
import AppLayout from '@/Layouts/AppLayout.vue'
import ContactForm from './Form.vue'

interface Activity { id: number; description: string; created_at: string }
interface Contact {
  id: number; full_name: string; first_name: string; last_name: string;
  email: string | null; phone: string | null; mobile: string | null;
  job_title: string | null; department: string | null; status: string;
  source: string | null;
  account: { id: number; name: string } | null; created_at: string;
}

const props = defineProps<{ contact: Contact; activities: Activity[] }>()
const showEditModal = ref(false)

const initials = computed(() =>
  props.contact.full_name?.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase() ?? '?'
)

const statusClass = computed(() => ({
  active: 'wh-badge-green', inactive: 'wh-badge-slate', prospect: 'wh-badge-blue',
}[props.contact.status] ?? 'wh-badge-slate'))

const statusLabel = computed(() => ({
  active: 'Actif', inactive: 'Inactif', prospect: 'Prospect',
}[props.contact.status] ?? props.contact.status))

const fields = computed(() => [
  { label: 'Email',        icon: 'pi pi-envelope', value: props.contact.email },
  { label: 'Téléphone',    icon: 'pi pi-phone',    value: props.contact.phone },
  { label: 'Mobile',       icon: 'pi pi-mobile',   value: props.contact.mobile },
  { label: 'Poste',        icon: 'pi pi-briefcase',value: props.contact.job_title },
  { label: 'Département',  icon: 'pi pi-building', value: props.contact.department },
  { label: 'Compte',       icon: 'pi pi-link',     value: props.contact.account?.name },
  { label: 'Créé le',      icon: 'pi pi-calendar', value: props.contact.created_at },
])

const openEditModal = () => { showEditModal.value = true }
const onSaved = () => { showEditModal.value = false; router.reload() }
</script>

<style scoped>
.page-head { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:24px; gap:16px; flex-wrap:wrap; }
.wh-page-title { margin:0; font-family:var(--font-display); font-size:24px; font-weight:600; letter-spacing:-0.022em; color:var(--fg-1); }
.wh-page-subtitle { margin:4px 0 0; font-size:13px; color:var(--fg-3); }
.page-actions { display:flex; gap:8px; }
.btn { font-family:var(--font-sans); font-weight:500; font-size:14px; padding:8px 14px; border-radius:var(--r-md); border:1px solid transparent; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:background var(--dur-base); line-height:1.2; text-decoration:none; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover { background:var(--halo-700); }
.btn-secondary { background:var(--bg-canvas); color:var(--fg-1); border-color:var(--border-subtle); }
.btn-secondary:hover { background:var(--bg-sunken); }
.btn-icon { background:var(--bg-canvas); color:var(--fg-2); border:1px solid var(--border-subtle); border-radius:var(--r-md); width:34px; height:34px; padding:0; display:inline-flex; align-items:center; justify-content:center; cursor:pointer; }
.contact-avatar { width:48px; height:48px; border-radius:50%; background:var(--halo-50,#EEF3FF); color:var(--halo-700,#1A3AA3); font-size:15px; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.panel-section-title { margin:0; font-size:13px; font-weight:600; color:var(--fg-2); text-transform:uppercase; letter-spacing:0.06em; }
.wh-panel-head { display:flex; align-items:center; justify-content:space-between; padding:14px 18px; border-bottom:1px solid var(--border-subtle); }
.wh-panel-head h3 { margin:0; font-size:14px; font-weight:600; color:var(--fg-1); }
.activity-row { display:flex; gap:12px; padding:14px 18px; align-items:flex-start; }
.activity-ic { width:24px; height:24px; border-radius:50%; background:var(--halo-50); display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:2px; }
.wh-badge { display:inline-flex; align-items:center; gap:5px; padding:2px 8px; border-radius:var(--r-pill); font-size:11px; font-weight:500; }
.wh-badge-dot { width:5px; height:5px; border-radius:50%; background:currentColor; }
.wh-badge-green { background:var(--success-bg); color:var(--success-fg); }
.wh-badge-blue { background:var(--halo-50); color:var(--halo-700); }
.wh-badge-slate { background:var(--bg-sunken); color:var(--fg-2); }
</style>
