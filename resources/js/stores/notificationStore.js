import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useNotificationStore = defineStore('notification', () => {
  const notifications = ref([])
  let notificationId = 0

  const count = computed(() => notifications.value.length)

  const unreadCount = computed(() => notifications.value.filter(n => !n.read).length)

  const successNotifications = computed(() => notifications.value.filter(n => n.type === 'success'))

  const errorNotifications = computed(() => notifications.value.filter(n => n.type === 'error'))

  const warningNotifications = computed(() => notifications.value.filter(n => n.type === 'warning'))

  const infoNotifications = computed(() => notifications.value.filter(n => n.type === 'info'))

  const addNotification = (message, type = 'info', duration = 5000) => {
    const id = notificationId++
    const notification = {
      id,
      message,
      type,
      read: false,
      timestamp: new Date(),
      duration
    }

    notifications.value.push(notification)

    if (duration > 0) {
      setTimeout(() => {
        removeNotification(id)
      }, duration)
    }

    return id
  }

  const addSuccess = (message, duration = 5000) => {
    return addNotification(message, 'success', duration)
  }

  const addError = (message, duration = 0) => {
    return addNotification(message, 'error', duration)
  }

  const addWarning = (message, duration = 5000) => {
    return addNotification(message, 'warning', duration)
  }

  const addInfo = (message, duration = 5000) => {
    return addNotification(message, 'info', duration)
  }

  const removeNotification = (id) => {
    const index = notifications.value.findIndex(n => n.id === id)
    if (index > -1) {
      notifications.value.splice(index, 1)
    }
  }

  const markAsRead = (id) => {
    const notification = notifications.value.find(n => n.id === id)
    if (notification) {
      notification.read = true
    }
  }

  const markAllAsRead = () => {
    notifications.value.forEach(n => {
      n.read = true
    })
  }

  const clearAll = () => {
    notifications.value = []
  }

  const clearByType = (type) => {
    notifications.value = notifications.value.filter(n => n.type !== type)
  }

  return {
    notifications,
    count,
    unreadCount,
    successNotifications,
    errorNotifications,
    warningNotifications,
    infoNotifications,
    addNotification,
    addSuccess,
    addError,
    addWarning,
    addInfo,
    removeNotification,
    markAsRead,
    markAllAsRead,
    clearAll,
    clearByType
  }
})
