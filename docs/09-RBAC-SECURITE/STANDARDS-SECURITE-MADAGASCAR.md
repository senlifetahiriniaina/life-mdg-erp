# Standards de sécurité — Madagascar

**Ce document est la source de vérité** pour toute affirmation de sécurité et de conformité de Life MDG ERP. `SECURITY.md` (racine) et `docs/09-RBAC-SECURITE/SECURITE.md` pointent ici plutôt que de reformuler son contenu.

**Portée** : revue de code statique (lecture directe du dépôt + un agent d'exploration dédié) et corrections mécaniques appliquées dans la foulée. Ce n'est **pas** un test d'intrusion (pentest) en conditions réelles — aucune instance déployée n'existe dans le périmètre de cette revue. Un pentest live nécessiterait une cible réelle, un périmètre d'autorisation écrit et des outils dédiés hors de cet environnement.

Dernière mise à jour : cette itération a corrigé les six éléments listés en section 5 (« Corrigé dans cette itération ») ; tout le reste de ce document décrit l'état constaté avant/après ces corrections, avec la distinction clairement marquée.

---

## 1. Cadre légal — Madagascar

Life MDG ERP cible en priorité les entreprises malgaches et traite des données personnelles sensibles (RH : CIN, passeport, coordonnées bancaires ; CRM : contacts clients). Le cadre légal applicable :

| Texte | Statut | Ce qu'il faut en retenir |
|---|---|---|
| **Loi n° 2014-038** relative à la protection des données à caractère personnel (adoptée 2015) | **En vigueur depuis 2015**, mais non appliquée pendant une décennie faute de décret d'application et d'autorité de contrôle opérationnelle | C'est le texte substantiel — obligations de licéité du traitement, droits des personnes concernées (accès, rectification, suppression), notification en cas de violation |
| **Décret n° 2023-1541** du 6 décembre 2023 | En vigueur | Organise enfin les attributions et le fonctionnement de la CMIL (Commission Malagasy de l'Informatique et des Libertés), l'autorité de contrôle prévue par la loi de 2014 |
| **Manuel de procédures de la CMIL** — délibéré le 17 octobre 2025, publié décembre 2025 | **En vigueur depuis fin 2025** | **C'est le vrai jalon « depuis 2025 »** : la CMIL ne devient opérationnellement réelle qu'à cette date. Le risque de conformité PDPL pour un ERP malgache traitant des données RH/clients devient donc **concret maintenant**, pas avant — désignation de DPO, processus de demande d'accès/suppression, notification de violation doivent être traités comme un risque de conformité actif, pas théorique. |
| **Convention de Malabo** (Union Africaine — cybersécurité + protection des données), ratifiée par Madagascar via la **Loi n° 2024-004** (2024), confirmée par décision de la Haute Cour Constitutionnelle du 10 juillet 2024 | En vigueur | Cadre continental de référence, complète la loi 2014-038 |
| **ANSSI-Madagascar** (Agence Nationale de la Sécurité des Systèmes d'Information) | Autorité nationale de cybersécurité, aux côtés de la CMIL | Point de contact pour les incidents de sécurité majeurs |
| **Loi n° 2014-006** relative à la lutte contre la cybercriminalité | **En vigueur dans sa version 2014, non réformée** — une refonte est **en projet uniquement** : un projet de loi a été adopté en Conseil des ministres en juillet 2026 mais **n'est pas promulgué**. | Ne jamais citer la refonte comme du droit positif actuel. La loi 2014-006 originale reste la référence en vigueur pour toute affirmation présente ; la refonte est « à surveiller », à réévaluer lors d'une prochaine revue. |

**Hors périmètre** : OHADA/SYSCOHADA (normes comptables) est traité séparément dans le module `Accounting`, sans lien avec la sécurité applicative — non retraité ici.

---

## 2. Modèle de menace : poste de travail non fiable

### Limite éthique (à respecter dans toute évolution de ce document)

Ce document ne fournit et ne fournira **aucune indication permettant de faire fonctionner ou de maintenir un logiciel piraté/cracké en sécurité**. Un poste non fiable — qu'il s'agisse d'un OS sans licence, d'un logiciel cracké, d'un poste public/partagé ou d'un poste personnel (BYOD) mal maîtrisé — est traité uniquement comme une **entrée du modèle de menace**, exactement comme le serait un poste infecté par un malware d'origine quelconque ou un OS non patché. Les mitigations ci-dessous sont **identiques quelle que soit la raison** pour laquelle le poste n'est pas fiable — elles durcissent le serveur, pas le poste.

### Recommandation opérationnelle (hors périmètre applicatif)

> **Standardiser sur des systèmes d'exploitation et logiciels sous licence sur les postes de travail accédant à l'ERP.** Un OS ou logiciel piraté/cracké est aujourd'hui l'un des vecteurs de compromission les plus documentés (les cracks embarquent fréquemment des keyloggers, chevaux de Troie d'accès distant ou voleurs d'identifiants), et son usage constitue par ailleurs un risque juridique propre, indépendant de la sécurité de l'ERP lui-même. Cette recommandation relève d'une politique interne de poste de travail et de la responsabilité de l'organisation cliente — elle ne relève pas d'un contrôle applicatif que Life MDG ERP peut imposer techniquement, d'où son traitement en tant qu'hypothèse de menace plutôt qu'en tant que fonctionnalité à développer.

### Tableau menace → mitigation

| Menace (poste non fiable) | Mitigation côté serveur | État |
|---|---|---|
| Détournement de session / rejeu d'un jeton volé depuis un poste compromis | Empreinte d'appareil (device fingerprinting), détection de hijack, limite de sessions concurrentes | **Réel et actif après cette itération** — `SessionSecurityService` (voir §4.10) |
| Vol d'identifiants via keylogger embarqué dans un logiciel cracké | Verrouillage de compte après échecs répétés (déjà réel sur le login principal, **corrigé sur le second endpoint JWT dans cette itération**), MFA | Verrouillage : réel. MFA : réel mais limité aux rôles admin/super-admin (backlog : élargissement) |
| Interception réseau / vol de cookie (MITM) sur un réseau non fiable | Cookie de session forcé HTTPS-only (**corrigé dans cette itération**), HSTS | Réel après correction |
| Rejeu de requêtes / falsification de requêtes intersites | CSRF (comportement Laravel 12 par défaut), rate limiting par endpoint | Déjà réel |
| Exfiltration de données PII via un compte compromis de faible privilège | Application du RBAC au niveau contrôleur (au minimum pour les données RH — **corrigé dans cette itération**) | Réel sur HR ; backlog pour généraliser à l'ensemble des contrôleurs (voir §5) |

---

## 3. État réel du dispositif de sécurité

Honnête, pas la version marketing précédemment affichée dans `SECURITY.md`/`CLAUDE.md`. Étiquettes : **Réel** (fonctionne tel que documenté), **Partiel** (fonctionne en partie ou avec une limite notable), **Mort** (code présent mais dépendance manquante — plante à l'exécution), **Inactif** (code présent et fonctionnel mais jamais appelé/enregistré).

### 3.1 Chiffrement au repos — Partiel

Le cipher réellement utilisé est **AES-256-CBC** (`config/app.php`), pas AES-256-GCM comme annoncé précédemment. 34 modèles utilisent correctement `App\Traits\EncryptableTrait`/les casts `encrypted` de Laravel sur de vrais champs PII (CIN, passeport, téléphone, email, coordonnées bancaires dans `Modules/HR/app/Models/Employee.php`, et des champs équivalents dans Accounting/BI/Calendar/Integration/Logistics/Settings/Setup).

Le « coffre-fort de secrets » annoncé (`Modules/Core/app/Services/SecretsService.php`, `SecretRotationManager`, `SecretAccessControl`) est **mort** : il dépend de `Modules\Core\Services\EncryptionService`, une classe qui n'existe nulle part dans le dépôt. Toute résolution de ces services plante. Les modèles `Modules/Security/app/Models/EncryptionKey.php`/`EncryptedField.php` sont des modèles de métadonnées inertes référençant un `App\Models\Company` qui n'a jamais été créé.

### 3.2 Journalisation d'audit — Partiel (corrigé partiellement dans cette itération)

`Modules\AuditLog\Traits\HasAuditLog` (utilisé sur ~208 modèles) écrivait vers `Log::channel('audit')`, qui n'était **jamais défini** dans `config/logging.php` — l'exception était avalée silencieusement par un `catch(\Throwable){}`. **Ce channel a été ajouté dans cette itération** (`config/logging.php`), donc les 208 modèles produisent désormais réellement une trace au lieu de silencieusement ne rien écrire.

Le trail persisté en base (table `audit_logs`, via `AuditService::log()`) ne couvre qu'une douzaine de points d'appel réels (dont les événements d'authentification — login/logout/échec/verrouillage — fonctionnels). Les deux mécanismes restent architecturalement disjoints ; les consolider est un chantier plus large, hors périmètre de cette itération (voir §5).

### 3.3 Webhooks — Partiel

Sortant : signature HMAC-SHA256 réelle et fonctionnelle (`IntegrationService`, `HttpActionHandler`). Entrant (workflow) : vérifiée HMAC uniquement **si un secret est configuré** sur le déclencheur — sinon la charge utile est acceptée sans vérification. Les endpoints publics de fédération (`/api/v1/federation/*`) sont censés être protégés par `App\Http\Middleware\VerifyFederationSignature`, une classe qui **n'existe pas** — ces routes échouent (500) sur toute requête au lieu d'être protégées. C'est un échec fermé accidentel, pas une protection par conception. Les connecteurs Shopify/Wave contiennent du code de vérification HMAC correct mais mort (jamais appelé, aucune route n'existe pour recevoir leurs webhooks).

### 3.4 RBAC — Partiel (deux failles corrigées dans cette itération)

22 rôles corrects dans le seeder (`database/seeders/RolesAndPermissionsSeeder.php`), 77 classes Policy avec permissions granulaires correctes. Mais seulement ~13 % des contrôleurs API appellent `authorize()`.

**Faille corrigée dans cette itération** : `app/Providers/AppServiceProvider.php` enregistrait `Employee::class => App\Policies\EmployeePolicy::class` — une coquille vide qui, via `BaseErpPolicy`, autorisait **tout utilisateur authentifié** à lire/modifier/supprimer les données PII des employés, sans aucun `authorize()` appelé dans le contrôleur. Repointé vers `Modules\HR\Policies\EmployeePolicy` (la vraie policy, déjà correcte et déjà alignée sur les permissions du seeder) + ajout des appels `authorize()` sur `index/show/store/update/destroy/export/byDepartment/metrics`.

**Découverte connexe corrigée dans la foulée** : `EmployeeController` étendait `Illuminate\Routing\Controller` (base Laravel brute, sans le trait `AuthorizesRequests`) au lieu de `App\Http\Controllers\Controller` — `$this->authorize()` aurait fatalement échoué (`BadMethodCallException`) sans ce correctif. **124 contrôleurs dans le dépôt ont la même mauvaise classe de base** ; seul `EmployeeController` a été corrigé dans cette itération (voir §5, backlog).

### 3.5 Session / authentification — Partiel (deux failles corrigées dans cette itération)

**Corrigé** : `SESSION_SECURE_COOKIE` n'était jamais défini dans `.env.example` (`config/session.php` le lit sans valeur par défaut → cookie non forcé HTTPS). Ajouté avec `true`.

**Corrigé** : un second point de login existait — `POST /api/v1/auth/jwt/login` (`JwtAuthController`) — **sans aucun throttle ni verrouillage de compte**, contrairement au login principal (`AuthController::login()`, protégé par `throttle:auth` + verrouillage après échecs répétés). C'était un contournement par force brute de la protection appliquée au login principal. Corrigé : mêmes appels `isAccountLocked()`/`recordFailedLoginAttempt()`/`resetLoginAttempts()` + `throttle:auth` sur la route.

Restant en l'état (backlog, voir §5) : 2FA TOTP réelle et fonctionnelle mais obligatoire seulement pour admin/super-admin ; `JWT_SECRET` réutilise `APP_KEY` (réutilisation de clé cryptographique entre deux usages distincts).

### 3.6 Rate limiting — globalement solide

Limiteurs nommés bien conçus (`api`, `auth`, `ai`, `sync`, `simple_get`, `complex_get`, `create_post`, `expensive`, `secrets`, `webhook`) + throttle API global (60/min). La seule brèche connue (login JWT) est corrigée dans cette itération (§3.5).

### 3.7 CSRF — Réel

Comportement Laravel 12 par défaut, aucune route exclue.

### 3.8 En-têtes de sécurité / CSP — Réel

`App\Http\Middleware\SecurityHeaders`, branché globalement (web + api) : X-Content-Type-Options, X-Frame-Options, X-XSS-Protection, Referrer-Policy, Permissions-Policy, HSTS conditionnel, CSP à nonce pour les réponses non-API. Un doublon plus élaboré (`Modules/Core/app/Services/SecurityHeadersService.php`) existe mais n'est jamais appelé — code mort, cosmétique, sans impact.

### 3.9 Validation des entrées — Réel (avec une note de nommage trompeur)

`Modules/Validation` **n'est pas** un framework de validation d'entrées malgré son nom — c'est un moteur de workflow d'approbation (`ApprovalRequest`/`ApprovalWorkflow`/etc.), sans rapport. La validation d'entrée réelle se fait de façon standard via des classes `FormRequest` par module — correct, pas une lacune de sécurité en soi, juste une source de confusion pour un futur lecteur. Les services XSS/encodage de sortie (`XssPreventionService`, `OutputEncodingService`, `HtmlPurifierService`) existent avec une vraie logique mais ne sont jamais appelés dans le pipeline requête/réponse — code mort, pas de sanitisation active en dehors de l'échappement Blade/Vue par défaut.

### 3.10 Détection d'anomalie de session/appareil — Corrigé dans cette itération

C'était l'élément le plus pertinent pour le modèle de menace « poste non fiable » (§2). `Modules/Core/app/Services/SessionSecurityService.php` — empreinte d'appareil, détection de hijack, limite de sessions concurrentes (3 par défaut), gestion du timeout d'inactivité avec période de grâce — était **entièrement codé mais 100 % inactif** avant cette itération.

Corrigé :
- `createSession()` appelé aux 3 points réels d'émission de jeton Sanctum (`AuthController::login()`/`register()`, `TwoFactorController::verify()`), clé sur l'ID du jeton Sanctum (le flux d'authentification étant du bearer-token sans état, pas une session PHP classique).
- Nouveau middleware `App\Http\Middleware\SanctumSessionSecurity` (alias `session.security`), qui appelle `validateSession()` par requête sur le même principe, appliqué pour l'instant aux routes du module HR (`Modules/HR/routes/api.php`) — le périmètre le plus sensible (données PII), et déployé comme référence pour une extension aux autres modules (backlog, §5).
- Clés de configuration manquantes ajoutées à `config/session.php` (`session_timeout`, `idle_timeout`, `concurrent_session_limit`, `fingerprinting_enabled`, etc.), désormais réglables par environnement via des variables `env()`.
- Effet de bord attendu et acceptable : les jetons déjà émis avant ce déploiement n'ont pas d'enregistrement `SessionEnhanced` correspondant et recevront un 419 à leur première requête sur une route protégée — forçant une reconnexion unique. Ce comportement fail-closed est identique à celui déjà en place pour les sessions web à état (`SessionSecurityMiddleware`).
- Non couvert : le flux JWT (`JwtAuthController`) appelle désormais `createSession()` à l'émission (valeur d'audit/limite de sessions concurrentes), mais **aucune validation par requête n'est câblée** pour ce flux — le middleware `JwtAuthenticate` qui vérifierait ces jetons sur des routes protégées existe mais n'est enregistré nulle part ; en pratique, aucune route n'utilise l'authentification JWT aujourd'hui (toutes les routes protégées utilisent `auth:sanctum`). Activer `JwtAuthenticate` est hors périmètre de cette itération.

### 3.11 Citations légales — Corrigé dans cette itération

Aucune citation légale malgache précise n'existait auparavant dans le dépôt — seulement « RGPD/PDPL/OHADA/OWASP » générique et non sourcé. Ce document remplace cette ligne par le cadre légal détaillé et sourcé de la section 1.

---

## 4. Standards à appliquer désormais

### Corrigé dans cette itération

1. Faille RBAC `EmployeeController` (mauvaise policy enregistrée + mauvaise classe de base empêchant `authorize()`) — voir §3.4.
2. Contournement force brute du login JWT (`throttle:auth` + verrouillage de compte) — voir §3.5.
3. `SESSION_SECURE_COOKIE` non défini — voir §3.5.
4. Activation de `SessionSecurityService` sur le flux Sanctum + module HR — voir §3.10.
5. Canal de log `audit` manquant — voir §3.2.
6. Citation légale malgache précise dans ce document — voir §1 et §3.11.

### Backlog priorisé (jugement produit/métier, non codé dans cette itération)

| Item | Pourquoi backlog |
|---|---|
| Implémenter réellement `EncryptionService` (restaurer le coffre-fort de secrets) vs déprécier/retirer `SecretsService` et les modèles inertes associés | Arbitrage : besoin réel d'un coffre-fort en base vs `.env` + gestionnaire de secrets externe plus tard |
| Migration de cipher CBC → GCM | Implique un re-chiffrement de toutes les données existantes — hors périmètre d'une correction mécanique |
| Rotation de `JWT_SECRET` indépendant d'`APP_KEY` | Décision de déploiement par environnement, coordonnée avec qui gère `.env` en production |
| Élargir le MFA obligatoire au-delà d'admin/super-admin | Arbitrage produit/friction d'onboarding |
| Décider si les endpoints de fédération doivent exister, et implémenter `VerifyFederationSignature` si oui | Décision produit sur l'existence même de la fonctionnalité de fédération |
| Étendre `session.security` aux autres modules (au-delà de HR) | Balayage mécanique mais large (dizaines de fichiers de routes) — HR sert de référence testée |
| Corriger les 123 autres contrôleurs utilisant `Illuminate\Routing\Controller` au lieu d'`App\Http\Controllers\Controller` | Audit plus large que le RBAC seul — tout contrôleur de cette liste qui tenterait d'appeler `$this->authorize()` planterait |
| Activer `JwtAuthenticate` (ou retirer le flux JWT s'il n'est pas utilisé) | Décision produit : le flux JWT est actuellement émis mais jamais vérifié sur aucune route protégée |
| Nettoyage du code mort (`SecurityHeadersService` dupliqué, `XssPreventionService`/`OutputEncodingService`/`HtmlPurifierService` jamais branchés) | Sans risque mais sans bénéfice sécurité non plus — nettoyage cosmétique |
| Consolidation des deux mécanismes d'audit log disjoints (`HasAuditLog` vs `AuditService`) | Décision architecturale plus large qu'une correction de configuration |
| Généraliser `authorize()` aux ~87 % de contrôleurs API qui n'en appellent aucun | Le cas HR (PII) était le plus urgent ; le reste est un chantier de fond, module par module |

---

## 5. Annexe — traçabilité

| Affirmation | Source (fichier:ligne au moment de cette revue) |
|---|---|
| Cipher réel AES-256-CBC | `config/app.php` (clé `cipher`) |
| `EncryptionService` manquant | Aucun fichier — `Modules\Core\Services\EncryptionService` introuvable dans tout le dépôt |
| 34 modèles avec casts `encrypted` | Ex. `Modules/HR/app/Models/Employee.php` |
| Canal `audit` absent (avant correction) | `config/logging.php` (avant cette itération) |
| `HasAuditLog` avale l'exception | `Modules/AuditLog/app/Traits/HasAuditLog.php` |
| Webhook fédération protégé par une classe inexistante | `Modules/Integration/routes/api.php`, middleware `App\Http\Middleware\VerifyFederationSignature` |
| Policy Employee mal enregistrée (avant correction) | `app/Providers/AppServiceProvider.php` (import `App\Policies\EmployeePolicy`, avant cette itération) |
| `BaseErpPolicy` autorise tout utilisateur authentifié en l'absence de colonne propriétaire | `app/Policies/BaseErpPolicy.php` |
| `EmployeeController` sans `authorize()` (avant correction) | `Modules/HR/app/Http/Controllers/Api/EmployeeController.php` (avant cette itération) |
| Login JWT sans throttle/verrouillage (avant correction) | `routes/api.php`, `app/Http/Controllers/Api/JwtAuthController.php` (avant cette itération) |
| Login principal avec throttle/verrouillage (référence) | `Modules/Core/app/Http/Controllers/Api/AuthController.php`, `Modules/Core/routes/api.php` |
| `SESSION_SECURE_COOKIE` non défini (avant correction) | `.env.example`, `config/session.php` (avant cette itération) |
| `SessionSecurityService` inactif (avant correction) | `Modules/Core/app/Services/SessionSecurityService.php`, `app/Http/Middleware/SessionSecurityMiddleware.php` (avant cette itération) |
| 22 rôles seedés | `database/seeders/RolesAndPermissionsSeeder.php` |
| Loi 2014-038, Décret 2023-1541, manuel CMIL, Convention de Malabo/Loi 2024-004 | Recherche web sourcée — voir historique de conversation de cette revue ; à re-vérifier périodiquement auprès de sources officielles (digital.gov.mg, hcc.gov.mg) |
