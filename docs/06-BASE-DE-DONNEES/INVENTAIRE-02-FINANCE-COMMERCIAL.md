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

## Détail des colonnes (succinct)

Extrait le 2026-08-21 directement du schéma SQLite réellement migré (`Schema::getColumns()`), pas des fichiers de migration — la source de vérité la plus fiable compte tenu du mécanisme « racine crée, module patche » documenté dans `SCHEMA-GENERAL.md`. Format : `colonne:type` — `!` = non nullable (requis). Types SQLite génériques (`integer`/`varchar`/`numeric`/`text`/`datetime`/`date`/`tinyint`) ; en production MySQL les types réels sont plus précis (`bigint unsigned`, `decimal(15,4)`, etc.) mais la structure des colonnes est identique.

### Accounting (83 tables)

**`acc_asset_depreciation`** — `id:integer!,asset_id:integer,period_start:date!,period_end:date!,period_month:integer,period_year:integer,depreciation_amount:numeric!,accumulated_depreciation:numeric!,net_book_value:numeric!,units_used:numeric,journal_entry_id:integer,created_at:datetime,updated_at:datetime`

**`acc_asset_disposals`** — `id:integer!,asset_id:integer,disposal_date:date!,disposal_type:varchar,disposal_proceeds:numeric!,net_book_value_at_disposal:numeric!,gain_loss:numeric!,notes:text,journal_entry_id:integer,created_by:integer,created_at:datetime,updated_at:datetime`

**`acc_audit_logs`** — `id:integer!,auditable_type:varchar,auditable_id:integer,event:varchar,user_id:integer,old_values:text,new_values:text,ip_address:varchar,user_agent:varchar,created_at:datetime,updated_at:datetime,entity_type:varchar,entity_id:integer,action:varchar,reason:text,reference_id:integer`

**`acc_bank_accounts`** — `id:integer!,name:varchar!,bank_name:varchar!,account_number:varchar,currency:varchar!,current_balance:numeric!,last_reconciled_at:datetime,last_reconciled_balance:numeric,is_active:tinyint!,notes:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,gl_account_id:integer,iban:varchar,bic:varchar`

**`acc_bank_feed_transactions`** — `id:integer!,feed_id:integer,external_id:varchar,date:date!,amount:numeric!,description:text,category:varchar,merchant:varchar,status:varchar!,journal_entry_id:integer,ai_category_suggestion:varchar,created_at:datetime,updated_at:datetime`

**`acc_bank_feeds`** — `id:integer!,connection_id:integer,external_account_id:varchar,account_name:varchar,iban:varchar,currency:varchar!,balance:numeric!,last_transaction_date:date,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`acc_bank_statements`** — `id:integer!,bank_account_id:integer,statement_date:date!,opening_balance:numeric!,closing_balance:numeric!,status:varchar!,transaction_count:integer!,matched_count:integer!,reconciled_at:datetime,notes:text,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`acc_bank_transactions`** — `id:integer!,statement_id:integer,transaction_date:date!,description:text,amount:numeric!,reference:varchar,status:varchar!,matched_entry_id:integer,matched_at:datetime,created_at:datetime,updated_at:datetime,is_ignored:tinyint!,bank_account_id:integer`

**`acc_budget_actuals`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,budget_id:integer,budget_line_id:integer,period_month:date,actual_amount:numeric!`

**`acc_budget_alerts`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,budget_id:integer,budget_line_id:integer,alert_type:varchar,status:varchar!,threshold_percent:numeric!,current_variance_percent:numeric!,triggered_at:datetime`

**`acc_budget_forecasts`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,budget_id:integer,budget_line_id:integer,forecast_month:date,forecasted_amount:numeric!,forecast_method:varchar!,confidence_level:numeric!`

**`acc_budget_lines`** — `id:integer!,budget_id:integer,account_id:integer,period_month:integer,period_year:integer,budgeted_amount:numeric!,actual_amount:numeric!,variance:numeric!,notes:text,category:varchar,description:text,spent_amount:numeric!,period:varchar,month:integer,quarter:integer,created_at:datetime,updated_at:datetime,gl_account_id:integer,amount:numeric!`

**`acc_budget_scenarios`** — `id:integer!,name:varchar!,base_budget_id:integer,scenario_type:varchar,adjustment_type:varchar,revenue_adjustment:numeric!,expense_adjustment:numeric!,description:text,assumptions:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,status:varchar!,approved_at:datetime`

**`acc_budgets`** — `id:integer!,company_id:integer,name:varchar!,description:text,budget_period_start:date,budget_period_end:date,fiscal_year:varchar,total_budget:numeric!,budgeted_amount:numeric!,actual_amount:numeric!,status:varchar!,approved_by_id:integer,approved_at:datetime,gl_account_id:integer,created_at:datetime,updated_at:datetime,deleted_at:datetime,created_by:integer,total_revenue_budget:numeric,total_spent:numeric!,department:varchar,total_expense_budget:numeric,parent_budget_id:integer,fiscal_year_start:date,fiscal_year_end:date,currency:varchar`

**`acc_cash_flow_forecast_items`** — `id:integer!,forecast_id:integer,date:date!,category:varchar,type:varchar!,source:varchar,description:text,amount:numeric!,probability:numeric!,weighted_amount:numeric!,is_actual:tinyint!,reference_type:varchar,reference_id:integer,created_at:datetime,updated_at:datetime`

**`acc_cash_flow_forecasts`** — `id:integer!,name:varchar!,description:text,base_date:date!,horizon:integer!,end_date:date,scenario:varchar!,opening_balance:numeric!,projected_closing_balance:numeric!,minimum_balance_threshold:numeric,assumptions:text,status:varchar!,created_by:integer,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`acc_chart_of_accounts`** — `id:integer!,parent_id:integer,code:varchar!,name:varchar!,type:varchar!,description:text,is_active:tinyint!,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`acc_companies`** — `id:integer!,name:varchar!,code:varchar,parent_company_id:integer,company_type:varchar!,ownership_percentage:numeric!,currency:varchar!,fiscal_year_start_month:integer!,is_active:tinyint!,elimination_account_id:integer,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`acc_consolidation_entries`** — `id:integer!,consolidation_group_id:integer!,entry_type:varchar!,related_transaction_id:integer,amount:numeric!,description:text,created_at:datetime,updated_at:datetime`

**`acc_consolidation_groups`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,name:varchar,description:text,currency:varchar!,is_active:tinyint!,consolidation_method:varchar!,parent_company_id:integer,fiscal_year:integer,consolidation_date:date,created_by:integer,status:varchar!,auto_eliminate_intercompany:tinyint!`

**`acc_consolidation_members`** — `id:integer!,consolidation_group_id:integer!,subsidiary_company_id:integer!,ownership_percentage:numeric!,relationship_type:varchar!,acquisition_date:date,acquisition_price:numeric!,exchange_rate:numeric!,created_at:datetime,updated_at:datetime`

**`acc_consolidation_reports`** — `id:integer!,consolidation_group_id:integer,report_type:varchar,reporting_currency:varchar!,consolidated_data:text,intercompany_eliminations:text,exchange_differences:text,total_adjustments:numeric!,status:varchar!,auditor_notes:text,created_by:integer,finalized_at:datetime,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`acc_consolidation_subsidiaries`** — `id:integer!,consolidation_id:integer!,company_id:integer!,ownership_percentage:numeric!,acquisition_date:date,created_at:datetime,updated_at:datetime`

**`acc_currency_gains_losses`** — `id:integer!,invoice_id:integer,original_amount:numeric!,original_currency:varchar,converted_amount:numeric!,base_currency:varchar,gain_loss:numeric!,realized:tinyint!,created_at:datetime,updated_at:datetime`

**`acc_exchange_rates`** — `id:integer!,base_currency:varchar!,target_currency:varchar!,rate:numeric!,source:varchar,date:date!,created_at:datetime,updated_at:datetime`

**`acc_expense_lines`** — `id:integer!,report_id:integer,date:date,category:varchar,description:text,amount:numeric!,currency:varchar!,receipt_url:varchar,km:numeric,created_at:datetime,updated_at:datetime`

**`acc_expense_reports`** — `id:integer!,employee_id:integer,title:varchar!,status:varchar!,total:numeric!,submitted_at:datetime,approved_at:datetime,created_at:datetime,updated_at:datetime,deleted_at:datetime,total_amount:numeric!,currency:varchar!,approved_by:integer,period_start:date,period_end:date,submitted_by_id:integer,company_id:integer`

**`acc_expenses`** — `id:integer!,company_id:integer,expense_number:varchar,employee_id:integer,expense_category_id:integer,expense_date:date,description:text,amount:numeric!,currency:varchar!,amount_approved:numeric,amount_reimbursed:numeric,tax_amount:numeric,tax_category_id:integer,status:varchar!,priority:varchar,submitted_at:datetime,submitted_by_id:integer,approved_at:datetime,rejected_reason:text,notes:text,gl_account_id:integer,created_at:datetime,updated_at:datetime,deleted_at:datetime,created_by:integer,category:varchar,expense_reference:varchar,vendor:varchar,receipt_number:varchar,approved_by_id:integer,created_by_id:integer,payment_method:varchar`

**`acc_finance_reviews`** — `id:integer!,company_id:integer,reviewer_id:integer,budget_id:integer,cadence:varchar!,period_start:date!,period_end:date!,review_date:date!,comments:text,created_at:datetime,updated_at:datetime`

**`acc_financial_simulation_lines`** — `id:integer!,financial_simulation_id:integer!,type:varchar!,product_id:integer,supplier_id:integer,contact_id:integer,label:varchar,quantity:numeric!,unit_price:numeric,recurrence:varchar!,start_date:date!,end_date:date,growth_rate_percent:numeric!,counterpart_account_code:varchar,status:varchar!,realized_at:datetime,realized_type:varchar,realized_id:integer,notes:text,created_at:datetime,updated_at:datetime`

**`acc_financial_simulations`** — `id:integer!,company_id:integer,name:varchar!,description:text,granularity:varchar!,start_date:date!,horizon_periods:integer!,opening_cash_balance:numeric,status:varchar!,created_by:integer,created_at:datetime,updated_at:datetime`

**`acc_fiscal_years`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,name:varchar,start_date:date,end_date:date,is_closed:tinyint!,status:varchar!`

**`acc_fixed_assets`** — `id:integer!,tenant_id:integer,asset_code:varchar,name:varchar!,description:text,asset_class:varchar,acquisition_date:date,acquisition_cost:numeric!,salvage_value:numeric!,useful_life_years:integer!,depreciation_method:varchar!,asset_account_id:integer,depreciation_expense_account_id:integer,accumulated_depreciation_account_id:integer,status:varchar!,disposal_date:date,disposal_proceeds:numeric,disposal_notes:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,created_by:integer,company_id:integer`

**`acc_gl_accounts`** — `id:integer!,account_number:varchar!,account_name:varchar!,account_type:varchar!,normal_balance:varchar!,description:text,balance:numeric!,status:varchar!,created_at:datetime,updated_at:datetime,deleted_at:datetime,code:varchar,name:varchar,parent_id:integer,is_active:tinyint!,created_by:integer,type:varchar,ohada_class:varchar,company_id:integer`

**`acc_gl_entries`** — `id:integer!,gl_account_id:integer,gl_journal_id:integer,journal_entry_id:integer,date:date,description:text,debit_amount:numeric!,credit_amount:numeric!,currency:varchar!,exchange_rate:numeric!,company_id:integer,reference:varchar,source_type:varchar,source_id:integer,period:varchar,created_at:datetime,updated_at:datetime,journal_id:integer,line_number:integer`

**`acc_gl_journals`** — `id:integer!,code:varchar,name:varchar,type:varchar!,description:text,company_id:integer,is_active:tinyint!,created_at:datetime,updated_at:datetime,status:varchar!,journal_type:varchar,posted_date:date,created_by_id:integer,number:varchar,date:date,reference_type:varchar,reference_id:integer`

**`acc_intercompany_rules`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,company_id:integer,name:varchar,description:text,transaction_type:varchar,source_entity_id:integer,target_entity_id:integer,source_gl_account_id:integer,target_gl_account_id:integer,auto_post:tinyint!,posting_frequency:varchar,require_approval:tinyint!,conditions:text,is_active:tinyint!,created_by_id:integer,updated_by_id:integer`

**`acc_intercompany_transactions`** — `id:integer!,consolidation_group_id:integer,from_company_id:integer,to_company_id:integer,transaction_type:varchar,transaction_date:date!,reference_number:varchar,amount:numeric!,currency:varchar!,exchange_rate:numeric!,is_eliminated:tinyint!,elimination_notes:text,related_transaction_id:integer,created_at:datetime,updated_at:datetime,deleted_at:datetime,journal_entry_id:integer,description:text,eliminated_at:datetime`

**`acc_invoice_lines`** — `id:integer!,invoice_id:integer,account_id:integer,product_id:integer,description:text,quantity:numeric!,unit_price:numeric!,tax_rate:numeric!,subtotal:numeric!,tax_amount:numeric!,total:numeric!,match_ref:varchar,matched_by:integer,matched_at:datetime,created_at:datetime,updated_at:datetime,discount_percent:numeric!,tax_percent:numeric!`

**`acc_invoice_payments`** — `id:integer!,invoice_id:integer,payment_date:date!,amount:numeric!,currency:varchar!,payment_method:varchar,reference:varchar,status:varchar!,notes:text,created_by:integer,created_at:datetime,updated_at:datetime`

**`acc_invoices`** — `id:integer!,journal_id:integer,created_by:integer,number:varchar!,type:varchar!,partner_id:integer,customer_id:integer,partner_name:varchar,partner_type:varchar,invoice_date:date,due_date:date,status:varchar!,currency:varchar!,exchange_rate:numeric!,subtotal:numeric!,tax_amount:numeric!,total:numeric!,amount_paid:numeric!,paid_amount:numeric!,amount_due:numeric!,notes:text,payment_terms:varchar,paid_at:datetime,created_at:datetime,updated_at:datetime,deleted_at:datetime,invoice_number:varchar,customer_name:varchar,customer_email:varchar,customer_phone:varchar,customer_address:varchar,sent_at:datetime,approval_status:varchar`

**`acc_journal_entries`** — `id:integer!,journal_id:integer,entry_number:varchar,entry_date:date,gl_account_id:integer,entry_type:varchar!,amount:numeric!,reference_type:varchar,reference_id:integer,description:text,status:varchar!,created_by:integer,posted_at:datetime,approved_by:integer,notes:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,fiscal_year_id:integer,reference:varchar,currency:varchar!,exchange_rate:numeric!,contract_id:integer,date:date,debit_amount:numeric,credit_amount:numeric,source_type:varchar,invoice_id:integer,type:varchar,debit:numeric!,credit:numeric!`

**`acc_journal_entry_lines`** — `id:integer!,entry_id:integer,account_id:integer,description:text,debit:numeric!,credit:numeric!,currency:varchar,currency_amount:numeric,created_at:datetime,updated_at:datetime,amount_currency:numeric!,match_ref:varchar`

**`acc_journals`** — `id:integer!,name:varchar!,code:varchar!,type:varchar!,currency:varchar!,default_account_id:integer,is_active:tinyint!,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`acc_open_banking_connections`** — `id:integer!,bank_name:varchar!,bank_code:varchar,status:varchar!,access_token:text,refresh_token:text,token_expires_at:datetime,last_synced_at:datetime,external_account_ids:text,error_message:text,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`acc_openbanking_connections`** — `id:integer!,bank_account_id:integer,provider:varchar,access_token:text,refresh_token:text,token_expires_at:datetime,requisition_id:varchar,status:varchar!,created_by:integer,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`acc_openbanking_sync_logs`** — `id:integer!,connection_id:integer,synced_at:datetime,transactions_fetched:integer!,status:varchar!,error_message:text,created_at:datetime,updated_at:datetime`

**`acc_operation_templates`** — `id:integer!,code:varchar!,label:varchar!,nature:varchar!,counterpart_account_code:varchar!,keywords:text,is_active:tinyint!,company_id:integer,created_at:datetime,updated_at:datetime`

**`acc_reconciliation_sessions`** — `id:integer!,bank_account_id:integer,period_start:date!,period_end:date!,status:varchar!,opening_balance:numeric!,closing_balance:numeric!,created_by:integer,completed_at:datetime,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`acc_reconciliations`** — `id:integer!,gl_account_id:integer,reconciliation_date:date!,book_balance:numeric!,bank_balance:numeric!,difference:numeric!,status:varchar!,notes:text,reconciled_by:integer,reconciled_at:datetime,created_at:datetime,updated_at:datetime,deleted_at:datetime,bank_account_id:integer,period_start:date,period_end:date,system_balance:numeric!,bank_statement_balance:numeric!,difference_amount:numeric,statement_balance:numeric,reconciled_balance:numeric`

**`acc_report_templates`** — `id:integer!,name:varchar!,type:varchar,description:text,config:text,is_default:tinyint!,is_active:tinyint!,created_by:integer,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`acc_saved_report_parameters`** — `id:integer!,template_id:integer,name:varchar!,parameters:text,created_by:integer,created_at:datetime,updated_at:datetime`

**`acc_scheduled_reports`** — `id:integer!,template_id:integer,name:varchar!,frequency:varchar!,format:varchar!,recipients:text,is_active:tinyint!,last_run_at:datetime,next_run_at:datetime,created_by:integer,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`acc_tax_entries`** — `id:integer!,tax_rate_id:integer,invoice_id:integer,journal_entry_id:integer,taxable_amount:numeric!,tax_amount:numeric!,period_start:date,period_end:date,type:varchar!,created_at:datetime,updated_at:datetime`

**`acc_tax_rates`** — `id:integer!,name:varchar!,code:varchar,rate:numeric!,type:varchar!,country:varchar,is_active:tinyint!,is_compound:tinyint!,applies_to:varchar,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`acc_tax_settings`** — `id:integer!,tax_name:varchar!,tax_rate:numeric!,tax_type:varchar,gl_account_id:integer,status:varchar!,description:text,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`acc_treasury_alerts`** — `id:integer!,name:varchar!,type:varchar!,threshold_amount:numeric!,days_lookahead:integer!,severity:varchar!,is_active:tinyint!,notification_channels:text,last_triggered_at:datetime,created_by:integer,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`acc_treasury_forecasts`** — `id:integer!,name:varchar!,period_start:date!,period_end:date!,status:varchar!,opening_balance:numeric!,total_inflows:numeric!,total_outflows:numeric!,closing_balance:numeric!,currency:varchar!,notes:text,created_by:integer,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`acc_treasury_lines`** — `id:integer!,forecast_id:integer,category:varchar,flow_type:varchar!,amount:numeric!,description:text,expected_date:date,is_recurring:tinyint!,recurrence_period:varchar,probability:numeric!,actual_amount:numeric,created_at:datetime,updated_at:datetime`

**`acc_treasury_scenarios`** — `id:integer!,forecast_id:integer,name:varchar!,type:varchar!,adjustment_factor:numeric!,scenario_balance:numeric,notes:text,created_at:datetime,updated_at:datetime`

**`acc_vat_declarations`** — `id:integer!,period_type:varchar!,period_year:integer!,period_number:integer!,status:varchar!,total_sales:numeric!,total_purchases:numeric!,vat_collected:numeric!,vat_deductible:numeric!,vat_due:numeric!,submitted_at:datetime,reference:varchar,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`acc_vat_rates`** — `id:integer!,name:varchar!,rate:numeric!,country_code:varchar,applies_from:date,applies_to:date,type:varchar!,is_default:tinyint!,created_at:datetime,updated_at:datetime,deleted_at:datetime,is_active:tinyint!`

**`accounting_approval_steps`** — `id:integer!,approval_id:integer,level:integer!,required_role:varchar,approver_id:integer,approved_at:datetime,action:varchar,comment:text,threshold_amount:numeric,created_at:datetime,updated_at:datetime`

**`accounting_invoice_approvals`** — `id:integer!,tenant_id:integer,invoice_id:integer,invoice_type:varchar!,invoice_number:varchar,amount:numeric!,currency:varchar!,status:varchar!,submitted_by:integer,submitted_at:datetime,approval_chain:text,current_level:integer!,rejection_reason:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,approval_request_id:integer`

**`accounting_payment_schedules`** — `id:integer!,tenant_id:integer,invoice_id:integer,due_date:date!,amount:numeric!,currency:varchar!,payment_method:varchar,status:varchar!,reminder_sent_at:datetime,calendar_event_id:integer,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`asset_impairments`** — `id:integer!,fixed_asset_id:integer,impairment_date:date!,original_cost:numeric!,accumulated_depreciation_before:numeric!,book_value_before:numeric!,fair_value:numeric!,impairment_loss:numeric!,new_book_value:numeric!,impairment_reason:text,journal_entry_id:integer,status:varchar!,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`consolidation_eliminations`** — `id:integer!,consolidation_hierarchy_id:integer,consolidation_period_id:integer,elimination_type:varchar,gl_account_id:integer,debit_amount:numeric!,credit_amount:numeric!,description:text,calculation_method:varchar,is_manual:tinyint!,created_at:datetime,updated_at:datetime`

**`consolidation_entries`** — `id:integer!,consolidation_period_id:integer,company_id:integer,gl_account_id:integer,opening_balance:numeric!,debit_amount:numeric!,credit_amount:numeric!,closing_balance:numeric!,consolidation_adjustment:numeric!,consolidated_amount:numeric!,notes:text,created_at:datetime,updated_at:datetime`

**`consolidation_hierarchies`** — `id:integer!,name:varchar!,description:text,type:varchar,parent_company_id:integer,company_id:integer,ownership_percentage:numeric!,effective_date:date,end_date:date,is_active:tinyint!,metadata:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,status:varchar!`

**`consolidation_periods`** — `id:integer!,consolidation_hierarchy_id:integer,period_start:date!,period_end:date!,frequency:varchar!,status:varchar!,notes:text,consolidated_at:datetime,consolidated_by:integer,consolidation_rules:text,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`cost_allocation_keys`** — `id:integer!,tenant_id:integer,name:varchar!,allocation_method:varchar,source_pool:varchar,target_type:varchar,formula:text,is_active:tinyint!,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`cost_categories`** — `id:integer!,tenant_id:integer,code:varchar,label:varchar!,description:text,color:varchar,is_active:tinyint!,ohada_account_class:varchar,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`cost_entries`** — `id:integer!,tenant_id:integer,category_code:varchar,amount:numeric!,currency:varchar!,amount_xof:numeric!,allocatable_type:varchar,allocatable_id:integer,source_module:varchar,source_type:varchar,source_id:integer,description:text,period:varchar,fiscal_year:integer,cost_driver:varchar,units:numeric,unit_cost:numeric,is_estimated:tinyint!,is_allocated:tinyint!,allocated_at:datetime,created_by:integer,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`cost_rollups`** — `id:integer!,tenant_id:integer,entity_type:varchar,entity_id:integer,entity_name:varchar,period:varchar,capex_total:numeric!,opex_total:numeric!,finex_total:numeric!,riskex_total:numeric!,total_cost:numeric!,currency:varchar!,unit_cost:numeric,margin:numeric,margin_pct:numeric,computed_at:datetime,created_at:datetime,updated_at:datetime`

**`depreciation_entries`** — `id:integer!,depreciation_schedule_id:integer,period_date:date!,depreciation_amount:numeric!,accumulated_depreciation:numeric!,book_value:numeric!,journal_entry_id:integer,status:varchar!,recorded_at:datetime,notes:text,created_at:datetime,updated_at:datetime`

**`depreciation_policies`** — `id:integer!,company_id:integer,policy_name:varchar!,asset_category:varchar,depreciation_method:varchar!,default_useful_life_years:integer!,default_residual_percentage:numeric!,tax_depreciation_method:varchar,tax_useful_life_years:integer,policy_description:text,is_active:tinyint!,effective_from:date,effective_to:date,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`depreciation_schedules`** — `id:integer!,fixed_asset_id:integer,depreciation_method:varchar!,useful_life_years:integer!,residual_value:numeric!,depreciation_start_date:date,depreciation_end_date:date,annual_depreciation_amount:numeric!,accumulated_depreciation:numeric!,book_value:numeric!,depreciation_expense_account_id:integer,accumulated_depreciation_account_id:integer,status:varchar!,depreciation_method_details:text,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`intercompany_clearances`** — `id:integer!,sending_company_id:integer,receiving_company_id:integer,transaction_date:date!,transaction_type:varchar,amount:numeric!,currency:varchar!,status:varchar!,sending_gl_account_id:integer,receiving_gl_account_id:integer,description:text,due_date:date,cleared_at:datetime,documents:text,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`intercompany_reconciliations`** — `id:integer!,company_a_id:integer,company_b_id:integer,reconciliation_date:date!,company_a_balance:numeric!,company_b_balance:numeric!,difference:numeric!,status:varchar!,reconciliation_notes:text,reconciled_at:datetime,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`tax_compliance_reports`** — `id:integer!,company_id:integer,tax_jurisdiction_id:integer,report_period_start:date!,report_period_end:date!,status:varchar!,tax_data:text,compliance_checks:text,notes:text,total_tax_liability:numeric!,total_tax_paid:numeric!,tax_due_or_refund:numeric!,filed_at:datetime,filing_reference_number:varchar,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`tax_compliance_rules`** — `id:integer!,tax_jurisdiction_id:integer,rule_name:varchar!,rule_type:varchar,rule_conditions:text,rule_actions:text,description:text,requires_documentation:tinyint!,effective_from:date,effective_to:date,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`tax_filing_templates`** — `id:integer!,tax_jurisdiction_id:integer,filing_form_number:varchar,filing_type:varchar,field_mappings:text,calculation_rules:text,validation_rules:text,filing_instructions:text,last_updated:date,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`tax_jurisdictions`** — `id:integer!,jurisdiction_code:varchar,jurisdiction_name:varchar!,country_code:varchar,region_code:varchar,tax_type:varchar,tax_rate:numeric!,effective_from:date,effective_to:date,tax_calculation_method:varchar,tax_rules:text,exemptions:text,filing_requirements:text,filing_due_date:date,is_active:tinyint!,created_at:datetime,updated_at:datetime,deleted_at:datetime`

### CRM (43 tables)

**`crm_accounts`** — `id:integer!,owner_id:integer,name:varchar!,type:varchar!,industry:varchar,website:varchar,phone:varchar,email:varchar,employee_count:integer,annual_revenue:numeric,currency:varchar!,billing_address:varchar,billing_city:varchar,billing_country:varchar,description:text,custom_fields:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,status:varchar,revenue:numeric,company_id:integer`

**`crm_activities`** — `id:integer!,contact_id:integer,user_id:integer!,type:varchar!,subject:varchar,description:text,scheduled_at:datetime,completed_at:datetime,created_at:datetime,updated_at:datetime,deleted_at:datetime,title:varchar,subject_type:varchar,subject_id:integer,status:varchar!,due_date:datetime,due_at:datetime,done_at:datetime,company_id:integer`

**`crm_ai_agent_runs`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,agent_id:integer,entity_type:varchar,entity_id:integer,status:varchar!,result:text,error_message:text,executed_at:datetime,duration_ms:integer`

**`crm_ai_agents`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,name:varchar,trigger_type:varchar,action_type:varchar,config:text,is_active:tinyint!,created_by:integer,description:text,trigger_config:text,action_config:text,conditions:text,last_run_at:datetime,run_count:integer!`

**`crm_call_logs`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,user_id:integer,direction:varchar!,status:varchar!,phone_number:varchar,called_at:datetime,contact_id:integer,lead_id:integer,duration:integer,notes:text,recording_url:varchar`

**`crm_call_recordings`** — `id:integer!,call_id:integer!,recording_url:varchar,duration_seconds:integer,transcript_text:text,ai_summary:text,status:varchar!,company_id:integer!,created_at:datetime,updated_at:datetime`

**`crm_campaign_actions`** — `id:integer!,campaign_id:integer!,enrollment_id:integer,action_type:varchar!,status:varchar!,payload:text,scheduled_at:datetime,executed_at:datetime,error_message:text,created_at:datetime,updated_at:datetime`

**`crm_campaign_analytics`** — `id:integer!,campaign_id:integer!,date:date!,impressions:integer!,opens:integer!,clicks:integer!,conversions:integer!,open_rate:numeric!,click_rate:numeric!,conversion_rate:numeric!,revenue_generated:numeric!,created_at:datetime,updated_at:datetime`

**`crm_campaign_enrollments`** — `id:integer!,campaign_id:integer!,enrollable_type:varchar!,enrollable_id:integer!,current_stage:varchar,status:varchar!,enrolled_at:datetime,completed_at:datetime,email_opens:integer!,email_clicks:integer!,sms_reads:integer!,interactions:integer!,metadata:text,created_at:datetime,updated_at:datetime`

**`crm_campaign_stages`** — `id:integer!,campaign_id:integer!,name:varchar!,sequence:integer!,delay_days:integer!,condition_type:varchar,conditions:text,actions:text,created_at:datetime,updated_at:datetime`

**`crm_campaigns`** — `id:integer!,name:varchar!,description:text,type:varchar!,status:varchar!,owner_id:integer,target_count:integer!,enrolled_count:integer!,converted_count:integer!,conversion_rate:numeric!,start_date:datetime,end_date:datetime,channels:text,segments:text,metadata:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,company_id:integer`

**`crm_companies`** — `id:integer!,name:varchar!,website:varchar,industry:varchar,phone:varchar,email:varchar,notes:text,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`crm_contacts`** — `id:integer!,account_id:integer,owner_id:integer,first_name:varchar!,last_name:varchar!,email:varchar,phone:varchar,mobile:varchar,job_title:varchar,department:varchar,linkedin_url:varchar,source:varchar,status:varchar!,notes:text,custom_fields:text,created_at:datetime,updated_at:datetime,deleted_at:datetime,company_id:integer,archived_at:datetime,merged_into_id:integer,lead_id:integer,tenant_id:varchar`

**`crm_customers`** — `id:integer!,name:varchar!,email:varchar!,phone:varchar,company:varchar,status:varchar!,tier:varchar!,lifetime_value:numeric!,created_by:integer,created_at:datetime,updated_at:datetime`

**`crm_email_sequence_enrollments`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,sequence_id:integer,contact_id:integer,current_step:integer!,status:varchar!,enrolled_at:datetime,completed_at:datetime`

**`crm_email_sequences`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,name:varchar,description:varchar,trigger_type:varchar,is_active:tinyint!,created_by:integer,trigger:varchar,status:varchar!,trigger_config:text,trigger_event:varchar,enabled:tinyint!`

**`crm_engagement_signals`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,opportunity_id:integer,signal_type:varchar,score_impact:integer!,description:text,source:varchar!,occurred_at:datetime,activity_id:integer`

**`crm_forecasts`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,pipeline_id:integer,user_id:integer,period:varchar,amount:numeric,weighted_amount:numeric,ai_prediction:numeric,forecast_amount:numeric!,commit_amount:numeric!,best_case:numeric!,pipeline_total:numeric!,confidence_pct:integer!,generated_at:datetime`

**`crm_lead_status_logs`** — `id:integer!,opportunity_id:integer,from_status:varchar,to_status:varchar!,reason:text,metadata:text,created_by:integer,created_at:datetime,updated_at:datetime`

**`crm_leads`** — `id:integer!,tenant_id:integer,first_name:varchar,last_name:varchar,email:varchar,phone:varchar,company:varchar,status:varchar!,source:varchar,score:integer!,assigned_to:integer,created_at:datetime,updated_at:datetime,deleted_at:datetime,title:varchar,converted_at:datetime,notes:text,description:text,contact_id:integer,opportunity_id:integer,owner_id:integer,estimated_value:numeric,currency:varchar,company_id:integer,converted_to_contact_id:integer`

**`crm_opportunities`** — `id:integer!,pipeline_id:integer,account_id:integer,contact_id:integer,owner_id:integer,name:varchar!,stage:varchar!,probability:integer!,amount:numeric!,currency:varchar!,expected_close_date:date,status:varchar!,description:text,custom_fields:text,closed_at:datetime,created_at:datetime,updated_at:datetime,deleted_at:datetime,territory_id:integer,title:varchar,lost_reason:varchar,tenant_id:integer`

**`crm_opportunity_history`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,opportunity_id:integer,field:varchar,old_value:text,new_value:text,changed_by:integer,changed_at:datetime,field_name:varchar`

**`crm_opportunity_scores`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,opportunity_id:integer,score:numeric!,factors:text,scored_at:datetime,total_score:numeric!,grade:varchar!,engagement_score:integer!,fit_score:integer!,velocity_score:integer!,history_score:integer!,win_probability:numeric!,score_breakdown:text,signals_used:text`

**`crm_pipeline_snapshots`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,pipeline_id:integer,snapshot_date:date,total_value:numeric!,deal_count:integer!,avg_deal_size:numeric!,stage_data:text`

**`crm_pipelines`** — `id:integer!,name:varchar!,is_default:tinyint!,stages:text!,created_at:datetime,updated_at:datetime,company_id:integer`

**`crm_product_bundles`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,items:text,total_price:numeric!,discount_pct:numeric!,is_active:tinyint!,created_by:integer`

**`crm_quote_lines`** — `id:integer!,quote_id:integer!,description:varchar,quantity:numeric!,unit_price:numeric!,total_price:numeric!,created_at:datetime,updated_at:datetime,product_id:integer,product_bundle_id:integer,discount_pct:numeric!,tax_rate:numeric!,line_total:numeric!,sort_order:integer!`

**`crm_quotes`** — `id:integer!,tenant_id:integer,reference:varchar,opportunity_id:integer,contact_id:integer,status:varchar!,total_amount:numeric!,currency:varchar!,valid_until:date,created_at:datetime,updated_at:datetime,deleted_at:datetime,subtotal:numeric!,discount_amount:numeric!,tax_amount:numeric!,total:numeric!,notes:text`

**`crm_revenue_anomalies`** — `id:integer!,anomaly_type:varchar!,metric_name:varchar!,dimension:varchar,dimension_value:varchar,detected_value:numeric!,expected_value:numeric!,deviation_pct:numeric!,severity:varchar!,status:varchar!,explanation:text,detected_at:datetime,created_at:datetime,updated_at:datetime,company_id:integer`

**`crm_revenue_insights`** — `id:integer!,insight_type:varchar!,category:varchar!,title:varchar!,description:text,data:text,impact_score:integer!,status:varchar!,relevant_user_id:integer,insight_generated_at:datetime,created_at:datetime,updated_at:datetime,deleted_at:datetime,company_id:integer`

**`crm_revenue_trends`** — `id:integer!,metric_name:varchar!,dimension:varchar,dimension_value:varchar,period_start:date!,period_end:date!,current_value:numeric!,previous_value:numeric!,change_pct:numeric!,trend_direction:varchar,data_points_count:integer!,created_at:datetime,updated_at:datetime,company_id:integer`

**`crm_scoring_rules`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,name:varchar,entity_type:varchar,condition_field:varchar,condition_operator:varchar,condition_value:varchar,points:integer!,category:varchar!,weight:integer!,is_active:tinyint!,sort_order:integer!`

**`crm_sequence_enrollments`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,sequence_id:integer,contact_id:integer,status:varchar!,current_step:integer!,enrolled_at:datetime,completed_at:datetime,enrolled_by:integer,next_send_at:datetime`

**`crm_sequence_steps`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,sequence_id:integer,step_number:integer!,type:varchar!,delay_days:integer!,content:text,order:integer!,subject:varchar,body:text,from_name:varchar,from_email:varchar,step_order:integer,delay_hours:integer,body_html:text`

**`crm_territories`** — `id:integer!,parent_territory_id:integer,assigned_to:integer!,name:varchar!,code:varchar!,description:text,region:varchar,sales_target:numeric!,currency:varchar!,is_active:tinyint!,year_start_date:date,created_at:datetime,updated_at:datetime,rules:text,parent_id:integer,type:varchar!,criteria:text,created_by:integer`

**`crm_territory_assignments`** — `id:integer!,territory_id:integer!,contact_id:integer,account_id:integer,auto_assigned:tinyint!,assignment_notes:text,created_at:datetime,updated_at:datetime,user_id:integer,role:varchar!,assigned_at:datetime`

**`crm_web_form_submissions`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,form_id:integer,form_data:text,ip_address:varchar,lead_id:integer,contact_id:integer,processed_at:datetime,user_agent:varchar`

**`crm_web_forms`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,name:varchar,slug:varchar,fields:text,default_lead_source:varchar,is_active:tinyint!,created_by:integer,create_lead:tinyint!`

**`crm_win_loss_records`** — `id:integer!,tenant_id:integer,data:text,created_at:datetime,updated_at:datetime,opportunity_id:integer,outcome:varchar,reason:varchar,deal_value:numeric,competitor:varchar,sales_cycle_days:integer!,recorded_by:integer,recorded_at:datetime,notes:text`

**`crm_workflow_edges`** — `id:integer!,workflow_id:integer!,from_node_id:varchar!,to_node_id:varchar!,condition:varchar,created_at:datetime,updated_at:datetime`

**`crm_workflow_executions`** — `id:integer!,workflow_id:integer!,subject_type:varchar,subject_id:integer,status:varchar!,started_at:datetime,completed_at:datetime,execution_trace:text,error_message:text,created_at:datetime,updated_at:datetime`

**`crm_workflow_nodes`** — `id:integer!,workflow_id:integer!,node_id:varchar!,type:varchar!,name:varchar!,config:text,position_x:integer!,position_y:integer!,created_at:datetime,updated_at:datetime`

**`crm_workflows`** — `id:integer!,name:varchar!,description:text,trigger_type:varchar!,owner_id:integer,status:varchar!,trigger_config:text,execution_count:integer!,success_count:integer!,failure_count:integer!,created_at:datetime,updated_at:datetime,deleted_at:datetime`

### Sales (6 tables)

**`sales_objectives`** — `id:integer!,tenant_id:integer,scope:varchar!,scope_ref_id:integer,period_start:date!,period_end:date!,target_amount:numeric!,currency:varchar!,proposal_label:varchar,basis:text,growth_rate_percent:numeric,status:varchar!,source:varchar!,created_by:integer,validated_by:integer,validated_at:datetime,created_at:datetime,updated_at:datetime`

**`sales_order_lines`** — `id:integer!,sales_order_id:integer!,product_id:integer,product_name:varchar,product_sku:varchar,quantity:numeric!,unit_price:numeric!,discount_pct:numeric!,tax_rate:numeric!,line_total:numeric!,description:text,sort_order:integer!,created_at:datetime,updated_at:datetime,discount_percent:numeric!`

**`sales_orders`** — `id:integer!,tenant_id:integer!,reference:varchar!,contact_id:integer,account_id:integer,opportunity_id:integer,status:varchar!,currency:varchar!,subtotal:numeric!,discount_amount:numeric!,tax_amount:numeric!,total:numeric!,notes:text,shipping_address:text,expected_delivery_date:date,confirmed_at:datetime,cancelled_at:datetime,created_by:integer!,created_at:datetime,updated_at:datetime,deleted_at:datetime,deposit_percent:numeric,deposit_required_amount:numeric,deposit_invoice_id:integer,balance_invoice_id:integer,sales_rep_id:integer`

**`sales_quotations`** — `id:integer!,tenant_id:integer!,reference:varchar!,contact_id:integer,account_id:integer,status:varchar!,currency:varchar!,valid_until:date,subtotal:numeric!,discount_amount:numeric!,tax_amount:numeric!,total:numeric!,notes:text,converted_to_order_id:integer,created_by:integer!,created_at:datetime,updated_at:datetime,deleted_at:datetime`

**`sales_recurring_order_template_lines`** — `id:integer!,recurring_order_template_id:integer!,product_id:integer,description:varchar!,quantity:numeric!,unit_price:numeric!,discount_percent:numeric!,tax_rate:numeric!,sequence:integer!,created_at:datetime,updated_at:datetime`

**`sales_recurring_order_templates`** — `id:integer!,tenant_id:integer!,reference:varchar!,name:varchar!,contact_id:integer,account_id:integer,currency:varchar!,recurrence:varchar!,next_run_at:date!,last_run_at:date,is_active:tinyint!,notes:text,created_by:integer,created_at:datetime,updated_at:datetime,deleted_at:datetime`

