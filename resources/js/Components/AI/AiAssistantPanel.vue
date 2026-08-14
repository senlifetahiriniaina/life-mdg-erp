<script setup>
defineProps({
  guidance: {
    type: Object,
    default: null,
  },
})
</script>

<template>
  <div
    v-if="guidance"
    class="ai-assistant-panel bg-surface-0 dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-xl shadow-md p-4 space-y-3"
  >
    <div class="flex items-center gap-2">
      <i class="pi pi-sparkles text-violet-600" style="font-size: 14px" aria-hidden="true" />
      <span class="font-semibold text-sm">Assistant IA</span>
      <span
        :class="[
          'text-xs px-1.5 py-0.5 rounded-full font-medium',
          guidance.enabled
            ? 'bg-green-100 text-green-700'
            : 'bg-surface-100 text-surface-500',
        ]"
      >
        {{ guidance.enabled ? 'live' : 'statique' }}
      </span>
    </div>

    <p v-if="guidance.what_to_do" class="text-sm text-surface-700 dark:text-surface-200">
      {{ guidance.what_to_do }}
    </p>

    <ol v-if="guidance.how_to_do && guidance.how_to_do.length" class="space-y-1.5">
      <li
        v-for="(step, i) in guidance.how_to_do.slice(0, 3)"
        :key="i"
        class="flex items-start gap-2 text-xs text-surface-600 dark:text-surface-300"
      >
        <span
          class="flex-shrink-0 w-5 h-5 rounded-full bg-violet-100 text-violet-700 flex items-center justify-center font-bold text-xs"
        >{{ i + 1 }}</span>
        <span class="leading-relaxed pt-0.5">{{ step }}</span>
      </li>
    </ol>

    <div
      v-if="guidance.decision_indicators && guidance.decision_indicators.length"
      class="flex flex-wrap gap-1.5"
    >
      <span
        v-for="indicator in guidance.decision_indicators"
        :key="indicator.label"
        :class="[
          'inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium border',
          indicator.status === 'ok'
            ? 'bg-green-50 text-green-700 border-green-200'
            : indicator.status === 'warning'
              ? 'bg-orange-50 text-orange-700 border-orange-200'
              : 'bg-red-50 text-red-700 border-red-200',
        ]"
      >
        {{ indicator.label }}: {{ indicator.value }}
      </span>
    </div>

    <div v-if="guidance.warnings && guidance.warnings.length" class="space-y-1.5">
      <div
        v-for="(warning, i) in guidance.warnings"
        :key="i"
        class="flex items-start gap-2 bg-yellow-50 border border-yellow-200 rounded-lg px-3 py-2 text-xs text-yellow-800"
      >
        <i class="pi pi-exclamation-triangle flex-shrink-0 mt-0.5" style="font-size: 11px" />
        <span>{{ warning }}</span>
      </div>
    </div>

    <div
      v-if="guidance.next_actions && guidance.next_actions.length"
      class="flex flex-wrap gap-1.5"
    >
      <span
        v-for="nextAction in guidance.next_actions"
        :key="nextAction.action"
        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-violet-50 text-violet-700 border border-violet-200"
      >
        <i class="pi pi-arrow-right" style="font-size: 10px" />
        {{ nextAction.label }}
      </span>
    </div>

    <ul v-if="guidance.tips && guidance.tips.length" class="space-y-1">
      <li
        v-for="(tip, i) in guidance.tips"
        :key="i"
        class="text-xs italic text-surface-500 pl-2 border-l-2 border-surface-200"
      >
        {{ tip }}
      </li>
    </ul>
  </div>
</template>
