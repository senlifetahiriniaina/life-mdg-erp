import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const usePreferencesStore = defineStore('preferences', () => {
  const theme = ref(localStorage.getItem('theme') || 'light')
  const language = ref(localStorage.getItem('language') || 'en')
  const itemsPerPage = ref(parseInt(localStorage.getItem('itemsPerPage')) || 15)
  const sidebarCollapsed = ref(localStorage.getItem('sidebarCollapsed') === 'true')
  const notifications = ref(JSON.parse(localStorage.getItem('notificationPrefs') || '{"email": true, "browser": true, "sound": true}'))
  const dateFormat = ref(localStorage.getItem('dateFormat') || 'MMM DD, YYYY')
  const currency = ref(localStorage.getItem('currency') || 'USD')
  const timezone = ref(localStorage.getItem('timezone') || Intl.DateTimeFormat().resolvedOptions().timeZone)

  const isDarkTheme = computed(() => theme.value === 'dark')

  const setTheme = (newTheme) => {
    theme.value = newTheme
    localStorage.setItem('theme', newTheme)
    document.documentElement.setAttribute('data-theme', newTheme)
  }

  const toggleTheme = () => {
    setTheme(isDarkTheme.value ? 'light' : 'dark')
  }

  const setLanguage = (newLanguage) => {
    language.value = newLanguage
    localStorage.setItem('language', newLanguage)
  }

  const setItemsPerPage = (perPage) => {
    itemsPerPage.value = perPage
    localStorage.setItem('itemsPerPage', perPage)
  }

  const setSidebarCollapsed = (collapsed) => {
    sidebarCollapsed.value = collapsed
    localStorage.setItem('sidebarCollapsed', collapsed)
  }

  const toggleSidebar = () => {
    setSidebarCollapsed(!sidebarCollapsed.value)
  }

  const setNotificationPreference = (type, enabled) => {
    notifications.value[type] = enabled
    localStorage.setItem('notificationPrefs', JSON.stringify(notifications.value))
  }

  const setDateFormat = (format) => {
    dateFormat.value = format
    localStorage.setItem('dateFormat', format)
  }

  const setCurrency = (newCurrency) => {
    currency.value = newCurrency
    localStorage.setItem('currency', newCurrency)
  }

  const setTimezone = (newTimezone) => {
    timezone.value = newTimezone
    localStorage.setItem('timezone', newTimezone)
  }

  const exportPreferences = () => {
    return {
      theme: theme.value,
      language: language.value,
      itemsPerPage: itemsPerPage.value,
      sidebarCollapsed: sidebarCollapsed.value,
      notifications: notifications.value,
      dateFormat: dateFormat.value,
      currency: currency.value,
      timezone: timezone.value
    }
  }

  const importPreferences = (prefs) => {
    if (prefs.theme) setTheme(prefs.theme)
    if (prefs.language) setLanguage(prefs.language)
    if (prefs.itemsPerPage) setItemsPerPage(prefs.itemsPerPage)
    if (typeof prefs.sidebarCollapsed === 'boolean') setSidebarCollapsed(prefs.sidebarCollapsed)
    if (prefs.notifications) {
      Object.keys(prefs.notifications).forEach(type => {
        setNotificationPreference(type, prefs.notifications[type])
      })
    }
    if (prefs.dateFormat) setDateFormat(prefs.dateFormat)
    if (prefs.currency) setCurrency(prefs.currency)
    if (prefs.timezone) setTimezone(prefs.timezone)
  }

  const resetToDefaults = () => {
    localStorage.clear()
    theme.value = 'light'
    language.value = 'en'
    itemsPerPage.value = 15
    sidebarCollapsed.value = false
    notifications.value = { email: true, browser: true, sound: true }
    dateFormat.value = 'MMM DD, YYYY'
    currency.value = 'USD'
    timezone.value = Intl.DateTimeFormat().resolvedOptions().timeZone
  }

  return {
    theme,
    language,
    itemsPerPage,
    sidebarCollapsed,
    notifications,
    dateFormat,
    currency,
    timezone,
    isDarkTheme,
    setTheme,
    toggleTheme,
    setLanguage,
    setItemsPerPage,
    setSidebarCollapsed,
    toggleSidebar,
    setNotificationPreference,
    setDateFormat,
    setCurrency,
    setTimezone,
    exportPreferences,
    importPreferences,
    resetToDefaults
  }
})
