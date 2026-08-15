/**
 * Ziggy's `route()` helper is injected onto `window` at runtime by the
 * @routes Blade directive (see resources/views/app.blade.php), not
 * imported — so every .vue/.ts file calling the bare global `route()`
 * needs an ambient declaration or vue-tsc reports TS2304 "Cannot find
 * name 'route'". Signature is intentionally loose (matches call sites
 * across many files with varying param shapes) rather than the fully
 * typed route-name union ziggy-js/vite-plugin-ziggy can generate — that
 * would require wiring up `php artisan ziggy:generate --types`, a
 * separate, larger change.
 */
declare global {
  function route(name?: string, params?: unknown, absolute?: boolean, config?: unknown): string;
}

export {};
