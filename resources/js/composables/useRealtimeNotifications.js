import { ref, computed } from 'vue'
import { useCrmBroadcasting, useHrBroadcasting, useAccountingBroadcasting, useInventoryBroadcasting } from './useBroadcasting'

export function useRealtimeNotifications(tenantId) {
  const notifications = ref([])
  const unreadCount = ref(0)

  const crmBroadcasting = useCrmBroadcasting(tenantId)
  const hrBroadcasting = useHrBroadcasting(tenantId)
  const accountingBroadcasting = useAccountingBroadcasting(tenantId)
  const inventoryBroadcasting = useInventoryBroadcasting(tenantId)

  const addNotification = (type, title, message, data = {}, timeout = 5000) => {
    const notification = {
      id: Date.now(),
      type,
      title,
      message,
      data,
      read: false,
      createdAt: new Date(),
    }

    notifications.value.unshift(notification)
    unreadCount.value++

    if (timeout > 0) {
      setTimeout(() => {
        removeNotification(notification.id)
      }, timeout)
    }

    return notification
  }

  const removeNotification = (id) => {
    const index = notifications.value.findIndex(n => n.id === id)
    if (index !== -1) {
      const notification = notifications.value[index]
      if (!notification.read) unreadCount.value--
      notifications.value.splice(index, 1)
    }
  }

  const markAsRead = (id) => {
    const notification = notifications.value.find(n => n.id === id)
    if (notification && !notification.read) {
      notification.read = true
      unreadCount.value--
    }
  }

  const clearAll = () => {
    notifications.value = []
    unreadCount.value = 0
  }

  // CRM event handlers
  crmBroadcasting.onContactUpdated((data) => {
    addNotification(
      'info',
      'Contact Updated',
      `${data.name} contact information has been updated`,
      data
    )
  })

  crmBroadcasting.onContactCreated((data) => {
    addNotification(
      'success',
      'New Contact',
      `${data.name} has been added to your CRM`,
      data,
      0
    )
  })

  crmBroadcasting.onAccountUpdated((data) => {
    addNotification(
      'info',
      'Account Updated',
      `${data.name} account details have changed`,
      data
    )
  })

  crmBroadcasting.onAccountCreated((data) => {
    addNotification(
      'success',
      'New Account',
      `${data.name} has been registered`,
      data,
      0
    )
  })

  crmBroadcasting.onOpportunityUpdated((data) => {
    addNotification(
      'info',
      'Opportunity Updated',
      `${data.name} - ${data.stage} (${data.probability}% probability)`,
      data
    )
  })

  crmBroadcasting.onOpportunityCreated((data) => {
    addNotification(
      'success',
      'New Opportunity',
      `${data.name} - $${data.amount?.toLocaleString() || 0}`,
      data,
      0
    )
  })

  // HR event handlers
  hrBroadcasting.onEmployeeUpdated((data) => {
    addNotification(
      'info',
      'Employee Updated',
      `${data.name} profile has been updated`,
      data
    )
  })

  hrBroadcasting.onEmployeeCreated((data) => {
    addNotification(
      'success',
      'New Employee',
      `${data.name} has joined as ${data.job_title}`,
      data,
      0
    )
  })

  hrBroadcasting.onLeaveRequested((data) => {
    addNotification(
      'warning',
      'Leave Request',
      `New ${data.type} leave request for ${data.days} day(s)`,
      data,
      0
    )
  })

  hrBroadcasting.onLeaveApproved((data) => {
    addNotification(
      'success',
      'Leave Approved',
      `${data.type} leave has been approved (${data.days} day(s))`,
      data
    )
  })

  hrBroadcasting.onLeaveRejected((data) => {
    addNotification(
      'error',
      'Leave Rejected',
      `${data.type} leave request has been rejected`,
      data
    )
  })

  // Accounting event handlers
  accountingBroadcasting.onInvoiceCreated((data) => {
    addNotification(
      'success',
      'Invoice Created',
      `${data.invoice_number} - $${data.total?.toLocaleString() || 0}`,
      data,
      0
    )
  })

  accountingBroadcasting.onInvoiceUpdated((data) => {
    addNotification(
      'info',
      'Invoice Updated',
      `${data.invoice_number} status: ${data.status}`,
      data
    )
  })

  accountingBroadcasting.onInvoiceSent((data) => {
    addNotification(
      'success',
      'Invoice Sent',
      `${data.invoice_number} has been sent to customer`,
      data
    )
  })

  accountingBroadcasting.onPaymentRecorded((data) => {
    addNotification(
      'success',
      'Payment Received',
      `$${data.amount?.toLocaleString() || 0} via ${data.method}`,
      data
    )
  })

  // Inventory event handlers
  inventoryBroadcasting.onProductUpdated((data) => {
    addNotification(
      'info',
      'Product Updated',
      `${data.name} pricing or details have changed`,
      data
    )
  })

  inventoryBroadcasting.onProductCreated((data) => {
    addNotification(
      'success',
      'New Product',
      `${data.name} (${data.sku}) has been added`,
      data,
      0
    )
  })

  inventoryBroadcasting.onStockLevelChanged((data) => {
    addNotification(
      'info',
      'Stock Updated',
      `${data.product_id} - New quantity: ${data.new_quantity} (${data.reason})`,
      data
    )
  })

  inventoryBroadcasting.onStockLowAlert((data) => {
    addNotification(
      'warning',
      'Low Stock Alert',
      `Product ${data.product_id} is below reorder level (${data.new_quantity}/${data.reorder_level})`,
      data,
      0
    )
  })

  return {
    notifications: computed(() => notifications.value),
    unreadCount: computed(() => unreadCount.value),
    addNotification,
    removeNotification,
    markAsRead,
    clearAll,
  }
}
