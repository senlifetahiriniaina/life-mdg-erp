<template>
  <AppLayout>
    <Head title="Marketplace Channels" />

    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">
            Marketplace Channels
          </h1>
          <p class="text-surface-500 text-sm mt-1">
            Connect and sync Amazon/eBay storefronts
          </p>
        </div>
        <Button
          icon="pi pi-plus"
          label="Connect Channel"
          @click="openConnectModal"
        />
      </div>

      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 overflow-hidden">
        <DataTable
          :value="channels"
          :loading="loading"
          striped-rows
          class="p-datatable-sm"
        >
          <Column field="name" header="Name" sortable>
            <template #body="{ data }">
              <div class="font-medium text-surface-900 dark:text-surface-50">{{ data.name }}</div>
            </template>
          </Column>
          <Column field="type" header="Type">
            <template #body="{ data }">
              <Tag :value="data.type" severity="info" />
            </template>
          </Column>
          <Column field="status" header="Status">
            <template #body="{ data }">
              <Tag
                :value="data.status"
                :severity="data.status === 'active' ? 'success' : 'secondary'"
              />
            </template>
          </Column>
          <Column field="last_synced_at" header="Last Synced">
            <template #body="{ data }">
              {{ data.last_synced_at ?? 'Never' }}
            </template>
          </Column>
          <Column header="Actions" style="width: 8rem">
            <template #body="{ data }">
              <Button
                icon="pi pi-sync"
                outlined
                size="small"
                :loading="syncingId === data.id"
                @click="syncChannel(data)"
              />
            </template>
          </Column>
          <template #empty>
            <div class="text-center py-12 text-surface-400">
              No channels connected.
            </div>
          </template>
        </DataTable>
      </div>
    </div>

    <Dialog
      v-model:visible="showModal"
      header="Connect Marketplace Channel"
      modal
      class="w-full max-w-lg"
    >
      <form class="space-y-4" @submit.prevent="submitConnect">
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700 dark:text-surface-200">
            Marketplace <span class="text-red-500">*</span>
          </label>
          <Select
            v-model="connectForm.type"
            :options="channelTypeOptions"
            option-label="label"
            option-value="value"
            placeholder="Select marketplace"
            class="w-full"
          />
        </div>

        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700 dark:text-surface-200">
            Name <span class="text-red-500">*</span>
          </label>
          <InputText
            v-model="connectForm.name"
            :class="{ 'p-invalid': connectErrors.name }"
            placeholder="e.g. Amazon FR Store"
          />
          <small v-if="connectErrors.name" class="text-red-500">{{ connectErrors.name }}</small>
        </div>

        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700 dark:text-surface-200">
            API Key
          </label>
          <InputText v-model="connectForm.config.api_key" placeholder="Marketplace API key" />
        </div>

        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-surface-700 dark:text-surface-200">
            Seller ID
          </label>
          <InputText v-model="connectForm.config.seller_id" placeholder="Marketplace seller ID" />
        </div>

        <div class="flex justify-end gap-2 pt-2 border-t border-surface-200 dark:border-surface-700">
          <Button type="button" label="Cancel" outlined @click="showModal = false" />
          <Button type="submit" label="Connect" :loading="submitting" />
        </div>
      </form>
    </Dialog>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import AppLayout from '@/Layouts/AppLayout.vue'

interface Channel {
  id: number
  type: string
  name: string
  status: string
  last_synced_at: string | null
}

const page = usePage()
const channels = ref<Channel[]>([])
const loading = ref(false)
const showModal = ref(false)
const submitting = ref(false)
const syncingId = ref<number | null>(null)

const connectForm = reactive({
  type: null as string | null,
  name: '',
  config: { api_key: '', seller_id: '' },
})

const connectErrors = reactive<Record<string, string>>({})

const channelTypeOptions = [
  { label: 'Amazon', value: 'amazon' },
  { label: 'eBay', value: 'ebay' },
]

const fetchChannels = async () => {
  loading.value = true
  try {
    const response = await fetch('/api/v1/inventory/channels', {
      headers: { Accept: 'application/json' },
    })
    const data = await response.json()
    channels.value = data.data ?? []
  } finally {
    loading.value = false
  }
}

const openConnectModal = () => {
  connectForm.type = null
  connectForm.name = ''
  connectForm.config = { api_key: '', seller_id: '' }
  Object.keys(connectErrors).forEach(k => delete connectErrors[k])
  showModal.value = true
}

const submitConnect = async () => {
  if (!connectForm.type) return
  submitting.value = true
  Object.keys(connectErrors).forEach(k => delete connectErrors[k])

  try {
    const response = await fetch(`/api/v1/inventory/channels/${connectForm.type}/connect`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        name: connectForm.name,
        config: connectForm.config,
        company_id: (page.props.auth as any)?.user?.company_id ?? 1,
      }),
    })

    if (!response.ok) {
      const data = await response.json()
      if (data.errors) Object.assign(connectErrors, data.errors)
      return
    }

    showModal.value = false
    fetchChannels()
  } finally {
    submitting.value = false
  }
}

const syncChannel = async (channel: Channel) => {
  syncingId.value = channel.id
  try {
    await fetch(`/api/v1/inventory/channels/${channel.id}/sync`, {
      method: 'POST',
      headers: { Accept: 'application/json' },
    })
    fetchChannels()
  } finally {
    syncingId.value = null
  }
}

onMounted(() => {
  fetchChannels()
})
</script>
