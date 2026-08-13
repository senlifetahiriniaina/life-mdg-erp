<template>
  <div class="mobile-data-table">
    <!-- Desktop Table -->
    <div v-if="!isMobile" class="desktop-view">
      <table class="table" role="table">
        <thead>
          <tr>
            <th v-for="col in columns" :key="col.key" @click="col.sortable && toggleSort(col.key)">
              <span>{{ col.label }}</span>
              <i v-if="col.sortable" class="pi pi-arrows-v"></i>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in sortedRows" :key="row.id">
            <td v-for="col in columns" :key="col.key">
              <slot :name="`cell-${col.key}`" :value="row[col.key]" :row="row">
                {{ row[col.key] }}
              </slot>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Mobile Cards View -->
    <div v-if="isMobile" class="mobile-view" role="list">
      <div
        v-for="row in sortedRows"
        :key="row.id"
        class="card"
        role="listitem"
        :aria-label="columns.length ? String(row[columns[0].key]) : undefined"
      >
        <dl class="card-dl">
          <div v-for="col in columns" :key="col.key" class="card-row">
            <dt class="card-label">{{ col.label }}</dt>
            <dd class="card-value">
              <slot :name="`cell-${col.key}`" :value="row[col.key]" :row="row">
                {{ row[col.key] }}
              </slot>
            </dd>
          </div>
        </dl>
        <div v-if="$slots.actions" class="card-actions">
          <slot name="actions" :row="row" />
        </div>
      </div>
    </div>

    <div v-if="!rows || rows.length === 0" class="empty-state">
      <p>{{ emptyMessage }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useResponsive } from '@/composables/useResponsive'

interface Column {
  key: string
  label: string
  sortable?: boolean
}

interface Props {
  columns: Column[]
  rows: Record<string, any>[]
  emptyMessage?: string
}

const props = withDefaults(defineProps<Props>(), {
  emptyMessage: 'No data available',
})

const { isMobile } = useResponsive()
const sortKey = ref<string | null>(null)
const sortAsc = ref(true)

const sortedRows = computed(() => {
  if (!sortKey.value || !props.rows) return props.rows

  return [...props.rows].sort((a, b) => {
    const aVal = a[sortKey.value!]
    const bVal = b[sortKey.value!]

    if (aVal < bVal) return sortAsc.value ? -1 : 1
    if (aVal > bVal) return sortAsc.value ? 1 : -1
    return 0
  })
})

const toggleSort = (key: string) => {
  if (sortKey.value === key) {
    sortAsc.value = !sortAsc.value
  } else {
    sortKey.value = key
    sortAsc.value = true
  }
}
</script>

<style scoped>
.mobile-data-table {
  width: 100%;
}

.desktop-view {
  overflow-x: auto;
}

.table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.table thead {
  background-color: var(--bg-subtle);
  border-bottom: 2px solid var(--border-subtle);
}

.table th {
  padding: 0.75rem;
  text-align: left;
  font-weight: 600;
  color: var(--fg-1);
  cursor: pointer;
  user-select: none;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.table th:hover {
  background-color: var(--bg-muted);
}

.table td {
  padding: 0.75rem;
  border-bottom: 1px solid var(--border-subtle);
}

.table tbody tr:hover {
  background-color: var(--bg-subtle);
}

.mobile-view {
  display: none;
  gap: 1rem;
}

@media (max-width: 768px) {
  .desktop-view {
    display: none;
  }

  .mobile-view {
    display: flex;
    flex-direction: column;
  }

  .card-dl {
    margin: 0;
    padding: 0;
  }

  .card {
    background: var(--bg-canvas);
    border: 1px solid var(--border-subtle);
    border-radius: 8px;
    padding: 1rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  }

  .card-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--bg-muted);
  }

  .card-row:last-of-type {
    border-bottom: none;
  }

  .card-label {
    font-weight: 500;
    color: var(--fg-3);
    font-size: 0.75rem;
    text-transform: uppercase;
  }

  .card-value {
    color: var(--fg-1);
    font-weight: 500;
  }

  .card-actions {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-subtle);
    display: flex;
    gap: 0.5rem;
  }
}

.empty-state {
  padding: 2rem;
  text-align: center;
  color: var(--fg-3);
}
</style>
