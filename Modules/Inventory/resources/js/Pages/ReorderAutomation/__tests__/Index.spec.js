import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import axios from 'axios'
import Index from '../Index.vue'

vi.mock('axios')

const stubs = {
  AppLayout: { template: '<div><slot /></div>' },
}

describe('ReorderAutomation/Index.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('builds AI reorder suggestions from the real low-stock report, not a fake /reorder/* API', async () => {
    axios.get.mockImplementation((url) => {
      if (url === '/api/v1/inventory/low-stock') {
        return Promise.resolve({ data: { low_stock_items: [
          { product_id: 1, product_name: 'Chaise', quantity: 2, reorder_level: 10, warehouse_id: 1 },
        ] } })
      }
      if (url === '/api/v1/inventory/seasonal-factors') {
        return Promise.resolve({ data: { data: [] } })
      }
      return Promise.resolve({ data: {} })
    })
    axios.post.mockResolvedValue({ data: { suggestions: [
      { product_id: 1, action: 'reorder', suggested_qty: 20, urgency: 'urgent', reason: 'Stock critique.' },
    ] } })

    const wrapper = mount(Index, { global: { stubs } })
    await flushPromises()

    expect(axios.get).toHaveBeenCalledWith('/api/v1/inventory/low-stock')
    expect(axios.post).toHaveBeenCalledWith('/api/v1/inventory/ai/suggest-reorder', {
      stock: [{ product_id: 1, product_name: 'Chaise', quantity: 2, reorder_level: 10, warehouse_id: 1 }],
    })
    expect(wrapper.text()).toContain('Chaise')
    expect(wrapper.text()).toContain('Stock critique.')
    expect(wrapper.text()).toContain('urgent')
  })

  it('loads real seasonal factors and creates a new one via the real CRUD endpoint', async () => {
    axios.get.mockImplementation((url) => {
      if (url === '/api/v1/inventory/low-stock') return Promise.resolve({ data: { low_stock_items: [] } })
      if (url === '/api/v1/inventory/seasonal-factors') {
        return Promise.resolve({ data: { data: [
          { id: 9, product_id: 3, category_id: null, period_type: 'monthly', period_index: 12, factor: 1.8, product: { name: 'Guirlande de Noël' } },
        ] } })
      }
      return Promise.resolve({ data: {} })
    })

    const wrapper = mount(Index, { global: { stubs } })
    await flushPromises()
    wrapper.vm.selectedTab = 'seasonal'
    await flushPromises()

    expect(wrapper.text()).toContain('Guirlande de Noël')
    expect(wrapper.text()).toContain('1.8x')

    axios.post.mockResolvedValue({ data: {} })
    await wrapper.vm.saveFactor()

    expect(axios.post).toHaveBeenCalledWith('/api/v1/inventory/seasonal-factors', expect.objectContaining({
      period_type: 'monthly',
    }))
  })
})
