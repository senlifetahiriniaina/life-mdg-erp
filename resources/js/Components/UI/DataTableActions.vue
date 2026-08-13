<template>
    <div class="flex items-center gap-1">
        <Button
            v-if="showView"
            icon="pi pi-eye"
            size="small"
            severity="secondary"
            text
            rounded
            v-tooltip.top="$t('common.view')"
            @click="$emit('view')"
        />
        <Button
            v-if="showEdit"
            icon="pi pi-pencil"
            size="small"
            severity="info"
            text
            rounded
            v-tooltip.top="$t('common.edit')"
            @click="$emit('edit')"
        />
        <Button
            v-if="showDelete"
            icon="pi pi-trash"
            size="small"
            severity="danger"
            text
            rounded
            v-tooltip.top="$t('common.delete')"
            @click="confirmDelete"
        />
    </div>
</template>

<script setup>
import { useConfirm } from 'primevue/useconfirm'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'

const props = defineProps({
    showView:   { type: Boolean, default: true },
    showEdit:   { type: Boolean, default: true },
    showDelete: { type: Boolean, default: true },
    deleteMessage: { type: String, default: 'Are you sure you want to delete this record?' },
})

const emit = defineEmits(['view', 'edit', 'delete'])
const confirm = useConfirm()
const { t } = useI18n()

function confirmDelete() {
    confirm.require({
        message: props.deleteMessage,
        header: t('common.confirm'),
        icon: 'pi pi-exclamation-triangle',
        rejectLabel: t('common.cancel'),
        acceptLabel: t('common.delete'),
        acceptClass: 'p-button-danger',
        accept: () => emit('delete'),
    })
}
</script>
