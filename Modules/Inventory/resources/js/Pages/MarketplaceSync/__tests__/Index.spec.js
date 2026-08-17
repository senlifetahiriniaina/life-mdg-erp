import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import axios from 'axios'
import Index from '../Index.vue'

vi.mock('axios')

const stubs = {
  AppLayout: { template: '<div><slot /></div>' },
  Card: { template: '<div><slot name="header" /><slot name="content" /><slot /></div>' },
  Button: { template: '<button><slot /></button>', props: ['loading', 'disabled', 'label'] },
  DataTable: { template: '<div><slot name="header" /><slot /></div>', props: ['value', 'loading'] },
  Column: { template: '<div><slot :data="{}" /></div>', props: ['field', 'header'] },
  Tag: { template: '<span><slot>{{ value }}</slot></span>', props: ['value'] },
  Select: { template: '<select></select>', props: ['modelValue', 'options'] },
}

describe('MarketplaceSync/Index.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('targets the single real ecommerce-sync endpoint, not a fabricated Shopify/Amazon/Jumia/eBay multi-channel API', async () => {
    axios.get.mockImplementation((url) => {
      if (url === '/api/v1/inventory/products') return Promise.resolve({ data: { data: [{ id: 1, sku: 'SKU-1', name: 'Chaise', ecommerce_synced_at: null }] } })
      if (url === '/api/v1/inventory/sync/ecommerce/status') return Promise.resolve({ data: { last_sync_at: null, pending_count: 3 } })
      return Promise.resolve({ data: {} })
    })

    const wrapper = mount(Index, { global: { stubs } })
    await flushPromises()

    expect(axios.get).toHaveBeenCalledWith('/api/v1/inventory/sync/ecommerce/status')
    expect(wrapper.text()).toContain('3')
    expect(wrapper.text()).toContain('Jamais')
  })

  it('syncs the whole catalog via sync/ecommerce/all', async () => {
    axios.get.mockResolvedValue({ data: { data: [], last_sync_at: null, pending_count: 0 } })
    axios.post.mockResolvedValue({ data: { message: 'Full sync queued successfully.' } })

    const wrapper = mount(Index, { global: { stubs } })
    await flushPromises()
    await wrapper.vm.syncAll()
    await flushPromises()

    expect(axios.post).toHaveBeenCalledWith('/api/v1/inventory/sync/ecommerce/all')
    expect(wrapper.text()).toContain('Full sync queued successfully.')
  })
})
