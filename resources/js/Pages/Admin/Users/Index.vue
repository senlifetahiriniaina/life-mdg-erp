<template>
  <AppLayout>
    <div class="p-6 space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-0">👥 Utilisateurs & Rôles</h1>
          <p class="text-surface-500 mt-1">Gérez les accès et permissions par utilisateur</p>
        </div>
        <InputText v-model="search" placeholder="Rechercher..." @input="onSearch" />
      </div>

      <!-- DataTable -->
      <DataTable :value="userList" striped-rows class="shadow-sm border border-surface-200 dark:border-surface-700 rounded-xl overflow-hidden">
        <Column field="name" header="Nom" sortable />
        <Column field="email" header="Email" />
        <Column header="Rôles">
          <template #body="{ data }">
            <div class="flex flex-wrap gap-1">
              <Tag
                v-for="role in data.roles"
                :key="role.id"
                :value="role.name"
                :severity="roleSeverity(role.name)"
                class="text-xs"
              />
            </div>
          </template>
        </Column>
        <Column header="Créé le">
          <template #body="{ data }">
            <span class="text-sm text-surface-500">{{ formatDate(data.created_at) }}</span>
          </template>
        </Column>
        <Column header="Actions" style="width: 120px">
          <template #body="{ data }">
            <Button size="small" icon="pi pi-user-edit" label="Gérer" @click="openUser(data)" />
          </template>
        </Column>
      </DataTable>

      <!-- User detail dialog -->
      <Dialog v-model:visible="showUserDialog" :header="selectedUser?.name ?? 'Utilisateur'" :style="{ width: '600px' }" modal>
        <TabView v-if="selectedUser">
          <!-- Info tab -->
          <TabPanel header="Informations">
            <div class="space-y-3 py-2">
              <div><span class="text-surface-500 text-sm">Nom :</span> <span class="font-medium">{{ selectedUser.name }}</span></div>
              <div><span class="text-surface-500 text-sm">Email :</span> <span class="font-medium">{{ selectedUser.email }}</span></div>
              <div><span class="text-surface-500 text-sm">Créé le :</span> <span class="font-medium">{{ formatDate(selectedUser.created_at) }}</span></div>
            </div>
          </TabPanel>

          <!-- Roles tab -->
          <TabPanel header="Rôles & Permissions">
            <div class="py-2 space-y-4">
              <div>
                <h3 class="font-semibold mb-3">Rôles assignés</h3>
                <div class="grid grid-cols-2 gap-2">
                  <div v-for="role in allRoles" :key="role.id" class="flex items-center gap-2">
                    <Checkbox
                      :model-value="hasRole(role.name)"
                      :binary="true"
                      @update:model-value="toggleRole(role.name, $event)"
                    />
                    <Tag :value="role.name" :severity="roleSeverity(role.name)" class="text-xs" />
                    <span class="text-xs text-surface-500">({{ role.users_count }} users)</span>
                  </div>
                </div>
              </div>
            </div>
          </TabPanel>

          <!-- Activity tab -->
          <TabPanel header="Activité récente">
            <div class="py-2">
              <p class="text-surface-500 text-sm">Les logs d'activité récente de cet utilisateur sont disponibles dans la section Audit.</p>
              <div class="mt-3">
                <a :href="`/admin/audit?user_id=${selectedUser.id}`" class="text-primary-600 hover:underline text-sm">
                  Voir les logs d'audit pour {{ selectedUser.name }}
                </a>
              </div>
            </div>
          </TabPanel>
        </TabView>
      </Dialog>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import TabView from 'primevue/tabview'
import TabPanel from 'primevue/tabpanel'
import InputText from 'primevue/inputtext'
import Checkbox from 'primevue/checkbox'
import { useToast } from 'primevue/usetoast'

const props = defineProps({
  users: { type: Object, default: () => ({ data: [] }) },
  roles: { type: Array, default: () => [] },
})

const toast = useToast()
const userList = ref(props.users.data)
const allRoles = ref(props.roles)
const search = ref('')
const showUserDialog = ref(false)
const selectedUser = ref(null)
let searchTimer = null

const roleSeverityMap = {
  'super-admin': 'danger',
  'admin': 'danger',
  'system-admin': 'warn',
  'security-admin': 'warn',
  'billing-admin': 'info',
  'support-admin': 'info',
  'content-admin': 'secondary',
  'tenant-admin': 'secondary',
  'manager': 'info',
  'employee': 'secondary',
  'accountant': 'info',
  'hr-manager': 'success',
  'sales-rep': 'success',
}

const roleSeverity = (name) => roleSeverityMap[name] ?? 'secondary'
const formatDate = (iso) => new Date(iso).toLocaleDateString('fr-FR')

const hasRole = (roleName) => {
  return selectedUser.value?.roles?.some(r => r.name === roleName) ?? false
}

const openUser = async (user) => {
  selectedUser.value = { ...user }
  showUserDialog.value = true
}

const toggleRole = async (roleName, assign) => {
  if (!selectedUser.value) return
  try {
    if (assign) {
      await axios.post(`/api/v1/admin/users/${selectedUser.value.id}/roles`, { role_name: roleName })
      if (!selectedUser.value.roles) selectedUser.value.roles = []
      selectedUser.value.roles.push({ name: roleName })
    } else {
      await axios.delete(`/api/v1/admin/users/${selectedUser.value.id}/roles/${roleName}`)
      selectedUser.value.roles = selectedUser.value.roles.filter(r => r.name !== roleName)
    }
    // Update in list
    const idx = userList.value.findIndex(u => u.id === selectedUser.value.id)
    if (idx !== -1) userList.value[idx].roles = selectedUser.value.roles
    toast.add({ severity: 'success', summary: assign ? 'Rôle assigné' : 'Rôle révoqué', life: 2000 })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.response?.data?.message, life: 4000 })
  }
}

const onSearch = () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(async () => {
    try {
      const { data } = await axios.get('/api/v1/admin/users', { params: { search: search.value } })
      userList.value = data.data
    } catch {
      // ignore
    }
  }, 300)
}
</script>
