import { ref, onMounted, onUnmounted } from 'vue'

export function useBroadcasting() {
  const isConnected = ref(false)
  const activeChannels = ref(new Set())

  onMounted(() => {
    if (window.Echo) {
      window.Echo.connector.socket?.on('connect', () => {
        isConnected.value = true
        console.log('[Broadcasting] Connected to WebSocket')
      })

      window.Echo.connector.socket?.on('disconnect', () => {
        isConnected.value = false
        console.log('[Broadcasting] Disconnected from WebSocket')
      })
    }
  })

  const subscribe = (channel, callback, eventName = null) => {
    if (!window.Echo) {
      console.warn('[Broadcasting] Echo not available, broadcasting disabled')
      return null
    }

    activeChannels.value.add(channel)
    const subscription = window.Echo.channel(channel)

    if (eventName) {
      subscription.listen(eventName, callback)
    } else {
      subscription.listen('.', callback)
    }

    return subscription
  }

  const unsubscribe = (channel) => {
    if (!window.Echo) return

    window.Echo.leaveChannel(channel)
    activeChannels.value.delete(channel)
  }

  const unsubscribeAll = () => {
    activeChannels.value.forEach(channel => {
      window.Echo.leaveChannel(channel)
    })
    activeChannels.value.clear()
  }

  onUnmounted(() => {
    unsubscribeAll()
  })

  return {
    isConnected,
    activeChannels,
    subscribe,
    unsubscribe,
    unsubscribeAll,
  }
}

export function useCrmBroadcasting(tenantId) {
  const { subscribe, ...rest } = useBroadcasting()

  const onContactUpdated = (callback) => {
    return subscribe(`crm.contacts.${tenantId}`, callback, 'contact.updated')
  }

  const onContactCreated = (callback) => {
    return subscribe(`crm.contacts.${tenantId}`, callback, 'contact.created')
  }

  const onAccountUpdated = (callback) => {
    return subscribe(`crm.accounts.${tenantId}`, callback, 'account.updated')
  }

  const onAccountCreated = (callback) => {
    return subscribe(`crm.accounts.${tenantId}`, callback, 'account.created')
  }

  const onOpportunityUpdated = (callback) => {
    return subscribe(`crm.opportunities.${tenantId}`, callback, 'opportunity.updated')
  }

  const onOpportunityCreated = (callback) => {
    return subscribe(`crm.opportunities.${tenantId}`, callback, 'opportunity.created')
  }

  return {
    ...rest,
    onContactUpdated,
    onContactCreated,
    onAccountUpdated,
    onAccountCreated,
    onOpportunityUpdated,
    onOpportunityCreated,
  }
}

export function useHrBroadcasting(tenantId) {
  const { subscribe, ...rest } = useBroadcasting()

  const onEmployeeUpdated = (callback) => {
    return subscribe(`hr.employees.${tenantId}`, callback, 'employee.updated')
  }

  const onEmployeeCreated = (callback) => {
    return subscribe(`hr.employees.${tenantId}`, callback, 'employee.created')
  }

  const onLeaveRequested = (callback) => {
    return subscribe(`hr.leaves.${tenantId}`, callback, 'leave.created')
  }

  const onLeaveApproved = (callback) => {
    return subscribe(`hr.leaves.${tenantId}`, callback, 'leave.approved')
  }

  const onLeaveRejected = (callback) => {
    return subscribe(`hr.leaves.${tenantId}`, callback, 'leave.rejected')
  }

  return {
    ...rest,
    onEmployeeUpdated,
    onEmployeeCreated,
    onLeaveRequested,
    onLeaveApproved,
    onLeaveRejected,
  }
}

export function useAccountingBroadcasting(tenantId) {
  const { subscribe, ...rest } = useBroadcasting()

  const onInvoiceCreated = (callback) => {
    return subscribe(`accounting.invoices.${tenantId}`, callback, 'invoice.created')
  }

  const onInvoiceUpdated = (callback) => {
    return subscribe(`accounting.invoices.${tenantId}`, callback, 'invoice.updated')
  }

  const onInvoiceSent = (callback) => {
    return subscribe(`accounting.invoices.${tenantId}`, callback, 'invoice.sent')
  }

  const onPaymentRecorded = (callback) => {
    return subscribe(`accounting.payments.${tenantId}`, callback, 'payment.recorded')
  }

  return {
    ...rest,
    onInvoiceCreated,
    onInvoiceUpdated,
    onInvoiceSent,
    onPaymentRecorded,
  }
}

export function useInventoryBroadcasting(tenantId) {
  const { subscribe, ...rest } = useBroadcasting()

  const onProductUpdated = (callback) => {
    return subscribe(`inventory.products.${tenantId}`, callback, 'product.updated')
  }

  const onProductCreated = (callback) => {
    return subscribe(`inventory.products.${tenantId}`, callback, 'product.created')
  }

  const onStockLevelChanged = (callback) => {
    return subscribe(`inventory.stock.${tenantId}`, callback, 'stock.level_changed')
  }

  const onStockLowAlert = (callback) => {
    // Special handler for low stock alerts
    const handler = (data) => {
      if (data.is_low_stock) {
        callback(data)
      }
    }
    return subscribe(`inventory.stock.${tenantId}`, handler, 'stock.level_changed')
  }

  return {
    ...rest,
    onProductUpdated,
    onProductCreated,
    onStockLevelChanged,
    onStockLowAlert,
  }
}
