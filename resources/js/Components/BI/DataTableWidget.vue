<template>
  <div class="dt-widget">
    <div v-if="title" class="dt-widget__head">
      <span class="dt-widget__title">{{ title }}</span>
      <div style="display:flex;gap:6px;align-items:center">
        <input
          v-if="searchable"
          v-model="search"
          class="dt-widget__search"
          placeholder="Rechercher…"
          type="text"
        />
        <slot name="actions" />
      </div>
    </div>

    <div v-if="loading" class="dt-widget__loading">
      <div v-for="i in 4" :key="i" class="dt-widget__skeleton-row" />
    </div>

    <div v-else-if="error" class="dt-widget__error">
      <i class="pi pi-exclamation-circle" />
      {{ error }}
    </div>

    <div v-else class="dt-widget__table-wrap">
      <table class="dt-widget__table">
        <thead>
          <tr>
            <th
              v-for="col in columns"
              :key="col.field"
              :style="col.width ? { width: col.width } : {}"
              :class="{ 'dt-widget__th--sortable': sortable }"
              @click="sortable && toggleSort(col.field)"
            >
              <span>{{ col.header }}</span>
              <i
                v-if="sortable"
                :class="sortIcon(col.field)"
                style="font-size:10px;margin-left:4px;opacity:0.6"
              />
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, idx) in paginatedRows" :key="idx" class="dt-widget__row">
            <td v-for="col in columns" :key="col.field">
              <slot :name="`cell-${col.field}`" :row="row" :value="row[col.field]">
                <span :class="col.class ? col.class(row[col.field]) : ''" :style="col.style ? col.style(row[col.field]) : ''">
                  {{ formatCell(row[col.field], col) }}
                </span>
              </slot>
            </td>
          </tr>
          <tr v-if="filteredRows.length === 0">
            <td :colspan="columns.length" class="dt-widget__empty-cell">
              <i class="pi pi-inbox" style="font-size:20px;margin-bottom:6px;display:block" />
              Aucune donnée
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="!loading && filteredRows.length > pageSize" class="dt-widget__pagination">
      <span class="dt-widget__page-info">
        {{ (currentPage - 1) * pageSize + 1 }}–{{ Math.min(currentPage * pageSize, filteredRows.length) }} sur {{ filteredRows.length }}
      </span>
      <div style="display:flex;gap:4px">
        <button class="dt-widget__page-btn" :disabled="currentPage === 1" @click="currentPage--">
          <i class="pi pi-chevron-left" style="font-size:11px" />
        </button>
        <button
          v-for="p in visiblePages"
          :key="p"
          class="dt-widget__page-btn"
          :class="{ 'dt-widget__page-btn--active': p === currentPage }"
          @click="currentPage = p"
        >{{ p }}</button>
        <button class="dt-widget__page-btn" :disabled="currentPage === totalPages" @click="currentPage++">
          <i class="pi pi-chevron-right" style="font-size:11px" />
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  title:     { type: String,  default: '' },
  columns:   { type: Array,   required: true },
  rows:      { type: Array,   default: () => [] },
  loading:   { type: Boolean, default: false },
  error:     { type: String,  default: '' },
  pageSize:  { type: Number,  default: 10 },
  sortable:  { type: Boolean, default: true },
  searchable:{ type: Boolean, default: true },
})

const search      = ref('')
const currentPage = ref(1)
const sortField   = ref('')
const sortDir     = ref('asc')

watch(search, () => { currentPage.value = 1 })

const filteredRows = computed(() => {
  let rows = [...props.rows]
  if (search.value) {
    const q = search.value.toLowerCase()
    rows = rows.filter(row =>
      Object.values(row).some(v => String(v ?? '').toLowerCase().includes(q))
    )
  }
  if (sortField.value) {
    rows.sort((a, b) => {
      const va = a[sortField.value] ?? ''
      const vb = b[sortField.value] ?? ''
      const cmp = typeof va === 'number' ? va - vb : String(va).localeCompare(String(vb))
      return sortDir.value === 'asc' ? cmp : -cmp
    })
  }
  return rows
})

const totalPages = computed(() => Math.ceil(filteredRows.value.length / props.pageSize))

const paginatedRows = computed(() => {
  const start = (currentPage.value - 1) * props.pageSize
  return filteredRows.value.slice(start, start + props.pageSize)
})

const visiblePages = computed(() => {
  const total = totalPages.value
  const cur   = currentPage.value
  const pages = []
  for (let p = Math.max(1, cur - 2); p <= Math.min(total, cur + 2); p++) {
    pages.push(p)
  }
  return pages
})

function toggleSort(field) {
  if (sortField.value === field) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortField.value = field
    sortDir.value   = 'asc'
  }
}

function sortIcon(field) {
  if (sortField.value !== field) return 'pi pi-sort'
  return sortDir.value === 'asc' ? 'pi pi-sort-up' : 'pi pi-sort-down'
}

function formatCell(value, col) {
  if (col.format === 'currency') {
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(value ?? 0)
  }
  if (col.format === 'number') {
    return new Intl.NumberFormat('fr-FR').format(value ?? 0)
  }
  if (col.format === 'date') {
    return value ? new Date(value).toLocaleDateString('fr-FR') : '—'
  }
  return value ?? '—'
}
</script>

<style scoped>
.dt-widget { display: flex; flex-direction: column; }
.dt-widget__head {
  display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;
}
.dt-widget__title { font-size: 13px; font-weight: 600; color: var(--fg-1); }
.dt-widget__search {
  font-family: var(--font-sans); font-size: 12px; padding: 5px 10px;
  border: 1px solid var(--border-subtle); border-radius: var(--r-md);
  background: var(--bg-canvas); color: var(--fg-1); outline: none;
  width: 160px;
}
.dt-widget__search:focus { border-color: var(--halo-400); }
.dt-widget__table-wrap { overflow-x: auto; }
.dt-widget__table { width: 100%; border-collapse: collapse; font-size: 13px; }
.dt-widget__table thead th {
  text-align: left; font-size: 11px; font-weight: 600; color: var(--fg-3);
  text-transform: uppercase; letter-spacing: 0.05em;
  padding: 8px 12px; background: var(--bg-sunken);
  border-bottom: 1px solid var(--border-subtle); white-space: nowrap; user-select: none;
}
.dt-widget__th--sortable { cursor: pointer; }
.dt-widget__th--sortable:hover { color: var(--fg-1); }
.dt-widget__row td { padding: 9px 12px; border-bottom: 1px solid var(--border-subtle); color: var(--fg-1); }
.dt-widget__row:last-child td { border-bottom: 0; }
.dt-widget__row:hover { background: var(--bg-sunken); }
.dt-widget__empty-cell {
  text-align: center; padding: 32px 16px; color: var(--fg-3); font-size: 13px;
}
.dt-widget__loading { display: flex; flex-direction: column; gap: 6px; padding: 8px 0; }
.dt-widget__skeleton-row {
  height: 36px; background: var(--bg-sunken); border-radius: var(--r-sm);
  animation: pulse 1.4s ease-in-out infinite;
}
.dt-widget__error {
  display: flex; align-items: center; gap: 8px;
  padding: 16px; color: var(--danger-fg); font-size: 13px;
}
.dt-widget__pagination {
  display: flex; align-items: center; justify-content: space-between;
  padding: 10px 12px; border-top: 1px solid var(--border-subtle);
  font-size: 12px; color: var(--fg-3);
}
.dt-widget__page-btn {
  min-width: 28px; height: 28px; padding: 0 6px;
  border: 1px solid var(--border-subtle); border-radius: var(--r-sm);
  background: var(--bg-canvas); color: var(--fg-2); cursor: pointer; font-size: 12px;
  display: inline-flex; align-items: center; justify-content: center;
  transition: all var(--dur-fast);
}
.dt-widget__page-btn:hover:not(:disabled) { background: var(--bg-sunken); color: var(--fg-1); }
.dt-widget__page-btn--active { background: var(--halo-500); color: #fff; border-color: var(--halo-500); }
.dt-widget__page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.5} }
</style>
