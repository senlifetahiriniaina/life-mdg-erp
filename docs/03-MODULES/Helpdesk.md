# Helpdesk

## Rôle

Le module Helpdesk gère le support client de bout en bout : tickets, équipes, SLA et escalade, base de connaissance, chat en direct, forum communautaire, enquêtes CSAT, et une couche d'analyse IA (sentiment, prédiction de satisfaction/escalade, coaching agents). Contrairement aux 26 autres modules du périmètre, Helpdesk est **couplé délibérément à tous les autres modules métier** : n'importe quel enregistrement (facture, contact, produit, commande, employé…) peut ouvrir et lister ses propres tickets. Ce couplage était en grande partie aspirationnel/cassé dans le dépôt source WideHalo ERP (mauvais nom de table dans deux intégrations) et a été réellement corrigé lors de l'extraction — voir la section Particularités ci-dessous, qui documente chaque point vérifié dans le code actuel.

## Modèles clés

| Modèle | Table DB | Rôle |
|---|---|---|
| `Ticket` | `hd_tickets` | Ticket de support (sujet, statut, priorité, canal, SLA, satisfaction) ; relation polymorphe `source()` vers l'enregistrement d'origine dans un autre module |
| `TicketComment` | `hd_ticket_comments` | Commentaire/réponse sur un ticket |
| `Team` | `hd_teams` | Équipe de support à laquelle un ticket peut être assigné |
| `SlaPolicy` / `HelpdeskSlaPolicy` | `hd_sla_policies` / `hd_helpdesk_sla_policies` | Politiques SLA (deux modèles distincts, deux tables distinctes — voir Particularités) |
| `SlaBreach` | `hd_sla_breaches` | Violation de SLA enregistrée |
| `EscalationRule` / `EscalationEvent` | `hd_escalation_rules` / `hd_escalation_events` | Règles et évènements d'escalade |
| `KbArticle` / `KbCategory` | `hd_kb_articles` / `hd_kb_categories` | Base de connaissance interne |
| `KbPortalArticle` / `KbPortalCategory` | `hd_kb_portal_articles` / `hd_kb_portal_categories` | Portail de base de connaissance public |
| `ChatSession` / `ChatMessage` | `hd_chat_sessions` / `hd_chat_messages` | Chat en direct, convertible en ticket |
| `Forum` / `ForumThread` / `ForumPost` / `ForumReply` / `ForumVote` | `helpdesk_forums` / `helpdesk_forum_threads` / `helpdesk_forum_posts` / `helpdesk_forum_replies` / `helpdesk_forum_votes` | Forum communautaire |
| `CsatSurvey` / `CsatCampaign` | `helpdesk_csat_surveys` / `helpdesk_csat_campaigns` | Enquêtes de satisfaction client |
| `TicketAssignment` | `helpdesk_ticket_assignments` | Historique d'assignation d'un ticket |
| `SentimentScore`, `EscalationPrediction`, `SatisfactionPrediction`, `AgentMetric`, etc. (préfixe `cs_*`) | `cs_*` | Modèles d'analyse/scoring IA (sentiment, prédiction d'escalade et de satisfaction, performance agent) — nombreux modèles annexes non détaillés ici |

## Endpoints principaux

Montés sous `api/v1/helpdesk` (`Modules/Helpdesk/routes/api.php`), avec des groupes de middleware distincts :

| Portée | Méthode | Route | Description |
|---|---|---|---|
| Public (sans auth) | POST | `helpdesk/chat/sessions`, `.../{session}/messages` | Chat en direct visiteur |
| Public | GET | `helpdesk/kb/portal/articles`, `.../{article}` | Portail base de connaissance public |
| Public (`throttle:20,1`) | POST | `helpdesk/bot/ask`, `helpdesk/bot/deflect` | Réponse automatique en libre-service |
| Auth (tout utilisateur) | GET/POST/PUT/DELETE | `helpdesk/forum/posts/*` | Forum communautaire, votes, réponse acceptée |
| Auth + `module:Helpdesk` | GET/POST/PUT/DELETE | `helpdesk/tickets`, `helpdesk/tickets/{ticket}` | CRUD ticket ; `source_module`/`source_id` optionnels à la création (voir Particularités) |
| — | POST | `helpdesk/tickets/{ticket}/assign\|resolve\|close\|escalate` | Cycle de vie du ticket |
| — | GET/POST/PUT/DELETE | `helpdesk/tickets/{ticket}/comments` | Commentaires |
| — | GET/POST/PUT/DELETE | `helpdesk/teams` | Équipes de support |
| — | GET/POST/PUT/DELETE | `helpdesk/kb/articles`, `helpdesk/kb/categories` (deux contrôleurs : `KbArticleController`/`KbCategoryController` et `KnowledgeBaseController` unifié) | Base de connaissance |
| — | POST (`throttle:ai`) | `helpdesk/ai/categorize`, `suggest-response`, `summarize`, `predict-escalation`, `kb-chatbot` | IA support |
| — | GET/POST/PUT/DELETE | `helpdesk/sla-policies`, `helpdesk/escalation-rules`, `helpdesk/sla/policies` | Configuration SLA/escalade |
| — | GET | `helpdesk/sla/breaches/pending`, `helpdesk/sla/stats/compliance\|performance` | Suivi SLA |
| — | POST | `helpdesk/sla/check`, `helpdesk/sla/escalate` | Déclenchement manuel de la vérification/escalade SLA |
| — | GET/POST | `helpdesk/csat/surveys`, `helpdesk/csat/campaigns`, `helpdesk/csat/report` | CSAT |
| — | GET/POST | `v1/helpdesk/forums`, `.../threads`, `.../replies` | Forums (recherche publique + actions authentifiées) |
| — | POST | `v1/helpdesk/ai/assist` | Guidance IA contextuelle (AI Assisted First) |

## Services

- **`TicketService`** — point d'entrée central de création de ticket (`createFromSource()`), utilisé à la fois par le trait `HelpdeskLinkable` et par `TicketController::store()`, garantissant que toute création (venant d'un autre module ou directe) applique la même logique d'assignation SLA et de génération de numéro de ticket.
- **`SlaService`** / **`SlaAutomationService`** — application d'une politique SLA à un ticket (calcul de l'échéance), automatisation des vérifications et relances.
- **`EscalationService`** / **`PredictiveEscalationService`** — escalade manuelle par règles, et prédiction ML d'urgence/escalade basée sur l'analyse de sentiment.
- **`TicketAssignmentService`** — assignation en file d'attente par répartition round-robin.
- **`KnowledgeBaseService`** — gestion des articles/catégories de la base de connaissance.
- **`ForumService`** — logique du forum communautaire.
- **`LiveChatService`** — sessions et messages de chat en direct, conversion en ticket.
- **`CsatReportService`** — rapports de satisfaction client.
- **`AlertService`** — alertes temps réel (violations SLA, notifications d'assignation).
- **`AiResponseService`** / **`AnswerBotService`** — suggestions de réponse et réponses automatiques en libre-service.
- **`SentimentAnalysisService`** / **`SatisfactionPredictionService`** — analyse de sentiment multilingue et prédiction ML de la satisfaction client.
- **`AgentPerformanceAnalyticsService`** — métriques de performance agent (temps de résolution, FCR, CSAT).

## Permissions RBAC

Permissions dédiées sous le préfixe `helpdesk.*` dans `database/seeders/RolesAndPermissionsSeeder.php` : ressources `ticket` et `team`, actions `view-any|view|create|update|delete`. Les rôles `support-admin` (gestion helpdesk), `customer-service` (Helpdesk complet + vue contact/compte CRM) et `service-partner` (prestataire externe : Helpdesk + Projects) reçoivent les permissions `helpdesk.*`. `TicketPolicy` (étend `App\Policies\BaseErpPolicy`) ajoute une logique fine par rôle Spatie (`support-agent`, `supervisor`, `manager`, `admin`, `super-admin`) pour la visibilité/mise à jour/fermeture/assignation d'un ticket, en plus des permissions granulaires.

## Dépendances avec d'autres modules

- **Consommé par 8 modules via le trait `Modules\Helpdesk\Traits\HelpdeskLinkable`** — vérifié par recherche de code : `Accounting\Invoice`, `CRM\Contact`, `Inventory\Product`, `Sales\SalesOrder`, `Achats\PurchaseOrder`, `Projects\Project`, `Logistics\Shipment`, `HR\Employee` utilisent tous le trait, qui expose `raiseTicket()` et `tickets` (relation `morphMany`).
- **`Modules\Calendar`** (`ModuleEventAggregatorService::importHelpdeskSla()`) lit `hd_tickets` pour synchroniser les échéances SLA dans le calendrier de l'agent assigné.
- **`Modules\Workflow`** (`HelpdeskActionHandler`) pilote le cycle de vie des tickets (`helpdesk.create_ticket`, `escalate_ticket`, `assign_ticket`, `close_ticket`, `send_satisfaction_survey`) via `TicketService`/`EscalationService`/`TicketAssignmentService`.
- **`Modules\Core`** : trait `RecordsActivity` sur `Ticket`.
- **`Modules\AI`** : `HelpdeskAiAssistController` appelle `AiContextualAssistantService`.

## Particularités du périmètre life-mdg-erp

Les 5 points de couplage cross-module décrits pour ce module ont tous été vérifiés dans le code actuel :

1. **Table réelle `hd_tickets` (et non `helpdesk_tickets`)** — confirmé dans `Modules/Helpdesk/app/Models/Ticket.php` (`protected $table = 'hd_tickets'`). L'intégration Calendar (`Modules/Calendar/app/Services/ModuleEventAggregatorService.php::importHelpdeskSla()`) interroge bien `hd_tickets` (`Schema::hasTable('hd_tickets')`, `DB::table('hd_tickets')`). L'intégration Workflow (`Modules/Workflow/app/Services/Actions/HelpdeskActionHandler.php`) porte un commentaire de tête explicite indiquant qu'elle a été **réécrite** pour utiliser le modèle Eloquent `Ticket` (schéma `hd_tickets` : `ticket_number`, `reporter_id`, `assignee_id`, `description`, …) après que l'implémentation précédente ciblait `helpdesk_tickets` avec des colonnes (`tenant_id`, `client_id`, `body`, `support_level`, `assigned_to`, `resolution_note`) qui ne correspondaient à aucun schéma réel et tombaient silencieusement dans une branche « simulée » sans jamais toucher de vraies données.
2. **Colonnes polymorphes `source_type`/`source_id` sur `hd_tickets` + relation `morphTo('source')`** — confirmé : migration `2026_06_19_000021_add_source_polymorphic_to_hd_tickets.php` (avec commentaire expliquant qu'elle remplace les anciennes colonnes non reliées `contact_id`/`customer_id`/`source_ref` qui n'avaient pas de relation correspondante sur le modèle) et `Ticket::source(): MorphTo`.
3. **Trait `HelpdeskLinkable`** — confirmé à `Modules/Helpdesk/app/Traits/HelpdeskLinkable.php`, exposant `tickets()` (morphMany) et `raiseTicket()`. Utilisé par les 8 modèles listés dans la section Dépendances.
4. **`POST /api/v1/helpdesk/tickets` avec `source_module`/`source_id` validés contre une allowlist** — confirmé dans `TicketController::store()` : `Rule::in(array_keys(Relation::morphMap()))`, jamais de résolution de classe brute depuis l'entrée client. La morph map est enregistrée dans `HelpdeskServiceProvider::registerTicketSourceMorphMap()` avec les 8 alias (`invoice`, `contact`, `product`, `sales_order`, `purchase_order`, `project`, `shipment`, `employee`).
5. **Bouton global « Signaler un incident »** — confirmé : `resources/js/Components/Helpdesk/QuickTicketButton.vue` existe et est importé/monté dans `resources/js/Layouts/AppLayout.vue`, rendant la création rapide de ticket accessible depuis n'importe quelle page authentifiée.

Point annexe observé pendant la vérification (non demandé explicitement mais pertinent pour la cohérence documentaire) : il existe deux modèles de politique SLA distincts, `SlaPolicy` (`hd_sla_policies`) et `HelpdeskSlaPolicy` (`hd_helpdesk_sla_policies`), utilisés par des contrôleurs différents (`EscalationController` vs `SlaController`) — ce n'est pas un bug d'extraction, les deux existent tels quels dans le code actuel.
