# Security

## Rôle

`Modules/Security` centralise le suivi et la gouvernance de la sécurité applicative : événements d'authentification, incidents de sécurité, indicateurs de menace (threat intelligence), zones de confiance réseau, identités de service, gestion des clés de chiffrement et contrôles de conformité (SOX, HIPAA, PCI-DSS, GDPR). Il complète — sans les remplacer — les mécanismes de hardening embarqués directement dans Core (CSRF, XSS, rate limiting, sessions) : Security est davantage tourné vers l'observabilité et la gouvernance (incidents, audits, conformité) que vers la protection en temps réel des requêtes HTTP.

**Chantier 32.3 (audit approfondi 14 couches)** a fermé plusieurs écarts réels — voir chaque section ci-dessous pour le détail, et l'entrée `CLAUDE.md` correspondante pour la méthodologie complète.

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `AuthenticationEvent` | `security_authentication_events` | Journal des tentatives de connexion (succès/échec, `trust_score`, `risk_factors`) — **désormais réellement alimenté** (voir Services) |
| `SecurityIncident` | `security_incidents` | Incidents de sécurité déclarés, avec statut et sévérité |
| `IncidentResponse` | `security_incident_responses` | Actions de réponse associées à un incident |
| `ThreatIndicator` | `security_threat_indicators` | Indicateurs de menace (IP, hash, domaine…), avec liste blanche et expiration |
| `ComplianceControl` | `security_compliance_controls` | Contrôles de conformité par référentiel (SOX, HIPAA, PCI-DSS, GDPR) |
| `ComplianceAudit` | `compliance_audits` (sans préfixe `security_`, table créée par une migration ultérieure — cohabitation confirmée sans conflit) | Audit de conformité formel |
| `ComplianceViolation` | `security_compliance_violations` | Violation détectée sur un contrôle — **aucun code applicatif ne crée jamais de violation réelle** (voir Fake/Dead) |
| `EncryptionKey` | `security_encryption_keys` | Registre de clés de chiffrement (métadonnées + référence vault — jamais la clé elle-même) |
| `KeyRotationLog` | `key_rotation_logs` (sans préfixe) | Historique de rotation d'une clé |
| `EncryptedField` | `encrypted_fields` (sans préfixe) | Déclaration des colonnes marquées chiffrées + clé associée |
| `TrustZone` | `security_trust_zones` | Zones de confiance réseau et ressources qui y sont assignées |
| `ServiceIdentity` | `service_identities` (sans préfixe) | Identités de service (comptes techniques), clé privée jamais persistée en clair (seul un hash l'est) |

**Note de nommage** : 4 tables (`compliance_audits`, `key_rotation_logs`, `encrypted_fields`, `service_identities`) n'ont pas le préfixe `security_` des 8 autres — une incohérence de convention entre deux vagues de migrations (juin puis août), confirmée sans impact fonctionnel (chaque modèle pointe correctement vers sa vraie table, vérifié via `Schema::getColumnListing()`), laissée en l'état plutôt que renommée (le risque d'un renommage dépasse le bénéfice purement cosmétique).

## Endpoints principaux

Tous préfixés `/api/v1/security/`, protégés `auth:sanctum` **+ `module:Security` + `role:security-admin,admin,super-admin` sur tout le groupe** (Chantier 32.3 — voir RBAC) :

- **Compliance** : CRUD `compliance/controls`, `compliance/audits`, `compliance/violations` (lecture/mise à jour seulement — aucune création, voir Fake/Dead), plus `compliance/controls/{id}/verify` et `compliance/audits/{id}/complete`
- **Encryption** : CRUD `encryption/keys`, `encryption/keys/{id}/rotate|revoke`, `encryption/rotation-logs`, `encryption/encrypted-fields`
- **Incidents** : CRUD `incidents`, `incidents/{id}/investigate|resolve`, `incidents/{id}/responses`
- **`GET summary`** (nouveau, Chantier 32.3) : agrégat unique (`open_incidents`, `compliance_score`, `auth_failures_24h`, `critical_threats`) consommé par `Index.vue`, remplace 2 appels précédemment recalculés côté client
- **Threats/Threat Indicators** — un seul jeu de routes désormais (`threat-indicators/*`, CRUD complet + `severity-summary` + `{id}/whitelist|unwhitelist`) : les anciennes routes doublon `threats`/`threats/{id}/whitelist|unwhitelist` (via `IncidentController`, confirmées mortes — zéro appelant réel hors leur propre test) ont été **supprimées** cette session, leur capacité `whitelist`/`unwhitelist` portée sur `ThreatIndicatorController`
- **Authentication Events** (`role:security-admin,admin,super-admin`, pas de Policy — le modèle n'a pas de `company_id`, cross-tenant par design — voir Particularités) : `auth-events`, `auth-events/summary`, `auth-events/suspicious`, `auth-events/{id}`
- **Trust Zones** : CRUD `trust-zones/*`, `trust-zones/{id}/assign-resource`
- **Service Identities** : CRUD `service-identities/*`, `{id}/rotate`, `{id}/revoke`
- **Rate Limits** (`role:security-admin,admin,super-admin`, cache-backé, pas de modèle Eloquent) : `rate-limits/status`, `rate-limits/reset`, `rate-limits/blocked-ips` (désormais une vraie liste, voir Particularités), `rate-limits/block-ip` (désormais réellement appliqué, voir Particularités), `rate-limits/unblock-ip`
- **AI Assisted First** : `POST security/ai/assist` (`SecurityAiAssistController`) — délibérément laissé hors du gate `role:security-admin,...` (même convention que les autres modules : le panneau guide qui regarde la page, pas seulement les admins), mais son résultat est actuellement toujours vide (voir Particularités)

## Contrôleurs

`Modules/Security/app/Http/Controllers/` (11 fichiers) :

| Contrôleur | Rôle |
|---|---|
| `ComplianceController` | Compliance controls/audits/violations — CRUD + `verify`/`complete` |
| `EncryptionController` | Clés de chiffrement, rotation/révocation, champs chiffrés déclarés |
| `IncidentController` | Incidents de sécurité (l'ancien endpoint `threats/*` a été supprimé cette session) |
| `ThreatIndicatorController` | CRUD complet des indicateurs de menace + `severity-summary` + `whitelist`/`unwhitelist` (portés depuis `IncidentController`) |
| `AuthenticationEventController` | Journal des tentatives de connexion |
| `TrustZoneController` | Zones de confiance réseau |
| `ServiceIdentityController` | Identités de service (comptes techniques) + rotation |
| `RateLimitController` | Statut/blocage IP du rate limiting |
| `SecurityDashboardController` (nouveau, Chantier 32.3) | `GET summary` — délègue à `SecurityAuditService::getSecuritySummary()`, désormais son seul appelant réel |
| `Web\SecurityWebController` | Rend `Security/Index` (voir Vues) |
| `Api\SecurityAiAssistController` | Guidance IA contextuelle (AI Assisted First) |

## Vues (Vue/Inertia)

Une seule page : `Modules/Security/resources/js/Pages/Index.vue`, rendue par `SecurityWebController::index()` (`GET /security`, `middleware(['auth', 'module:Security', 'role:security-admin,admin,super-admin'])` — le gate de rôle est nouveau, Chantier 32.3). Ses statistiques viennent désormais du seul `GET /security/summary` plutôt que d'être recalculées côté client depuis 2 appels supplémentaires (`auth-events/summary`, `compliance/controls?per_page=100`) ; une 4ᵉ carte (« Menaces critiques actives ») a été ajoutée puisque cette donnée n'était jamais réellement calculée avant ce chantier. Aucune page équivalente n'existe au niveau racine (`resources/js/Pages/Security/`) — pas de collision de résolution Inertia à surveiller ici.

## Services

- `SecurityAuditService` — `recordAuthEvent()` a désormais un vrai producteur (voir ci-dessous) ; `getSecuritySummary()`/`getComplianceScore()` ont désormais un vrai appelant (`SecurityDashboardController::summary()`) ; `critical_threats` n'est plus une valeur codée en dur (`0`) mais une vraie requête sur `ThreatIndicator` (critique, non liste blanche, non expirée) ; `getComplianceScore()` retourne désormais `null` (pas `0.0`) quand aucun contrôle n'existe, distinguant « rien d'enregistré » de « 0% conforme »
- `ThreatDetectionService` — `isKnownThreatIp()` (le seul chemin réellement appelé par le middleware WAF racine `app/Http/Middleware/RequestInspectionMiddleware.php`, hors périmètre de ce module) vérifie désormais aussi la clé de cache `security.blocked_ip.{ip}` écrite par `RateLimitController::blockIp()` — avant ce chantier, un blocage manuel d'IP n'était **jamais réellement appliqué nulle part** (voir Particularités). `analyzeRequest()`/`createIncident()`/`addThreatIndicator()` restent sans appelant réel (voir Fake/Dead)
- `RateLimitService` — limitation de débit par utilisateur/IP/clé API (implémentation **propre à Security**, distincte de `Modules\Core\Services\RateLimitService` — voir Particularités)
- ~~`GdprComplianceService`~~ — **supprimé cette session** (Chantier 32.3) : zéro appelant réel nulle part dans l'app (pas même ses propres tests, confirmé par grep), méthodes `processErasureRequest()`/`exportUserData()` factices (`uniqid()`, aucune persistance réelle — exactement le motif « mort/factice » décrit dans la méthodologie d'audit), entièrement superseded par le vrai pipeline GDPR de `Modules\Core` (`GdprController`/`ImportController`/`AnonymizeUserJob`/`SarExportJob`, confirmé réel au Chantier 32.1)

### `RecordAuthenticationEvent` (nouveau, Chantier 32.3) — active `AuthenticationEvent`

`SecurityAuditService::recordAuthEvent()` était une méthode réelle sans aucun producteur — `security_authentication_events` n'a jamais été alimentée par un vrai login/logout/échec avant ce chantier (confirmé par grep, zéro appelant). Un nouveau `Modules\Security\Providers\EventServiceProvider` (enregistré depuis `SecurityServiceProvider::register()`) écoute les vrais événements Laravel `Login`/`Logout`/`Failed` via un nouveau `Modules\Security\Listeners\RecordAuthenticationEvent`, coexistant avec `Modules\Core\Listeners\AuditAuthListener` (qui alimente `core_audit_logs`, un journal générique différent — Laravel fusionne les `$listen` de chaque provider enregistré, les deux tournent indépendamment). Vérifié empiriquement de bout en bout via une vraie requête `POST /login` (`Auth::attempt()`, la route web session-based) — **et non** via `app/Http/Controllers/Api/JwtAuthController` (le chemin de connexion JWT de l'API), confirmé ne jamais déclencher `Auth::attempt()`/les événements Laravel standard du tout, un écart hors périmètre de ce module documenté ici mais non corrigé (fichier racine).

`trust_score`/`risk_factors`/`device_info` restent `null` — un vrai score de risque par connexion serait une nouvelle logique métier zero-trust jamais spécifiée nulle part dans l'app, documenté comme un écart plutôt que deviné.

## Permissions RBAC

Security possède un bloc `security.*.*` dans `RolesAndPermissionsSeeder::MODULES` (`'security' => ['incident', 'audit', 'compliance', 'encryption', 'threat', 'identity', 'zone']`), qui produit les permissions standard `security.{resource}.{view-any,view,create,update,delete}` — utilisées par `ComplianceAuditPolicy`, `ComplianceControlPolicy`, `ComplianceViolationPolicy`, `EncryptionKeyPolicy`, `SecurityIncidentPolicy`, `ThreatIndicatorPolicy`, `ServiceIdentityPolicy` et `TrustZonePolicy`. Deux verbes non-standard sont seedés à part via `SECURITY_EXTRA_PERMISSIONS` : `security.encryption.rotate` et `security.identity.rotate`. Les 8 policies **s'auto-découvrent réellement** via le guesser par défaut de Laravel (`Modules\Security\Models\X` → `Modules\Security\Policies\XPolicy`, confirmé empiriquement via `Gate::getPolicyFor()`) — aucun `registerPolicies()` n'est nécessaire ni présent dans `SecurityServiceProvider`, contrairement à la plupart des autres modules de cette session (dont les modèles ne vivent pas tous à un seul niveau sous `Models\`).

**Trouvaille principale de ce chantier — trop de rôles avaient accès** : `manager` et `employee` (les deux rôles les plus largement attribués de l'app) recevaient silencieusement **tout** `security.*` via la boucle générique `MODULES`/`$allPermissions` — y compris `security.encryption.rotate`/`security.identity.rotate` (créer/faire tourner des identifiants de service à service), la création/modification d'incidents de sécurité, de contrôles/audits de conformité, etc. — alors que le module lui-même ne gate historiquement que 2 de ses 8 sous-ressources (`auth-events`, `rate-limits`) à `role:security-admin,admin,super-admin`, révélant l'audience réellement voulue. Corrigé en deux temps (défense en profondeur) : (1) `RolesAndPermissionsSeeder` exclut désormais `security.*` du grant générique `manager`/`employee` ; (2) **tout** le groupe de routes `Modules/Security/routes/api.php` porte désormais `module:Security` + `role:security-admin,admin,super-admin` (les gates internes déjà présents sur `auth-events`/`rate-limits` sont conservés, redondants mais explicites). `admin`/`security-admin` ne sont pas affectés (grants dédiés inchangés).

**Bug de comparaison déjà corrigé (Chantier 8.3cs), re-confirmé empiriquement cette session** : toutes les colonnes `company_id` des modèles Security historiques sont `string(36)` (reliquat d'une conception multi-tenant par UUID) alors que `users.company_id` est `unsignedBigInteger` — la comparaison `(string) $user->company_id === (string) $model->company_id` utilisée par les 8 policies du module reste correcte ; verrouillée par un nouveau test de bout en bout HTTP par ressource (`Chantier32SecurityDeepAuditTest`, 8 tests cross-tenant) plutôt que de faire confiance à la note du changelog seule — méthodologie explicitement demandée pour ce chantier. Le même motif de cast a été appliqué à un nouveau site de comparaison introduit cette session (`EncryptionController::storeEncryptedField()`'s `Rule::exists(...)->where('company_id', ...)`).

## Sécurité approfondie (Chantier 32.3)

- **`EncryptionController::storeEncryptedField()`** validait l'existence de `encryption_key_id` n'importe où dans la base, jamais qu'elle appartienne à la société de l'appelant — un security-admin de la société A pouvait référencer une clé de la société B, et puisque `EncryptionKey::encryptedFields()` n'a aucun scope de société, l'admin de la société B consultant **sa propre** clé (une vue par ailleurs correctement autorisée) verrait alors fuiter le `table_name`/`column_name` de la société A dans `$key->encryptedFields`. Corrigé avec `Rule::exists(...)->where('company_id', ...)`.
- **`RateLimitController::blockIp()`/`unblockIp()`** écrivaient une entrée de cache (`security.blocked_ip.{ip}`) que **rien nulle part** ne lisait jamais — le message de succès (« IP X a été bloquée ») était une fausse affirmation. Corrigé en câblant `ThreatDetectionService::isKnownThreatIp()` (déjà le seul point de lecture réel, via le middleware WAF racine) sur cette même clé — sans modifier ce fichier racine, hors périmètre de ce module.
- **`RateLimitController::blockedIps()`** retournait inconditionnellement `[]` avec un message suggérant un `SCAN` Redis manuel — inutilisable puisque le driver de cache réel de cette app est `file` (`config/cache.php`), qui ne supporte aucun balayage par motif. Un index compagnon (une seule clé de cache contenant la liste des IP, maintenue par `blockIp()`/`unblockIp()`, purgée des entrées expirées à la lecture) fonctionne identiquement sur tout driver de cache.
- **`ThreatIndicatorController::store()`** n'avait aucune règle de validation d'unicité sur `indicator_value` malgré une vraie contrainte unique en base (`2026_08_19_000002_add_unique_index_to_security_threat_indicators.php`) — un doublon levait une `QueryException` brute (500) plutôt qu'un 422 propre. Corrigé (`unique:security_threat_indicators,indicator_value`).

## Fake/Dead (couche 9)

- **`GdprComplianceService`** — supprimé (voir Services).
- **Routes legacy `threats`/`threats/{id}/whitelist|unwhitelist`** (`IncidentController`) — supprimées, confirmées dupliquées de `ThreatIndicatorController` (zéro appelant réel hors leur propre fichier de test, un enum `indicator_type` différent — `ip_address` vs `ip` — de celui que le vrai frontend utilise). La capacité `whitelist`/`unwhitelist` (la seule non-redondante de l'ancien contrôleur) a été portée sur `ThreatIndicatorController` plutôt que perdue.
- **`ComplianceViolation` — un registre réel sans aucun producteur** : le modèle/table/policy/contrôleur (index/show/update) sont réels et corrects, mais **aucun code applicatif de ce module ne crée jamais de violation** (confirmé par grep — zéro `ComplianceViolation::create()` en dehors des tests/factories). Ni activé ni supprimé cette session : quel événement métier devrait déclencher une violation (un contrôle passant à `implementation_status = 'failed'` ? un audit avec `controls_non_compliant > 0` ?) est une vraie décision produit non spécifiée ailleurs dans l'app — documenté comme écart plutôt que deviné.
- **`SecurityAuditService::getFailedLoginAttempts()`** reste sans appelant réel malgré son nom laissant penser à un usage dans une logique de verrouillage de compte — cette logique existe réellement ailleurs (`User::recordFailedLoginAttempt()`/`isAccountLocked()`, racine, hors périmètre) et n'utilise pas cette méthode.
- **`ThreatDetectionService::analyzeRequest()`/`createIncident()`/`addThreatIndicator()`** restent sans appelant réel — seul `isKnownThreatIp()` (appelé par le WAF racine) a un vrai producteur. Activer `createIncident()` depuis le WAF (créer un vrai `SecurityIncident` quand une IP connue est bloquée hors mode fantôme) serait une extension naturelle, mais nécessiterait de modifier `app/Http/Middleware/RequestInspectionMiddleware.php`, un fichier racine hors du périmètre de ce module — documenté pour un futur chantier plutôt que fait ici.

## Dépendances avec d'autres modules

- **Utilise** : `Modules\AuditLog\Traits\HasAuditLog` — la quasi-totalité des modèles Security l'utilisent pour tracer create/update/delete ; `Modules\AI\Services\AiContextualAssistantService` (voir Particularités — actuellement toujours vide pour ce module).
- **Utilisé par** : aucun autre module de l'application n'importe de classe `Modules\Security\*` — le module reste un module « feuille », consommé uniquement via son API REST.

## Particularités du périmètre life-mdg-erp

- **Doublon de `RateLimitService`** avec Core : les deux implémentations (`Modules\Core\Services\RateLimitService`, fenêtre glissante avec cache Laravel dédié, et `Modules\Security\Services\RateLimitService`, limites par tier codées en `const LIMITS`) coexistent sans partager de code ni de configuration ; les endpoints `rate-limits/*` de Security pilotent uniquement l'implémentation Security.
- **`AuthenticationEvent` cross-tenant par design, évalué et laissé tel quel** : `security_authentication_events` n'a pas de `company_id` — n'importe quel `security-admin` voit l'historique de connexion de toutes les sociétés. Défendable si `security-admin` est réellement un rôle de plateforme (comme le suggère sa place dans le docblock de `RolesAndPermissionsSeeder`, aux côtés de `system-admin`/`billing-admin`) — mais les rôles Spatie de cette app sont globaux, pas scopés par société, et `app/Http/Controllers/Admin/RoleManagementController::assignRole()` (racine, hors périmètre) ne restreint l'attribution de rôle qu'à `admin`/`super-admin`, sans empêcher un `admin` d'une société quelconque de s'auto-attribuer (ou d'attribuer à un employé) `security-admin` — flaggé pour un futur chantier plutôt que résolu ici, une question d'architecture RBAC plus large que ce seul module.
- **`AiContextualAssistantService::supportedModules()` ne connaît pas `Security`** — `SecurityAiAssistController`/`Index.vue` (`useAiAssistant('Security', 'view_dashboard')`) existent et sont réellement appelés, mais délèguent à `Modules\AI\Services\AiContextualAssistantService::getGuidance()`, qui ne référence `'Security'` nulle part (ni dans `supportedModules()`, ni dans la table de repli statique) — confirmé empiriquement (`enabled: false`, tous les champs vides à chaque appel réel). Exactement le même motif déjà trouvé et corrigé pour `Strategy` au Chantier 30, mais **`Modules/AI` est hors du périmètre fichier de ce chantier** (module audité concurremment par un autre agent) — non corrigé ici, flaggé avec la même sévérité que la trouvaille Strategy d'origine.
- **`ComplianceControlController` (doublon)** : déjà supprimé lors d'une session précédente — confirmé toujours absent, un seul jeu de routes `compliance/controls/*` subsiste.
- **Migration additive** (`2026_08_26_000001_patch_security_trust_zones_and_service_identities.php`) : toujours en place, inchangée cette session.
