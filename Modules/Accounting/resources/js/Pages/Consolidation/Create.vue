<template>
  <div class="consolidation-create">
    <h1>Create Consolidation Group</h1>

    <div class="wizard">
      <div class="steps">
        <div v-for="(step, idx) in steps" :key="idx" 
             :class="['step', { active: currentStep === idx, completed: currentStep > idx }]">
          <div class="step-number">{{ idx + 1 }}</div>
          <div class="step-label">{{ step }}</div>
        </div>
      </div>

      <form @submit.prevent="submitForm" class="form">
        <!-- Step 1: Basic Info -->
        <div v-if="currentStep === 0" class="step-content">
          <h2>Basic Information</h2>
          <div class="form-group">
            <label for="label-group-name">Group Name</label>
            <input id="label-group-name" v-model="form.name" type="text" class="form-control" required/>
          </div>
          <div class="form-group">
            <label for="label-description">Description</label>
            <textarea id="label-description" v-model="form.description" class="form-control"></textarea>
          </div>
          <div class="form-group">
            <label for="label-parent-company">Parent Company</label>
            <select id="label-parent-company" v-model="form.parent_company_id" class="form-control" required>
              <option value="">-- Select Company --</option>
              <option v-for="company in companies" :key="company.id" :value="company.id">
                {{ company.name }}
              </option>
            </select>
          </div>
          <div class="form-group">
            <label for="label-consolidation-method">Consolidation Method</label>
            <select id="label-consolidation-method" v-model="form.consolidation_method" class="form-control" required>
              <option value="full">Full Consolidation</option>
              <option value="proportionate">Proportionate</option>
              <option value="equity">Equity Method</option>
            </select>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="label-fiscal-year">Fiscal Year</label>
              <input id="label-fiscal-year" v-model.number="form.fiscal_year" type="number" class="form-control" required/>
            </div>
            <div class="form-group">
              <label for="label-consolidation-date">Consolidation Date</label>
              <input id="label-consolidation-date" v-model="form.consolidation_date" type="date" class="form-control" required/>
            </div>
          </div>
        </div>

        <!-- Step 2: Add Members -->
        <div v-if="currentStep === 1" class="step-content">
          <h2>Add Subsidiary Members</h2>
          <div class="form-group">
            <label for="label-subsidiary-company">Subsidiary Company</label>
            <select id="label-subsidiary-company" v-model="newMember.subsidiary_company_id" class="form-control">
              <option value="">-- Select Company --</option>
              <option v-for="company in companies" :key="company.id" :value="company.id">
                {{ company.name }}
              </option>
            </select>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="ownership">Ownership %</label>
              <input id="ownership" v-model.number="newMember.ownership_percentage" type="number" 
                     min="0" max="100" class="form-control" />
            </div>
            <div class="form-group">
              <label for="label-relationship-type">Relationship Type</label>
              <select id="label-relationship-type" v-model="newMember.relationship_type" class="form-control">
                <option value="subsidiary">Subsidiary</option>
                <option value="associate">Associate</option>
                <option value="joint_venture">Joint Venture</option>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="label-acquisition-date">Acquisition Date</label>
              <input id="label-acquisition-date" v-model="newMember.acquisition_date" type="date" class="form-control" required/>
            </div>
            <div class="form-group">
              <label for="acquisition-price">Acquisition Price</label>
              <input id="acquisition-price" v-model.number="newMember.acquisition_price" type="number" 
                     step="0.01" class="form-control" required />
            </div>
          </div>
          <button type="button" @click="addMember" class="btn btn-secondary">
            Add Member
          </button>

          <div v-if="form.members.length > 0" class="members-list">
            <h3>Added Members ({{ form.members.length }})</h3>
            <table class="table">
              <thead>
                <tr>
                  <th scope="col">Company</th>
                  <th scope="col">Ownership %</th>
                  <th scope="col">Relationship</th>
                  <th scope="col">Action</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(member, idx) in form.members" :key="idx">
                  <td>{{ getCompanyName(member.subsidiary_company_id) }}</td>
                  <td>{{ member.ownership_percentage }}%</td>
                  <td>{{ member.relationship_type }}</td>
                  <td>
                    <button type="button" @click="removeMember(idx)" class="btn btn-sm btn-danger">
                      Remove
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Step 3: Review -->
        <div v-if="currentStep === 2" class="step-content">
          <h2>Review</h2>
          <div class="review-section">
            <h3>Group Information</h3>
            <p><strong>Name:</strong> {{ form.name }}</p>
            <p><strong>Parent Company:</strong> {{ getCompanyName(form.parent_company_id) }}</p>
            <p><strong>Method:</strong> {{ form.consolidation_method }}</p>
            <p><strong>Fiscal Year:</strong> {{ form.fiscal_year }}</p>
            <p><strong>Date:</strong> {{ form.consolidation_date }}</p>
          </div>

          <div class="review-section">
            <h3>Members ({{ form.members.length }})</h3>
            <table class="table" v-if="form.members.length > 0">
              <thead>
                <tr>
                  <th scope="col">Company</th>
                  <th scope="col">Ownership</th>
                  <th scope="col">Type</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="member in form.members" :key="member.subsidiary_company_id">
                  <td>{{ getCompanyName(member.subsidiary_company_id) }}</td>
                  <td>{{ member.ownership_percentage }}%</td>
                  <td>{{ member.relationship_type }}</td>
                </tr>
              </tbody>
            </table>
            <p v-else class="text-muted">No members added</p>
          </div>
        </div>

        <!-- Navigation Buttons -->
        <div class="form-actions">
          <button v-if="currentStep > 0" type="button" @click="previousStep" class="btn btn-secondary">
            Back
          </button>
          <button v-if="currentStep < steps.length - 1" type="button" @click="nextStep" class="btn btn-primary">
            Next
          </button>
          <button v-else type="submit" :disabled="submitting" class="btn btn-success">
            {{ submitting ? 'Creating...' : 'Create Group' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed} from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useRouter } from 'vue-router'
import StrategicContext from '@/Components/StrategicContext.vue'
import { useStrategicLink } from '@/composables/useStrategicLink'

const page = usePage()
const roles = computed(() => page.props.auth?.user?.roles?.map(r => r.name) || [])
const isAdmin = computed(() => roles.value.some(r => ['admin', 'super-admin'].includes(r)))
const canManage = computed(() => roles.value.some(r => ['accounting-manager', 'admin', 'super-admin'].includes(r)))
const canCreate = computed(() => canManage.value)
const canEdit = computed(() => canManage.value)
const canDelete = computed(() => isAdmin.value)


const router = useRouter()
const currentStep = ref(0)
const steps = ['Basic Info', 'Add Members', 'Review']
const submitting = ref(false)
const companies = ref([])

const form = ref({
  name: '',
  description: '',
  parent_company_id: '',
  consolidation_method: 'full',
  fiscal_year: new Date().getFullYear(),
  consolidation_date: new Date().toISOString().split('T')[0],
  members: []
})

const newMember = ref({
  subsidiary_company_id: '',
  ownership_percentage: 100,
  relationship_type: 'subsidiary',
  acquisition_date: '',
  acquisition_price: 0
})

onMounted(async () => {
  const response = await fetch('/api/companies')
  companies.value = await response.json()
})

const nextStep = () => {
  if (currentStep.value < steps.length - 1) {
    currentStep.value++
  }
}

const previousStep = () => {
  if (currentStep.value > 0) {
    currentStep.value--
  }
}

const addMember = () => {
  if (!newMember.value.subsidiary_company_id) {
    alert('Please select a subsidiary company')
    return
  }
  
  form.value.members.push({ ...newMember.value })
  newMember.value = {
    subsidiary_company_id: '',
    ownership_percentage: 100,
    relationship_type: 'subsidiary',
    acquisition_date: '',
    acquisition_price: 0
  }
}

const removeMember = (idx) => {
  form.value.members.splice(idx, 1)
}

const getCompanyName = (companyId) => {
  const company = companies.value.find(c => c.id === companyId)
  return company ? company.name : 'Unknown'
}

const submitForm = async () => {
  submitting.value = true
  try {
    const response = await fetch('/api/consolidations', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(form.value)
    })
    
    if (response.ok) {
      const group = await response.json()
      router.push(`/consolidations/${group.id}`)
    } else {
      const error = await response.json()
      alert(`Error: ${error.message}`)
    }
  } catch (error) {
    console.error('Error creating group:', error)
    alert('Error creating consolidation group')
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.consolidation-create {
  max-width: 900px;
  margin: 0 auto;
  padding: 20px;
}

.wizard {
  margin-top: 30px;
}

.steps {
  display: flex;
  justify-content: space-between;
  margin-bottom: 40px;
}

.step {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  position: relative;
}

.step:not(:last-child)::after {
  content: '';
  position: absolute;
  top: 20px;
  left: 50%;
  width: 100%;
  height: 2px;
  background-color: var(--slate-200);
  z-index: -1;
}

.step.completed:not(:last-child)::after {
  background-color: var(--green-500);
}

.step-number {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background-color: var(--slate-100);
  border: 2px solid var(--slate-200);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
  margin-bottom: 10px;
}

.step.active .step-number {
  background-color: var(--halo-500);
  color: white;
  border-color: var(--halo-500);
}

.step.completed .step-number {
  background-color: var(--green-500);
  color: white;
  border-color: var(--green-500);
}

.step-label {
  font-size: 14px;
  color: var(--slate-500);
  text-align: center;
}

.step.active .step-label {
  color: var(--halo-500);
  font-weight: bold;
}

.form {
  border: 1px solid var(--slate-200);
  border-radius: 8px;
  padding: 30px;
  background-color: var(--slate-50);
}

.step-content {
  margin-bottom: 30px;
}

.step-content h2 {
  margin-bottom: 20px;
  color: var(--slate-700);
}

.form-group {
  margin-bottom: 15px;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 15px;
}

label {
  display: block;
  margin-bottom: 5px;
  font-weight: bold;
  color: var(--slate-700);
}

.form-control {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid var(--slate-300);
  border-radius: 4px;
  font-size: 14px;
}

.form-control:focus {
  outline: none;
  border-color: var(--halo-500);
  box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
}

.members-list {
  margin-top: 30px;
  padding-top: 20px;
  border-top: 1px solid var(--slate-200);
}

.members-list h3 {
  margin-bottom: 15px;
}

.table {
  width: 100%;
  border-collapse: collapse;
  background-color: white;
}

.table th,
.table td {
  padding: 10px;
  text-align: left;
  border-bottom: 1px solid var(--slate-200);
}

.table th {
  background-color: var(--slate-50);
  font-weight: bold;
}

.review-section {
  margin-bottom: 20px;
  padding: 15px;
  background-color: white;
  border-radius: 4px;
  border: 1px solid var(--slate-200);
}

.review-section h3 {
  margin-bottom: 10px;
  color: var(--slate-700);
}

.review-section p {
  margin: 5px 0;
  font-size: 14px;
}

.text-muted {
  color: var(--slate-400);
}

.form-actions {
  display: flex;
  gap: 10px;
  margin-top: 30px;
  justify-content: flex-end;
}

.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 14px;
  font-weight: bold;
}

.btn-primary {
  background-color: var(--halo-500);
  color: white;
}

.btn-secondary {
  background-color: var(--slate-500);
  color: white;
}

.btn-success {
  background-color: var(--green-500);
  color: white;
}

.btn-danger {
  background-color: var(--red-500);
  color: white;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn:hover:not(:disabled) {
  opacity: 0.9;
}

.btn-sm {
  padding: 5px 10px;
  font-size: 12px;
}
</style>
