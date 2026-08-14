# Politique de sécurité

## Signaler une vulnérabilité

Si vous découvrez une faille de sécurité dans Life MDG ERP, merci de **ne pas** ouvrir d'issue publique. Contactez l'équipe de maintenance directement (voir `CLAUDE.md` pour les coordonnées du mainteneur) avec :

- Une description de la vulnérabilité et de son impact potentiel
- Les étapes de reproduction
- La version/commit concerné

Nous accusons réception sous 72h et visons un correctif proportionné à la sévérité :

| Sévérité (CVSS) | Délai de réponse cible |
|---|---|
| Critique (9.0–10.0) | 24 heures |
| Élevée (7.0–8.9) | 7 jours |
| Modérée (4.0–6.9) | 30 jours |
| Faible (0.1–3.9) | 90 jours |

## Périmètre

Ce dépôt (`life-mdg-erp`) est un ERP Laravel 12 + Vue 3 à 27 modules. Le périmètre inclut le code applicatif (`app/`, `Modules/`, `resources/js/`), les migrations de base de données, et la configuration livrée dans le dépôt (`config/`, `.github/workflows/`). Les dépendances tierces (packages Composer/npm) doivent être signalées à leurs mainteneurs respectifs sauf si l'usage qu'en fait ce dépôt introduit une vulnérabilité spécifique.

## Dispositifs de sécurité en place

**État détaillé et sourcé (chaque ligne ci-dessous) : voir `docs/09-RBAC-SECURITE/STANDARDS-SECURITE-MADAGASCAR.md`.**

- RBAC via `spatie/laravel-permission` (22 rôles, cf. `database/seeders/RolesAndPermissionsSeeder.php`) — application au niveau contrôleur en cours de généralisation, la faille sur les données RH a été corrigée
- Journalisation d'audit partielle (`Modules/AuditLog`)
- Chiffrement au repos (AES-256-CBC, champs PII via `EncryptableTrait`) — le coffre-fort de secrets applicatif n'est pas fonctionnel (classe manquante), voir le document ci-dessus
- Webhooks signés HMAC (sortant réel ; entrant partiel)
- Conformité : cadre légal malgache détaillé (Loi 2014-038, Décret 2023-1541, CMIL, Convention de Malabo/Loi 2024-004, ANSSI-Madagascar) dans le document ci-dessus — plus rigoureux que la mention générique RGPD/PDPL/OHADA/OWASP précédemment affichée ici

## Analyse automatisée

Le dépôt exécute des scans de sécurité informationnels (non bloquants) via GitHub Actions :

- `security-scan` dans `ci.yml` — `composer audit` / `npm audit` à chaque push/PR
- `security-audit-scheduled.yml` — audit hebdomadaire complet (lundi 02h UTC)
- `supply-chain.yml` — scan CVE (Trivy), génération de SBOM, détection de secrets (TruffleHog), revue de dépendances sur PR

Ces checks remontent les résultats dans les résumés de workflow et les artefacts, mais ne bloquent pas les merges — voir `docs/09-RBAC-SECURITE/SECURITE.md` pour le détail des choix de configuration CI.

## Versions supportées

Une seule branche de développement est maintenue activement (`claude/life-mdg-erp-setup-aksh8m` puis la branche par défaut une fois mergée). Il n'y a pas de politique de rétro-portage de correctifs de sécurité vers d'anciennes versions taguées à ce stade du projet.
