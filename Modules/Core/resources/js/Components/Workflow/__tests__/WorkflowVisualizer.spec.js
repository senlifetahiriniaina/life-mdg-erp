import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import WorkflowVisualizer from '../WorkflowVisualizer.vue'

describe('WorkflowVisualizer.vue', () => {
  const mockWorkflow = {
    id: 1,
    name: 'Invoice Approval',
    description: 'Multi-level invoice approval process',
    allow_parallel: false,
    steps: [
      {
        order: 1,
        label: 'Manager Review',
        approver_type: 'role',
        approver_value: 'manager',
        timeout_hours: 48,
      },
      {
        order: 2,
        label: 'Finance Review',
        approver_type: 'role',
        approver_value: 'finance-manager',
        timeout_hours: 24,
      },
      {
        order: 3,
        label: 'Director Approval',
        approver_type: 'user',
        approver_value: '5',
        timeout_hours: 12,
      },
    ],
  }

  const mockInstance = {
    id: 1,
    current_step: 2,
    decisions: [
      {
        id: 1,
        approver_name: 'John Doe',
        decision: 'approved',
      },
    ],
  }

  let wrapper

  beforeEach(() => {
    wrapper = mount(WorkflowVisualizer, {
      props: {
        workflow: mockWorkflow,
      },
      global: {
        mocks: {
          $api: {
            delete: vi.fn(),
          },
        },
      },
    })
  })

  it('renders workflow header with name', () => {
    expect(wrapper.text()).toContain('Invoice Approval')
  })

  it('displays sequential badge for non-parallel workflow', () => {
    expect(wrapper.find('.badge-secondary').exists()).toBe(true)
    expect(wrapper.find('.badge-secondary').text()).toBe('Sequential')
  })

  it('displays parallel badge for parallel workflow', async () => {
    const parallelWorkflow = { ...mockWorkflow, allow_parallel: true }
    await wrapper.setProps({ workflow: parallelWorkflow })

    expect(wrapper.find('.badge-info').exists()).toBe(true)
    expect(wrapper.find('.badge-info').text()).toBe('Parallel')
  })

  it('displays workflow description', () => {
    expect(wrapper.text()).toContain('Multi-level invoice approval process')
  })

  it('renders all steps', () => {
    const steps = wrapper.findAll('.step')
    expect(steps).toHaveLength(3)
  })

  it('displays step labels', () => {
    expect(wrapper.text()).toContain('Manager Review')
    expect(wrapper.text()).toContain('Finance Review')
    expect(wrapper.text()).toContain('Director Approval')
  })

  it('displays step approvers with correct formatting', () => {
    expect(wrapper.text()).toContain('Role: manager')
    expect(wrapper.text()).toContain('Role: finance-manager')
    expect(wrapper.text()).toContain('User: 5')
  })

  it('displays timeout hours for each step', () => {
    expect(wrapper.text()).toContain('48 hours')
    expect(wrapper.text()).toContain('24 hours')
    expect(wrapper.text()).toContain('12 hours')
  })

  it('marks completed steps', async () => {
    await wrapper.setProps({ currentInstance: mockInstance })

    const steps = wrapper.findAll('.step')
    expect(steps[0].classes()).toContain('step-completed')
  })

  it('marks current step', async () => {
    await wrapper.setProps({ currentInstance: mockInstance })

    const steps = wrapper.findAll('.step')
    expect(steps[1].classes()).toContain('step-current')
  })

  it('marks pending steps', async () => {
    await wrapper.setProps({ currentInstance: mockInstance })

    const steps = wrapper.findAll('.step')
    expect(steps[2].classes()).toContain('step-pending')
  })

  it('displays progress bar', async () => {
    await wrapper.setProps({ currentInstance: mockInstance })

    expect(wrapper.find('.progress-bar').exists()).toBe(true)
  })

  it('calculates progress percentage correctly', async () => {
    await wrapper.setProps({ currentInstance: mockInstance })

    expect(wrapper.vm.progressPercentage).toBe((2 / 3) * 100)
  })

  it('displays current step information', async () => {
    await wrapper.setProps({ currentInstance: mockInstance })

    expect(wrapper.text()).toContain('Step 2 of 3')
  })

  it('displays decision history', async () => {
    await wrapper.setProps({ currentInstance: mockInstance })

    expect(wrapper.text()).toContain('Approvals Made')
    expect(wrapper.text()).toContain('John Doe')
    expect(wrapper.text()).toContain('approved')
  })

  it('hides instance details when no instance', () => {
    expect(wrapper.find('.instance-details').exists()).toBe(false)
  })

  it('shows edit button when showEditButton is true', async () => {
    await wrapper.setProps({ showEditButton: true })

    expect(wrapper.find('.btn-secondary').exists()).toBe(true)
    expect(wrapper.find('.btn-secondary').text()).toBe('Edit Workflow')
  })

  it('shows delete button when showDeleteButton is true', async () => {
    await wrapper.setProps({ showDeleteButton: true })

    const deleteBtn = wrapper.findAll('.btn-danger')[0]
    expect(deleteBtn.text()).toBe('Delete')
  })

  it('emits edit-workflow event', async () => {
    await wrapper.setProps({ showEditButton: true })

    await wrapper.find('.btn-secondary').trigger('click')

    expect(wrapper.emitted('edit-workflow')).toBeTruthy()
  })

  it('opens delete confirmation modal', async () => {
    await wrapper.setProps({ showDeleteButton: true })

    const deleteBtn = wrapper.findAll('.btn-danger')[0]
    await deleteBtn.trigger('click')

    expect(wrapper.vm.showDeleteConfirm).toBe(true)
    expect(wrapper.find('.modal').exists()).toBe(true)
  })

  it('closes delete confirmation modal', async () => {
    await wrapper.setProps({ showDeleteButton: true })

    wrapper.vm.showDeleteConfirm = true
    await wrapper.vm.$nextTick()

    await wrapper.find('.modal .btn-secondary').trigger('click')

    expect(wrapper.vm.showDeleteConfirm).toBe(false)
  })

  it('deletes workflow on confirmation', async () => {
    await wrapper.setProps({ showDeleteButton: true })

    wrapper.vm.$api.delete.mockResolvedValue({ success: true })

    wrapper.vm.showDeleteConfirm = true
    await wrapper.vm.$nextTick()

    await wrapper.find('.modal .btn-danger').trigger('click')
    await wrapper.vm.$nextTick()

    expect(wrapper.vm.$api.delete).toHaveBeenCalledWith('/api/v1/core/approvals/workflows/1')
  })

  it('emits workflow-deleted event', async () => {
    await wrapper.setProps({ showDeleteButton: true })

    wrapper.vm.$api.delete.mockResolvedValue({ success: true })

    wrapper.vm.showDeleteConfirm = true
    await wrapper.vm.$nextTick()

    await wrapper.find('.modal .btn-danger').trigger('click')
    await wrapper.vm.$nextTick()

    expect(wrapper.emitted('workflow-deleted')).toBeTruthy()
    expect(wrapper.emitted('workflow-deleted')[0][0]).toBe(1)
  })

  it('emits delete-error event on API failure', async () => {
    await wrapper.setProps({ showDeleteButton: true })

    const errorMessage = 'Cannot delete workflow with active instances'
    wrapper.vm.$api.delete.mockRejectedValue(new Error(errorMessage))

    wrapper.vm.showDeleteConfirm = true
    await wrapper.vm.$nextTick()

    await wrapper.find('.modal .btn-danger').trigger('click')
    await wrapper.vm.$nextTick()

    expect(wrapper.emitted('delete-error')).toBeTruthy()
    expect(wrapper.emitted('delete-error')[0][0]).toBe(errorMessage)
  })

  it('formats different approver types', () => {
    expect(wrapper.vm.formatApprover({ approver_type: 'user', approver_value: '5' })).toBe(
      'User: 5'
    )
    expect(wrapper.vm.formatApprover({ approver_type: 'role', approver_value: 'manager' })).toBe(
      'Role: manager'
    )
    expect(
      wrapper.vm.formatApprover({ approver_type: 'department', approver_value: 'Finance' })
    ).toBe('Department: Finance')
  })

  it('calculates step completion status correctly', async () => {
    await wrapper.setProps({ currentInstance: mockInstance })

    expect(wrapper.vm.isStepCompleted(0)).toBe(true)
    expect(wrapper.vm.isStepCompleted(1)).toBe(false)
    expect(wrapper.vm.isStepPending(2)).toBe(true)
  })

  it('displays multiple decisions', async () => {
    const instanceWithMultipleDecisions = {
      ...mockInstance,
      decisions: [
        { id: 1, approver_name: 'John Doe', decision: 'approved' },
        { id: 2, approver_name: 'Jane Smith', decision: 'approved' },
      ],
    }

    await wrapper.setProps({ currentInstance: instanceWithMultipleDecisions })

    expect(wrapper.text()).toContain('John Doe')
    expect(wrapper.text()).toContain('Jane Smith')
  })
})
