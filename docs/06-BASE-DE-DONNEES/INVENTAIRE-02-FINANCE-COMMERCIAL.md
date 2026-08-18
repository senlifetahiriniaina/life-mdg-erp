# Inventaire des tables — Compta/Finance et Commercial/CRM (3 modules)

Accounting, CRM, Sales. Voir `SCHEMA-GENERAL.md` pour la méthode et la légende **réelle / stub patché / stub pur**.

## Accounting (118 tables : 76 réelles, 42 stub dont 5 patchées / 37 pures — le plus gros module, 76+ modèles)

Deux origines réelles distinctes coexistent, **toutes deux issues de la même migration** `2026_05_29_000001_create_accounting_core_tables.php` (94 tables au total à elle seule) : 52 tables préfixées `acc_`, et 23 tables **sans préfixe** correspondant aux fonctionnalités de phase plus tardive (Budget variance, Consolidation, Cost Engine, ASC606, Dépréciation, Intercompany, Tax compliance — voir `SCHEMA-GENERAL.md` § Convention de nommage). Une troisième vague — la boucle stub du catch-all — ajoute 42 tables `acc_*` supplémentaires au schéma générique.

### Grand livre, journaux, immobilisations (préfixe `acc_`)

| Table | Rôle |
|---|---|
| `acc_companies` | Entité comptable (peut différer de `App\Models\Company` racine — multi-entité) |
| `acc_chart_of_accounts` | Plan comptable hiérarchique (parent/enfants) |
| `acc_gl_accounts` / `acc_gl_journals` / `acc_gl_entries` | Grand livre général — `acc_gl_entries` correspond au modèle `GLEntry` documenté dans `CLAUDE.md` (Known Gaps) comme reconnecté une fois la fondation `Company` en place |
| `acc_journals` / `acc_journal_entries` / `acc_journal_entry_lines` | Journaux et écritures comptables (numéro, date, compte GL, type) |
| `acc_fixed_assets` / `acc_asset_depreciation` / `acc_asset_disposals` | Immobilisations, amortissements, cessions |
| `acc_audit_logs` | Journal d'audit propre à Accounting (distinct de `core_audit_logs`) |

### Facturation et dépenses

| Table | Rôle |
|---|---|
| `acc_invoices` | Facture client/fournisseur/avoir (`type`: invoice/bill/credit_note), `partner_id`/`customer_id`, statut `draft/posted/paid/cancelled`, devise (`XOF` par défaut), montants (`subtotal`/`tax_amount`/`total`/`amount_paid`/`amount_due`) |
| `acc_invoice_lines` / `acc_invoice_payments` | Lignes de facture et paiements associés |
| `accounting_invoice_approvals` / `accounting_approval_steps` | Circuit d'approbation de facture par palier OHADA (100K/500K XOF, Phase 34) — **sans préfixe `acc_`** |
| `accounting_payment_schedules` | Échéancier de paiement — sans préfixe |
| `acc_expenses` / `acc_expense_lines` / `acc_expense_reports` | Notes de frais et rapports de dépenses |

### Banque et rapprochement

| Table | Rôle |
|---|---|
| `acc_bank_accounts` | Compte bancaire — patché au Chantier 8.1 avec `gl_account_id`/`iban`/`bic` (validés par `StoreBankAccountRequest` mais absents à l'origine, silencieusement perdus en écriture avant le fix) |
| `acc_bank_statements` / `acc_bank_transactions` | Relevés et transactions bancaires |
| `acc_bank_feeds` / `acc_bank_feed_transactions` | Flux bancaires connectés et leurs transactions importées |
| `acc_reconciliations` / `acc_reconciliation_sessions` | Rapprochement bancaire — `BankAccount::reconciliationSessions()` était une relation manquante, ajoutée au Chantier 8.1 |
| `acc_open_banking_connections` / `acc_openbanking_connections` / `acc_openbanking_sync_logs` | Connexions open banking (deux noms de table très proches — `open_banking` et `openbanking` — probable doublon historique, à vérifier avant d'écrire dessus) |

### Budget, coûts, trésorerie

| Table | Rôle |
|---|---|
| `acc_budgets` / `acc_budget_lines` / `acc_budget_scenarios` | Budgets, lignes budgétaires, scénarios (Chantier 8.1b : `BudgetPolicy`/`BudgetScenarioPolicy` + permissions `accounting.budget.*`/`accounting.budget_scenario.*` ajoutées) |
| `cost_allocation_keys` / `cost_categories` / `cost_entries` / `cost_rollups` | Moteur de coûts CAPEX/OPEX/FINEX/RISKEX (`CostEngineService::calculateBomCosts()`), mapping OHADA `Cl.2/6/67/69` — sans préfixe `acc_` |
| `acc_cash_flow_forecasts` / `acc_cash_flow_forecast_items` | Prévisions de trésorerie |
| `acc_treasury_forecasts` / `acc_treasury_scenarios` / `acc_treasury_lines` / `acc_treasury_alerts` | Prévisions de trésorerie OHADA à 90 jours et alertes |
| `acc_currency_gains_losses` / `acc_exchange_rates` | Multi-devises, gains/pertes de change |

### Consolidation, intercompany, ASC606, tax

| Table | Rôle |
|---|---|
| `acc_consolidation_entries` / `acc_consolidation_members` / `acc_consolidation_reports` / `acc_consolidation_subsidiaries` | Modèle `ConsolidationGroup` unifié (`CLAUDE.md` : « multi-company Consolidation vit désormais derrière un `ConsolidationGroup` unique ») |
| `consolidation_eliminations` / `consolidation_entries` / `consolidation_hierarchies` / `consolidation_periods` | Stack `ConsolidationHierarchy` **parallèle et conservé délibérément** — un concept différent (arbre de propriété avec périodes/éliminations), pas un doublon — sans préfixe `acc_` |
| `acc_intercompany_transactions` | Transactions intercompany, préfixée |
| `intercompany_clearances` / `intercompany_reconciliations` | Chantier 8.1b — `IntercompanyClearanceController` construit en feature complète (bug fixé : `$q->user()` sur un `Builder` au lieu de `$request->user()`) — sans préfixe |
| `revenue_contracts` / `revenue_recognition_schedules` | **ASC606 Revenue Recognition — le seul gap explicitement exclu plutôt que construit** (`CLAUDE.md` : norme US-GAAP sans équivalent OHADA/SYSCOHADA). `Modules/Accounting/tests/Feature/RevenueContractTest.php` (5 échecs) documente ce gap, pas une régression |
| `acc_tax_entries` / `acc_tax_rates` / `acc_tax_settings` / `acc_vat_declarations` / `acc_vat_rates` | Fiscalité préfixée `acc_` |
| `tax_compliance_reports` / `tax_compliance_rules` / `tax_filing_templates` / `tax_jurisdictions` | Fiscalité sans préfixe — conformité et dépôt par juridiction |
| `acc_report_templates` / `acc_saved_report_parameters` / `acc_scheduled_reports` | Reporting comptable interne |

### Tables stub (schéma générique `id/tenant_id/data/timestamps`)

**Patchées depuis leur création générique** (probablement fonctionnelles) : `acc_budget_actuals`, `acc_budget_alerts`, `acc_budget_forecasts` (le trio `BudgetActual`/`BudgetAlert`/`BudgetForecast` documenté dans `CLAUDE.md` comme « reconnecté »), `acc_consolidation_groups`, `acc_intercompany_rules`.

**Jamais retouchées (stub pur, 37 tables)** : `acc_consolidated_financial_statements`, `acc_consolidation_adjustments`, `acc_consolidation_entities`, `acc_consolidation_rules`, `acc_consolidation_worksheets`, `acc_consolidations`, `acc_ebitda_reconciliations`, `acc_entity_relationships`, `acc_expense_approvals`, `acc_expense_categories`, `acc_expense_receipts`, `acc_financial_metric_trends`, `acc_financial_reports`, `acc_fiscal_years`, `acc_generated_reports`, `acc_impairment_tests`, `acc_lease_payments`, `acc_minority_interests`, `acc_ml_matching_metrics`, `acc_operating_leases`, `acc_outstanding_items`, `acc_reconciliation_exceptions`, `acc_reconciliation_matches`, `acc_reporting_currencies`, `acc_revenue_contracts`, `acc_revenue_recognition_events`, `acc_revenue_recognition_policies`, `acc_segment_reports`, `acc_tax_automation_rules`, `acc_tax_calculation_audits`, `acc_tax_categories`, `acc_tax_compliance`, `acc_tax_deductions`, `acc_tax_rule_audit_logs`, `acc_tax_rules`, `acc_trend_forecasts`, `acc_xbrl_exports`. C'est très probablement le même ensemble (à quelques renommages près) que les « 36 des 42 tables `acc_` sans modèle Eloquent, jamais interrogées par aucun code » documenté dans `CLAUDE.md` Known Gaps — laissé tel quel intentionnellement (dette de scaffold inerte, pas un gap fonctionnel).

Voir aussi `docs/03-MODULES/Accounting.md` pour les modèles Eloquent réels (`ChartOfAccount`, `Invoice`/`InvoiceLine`/`InvoicePayment`, `Budget`/`BudgetLine`/`BudgetScenario`, etc.) et les 7 pages Vue Chantier 8.1b (`AssetImpairments`, `BudgetVariance`, `CostEngine`, `DepreciationPolicies`, `DepreciationSchedules`, `IntercompanyClearances`, `ScenarioPlanning`).

## CRM (44 tables : 26 réelles, 18 stub dont 6 patchées / 12 pures)

| Table | Rôle |
|---|---|
| `crm_contacts` / `crm_accounts` | Contact (personne) et compte (entreprise cliente/prospect) |
| `crm_leads` | Prospect entrant — `status`/`source`/`score`, `assigned_to` (FK `users`) |
| `crm_lead_status_logs` | Historique des changements de statut |
| `crm_opportunities` | Opportunité commerciale |
| `crm_pipelines` | Étapes du pipeline de vente |
| `crm_quotes` / `crm_quote_lines` | Devis et lignes — `opportunity_id`/`contact_id`, statut, `total_amount`, devise |
| `crm_territories` / `crm_territory_assignments` | Territoires commerciaux et affectations (le sous-système parallèle `TerritoryManagementController`/`TerritoryQuota`/`TerritoryAlert`, cassé et redondant avec `Territory`/`TerritoryService` réel, a été supprimé au Chantier 8.2 — ces deux tables restent celles du système réel) |
| `crm_campaigns` / `crm_campaign_stages` / `crm_campaign_enrollments` / `crm_campaign_actions` / `crm_campaign_analytics` | Campagnes marketing internes (indépendantes du module Email exclu du périmètre) |
| `crm_workflows` / `crm_workflow_nodes` / `crm_workflow_edges` / `crm_workflow_executions` | Mini-moteur de workflow **propre au module CRM**, distinct de `Modules\Workflow` |
| `crm_revenue_anomalies` / `crm_revenue_insights` / `crm_revenue_trends` | Intelligence de revenu |
| `crm_call_recordings` | Enregistrements d'appels |
| `crm_companies` / `crm_customers` | Deux tables additionnelles distinctes de `crm_accounts` — vérifier le modèle exact avant usage, chevauchement conceptuel probable |
| `crm_activities` | Activités CRM génériques |

**Tables stub, patchées depuis** : `crm_ai_agents`, `crm_email_sequences` (2 patches), `crm_forecasts`, `crm_scoring_rules`, `crm_sequence_steps`, `crm_web_forms`. **Stub pures (12)** : `crm_ai_agent_runs`, `crm_call_logs`, `crm_email_sequence_enrollments`, `crm_email_sequence_steps`, `crm_engagement_signals`, `crm_opportunity_history`, `crm_opportunity_scores`, `crm_pipeline_snapshots`, `crm_product_bundles`, `crm_sequence_enrollments`, `crm_web_form_submissions`, `crm_win_loss_records`.

Le sous-système `TerritoryManagementController`/`TerritoryManagementService`/`TerritoryQuota`/`TerritoryAlert` (Chantier 8.2, supprimé — 6 méthodes appelaient des méthodes de service inexistantes) n'a donc **pas** de tables propres restant dans le schéma sous ces noms ; les tables `crm_territories`/`crm_territory_assignments` ci-dessus appartiennent au système réel (`Territory`/`TerritoryService`/`TerritoryForecastService`).

**Gap non documenté ailleurs** : `ForecastInput` (`crm_forecast_inputs`) et un second `ForecastModel` propre au CRM (`crm_forecast_models`, distinct du `ForecastModel` d'Analytics et de celui de BI — 3 classes homonymes, 3 tables différentes dans 3 modules) déclarent leur `$table` mais **aucune migration ne les crée** — seule `crm_forecasts` (stub, patchée) existe réellement du triplet `Forecast`/`ForecastModel`/`ForecastInput` documenté dans `docs/03-MODULES/CRM.md`.

## Sales (3 tables, toutes réelles)

| Table | Rôle |
|---|---|
| `sales_orders` | Commande client — statut, devise, sous-total/remise/taxe/total, adresse de livraison, dates confirmation/annulation. Utilise `HelpdeskLinkable` (voir `DEPENDANCES-MODULES.md`) et `HasAuditLog` |
| `sales_order_lines` | Ligne de commande — produit, quantité, prix unitaire, remise %, taux de taxe, total calculé par `computeTotal()` |
| `sales_quotations` | Devis — référence, statut, devise, total, validité, `converted_to_order_id` |

Une vraie fuite cross-tenant a été corrigée au Chantier 8.5-light : `SalesController` utilisait `$user->tenant_id ?? 1` (colonne fantôme jamais peuplée) au lieu de `$user->company_id ?? 0` — toutes les commandes/devis de toutes les entreprises atterrissaient silencieusement dans le même « bucket » tenant 1.
