# Sécurité et conformité

Voir aussi `SECURITY.md` à la racine pour la politique de signalement de vulnérabilité, et **`STANDARDS-SECURITE-MADAGASCAR.md`** dans ce même dossier pour le cadre légal malgache détaillé et l'état réel (vérifié, sourcé fichier:ligne) de chaque dispositif de sécurité — c'est la source de vérité, ce fichier-ci ne fait que couvrir le fonctionnement opérationnel RBAC/webhooks/rate-limiting.

## Conformité (Compliance First)

Cadre légal détaillé (Loi 2014-038, Décret 2023-1541, CMIL, Convention de Malabo/Loi 2024-004, ANSSI-Madagascar) : voir `STANDARDS-SECURITE-MADAGASCAR.md`.

- **OHADA/SYSCOHADA** : normes comptables africaines implémentées dans le module `Accounting`.
- **OWASP by design** : validation des entrées, protection CSRF, en-têtes de sécurité — état détaillé (réel/partiel/mort/inactif) dans `STANDARDS-SECURITE-MADAGASCAR.md`.

## RBAC

`spatie/laravel-permission`, 22 rôles — voir `docs/09-RBAC-SECURITE/MATRICE-RBAC.md`.

## Journalisation d'audit

Module `AuditLog` — état réel (couverture partielle, deux mécanismes disjoints) détaillé dans `STANDARDS-SECURITE-MADAGASCAR.md`. Permission dédiée `admin.audit.view` pour la consultation.

## Chiffrement

AES-256-CBC au repos pour les champs sensibles via `App\Traits\EncryptableTrait`/casts `encrypted` Laravel (pas AES-256-GCM — voir `STANDARDS-SECURITE-MADAGASCAR.md` pour le détail, y compris le coffre-fort de secrets non fonctionnel de `Modules/Security`).

## Webhooks

Signature HMAC pour authentifier l'origine des webhooks entrants/sortants.

## Authentification

- Sanctum (tokens API, 30 jours par défaut) — voir `docs/04-API/CONVENTIONS.md`.
- 2FA disponible via `pragmarx/google2fa-laravel` (`GOOGLE2FA_ENABLED` dans `.env.example`).

## Rate limiting

60 requêtes/minute par défaut sur l'API, limiteur dédié `throttle:ai` pour les endpoints IA — voir `docs/04-API/CONVENTIONS.md`.

## Analyse de sécurité automatisée (CI)

Les workflows GitHub Actions suivants exécutent des scans de sécurité **informationnels** (`continue-on-error: true` — ils remontent des résultats mais ne bloquent pas les merges, un choix délibéré documenté ci-dessous) :

| Workflow | Fréquence | Ce qu'il vérifie |
|---|---|---|
| `ci.yml` (job `security-scan`) | À chaque push/PR | `composer audit`, `npm audit` |
| `security-audit-scheduled.yml` | Hebdomadaire (lundi 02h UTC) | Audit complet composer + npm, ouvre une issue GitHub en cas d'échec du workflow lui-même |
| `supply-chain.yml` | Push/PR sur `main` + hebdomadaire | Scan CVE (Trivy), génération de SBOM (CycloneDX), détection de secrets (TruffleHog), revue de dépendances sur PR |
| `dependency-check.yml` | Hebdomadaire (vendredi 10h UTC) | Paquets obsolètes (composer + npm) |

**Pourquoi informationnel et non bloquant** : ce choix a été fait consciemment pour life-mdg-erp — les checks tournent et remontent les problèmes dans les résumés de workflow et en artefacts téléchargeables, mais un échec de scan ne bloque pas un merge. Cela évite qu'une vulnérabilité pré-existante dans une dépendance transitive (hors du contrôle immédiat de l'équipe) bloque tout le flux de développement ; la contrepartie est qu'il faut consulter activement les résumés de run (ou les issues automatiquement créées par `security-audit-scheduled.yml` en cas d'échec du workflow) plutôt que de compter sur un statut rouge dans l'interface PR.

## Chaîne de dépendances

Toutes les actions GitHub utilisées dans `.github/workflows/` sont épinglées sur des tags de version vérifiés (pas de SHA de commit non vérifiable) — voir `docs/07-DEPLOIEMENT/README.md` pour le détail de la validation effectuée.
