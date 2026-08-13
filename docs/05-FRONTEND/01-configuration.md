# Configuration frontend

## Stack

Vue 3 (Composition API) + Inertia.js + Vite + PrimeVue + Pinia + vue-i18n + TailwindCSS.

Contrairement à Widehalo-ERP (dossier `webapp/` séparé), le frontend de life-mdg-erp est directement intégré à l'application Laravel : point d'entrée `resources/js/app.js`, pas de serveur frontend distinct en production, pas de `package-lock.json`/`vite.config.js` dans un sous-dossier.

## Alias et résolution de modules

```js
// vite.config.js
resolve: {
  alias: {
    '@': resolve(__dirname, 'resources/js'),
    '@modules': resolve(__dirname, 'Modules'),
  },
}
```

`@modules` permet aux pages Vue de chaque module métier d'importer des composants partagés sans chemin relatif fragile (`../../../resources/js/Components/...`).

## Découpage des chunks (cache navigateur)

`vite.config.js` définit un `manualChunks` explicite pour optimiser le cache HTTP long terme :

| Chunk | Contenu |
|---|---|
| `vendor-core` | vue, @inertiajs/vue3, pinia, vue-i18n |
| `vendor-utils` | lodash-es, dayjs, zod, vee-validate |
| `vendor-http` | axios, @tanstack/vue-query, graphql |
| `vendor-monitoring` | @sentry/* |
| `primevue-table` | PrimeVue DataTable/Column (chargement différé, composants lourds) |
| `primevue-*` (autres) | Découpage supplémentaire par famille de composants PrimeVue |

## i18n

`lang/{ar,en,es,fr,ha,hi,mg,pt,sw,zh}.json` — 10 langues disponibles. `resources/js/app.js` câble actuellement `en, fr, pt, es` dans vue-i18n ; les 6 autres sont prêtes à être ajoutées à la liste d'import dès que le besoin apparaît (aucun travail de traduction supplémentaire requis, les fichiers existent déjà).

## Commandes

```bash
npm run dev            # serveur de développement Vite (hot-reload)
npm run build           # build de production → public/build/
npm run type-check      # vérification TypeScript (vue-tsc)
npm run lint             # ESLint (resources/js, --fix)
npm run test              # tests unitaires Vitest
npm run test:coverage      # avec couverture
npm run test:e2e            # tests end-to-end Playwright
```

## Composants globaux embarqués partout

- `AiAssistantPanel` — guidance IA contextuelle (`useAiAssistant` composable), appelée au `onMounted()` de chaque page intégrée.
- `StrategyWidget` — résumé compact de ratios stratégiques, embarqué dans les tableaux de bord de module.
- `QuickTicketButton` (`resources/js/Components/Helpdesk/`) — bouton global "Signaler un incident", embarqué dans `AppLayout.vue`, disponible depuis n'importe quelle page.
