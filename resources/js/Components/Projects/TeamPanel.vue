<template>
  <div class="wh-panel">
    <div class="panel-head">
      <h3>Team Members</h3>
      <button class="btn btn-primary btn-sm" @click="showAddModal = true">
        <i class="pi pi-user-plus" /> Add Member
      </button>
    </div>

    <div v-if="members.length === 0" class="empty-state">
      No team members yet.
    </div>

    <table v-else class="wh-dt">
      <thead>
        <tr>
          <th>Member</th>
          <th>Role</th>
          <th class="num">Hours</th>
          <th style="width:80px"></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="member in members" :key="member.id" class="wh-dt-row">
          <td>
            <div class="member-cell">
              <div class="avatar">{{ initials(member.name) }}</div>
              <div>
                <div class="member-name">{{ member.name }}</div>
                <div class="member-email">{{ member.email }}</div>
              </div>
            </div>
          </td>
          <td>
            <select
              class="role-select"
              :value="member.role"
              @change="(e) => changeRole(member, (e.target as HTMLSelectElement).value)"
            >
              <option value="owner">Owner</option>
              <option value="manager">Manager</option>
              <option value="member">Member</option>
              <option value="viewer">Viewer</option>
            </select>
          </td>
          <td class="num">{{ memberHours[member.user_id] ?? 0 }}h</td>
          <td>
            <button class="btn btn-ghost btn-sm text-danger" @click="removeMember(member)">
              <i class="pi pi-trash" />
            </button>
          </td>
        </tr>
      </tbody>
    </table>

    <!-- Add Member Modal -->
    <div v-if="showAddModal" class="modal-overlay" @click.self="showAddModal = false">
      <div class="modal">
        <div class="modal-head">
          <h4>Add Team Member</h4>
          <button class="btn btn-ghost btn-sm" @click="showAddModal = false"><i class="pi pi-times" /></button>
        </div>
        <div class="modal-body">
          <div class="field">
            <label class="field-label">User ID</label>
            <input v-model.number="newMember.user_id" type="number" class="field-input" placeholder="Enter user ID" />
          </div>
          <div class="field">
            <label class="field-label">Role</label>
            <select v-model="newMember.role" class="field-input">
              <option value="owner">Owner</option>
              <option value="manager">Manager</option>
              <option value="member">Member</option>
              <option value="viewer">Viewer</option>
            </select>
          </div>
        </div>
        <div class="modal-foot">
          <button class="btn btn-ghost" @click="showAddModal = false">{{ $t('common.cancel') }}</button>
          <button class="btn btn-primary" :disabled="!newMember.user_id" @click="addMember">Add</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'

interface Member {
  id: number
  user_id: number
  name: string
  email: string
  role: string
  joined_at: string
}

const props = defineProps<{
  projectId: number
}>()

const members = ref<Member[]>([])
const memberHours = ref<Record<number, number>>({})
const showAddModal = ref(false)
const newMember = reactive({ user_id: 0, role: 'member' })

const initials = (name: string) =>
  name?.split(' ').map((n) => n[0]).slice(0, 2).join('').toUpperCase() ?? '??'

async function fetchMembers() {
  const { data } = await axios.get(`/api/v1/projects/${props.projectId}/team`)
  members.value = data
}

async function fetchHours() {
  const { data } = await axios.get(`/api/v1/projects/${props.projectId}/time-report`)
  const map: Record<number, number> = {}
  for (const m of data.by_member ?? []) {
    map[m.user_id] = m.hours
  }
  memberHours.value = map
}

async function addMember() {
  await axios.post(`/api/v1/projects/${props.projectId}/team`, {
    user_id: newMember.user_id,
    role: newMember.role,
  })
  showAddModal.value = false
  newMember.user_id = 0
  newMember.role = 'member'
  await fetchMembers()
}

async function removeMember(member: Member) {
  if (!confirm(`Remove ${member.name} from team?`)) return
  await axios.delete(`/api/v1/projects/${props.projectId}/team/${member.id}`)
  await fetchMembers()
}

async function changeRole(member: Member, role: string) {
  await axios.put(`/api/v1/projects/${props.projectId}/team/${member.id}`, { role })
  await fetchMembers()
}

onMounted(() => {
  fetchMembers()
  fetchHours()
})
</script>

<style scoped>
.panel-head { display:flex; align-items:center; justify-content:space-between; padding:14px 18px; border-bottom:1px solid var(--border-subtle); }
.panel-head h3 { margin:0; font-size:14px; font-weight:600; color:var(--fg-1); }
.empty-state { padding:32px 18px; text-align:center; color:var(--fg-3); font-size:13px; }
.member-cell { display:flex; align-items:center; gap:10px; }
.avatar { width:32px; height:32px; border-radius:50%; background:var(--halo-500); color:#fff; font-size:12px; font-weight:600; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.member-name { font-weight:500; color:var(--fg-1); font-size:13px; }
.member-email { font-size:11px; color:var(--fg-3); }
.role-select { font-size:12px; border:1px solid var(--border-subtle); border-radius:var(--r-sm); padding:3px 8px; background:var(--bg-canvas); color:var(--fg-1); cursor:pointer; }
.btn { display:inline-flex; align-items:center; gap:6px; padding:6px 14px; border-radius:var(--r-md); font-size:13px; font-weight:500; cursor:pointer; border:none; transition:background var(--dur-fast); }
.btn-sm { padding:4px 10px; font-size:12px; }
.btn-primary { background:var(--halo-500); color:#fff; }
.btn-primary:hover { background:var(--halo-600); }
.btn-ghost { background:transparent; color:var(--fg-2); }
.btn-ghost:hover { background:var(--bg-sunken); }
.text-danger { color:var(--danger-fg); }
.num { text-align:right; }
.wh-dt { width:100%; border-collapse:collapse; font-size:14px; }
.wh-dt thead th { text-align:left; font-size:11px; letter-spacing:0.06em; text-transform:uppercase; color:var(--fg-3); font-weight:600; padding:10px 18px; border-bottom:1px solid var(--border-subtle); background:var(--bg-sunken); }
.wh-dt thead th.num { text-align:right; }
.wh-dt-row td { padding:11px 18px; border-bottom:1px solid var(--border-subtle); }
.wh-dt-row:last-child td { border-bottom:0; }
.wh-dt-row:hover { background:var(--bg-sunken); }

/* Modal */
.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.4); display:flex; align-items:center; justify-content:center; z-index:100; }
.modal { background:var(--bg-canvas); border-radius:var(--r-lg); width:400px; box-shadow:0 20px 60px rgba(0,0,0,0.2); }
.modal-head { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid var(--border-subtle); }
.modal-head h4 { margin:0; font-size:15px; font-weight:600; }
.modal-body { padding:20px; display:flex; flex-direction:column; gap:14px; }
.modal-foot { display:flex; justify-content:flex-end; gap:8px; padding:14px 20px; border-top:1px solid var(--border-subtle); }
.field-label { font-size:12px; font-weight:500; color:var(--fg-2); display:block; margin-bottom:5px; }
.field-input { width:100%; padding:8px 10px; border:1px solid var(--border-subtle); border-radius:var(--r-md); font-size:13px; background:var(--bg-canvas); color:var(--fg-1); }
</style>
