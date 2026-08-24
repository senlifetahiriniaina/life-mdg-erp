# Sales

## Rôle

Le module Sales gère le cycle de vente transactionnel de Life MDG ERP : devis (`SalesQuotation`) et commandes clients (`SalesOrder`) avec leurs lignes, calcul automatique des totaux (sous-total, remise, taxe), confirmation/annulation de commande, et conversion d'un devis accepté en commande confirmée. Depuis les Chantiers 21-26, le module porte aussi trois extensions réelles construites sur ce socle : le cycle acompte/solde (`SalesDepositService`, Chantier 22), les commandes récurrentes (`RecurringOrderTemplate`, Chantier 25), et les objectifs commerciaux assistés par IA (`SalesObjective`, Chantier 26 volet B). C'est un module volontairement compact comparé à CRM, mais nettement plus étoffé aujourd'hui que sa description d'origine (Phase 11 de WideHalo-ERP).

**Chantier 32.16 — audit approfondi en 14 couches** : ce module a fait l'objet d'un audit complet re-vérifiant chaque couche par exécution réelle (jamais une simple relecture de code). Voir « Particularités du périmètre life-mdg-erp » ci-dessous pour le détail des bugs réels trouvés et corrigés.

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `SalesOrder` | `sales_orders` | Commande client (statut, devise, sous-total/remise/taxe/total, adresse de livraison, dates de confirmation/annulation, cycle acompte/solde) ; utilise `HelpdeskLinkable` (peut ouvrir des tickets Helpdesk), `AuditableActions` (écrit dans `core_audit_logs`, le vrai journal d'audit de l'app) et `HasAuditLog` (écrit dans `storage/logs/audit.log`, un mécanisme complémentaire, pas un doublon — les deux coexistent délibérément). Relations réelles : `lines()`, `createdBy()`, `salesRep()`, `depositInvoice()`/`balanceInvoice()` (vers `Modules\Accounting\Models\Invoice`), et `contact()`/`account()` (vers `Modules\CRM\Models\{Contact,Account}`, ajoutées au Chantier 32.16 — voir plus bas). L'attribut calculé `payment_stage` (`none`/`deposit_invoiced`/`deposit_paid`/`balance_invoiced`/`paid_in_full`) est dérivé en direct des deux factures liées, jamais stocké séparément. |
| `SalesOrderLine` | `sales_order_lines` | Ligne de commande (produit, quantité, prix unitaire, remise %, taux de taxe, total de ligne calculé par `computeTotal()`) |
| `SalesQuotation` | `sales_quotations` | Devis (référence, statut `draft`/`sent`/`accepted`/`rejected`, devise, total, date de validité, contact **et** compte CRM liés — `account_id` activé au Chantier 32.16, lien vers la commande convertie `converted_to_order_id`) |
| `RecurringOrderTemplate`/`RecurringOrderTemplateLine` | `sales_recurring_order_templates`/`_lines` | Modèle de commande récurrente (Chantier 25) — client + lignes + périodicité (hebdomadaire/mensuelle/trimestrielle), génère de vraies `SalesOrder` via `SalesService::createOrder()` |
| `SalesObjective` | `sales_objectives` | Cible de chiffre d'affaires proposée par IA ou manuelle (Chantier 26 volet B), pour un périmètre (`global`/`rep`/`client`/`category`) et une période, dérivée de l'historique réel des 6 derniers mois |

## Endpoints principaux

Routes API sous `auth:sanctum`, middleware `module:Sales`, préfixe `v1/sales` (`Modules/Sales/routes/api.php`).

| Méthode | Route | Description |
|---|---|---|
| GET | `sales/orders` | Liste des commandes (filtres `status`, `contact_id`, `search` — ce dernier activé au Chantier 32.16) |
| POST | `sales/orders` | Créer une commande (avec ses lignes) |
| GET | `sales/orders/{id}` | Détail d'une commande |
| PUT | `sales/orders/{id}` | Modifier une commande (brouillon uniquement, champs d'en-tête seulement) |
| PUT | `sales/orders/{id}/status` | Changer le statut d'une commande |
| POST | `sales/orders/{id}/confirm` | Confirmer une commande (`draft` → `confirmed`) |
| POST | `sales/orders/{id}/cancel` | Annuler une commande (avec motif) |
| POST | `sales/orders/{id}/deposit/request` | Demander un acompte (crée une vraie facture liée) — Chantier 22 |
| POST | `sales/orders/{id}/deposit/pay` | Enregistrer le paiement de l'acompte (écriture comptable réelle, compte 419) |
| POST | `sales/orders/{id}/balance/request` | Demander le solde restant |
| POST | `sales/orders/{id}/balance/pay` | Enregistrer le paiement du solde (écriture comptable réelle, compte 411) |
| GET | `sales/quotations` | Liste des devis |
| POST | `sales/quotations` | Créer un devis |
| GET | `sales/quotations/{id}` | Détail d'un devis |
| PUT | `sales/quotations/{id}` | Modifier un devis |
| POST | `sales/quotations/{id}/send` | Envoyer un devis |
| POST | `sales/quotations/{id}/convert` | Convertir un devis accepté en commande confirmée |
| GET/POST/PUT/DELETE | `sales/recurring-order-templates[/{id}]` | CRUD des modèles de commande récurrente — Chantier 25 |
| POST | `sales/recurring-order-templates/{id}/run` | Déclencher manuellement la génération d'une commande, indépendamment de l'échéance |
| GET/POST/PUT/DELETE | `sales/objectives[/{id}]` | Objectifs commerciaux — Chantier 26 volet B |
| POST | `sales/objectives/propose` | Proposer 3 cibles (conservateur/modéré/ambitieux) depuis l'historique réel |
| POST | `sales/objectives/{id}/validate` | Valider une proposition (rejette automatiquement ses sœurs) |
| POST | `v1/sales/ai/assist` | Guidance IA contextuelle par module dédié — **confirmé au Chantier 32.16 sans appelant réel côté frontend** (voir « Particularités » ci-dessous ; le vrai chemin est le point d'entrée générique `POST /api/v1/ai/assist`) |

Commande planifiée : `sales:generate-recurring-orders`, câblée quotidiennement à 06:00 UTC via `callAfterResolving(Schedule::class, ...)` dans `SalesServiceProvider` (`withoutOverlapping()` activé), re-confirmée réellement enregistrée via `php artisan schedule:list` au Chantier 32.16.

## Contrôleurs

`Modules/Sales/app/Http/Controllers/Api/` contient 4 contrôleurs réels : `SalesController` (CRUD commandes/devis + cycle acompte/solde), `RecurringOrderTemplateController`, `SalesObjectiveController`, et `SalesAiAssistController` (guidance IA — voir la note ci-dessus sur son inaccessibilité réelle depuis le frontend). Aucun contrôleur Web dédié : les 5 routes `/sales/*` sont de simples closures `Inertia::render()`.

## Vues (Vue/Inertia)

`Modules/Sales/resources/js/Pages/` contient `SalesIndex.vue` (liste + KPI, self-fetch), `Orders/Show.vue` + `Orders/Create.vue` + `Orders/Edit.vue` (détail avec panneau acompte/solde, création, modification — les deux dernières construites au Chantier 32.16, fermant un lien mort documenté depuis les Chantiers 22/25), `RecurringOrders/Index.vue`, et `Objectives/Index.vue`. Les anciennes pages maquette `CPQ/Index.vue`/`Subscriptions/Index.vue` (documentées comme « laissées en l'état » dans une note historique de ce fichier) ont depuis été supprimées — elles n'existent plus sur le disque, confirmé au Chantier 32.16.

**Chantier 32.16 — le module était invisible dans la navigation principale** : `resources/js/Layouts/AppLayout.vue` n'avait aucune entrée de menu pour Sales — atteignable uniquement en tapant une URL directement, pour n'importe quel rôle y compris `sales-manager`. Une entrée a été ajoutée dans le groupe « Ventes & clients ». Clé `nav.sales` ajoutée aux 10 fichiers `lang/*.json`.

**Chantier 32.16 — `SalesIndex.vue` n'a jamais affiché les vraies données de sa table** : la colonne client lisait `data.customer?.name` (relation qui n'a jamais existé), la colonne montant lisait `data.amount` (le vrai champ est `total`), et la colonne date lisait `data.order_date` (colonne inexistante) — confirmé par une vraie requête HTTP que ces trois colonnes ont toujours affiché « — »/« NaN »/« Invalid Date ». Corrigé : `SalesOrder` a désormais de vraies relations `contact()`/`account()` (vers `Modules\CRM\Models\{Contact,Account}`), eager-chargées par `indexOrders()`, et le tableau lit `total`/`created_at` réels.

## Services

- **`SalesService`** — génération de référence unique par année, `createOrder()`/`recalculateOrderTotals()`/`confirmOrder()`/`cancelOrder()`/`createQuotation()`/`convertQuotationToOrder()` (verrouillage de ligne — `lockForUpdate()` — ajouté au Chantier 32.16 pour empêcher une double conversion sous requêtes concurrentes).
- **`SalesDepositService`** (Chantier 22) — cycle acompte/solde. Réutilise l'infrastructure Accounting réelle (`Invoice`, `Payment`, `JournalEntry`) plutôt que de la dupliquer ; poste une vraie écriture équilibrée sur les comptes OHADA 419 (acompte)/411 (solde) à chaque paiement encaissé, échoue fort (jamais silencieusement) si le plan comptable n'est pas seedé.
- **`RecurringOrderService`** (Chantier 25) — `generateDueOrders()` (appelée par la commande planifiée, verrou par ligne ajouté au Chantier 32.16 contre une double invocation concurrente réelle du process), `runNow()` (déclenchement manuel), `create()`/`update()` (synchronise les lignes du modèle).
- **`SalesObjectiveService`**/`SalesObjectiveAiService` (Chantier 26 volet B) — calcule 3 propositions (0%/+5%/+15%) depuis la moyenne mensuelle réelle des commandes confirmées sur les 6 derniers mois du périmètre demandé, jamais un chiffre inventé ; l'IA ne rédige qu'un texte de justification, jamais le montant (repli statique fr/en si `ANTHROPIC_API_KEY` absente).

Devise par défaut : `MGA` côté services (`SalesDepositService`/`RecurringOrderService`/`SalesObjectiveService`), `XOF` côté `SalesService::createOrder()`/`createQuotation()` quand aucune devise n'est fournie — une incohérence mineure et documentée plutôt qu'unifiée silencieusement, puisque chaque appelant réel (frontend + tests) passe toujours explicitement une devise.

## Permissions RBAC

Sales a une entrée dédiée dans `RolesAndPermissionsSeeder::MODULES` : `'sales' => ['order', 'line', 'quotation']` (permissions `sales.order.*`/`sales.line.*`/`sales.quotation.*`, jamais réellement consommées par aucun contrôleur — voir ci-dessous), mais **l'autorisation réelle** de `SalesController`/`RecurringOrderTemplateController`/`SalesObjectiveController` repose sur 4 permissions plates dédiées (`SALES_PERMISSIONS` dans le seeder) : `sales.read`, `sales.view` (guidance IA), `sales.create`, `sales.update` — vérifiées via `abort_unless($user->can(...))`, jamais via une classe Policy. `finance-manager` reçoit une grille explicite et volontairement étroite (`sales.read` seul, pas `sales.*`) pour la revue finance périodique (Chantier 26 volet D) sans porter les permissions d'écriture commerciale complètes. `sales-manager` reçoit l'intégralité de `sales.*` via wildcard. Cloisonnement multi-tenant réel via `SalesOrder::forTenant()`/`SalesQuotation::forTenant()`/`RecurringOrderTemplate::forTenant()`/`SalesObjective::forTenant()`, tous basés sur `company_id` (jamais la colonne fantôme `users.tenant_id`).

**Chantier 32.16 — `SalesOrderPolicy` supprimée (couche 9, fake/dead)** : cette policy était bien enregistrée sur le Gate mais **jamais appelée par aucun contrôleur** — confirmé par un grep exhaustif, son propre test unitaire ne faisait que mocker `User::can()` directement, jamais un vrai passage par le Gate. Ses permissions plus granulaires (`sales.order.view-any`/`.view`/`.create`/`.update`/`.delete`) forment un jeu **différent** de celui réellement vérifié par `SalesController` — l'activer aurait silencieusement fait régresser la grille `finance-manager` volontairement étroite du Chantier 26 volet D (qui ne porte que `sales.read`, pas `sales.order.*`). Supprimée plutôt qu'activée : les vérifications plates + `forTenant()` fournissent déjà une autorisation complète et empiriquement re-vérifiée avec 2 sociétés réelles sur chaque endpoint (commandes, devis, modèles récurrents, objectifs).

**Chantier 8.5-light** a ajouté l'autorisation manquante sur `confirmOrder()`/`cancelOrder()` et un contrôle d'appartenance au tenant sur `showOrder`/`updateOrder`/`confirmOrder`/`cancelOrder`. **Chantier 10** a fermé un trou plus large : les 6 endpoints de devis + `updateOrderStatus` n'avaient alors ni vérification de permission ni cloisonnement tenant.

## Dépendances avec d'autres modules

Sales importe (`use Modules\...`) `Modules\AI` (guidance IA), `Modules\Helpdesk` (trait `HelpdeskLinkable`), `Modules\Accounting` (`Invoice`/`Payment`/`JournalEntry`/`Journal`/`ChartOfAccount` — cycle acompte/solde), `Modules\Core` (`ParticipantNotificationService`), `Modules\AuditLog` (trait `HasAuditLog`), et, depuis le Chantier 32.16, `Modules\CRM` (`Contact`/`Account`, relations réelles sur `SalesOrder` — `SalesDepositService` importait déjà ces deux classes depuis le Chantier 22, donc ce n'est pas une nouvelle dépendance de fait, seulement sa première expression via une relation Eloquent plutôt qu'un usage direct dans un service).

## Particularités du périmètre life-mdg-erp

**Fuite cross-tenant corrigée (Chantier 8.5-light)** : `SalesController` résolvait le tenant via `$user->tenant_id ?? 1` (colonne fantôme) — corrigé pour utiliser `$user->company_id ?? 0`, la vraie colonne de frontière multi-tenant, re-vérifié à plusieurs reprises depuis (Chantiers 19, 32.16).

**Chantier 32.16 — audit approfondi en 14 couches, synthèse des trouvailles réelles** (voir `CLAUDE.md` pour le détail complet) :
- **Couche 9 (fake/dead)** : `SalesOrderPolicy` supprimée (voir « Permissions RBAC » ci-dessus) ; `SalesAiAssistController`/`v1/sales/ai/assist` confirmé n'avoir aucun appelant frontend réel (`useAiAssistant()` poste toujours vers le point d'entrée générique `/api/v1/ai/assist`, jamais vers un contrôleur par module — motif déjà documenté à l'échelle de l'app au Chantier 32.2, touchant 26 modules, volontairement non traité module par module).
- **Couche 5/10 (format de données)** : `SalesOrderFactory`/`SalesQuotationFactory`/`SalesOrderLineFactory` étaient du scaffold cassé — `fake()->word()` sur des colonnes typées/FK/date, avec un statut (`'published'`/`'archived'`) qui ne correspond à aucun statut réel de commande/devis. Confirmé **totalement inutilisable** par un vrai appel `SalesOrder::factory()->create()` (exception fatale de parsing de date) — invisible jusqu'ici car aucun test du module ne les appelait jamais. Réécrites pour correspondre aux vrais `$fillable`/`$casts`/vocabulaire de statut. `sales_quotations.account_id` (colonne migrée mais jamais mass-assignable) activée pour de vrai, symétrie avec `SalesOrder`.
- **Couche 6 (sécurité approfondie)** : re-vérification empirique complète, avec 2 sociétés réelles, de chaque endpoint (commandes, devis, modèles récurrents, objectifs) — aucune régression trouvée depuis les Chantiers 8.5-light/10/19, tous les correctifs précédents tiennent toujours.
- **Couche 8 (validation métier)** : `convertQuotationToOrder()` n'avait qu'une vérification `isConvertible()` avant l'ouverture de la transaction — une vraie fenêtre de double-conversion sous requêtes concurrentes (deux commandes réelles depuis un seul devis). Corrigé avec un verrou de ligne (`lockForUpdate()`) et une re-vérification après acquisition. `RecurringOrderService::generateDueOrders()` durci de la même façon en défense en profondeur (la ré-invocation séquentielle était déjà prouvée sûre par construction, verrouillé par un test dédié).
- **Couche 12 (contrat API)** : `SalesIndex.vue` n'a jamais affiché ses vraies données (voir « Vues » ci-dessus) ; le paramètre `search` envoyé par la page était silencieusement ignoré côté serveur — activé.
- **Couche 13 (IA)** : 3 des 4 pages Vue réelles du module (`Orders/Show.vue`, `RecurringOrders/Index.vue`, `Objectives/Index.vue`) n'appelaient jamais `useAiAssistant()` — même motif déjà trouvé pour Strategy (Chantier 30) et Validation (Chantier 32.7). Corrigé, avec 3 nouvelles actions grounded (`manage_deposit_balance`, `manage_recurring_orders`, `manage_sales_objectives`) ; le texte de repli `Sales.confirm_order` affirmait à tort qu'une facture était générée automatiquement à la confirmation — corrigé pour refléter le vrai flux acompte/solde explicite.
- **Couche 14 (performance)** : `SalesOrder::$appends = ['payment_stage']` déclenchait un vrai N+1 sur `GET sales/orders` (2 requêtes paresseuses par commande pour `depositInvoice`/`balanceInvoice`, confirmé empiriquement) — corrigé par eager-loading dans `indexOrders()`.
- **Couche 3 (vue)** : le lien mort « Nouvelle commande »/« Modifier » de `SalesIndex.vue` (documenté comme un gap connu depuis les Chantiers 22/25, jamais construit faute de périmètre) a été fermé — nouvelles pages `Orders/Create.vue`/`Orders/Edit.vue` + routes web associées.

**Volontairement non modifié** : la grille `SALES_PERMISSIONS`/`MODULES['sales']` du seeder (les permissions `sales.order.*` générées par la boucle générique restent orphelines maintenant que `SalesOrderPolicy` est supprimée — laissées en l'état plutôt que retirées du seeder, un nettoyage de permissions non consommées étant hors du périmètre de cette correction ciblée) ; le nettoyage du contrôleur `SalesAiAssistController` orphelin (motif app-wide, réservé à un futur chantier dédié, cohérent avec le traitement des 26 modules équivalents) ; l'incohérence mineure de devise par défaut entre `SalesService` (`XOF`) et les 3 autres services (`MGA`), documentée ci-dessus.
