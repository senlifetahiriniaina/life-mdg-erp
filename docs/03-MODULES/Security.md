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
- **Threats** (deux jeux de routes coexistent) : `threats` / `threats/{id}/whitelist|unwhitelist` (via `IncidentController`, ancien) **et** un CRUD complet `threat-indicators/*` avec `severity-summary` (via `ThreatIndicatorController`) — les deux ciblent le même modèle `ThreatIndicator`
- **Authentication Events** (`role:security-admin,admin,super-admin`, pas de Policy — le modèle n'a pas de `company_id`) : `auth-events`, `auth-events/summary`, `auth-events/suspicious`, `auth-events/{id}`
- **Trust Zones** : CRUD `trust-zones/*`, `trust-zones/{id}/assign-resource`
- **Service Identities** : CRUD `service-identities/*`, `{id}/rotate`, `{id}/revoke`
- **Rate Limits** (`role:security-admin,admin,super-admin`, cache-backé, pas de modèle Eloquent) : `rate-limits/status`, `rate-limits/reset`, `rate-limits/blocked-ips`, `rate-limits/block-ip`, `rate-limits/unblock-ip`
- **AI Assisted First** : `POST security/ai/assist` (`SecurityAiAssistController`)

`ComplianceControlController` (doublon 100% redondant de `ComplianceController::*Control`, confirmé par le fait que le vrai dashboard front appelle `compliance/controls` et non `compliance-controls`) a été **supprimé** cette session — il n'y a donc plus de doublon `compliance/controls/*` vs `compliance-controls/*` : un seul jeu de routes de conformité subsiste.

## Contrôleurs

`Modules/Security/app/Http/Controllers/` (10 fichiers — la majorité directement sous `Http/Controllers/`, pas sous `Api/`) :

| Contrôleur | Rôle |
|---|---|
| `ComplianceController` | Compliance controls/audits/violations — CRUD + `verify`/`complete` |
| `EncryptionController` | Clés de chiffrement, rotation/révocation, champs chiffrés déclarés |
| `IncidentController` | Incidents de sécurité + ancien endpoint `threats/*` |
| `ThreatIndicatorController` | CRUD complet des indicateurs de menace + `severity-summary` |
| `AuthenticationEventController` | Journal des tentatives de connexion |
| `TrustZoneController` | Zones de confiance réseau |
| `ServiceIdentityController` | Identités de service (comptes techniques) + rotation |
| `RateLimitController` | Statut/blocage IP du rate limiting |
| `Web\SecurityWebController` | Rend `Security/Index` (voir Vues) |
| `Api\SecurityAiAssistController` | Guidance IA contextuelle (AI Assisted First) |

**Correctif RBAC de cette session** : `ServiceIdentityController`/`TrustZoneController`/`RateLimitController`/`AuthenticationEventController` n'avaient **aucune** vérification d'autorisation ni scope `company_id` — n'importe quel utilisateur authentifié de n'importe quel tenant pouvait lister/faire tourner des identifiants de service (`client_secret` inclus dans la réponse), bloquer/débloquer des IP arbitraires et lire l'historique de connexion de tous les tenants. `ComplianceControlController`/`ThreatIndicatorController` avaient aussi zéro `authorize()` malgré des policies déjà écrites. Tout est désormais couvert (voir Permissions RBAC).

## Vues (Vue/Inertia)

Une seule page : `Modules/Security/resources/js/Pages/Index.vue`, rendue par `SecurityWebController::index()` (`GET /security`, `middleware(['auth', 'module:Security'])`). Aucune page équivalente n'existe au niveau racine (`resources/js/Pages/Security/`) — pas de collision de résolution Inertia à surveiller ici.

## Services

- `SecurityAuditService` — enregistre les événements d'authentification, calcule un résumé sécurité (`getSecuritySummary`) et un score de conformité (`getComplianceScore`) par `company_id`, avec mise en cache 5 min
- `ThreatDetectionService` — analyse une requête entrante (`analyzeRequest`) en combinant rate limiting et indicateurs de menace connus, crée des incidents
- `RateLimitService` — limitation de débit par utilisateur/IP/clé API (implémentation **propre à Security**, distincte de `Modules\Core\Services\RateLimitService` — voir Particularités)
- `GdprComplianceService` — volet conformité RGPD côté Security (distinct de `Modules\Core\Services\GdprService`)

## Permissions RBAC

Security possède désormais un bloc `security.*.*` dans `RolesAndPermissionsSeeder::MODULES` (`'security' => ['incident', 'audit', 'compliance', 'encryption', 'threat', 'identity', 'zone']`, ajouté cette session), qui produit les permissions standard `security.{resource}.{view-any,view,create,update,delete}` — utilisées par `ComplianceAuditPolicy`, `ComplianceControlPolicy`, `ComplianceViolationPolicy` (nouvelle), `EncryptionKeyPolicy`, `SecurityIncidentPolicy`, `ThreatIndicatorPolicy`, `ServiceIdentityPolicy` (nouvelle) et `TrustZonePolicy` (nouvelle). Deux verbes non-standard sont seedés à part via `SECURITY_EXTRA_PERMISSIONS` : `security.encryption.rotate` et `security.identity.rotate`.

Le rôle `security-admin` reçoit désormais le wildcard complet `security.*` en plus de `admin.audit.view`, `admin.security.manage`, `admin.users.view` et `auditlog.logs.view-any`/`.view`. `AuthenticationEventController`/`RateLimitController` (pas de modèle `company_id`-scopable) restent gérés par middleware de rôle direct (`role:security-admin,admin,super-admin`) plutôt que par Policy — même raisonnement que `TerritoryController` (CRM).

**Bug de comparaison corrigé cette session** : toutes les colonnes `company_id` des modèles Security sont `string(36)` (reliquat d'une conception multi-tenant par UUID) alors que `users.company_id` est `unsignedBigInteger` — la comparaison `$user->company_id === $model->company_id` utilisée par les 8 policies du module (28 comparaisons au total) comparait donc systématiquement un entier à une chaîne et échouait toujours silencieusement (fail-closed : personne, pas même `security-admin`, ne passait jamais le contrôle — pas une fuite, mais une régression de fonctionnalité réelle). Corrigé en castant les deux côtés en chaîne à chaque site de comparaison.

## Dépendances avec d'autres modules

- **Utilise** : `Modules\AuditLog\Traits\HasAuditLog` — la quasi-totalité des modèles Security (`AuthenticationEvent`, `SecurityIncident`, `ThreatIndicator`, `ComplianceControl`, `EncryptionKey`, `TrustZone`, `ServiceIdentity`…) l'utilisent pour tracer create/update/delete.
- **Utilisé par** : aucun autre module de l'application n'importe de classe `Modules\Security\*` — le module est un module « feuille », consommé uniquement via son API REST.

## Particularités du périmètre life-mdg-erp

- **Doublon de `RateLimitService`** avec Core : les deux implémentations (`Modules\Core\Services\RateLimitService`, fenêtre glissante avec cache Laravel dédié, et `Modules\Security\Services\RateLimitService`, limites par tier codées en `const LIMITS`) coexistent sans partager de code ni de configuration ; les endpoints `rate-limits/*` de Security pilotent uniquement l'implémentation Security.
- **Route legacy `threats/*` toujours présente** aux côtés de `threat-indicators/*` — les deux ciblent le même modèle `ThreatIndicator` ; contrairement au doublon `compliance-controls/*` (supprimé), celui-ci n'a pas été retiré cette session (`IncidentController::indexThreats`/`storeThreat`/`whitelistThreat`/`unwhitelistThreat` restent actifs), à documenter comme dette technique restante plutôt que fusionnée.
- **Correctif de champ** : `ThreatIndicatorController::severitySummary()` filtrait/groupait sur une colonne `severity` qui n'existe pas sur `security_threat_indicators` (la vraie colonne est `threat_level`) — corrigé cette session, l'endpoint provoquait une erreur SQL systématique auparavant.
- **Migration additive** (`2026_08_26_000001_patch_security_trust_zones_and_service_identities.php`) : `ServiceIdentityController`/`TrustZoneController` validaient/écrivaient des noms de champs (`name`, `client_id`, `client_secret`, `trust_level`, `ip_ranges`) qui ne correspondaient ni à la table réelle ni même au `$fillable` déjà correct de `TrustZone` — la migration ajoute les colonnes manquantes (`zone_type`, `cidr_blocks`, `device_policies`, `authentication_policies`, `trust_score_minimum`, `assigned_resources`).
