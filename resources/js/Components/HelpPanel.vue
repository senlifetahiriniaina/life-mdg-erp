<template>
    <Transition name="help-slide">
        <aside
            v-if="isOpen"
            class="fixed right-0 top-0 z-40 flex h-full w-80 flex-col bg-canvas shadow-xl"
            aria-label="Contextual help"
        >
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-subtle px-4 py-3">
                <div class="flex items-center gap-2">
                    <span class="i-heroicons-question-mark-circle text-primary-500 size-5" />
                    <span class="text-sm font-semibold text-fg-1">Help</span>
                </div>
                <button
                    type="button"
                    class="rounded p-1 text-fg-4 hover:bg-sunken hover:text-fg-3"
                    aria-label="Close help panel"
                    @click="$emit('close')"
                >
                    <span class="i-heroicons-x-mark size-4" />
                </button>
            </div>

            <!-- Loading state -->
            <div v-if="isLoading" class="flex flex-1 items-center justify-center">
                <span class="i-heroicons-arrow-path animate-spin size-6 text-fg-4" />
            </div>

            <!-- Content -->
            <div v-else-if="help" class="flex flex-1 flex-col gap-4 overflow-y-auto p-4">
                <h2 class="text-base font-semibold text-fg-1">
                    {{ help.title }}
                </h2>

                <p class="text-sm text-fg-2">
                    {{ help.summary }}
                </p>

                <div v-if="help.tips.length" class="space-y-2">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-fg-3">
                        Tips
                    </h3>
                    <ul class="space-y-2">
                        <li
                            v-for="(tip, i) in help.tips"
                            :key="i"
                            class="flex gap-2 text-sm text-fg-2"
                        >
                            <span class="mt-0.5 shrink-0 text-primary-500">•</span>
                            {{ tip }}
                        </li>
                    </ul>
                </div>

                <a
                    v-if="help.docs_url"
                    :href="help.docs_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-auto inline-flex items-center gap-1 text-sm text-link hover:underline"
                >
                    Full documentation
                    <span class="i-heroicons-arrow-top-right-on-square size-3.5" />
                </a>
            </div>

            <!-- Empty state -->
            <div v-else class="flex flex-1 flex-col items-center justify-center gap-2 p-6 text-center">
                <span class="i-heroicons-document-text size-10 text-fg-4" />
                <p class="text-sm text-fg-3">No help content available for this page.</p>
            </div>
        </aside>
    </Transition>

    <!-- Backdrop -->
    <Transition name="fade">
        <div
            v-if="isOpen"
            class="fixed inset-0 z-30 bg-black/20 dark:bg-black/40"
            @click="$emit('close')"
        />
    </Transition>
</template>

<script setup lang="ts">
import { watch } from 'vue'
import { useHelp } from '@/composables/useHelp'

const props = defineProps<{
    isOpen: boolean
    contextKey: string
}>()

defineEmits<{
    close: []
}>()

const { help, isLoading, loadHelp } = useHelp()

watch(
    () => [props.isOpen, props.contextKey],
    ([open, key]) => {
        if (open && key) loadHelp(key as string)
    },
    { immediate: true }
)
</script>

<style scoped>
.help-slide-enter-active,
.help-slide-leave-active {
    transition: transform 0.2s ease;
}
.help-slide-enter-from,
.help-slide-leave-to {
    transform: translateX(100%);
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
