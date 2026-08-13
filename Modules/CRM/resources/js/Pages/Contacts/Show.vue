<template>
  <AppLayout>
    <Head :title="contact.full_name" />

    <div class="space-y-6">
      <!-- Header -->
      <div class="flex items-center gap-4 flex-wrap">
        <Button
          icon="pi pi-arrow-left"
          text
          rounded
          @click="$inertia.visit(route('crm.contacts.index'))"
        />
        <div class="w-14 h-14 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-700 dark:text-blue-400 font-bold text-lg flex-shrink-0">
          {{ initials }}
        </div>
        <div class="flex-1 min-w-0">
          <h2 class="text-2xl font-bold text-surface-900 dark:text-surface-50 truncate">{{ contact.full_name }}</h2>
          <p class="text-surface-500 text-sm">{{ contact.email }}</p>
        </div>
        <div class="flex gap-2 ml-auto">
          <Button
            label="Edit"
            icon="pi pi-pencil"
            severity="secondary"
            @click="openEditModal"
          />
          <Button
            label="Send Email"
            icon="pi pi-envelope"
            :disabled="!contact.email"
            :href="contact.email ? `mailto:${contact.email}` : undefined"
          />
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Contact details card -->
        <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6 space-y-4">
          <h3 class="font-semibold text-surface-900 dark:text-surface-50">Details</h3>
          <div class="space-y-3">
            <div v-for="field in fields" :key="field.label" class="flex items-start gap-3">
              <i :class="[field.icon, 'text-surface-400 mt-0.5 flex-shrink-0']" />
              <div class="min-w-0">
                <p class="text-xs text-surface-500 uppercase tracking-wide">{{ field.label }}</p>
                <p class="text-sm font-medium text-surface-900 dark:text-surface-50 break-words">
                  {{ field.value || '—' }}
                </p>
              </div>
            </div>
          </div>

          <div class="pt-2 border-t border-surface-200 dark:border-surface-700">
            <Tag :value="contact.status" :severity="statusSeverity" class="capitalize" />
          </div>
        </div>

        <!-- Activity timeline -->
        <div class="lg:col-span-2 bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700">
          <div class="flex items-center justify-between p-5 border-b border-surface-200 dark:border-surface-700">
            <h3 class="font-semibold text-surface-900 dark:text-surface-50">Activity</h3>
            <Button label="Log Activity" icon="pi pi-plus" size="small" outlined />
          </div>

          <div class="p-5">
            <div v-if="activities.length === 0" class="text-center py-10 text-surface-400">
              <i class="pi pi-clock text-3xl mb-2 block" />
              <p class="text-sm">No activities yet</p>
            </div>

            <div v-else class="space-y-4">
              <div
                v-for="activity in activities"
                :key="activity.id"
                class="flex items-start gap-3"
              >
                <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center flex-shrink-0">
                  <i class="pi pi-circle-fill text-primary-600 text-xs" />
                </div>
                <div class="flex-1 min-w-0 pb-4 border-b border-surface-100 dark:border-surface-700 last:border-0">
                  <p class="text-sm text-surface-700 dark:text-surface-200">{{ activity.description }}</p>
                  <p class="text-xs text-surface-400 mt-0.5">{{ activity.created_at }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Modal -->
    <Dialog
      v-model:visible="showEditModal"
      header="Edit Contact"
      modal
      class="w-full max-w-2xl"
    >
      <ContactForm
        :contact="contact"
        :accounts="[]"
        @saved="onSaved"
        @cancel="showEditModal = false"
      />
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import AppLayout from '@/Layouts/AppLayout.vue'
import ContactForm from './Form.vue'

interface Activity {
  id: number
  description: string
  created_at: string
}

interface Contact {
  id: number
  full_name: string
  first_name: string
  last_name: string
  email: string | null
  phone: string | null
  mobile: string | null
  job_title: string | null
  department: string | null
  status: string
  account?: { id: number; name: string } | null
  created_at: string
}

const props = defineProps<{
  contact: Contact
  activities: Activity[]
}>()

const showEditModal = ref(false)

const initials = computed(() =>
  props.contact.full_name
    ?.split(' ')
    .map(n => n[0])
    .join('')
    .slice(0, 2)
    .toUpperCase() ?? '?'
)

const statusSeverity = computed(() => {
  const map: Record<string, string> = {
    active: 'success',
    inactive: 'secondary',
    prospect: 'info',
  }
  return map[props.contact.status] ?? 'secondary'
})

const fields = computed(() => [
  { label: 'Email', icon: 'pi pi-envelope', value: props.contact.email },
  { label: 'Phone', icon: 'pi pi-phone', value: props.contact.phone },
  { label: 'Mobile', icon: 'pi pi-mobile', value: props.contact.mobile },
  { label: 'Job Title', icon: 'pi pi-briefcase', value: props.contact.job_title },
  { label: 'Department', icon: 'pi pi-building', value: props.contact.department },
  { label: 'Account', icon: 'pi pi-link', value: props.contact.account?.name },
  { label: 'Created', icon: 'pi pi-calendar', value: props.contact.created_at },
])

const openEditModal = () => {
  showEditModal.value = true
}

const onSaved = () => {
  showEditModal.value = false
  router.reload()
}
</script>
