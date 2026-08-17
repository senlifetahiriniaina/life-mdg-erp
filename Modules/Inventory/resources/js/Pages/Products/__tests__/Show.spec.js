import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import axios from 'axios'
import Show from '../Show.vue'

vi.mock('axios')

vi.mock('@inertiajs/vue3', () => ({
  Link: { template: '<a><slot /></a>' },
}))

describe('Inventory Products/Show.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('loads the product via a plain axios call (session cookie auth), not a Bearer meta-tag header', async () => {
    window.history.pushState({}, '', '/inventory/products/1')
    axios.get.mockImplementation((url) => {
      if (url === '/api/v1/inventory/products/1') {
        return Promise.resolve({ data: { data: { id: 1, name: 'Chaise', sku: 'CH-1', cost_price: 10, selling_price: 15, margin_percent: 50, quantity_on_hand: 4, reorder_level: 2, status: 'active', is_low_stock: false } } })
      }
      if (url === '/api/v1/inventory/products/1/history') return Promise.resolve({ data: { data: [] } })
      return Promise.resolve({ data: {} })
    })

    mount(Show)
    await flushPromises()

    expect(axios.get).toHaveBeenCalledWith('/api/v1/inventory/products/1')
    // No custom Authorization header is passed — axios.get is called with just the URL.
    expect(axios.get.mock.calls[0].length).toBe(1)
  })
})
