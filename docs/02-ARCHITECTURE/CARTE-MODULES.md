# Carte des 27 modules et de leurs dépendances

## Regroupement fonctionnel

| Groupe | Modules |
|---|---|
| **Socle CORE / système (12)** | `Core, AI, Security, AuditLog, API, Integration, Validation, Shared, Settings, Setup, Workflow, Calendar` |
| **Compta et Finance (1)** | `Accounting` |
| **Commercial et CRM (2)** | `CRM, Sales` |
| **Stock et Logistique (3)** | `Inventory, Logistics, Achats` |
| **Pilotage et Reporting (4)** | `BI, Analytics, Reporting, Strategy` |
| **RH basique (4)** | `HR` (périmètre réduit), `Payroll, Timesheets, Projects` |
| **Support (1)** | `Helpdesk` |

Soit 27 modules sur les 49 du monorepo WideHalo ERP d'origine. Modules exclus : Manufacturing, POS, Ecommerce, Email, WhatsApp, Documents, Planning, Quality, PLM, Discussion, Assets, Contracts, SMS, SmartTable, Notes, CustomerService, MarketingAutomation, RealTime, MobileSync, Messaging, ainsi que les fonctionnalités RH avancées (ATS, performance 360°, formation, succession).

## Dépendances structurelles entre modules gardés

- **`AI`** est la dépendance universelle : tous les modules importent `AiContextualAssistantService`, mais `AI` lui-même n'importe rien d'autre — c'est la base de la chaîne de dépendances.
- **`Achats` ↔ `Inventory`** : couplage bidirectionnel via `ReorderAutomationService`/`PurchaseOrder` — une rupture de stock peut déclencher une commande fournisseur, et la réception d'une commande met à jour le stock.
- **`Achats` → `Validation`** : le moteur d'approbation générique (`Validation`) est utilisé par les workflows de validation des commandes d'achat.
- **`Timesheets` → `Projects`** : dépendance dure — une feuille de temps est toujours rattachée à un projet (`use Modules\Projects` dans `Timesheets/app`).
- **`Helpdesk` → tous les modules métier** : `Accounting\Invoice`, `CRM\Contact`, `Inventory\Product`, `Sales\SalesOrder`, `Achats\PurchaseOrder`, `Projects\Project`, `Logistics\Shipment`, `HR\Employee` peuvent tous lever et lister leurs propres tickets via le trait `Modules\Helpdesk\Traits\HelpdeskLinkable` — voir `docs/03-MODULES/Helpdesk.md`.
- **`Strategy`** lit des KPI cross-modules (Accounting, CRM, Inventory, Sales, Helpdesk, HR) sans imposer de dépendance dure dans l'autre sens — chaque source de KPI est interrogée via un callback encapsulé dans un try/catch, avec repli si la source est absente.

## Découplages appliqués lors de l'extraction

Trois couplages étroits vers des modules **exclus** du périmètre ont été retirés :

| Module gardé | Couplage retiré | Remplacement |
|---|---|---|
| `CRM` | `Email` (`ContactEmailService` utilisait `DynamicMail`/`EmailTemplate`) | `Illuminate\Mail` (Mailable Laravel natif) |
| `HR` | `Planning` (`Employee` avait une relation vers `Modules\Planning\Models\EmployeeSchedule`) | Relation retirée ; la présence/absence basique (`Attendance`/`ShiftSchedule`) suffit au périmètre de lancement |
| `Projects` | `Notes` (`ProjectWikiService` utilisait `Modules\Notes\Models\Note`) | Fonctionnalité wiki de projet retirée (annexe, non demandée pour le lancement) |

## Modules socle CORE — rôle de chacun

| Module | Rôle |
|---|---|
| `Core` | Services transverses (SmartDefaultsService, etc.), pas de dépendance vers un autre module métier |
| `AI` | `AiContextualAssistantService` — guidance IA contextuelle pour tous les modules |
| `Security` | Authentification, chiffrement, conformité |
| `AuditLog` | Journal d'audit de toutes les actions |
| `API` | Infrastructure API transverse (rate limiting, versioning) |
| `Integration` | Connecteurs externes (mobile money, etc.) |
| `Validation` | Moteur d'approbation générique, réutilisé par Achats et d'autres workflows |
| `Shared` | Utilitaires/traits partagés entre modules |
| `Settings` | Paramétrage applicatif |
| `Setup` | Assistant d'onboarding, import de données assisté par IA |
| `Workflow` | Automatisation de processus (déclencheurs/actions cross-modules) |
| `Calendar` | Agrégateur d'événements cross-modules (échéances Accounting, congés HR, SLA Helpdesk...) |
