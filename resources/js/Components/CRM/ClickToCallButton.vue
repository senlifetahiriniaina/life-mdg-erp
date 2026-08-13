<template>
  <div class="ctc-wrapper">
    <Button
      :label="callState === 'idle' ? $t('crm.voip.call') : statusLabel"
      :icon="callIcon"
      :severity="callSeverity"
      :loading="callState === 'calling'"
      size="small"
      @click="initiateCall"
    />
    <Badge v-if="callState === 'active'" :value="formattedDuration" severity="success" style="margin-left:6px" />
    <Badge v-if="callState === 'ringing'" :value="$t('crm.voip.status_ringing')" severity="warning" style="margin-left:6px" />
  </div>
</template>

<script setup>
import { ref, computed, onUnmounted } from 'vue'
import Button from 'primevue/button'
import Badge from 'primevue/badge'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  phone: { type: String, required: true },
  contactId: { type: Number, default: null },
})

const { t } = useI18n()

const callState = ref('idle')  // idle | calling | ringing | active | ended
const callSid = ref('')
const elapsedSeconds = ref(0)
let pollTimer = null
let durationTimer = null

const statusLabel = computed(() => {
  switch (callState.value) {
    case 'calling':  return t('crm.voip.status_initiated')
    case 'ringing':  return t('crm.voip.status_ringing')
    case 'active':   return t('crm.voip.status_answered')
    case 'ended':    return t('crm.voip.call_ended')
    default:         return t('crm.voip.call')
  }
})

const callIcon = computed(() => {
  switch (callState.value) {
    case 'calling': return 'pi pi-spin pi-spinner'
    case 'active':  return 'pi pi-phone'
    case 'ended':   return 'pi pi-phone-off'
    default:        return 'pi pi-phone'
  }
})

const callSeverity = computed(() => {
  switch (callState.value) {
    case 'active': return 'success'
    case 'ended':  return 'secondary'
    default:       return 'primary'
  }
})

const formattedDuration = computed(() => {
  const m = Math.floor(elapsedSeconds.value / 60)
  const s = elapsedSeconds.value % 60
  return `${m}:${String(s).padStart(2, '0')}`
})

const initiateCall = async () => {
  if (callState.value !== 'idle' && callState.value !== 'ended') return

  callState.value = 'calling'
  elapsedSeconds.value = 0

  try {
    const body = { phone: props.phone }
    if (props.contactId) body.contact_id = props.contactId

    const res = await fetch('/api/v1/crm/voip/call', {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    })

    const data = await res.json()
    callSid.value = data.call_sid ?? ''
    callState.value = 'ringing'

    startPolling()
  } catch {
    callState.value = 'idle'
  }
}

const startPolling = () => {
  pollTimer = setInterval(async () => {
    if (!callSid.value) return
    try {
      const res = await fetch(`/api/v1/crm/voip/status?call_sid=${callSid.value}`, { headers: { Accept: 'application/json' } })
      const data = await res.json()
      const status = data.status ?? ''

      if (status === 'answered' || status === 'in-progress') {
        callState.value = 'active'
        startDurationTimer()
        clearInterval(pollTimer)
      } else if (['missed', 'failed', 'completed'].includes(status)) {
        callState.value = 'ended'
        clearInterval(pollTimer)
      }
    } catch { /* ignore */ }
  }, 2000)
}

const startDurationTimer = () => {
  durationTimer = setInterval(() => { elapsedSeconds.value++ }, 1000)
}

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer)
  if (durationTimer) clearInterval(durationTimer)
})
</script>

<style scoped>
.ctc-wrapper {
  display: inline-flex;
  align-items: center;
}
</style>
