<template>
  <div class="accessible-table-container">
    <table
      role="table"
      :aria-label="caption || 'Data table'"
      :aria-rowcount="rows.length"
      :aria-colcount="columns.length"
    >
      <caption v-if="caption" class="sr-only">{{ caption }}</caption>

      <thead>
        <tr role="row">
          <th
            v-for="(col, idx) in columns"
            :key="idx"
            scope="col"
            role="columnheader"
            :aria-sort="getSortState(col.key)"
            :aria-colindex="idx + 1"
          >
            <button
              v-if="sortable && col.sortable !== false"
              @click="toggleSort(col.key)"
              class="sort-button"
              :aria-label="`Sort by ${col.label}, ${getSortLabel(col.key)}`"
            >
              {{ col.label }}
              <span v-if="sortKey === col.key" class="sort-indicator">
                {{ sortAsc ? '↑' : '↓' }}
              </span>
            </button>
            <span v-else>{{ col.label }}</span>
          </th>
        </tr>
      </thead>

      <tbody>
        <tr v-for="(row, rowIdx) in rows" :key="rowIdx" role="row" :aria-rowindex="rowIdx + 2">
          <td
            v-for="(col, colIdx) in columns"
            :key="`${rowIdx}-${colIdx}`"
            role="cell"
            :aria-colindex="colIdx + 1"
          >
            <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">
              {{ row[col.key] }}
            </slot>
          </td>
        </tr>
      </tbody>
    </table>

    <div v-if="rows.length === 0" role="status" class="no-data">
      {{ emptyMessage }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'

interface Column {
  key: string
  label: string
  sortable?: boolean
}

interface Props {
  columns: Column[]
  rows: Record<string, any>[]
  caption?: string
  sortable?: boolean
  emptyMessage?: string
}

withDefaults(defineProps<Props>(), {
  sortable: false,
  emptyMessage: 'No data available',
})

const sortKey = ref<string | null>(null)
const sortAsc = ref(true)

const toggleSort = (key: string) => {
  if (sortKey.value === key) {
    sortAsc.value = !sortAsc.value
  } else {
    sortKey.value = key
    sortAsc.value = true
  }
}

const getSortState = (key: string) => {
  if (sortKey.value !== key) return 'none'
  return sortAsc.value ? 'ascending' : 'descending'
}

const getSortLabel = (key: string) => {
  if (sortKey.value !== key) return 'not sorted'
  return sortAsc.value ? 'sorted ascending' : 'sorted descending'
}
</script>

<style scoped>
.accessible-table-container {
  width: 100%;
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
  border: 1px solid var(--border-default, #D0D4DE);
}

thead {
  background-color: var(--bg-subtle, #F0F2F7);
  border-bottom: 2px solid var(--border-default, #D0D4DE);
}

th {
  padding: 0.75rem 1rem;
  text-align: left;
  font-weight: 600;
  color: var(--fg-1, var(--fg-1));
  white-space: nowrap;
}

.sort-button {
  background: none;
  border: none;
  padding: 0;
  font-size: inherit;
  font-weight: inherit;
  cursor: pointer;
  color: inherit;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-height: 44px;
  width: 100%;
}

.sort-button:focus-visible {
  outline: 3px solid var(--halo-500, var(--halo-500));
  outline-offset: 2px;
  border-radius: var(--radius-sm, 4px);
}

.sort-indicator {
  opacity: 0.7;
  font-size: 0.75rem;
}

td {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid var(--border-subtle, #E4E8F2);
  color: var(--fg-1, var(--fg-1));
}

tbody tr:hover {
  background-color: var(--bg-subtle, #F0F2F7);
}

tbody tr:focus-within {
  background-color: var(--halo-50, var(--halo-50));
  outline: 2px solid var(--halo-500, var(--halo-500));
  outline-offset: -1px;
}

.no-data {
  padding: 2rem;
  text-align: center;
  color: var(--fg-3, var(--fg-3));
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border-width: 0;
}
</style>
