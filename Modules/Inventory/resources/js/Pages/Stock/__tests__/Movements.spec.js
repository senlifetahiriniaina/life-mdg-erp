import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import axios from 'axios'
import Movements from '../Movements.vue'

vi.mock('axios')

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
}))

const stubs = {
  AppLayout: { template: '<div><slot /></div>' },
  Button: { template: '<button><slot /></button>', props: ['label', 'loading', 'outlined', 'icon'] },
  Select: { template: '<select></select>', props: ['modelValue', 'options'] },
  DataTable: { template: '<div><slot /><template v-for="row in value"><slot name="default" :data="row" /></template></div>', props: ['value', 'loading'] },
  Column: { template: '<div><slot :data="{}" /></div>', props: ['field', 'header'] },
  Tag: { template: '<span />', props: ['value'] },
  InputNumber: { template: '<input type="number" />', props: ['modelValue'] },
  Textarea: { template: '<textarea></textarea>', props: ['modelValue'] },
  DatePicker: { template: '<input type="date" />', props: ['modelValue'] },
  Dialog: { template: '<div><slot /></div>', props: ['visible'] },
  Paginator: { template: '<div />', props: ['rows', 'totalRecords', 'first'] },
}

describe('Inventory Stock/Movements.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('fetches from the real stock-movements endpoint, not the non-existent movements one', async () => {
    axios.get.mockImplementation((url) => {
      if (url.startsWith('/api/v1/inventory/stock-movements')) {
        return Promise.resolve({ data: { data: [{ id: 1, type: 'in', quantity: 5, reason: 'Réception fournisseur', product: { name: 'Chaise', sku: 'CH-1' }, warehouse: { name: 'Entrepôt A' }, created_at: '2026-05-01T10:00:00Z' }], meta: { current_page: 1, total: 1, last_page: 1 } } })
      }
      if (url.startsWith('/api/v1/inventory/products')) return Promise.resolve({ data: { data: [] } })
      if (url.startsWith('/api/v1/inventory/warehouses')) return Promise.resolve({ data: { data: [] } })
      return Promise.resolve({ data: {} })
    })

    const wrapper = mount(Movements, { global: { stubs } })
    await flushPromises()

    const calledUrls = axios.get.mock.calls.map(c => c[0])
    expect(calledUrls.some(u => u.startsWith('/api/v1/inventory/stock-movements'))).toBe(true)
    expect(calledUrls.some(u => u.startsWith('/api/v1/inventory/movements') && !u.includes('stock-movements'))).toBe(false)
    expect(wrapper.vm.pagination.total).toBe(1)
  })

  it('submits a new movement with a real reason field, not the invented unit_cost/reference fields', async () => {
    axios.get.mockResolvedValue({ data: { data: [], meta: { current_page: 1, total: 0, last_page: 1 } } })
    axios.post.mockResolvedValue({ data: {} })

    const wrapper = mount(Movements, { global: { stubs } })
    await flushPromises()

    wrapper.vm.movementForm.product_id = 1
    wrapper.vm.movementForm.warehouse_id = 2
    wrapper.vm.movementForm.quantity = 10
    wrapper.vm.movementForm.reason = 'Comptage cycle'
    await wrapper.vm.submitMovement()

    expect(axios.post).toHaveBeenCalledWith('/api/v1/inventory/stock-movements', {
      product_id: 1, warehouse_id: 2, type: 'in', quantity: 10, reason: 'Comptage cycle',
    })
  })
})
