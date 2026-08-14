<template>
  <div class="max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-3xl font-bold text-surface-900 dark:text-surface-50">
        {{ isEditing ? 'Edit Supplier' : 'New Supplier' }}
      </h1>
      <Link href="/suppliers" class="text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:text-surface-50">
        ← Back to Suppliers
      </Link>
    </div>

    <div class="bg-white dark:bg-surface-800 dark:bg-surface-800 rounded-lg shadow p-6">
      <form @submit.prevent="handleSubmit" class="space-y-6">
        <!-- Basic Information -->
        <div class="border-b border-gray-200 dark:border-surface-700 pb-6">
          <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Basic Information</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label for="supplier-name" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                Supplier Name *
              </label>
              <input id="supplier-name"
                v-model="form.name"
                type="text"
                required
                class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
                placeholder="E.g., Tech Supplies Inc" :aria-describedby="errors.name ? 'supplier-name-error' : undefined" :aria-invalid="!!errors.name" />
              <p id="supplier-name-error" role="alert" v-if="errors.name" class="mt-1 text-sm text-red-700 dark:text-red-300">{{ errors.name }}</p>
            </div>

            <div>
              <label for="supplier-code" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                Supplier Code
              </label>
              <input id="supplier-code"
                v-model="form.code"
                type="text"
                class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent bg-gray-50 dark:bg-surface-800 dark:bg-surface-800"
                placeholder="Auto-generated"
                disabled
              />
            </div>

            <div>
              <label for="email" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                Email *
              </label>
              <input id="email"
                v-model="form.email"
                type="email"
                required
                class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
                placeholder="contact@supplier.com" :aria-describedby="errors.email ? 'email-error' : undefined" :aria-invalid="!!errors.email" />
              <p id="email-error" role="alert" v-if="errors.email" class="mt-1 text-sm text-red-700 dark:text-red-300">{{ errors.email }}</p>
            </div>

            <div>
              <label for="phone" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                Phone
              </label>
              <input id="phone"
                v-model="form.phone"
                type="tel"
                class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
                placeholder="+1-555-0123"
              />
            </div>
          </div>
        </div>

        <!-- Contact Information -->
        <div class="border-b border-gray-200 dark:border-surface-700 pb-6">
          <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Contact Information</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label for="contact-person" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                Contact Person
              </label>
              <input id="contact-person"
                v-model="form.contact_person"
                type="text"
                class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
                placeholder="John Doe"
              />
            </div>

            <div>
              <label for="contact-email" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                Contact Email
              </label>
              <input id="contact-email"
                v-model="form.contact_email"
                type="email"
                class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
                placeholder="john@supplier.com"
              />
            </div>

            <div>
              <label for="contact-phone" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                Contact Phone
              </label>
              <input id="contact-phone"
                v-model="form.contact_phone"
                type="tel"
                class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
              />
            </div>

            <div>
              <label for="contact-mobile" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                Contact Mobile
              </label>
              <input id="contact-mobile"
                v-model="form.contact_mobile"
                type="tel"
                class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
              />
            </div>
          </div>
        </div>

        <!-- Address Information -->
        <div class="border-b border-gray-200 dark:border-surface-700 pb-6">
          <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Address Information</h2>
          <div class="grid grid-cols-1 gap-4">
            <div>
              <label for="street-address" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                Street Address
              </label>
              <input id="street-address"
                v-model="form.street_address"
                type="text"
                class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
                placeholder="123 Business Ave"
              />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label for="city" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                  City
                </label>
                <input id="city"
                  v-model="form.city"
                  type="text"
                  class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
                  placeholder="New York"
                />
              </div>

              <div>
                <label for="state-province" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                  State/Province
                </label>
                <input id="state-province"
                  v-model="form.state"
                  type="text"
                  class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
                  placeholder="NY"
                />
              </div>

              <div>
                <label for="postal-code" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                  Postal Code
                </label>
                <input id="postal-code"
                  v-model="form.postal_code"
                  type="text"
                  class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
                  placeholder="10001"
                />
              </div>
            </div>

            <div>
              <label for="country" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                Country *
              </label>
              <input id="country"
                v-model="form.country"
                type="text"
                required
                class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
                placeholder="USA"
              />
            </div>
          </div>
        </div>

        <!-- Business Details -->
        <div class="border-b border-gray-200 dark:border-surface-700 pb-6">
          <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-50 mb-4">Business Details</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label for="default-currency" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                Default Currency
              </label>
              <select id="default-currency"
                v-model="form.currency"
                class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
              >
                <option value="USD">USD - US Dollar</option>
                <option value="EUR">EUR - Euro</option>
                <option value="GBP">GBP - British Pound</option>
                <option value="JPY">JPY - Japanese Yen</option>
                <option value="CAD">CAD - Canadian Dollar</option>
                <option value="AUD">AUD - Australian Dollar</option>
              </select>
            </div>

            <div>
              <label for="tax-id-vat-number" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                Tax ID / VAT Number
              </label>
              <input id="tax-id-vat-number"
                v-model="form.tax_id"
                type="text"
                class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
                placeholder="VAT12345678"
              />
            </div>

            <div>
              <label for="payment-terms-days" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                Payment Terms (days)
              </label>
              <input id="payment-terms-days"
                v-model.number="form.payment_terms"
                type="number"
                class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
                placeholder="30"
              />
            </div>

            <div>
              <label for="bank-account-details" class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-1">
                Bank Account Details
              </label>
              <input id="bank-account-details"
                v-model="form.bank_account"
                type="text"
                class="w-full px-4 py-2 border border-gray-300 dark:border-surface-600 rounded-lg focus:ring-blue-500 focus:border-transparent"
                placeholder="Account number or IBAN"
              />
            </div>
          </div>
        </div>

        <!-- Status -->
        <div class="pb-6">
          <label class="flex items-center">
            <input
              v-model="form.is_active"
              type="checkbox"
              class="h-4 w-4 text-primary-700 dark:text-primary-300 border-gray-300 dark:border-surface-600 rounded focus:ring-blue-500"
            />
            <span class="ml-2 text-sm text-surface-700 dark:text-surface-300">Active Supplier</span>
          </label>
        </div>

        <!-- Actions -->
        <div class="flex gap-4">
          <button
            type="submit"
            :disabled="loading"
            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
          >
            {{ loading ? 'Saving...' : 'Save Supplier' }}
          </button>
          <Link
            href="/suppliers"
            class="px-6 py-2 border border-gray-300 dark:border-surface-600 text-surface-700 dark:text-surface-300 rounded-lg hover:bg-gray-50 dark:bg-surface-800 dark:bg-surface-800"
          >
            Cancel
          </Link>
        </div>

        <p v-if="submitError" class="text-sm text-red-700 dark:text-red-300 mt-4">{{ submitError }}</p>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useRouteId } from '@/composables/useRouteId'
const routeId = useRouteId()
const isEditing = computed(() => !!routeId.value)
const loading = ref(false)
const submitError = ref('')
const errors = ref({})

const form = ref({
  name: '',
  code: '',
  email: '',
  phone: '',
  contact_person: '',
  contact_email: '',
  contact_phone: '',
  contact_mobile: '',
  street_address: '',
  city: '',
  state: '',
  postal_code: '',
  country: '',
  currency: 'USD',
  tax_id: '',
  payment_terms: 30,
  bank_account: '',
  is_active: true,
})

const loadSupplier = async () => {
  if (!isEditing.value) return

  try {
    const response = await fetch(`/api/v1/achats/suppliers/${routeId.value}`, {
      headers: {
        'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]').content}`
      }
    })
    if (response.ok) {
      const data = await response.json()
      Object.assign(form.value, data)
    }
  } catch (error) {
    console.error('Failed to load supplier:', error)
    submitError.value = 'Failed to load supplier data'
  }
}

const handleSubmit = async () => {
  loading.value = true
  submitError.value = ''
  errors.value = {}

  try {
    const url = isEditing.value
      ? `/api/v1/achats/suppliers/${routeId.value}`
      : '/api/v1/achats/suppliers'
    const method = isEditing.value ? 'PATCH' : 'POST'

    const response = await fetch(url, {
      method,
      headers: {
        'Authorization': `Bearer ${document.querySelector('meta[name="api-token"]').content}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(form.value)
    })

    if (!response.ok) {
      const data = await response.json()
      if (data.errors) {
        errors.value = data.errors
      } else {
        submitError.value = data.message || 'Failed to save supplier'
      }
      return
    }

    window.location.href = '/suppliers'
  } catch (error) {
    console.error('Failed to save supplier:', error)
    submitError.value = 'An error occurred while saving'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadSupplier()
})
</script>
