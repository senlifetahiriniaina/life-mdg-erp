# Structure du code

```
/
├── app/                    # Application Laravel racine — VRAI point d'entrée
│   ├── Http/Controllers/   # Contrôleurs globaux (Admin/*, Api/*, HealthController, MetricsController...)
│   ├── Http/Middleware/    # Middlewares globaux (MetricsToken, etc.)
│   ├── Models/             # Modèles racine (User, Tenant...)
│   └── Exceptions/         # Gestion d'erreurs (rendu JSON pour API, page Inertia "Error" pour le web)
├── Modules/                # 27 modules métier (laravel-modules / nwidart)
│   └── <Nom>/
│       ├── app/
│       │   ├── Models/
│       │   ├── Http/Controllers/
│       │   ├── Http/Requests/
│       │   ├── Services/
│       │   └── Policies/
│       ├── database/
│       │   ├── migrations/
│       │   ├── factories/
│       │   └── seeders/
│       ├── resources/js/Pages/   # Pages Vue propres au module (si applicable)
│       ├── routes/               # api.php / web.php du module
│       └── tests/{Unit,Feature}/
├── resources/js/           # Frontend Vue 3 + Inertia
│   ├── app.js               # Point d'entrée réel (PAS apps/webapp, qui n'existe pas ici)
│   ├── Pages/               # Pages globales (dashboard, auth, admin...)
│   └── Components/          # Composants partagés (QuickTicketButton, StrategyWidget, AiAssistantPanel...)
├── database/
│   ├── migrations/          # Migrations racine — schéma partagé entre modules (users, permissions,
│   │                         # activity_log, tenant_modules, personal_access_tokens, consent_logs/gdpr_*, webhooks)
│   └── seeders/              # RolesAndPermissionsSeeder = seeder RBAC actif
├── lang/                    # Dictionnaires i18n : ar, en, es, fr, ha, hi, mg, pt, sw, zh
├── tests/                   # Tests racine
│   ├── Unit/
│   ├── Feature/
│   └── Integration/          # Tests d'intégration cross-modules (Accounting, CRM, Helpdesk, HR, Inventory)
├── config/                  # Configuration Laravel standard + modules_statuses.json (liste des 27 modules actifs)
├── routes/                  # api.php/web.php — routes GLOBALES uniquement (santé, auth, dashboard, chat IA, admin)
├── docs/                    # Cette documentation
├── .github/workflows/       # CI GitHub Actions (voir docs/07-DEPLOIEMENT/)
└── SECURITY.md, CLAUDE.md, README.md
```

## Points clés

- **Chaque module s'auto-enregistre.** Le `RouteServiceProvider` de chaque module dans `Modules/<Nom>/app/Providers/` déclare ses propres routes — `routes/api.php` à la racine ne contient que les routes transverses (santé, métriques, auth JWT, chat IA, admin). Retirer un module se fait par `rm -rf Modules/<Nom>` + suppression de son entrée dans `config/modules_statuses.json`, sans toucher au routing global.
- **Les migrations sont réparties en deux niveaux** : `database/migrations/` (racine) porte le schéma partagé entre plusieurs modules (utilisateurs, permissions RBAC, journal d'audit, consentement RGPD, webhooks), tandis que chaque module porte ses propres tables dans `Modules/<Nom>/database/migrations/`.
- **Les tests suivent la même répartition** : `tests/{Unit,Feature}` (racine) + `Modules/*/tests/{Unit,Feature}` (par module) + `tests/Integration/` (tests cross-modules, ex. `Helpdesk/TicketWorkflowTest.php` qui vérifie qu'un ticket peut bien être créé depuis un autre module).
- **Alias Vite** : `@` → `resources/js`, `@modules` → `Modules` — permet aux pages Vue d'un module d'importer des composants partagés sans chemin relatif fragile.
