<template>
  <AppLayout>
    <Head :title="invoice ? 'Edit Invoice' : 'New Invoice'" />

    <div class="max-w-5xl mx-auto space-y-6">
      <!-- Page header -->
      <div class="flex items-center gap-4">
        <Button
          icon="pi pi-arrow-left"
          outlined
          @click="cancel"
        />
        <div>
          <h1 class="text-2xl font-bold text-surface-900 dark:text-surface-50">
            {{ invoice ? `Invoice ${invoice.number}` : 'New Invoice' }}
          </h1>
        </div>
      </div>

      <!-- Form card -->
      <div class="bg-surface-0 dark:bg-surface-800 rounded-xl border border-surface-200 dark:border-surface-700 p-6">
        <form class="space-y-6" @submit.prevent="submit('draft')">

          <!-- Header fields -->
          <div>
            <h2 class="text-base font-semibold text-surface-700 dark:text-surface-200 mb-4">Invoice Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-surface-700 dark:text-surface-200">
                  Type <span class="text-red-500">*</span>
                </label>
                <Select
                  v-model="form.type"
                  :options="typeOptions"
                  option-label="label"
                  option-value="value"
                  class="w-full"
                  :class="{ 'p-invalid': errors.type }"
                />
                <small v-if="errors.type" class="text-red-500">{{ errors.type }}</small>
              </div>

              <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-surface-700 dark:text-surface-200">
                  Partner Name <span class="text-red-500">*</span>
                </label>
                <InputText
                  v-model="form.partner_name"
                  :class="{ 'p-invalid': errors.partner_name }"
                  placeholder="Company or person name"
                />
                <small v-if="errors.partner_name" class="text-red-500">{{ errors.partner_name }}</small>
              </div>

              <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Partner Type</label>
                <Select
                  v-model="form.partner_type"
                  :options="partnerTypeOptions"
                  option-label="label"
                  option-value="value"
                  class="w-full"
                />
              </div>

              <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-surface-700 dark:text-surface-200">
                  Invoice Date <span class="text-red-500">*</span>
                </label>
                <DatePicker
                  v-model="form.invoice_date"
                  date-format="yy-mm-dd"
                  class="w-full"
                  :class="{ 'p-invalid': errors.invoice_date }"
                />
                <small v-if="errors.invoice_date" class="text-red-500">{{ errors.invoice_date }}</small>
              </div>

              <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Due Date</label>
                <DatePicker
                  v-model="form.due_date"
                  date-format="yy-mm-dd"
                  class="w-full"
                />
              </div>

              <div class="flex flex-col gap-1">
                <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Currency</label>
                <Select
                  v-model="form.currency"
                  :options="currencyOptions"
                  class="w-full"
                />
              </div>
            </div>
          </div>

          <!-- Lines -->
          <div>
            <div class="flex items-center justify-between mb-4">
              <h2 class="text-base font-semibold text-surface-700 dark:text-surface-200">Invoice Lines</h2>
              <Button
                type="button"
                icon="pi pi-plus"
                label="Add Line"
                size="small"
                outlined
                @click="addLine"
              />
            </div>

            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-b border-surface-200 dark:border-surface-700">
                    <th scope="col" class="text-left py-2 px-3 font-medium text-surface-600 dark:text-surface-300 w-5/12">Description</th>
                    <th scope="col" class="text-right py-2 px-3 font-medium text-surface-600 dark:text-surface-300 w-1/12">Qty</th>
                    <th scope="col" class="text-right py-2 px-3 font-medium text-surface-600 dark:text-surface-300 w-2/12">Unit Price</th>
                    <th scope="col" class="text-right py-2 px-3 font-medium text-surface-600 dark:text-surface-300 w-1/12">Tax %</th>
                    <th scope="col" class="text-right py-2 px-3 font-medium text-surface-600 dark:text-surface-300 w-2/12">Subtotal</th>
                    <th scope="col" class="w-8"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(line, index) in form.lines"
                    :key="index"
                    class="border-b border-surface-100 dark:border-surface-700"
                  >
                    <td class="py-2 px-3">
                      <InputText
                        v-model="line.description"
                        placeholder="Description"
                        class="w-full"
                        :class="{ 'p-invalid': lineErrors[index]?.description }"
                      />
                    </td>
                    <td class="py-2 px-3">
                      <InputNumber
                        v-model="line.quantity"
                        :min="0"
                        :min-fraction-digits="0"
                        class="w-full text-right"
                        @update:model-value="recalculate"
                      />
                    </td>
                    <td class="py-2 px-3">
                      <InputNumber
                        v-model="line.unit_price"
                        :min="0"
                        :min-fraction-digits="2"
                        :max-fraction-digits="2"
                        class="w-full text-right"
                        @update:model-value="recalculate"
                      />
                    </td>
                    <td class="py-2 px-3">
                      <InputNumber
                        v-model="line.tax_rate"
                        :min="0"
                        :max="100"
                        :min-fraction-digits="0"
                        :max-fraction-digits="2"
                        class="w-full text-right"
                        @update:model-value="recalculate"
                      />
                    </td>
                    <td class="py-2 px-3 text-right font-medium">
                      {{ formatCurrency(lineSubtotal(line), form.currency) }}
                    </td>
                    <td class="py-2 px-3">
                      <Button
                        type="button"
                        icon="pi pi-times"
                        text
                        severity="danger"
                        size="small"
                        :disabled="form.lines.length === 1"
                        @click="removeLine(index)"
                      />
                    </td>
                  </tr>
                  <tr v-if="form.lines.length === 0">
                    <td colspan="6" class="py-8 text-center text-surface-400">
                      No lines added yet. Click "Add Line" to start.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Totals -->
            <div class="mt-4 flex justify-end">
              <div class="w-64 space-y-2">
                <div class="flex justify-between text-sm">
                  <span class="text-surface-600 dark:text-surface-300">Subtotal</span>
                  <span class="font-medium">{{ formatCurrency(totals.subtotal, form.currency) }}</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span class="text-surface-600 dark:text-surface-300">Tax</span>
                  <span class="font-medium">{{ formatCurrency(totals.tax, form.currency) }}</span>
                </div>
                <div class="flex justify-between text-base font-bold border-t border-surface-200 dark:border-surface-700 pt-2">
                  <span>Total</span>
                  <span>{{ formatCurrency(totals.total, form.currency) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex justify-end gap-2 pt-4 border-t border-surface-200 dark:border-surface-700">
            <Button
              type="button"
              label="Cancel"
              outlined
              @click="cancel"
            />
            <Button
              type="button"
              label="Save as Draft"
              outlined
              severity="secondary"
              :loading="submitting && submitAction === 'draft'"
              @click="submit('draft')"
            />
            <Button
              type="button"
              label="Send"
              icon="pi pi-send"
              :loading="submitting && submitAction === 'sent'"
              @click="submit('sent')"
            />
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import AppLayout from '@/Layouts/AppLayout.vue'

// Chantier 32 (volet B) — remplace le champ libre par un vrai sélecteur ;
// les 4 devises explicitement demandées d'abord (MGA en tête, cohérent
// avec le reste de l'app), puis le reste des devises réellement seedées
// (shared_currencies, Chantier 17) pour ne pas retirer de choix existant.
const currencyOptions = [
  'MGA', 'EUR', 'USD', 'CNY',
  'XOF', 'XAF', 'MAD', 'NGN', 'GHS', 'KES', 'TZS', 'INR', 'EGP',
]

interface InvoiceLine {
  id?: number
  description: string
  quantity: number
  unit_price: number
  tax_rate: number
  subtotal?: number
  total?: number
}

interface Invoice {
  id: number
  number: string
  type: string
  partner_name: string | null
  partner_type: string | null
  invoice_date: string | null
  due_date: string | null
  currency: string
  lines: InvoiceLine[]
}

const props = defineProps<{
  invoice?: Invoice | null
}>()

const submitting = ref(false)
const submitAction = ref<'draft' | 'sent'>('draft')
const errors = reactive<Record<string, string>>({})
const lineErrors = reactive<Record<number, Record<string, string>>>({})

const typeOptions = [
  { label: 'Customer Invoice', value: 'customer_invoice' },
  { label: 'Supplier Invoice', value: 'supplier_invoice' },
  { label: 'Credit Note (Customer)', value: 'customer_credit_note' },
  { label: 'Credit Note (Supplier)', value: 'supplier_credit_note' },
]

const partnerTypeOptions = [
  { label: 'Customer', value: 'customer' },
  { label: 'Vendor', value: 'vendor' },
]

const parseDateProp = (val: string | null): Date | null => {
  if (!val) return null
  return new Date(val)
}

const form = reactive({
  type: props.invoice?.type ?? 'customer_invoice',
  partner_name: props.invoice?.partner_name ?? '',
  partner_type: props.invoice?.partner_type ?? 'customer',
  invoice_date: parseDateProp(props.invoice?.invoice_date ?? null) as Date | null,
  due_date: parseDateProp(props.invoice?.due_date ?? null) as Date | null,
  currency: props.invoice?.currency ?? 'MGA',
  lines: (props.invoice?.lines?.map(l => ({
    id: l.id,
    description: l.description,
    quantity: l.quantity,
    unit_price: l.unit_price,
    tax_rate: l.tax_rate ?? 0,
  })) ?? [{ description: '', quantity: 1, unit_price: 0, tax_rate: 0 }]) as InvoiceLine[],
})

const lineSubtotal = (line: InvoiceLine): number => {
  const base = (line.quantity ?? 0) * (line.unit_price ?? 0)
  const tax = base * ((line.tax_rate ?? 0) / 100)
  return base + tax
}

const totals = computed(() => {
  const subtotal = form.lines.reduce((sum, l) => sum + (l.quantity ?? 0) * (l.unit_price ?? 0), 0)
  const tax = form.lines.reduce((sum, l) => {
    const base = (l.quantity ?? 0) * (l.unit_price ?? 0)
    return sum + base * ((l.tax_rate ?? 0) / 100)
  }, 0)
  return { subtotal, tax, total: subtotal + tax }
})

const recalculate = () => {
  // Totals are reactive via computed — no manual trigger needed
}

const formatCurrency = (value: number, currency = 'USD'): string => {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(value)
}

const formatDatePayload = (date: Date | null): string | null => {
  if (!date) return null
  return date.toISOString().split('T')[0]
}

const addLine = () => {
  form.lines.push({ description: '', quantity: 1, unit_price: 0, tax_rate: 0 })
}

const removeLine = (index: number) => {
  form.lines.splice(index, 1)
}

const cancel = () => {
  router.visit('/accounting/invoices')
}

const submit = async (action: 'draft' | 'sent') => {
  submitting.value = true
  submitAction.value = action
  Object.keys(errors).forEach(k => delete errors[k])
  Object.keys(lineErrors).forEach(k => delete lineErrors[Number(k)])

  const url = props.invoice
    ? `/api/v1/accounting/invoices/${props.invoice.id}`
    : '/api/v1/accounting/invoices'

  const method = props.invoice ? 'PUT' : 'POST'

  try {
    const response = await fetch(url, {
      method,
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({
        type: form.type,
        partner_name: form.partner_name,
        partner_type: form.partner_type,
        invoice_date: formatDatePayload(form.invoice_date),
        due_date: formatDatePayload(form.due_date),
        currency: form.currency,
        status: action,
        lines: form.lines.map(l => ({
          id: l.id,
          description: l.description,
          quantity: l.quantity,
          unit_price: l.unit_price,
          tax_rate: l.tax_rate,
        })),
      }),
    })

    if (!response.ok) {
      const data = await response.json()
      if (data.errors) {
        Object.assign(errors, data.errors)
      }
      return
    }

    router.visit('/accounting/invoices')
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  // Could pre-load journals or other data here
})
</script>
