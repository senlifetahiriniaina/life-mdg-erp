import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import ApprovalCard from '../ApprovalCard.vue'

describe('ApprovalCard.vue', () => {
  const mockApproval = {
    id: 1,
    workflow_name: 'Invoice Approval',
    status: 'pending',
    current_step: 1,
    total_steps: 2,
    subject_type: 'Invoice',
    subject_id: 123,
    initiated_by_name: 'John Doe',
    decisions: [],
  }

  let wrapper

  beforeEach(() => {
    wrapper = mount(ApprovalCard, {
      props: {
        approval: mockApproval,
      },
      global: {
        mocks: {
          $api: {
            post: vi.fn(),
          },
        },
      },
    })
  })

  it('renders approval card with correct information', () => {
    expect(wrapper.find('.approval-card').exists()).toBe(true)
    expect(wrapper.text()).toContain('Invoice Approval')
    expect(wrapper.text()).toContain('Invoice #123')
    expect(wrapper.text()).toContain('John Doe')
  })

  it('displays correct status badge', () => {
    expect(wrapper.find('.badge').exists()).toBe(true)
    expect(wrapper.find('.badge').text()).toBe('pending')
    expect(wrapper.find('.badge').classes()).toContain('badge-warning')
  })

  it('shows current step information', () => {
    expect(wrapper.text()).toContain('1 of 2')
  })

  it('shows decision buttons for pending approvals', () => {
    expect(wrapper.find('.btn-success').exists()).toBe(true)
    expect(wrapper.find('.btn-danger').exists()).toBe(true)
    expect(wrapper.find('.btn-success').text()).toBe('Approve')
    expect(wrapper.find('.btn-danger').text()).toBe('Reject')
  })

  it('submits approval decision with comment', async () => {
    wrapper.vm.$api.post.mockResolvedValue({ success: true })

    await wrapper.find('textarea').setValue('Looks good to me')
    await wrapper.find('.btn-success').trigger('click')

    await wrapper.vm.$nextTick()

    expect(wrapper.vm.$api.post).toHaveBeenCalledWith('/api/v1/core/approvals/instances/1/decide', {
      decision: 'approved',
      comment: 'Looks good to me',
    })
  })

  it('submits rejection decision', async () => {
    wrapper.vm.$api.post.mockResolvedValue({ success: true })

    await wrapper.find('textarea').setValue('Not ready yet')
    await wrapper.find('.btn-danger').trigger('click')

    expect(wrapper.vm.$api.post).toHaveBeenCalledWith('/api/v1/core/approvals/instances/1/decide', {
      decision: 'rejected',
      comment: 'Not ready yet',
    })
  })

  it('submits decision without comment', async () => {
    wrapper.vm.$api.post.mockResolvedValue({ success: true })

    await wrapper.find('.btn-success').trigger('click')

    expect(wrapper.vm.$api.post).toHaveBeenCalledWith(
      '/api/v1/core/approvals/instances/1/decide',
      expect.objectContaining({
        decision: 'approved',
        comment: null,
      })
    )
  })

  it('emits decision-submitted event', async () => {
    wrapper.vm.$api.post.mockResolvedValue({ success: true })

    await wrapper.find('.btn-success').trigger('click')
    await wrapper.vm.$nextTick()

    expect(wrapper.emitted('decision-submitted')).toBeTruthy()
  })

  it('disables buttons while submitting', async () => {
    wrapper.vm.$api.post.mockImplementation(
      () =>
        new Promise((resolve) => {
          setTimeout(resolve, 100)
        })
    )

    await wrapper.find('.btn-success').trigger('click')
    expect(wrapper.find('.btn-success').attributes('disabled')).toBeDefined()

    await new Promise((resolve) => setTimeout(resolve, 150))
  })

  it('displays error message on API failure', async () => {
    const errorMessage = 'Failed to process approval'
    wrapper.vm.$api.post.mockRejectedValue(new Error(errorMessage))

    await wrapper.find('.btn-success').trigger('click')
    await wrapper.vm.$nextTick()

    expect(wrapper.vm.submitError).toBe(errorMessage)
    expect(wrapper.find('.alert-danger').exists()).toBe(true)
  })

  it('shows escalation button when enabled', async () => {
    await wrapper.setProps({ canEscalate: true })
    expect(wrapper.find('.btn-warning').exists()).toBe(true)
    expect(wrapper.find('.btn-warning').text()).toContain('Escalate')
  })

  it('shows decision history for completed approvals', async () => {
    const completedApproval = {
      ...mockApproval,
      status: 'approved',
      decisions: [
        {
          id: 1,
          approver_name: 'Jane Smith',
          decision: 'approved',
          comment: 'Looks good',
          decided_at: '2026-05-17T10:00:00Z',
        },
      ],
    }

    await wrapper.setProps({ approval: completedApproval })

    expect(wrapper.text()).toContain('Decision History')
    expect(wrapper.text()).toContain('Jane Smith')
    expect(wrapper.text()).toContain('approved')
    expect(wrapper.text()).toContain('Looks good')
  })

  it('marks card as urgent when deadline is within 24 hours', async () => {
    const now = new Date()
    const deadline = new Date(now.getTime() + 12 * 60 * 60 * 1000) // 12 hours

    await wrapper.setProps({
      approval: {
        ...mockApproval,
        deadline: deadline.toISOString(),
      },
    })

    expect(wrapper.find('.approval-card').classes()).toContain('is-urgent')
  })

  it('marks deadline as overdue', async () => {
    const pastDate = new Date(new Date().getTime() - 1 * 60 * 60 * 1000) // 1 hour ago

    await wrapper.setProps({
      approval: {
        ...mockApproval,
        deadline: pastDate.toISOString(),
      },
    })

    expect(wrapper.find('.deadline').classes()).toContain('is-overdue')
  })

  it('clears comment after successful submission', async () => {
    wrapper.vm.$api.post.mockResolvedValue({ success: true })

    await wrapper.find('textarea').setValue('Test comment')
    await wrapper.find('.btn-success').trigger('click')
    await wrapper.vm.$nextTick()

    expect(wrapper.vm.comment).toBe('')
  })
})
