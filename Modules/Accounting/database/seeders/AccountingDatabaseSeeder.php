<?php

namespace Modules\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Chantier 12: now actually wired into the live seed chain from
 * database/seeders/DatabaseSeeder.php — previously only reachable via the
 * broken TenantDefaultSeeder/ProvisionTenantJob path (see CLAUDE.md's
 * "Known gaps"), so this 76-account SYSCOHADA-style chart of accounts was
 * written but never actually seeded by `migrate --seed`.
 *
 * A handful of accounts below (431/437/447, 531/532) are labelled for
 * Madagascar specifically (CNaPS/OSTIE/IRSA, Mvola/Airtel Money) rather than
 * left as generic French SYSCOHADA placeholders — a pragmatic adaptation of
 * the existing base, not a certified official PCG 2005 malgache reference;
 * have an accountant review/adjust before real production bookkeeping.
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

        $accounts = [
            // Classe 1 — Comptes de capitaux
            ['code' => '101', 'name' => 'Capital social',                       'type' => 'equity',     'is_active' => true],
            ['code' => '106', 'name' => 'Réserves',                             'type' => 'equity',     'is_active' => true],
            ['code' => '110', 'name' => 'Report à nouveau (solde créditeur)',    'type' => 'equity',     'is_active' => true],
            ['code' => '119', 'name' => 'Report à nouveau (solde débiteur)',     'type' => 'equity',     'is_active' => true],
            ['code' => '120', 'name' => 'Résultat de l\'exercice (bénéfice)',   'type' => 'equity',     'is_active' => true],
            ['code' => '129', 'name' => 'Résultat de l\'exercice (perte)',      'type' => 'equity',     'is_active' => true],
            ['code' => '164', 'name' => 'Emprunts auprès des établissements',   'type' => 'liability',  'is_active' => true],
            ['code' => '168', 'name' => 'Autres emprunts et dettes assimilées', 'type' => 'liability',  'is_active' => true],

            // Classe 2 — Comptes d'immobilisations
            ['code' => '205', 'name' => 'Concessions, brevets, licences',       'type' => 'asset',      'is_active' => true],
            ['code' => '211', 'name' => 'Terrains',                             'type' => 'asset',      'is_active' => true],
            ['code' => '213', 'name' => 'Constructions',                        'type' => 'asset',      'is_active' => true],
            ['code' => '215', 'name' => 'Installations techniques, matériel',   'type' => 'asset',      'is_active' => true],
            ['code' => '218', 'name' => 'Autres immobilisations corporelles',   'type' => 'asset',      'is_active' => true],
            ['code' => '231', 'name' => 'Immobilisations corporelles en cours', 'type' => 'asset',      'is_active' => true],
            ['code' => '261', 'name' => 'Titres de participation',              'type' => 'asset',      'is_active' => true],
            ['code' => '280', 'name' => 'Amortissements des immob. incorporelles', 'type' => 'asset',   'is_active' => true],
            ['code' => '281', 'name' => 'Amortissements des immob. corporelles',   'type' => 'asset',   'is_active' => true],

            // Classe 3 — Comptes de stocks
            ['code' => '310', 'name' => 'Stocks de matières premières',         'type' => 'asset',      'is_active' => true],
            // Chantier 17 — accessoires/fournitures (boutons, fermetures, fil,
            // étiquettes) et en-cours de production (vêtements semi-finis)
            // sont des concepts de stock distincts des matières premières
            // brutes — même style pragmatique d'adaptation que 310/355/370
            // (non un numéro SYSCOHADA officiel certifié, voir CLAUDE.md).
            ['code' => '312', 'name' => "Stocks d'accessoires et fournitures",  'type' => 'asset',      'is_active' => true],
            ['code' => '335', 'name' => 'Stocks en-cours (vêtements semi-finis)', 'type' => 'asset',    'is_active' => true],
            ['code' => '355', 'name' => 'Stocks de produits finis',             'type' => 'asset',      'is_active' => true],
            ['code' => '370', 'name' => 'Stocks de marchandises',               'type' => 'asset',      'is_active' => true],
            ['code' => '390', 'name' => 'Dépréciations des stocks MP',          'type' => 'asset',      'is_active' => true],
            ['code' => '397', 'name' => 'Dépréciations des stocks marchandi.',  'type' => 'asset',      'is_active' => true],

            // Classe 4 — Comptes de tiers
            ['code' => '401', 'name' => 'Fournisseurs',                         'type' => 'liability',  'is_active' => true],
            ['code' => '403', 'name' => 'Fournisseurs — Effets à payer',        'type' => 'liability',  'is_active' => true],
            ['code' => '408', 'name' => 'Fournisseurs — Factures non parvenues', 'type' => 'liability',  'is_active' => true],
            // Chantier 22 (volet B — cycle acompte/solde) : pendant de 419 côté
            // achats, jusque-là seulement documenté comme manquant dans
            // CLAUDE.md ("No OHADA 409x account seeded"). Un acompte versé à
            // un fournisseur est une créance (actif), pas une dette.
            ['code' => '4091', 'name' => 'Fournisseurs — Avances et acomptes versés', 'type' => 'asset', 'is_active' => true],
            ['code' => '411', 'name' => 'Clients',                              'type' => 'asset',      'is_active' => true],
            ['code' => '413', 'name' => 'Clients — Effets à recevoir',          'type' => 'asset',      'is_active' => true],
            ['code' => '416', 'name' => 'Clients douteux ou litigieux',         'type' => 'asset',      'is_active' => true],
            ['code' => '419', 'name' => 'Clients créditeurs — Avances reçues',  'type' => 'liability',  'is_active' => true],
            ['code' => '421', 'name' => 'Personnel — Rémunérations dues',       'type' => 'liability',  'is_active' => true],
            // 431/437 relabellisés pour Madagascar (CNaPS = caisse de retraite/
            // prévoyance sociale, OSTIE = organisme de santé au travail le plus
            // répandu) — adaptation pragmatique de la base SYSCOHADA, pas une
            // référence PCG 2005 malgache officielle certifiée ; à faire
            // valider par un comptable avant tout usage en production réelle.
            ['code' => '431', 'name' => 'CNaPS — Caisse Nationale de Prévoyance Sociale', 'type' => 'liability', 'is_active' => true],
            ['code' => '437', 'name' => 'OSTIE — Organisme de santé au travail', 'type' => 'liability',  'is_active' => true],
            ['code' => '447', 'name' => 'État — IRSA (impôt sur les revenus salariaux)', 'type' => 'liability', 'is_active' => true],
            ['code' => '441', 'name' => 'État — Subventions à recevoir',        'type' => 'asset',      'is_active' => true],
            ['code' => '444', 'name' => 'État — Impôts sur les bénéfices',      'type' => 'liability',  'is_active' => true],
            ['code' => '445', 'name' => 'État — Taxes sur le chiffre d\'affaires', 'type' => 'liability', 'is_active' => true],
            ['code' => '4452', 'name' => 'TVA due intracommunautaire',          'type' => 'liability',  'is_active' => true],
            ['code' => '4456', 'name' => 'TVA déductible',                      'type' => 'asset',      'is_active' => true],
            ['code' => '4457', 'name' => 'TVA collectée',                       'type' => 'liability',  'is_active' => true],
            ['code' => '4458', 'name' => 'TVA à régulariser',                   'type' => 'liability',  'is_active' => true],
            ['code' => '451', 'name' => 'Groupe — Opérations intragroupe',      'type' => 'asset',      'is_active' => true],
            ['code' => '467', 'name' => 'Autres comptes débiteurs ou créditeurs', 'type' => 'asset',    'is_active' => true],
            ['code' => '481', 'name' => 'Charges à répartir sur plusieurs exercices', 'type' => 'asset', 'is_active' => true],
            ['code' => '486', 'name' => 'Charges constatées d\'avance',         'type' => 'asset',      'is_active' => true],
            ['code' => '487', 'name' => 'Produits constatés d\'avance',         'type' => 'liability',  'is_active' => true],

            // Classe 5 — Comptes financiers
            ['code' => '512', 'name' => 'Banques',                              'type' => 'asset',      'is_active' => true],
            ['code' => '514', 'name' => 'Chèques postaux',                      'type' => 'asset',      'is_active' => true],
            ['code' => '530', 'name' => 'Caisse',                               'type' => 'asset',      'is_active' => true],
            // Comptes mobile money — moyens de paiement réels listés pour MG
            // dans Modules\Core\Services\SmartDefaultsService::COUNTRIES['MG'].
            ['code' => '531', 'name' => 'Mvola (Telma)',                        'type' => 'asset',      'is_active' => true],
            ['code' => '532', 'name' => 'Airtel Money',                         'type' => 'asset',      'is_active' => true],
            ['code' => '540', 'name' => 'Régies d\'avances et accréditifs',     'type' => 'asset',      'is_active' => true],

            // Classe 6 — Comptes de charges
            ['code' => '601', 'name' => 'Achats de matières premières',         'type' => 'expense',    'is_active' => true],
            // Comptes de compensation ("variation des stocks") — sans eux, un
            // achat porté en 601/607 ne se réconcilie jamais avec le stock
            // porté à l'actif (310/355/370) : ces comptes sont ce qui absorbe
            // l'écart en fin de période (Chantier 13, sur la base des
            // matières premières/produits finis/marchandises par défaut
            // ajoutés dans DefaultDataSeeder). Numérotation SYSCOHADA/PCG
            // standard (603x côté charges, 713x côté produits).
            ['code' => '6031', 'name' => 'Variation des stocks de matières premières', 'type' => 'expense', 'is_active' => true],
            // Chantier 17 — mêmes comptes de compensation, pour les 2 nouvelles
            // catégories de stock (312/335) ajoutées par ce chantier.
            ['code' => '602', 'name' => "Achats d'accessoires et fournitures", 'type' => 'expense',    'is_active' => true],
            ['code' => '6032', 'name' => "Variation des stocks d'accessoires et fournitures", 'type' => 'expense', 'is_active' => true],
            ['code' => '6035', 'name' => 'Variation des stocks en-cours (vêtements semi-finis)', 'type' => 'expense', 'is_active' => true],
            ['code' => '6037', 'name' => 'Variation des stocks de marchandises', 'type' => 'expense',    'is_active' => true],
            ['code' => '607', 'name' => 'Achats de marchandises',               'type' => 'expense',    'is_active' => true],
            ['code' => '611', 'name' => 'Sous-traitance générale',              'type' => 'expense',    'is_active' => true],
            ['code' => '613', 'name' => 'Locations',                            'type' => 'expense',    'is_active' => true],
            ['code' => '615', 'name' => 'Entretien et réparations',             'type' => 'expense',    'is_active' => true],
            ['code' => '616', 'name' => 'Primes d\'assurances',                 'type' => 'expense',    'is_active' => true],
            ['code' => '622', 'name' => 'Rémunérations d\'intermédiaires',      'type' => 'expense',    'is_active' => true],
            ['code' => '625', 'name' => 'Déplacements, missions et réceptions', 'type' => 'expense',    'is_active' => true],
            ['code' => '626', 'name' => 'Frais postaux et de télécom.',         'type' => 'expense',    'is_active' => true],
            ['code' => '627', 'name' => 'Services bancaires et assimilés',      'type' => 'expense',    'is_active' => true],
            ['code' => '631', 'name' => 'Impôts, taxes et versements assimilés', 'type' => 'expense',    'is_active' => true],
            ['code' => '641', 'name' => 'Rémunérations du personnel',           'type' => 'expense',    'is_active' => true],
            ['code' => '645', 'name' => 'Charges de sécurité sociale',          'type' => 'expense',    'is_active' => true],
            ['code' => '651', 'name' => 'Redevances pour concessions, brevets', 'type' => 'expense',    'is_active' => true],
            ['code' => '661', 'name' => 'Charges d\'intérêts',                  'type' => 'expense',    'is_active' => true],
            ['code' => '671', 'name' => 'Charges exceptionnelles sur opérations de gestion', 'type' => 'expense', 'is_active' => true],
            ['code' => '681', 'name' => 'Dotations amortissements — Immo.',     'type' => 'expense',    'is_active' => true],
            ['code' => '687', 'name' => 'Dotations amortissements — Charges exceptionnelles', 'type' => 'expense', 'is_active' => true],

            // Classe 7 — Comptes de produits
            ['code' => '701', 'name' => 'Ventes de produits finis',             'type' => 'revenue',    'is_active' => true],
            ['code' => '7135', 'name' => 'Variation des stocks de produits finis', 'type' => 'revenue', 'is_active' => true],
            ['code' => '706', 'name' => 'Prestations de services',              'type' => 'revenue',    'is_active' => true],
            ['code' => '707', 'name' => 'Ventes de marchandises',               'type' => 'revenue',    'is_active' => true],
            ['code' => '708', 'name' => 'Produits des activités annexes',       'type' => 'revenue',    'is_active' => true],
            ['code' => '709', 'name' => 'Rabais, remises et ristournes accordés', 'type' => 'revenue',  'is_active' => true],
            ['code' => '741', 'name' => 'Subventions d\'exploitation',          'type' => 'revenue',    'is_active' => true],
            ['code' => '751', 'name' => 'Redevances pour concessions',          'type' => 'revenue',    'is_active' => true],
            ['code' => '761', 'name' => 'Produits de participations',           'type' => 'revenue',    'is_active' => true],
            ['code' => '771', 'name' => 'Produits exceptionnels sur opérations de gestion', 'type' => 'revenue', 'is_active' => true],
            ['code' => '781', 'name' => 'Reprises sur amortissements',          'type' => 'revenue',    'is_active' => true],
        ];

        DB::table('acc_chart_of_accounts')->insert(
            array_map(fn ($a) => array_merge($a, ['created_at' => $now, 'updated_at' => $now]), $accounts)
        );
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
     * Chantier 15: default cash/bank "operation templates" — the catalogue
     * TreasuryImportService matches an imported caisse/relevé-bancaire row
     * against to propose the right double-entry pairing (treasury account
     * vs. a fixed counterpart GL account) before the user validates. Every
     * `counterpart_account_code` below is a real code from the chart above.
     */
    private function seedOperationTemplates(): void
    {
        if (DB::table('acc_operation_templates')->exists()) {
            return;
        }

        $now = now();

        $templates = [
            // Encaissements — l'argent entre : le compte de trésorerie est débité.
            ['code' => 'vente_comptant', 'label' => 'Vente de marchandises au comptant', 'nature' => 'encaissement', 'counterpart_account_code' => '707', 'keywords' => ['vente', 'ventes', 'vente comptant', 'client comptant']],
            ['code' => 'prestation_service', 'label' => 'Encaissement prestation de service', 'nature' => 'encaissement', 'counterpart_account_code' => '706', 'keywords' => ['prestation', 'service', 'honoraires']],
            ['code' => 'reglement_client', 'label' => 'Règlement client (facture)', 'nature' => 'encaissement', 'counterpart_account_code' => '411', 'keywords' => ['règlement client', 'reglement client', 'paiement facture', 'virement client', 'encaissement facture']],
            ['code' => 'apport_capital', 'label' => "Apport en capital / associé", 'nature' => 'encaissement', 'counterpart_account_code' => '101', 'keywords' => ['apport', 'capital', 'associé', 'associe', 'actionnaire']],
            ['code' => 'emprunt_recu', 'label' => 'Emprunt / prêt bancaire reçu', 'nature' => 'encaissement', 'counterpart_account_code' => '164', 'keywords' => ['emprunt', 'prêt bancaire', 'pret bancaire', 'crédit reçu', 'credit recu']],
            ['code' => 'virement_interne_in', 'label' => 'Virement interne (dépôt en banque / approvisionnement)', 'nature' => 'encaissement', 'counterpart_account_code' => '512', 'keywords' => ['virement interne', 'dépôt banque', 'depot banque', 'approvisionnement caisse']],
            ['code' => 'autre_produit', 'label' => 'Autre produit divers', 'nature' => 'encaissement', 'counterpart_account_code' => '771', 'keywords' => ['divers', 'autre recette', 'produit exceptionnel']],

            // Décaissements — l'argent sort : le compte de trésorerie est crédité.
            ['code' => 'reglement_fournisseur', 'label' => 'Règlement fournisseur (facture)', 'nature' => 'decaissement', 'counterpart_account_code' => '401', 'keywords' => ['fournisseur', 'règlement fournisseur', 'reglement fournisseur', 'paiement facture achat']],
            ['code' => 'achat_comptant', 'label' => 'Achat de marchandises au comptant', 'nature' => 'decaissement', 'counterpart_account_code' => '607', 'keywords' => ['achat comptant', 'achat marchandise']],
            ['code' => 'paiement_salaire', 'label' => 'Paiement des salaires', 'nature' => 'decaissement', 'counterpart_account_code' => '641', 'keywords' => ['salaire', 'salaires', 'paie', 'paye']],
            ['code' => 'charges_sociales', 'label' => 'Charges sociales (CNaPS / OSTIE)', 'nature' => 'decaissement', 'counterpart_account_code' => '645', 'keywords' => ['cnaps', 'ostie', 'charges sociales', 'cotisation']],
            ['code' => 'loyer', 'label' => 'Loyer / location', 'nature' => 'decaissement', 'counterpart_account_code' => '613', 'keywords' => ['loyer', 'location']],
            ['code' => 'assurance', 'label' => "Prime d'assurance", 'nature' => 'decaissement', 'counterpart_account_code' => '616', 'keywords' => ['assurance', 'prime assurance']],
            ['code' => 'entretien_reparation', 'label' => 'Entretien et réparations', 'nature' => 'decaissement', 'counterpart_account_code' => '615', 'keywords' => ['entretien', 'réparation', 'reparation', 'maintenance']],
            ['code' => 'telecom', 'label' => 'Frais postaux et télécommunications', 'nature' => 'decaissement', 'counterpart_account_code' => '626', 'keywords' => ['telma', 'orange', 'airtel', 'telephone', 'téléphone', 'internet', 'télécom', 'telecom']],
            ['code' => 'frais_bancaires', 'label' => 'Frais et commissions bancaires', 'nature' => 'decaissement', 'counterpart_account_code' => '627', 'keywords' => ['frais bancaire', 'commission', 'agios', 'frais de tenue de compte']],
            ['code' => 'impots_taxes', 'label' => 'Impôts et taxes', 'nature' => 'decaissement', 'counterpart_account_code' => '631', 'keywords' => ['impôt', 'impot', 'tva', 'irsa', 'taxe']],
            ['code' => 'deplacement', 'label' => 'Déplacements et missions', 'nature' => 'decaissement', 'counterpart_account_code' => '625', 'keywords' => ['déplacement', 'deplacement', 'mission', 'transport', 'carburant']],
            ['code' => 'virement_interne_out', 'label' => 'Virement interne (retrait vers caisse / mobile money)', 'nature' => 'decaissement', 'counterpart_account_code' => '512', 'keywords' => ['virement interne', 'retrait', 'retrait banque']],
            ['code' => 'autre_charge', 'label' => 'Autre charge diverse', 'nature' => 'decaissement', 'counterpart_account_code' => '671', 'keywords' => ['divers', 'autre charge', 'charge exceptionnelle']],
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
