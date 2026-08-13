# Stratégie de tests

## Structure

Trois suites déclarées dans `phpunit.xml` :

| Suite | Répertoires | Contenu |
|---|---|---|
| `Unit` | `tests/Unit`, `Modules/*/tests/Unit` | Tests unitaires, pas d'accès base de données en général |
| `Feature` | `tests/Feature`, `Modules/*/tests/Feature` | Tests HTTP/Eloquent avec base de données |
| `Integration` | `tests/Integration` | Tests cross-modules (Accounting, CRM, Helpdesk, HR, Inventory) — vérifient des scénarios qui traversent plusieurs modules à la fois |

**Historique** : la suite `Integration` existait sur le disque (5 fichiers) mais n'était pas déclarée dans `phpunit.xml` — ces tests ne tournaient donc jamais, ni en local ni en CI, sans qu'aucune erreur ne le signale. Corrigé lors de la validation CI de ce dépôt. Les faire tourner pour la première fois a immédiatement révélé un bug réel préexistant (référence à un modèle `HR\Payroll` retiré intentionnellement pendant l'extraction) — corrigé dans la foulée. **Enseignement** : une suite de tests non déclarée dans la configuration est un faux sentiment de couverture ; vérifiez périodiquement que `phpunit.xml` couvre bien tous les répertoires `tests/*` présents sur le disque.

## Lancer les tests

```bash
vendor/bin/pest                          # toutes les suites
vendor/bin/pest --testsuite=Integration  # une suite spécifique
vendor/bin/pest --filter=NomDuTest       # un test spécifique
```

## Environnement de test

SQLite en mémoire par défaut (`phpunit.xml`), MySQL réel en CI (`ci.yml`, service `mysql:8.4.5`) — voir `docs/06-BASE-DE-DONNEES/SCHEMA-GENERAL.md`.

## Point d'attention : tests liés à l'IA

Certains tests (notamment dans `Accounting`, ex. `AccountingAiExtendedTest`) exercent des chemins de code qui appellent (ou tentent d'appeler) l'API Anthropic. En l'absence de `ANTHROPIC_API_KEY` en environnement de test, ces appels ne court-circuitent pas immédiatement — ils attendent un timeout réseau réel avant de basculer sur le comportement de repli, ce qui peut prendre **20 à 25 secondes par test concerné**. Sur un environnement avec de nombreux tests de ce type, cela peut représenter une part significative du temps total d'exécution de la suite.

**Recommandation pour un run CI rapide et fiable** : mocker les appels HTTP sortants vers l'API Anthropic dans ces tests spécifiques (`Http::fake()` côté Laravel) plutôt que de laisser le test dépendre d'un vrai timeout réseau — c'est un chantier identifié mais non traité dans le cadre de cette extraction (les tests fonctionnent et passent, juste lentement). Ce n'est pas un défaut du principe "Fallback-First" lui-même (qui fonctionne comme prévu, cf. `docs/02-ARCHITECTURE/PRINCIPES.md`), seulement de la façon dont certains tests l'exercent.

## Écarts de couverture connus (backlog, pas une régression)

Documentés en détail dans `CLAUDE.md` sous "Known gaps" : un lot de fonctionnalités déjà incomplètes dans le Widehalo-ERP source (Consolidation multi-société, ASC606, quelques modèles avancés de Security/Analytics) génèrent des échecs de tests individuels, sans crash — `vendor/bin/pest` va jusqu'au bout de la suite. Hors périmètre du lancement Life MDG, traité comme backlog.

## Avant tout merge

```bash
php artisan migrate:fresh --seed   # schéma propre
vendor/bin/pest                    # tests backend
npm run build && npm run type-check # frontend
php -l <fichiers modifiés>         # sweep syntaxe PHP (historique de bugs namespace/nom-de-fichier, cf. docs/01-DEMARRAGE/01-installation.md)
```
