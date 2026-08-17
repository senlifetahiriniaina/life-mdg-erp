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
}

const mockAnomaly = { id: 'abc', module: 'Inventory', type: 'low_stock', severity: 'critical', title: 'Rupture de stock: Chaise', description: 'Stock à 0.' }

function mockGet() {
  axios.get.mockImplementation((url) => {
    if (url === '/api/v1/forecasting/models') return Promise.resolve({ data: { data: [{ id: 1, name: 'Prévision ventes', module: 'CRM', algorithm: 'linear_regression', horizon_days: 30, is_active: true }] } })
    if (url === '/api/v1/ai/anomalies') return Promise.resolve({ data: { data: [mockAnomaly], count: 1 } })
    if (url === '/api/v1/forecasting/alerts') return Promise.resolve({ data: { data: [] } })
    if (url === '/api/v1/analytics/predictions') return Promise.resolve({ data: { data: [], meta: {} } })
    if (url === '/api/v1/analytics/ml-models') return Promise.resolve({ data: { data: [] } })
    return Promise.resolve({ data: {} })
  })
}

describe('Analytics/Index.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('loads real forecast models and anomalies from the corrected endpoints', async () => {
    mockGet()

    const wrapper = mount(Index, { global: { stubs } })
    await flushPromises()

    expect(axios.get).toHaveBeenCalledWith('/api/v1/forecasting/models', { params: { active: true, per_page: 12 } })
    expect(axios.get).toHaveBeenCalledWith('/api/v1/ai/anomalies')
    expect(axios.get).toHaveBeenCalledWith('/api/v1/forecasting/alerts')
    expect(wrapper.text()).toContain('Prévision ventes')
    expect(wrapper.text()).toContain('Rupture de stock: Chaise')
    expect(wrapper.text()).toContain('critical')
    // The removed deviation_score binding must not leak a "NaN" into the anomaly card.
    expect(wrapper.text()).not.toContain('NaN')
  })

  it('loads real prediction models on the Prédictions tab', async () => {
    mockGet()
    axios.get.mockImplementation((url) => {
      if (url === '/api/v1/analytics/predictions') return Promise.resolve({ data: { data: [{ id: 5, model_name: 'Prévision demande', model_type: 'regression', status: 'trained' }] } })
      return Promise.resolve({ data: { data: [] } })
    })

    const wrapper = mount(Index, { global: { stubs } })
    await flushPromises()
    wrapper.vm.activeTab = 'predictions'
    await flushPromises()

    expect(wrapper.text()).toContain('Prévision demande')
  })

  it('loads recommendations for a chosen entity type/id', async () => {
    mockGet()

    const wrapper = mount(Index, { global: { stubs } })
    await flushPromises()

    axios.get.mockResolvedValueOnce({ data: { data: [{ id: 1, title: 'Relancer le client', description: 'Aucune commande depuis 60 jours.' }] } })
    wrapper.vm.activeTab = 'recommendations'
    wrapper.vm.recipientType = 'Customer'
    wrapper.vm.recipientId = 42
    await wrapper.vm.loadRecommendations()
    await flushPromises()

    expect(axios.get).toHaveBeenCalledWith('/api/v1/analytics/recommendations/for-user', {
      params: { user_type: 'Customer', user_id: 42 },
    })
    expect(wrapper.text()).toContain('Relancer le client')
  })
})
