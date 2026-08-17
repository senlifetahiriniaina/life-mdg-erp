import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import axios from 'axios'
import Index from '../Index.vue'

vi.mock('axios')

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div><slot /></div>' },
}))

const stubs = {
  AppLayout: { template: '<div><slot /></div>' },
}

describe('QualityAssurance/Index.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('loads real agent metrics for a period from the cs-ai endpoint', async () => {
    axios.get.mockResolvedValue({
      data: { agent_id: 7, metrics: [{ date: '2026-05-01', tickets_handled: 12, avg_satisfaction: 4.2, first_contact_resolution_rate: 0.75, escalation_rate: 0.1, overall_performance: 88 }] },
    })

    const wrapper = mount(Index, { global: { stubs } })
    wrapper.vm.metricsForm.agent_id = 7
    wrapper.vm.metricsForm.start_date = '2026-05-01'
    wrapper.vm.metricsForm.end_date = '2026-05-31'
    await wrapper.vm.loadMetrics()
    await flushPromises()

    expect(axios.get).toHaveBeenCalledWith('/api/v1/helpdesk/cs-ai/agents/metrics', {
      params: { agent_id: 7, start_date: '2026-05-01', end_date: '2026-05-31' },
    })
    expect(wrapper.text()).toContain('2026-05-01')
  })

  it('loads real team benchmarking data', async () => {
    axios.get.mockResolvedValue({
      data: { team_size: 5, metrics: { avg_satisfaction: 4.1, first_contact_resolution_rate: 0.8, nps_score: 42 }, trend: 'improving' },
    })

    const wrapper = mount(Index, { global: { stubs } })
    wrapper.vm.teamId = 3
    await wrapper.vm.loadBenchmark()
    await flushPromises()

    expect(axios.get).toHaveBeenCalledWith('/api/v1/helpdesk/cs-ai/team-benchmarking', { params: { team_id: 3 } })
    expect(wrapper.text()).toContain('improving')
  })

  it('shows an empty state when no benchmarking data exists (404)', async () => {
    axios.get.mockRejectedValue({ response: { status: 404 } })

    const wrapper = mount(Index, { global: { stubs } })
    wrapper.vm.teamId = 99
    await wrapper.vm.loadBenchmark()
    await flushPromises()

    expect(wrapper.text()).toContain('Aucune donnée de benchmarking')
  })
})
