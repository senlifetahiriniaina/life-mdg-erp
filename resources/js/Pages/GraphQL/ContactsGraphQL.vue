<template>
  <div class="contacts-container">
    <div class="header">
      <h1>Contacts (GraphQL)</h1>
      <button @click="showCreateDialog = true" class="btn-primary">
        Add Contact
      </button>
    </div>

    <div v-if="loading" class="loading">
      Loading contacts...
    </div>

    <div v-else-if="error" class="error">
      {{ error.message }}
    </div>

    <div v-else class="contacts-list">
      <div v-for="edge in contacts" :key="edge.node.id" class="contact-card">
        <h3>{{ edge.node.firstName }} {{ edge.node.lastName }}</h3>
        <p><strong>Email:</strong> {{ edge.node.email }}</p>
        <p v-if="edge.node.phone"><strong>Phone:</strong> {{ edge.node.phone }}</p>
        <p v-if="edge.node.jobTitle"><strong>Title:</strong> {{ edge.node.jobTitle }}</p>
        <p v-if="edge.node.account"><strong>Account:</strong> {{ edge.node.account.name }}</p>
        <div v-if="edge.node.opportunities.length" class="opportunities">
          <strong>Opportunities:</strong>
          <ul>
            <li v-for="opp in edge.node.opportunities" :key="opp.id">
              {{ opp.name }} ({{ opp.stage }}) - ${{ opp.amount }}
            </li>
          </ul>
        </div>
        <div class="actions">
          <button @click="editContact(edge.node)" class="btn-edit">Edit</button>
          <button @click="deleteContactHandler(edge.node.id)" class="btn-delete">Delete</button>
        </div>
      </div>

      <div v-if="pageInfo?.hasNextPage" class="pagination">
        <button @click="loadMore" class="btn-secondary">
          Load More
        </button>
      </div>
    </div>

    <!-- Create/Edit Dialog -->
    <dialog v-if="showCreateDialog" class="dialog">
      <div class="dialog-content">
        <h2>{{ editingContact?.id ? 'Edit' : 'Create' }} Contact</h2>
        <form @submit.prevent="saveContact">
          <input
            v-model="formData.firstName"
            type="text"
            placeholder="First Name"
            required
            class="form-input"
          />
          <input
            v-model="formData.lastName"
            type="text"
            placeholder="Last Name"
            required
            class="form-input"
          />
          <input
            v-model="formData.email"
            type="email"
            placeholder="Email"
            required
            class="form-input"
          />
          <input
            v-model="formData.phone"
            type="tel"
            placeholder="Phone"
            class="form-input"
          />
          <input
            v-model="formData.jobTitle"
            type="text"
            placeholder="Job Title"
            class="form-input"
          />
          <select v-model="formData.status" class="form-input">
            <option value="ACTIVE">Active</option>
            <option value="INACTIVE">Inactive</option>
            <option value="PROSPECT">Prospect</option>
          </select>
          <div class="dialog-actions">
            <button type="submit" class="btn-primary">
              {{ editingContact?.id ? 'Update' : 'Create' }}
            </button>
            <button type="button" @click="showCreateDialog = false" class="btn-secondary">
              Cancel
            </button>
          </div>
        </form>
      </div>
    </dialog>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useQuery, useMutation } from '@vue/apollo-composable';
import { GET_CONTACTS, CREATE_CONTACT, UPDATE_CONTACT, DELETE_CONTACT } from '@/graphql/queries';

const showCreateDialog = ref(false);
const editingContact = ref(null);
const formData = ref({
  firstName: '',
  lastName: '',
  email: '',
  phone: '',
  jobTitle: '',
  status: 'ACTIVE',
});

const { result, loading, error, fetchMore } = useQuery(GET_CONTACTS, {
  first: 10,
});

const { mutate: createContact } = useMutation(CREATE_CONTACT);
const { mutate: updateContact } = useMutation(UPDATE_CONTACT);
const { mutate: deleteContact } = useMutation(DELETE_CONTACT);

const contacts = computed(() => result.value?.contacts?.edges || []);
const pageInfo = computed(() => result.value?.contacts?.pageInfo);

const editContact = (contact) => {
  editingContact.value = contact;
  formData.value = {
    firstName: contact.firstName,
    lastName: contact.lastName,
    email: contact.email,
    phone: contact.phone || '',
    jobTitle: contact.jobTitle || '',
    status: contact.status,
  };
  showCreateDialog.value = true;
};

const saveContact = async () => {
  try {
    if (editingContact.value?.id) {
      await updateContact({
        id: editingContact.value.id,
        input: formData.value,
      });
    } else {
      await createContact({
        input: formData.value,
      });
    }
    showCreateDialog.value = false;
    editingContact.value = null;
  } catch (err) {
    console.error('Error saving contact:', err);
  }
};

const deleteContactHandler = async (id) => {
  if (confirm('Are you sure?')) {
    try {
      await deleteContact({ id });
    } catch (err) {
      console.error('Error deleting contact:', err);
    }
  }
};

const loadMore = () => {
  fetchMore({
    variables: {
      after: pageInfo.value?.endCursor,
    },
  });
};
</script>

<style scoped>
.contacts-container {
  padding: 20px;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.contacts-list {
  display: grid;
  gap: 20px;
}

.contact-card {
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  padding: 16px;
  background: white;
}

.contact-card h3 {
  margin: 0 0 12px 0;
  color: #333;
}

.contact-card p {
  margin: 8px 0;
  color: #666;
}

.opportunities {
  margin-top: 12px;
  padding: 8px;
  background: #f5f5f5;
  border-radius: 4px;
}

.opportunities ul {
  margin: 8px 0 0 20px;
  padding: 0;
}

.actions {
  display: flex;
  gap: 8px;
  margin-top: 12px;
}

.btn-primary, .btn-secondary, .btn-edit, .btn-delete {
  padding: 8px 16px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 14px;
}

.btn-primary {
  background: #007bff;
  color: white;
}

.btn-secondary {
  background: #6c757d;
  color: white;
}

.btn-edit {
  background: #28a745;
  color: white;
}

.btn-delete {
  background: #dc3545;
  color: white;
}

.pagination {
  display: flex;
  justify-content: center;
  margin-top: 20px;
}

.loading, .error {
  padding: 20px;
  text-align: center;
  border-radius: 4px;
}

.error {
  background: #f8d7da;
  color: #721c24;
}

.dialog {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.dialog-content {
  background: white;
  padding: 24px;
  border-radius: 8px;
  width: 100%;
  max-width: 500px;
}

.dialog-content h2 {
  margin-top: 0;
}

.form-input {
  width: 100%;
  padding: 10px;
  margin: 10px 0;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
  box-sizing: border-box;
}

.dialog-actions {
  display: flex;
  gap: 10px;
  margin-top: 20px;
}

.dialog-actions button {
  flex: 1;
}
</style>
