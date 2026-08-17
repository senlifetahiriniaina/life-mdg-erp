import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import axios from 'axios'
import Index from '../Index.vue'

vi.mock('axios')

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div><slot /></div>' },
  usePage: () => ({ props: { auth: { user: { roles: ['security-admin'], permissions: [] } } } }),
}))

const stubs = {
  AppLayout: { template: '<div><slot /></div>' },
}

describe('Security/Index.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('loads real incidents/threats and computes stats client-side (no fake dashboard/summary call)', async () => {
    axios.get.mockImplementation((url) => {
      if (url === '/api/v1/security/incidents') return Promise.resolve({ data: { data: [{ id: 1, incident_type: 'intrusion_attempt', severity: 'high', description: 'Tentative détectée.' }], total: 1 } })
      if (url === '/api/v1/security/threat-indicators') return Promise.resolve({ data: { data: [{ id: 2, indicator_value: '1.2.3.4', indicator_type: 'ip', source: 'firewall', threat_level: 'critical' }] } })
      if (url === '/api/v1/security/auth-events/summary') return Promise.resolve({ data: { data: { failed: 7, login: 40 } } })
      if (url === '/api/v1/security/compliance/controls') return Promise.resolve({ data: { data: [{ id: 1, implementation_status: 'implemented' }, { id: 2, implementation_status: 'not_implemented' }] } })
      return Promise.resolve({ data: {} })
    })

    const wrapper = mount(Index, { props: {}, global: { stubs } })
    await flushPromises()

    expect(axios.get).not.toHaveBeenCalledWith('/api/v1/security/dashboard/summary')
    expect(axios.get).toHaveBeenCalledWith('/api/v1/security/threat-indicators', { params: { per_page: 5 } })
    expect(wrapper.text()).toContain('intrusion_attempt')
    expect(wrapper.text()).toContain('1.2.3.4')
    expect(wrapper.text()).toContain('7') // auth_failures_24h
    expect(wrapper.text()).toContain('50%') // 1/2 controls implemented
  })

  it('shows a placeholder compliance score when no controls exist yet', async () => {
    axios.get.mockImplementation((url) => {
      if (url === '/api/v1/security/compliance/controls') return Promise.resolve({ data: { data: [] } })
      return Promise.resolve({ data: { data: [] } })
    })

    const wrapper = mount(Index, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).toContain('—')
    expect(wrapper.text()).toContain('Aucun contrôle de conformité enregistré')
  })
})
