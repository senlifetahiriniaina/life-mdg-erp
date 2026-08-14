<template>
  <div class="consolidation-index">
    <div class="header">
      <h1>Consolidation Groups</h1>
      <router-link to="/consolidations/create" class="btn btn-primary">
        New Consolidation
      </router-link>
    </div>

    <div class="filters">
      <input
        v-model="filters.search"
        type="text"
        placeholder="Search by name..."
        class="form-control"
      />
      <select v-model="filters.status" class="form-control">
        <option value="">All Statuses</option>
        <option value="draft">Draft</option>
        <option value="in_progress">In Progress</option>
        <option value="completed">Completed</option>
        <option value="approved">Approved</option>
      </select>
      <button @click="fetchGroups" class="btn btn-secondary">Search</button>
    </div>

    <div v-if="loading" class="spinner">Loading...</div>

    <table v-else class="table">
      <thead>
        <tr>
          <th scope="col">Name</th>
          <th scope="col">Parent Company</th>
          <th scope="col">Fiscal Year</th>
          <th scope="col">Method</th>
          <th scope="col">Status</th>
          <th scope="col">Members</th>
          <th scope="col">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="group in groups" :key="group.id">
          <td>{{ group.name }}</td>
          <td>{{ group.parent_company.name }}</td>
          <td>{{ group.fiscal_year }}</td>
          <td>{{ group.consolidation_method }}</td>
          <td>
            <span :class="`badge badge-${getStatusClass(group.status)}`">
              {{ group.status }}
            </span>
          </td>
          <td>{{ group.members.length }}</td>
          <td>
            <router-link :to="`/consolidations/${group.id}`" class="btn btn-sm btn-info">
              View
            </router-link>
            <button @click="deleteGroup(group.id)" class="btn btn-sm btn-danger">
              Delete
            </button>
          </td>
        </tr>
      </tbody>
    </table>

    <div v-if="!loading && groups.length === 0" class="alert alert-info">
      No consolidation groups found. Create one to get started.
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed} from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useRouter } from 'vue-router'

const page = usePage()
const { isAdmin, isElevated, hasAnyRole } = useRoleAccess()
const canManage = computed(() => isElevated.value || hasAnyRole(['finance-manager']))
const canCreate = computed(() => canManage.value)
const canEdit = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)


const router = useRouter()
const groups = ref([])
const loading = ref(true)
const filters = ref({
  search: '',
  status: ''
})

const fetchGroups = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams(filters.value)
    const response = await fetch(`/api/consolidations?${params}`)
    groups.value = await response.json()
  } catch (error) {
    console.error('Error fetching groups:', error)
  } finally {
    loading.value = false
  }
}

const deleteGroup = async (groupId) => {
  if (!confirm('Are you sure?')) return
  
  try {
    await fetch(`/api/consolidations/${groupId}`, { method: 'DELETE' })
    await fetchGroups()
  } catch (error) {
    console.error('Error deleting group:', error)
  }
}

const getStatusClass = (status) => {
  const classes = {
    draft: 'warning',
    in_progress: 'info',
    completed: 'success',
    approved: 'success'
  }
  return classes[status] || 'secondary'
}

onMounted(() => fetchGroups())
</script>

<style scoped>
.consolidation-index {
  padding: 20px;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
}

.filters {
  display: flex;
  gap: 10px;
  margin-bottom: 20px;
}

.filters input,
.filters select,
.filters button {
  padding: 8px 12px;
  border: 1px solid var(--slate-300);
  border-radius: 4px;
}

.table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 20px;
}

.table th,
.table td {
  padding: 12px;
  text-align: left;
  border-bottom: 1px solid var(--slate-200);
}

.table th {
  background-color: var(--slate-50);
  font-weight: bold;
}

.table tr:hover {
  background-color: var(--slate-50);
}

.badge {
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: bold;
}

.badge-warning {
  background-color: var(--amber-400);
  color: black;
}

.badge-info {
  background-color: var(--halo-600);
  color: white;
}

.badge-success {
  background-color: var(--green-500);
  color: white;
}

.btn {
  padding: 8px 12px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  margin-right: 5px;
}

.btn-primary {
  background-color: var(--halo-500);
  color: white;
}

.btn-secondary {
  background-color: var(--slate-500);
  color: white;
}

.btn-info {
  background-color: var(--halo-600);
  color: white;
}

.btn-danger {
  background-color: var(--red-500);
  color: white;
}

.btn:hover {
  opacity: 0.9;
}

.spinner {
  text-align: center;
  padding: 40px;
  font-size: 18px;
  color: var(--slate-500);
}

.alert {
  padding: 15px;
  border-radius: 4px;
  margin-top: 20px;
}

.alert-info {
  background-color: var(--halo-100);
  color: var(--halo-800);
  border: 1px solid var(--halo-100);
}
</style>
