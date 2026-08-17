import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import axios from 'axios'
import Index from '../Index.vue'

vi.mock('axios')

vi.mock('@/composables/useAiAssistant', () => ({
  useAiAssistant: () => ({ guidance: { value: null } }),
}))

const stubs = {
  AppLayout: { template: '<div><slot /></div>' },
  Card: { template: '<div><slot name="header" /><slot name="content" /><slot /></div>' },
  Button: { template: '<button type="submit"><slot /></button>', props: ['disabled', 'loading'] },
  DataTable: { template: '<div><slot /><template v-for="row in value"><slot name="default" :data="row" /></template></div>', props: ['value', 'loading'] },
  Column: { template: '<div><slot :data="{}" /></div>', props: ['field', 'header'] },
  Tag: { template: '<span><slot>{{ value }}</slot></span>', props: ['value'] },
  Select: { template: '<select></select>', props: ['modelValue', 'options'] },
  InputNumber: { template: '<input type="number" />', props: ['modelValue'] },
  ProgressBar: { template: '<div />', props: ['value'] },
  AIAssistantPanel: { template: '<div />' },
}

describe('DemandForecast/Index.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('generates a real forecast via demand-forecasts/generate instead of setTimeout mock data', async () => {
    axios.get.mockImplementation((url) => {
      if (url === '/api/v1/inventory/products') return Promise.resolve({ data: { data: [{ id: 1, name: 'Écran LCD 24"' }] } })
      if (url === '/api/v1/inventory/demand-forecasts') return Promise.resolve({ data: { data: [] } })
      return Promise.resolve({ data: {} })
    })
    axios.post.mockResolvedValue({ data: { forecasts: [{ id: 1 }, { id: 2 }], count: 2 } })

    const wrapper = mount(Index, { global: { stubs } })
    await flushPromises()

    wrapper.vm.form.product_id = 1
    await wrapper.vm.generateForecast()
    await flushPromises()

    expect(axios.post).toHaveBeenCalledWith('/api/v1/inventory/demand-forecasts/generate', {
      product_id: 1, method: 'moving_average', months: 3,
    })
    expect(wrapper.text()).toContain('2 prévision(s) générée(s).')
  })
})
