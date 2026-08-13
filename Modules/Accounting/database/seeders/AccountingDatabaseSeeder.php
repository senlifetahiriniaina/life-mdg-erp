<?php

namespace Modules\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccountingDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedChartOfAccounts();
        $this->seedDefaultJournals();
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
            ['code' => '355', 'name' => 'Stocks de produits finis',             'type' => 'asset',      'is_active' => true],
            ['code' => '370', 'name' => 'Stocks de marchandises',               'type' => 'asset',      'is_active' => true],
            ['code' => '390', 'name' => 'Dépréciations des stocks MP',          'type' => 'asset',      'is_active' => true],
            ['code' => '397', 'name' => 'Dépréciations des stocks marchandi.',  'type' => 'asset',      'is_active' => true],

            // Classe 4 — Comptes de tiers
            ['code' => '401', 'name' => 'Fournisseurs',                         'type' => 'liability',  'is_active' => true],
            ['code' => '403', 'name' => 'Fournisseurs — Effets à payer',        'type' => 'liability',  'is_active' => true],
            ['code' => '408', 'name' => 'Fournisseurs — Factures non parvenues', 'type' => 'liability',  'is_active' => true],
            ['code' => '411', 'name' => 'Clients',                              'type' => 'asset',      'is_active' => true],
            ['code' => '413', 'name' => 'Clients — Effets à recevoir',          'type' => 'asset',      'is_active' => true],
            ['code' => '416', 'name' => 'Clients douteux ou litigieux',         'type' => 'asset',      'is_active' => true],
            ['code' => '419', 'name' => 'Clients créditeurs — Avances reçues',  'type' => 'liability',  'is_active' => true],
            ['code' => '421', 'name' => 'Personnel — Rémunérations dues',       'type' => 'liability',  'is_active' => true],
            ['code' => '431', 'name' => 'Sécurité sociale',                     'type' => 'liability',  'is_active' => true],
            ['code' => '437', 'name' => 'Autres organismes sociaux',            'type' => 'liability',  'is_active' => true],
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
            ['code' => '540', 'name' => 'Régies d\'avances et accréditifs',     'type' => 'asset',      'is_active' => true],

            // Classe 6 — Comptes de charges
            ['code' => '601', 'name' => 'Achats de matières premières',         'type' => 'expense',    'is_active' => true],
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
}
