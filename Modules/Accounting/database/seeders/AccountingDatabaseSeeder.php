<?php

namespace Modules\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Chantier 36 — replaces the previous generic ~76-account SYSCOHADA-style
 * chart with the real chart of accounts the user provided for their own
 * textile/import/EPI business in Madagascar
 * (PLAN_DE_COMPTES_OHADA_TEXTILE_MADAGASCAR.xlsx — 202 real accounts across
 * SYSCOHADA classes 1-8), plus 6 standard SYSCOHADA accounts the curated
 * file didn't spell out but the app's existing code genuinely needs
 * (`422` Personnel/rémunérations dues, and the 5 real "variation des
 * stocks" compensation accounts `6031`/`6032`/`6033`/`734`/`736` — official
 * numbering, not invented, matching the SYSCOHADA-revised nomenclature).
 * Real hierarchy is populated via `parent_id` (a column `ChartOfAccount`
 * already supported but the previous flat seed never used).
 *
 * This is still a pragmatic adaptation, not a certified official
 * accountant-reviewed reference — the source file's own docblock says as
 * much (see the GUIDE/NOTES sheets) — have an accountant review before real
 * production bookkeeping, same caveat already carried by every prior
 * chantier that touched this chart.
 *
 * `Modules\Accounting\database\seeders\OperationTemplateSeeder`-equivalent
 * data below (see `seedOperationTemplates()`) uses only the *names* of the
 * 54 real cash-operation categories the user's own historical caisse file
 * groups its ~3,447 real transactions into — never the transaction rows
 * themselves, which contain real employee/supplier/amount data and were
 * never copied into this repository (see CLAUDE.md's Chantier 36 entry).
 */
class AccountingDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedChartOfAccounts();
        $this->seedDefaultJournals();
        $this->seedOperationTemplates();
    }

    private function seedChartOfAccounts(): void
    {
        if (DB::table('acc_chart_of_accounts')->exists()) {
            return;
        }

        $now = now();

        // Ordered so every account's parent (by numeric-prefix nesting,
        // matching the source file's own NIVEAU column: Classe > Compte (2
        // chiffres) > Sous-compte (3 chiffres) > Détail (4+ chiffres))
        // always appears earlier in this array — verified programmatically
        // before writing this file, not assumed.
        $accounts = [
            ['code' => '11', 'name' => 'Réserves', 'type' => 'equity', 'parent_code' => null],
            ['code' => '12', 'name' => 'Report à nouveau', 'type' => 'equity', 'parent_code' => null],
            ['code' => '13', 'name' => 'Résultat net de l\'exercice', 'type' => 'equity', 'parent_code' => null],
            ['code' => '16', 'name' => 'Emprunts et dettes assimilées', 'type' => 'liability', 'parent_code' => null],
            ['code' => '18', 'name' => 'Dettes liées à des participations et comptes de liaison', 'type' => 'liability', 'parent_code' => null],
            ['code' => '19', 'name' => 'Provisions pour risques et charges', 'type' => 'liability', 'parent_code' => null],
            ['code' => '21', 'name' => 'Immobilisations incorporelles', 'type' => 'asset', 'parent_code' => null],
            ['code' => '22', 'name' => 'Terrains', 'type' => 'asset', 'parent_code' => null],
            ['code' => '23', 'name' => 'Bâtiments, installations techniques et agencements', 'type' => 'asset', 'parent_code' => null],
            ['code' => '24', 'name' => 'Matériel, mobilier et actifs biologiques', 'type' => 'asset', 'parent_code' => null],
            ['code' => '25', 'name' => 'Avances et acomptes versés sur immobilisations', 'type' => 'asset', 'parent_code' => null],
            ['code' => '27', 'name' => 'Autres immobilisations financières', 'type' => 'asset', 'parent_code' => null],
            ['code' => '28', 'name' => 'Amortissements', 'type' => 'asset', 'parent_code' => null],
            ['code' => '29', 'name' => 'Dépréciations des immobilisations', 'type' => 'asset', 'parent_code' => null],
            ['code' => '31', 'name' => 'Marchandises', 'type' => 'asset', 'parent_code' => null],
            ['code' => '32', 'name' => 'Matières premières et fournitures liées', 'type' => 'asset', 'parent_code' => null],
            ['code' => '33', 'name' => 'Autres approvisionnements', 'type' => 'asset', 'parent_code' => null],
            ['code' => '34', 'name' => 'Produits en cours', 'type' => 'asset', 'parent_code' => null],
            ['code' => '35', 'name' => 'Services en cours', 'type' => 'asset', 'parent_code' => null],
            ['code' => '36', 'name' => 'Produits finis', 'type' => 'asset', 'parent_code' => null],
            ['code' => '38', 'name' => 'Stocks en cours de route, en consignation ou en dépôt', 'type' => 'asset', 'parent_code' => null],
            ['code' => '39', 'name' => 'Dépréciations des stocks', 'type' => 'asset', 'parent_code' => null],
            ['code' => '40', 'name' => 'Fournisseurs et comptes rattachés', 'type' => 'liability', 'parent_code' => null],
            ['code' => '41', 'name' => 'Clients et comptes rattachés', 'type' => 'asset', 'parent_code' => null],
            ['code' => '42', 'name' => 'Personnel', 'type' => 'liability', 'parent_code' => null],
            ['code' => '43', 'name' => 'Organismes sociaux', 'type' => 'liability', 'parent_code' => null],
            ['code' => '44', 'name' => 'État et collectivités publiques', 'type' => 'liability', 'parent_code' => null],
            ['code' => '46', 'name' => 'Apporteurs, associés et groupe', 'type' => 'liability', 'parent_code' => null],
            ['code' => '47', 'name' => 'Débiteurs et créditeurs divers', 'type' => 'asset', 'parent_code' => null],
            ['code' => '48', 'name' => 'Créances et dettes hors activités ordinaires', 'type' => 'asset', 'parent_code' => null],
            ['code' => '49', 'name' => 'Dépréciations et provisions pour risques à court terme (Tiers)', 'type' => 'liability', 'parent_code' => null],
            ['code' => '52', 'name' => 'Banques', 'type' => 'asset', 'parent_code' => null],
            ['code' => '53', 'name' => 'Établissements financiers et assimilés', 'type' => 'asset', 'parent_code' => null],
            ['code' => '55', 'name' => 'Instruments de monnaie électronique', 'type' => 'asset', 'parent_code' => null],
            ['code' => '57', 'name' => 'Caisse', 'type' => 'asset', 'parent_code' => null],
            ['code' => '58', 'name' => 'Régies d\'avances, accréditifs et virements internes', 'type' => 'asset', 'parent_code' => null],
            ['code' => '59', 'name' => 'Dépréciations et provisions pour risques à court terme (Trésorerie)', 'type' => 'asset', 'parent_code' => null],
            ['code' => '61', 'name' => 'Transports', 'type' => 'expense', 'parent_code' => null],
            ['code' => '62', 'name' => 'Services extérieurs', 'type' => 'expense', 'parent_code' => null],
            ['code' => '63', 'name' => 'Autres services extérieurs', 'type' => 'expense', 'parent_code' => null],
            ['code' => '64', 'name' => 'Impôts et taxes', 'type' => 'expense', 'parent_code' => null],
            ['code' => '65', 'name' => 'Autres charges', 'type' => 'expense', 'parent_code' => null],
            ['code' => '66', 'name' => 'Charges de personnel', 'type' => 'expense', 'parent_code' => null],
            ['code' => '67', 'name' => 'Frais financiers et charges assimilées', 'type' => 'expense', 'parent_code' => null],
            ['code' => '68', 'name' => 'Dotations aux amortissements', 'type' => 'expense', 'parent_code' => null],
            ['code' => '69', 'name' => 'Dotations aux provisions et aux dépréciations', 'type' => 'expense', 'parent_code' => null],
            ['code' => '71', 'name' => 'Subventions d\'exploitation', 'type' => 'revenue', 'parent_code' => null],
            ['code' => '75', 'name' => 'Autres produits', 'type' => 'revenue', 'parent_code' => null],
            ['code' => '77', 'name' => 'Revenus financiers et produits assimilés', 'type' => 'revenue', 'parent_code' => null],
            ['code' => '78', 'name' => 'Transferts de charges', 'type' => 'revenue', 'parent_code' => null],
            ['code' => '79', 'name' => 'Reprises de provisions, dépréciations et autres', 'type' => 'revenue', 'parent_code' => null],
            ['code' => '81', 'name' => 'Valeurs comptables des cessions d\'immobilisations', 'type' => 'expense', 'parent_code' => null],
            ['code' => '82', 'name' => 'Produits des cessions d\'immobilisations', 'type' => 'revenue', 'parent_code' => null],
            ['code' => '83', 'name' => 'Charges hors activités ordinaires', 'type' => 'expense', 'parent_code' => null],
            ['code' => '84', 'name' => 'Produits hors activités ordinaires', 'type' => 'revenue', 'parent_code' => null],
            ['code' => '87', 'name' => 'Participation des travailleurs', 'type' => 'expense', 'parent_code' => null],
            ['code' => '89', 'name' => 'Impôts sur le résultat', 'type' => 'expense', 'parent_code' => null],
            ['code' => '101', 'name' => 'Capital social', 'type' => 'equity', 'parent_code' => null],
            ['code' => '104', 'name' => 'Compte de l\'exploitant', 'type' => 'equity', 'parent_code' => null],
            ['code' => '111', 'name' => 'Réserve légale', 'type' => 'equity', 'parent_code' => '11'],
            ['code' => '131', 'name' => 'Résultat net : Bénéfice', 'type' => 'equity', 'parent_code' => '13'],
            ['code' => '139', 'name' => 'Résultat net : Perte', 'type' => 'equity', 'parent_code' => '13'],
            ['code' => '162', 'name' => 'Emprunts et dettes auprès des établissements de crédit', 'type' => 'liability', 'parent_code' => '16'],
            ['code' => '165', 'name' => 'Dépôts et cautionnements reçus', 'type' => 'liability', 'parent_code' => '16'],
            ['code' => '194', 'name' => 'Provisions pour pertes de change', 'type' => 'liability', 'parent_code' => '19'],
            ['code' => '214', 'name' => 'Marques', 'type' => 'asset', 'parent_code' => '21'],
            ['code' => '216', 'name' => 'Droit au bail', 'type' => 'asset', 'parent_code' => '21'],
            ['code' => '217', 'name' => 'Investissements de création', 'type' => 'asset', 'parent_code' => '21'],
            ['code' => '311', 'name' => 'Marchandises A - Vêtements prêt-à-porter', 'type' => 'asset', 'parent_code' => '31'],
            ['code' => '312', 'name' => 'Marchandises B - Chaussures', 'type' => 'asset', 'parent_code' => '31'],
            ['code' => '313', 'name' => 'Marchandises C - Équipements de protection individuelle (EPI)', 'type' => 'asset', 'parent_code' => '31'],
            ['code' => '314', 'name' => 'Marchandises D - Accessoires vestimentaires', 'type' => 'asset', 'parent_code' => '31'],
            ['code' => '321', 'name' => 'Matières A - Tissus et textiles', 'type' => 'asset', 'parent_code' => '32'],
            ['code' => '322', 'name' => 'Matières B - Cuirs et matières techniques', 'type' => 'asset', 'parent_code' => '32'],
            ['code' => '323', 'name' => 'Fournitures liées', 'type' => 'asset', 'parent_code' => '32'],
            ['code' => '331', 'name' => 'Matières consommables', 'type' => 'asset', 'parent_code' => '33'],
            ['code' => '361', 'name' => 'Produits finis - Vêtements confectionnés à façon', 'type' => 'asset', 'parent_code' => '36'],
            ['code' => '362', 'name' => 'Produits finis - EPI confectionnés à façon', 'type' => 'asset', 'parent_code' => '36'],
            ['code' => '381', 'name' => 'Marchandises en cours de route (fret maritime/aérien)', 'type' => 'asset', 'parent_code' => '38'],
            ['code' => '382', 'name' => 'Marchandises en attente de dédouanement', 'type' => 'asset', 'parent_code' => '38'],
            ['code' => '383', 'name' => 'Matières et articles en dépôt chez un façonnier', 'type' => 'asset', 'parent_code' => '38'],
            ['code' => '402', 'name' => 'Fournisseurs, effets à payer', 'type' => 'liability', 'parent_code' => '40'],
            ['code' => '408', 'name' => 'Fournisseurs, factures non parvenues', 'type' => 'liability', 'parent_code' => '40'],
            ['code' => '409', 'name' => 'Fournisseurs débiteurs', 'type' => 'asset', 'parent_code' => '40'],
            ['code' => '412', 'name' => 'Clients, effets à recevoir', 'type' => 'asset', 'parent_code' => '41'],
            ['code' => '416', 'name' => 'Créances clients litigieuses ou douteuses', 'type' => 'asset', 'parent_code' => '41'],
            ['code' => '418', 'name' => 'Clients, produits à recevoir (factures à établir)', 'type' => 'asset', 'parent_code' => '41'],
            ['code' => '419', 'name' => 'Clients créditeurs', 'type' => 'liability', 'parent_code' => '41'],
            ['code' => '421', 'name' => 'Personnel, avances et acomptes', 'type' => 'asset', 'parent_code' => '42'],
            // Chantier 36 addition — real SYSCOHADA code (not in the curated
            // source file, which only lists 421), needed because
            // PayrollIntegrationService::postPayslipsToAccounting() posts a
            // real "salaire net à payer" liability line, and 421 in the new
            // chart means something different (advances to staff, an asset).
            ['code' => '422', 'name' => 'Personnel, rémunérations dues', 'type' => 'liability', 'parent_code' => '42'],
            ['code' => '441', 'name' => 'État, impôt sur les bénéfices (IR/IS)', 'type' => 'liability', 'parent_code' => '44'],
            ['code' => '443', 'name' => 'État, TVA facturée', 'type' => 'liability', 'parent_code' => '44'],
            ['code' => '445', 'name' => 'État, TVA récupérable', 'type' => 'asset', 'parent_code' => '44'],
            ['code' => '447', 'name' => 'État, impôts retenus à la source', 'type' => 'liability', 'parent_code' => '44'],
            ['code' => '462', 'name' => 'Associés, comptes courants', 'type' => 'liability', 'parent_code' => '46'],
            ['code' => '467', 'name' => 'Apporteurs, restant dû sur capital appelé', 'type' => 'asset', 'parent_code' => '46'],
            ['code' => '476', 'name' => 'Charges constatées d\'avance', 'type' => 'asset', 'parent_code' => '47'],
            ['code' => '478', 'name' => 'Écarts de conversion - Actif', 'type' => 'asset', 'parent_code' => '47'],
            ['code' => '479', 'name' => 'Écarts de conversion - Passif', 'type' => 'liability', 'parent_code' => '47'],
            ['code' => '491', 'name' => 'Dépréciations des comptes clients', 'type' => 'asset', 'parent_code' => '49'],
            ['code' => '601', 'name' => 'Achats de marchandises', 'type' => 'expense', 'parent_code' => null],
            ['code' => '602', 'name' => 'Achats de matières premières et fournitures liées', 'type' => 'expense', 'parent_code' => null],
            ['code' => '604', 'name' => 'Achats stockés de matières et fournitures consommables', 'type' => 'expense', 'parent_code' => null],
            ['code' => '605', 'name' => 'Autres achats', 'type' => 'expense', 'parent_code' => null],
            ['code' => '608', 'name' => 'Achats d\'emballages', 'type' => 'expense', 'parent_code' => null],
            ['code' => '612', 'name' => 'Transports sur ventes', 'type' => 'expense', 'parent_code' => '61'],
            ['code' => '618', 'name' => 'Autres frais de transport', 'type' => 'expense', 'parent_code' => '61'],
            ['code' => '621', 'name' => 'Sous-traitance générale', 'type' => 'expense', 'parent_code' => '62'],
            ['code' => '622', 'name' => 'Locations et charges locatives', 'type' => 'expense', 'parent_code' => '62'],
            ['code' => '624', 'name' => 'Entretien, réparations et maintenance', 'type' => 'expense', 'parent_code' => '62'],
            ['code' => '625', 'name' => 'Primes d\'assurance', 'type' => 'expense', 'parent_code' => '62'],
            ['code' => '627', 'name' => 'Publicité, publications, relations publiques', 'type' => 'expense', 'parent_code' => '62'],
            ['code' => '628', 'name' => 'Frais de télécommunications', 'type' => 'expense', 'parent_code' => '62'],
            ['code' => '631', 'name' => 'Frais bancaires', 'type' => 'expense', 'parent_code' => '63'],
            ['code' => '632', 'name' => 'Rémunérations d\'intermédiaires et de conseils', 'type' => 'expense', 'parent_code' => '63'],
            ['code' => '635', 'name' => 'Cotisations', 'type' => 'expense', 'parent_code' => '63'],
            ['code' => '637', 'name' => 'Rémunération de personnel extérieur à l\'entité', 'type' => 'expense', 'parent_code' => '63'],
            ['code' => '638', 'name' => 'Autres charges externes', 'type' => 'expense', 'parent_code' => '63'],
            ['code' => '648', 'name' => 'Autres impôts et taxes', 'type' => 'expense', 'parent_code' => '64'],
            ['code' => '651', 'name' => 'Pertes sur créances clients et autres débiteurs', 'type' => 'expense', 'parent_code' => '65'],
            ['code' => '658', 'name' => 'Charges diverses', 'type' => 'expense', 'parent_code' => '65'],
            ['code' => '661', 'name' => 'Rémunérations directes versées au personnel national', 'type' => 'expense', 'parent_code' => '66'],
            ['code' => '663', 'name' => 'Indemnités forfaitaires versées au personnel', 'type' => 'expense', 'parent_code' => '66'],
            ['code' => '664', 'name' => 'Charges sociales', 'type' => 'expense', 'parent_code' => '66'],
            ['code' => '671', 'name' => 'Intérêts des emprunts', 'type' => 'expense', 'parent_code' => '67'],
            ['code' => '676', 'name' => 'Pertes de change', 'type' => 'expense', 'parent_code' => '67'],
            ['code' => '701', 'name' => 'Ventes de marchandises', 'type' => 'revenue', 'parent_code' => null],
            ['code' => '702', 'name' => 'Ventes de produits finis', 'type' => 'revenue', 'parent_code' => null],
            ['code' => '706', 'name' => 'Services vendus', 'type' => 'revenue', 'parent_code' => null],
            ['code' => '707', 'name' => 'Produits accessoires', 'type' => 'revenue', 'parent_code' => null],
            // Chantier 36 additions — real SYSCOHADA "variation des stocks"
            // compensation accounts (official numbering: 603x compensates
            // class-3 purchased-goods stock, 73x compensates class-3
            // produced-goods stock). Not in the curated source file, but
            // needed to close a real bilan/résultat reconciliation gap
            // (Chantier 13) between a purchase/production posted to class 6
            // and the matching stock movement on the balance sheet — see
            // DefaultDataSeeder's category → account mapping below.
            ['code' => '734', 'name' => 'Variation des stocks de produits en cours', 'type' => 'revenue', 'parent_code' => null],
            ['code' => '736', 'name' => 'Variation des stocks de produits finis', 'type' => 'revenue', 'parent_code' => null],
            ['code' => '758', 'name' => 'Produits divers', 'type' => 'revenue', 'parent_code' => '75'],
            ['code' => '773', 'name' => 'Escomptes obtenus', 'type' => 'revenue', 'parent_code' => '77'],
            ['code' => '776', 'name' => 'Gains de change', 'type' => 'revenue', 'parent_code' => '77'],
            ['code' => '831', 'name' => 'Charges HAO constatées', 'type' => 'expense', 'parent_code' => '83'],
            ['code' => '2131', 'name' => 'Logiciels', 'type' => 'asset', 'parent_code' => '21'],
            ['code' => '2313', 'name' => 'Bâtiments administratifs et commerciaux', 'type' => 'asset', 'parent_code' => '23'],
            ['code' => '2345', 'name' => 'Aménagements et agencements des bâtiments', 'type' => 'asset', 'parent_code' => '23'],
            ['code' => '2411', 'name' => 'Matériel industriel', 'type' => 'asset', 'parent_code' => '24'],
            ['code' => '2413', 'name' => 'Matériel commercial', 'type' => 'asset', 'parent_code' => '24'],
            ['code' => '2441', 'name' => 'Matériel de bureau', 'type' => 'asset', 'parent_code' => '24'],
            ['code' => '2442', 'name' => 'Matériel informatique', 'type' => 'asset', 'parent_code' => '24'],
            ['code' => '2444', 'name' => 'Mobilier de bureau', 'type' => 'asset', 'parent_code' => '24'],
            ['code' => '2451', 'name' => 'Matériel automobile', 'type' => 'asset', 'parent_code' => '24'],
            ['code' => '2458', 'name' => 'Autres (vélo, mobylette, moto)', 'type' => 'asset', 'parent_code' => '24'],
            ['code' => '2751', 'name' => 'Dépôts pour loyers d\'avance', 'type' => 'asset', 'parent_code' => '27'],
            ['code' => '2756', 'name' => 'Cautionnements sur marchés publics', 'type' => 'asset', 'parent_code' => '27'],
            ['code' => '4011', 'name' => 'Fournisseurs - Chine', 'type' => 'liability', 'parent_code' => '40'],
            ['code' => '4012', 'name' => 'Fournisseurs - Europe', 'type' => 'liability', 'parent_code' => '40'],
            ['code' => '4013', 'name' => 'Fournisseurs sous-traitants (façonniers)', 'type' => 'liability', 'parent_code' => '40'],
            ['code' => '4014', 'name' => 'Fournisseurs - Île Maurice', 'type' => 'liability', 'parent_code' => '40'],
            ['code' => '4015', 'name' => 'Fournisseurs - Afrique du Sud', 'type' => 'liability', 'parent_code' => '40'],
            ['code' => '4016', 'name' => 'Fournisseurs - Madagascar (locaux)', 'type' => 'liability', 'parent_code' => '40'],
            ['code' => '4017', 'name' => 'Fournisseurs, retenues de garantie', 'type' => 'liability', 'parent_code' => '40'],
            ['code' => '4091', 'name' => 'Fournisseurs, avances et acomptes versés', 'type' => 'asset', 'parent_code' => '409'],
            ['code' => '4093', 'name' => 'Fournisseurs sous-traitants, avances et acomptes versés', 'type' => 'asset', 'parent_code' => '409'],
            ['code' => '4111', 'name' => 'Clients - Ventes au détail / boutique', 'type' => 'asset', 'parent_code' => '41'],
            ['code' => '4112', 'name' => 'Clients - Grossistes / revendeurs', 'type' => 'asset', 'parent_code' => '41'],
            ['code' => '4114', 'name' => 'Clients - État et collectivités publiques', 'type' => 'asset', 'parent_code' => '41'],
            ['code' => '4116', 'name' => 'Clients, ventes avec réserve de propriété', 'type' => 'asset', 'parent_code' => '41'],
            ['code' => '4117', 'name' => 'Clients, retenues de garantie', 'type' => 'asset', 'parent_code' => '41'],
            ['code' => '4191', 'name' => 'Clients, avances et acomptes reçus', 'type' => 'liability', 'parent_code' => '419'],
            ['code' => '4311', 'name' => 'CNAPS - Caisse Nationale de Prévoyance Sociale', 'type' => 'liability', 'parent_code' => '43'],
            ['code' => '4331', 'name' => 'OSTIE - Organisme sanitaire inter-entreprises', 'type' => 'liability', 'parent_code' => '43'],
            ['code' => '4426', 'name' => 'État, droits de douane', 'type' => 'liability', 'parent_code' => '44'],
            ['code' => '4452', 'name' => 'TVA récupérable sur achats', 'type' => 'asset', 'parent_code' => '445'],
            ['code' => '4453', 'name' => 'TVA récupérable sur transport', 'type' => 'asset', 'parent_code' => '445'],
            ['code' => '4471', 'name' => 'IRSA - Impôt sur les Revenus Salariaux et Assimilés', 'type' => 'liability', 'parent_code' => '447'],
            ['code' => '5211', 'name' => 'Banque - Compte principal MGA', 'type' => 'asset', 'parent_code' => '52'],
            ['code' => '5212', 'name' => 'Banque - Autre compte MGA', 'type' => 'asset', 'parent_code' => '52'],
            ['code' => '5218', 'name' => 'Banque - Compte(s) en devises (USD/EUR)', 'type' => 'asset', 'parent_code' => '52'],
            ['code' => '5521', 'name' => 'Mvola', 'type' => 'asset', 'parent_code' => '55'],
            ['code' => '5522', 'name' => 'Orange Money', 'type' => 'asset', 'parent_code' => '55'],
            ['code' => '5523', 'name' => 'Airtel Money', 'type' => 'asset', 'parent_code' => '55'],
            ['code' => '5711', 'name' => 'Caisse principale / caisse générale', 'type' => 'asset', 'parent_code' => '57'],
            ['code' => '5721', 'name' => 'Caisse secondaire', 'type' => 'asset', 'parent_code' => '57'],
            ['code' => '5722', 'name' => 'Caisse(s) succursale(s) / point(s) de vente', 'type' => 'asset', 'parent_code' => '57'],
            ['code' => '6011', 'name' => 'Achats de marchandises - dans la région (Madagascar/COMESA/SADC)', 'type' => 'expense', 'parent_code' => '601'],
            ['code' => '6012', 'name' => 'Achats de marchandises - hors région (Chine, Europe, etc.)', 'type' => 'expense', 'parent_code' => '601'],
            ['code' => '6015', 'name' => 'Frais sur achats de marchandises', 'type' => 'expense', 'parent_code' => '601'],
            ['code' => '6021', 'name' => 'Achats de matières premières - dans la région', 'type' => 'expense', 'parent_code' => '602'],
            ['code' => '6022', 'name' => 'Achats de matières premières - hors région', 'type' => 'expense', 'parent_code' => '602'],
            ['code' => '6025', 'name' => 'Frais sur achats de matières premières', 'type' => 'expense', 'parent_code' => '602'],
            ['code' => '6031', 'name' => 'Variation des stocks de marchandises', 'type' => 'expense', 'parent_code' => null],
            ['code' => '6032', 'name' => 'Variation des stocks de matières premières et fournitures liées', 'type' => 'expense', 'parent_code' => null],
            ['code' => '6033', 'name' => 'Variation des stocks d\'autres approvisionnements', 'type' => 'expense', 'parent_code' => null],
            ['code' => '6041', 'name' => 'Matières consommables', 'type' => 'expense', 'parent_code' => '604'],
            ['code' => '6051', 'name' => 'Fournitures non stockables - Eau', 'type' => 'expense', 'parent_code' => '605'],
            ['code' => '6052', 'name' => 'Fournitures non stockables - Électricité', 'type' => 'expense', 'parent_code' => '605'],
            ['code' => '6056', 'name' => 'Achats de petit matériel et outillage', 'type' => 'expense', 'parent_code' => '605'],
            ['code' => '6181', 'name' => 'Voyages et déplacements', 'type' => 'expense', 'parent_code' => '618'],
            ['code' => '6222', 'name' => 'Locations de bâtiments', 'type' => 'expense', 'parent_code' => '622'],
            ['code' => '6243', 'name' => 'Maintenance', 'type' => 'expense', 'parent_code' => '624'],
            ['code' => '6251', 'name' => 'Assurances multirisques', 'type' => 'expense', 'parent_code' => '625'],
            ['code' => '6257', 'name' => 'Assurances transports sur ventes', 'type' => 'expense', 'parent_code' => '625'],
            ['code' => '6317', 'name' => 'Frais sur instruments de monnaie électronique', 'type' => 'expense', 'parent_code' => '631'],
            ['code' => '6318', 'name' => 'Autres frais bancaires', 'type' => 'expense', 'parent_code' => '631'],
            ['code' => '6321', 'name' => 'Commissions et courtages sur achats', 'type' => 'expense', 'parent_code' => '632'],
            ['code' => '6324', 'name' => 'Honoraires des professions réglementées', 'type' => 'expense', 'parent_code' => '632'],
            ['code' => '6327', 'name' => 'Rémunérations des autres prestataires de services', 'type' => 'expense', 'parent_code' => '632'],
            ['code' => '6383', 'name' => 'Réceptions', 'type' => 'expense', 'parent_code' => '638'],
            ['code' => '6384', 'name' => 'Missions', 'type' => 'expense', 'parent_code' => '638'],
            ['code' => '6412', 'name' => 'Patentes, licences et taxes annexes', 'type' => 'expense', 'parent_code' => '64'],
            ['code' => '7011', 'name' => 'Ventes de marchandises - dans la région (Madagascar)', 'type' => 'revenue', 'parent_code' => '701'],
            ['code' => '7012', 'name' => 'Ventes de marchandises - hors région (export)', 'type' => 'revenue', 'parent_code' => '701'],
            ['code' => '7015', 'name' => 'Ventes de marchandises - sur internet', 'type' => 'revenue', 'parent_code' => '701'],
            ['code' => '70841', 'name' => 'Locations diverses', 'type' => 'revenue', 'parent_code' => null],
        ];

        $codeToId = [];
        foreach ($accounts as $account) {
            $id = DB::table('acc_chart_of_accounts')->insertGetId([
                'parent_id' => $account['parent_code'] !== null ? ($codeToId[$account['parent_code']] ?? null) : null,
                'code' => $account['code'],
                'name' => $account['name'],
                'type' => $account['type'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $codeToId[$account['code']] = $id;
        }
    }

    private function seedDefaultJournals(): void
    {
        if (DB::table('acc_journals')->exists()) {
            return;
        }

        $now = now();

        DB::table('acc_journals')->insert([
            [
                'code' => 'VTE',
                'name' => 'Journal des ventes',
                'type' => 'sales',
                'default_account_id' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'ACH',
                'name' => 'Journal des achats',
                'type' => 'purchase',
                'default_account_id' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'BNQ',
                'name' => 'Journal de banque',
                'type' => 'bank',
                'default_account_id' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'CAI',
                'name' => 'Journal de caisse',
                'type' => 'cash',
                'default_account_id' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'OD',
                'name' => 'Journal des opérations diverses',
                'type' => 'general',
                'default_account_id' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    /**
     * Chantier 15's original 20-template catalogue, extended (Chantier 36)
     * to the 54 real cash-operation category *names* the user's own
     * historical caisse file (2025-2026) actually groups its ~3,447 real
     * transactions into — only the names, never a transaction row. The 3
     * purely technical categories ("À catégoriser" / "Report à nouveau" /
     * "Annulation d'opération") are deliberately excluded, matching the
     * plan's own scope. `nature` was inferred from which of the source
     * file's real ENTREE/SORTIE columns each category actually populated
     * most often (encaissement/decaissement counts only — never an amount
     * or description), and `counterpart_account_code` resolved against the
     * new chart above.
     */
    private function seedOperationTemplates(): void
    {
        if (DB::table('acc_operation_templates')->exists()) {
            return;
        }

        $now = now();

        $templates = [
            ['code' => 'deplacement', 'label' => 'Déplacement', 'nature' => 'decaissement', 'counterpart_account_code' => '6181', 'keywords' => ['deplacement']],
            ['code' => 'achat_divers', 'label' => 'Achat divers', 'nature' => 'decaissement', 'counterpart_account_code' => '605', 'keywords' => ['achat', 'divers']],
            ['code' => 'vente_au_comptant_boutique', 'label' => 'Vente au comptant / boutique', 'nature' => 'encaissement', 'counterpart_account_code' => '701', 'keywords' => ['vente', 'comptant', 'boutique']],
            ['code' => 'frais_divers', 'label' => 'Frais divers', 'nature' => 'decaissement', 'counterpart_account_code' => '658', 'keywords' => ['frais', 'divers']],
            ['code' => 'telecommunications', 'label' => 'Télécommunications', 'nature' => 'decaissement', 'counterpart_account_code' => '628', 'keywords' => ['telecommunications']],
            ['code' => 'achat_de_matieres_premieres', 'label' => 'Achat de matières premières', 'nature' => 'decaissement', 'counterpart_account_code' => '602', 'keywords' => ['achat', 'matieres', 'premieres']],
            ['code' => 'services_exterieurs', 'label' => 'Services extérieurs', 'nature' => 'decaissement', 'counterpart_account_code' => '62', 'keywords' => ['services', 'exterieurs']],
            ['code' => 'achat_de_consommables', 'label' => 'Achat de consommables', 'nature' => 'decaissement', 'counterpart_account_code' => '604', 'keywords' => ['achat', 'consommables']],
            ['code' => 'mouvement_de_fonds_interne', 'label' => 'Mouvement de fonds interne', 'nature' => 'encaissement', 'counterpart_account_code' => '58', 'keywords' => ['mouvement', 'fonds', 'interne']],
            ['code' => 'retour_de_fonds', 'label' => 'Retour de fonds', 'nature' => 'encaissement', 'counterpart_account_code' => '58', 'keywords' => ['retour', 'fonds']],
            ['code' => 'vente_de_matieres_accessoires', 'label' => 'Vente de matières / accessoires', 'nature' => 'encaissement', 'counterpart_account_code' => '707', 'keywords' => ['vente', 'matieres', 'accessoires']],
            ['code' => 'transport_de_biens', 'label' => 'Transport de biens', 'nature' => 'decaissement', 'counterpart_account_code' => '618', 'keywords' => ['transport', 'biens']],
            ['code' => 'avance_acompte_fournisseur', 'label' => 'Avance / acompte fournisseur', 'nature' => 'decaissement', 'counterpart_account_code' => '4091', 'keywords' => ['avance', 'acompte', 'fournisseur']],
            ['code' => 'vente_de_marchandises', 'label' => 'Vente de marchandises', 'nature' => 'encaissement', 'counterpart_account_code' => '701', 'keywords' => ['vente', 'marchandises']],
            ['code' => 'approvisionnement_de_caisse', 'label' => 'Approvisionnement de caisse', 'nature' => 'encaissement', 'counterpart_account_code' => '58', 'keywords' => ['approvisionnement', 'caisse']],
            ['code' => 'paiement_fournisseur', 'label' => 'Paiement fournisseur', 'nature' => 'decaissement', 'counterpart_account_code' => '40', 'keywords' => ['paiement', 'fournisseur']],
            ['code' => 'achat_de_fournitures_de_bureau', 'label' => 'Achat de fournitures de bureau', 'nature' => 'decaissement', 'counterpart_account_code' => '605', 'keywords' => ['achat', 'fournitures', 'bureau']],
            ['code' => 'salaires', 'label' => 'Salaires', 'nature' => 'decaissement', 'counterpart_account_code' => '661', 'keywords' => ['salaires']],
            ['code' => 'sous_traitance_faconnage', 'label' => 'Sous-traitance / façonnage', 'nature' => 'decaissement', 'counterpart_account_code' => '4013', 'keywords' => ['sous', 'traitance', 'faconnage']],
            ['code' => 'acquisition_de_materiel', 'label' => 'Acquisition de matériel', 'nature' => 'decaissement', 'counterpart_account_code' => '24', 'keywords' => ['acquisition', 'materiel']],
            ['code' => 'achat_de_fournitures_administratives', 'label' => 'Achat de fournitures administratives', 'nature' => 'decaissement', 'counterpart_account_code' => '605', 'keywords' => ['achat', 'fournitures', 'administratives']],
            ['code' => 'entretien_et_reparations', 'label' => 'Entretien et réparations', 'nature' => 'decaissement', 'counterpart_account_code' => '624', 'keywords' => ['entretien', 'reparations']],
            ['code' => 'achat_de_marchandises', 'label' => 'Achat de marchandises', 'nature' => 'decaissement', 'counterpart_account_code' => '601', 'keywords' => ['achat', 'marchandises']],
            ['code' => 'achat_de_carburant', 'label' => 'Achat de carburant', 'nature' => 'decaissement', 'counterpart_account_code' => '618', 'keywords' => ['achat', 'carburant']],
            ['code' => 'frais_bancaires_transfert_financier', 'label' => 'Frais bancaires / transfert financier', 'nature' => 'decaissement', 'counterpart_account_code' => '631', 'keywords' => ['frais', 'bancaires', 'transfert', 'financier']],
            ['code' => 'vente_sur_commande', 'label' => 'Vente sur commande', 'nature' => 'encaissement', 'counterpart_account_code' => '701', 'keywords' => ['vente', 'commande']],
            ['code' => 'charges_de_personnel', 'label' => 'Charges de personnel', 'nature' => 'decaissement', 'counterpart_account_code' => '66', 'keywords' => ['charges', 'personnel']],
            ['code' => 'indemnites_du_personnel', 'label' => 'Indemnités du personnel', 'nature' => 'decaissement', 'counterpart_account_code' => '663', 'keywords' => ['indemnites', 'personnel']],
            ['code' => 'location_loyer', 'label' => 'Location / loyer', 'nature' => 'decaissement', 'counterpart_account_code' => '622', 'keywords' => ['location', 'loyer']],
            ['code' => 'vente_sur_projet', 'label' => 'Vente sur projet', 'nature' => 'encaissement', 'counterpart_account_code' => '706', 'keywords' => ['vente', 'projet']],
            ['code' => 'eau_et_electricite', 'label' => 'Eau et électricité', 'nature' => 'decaissement', 'counterpart_account_code' => '605', 'keywords' => ['eau', 'electricite']],
            ['code' => 'receptions_restauration', 'label' => 'Réceptions / restauration', 'nature' => 'decaissement', 'counterpart_account_code' => '6383', 'keywords' => ['receptions', 'restauration']],
            ['code' => 'avance_sur_salaire', 'label' => 'Avance sur salaire', 'nature' => 'decaissement', 'counterpart_account_code' => '421', 'keywords' => ['avance', 'salaire']],
            ['code' => 'remboursement_de_frais', 'label' => 'Remboursement de frais', 'nature' => 'decaissement', 'counterpart_account_code' => '658', 'keywords' => ['remboursement', 'frais']],
            ['code' => 'virement_interne_de_tresorerie', 'label' => 'Virement interne de trésorerie', 'nature' => 'decaissement', 'counterpart_account_code' => '58', 'keywords' => ['virement', 'interne', 'tresorerie']],
            ['code' => 'abonnements_logiciels', 'label' => 'Abonnements / logiciels', 'nature' => 'decaissement', 'counterpart_account_code' => '638', 'keywords' => ['abonnements', 'logiciels']],
            ['code' => 'achat_de_fournitures_d_entretien', 'label' => 'Achat de fournitures d\'entretien', 'nature' => 'decaissement', 'counterpart_account_code' => '605', 'keywords' => ['achat', 'fournitures', 'entretien']],
            ['code' => 'achat_d_emballages', 'label' => 'Achat d\'emballages', 'nature' => 'decaissement', 'counterpart_account_code' => '608', 'keywords' => ['achat', 'emballages']],
            ['code' => 'missions', 'label' => 'Missions', 'nature' => 'decaissement', 'counterpart_account_code' => '6384', 'keywords' => ['missions']],
            ['code' => 'publicite', 'label' => 'Publicité', 'nature' => 'decaissement', 'counterpart_account_code' => '627', 'keywords' => ['publicite']],
            ['code' => 'client_avance_acompte', 'label' => 'Client - avance / acompte', 'nature' => 'encaissement', 'counterpart_account_code' => '4191', 'keywords' => ['client', 'avance', 'acompte']],
            ['code' => 'personnel_avances_et_acomptes', 'label' => 'Personnel - avances et acomptes', 'nature' => 'decaissement', 'counterpart_account_code' => '421', 'keywords' => ['personnel', 'avances', 'acomptes']],
            ['code' => 'emprunt_pret', 'label' => 'Emprunt / prêt', 'nature' => 'decaissement', 'counterpart_account_code' => '16', 'keywords' => ['emprunt', 'pret']],
            ['code' => 'associes_comptes_courants', 'label' => 'Associés - comptes courants', 'nature' => 'decaissement', 'counterpart_account_code' => '462', 'keywords' => ['associes', 'comptes', 'courants']],
            ['code' => 'vente_regularisation', 'label' => 'Vente - régularisation', 'nature' => 'encaissement', 'counterpart_account_code' => '758', 'keywords' => ['vente', 'regularisation']],
            ['code' => 'remboursement_de_pret', 'label' => 'Remboursement de prêt', 'nature' => 'encaissement', 'counterpart_account_code' => '16', 'keywords' => ['remboursement', 'pret']],
            ['code' => 'regularisation_de_caisse', 'label' => 'Régularisation de caisse', 'nature' => 'encaissement', 'counterpart_account_code' => '758', 'keywords' => ['regularisation', 'caisse']],
            ['code' => 'charges_sociales', 'label' => 'Charges sociales', 'nature' => 'decaissement', 'counterpart_account_code' => '664', 'keywords' => ['charges', 'sociales']],
            ['code' => 'produit_exceptionnel', 'label' => 'Produit exceptionnel', 'nature' => 'encaissement', 'counterpart_account_code' => '84', 'keywords' => ['produit', 'exceptionnel']],
            ['code' => 'remboursement_client', 'label' => 'Remboursement client', 'nature' => 'decaissement', 'counterpart_account_code' => '41', 'keywords' => ['remboursement', 'client']],
            ['code' => 'achat_d_echantillons', 'label' => 'Achat d\'échantillons', 'nature' => 'decaissement', 'counterpart_account_code' => '605', 'keywords' => ['achat', 'echantillons']],
            ['code' => 'etudes_recherche_et_documentation', 'label' => 'Études, recherche et documentation', 'nature' => 'decaissement', 'counterpart_account_code' => '638', 'keywords' => ['etudes', 'recherche', 'documentation']],
            ['code' => 'frais_de_douane', 'label' => 'Frais de douane', 'nature' => 'decaissement', 'counterpart_account_code' => '6015', 'keywords' => ['frais', 'douane']],
            ['code' => 'recette_diverse', 'label' => 'Recette diverse', 'nature' => 'encaissement', 'counterpart_account_code' => '758', 'keywords' => ['recette', 'diverse']],
        ];

        DB::table('acc_operation_templates')->insert(
            array_map(fn ($t) => [
                'code' => $t['code'],
                'label' => $t['label'],
                'nature' => $t['nature'],
                'counterpart_account_code' => $t['counterpart_account_code'],
                'keywords' => json_encode($t['keywords']),
                'is_active' => true,
                'company_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ], $templates)
        );
    }
}
