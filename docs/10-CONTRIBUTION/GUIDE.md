# Guide de contribution

## Avant de commencer

- Lire `CLAUDE.md` (périmètre des 27 modules, décisions de trim documentées, gaps connus)
- Lire `docs/02-ARCHITECTURE/CARTE-MODULES.md` pour comprendre les dépendances entre modules avant d'en ajouter une nouvelle

## Workflow

1. Créer une branche depuis la branche par défaut
2. Développer dans le module concerné (`Modules/<Nom>/`) ou à la racine (`app/`, `resources/js/`) pour du code transverse
3. Écrire des tests (backend + frontend si UI) — voir `docs/08-TESTS/STRATEGIE-TESTS.md`
4. Vérifier localement avant de pousser :
   ```bash
   vendor/bin/pest
   vendor/bin/pint --test
   npm run build && npm run type-check
   php -l <fichiers modifiés>
   ```
5. Ouvrir une Pull Request — le template par défaut (`.github/pull_request_template.md`) guide la description ; pour une mise à jour de dépendance à impact sécurité, utiliser le template dédié "Security Update / Dependency Patch" (sélecteur de templates GitHub)

## Ajouter un module

1. Créer `Modules/<Nom>/` avec la structure standard (voir un module existant comme référence, ex. `Modules/CRM`)
2. Ajouter l'entrée dans `config/modules_statuses.json`
3. Le `RouteServiceProvider` du module enregistre ses propres routes — pas besoin de toucher `routes/api.php` à la racine
4. Si le module doit être "linkable" depuis Helpdesk (recevoir des tickets), ajouter le trait `HelpdeskLinkable` et enregistrer son alias morph-map dans `HelpdeskServiceProvider` — voir `docs/03-MODULES/Helpdesk.md`
5. Ajouter les permissions RBAC du nouveau module dans `database/seeders/RolesAndPermissionsSeeder.php` (constante `MODULES`) si des rôles doivent y accéder de façon granulaire
6. Documenter le module : créer `docs/03-MODULES/<Nom>.md` en suivant la structure des fichiers existants, et l'ajouter à `docs/INDEX.md`

## Style de code

- PHP : `vendor/bin/pint --test` (Laravel Pint, configuration standard)
- Vue/JS : `npm run lint` (ESLint)
- Pas de commentaire sur le "quoi" — le code doit être lisible par lui-même ; commenter uniquement les décisions non évidentes (contrainte cachée, contournement d'un bug spécifique)

## Sécurité

Toute vulnérabilité découverte doit être signalée selon `SECURITY.md`, pas via une issue publique. Voir `docs/09-RBAC-SECURITE/SECURITE.md` pour le dispositif de sécurité en place.
