import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import axios from 'axios'
import Index from '../Index.vue'

vi.mock('axios')

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div><slot /></div>' },
  usePage: () => ({ props: { auth: { user: { roles: ['manager'], permissions: [] } } } }),
}))

const stubs = {
  AppLayout: { template: '<div><slot /></div>' },
  // PrimeVue's Select registers a matchMedia listener on mount that jsdom
  // doesn't implement — stub it out, the other PrimeVue components used on
  // this page (Card/Button/DataTable/Tag/ProgressBar/TabView) render fine
  // without the PrimeVue plugin installed.
  Select: { template: '<select><slot /></select>', props: ['modelValue', 'options'] },
}

const mockModels = [
  { id: 1, name: 'Prévision CA', model_type: 'linear_regression', entity_type: 'revenue', accuracy_score: 0.91, last_trained_at: '2026-05-01T00:00:00Z', forecast_horizon_days: 30 },
]

const mockAnomalies = [
  { id: 5, entity_type: 'revenue', metric_name: 'monthly_revenue', anomaly_date: '2026-05-10', deviation_percent: 35, severity: 'high', status: 'new' },
]

const mockRevenueTrend = {
  months: [{ month: '2026-04', revenue: 1000, growth_rate: 0.1 }],
  trend: 'up',
  forecast: [{ month: '2026-05', forecast_value: 1100 }],
}

describe('PredictiveAnalytics/Index.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('loads real predictive models, anomalies and revenue trend on mount', async () => {
    axios.get.mockImplementation((url) => {
      if (url === '/api/v1/bi/predictive-models') return Promise.resolve({ data: { data: mockModels, total: 1 } })
      if (url === '/api/v1/bi/anomalies') return Promise.resolve({ data: { data: mockAnomalies, total: 1 } })
      if (url === '/api/v1/bi/analytics/revenue-trend') return Promise.resolve({ data: mockRevenueTrend })
      return Promise.resolve({ data: {} })
    })

    const wrapper = mount(Index, { global: { stubs } })
    await flushPromises()

    expect(axios.get).toHaveBeenCalledWith('/api/v1/bi/predictive-models')
    expect(axios.get).toHaveBeenCalledWith('/api/v1/bi/anomalies')
    expect(axios.get).toHaveBeenCalledWith('/api/v1/bi/analytics/revenue-trend', expect.objectContaining({ params: { months: 12 } }))
    expect(wrapper.text()).toContain('Prévision CA')
    expect(wrapper.text()).toContain('monthly_revenue')
  })

  it('acknowledges an anomaly via the real endpoint', async () => {
    axios.get.mockImplementation((url) => {
      if (url === '/api/v1/bi/predictive-models') return Promise.resolve({ data: { data: [], total: 0 } })
      if (url === '/api/v1/bi/anomalies') return Promise.resolve({ data: { data: mockAnomalies, total: 1 } })
      if (url === '/api/v1/bi/analytics/revenue-trend') return Promise.resolve({ data: mockRevenueTrend })
      return Promise.resolve({ data: {} })
    })
    axios.post.mockResolvedValue({ data: {} })

    const wrapper = mount(Index, { global: { stubs } })
    await flushPromises()

    await wrapper.vm.acknowledge(wrapper.vm.anomalies[0])
    await flushPromises()

    expect(axios.post).toHaveBeenCalledWith('/api/v1/bi/anomalies/5/acknowledge')
    expect(wrapper.vm.anomalies[0].status).toBe('acknowledged')
  })

  it('generates forecasts for a model via the real endpoint', async () => {
    axios.get.mockImplementation((url) => {
      if (url === '/api/v1/bi/predictive-models') return Promise.resolve({ data: { data: mockModels, total: 1 } })
      if (url === '/api/v1/bi/anomalies') return Promise.resolve({ data: { data: [], total: 0 } })
      if (url === '/api/v1/bi/analytics/revenue-trend') return Promise.resolve({ data: mockRevenueTrend })
      return Promise.resolve({ data: {} })
    })
    axios.post.mockResolvedValue({ data: {} })

    const wrapper = mount(Index, { global: { stubs } })
    await flushPromises()

    await wrapper.vm.generateForecasts(wrapper.vm.models[0])
    await flushPromises()

    expect(axios.post).toHaveBeenCalledWith('/api/v1/bi/predictive-models/1/generate', { days: 30 })
  })
})
