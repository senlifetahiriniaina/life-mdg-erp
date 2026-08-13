# Les 7 piliers fondateurs, dans le périmètre life-mdg-erp

life-mdg-erp conserve les 7 principes fondateurs de WideHalo ERP, adaptés au périmètre de 27 modules retenu pour le lancement de Life MDG.

## Africa First

- **Comptabilité** : normes OHADA/SYSCOHADA (module `Accounting`).
- **Mobile money** : connecteurs Orange Money, MTN MoMo (`Modules/Integration/app/Services/Connectors/`).
- **Devises** : XOF/XAF/MGA et autres, via `Modules/Core/app/Services/SmartDefaultsService`.
- Contrairement à WideHalo ERP (25+ pays africains, UEMOA/CEMAC complets), life-mdg-erp se concentre sur les besoins de démarrage de Life MDG — les connecteurs et devises additionnels de WideHalo restent disponibles dans le code hérité mais ne sont pas la priorité de configuration initiale.

## Asia First

- Multi-devise / multi-fuseau horaire au niveau du socle Core.
- i18n : 10 dictionnaires présents dans `lang/` (ar, en, es, fr, ha, hi, mg, pt, sw, zh), dont 4 câblés par défaut dans `resources/js/app.js` (en, fr, pt, es) — les 6 autres sont prêts à être ajoutés à la liste d'import vue-i18n dès que nécessaire.

## API First

Toute fonctionnalité métier est exposée en REST avant toute interface. Voir `docs/04-API/CONVENTIONS.md` pour les conventions transverses (authentification, pagination, format d'erreur, rate limiting).

## Compliance First

RGPD, PDPL, OHADA, OWASP by design — repris intégralement de WideHalo : RBAC via `spatie/laravel-permission`, journal d'audit (`Modules/AuditLog`), chiffrement AES-256-GCM au repos, webhooks signés HMAC. Voir `docs/09-RBAC-SECURITE/SECURITE.md` et `SECURITY.md`.

## Simplicity First

- Assistant d'onboarding (module `Setup`) : import de données assisté par IA (Excel/CSV/PDF), mapping de colonnes intelligent.
- Valeurs par défaut intelligentes selon le pays/l'industrie sélectionné (`SmartDefaultsService`).
- Un bouton global "Signaler un incident" (`resources/js/Components/Helpdesk/QuickTicketButton.vue`), embarqué dans `AppLayout.vue`, permet à tout utilisateur connecté de créer un ticket Helpdesk depuis n'importe quelle page de n'importe quel module, sans avoir à naviguer jusqu'au module Helpdesk.

## AI Assisted First

`Modules\AI\Services\AiContextualAssistantService::getGuidance()` — même contrat que WideHalo. Le fallback statique (utilisé quand `ANTHROPIC_API_KEY` est absente — `enabled: false` renvoyé, jamais d'erreur) couvre déjà CRM, Accounting, HR, Inventory, Sales pour ce périmètre.

**Point d'attention CI/tests** : les tests backend qui exercent des chemins d'appel réels vers l'API Anthropic (sans mock HTTP) prennent chacun ~20-25 secondes en l'absence de clé API, faute de court-circuiter rapidement sur un timeout réseau — voir `docs/08-TESTS/STRATEGIE-TESTS.md`.

## Strategy First

Cockpit de KPI/ratios cross-modules, benchmarks, corrélations, recommandations stratégiques par IA — `Modules/Strategy/app/Services/KPIRegistryService.php`. Ratios conservés pour Accounting, CRM, Inventory, Sales, Helpdesk et HR (basique). Les ratios propres à Manufacturing (module exclu du périmètre) ont été retirés avec le module. Les ratios HR `training_roi`/`time_to_fill` renvoient des valeurs de repli statiques (145.0 / 28 jours) car ils dépendent des fonctionnalités Formation/ATS hors périmètre — c'est le même pattern de repli utilisé dans tout `KPIRegistryService` pour toute source de données absente (chaque requête KPI est encapsulée dans un try/catch avec repli), donc ce n'est pas une régression spécifique à ce ratio.
