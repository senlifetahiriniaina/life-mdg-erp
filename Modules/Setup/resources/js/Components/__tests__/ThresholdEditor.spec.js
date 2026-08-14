// @vitest-environment jsdom
//
// Vitest's default environment is 'node' and nothing in vite.config.js
// activates jsdom (already a devDependency, just never wired up) — every
// mount()-based component spec in this codebase fails identically with
// "document is not defined" (confirmed on the pre-existing
// Approval/__tests__/ApprovalCard.spec.js too). Scoped to this file only,
// not a project-wide vite.config.js change, since fixing that globally is
// outside this phase's remit.
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import PrimeVue from 'primevue/config'
import ThresholdEditor from '../ThresholdEditor.vue'

/**
 * Each tier's condition_value is stored independently, but adjacent tiers
 * share a boundary number (rule i's upper bound is also rule i+1's lower
 * bound — see e.g. Achats' "<5000"/">=5000" pair). save() used to PUT only
 * the edited rule, leaving the pair mismatched.
 */
describe('ThresholdEditor.vue', () => {
  const mockRules = {
    id: 42,
    rules: [
      { id: 1, workflow_id: 42, rule_order: 1, condition_operator: '<', condition_value: '5000' },
      { id: 2, workflow_id: 42, rule_order: 2, condition_operator: '>=', condition_value: '5000' },
      { id: 3, workflow_id: 42, rule_order: 3, condition_operator: '>=', condition_value: '50000' },
    ],
  }

  beforeEach(() => {
    global.fetch = vi.fn()
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  async function mountAndLoad() {
    global.fetch.mockResolvedValueOnce({
      ok: true,
      json: async () => ({ data: mockRules }),
    })

    const wrapper = mount(ThresholdEditor, {
      props: { module: 'achats', title: 'Seuils Achats' },
      global: { plugins: [PrimeVue] },
    })

    await wrapper.find('button').trigger('click')
    await flushPromises()

    return wrapper
  }

  const flushPromises = () => new Promise((resolve) => setTimeout(resolve, 0))

  it('editing rule 1 also PUTs rule 2 with the same value', async () => {
    const wrapper = await mountAndLoad()
    global.fetch.mockResolvedValue({ ok: true, json: async () => ({}) })

    wrapper.vm.rules[0].condition_value = 6000
    await wrapper.vm.save(wrapper.vm.rules[0])

    expect(global.fetch).toHaveBeenCalledWith(
      '/api/v1/validation/approval-workflows/42/rules/1',
      expect.objectContaining({ body: JSON.stringify({ condition_value: '6000' }) })
    )
    expect(global.fetch).toHaveBeenCalledWith(
      '/api/v1/validation/approval-workflows/42/rules/2',
      expect.objectContaining({ body: JSON.stringify({ condition_value: '6000' }) })
    )
    // rule 2's own reactive value follows too, not just the network call.
    expect(wrapper.vm.rules[1].condition_value).toBe(6000)
  })

  it('editing the last rule does not attempt to PUT a nonexistent next rule', async () => {
    const wrapper = await mountAndLoad()
    global.fetch.mockClear()
    global.fetch.mockResolvedValue({ ok: true, json: async () => ({}) })

    wrapper.vm.rules[2].condition_value = 60000
    await wrapper.vm.save(wrapper.vm.rules[2])

    expect(global.fetch).toHaveBeenCalledTimes(1)
    expect(global.fetch).toHaveBeenCalledWith(
      '/api/v1/validation/approval-workflows/42/rules/3',
      expect.objectContaining({ body: JSON.stringify({ condition_value: '60000' }) })
    )
  })
})
