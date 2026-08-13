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

Conformément aux principes **Compliance First** documentés dans `CLAUDE.md` :

- RBAC via `spatie/laravel-permission` (22 rôles, cf. `database/seeders/RolesAndPermissionsSeeder.php`)
- Journalisation d'audit (`Modules/AuditLog`)
- Chiffrement au repos AES-256-GCM pour les champs sensibles
- Webhooks signés HMAC
- Conformité RGPD/PDPL/OHADA/OWASP by design

## Analyse automatisée

Le dépôt exécute des scans de sécurité informationnels (non bloquants) via GitHub Actions :

- `security-scan` dans `ci.yml` — `composer audit` / `npm audit` à chaque push/PR
- `security-audit-scheduled.yml` — audit hebdomadaire complet (lundi 02h UTC)
- `supply-chain.yml` — scan CVE (Trivy), génération de SBOM, détection de secrets (TruffleHog), revue de dépendances sur PR

Ces checks remontent les résultats dans les résumés de workflow et les artefacts, mais ne bloquent pas les merges — voir `docs/09-RBAC-SECURITE/SECURITE.md` pour le détail des choix de configuration CI.

## Versions supportées

Une seule branche de développement est maintenue activement (`claude/life-mdg-erp-setup-aksh8m` puis la branche par défaut une fois mergée). Il n'y a pas de politique de rétro-portage de correctifs de sécurité vers d'anciennes versions taguées à ce stade du projet.
