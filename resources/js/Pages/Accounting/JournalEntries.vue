<!--
  Accounting · Journal Entries — flat-file entry point.
  The full implementation lives in JournalEntries/Index.vue (paginated table,
  KPI cards, create dialog). This file re-exports that component so that both
  the route name "Accounting/JournalEntries" and "Accounting/JournalEntries/Index"
  resolve to the same page.
-->
<script>
import JournalEntriesIndex from './JournalEntries/Index.vue'
export default JournalEntriesIndex
</script>
