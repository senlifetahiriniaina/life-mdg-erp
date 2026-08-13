import { onMounted, onUnmounted } from 'vue'

export function useHelpdeskChannel(
    onTicketCreated: (data: any) => void,
    onTicketUpdated: (data: any) => void,
) {
    let channel: any = null

    onMounted(() => {
        if (!window.Echo) return
        channel = window.Echo.channel('helpdesk.tickets')
        channel.listen('.ticket.created', onTicketCreated)
        channel.listen('.ticket.updated', onTicketUpdated)
    })

    onUnmounted(() => {
        window.Echo?.leaveChannel('helpdesk.tickets')
    })
}

export function useWhatsAppChannel(
    conversationId: number,
    onMessage: (data: any) => void,
) {
    let channel: any = null

    onMounted(() => {
        if (!window.Echo) return
        channel = window.Echo.private(`whatsapp.conversation.${conversationId}`)
        channel.listen('.message.received', onMessage)
    })

    onUnmounted(() => {
        window.Echo?.leaveChannel(`whatsapp.conversation.${conversationId}`)
    })
}

export function useInventoryChannel(onStockUpdated: (data: any) => void) {
    let channel: any = null

    onMounted(() => {
        if (!window.Echo) return
        channel = window.Echo.channel('inventory.stock')
        channel.listen('.stock.updated', onStockUpdated)
    })

    onUnmounted(() => {
        window.Echo?.leaveChannel('inventory.stock')
    })
}

export function useManufacturingChannel(onStatusChanged: (data: any) => void) {
    let channel: any = null

    onMounted(() => {
        if (!window.Echo) return
        channel = window.Echo.private('manufacturing')
        channel.listen('.ProductionStatusChanged', onStatusChanged)
    })

    onUnmounted(() => {
        window.Echo?.leaveChannel('private-manufacturing')
    })
}

export function useProjectChannel(
    projectId: number,
    onTaskUpdated: (data: any) => void,
) {
    let channel: any = null

    onMounted(() => {
        if (!window.Echo || !projectId) return
        channel = window.Echo.private(`project.${projectId}`)
        channel.listen('.TaskUpdated', onTaskUpdated)
    })

    onUnmounted(() => {
        if (projectId) window.Echo?.leaveChannel(`private-project.${projectId}`)
    })
}
