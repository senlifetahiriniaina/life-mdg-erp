import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import CustomFieldEditor from '../CustomFieldEditor.vue'

describe('CustomFieldEditor.vue', () => {
  let wrapper

  beforeEach(() => {
    wrapper = mount(CustomFieldEditor, {
      global: {
        mocks: {
          $api: {
            post: vi.fn(),
            put: vi.fn(),
            delete: vi.fn(),
          },
        },
      },
    })
  })

  it('renders create form by default', () => {
    expect(wrapper.text()).toContain('Create Field')
  })

  it('renders edit form when field prop provided', async () => {
    const field = {
      id: 1,
      name: 'Customer Type',
      type: 'select',
      label: 'Select customer type',
      required: true,
      active: true,
      options: ['Individual', 'Business'],
    }

    await wrapper.setProps({ field })

    expect(wrapper.text()).toContain('Edit Field')
    expect(wrapper.find('input[id="field-name"]').element.value).toBe('Customer Type')
  })

  it('populates form from field prop', async () => {
    const field = {
      id: 1,
      name: 'Email',
      type: 'text',
      label: 'User Email',
      required: true,
      active: true,
    }

    await wrapper.setProps({ field })

    expect(wrapper.vm.form.name).toBe('Email')
    expect(wrapper.vm.form.type).toBe('text')
    expect(wrapper.vm.form.label).toBe('User Email')
    expect(wrapper.vm.form.required).toBe(true)
  })

  it('validates required field name', async () => {
    await wrapper.find('form').trigger('submit')
    expect(wrapper.vm.errors.name).toBeDefined()
  })

  it('validates field name length', async () => {
    await wrapper.find('input[id="field-name"]').setValue('A')
    await wrapper.find('form').trigger('submit')
    expect(wrapper.vm.errors.name).toContain('at least 2 characters')
  })

  it('validates field name max length', async () => {
    const longName = 'A'.repeat(101)
    await wrapper.find('input[id="field-name"]').setValue(longName)
    await wrapper.find('form').trigger('submit')
    expect(wrapper.vm.errors.name).toContain('cannot exceed 100 characters')
  })

  it('validates field type selection', async () => {
    await wrapper.find('input[id="field-name"]').setValue('Test Field')
    await wrapper.find('form').trigger('submit')
    expect(wrapper.vm.errors.type).toBe('Field type is required')
  })

  it('shows options textarea for dropdown fields', async () => {
    await wrapper.find('input[id="field-name"]').setValue('Status')
    await wrapper.find('select[id="field-type"]').setValue('select')
    await wrapper.vm.$nextTick()

    expect(wrapper.find('textarea').exists()).toBe(true)
  })

  it('hides options textarea for text fields', async () => {
    await wrapper.find('input[id="field-name"]').setValue('Description')
    await wrapper.find('select[id="field-type"]').setValue('text')
    await wrapper.vm.$nextTick()

    const textareas = wrapper.findAll('textarea')
    expect(textareas.length).toBe(0)
  })

  it('requires options for dropdown fields', async () => {
    await wrapper.find('input[id="field-name"]').setValue('Status')
    await wrapper.find('select[id="field-type"]').setValue('select')
    await wrapper.vm.$nextTick()
    await wrapper.find('form').trigger('submit')

    expect(wrapper.vm.errors.options).toBeDefined()
  })

  it('creates new field with valid data', async () => {
    const mockResponse = { id: 1, name: 'Test', type: 'text' }
    wrapper.vm.$api.post.mockResolvedValue(mockResponse)

    await wrapper.find('input[id="field-name"]').setValue('Test Field')
    await wrapper.find('select[id="field-type"]').setValue('text')
    await wrapper.find('form').trigger('submit')

    expect(wrapper.vm.$api.post).toHaveBeenCalledWith(
      '/api/v1/core/custom-fields',
      expect.objectContaining({
        name: 'Test Field',
        type: 'text',
      })
    )
  })

  it('creates dropdown field with options', async () => {
    const mockResponse = { id: 1 }
    wrapper.vm.$api.post.mockResolvedValue(mockResponse)

    await wrapper.find('input[id="field-name"]').setValue('Status')
    await wrapper.find('select[id="field-type"]').setValue('select')
    await wrapper.vm.$nextTick()
    await wrapper.find('textarea').setValue('Active\nInactive\nPending')
    await wrapper.find('form').trigger('submit')

    expect(wrapper.vm.$api.post).toHaveBeenCalledWith(
      '/api/v1/core/custom-fields',
      expect.objectContaining({
        options: ['Active', 'Inactive', 'Pending'],
      })
    )
  })

  it('updates existing field', async () => {
    const field = { id: 1, name: 'Old Name', type: 'text' }
    await wrapper.setProps({ field })

    const mockResponse = { id: 1, name: 'New Name' }
    wrapper.vm.$api.put.mockResolvedValue(mockResponse)

    await wrapper.find('input[id="field-name"]').setValue('New Name')
    await wrapper.find('form').trigger('submit')

    expect(wrapper.vm.$api.put).toHaveBeenCalledWith(
      '/api/v1/core/custom-fields/1',
      expect.objectContaining({
        name: 'New Name',
      })
    )
  })

  it('emits saved event on successful creation', async () => {
    const mockResponse = { id: 1 }
    wrapper.vm.$api.post.mockResolvedValue(mockResponse)

    await wrapper.find('input[id="field-name"]').setValue('Test')
    await wrapper.find('select[id="field-type"]').setValue('text')
    await wrapper.find('form').trigger('submit')
    await wrapper.vm.$nextTick()

    expect(wrapper.emitted('saved')).toBeTruthy()
    expect(wrapper.emitted('saved')[0][0]).toEqual(mockResponse)
  })

  it('disables submit button while submitting', async () => {
    wrapper.vm.$api.post.mockImplementation(
      () =>
        new Promise((resolve) => {
          setTimeout(resolve, 100)
        })
    )

    await wrapper.find('input[id="field-name"]').setValue('Test')
    await wrapper.find('select[id="field-type"]').setValue('text')
    await wrapper.find('form').trigger('submit')

    expect(wrapper.find('button[type="submit"]').attributes('disabled')).toBeDefined()
  })

  it('displays error message on API failure', async () => {
    const errorMessage = 'Field name already exists'
    wrapper.vm.$api.post.mockRejectedValue(new Error(errorMessage))

    await wrapper.find('input[id="field-name"]').setValue('Test')
    await wrapper.find('select[id="field-type"]').setValue('text')
    await wrapper.find('form').trigger('submit')
    await wrapper.vm.$nextTick()

    expect(wrapper.vm.submitError).toBe(errorMessage)
    expect(wrapper.find('.alert-danger').exists()).toBe(true)
  })

  it('shows delete button when editing', async () => {
    const field = { id: 1, name: 'Test', type: 'text' }
    await wrapper.setProps({ field })

    expect(wrapper.find('.btn-danger').exists()).toBe(true)
    expect(wrapper.find('.btn-danger').text()).toContain('Delete')
  })

  it('opens delete confirmation modal', async () => {
    const field = { id: 1, name: 'Test', type: 'text' }
    await wrapper.setProps({ field })

    await wrapper.find('.btn-danger').trigger('click')

    expect(wrapper.vm.showDeleteConfirm).toBe(true)
    expect(wrapper.find('.modal').exists()).toBe(true)
  })

  it('deletes field with confirmation', async () => {
    const field = { id: 1, name: 'Test', type: 'text' }
    await wrapper.setProps({ field })

    wrapper.vm.$api.delete.mockResolvedValue({ success: true })

    await wrapper.find('.btn-danger').trigger('click')
    await wrapper.find('.modal .btn-danger').trigger('click')
    await wrapper.vm.$nextTick()

    expect(wrapper.vm.$api.delete).toHaveBeenCalledWith('/api/v1/core/custom-fields/1')
    expect(wrapper.emitted('deleted')).toBeTruthy()
  })

  it('trims whitespace from field name', async () => {
    const mockResponse = { id: 1 }
    wrapper.vm.$api.post.mockResolvedValue(mockResponse)

    await wrapper.find('input[id="field-name"]').setValue('  Test Field  ')
    await wrapper.find('select[id="field-type"]').setValue('text')
    await wrapper.find('form').trigger('submit')

    expect(wrapper.vm.$api.post).toHaveBeenCalledWith(
      '/api/v1/core/custom-fields',
      expect.objectContaining({
        name: 'Test Field',
      })
    )
  })

  it('handles required checkbox', async () => {
    await wrapper.find('input[id="field-name"]').setValue('Email')
    await wrapper.find('select[id="field-type"]').setValue('text')

    const requiredCheckbox = wrapper.findAll('input[type="checkbox"]')[0]
    await requiredCheckbox.setValue(true)

    expect(wrapper.vm.form.required).toBe(true)
  })

  it('handles active checkbox', async () => {
    await wrapper.find('input[id="field-name"]').setValue('Email')
    await wrapper.find('select[id="field-type"]').setValue('text')

    const activeCheckbox = wrapper.findAll('input[type="checkbox"]')[1]
    await activeCheckbox.setValue(false)

    expect(wrapper.vm.form.active).toBe(false)
  })
})
