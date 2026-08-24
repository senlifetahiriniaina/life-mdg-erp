<template>
  <AppLayout>
    <Head title="Comptes de rôle" />

    <div class="space-y-6">
      <div class="page-head flex items-center justify-between">
        <div>
          <h1 class="wh-page-title">Comptes de rôle</h1>
          <p class="text-surface-500 text-sm mt-1">
            Chantier 37 — associez chaque rôle comptable utilisé par l'application (trésorerie par défaut, clients,
            avances, fournisseurs, paie...) à un compte réel de votre plan comptable. Modifiable à tout moment,
            sans toucher au code.
          </p>
        </div>
      </div>

      <AIAssistantPanel v-if="guidance" :guidance="guidance" />

      <div class="wh-panel overflow-hidden">
        <DataTable :value="roles" :loading="loading" striped-rows class="p-datatable-sm">
          <Column field="label" header="Rôle">
            <template #body="{ data }">
              <div class="font-medium text-surface-900 dark:text-surface-50">{{ data.label }}</div>
              <div class="text-xs text-surface-400">{{ data.role }}</div>
            </template>
          </Column>

          <Column header="Compte utilisé">
            <template #body="{ data }">
              <Tag
                v-if="!data.is_resolvable"
                value="Non résolu"
                severity="danger"
              />
              <div v-else class="text-sm">
                <code class="text-xs bg-surface-100 dark:bg-surface-700 px-2 py-0.5 rounded">{{ data.resolved_code }}</code>
                {{ data.resolved_account_name }}
              </div>
              <Tag
                v-if="data.is_customized"
                value="Personnalisé"
                severity="info"
                class="mt-1"
              />
              <Tag
                v-else
                value="Valeur par défaut"
                severity="secondary"
                class="mt-1"
              />
            </template>
          </Column>

          <Column header="Compte par défaut" style="width: 12rem">
            <template #body="{ data }">
              <code class="text-xs bg-surface-100 dark:bg-surface-700 px-2 py-0.5 rounded">{{ data.default_code }}</code>
            </template>
          </Column>

          <Column header="Changer le compte" style="width: 22rem">
            <template #body="{ data }">
              <Dropdown
                v-model="selections[data.role]"
                :options="accountOptions"
                option-label="displayLabel"
                option-value="code"
                filter
                placeholder="Choisir un compte..."
                class="w-full"
              />
            </template>
          </Column>

          <Column header="Actions" style="width: 12rem">
            <template #body="{ data }">
              <div class="flex gap-2">
                <Button
                  label="Enregistrer"
                  size="small"
                  :disabled="!selections[data.role] || selections[data.role] === data.resolved_code"
                  :loading="saving === data.role"
                  @click="save(data)"
                />
                <Button
                  label="Réinitialiser"
                  size="small"
                  outlined
                  severity="secondary"
                  :disabled="!data.is_customized"
                  :loading="saving === data.role"
                  @click="reset(data)"
                />
              </div>
            </template>
          </Column>

          <template #empty>
            <div class="text-center py-12 text-surface-400">Aucun rôle de compte.</div>
          </template>
        </DataTable>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Dropdown from 'primevue/dropdown'
import Button from 'primevue/button'
import AppLayout from '@/Layouts/AppLayout.vue'
import AIAssistantPanel from '@/Components/UI/AIAssistantPanel.vue'
import { useAiAssistant } from '@/composables/useAiAssistant'

interface AccountRole {
  role: string
  label: string
  default_code: string
  resolved_code: string | null
  resolved_account_id: number | null
  resolved_account_name: string | null
  is_customized: boolean
  is_resolvable: boolean
}

interface ChartOfAccountOption {
  code: string
  name: string
  displayLabel: string
}

const { guidance } = useAiAssistant('Accounting', 'manage_account_roles')

const roles = ref<AccountRole[]>([])
const accountOptions = ref<ChartOfAccountOption[]>([])
const selections = reactive<Record<string, string | null>>({})
const loading = ref(false)
const saving = ref<string | null>(null)

const fetchRoles = async () => {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/accounting/account-roles')
    roles.value = data.data
    roles.value.forEach((r) => {
      selections[r.role] = r.resolved_code
    })
  } finally {
    loading.value = false
  }
}

const fetchAccounts = async () => {
  const accounts: ChartOfAccountOption[] = []
  let page = 1
  let lastPage = 1

  do {
    const { data } = await axios.get('/api/v1/accounting/chart-of-accounts', {
      params: { is_active: true, per_page: 100, page },
    })
    ;(data.data ?? []).forEach((a: { code: string; name: string }) => {
      accounts.push({ code: a.code, name: a.name, displayLabel: `${a.code} — ${a.name}` })
    })
    // Chantier 38.2: ChartOfAccountController::index() returns Laravel's
    // standard paginated-resource shape ({data, links, meta}) — the real
    // last_page lives under `data.meta.last_page`, never a top-level
    // `data.last_page`. The old `data.last_page ?? 1` read always fell
    // through to the `?? 1` default (confirmed empirically: real response
    // has no top-level `last_page` key at all), so this loop silently
    // stopped after page 1 on every real chart with more than 100 active
    // accounts — 109 of the real 209 seeded accounts (Chantier 36) never
    // reached the dropdown.
    lastPage = data.meta?.last_page ?? 1
    page += 1
  } while (page <= lastPage)

  accountOptions.value = accounts
}

const applyUpdate = async (role: AccountRole, code: string) => {
  saving.value = role.role
  try {
    const { data } = await axios.put(`/api/v1/accounting/account-roles/${role.role}`, { code })
    const idx = roles.value.findIndex((r) => r.role === role.role)
    if (idx !== -1) {
      roles.value[idx] = data.data
      selections[role.role] = data.data.resolved_code
    }
  } finally {
    saving.value = null
  }
}

const save = (role: AccountRole) => {
  const code = selections[role.role]
  if (!code) return
  applyUpdate(role, code)
}

const reset = (role: AccountRole) => {
  applyUpdate(role, role.default_code)
}

onMounted(() => {
  fetchRoles()
  fetchAccounts()
})
</script>
