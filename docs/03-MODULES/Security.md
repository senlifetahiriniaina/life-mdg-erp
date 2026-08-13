# Security

## Rôle

`Modules/Security` centralise le suivi et la gouvernance de la sécurité applicative : événements d'authentification, incidents de sécurité, indicateurs de menace (threat intelligence), zones de confiance réseau, identités de service, gestion des clés de chiffrement et contrôles de conformité (SOX, HIPAA, PCI-DSS, GDPR). Il complète — sans les remplacer — les mécanismes de hardening embarqués directement dans Core (CSRF, XSS, rate limiting, sessions) : Security est davantage tourné vers l'observabilité et la gouvernance (incidents, audits, conformité) que vers la protection en temps réel des requêtes HTTP.

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `AuthenticationEvent` | `security_authentication_events` | Journal des tentatives de connexion (succès/échec, `trust_score`, `risk_factors`) |
| `SecurityIncident` | `security_incidents` | Incidents de sécurité déclarés, avec statut et sévérité |
| `IncidentResponse` | `security_incident_responses` | Actions de réponse associées à un incident |
| `ThreatIndicator` | `security_threat_indicators` | Indicateurs de menace (IP, hash, domaine…), avec liste blanche et expiration |
| `ComplianceControl` | `security_compliance_controls` | Contrôles de conformité par référentiel (SOX, HIPAA, PCI-DSS, GDPR) |
| `ComplianceAudit` / `ComplianceViolation` | — | Audits de conformité et violations détectées |
| `EncryptionKey` / `KeyRotationLog` / `EncryptedField` | `security_encryption_keys` | Clés de chiffrement, historique de rotation, champs chiffrés déclarés |
| `TrustZone` | `security_trust_zones` | Zones de confiance réseau et ressources qui y sont assignées |
| `ServiceIdentity` | — | Identités de service (comptes techniques) avec rotation de credentials |

## Endpoints principaux

Tous préfixés `/api/v1/security/`, protégés `auth:sanctum` (voir `Modules/Security/routes/api.php`) :

- **Compliance** : CRUD `compliance/controls`, `compliance/audits`, `compliance/violations`, plus `compliance/controls/{id}/verify` et `compliance/audits/{id}/complete`
- **Encryption** : CRUD `encryption/keys`, `encryption/keys/{id}/rotate|revoke`, `encryption/rotation-logs`, `encryption/encrypted-fields`
- **Incidents** : CRUD `incidents`, `incidents/{id}/investigate|resolve`, `incidents/{id}/responses`
- **Threats** (deux jeux de routes coexistent) : `threats` / `threats/{id}/whitelist|unwhitelist` (via `IncidentController`) **et** un CRUD complet `threat-indicators/*` avec `severity-summary` (via `ThreatIndicatorController`, ajouté ultérieurement)
- **Authentication Events** : `auth-events`, `auth-events/summary`, `auth-events/suspicious`, `auth-events/{id}`
- **Compliance Controls (dédié)** : `compliance-controls/*` avec `framework-summary` — doublon fonctionnel de `compliance/controls/*` via `ComplianceControlController`
- **Trust Zones** : CRUD `trust-zones/*`, `trust-zones/{id}/assign-resource`
- **Service Identities** : CRUD `service-identities/*`, `{id}/rotate`, `{id}/revoke`
- **Rate Limits** : `rate-limits/status`, `rate-limits/reset`, `rate-limits/blocked-ips`, `rate-limits/block-ip`, `rate-limits/unblock-ip`
- **AI Assisted First** : `POST security/ai/assist` (`SecurityAiAssistController`)

## Services

- `SecurityAuditService` — enregistre les événements d'authentification, calcule un résumé sécurité (`getSecuritySummary`) et un score de conformité (`getComplianceScore`) par `company_id`, avec mise en cache 5 min
- `ThreatDetectionService` — analyse une requête entrante (`analyzeRequest`) en combinant rate limiting et indicateurs de menace connus, crée des incidents
- `RateLimitService` — limitation de débit par utilisateur/IP/clé API (implémentation **propre à Security**, distincte de `Modules\Core\Services\RateLimitService` — voir Particularités)
- `GdprComplianceService` — volet conformité RGPD côté Security (distinct de `Modules\Core\Services\GdprService`)

## Permissions RBAC

Security n'a pas de bloc `security.*.*` dans `RolesAndPermissionsSeeder::MODULES`. Le contrôle d'accès repose sur :
- des policies dédiées (`ComplianceAuditPolicy`, `ComplianceControlPolicy`, `EncryptionKeyPolicy`, `SecurityIncidentPolicy`, `ThreatIndicatorPolicy`) appelées via `$this->authorize(...)` dans les contrôleurs ;
- la permission d'administration globale `admin.security.manage` (module `ADMIN_PERMISSIONS` de Core), attribuée au rôle `admin` (toutes permissions) et au rôle `security-admin` avec en plus `admin.audit.view`, `admin.users.view` et `auditlog.logs.view-any`/`auditlog.logs.view`.

## Dépendances avec d'autres modules

- **Utilise** : `Modules\AuditLog\Traits\HasAuditLog` — la quasi-totalité des modèles Security (`AuthenticationEvent`, `SecurityIncident`, `ThreatIndicator`, `ComplianceControl`, `EncryptionKey`, `TrustZone`, `ServiceIdentity`…) l'utilisent pour tracer create/update/delete.
- **Utilisé par** : aucun autre module de l'application n'importe de classe `Modules\Security\*` — le module est un module « feuille », consommé uniquement via son API REST.

## Particularités du périmètre life-mdg-erp

- Confirmé par le code : `ComplianceController::indexControls()` filtre par `auth()->user()->company_id`, alors qu'aucun modèle `App\Models\Company` n'existe dans ce périmètre trimmé (cf. section « Known gaps » du `CLAUDE.md` racine) — ces endpoints sont fonctionnellement bloqués tant que ce modèle n'est pas réintroduit.
- **Doublon de `RateLimitService`** avec Core : les deux implémentations (`Modules\Core\Services\RateLimitService`, fenêtre glissante avec cache Laravel dédié, et `Modules\Security\Services\RateLimitService`, limites par tier codées en `const LIMITS`) coexistent sans partager de code ni de configuration ; les endpoints `rate-limits/*` de Security pilotent uniquement l'implémentation Security.
- **Routes threat/compliance dupliquées** : `threats/*` (ancien, via `IncidentController`) et `threat-indicators/*` (nouveau CRUD complet, via `ThreatIndicatorController`) coexistent pour le même modèle `ThreatIndicator`, de même que `compliance/controls/*` et `compliance-controls/*` pour `ComplianceControl` — les deux jeux de routes sont actifs simultanément dans `routes/api.php`.
