/**
 * Standard Vue + TypeScript shim: without this, importing a .vue file from
 * a .ts context has no declaration to resolve against, so vue-tsc reports
 * TS7016 "Could not find a declaration file for module '...'" for every
 * such import — this was previously hidden for many files because their
 * importers weren't themselves under lang="ts" (see AppLayout.vue), but
 * surfaces for any .vue file, once its importer is.
 */
declare module '*.vue' {
  import type { DefineComponent } from 'vue'
  const component: DefineComponent<{}, {}, any>
  export default component
}
