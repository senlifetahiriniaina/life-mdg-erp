<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\InvoiceLine;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Journal;
use Modules\CRM\Models\Account;
use Modules\CRM\Models\Activity;
use Modules\CRM\Models\Contact;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\Opportunity;
use Modules\CRM\Models\Pipeline;
use Modules\Helpdesk\Models\SlaPolicy;
use Modules\Helpdesk\Models\Team;
use Modules\Helpdesk\Models\Ticket;
use Modules\HR\Models\Department;
use Modules\HR\Models\Employee;
use Modules\HR\Models\JobPosition;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Models\LeaveType;
use Modules\Inventory\Models\Category;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Stock;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Models\Warehouse;
use Modules\Projects\Models\Milestone;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\Task;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // ── Demo users ─────────────────────────────────────────────────────────
        $admin = User::firstOrCreate(
            ['email' => 'admin@widehalo.com'],
            ['name' => 'Admin User', 'password' => Hash::make('Admin#Wh2025!')]
        );

        $userDefs = [
            ['name' => 'Sophie Martin', 'email' => 'sophie.martin@widehalo.com', 'role' => 'sales-rep'],
            ['name' => 'Luca Rossi',    'email' => 'luca.rossi@widehalo.com',    'role' => 'manager'],
            ['name' => 'Amara Diallo',  'email' => 'amara.diallo@widehalo.com',  'role' => 'hr-manager'],
            ['name' => 'Chen Wei',      'email' => 'chen.wei@widehalo.com',      'role' => 'accountant'],
            ['name' => 'Marc Dubois',   'email' => 'marc.dubois@widehalo.com',   'role' => 'employee'],
        ];
        $userObjs = [];
        foreach ($userDefs as $d) {
            $u = User::firstOrCreate(['email' => $d['email']], [
                'name' => $d['name'], 'password' => Hash::make('Demo#Wh2025!'),
            ]);
            $u->syncRoles([$d['role']]);
            $userObjs[] = $u;
        }
        [$sophie, $luca, $amara, $chen, $marc] = $userObjs;

        // ── CRM ────────────────────────────────────────────────────────────────

        $pipeline = Pipeline::firstOrCreate(['name' => 'Ventes'], [
            'stages' => ['Prospect', 'Qualification', 'Proposition', 'Négociation', 'Gagné', 'Perdu'],
        ]);

        $acme = Account::firstOrCreate(['name' => 'Acme Corporation'], [
            'owner_id' => $sophie->id, 'type' => 'customer', 'industry' => 'Technology',
            'website' => 'https://acme.example.com', 'phone' => '+33 1 40 00 00 01',
            'email' => 'contact@acme.example.com', 'employee_count' => 250,
            'annual_revenue' => 12_500_000, 'currency' => 'EUR',
            'billing_city' => 'Paris', 'billing_country' => 'FR',
        ]);
        $globaltrade = Account::firstOrCreate(['name' => 'GlobalTrade SA'], [
            'owner_id' => $luca->id, 'type' => 'customer', 'industry' => 'Retail',
            'phone' => '+33 4 91 00 00 02', 'employee_count' => 80,
            'annual_revenue' => 3_200_000, 'currency' => 'EUR',
            'billing_city' => 'Marseille', 'billing_country' => 'FR',
        ]);
        $novatech = Account::firstOrCreate(['name' => 'NovaTech SARL'], [
            'owner_id' => $sophie->id, 'type' => 'prospect', 'industry' => 'Manufacturing',
            'phone' => '+33 5 56 00 00 03', 'employee_count' => 45,
            'annual_revenue' => 1_800_000, 'currency' => 'EUR',
            'billing_city' => 'Bordeaux', 'billing_country' => 'FR',
        ]);

        $pierre = Contact::firstOrCreate(['email' => 'pierre.dupont@acme.example.com'], [
            'account_id' => $acme->id, 'owner_id' => $sophie->id,
            'first_name' => 'Pierre', 'last_name' => 'Dupont',
            'phone' => '+33 6 10 00 00 01', 'job_title' => 'DSI',
            'status' => 'active', 'source' => 'website',
        ]);
        $marie = Contact::firstOrCreate(['email' => 'marie.leroy@globaltrade.example.com'], [
            'account_id' => $globaltrade->id, 'owner_id' => $luca->id,
            'first_name' => 'Marie', 'last_name' => 'Leroy',
            'phone' => '+33 6 20 00 00 02', 'job_title' => 'Directrice Achats',
            'status' => 'active', 'source' => 'referral',
        ]);
        $thomas = Contact::firstOrCreate(['email' => 'thomas.girard@novatech.example.com'], [
            'account_id' => $novatech->id, 'owner_id' => $sophie->id,
            'first_name' => 'Thomas', 'last_name' => 'Girard',
            'job_title' => 'PDG', 'status' => 'prospect', 'source' => 'cold_call',
        ]);

        Opportunity::firstOrCreate(['name' => 'Déploiement ERP Acme 2026'], [
            'account_id' => $acme->id, 'owner_id' => $sophie->id,
            'pipeline_id' => $pipeline->id, 'stage' => 'Proposition',
            'amount' => 85_000, 'currency' => 'EUR',
            'expected_close_date' => Carbon::now()->addDays(45)->toDateString(),
            'probability' => 60, 'status' => 'open',
        ]);
        Opportunity::firstOrCreate(['name' => 'Module POS GlobalTrade'], [
            'account_id' => $globaltrade->id, 'owner_id' => $luca->id,
            'pipeline_id' => $pipeline->id, 'stage' => 'Négociation',
            'amount' => 22_000, 'currency' => 'EUR',
            'expected_close_date' => Carbon::now()->addDays(15)->toDateString(),
            'probability' => 80, 'status' => 'open',
        ]);
        Opportunity::firstOrCreate(['name' => 'ERP NovaTech — Prospection'], [
            'account_id' => $novatech->id, 'owner_id' => $sophie->id,
            'pipeline_id' => $pipeline->id, 'stage' => 'Qualification',
            'amount' => 15_000, 'currency' => 'EUR',
            'expected_close_date' => Carbon::now()->addDays(90)->toDateString(),
            'probability' => 25, 'status' => 'open',
        ]);

        Activity::firstOrCreate(['title' => 'Appel de découverte — Pierre Dupont'], [
            'user_id' => $sophie->id, 'type' => 'call', 'status' => 'done',
            'due_at' => Carbon::now()->subDays(3),
            'subject_id' => $pierre->id, 'subject_type' => Contact::class,
            'description' => 'Intéressé par le module BI et les dashboards temps réel.',
        ]);
        Activity::firstOrCreate(['title' => 'Démo WideHalo — GlobalTrade'], [
            'user_id' => $luca->id, 'type' => 'meeting', 'status' => 'planned',
            'due_at' => Carbon::now()->addDays(2),
            'subject_id' => $marie->id, 'subject_type' => Contact::class,
        ]);

        Lead::firstOrCreate(['title' => 'Romain Petit — StartupXYZ'], [
            'owner_id' => $sophie->id, 'title' => 'Romain Petit — StartupXYZ',
            'status' => 'new', 'source' => 'website', 'score' => 72,
            'estimated_value' => 8_000, 'currency' => 'EUR',
            'description' => 'Intéressé par le module Manufacturing + BI.',
        ]);

        // ── HR ─────────────────────────────────────────────────────────────────

        $depts = [
            Department::firstOrCreate(['name' => 'Direction'],    ['code' => 'DIR']),
            Department::firstOrCreate(['name' => 'Commercial'],   ['code' => 'COM']),
            Department::firstOrCreate(['name' => 'Technologie'],  ['code' => 'TECH']),
            Department::firstOrCreate(['name' => 'Finance'],      ['code' => 'FIN']),
            Department::firstOrCreate(['name' => 'Ressources Humaines'], ['code' => 'RH']),
        ];

        $positions = [
            JobPosition::firstOrCreate(['title' => 'Responsable Commercial'],  ['department_id' => $depts[1]->id, 'level' => 'manager']),
            JobPosition::firstOrCreate(['title' => 'Attaché Commercial'],      ['department_id' => $depts[1]->id, 'level' => 'intermediate']),
            JobPosition::firstOrCreate(['title' => 'Chargé RH'],              ['department_id' => $depts[4]->id, 'level' => 'intermediate']),
            JobPosition::firstOrCreate(['title' => 'Comptable Senior'],        ['department_id' => $depts[3]->id, 'level' => 'senior']),
            JobPosition::firstOrCreate(['title' => 'Chef de Projet'],          ['department_id' => $depts[2]->id, 'level' => 'senior']),
        ];

        $ltCP  = LeaveType::firstOrCreate(['code' => 'CP'],  ['name' => 'Congés payés',     'days_per_year' => 25, 'is_paid' => true]);
        $ltRTT = LeaveType::firstOrCreate(['code' => 'RTT'], ['name' => 'RTT',               'days_per_year' => 12, 'is_paid' => true]);
        $ltCM  = LeaveType::firstOrCreate(['code' => 'CM'],  ['name' => 'Congé maladie',    'days_per_year' => 15, 'is_paid' => true]);

        $employees = [
            Employee::firstOrCreate(['email' => 'luca.rossi@widehalo.com'], [
                'user_id' => $luca->id, 'department_id' => $depts[1]->id,
                'job_position_id' => $positions[0]->id,
                'employee_number' => 'WH-001', 'first_name' => 'Luca', 'last_name' => 'Rossi',
                'phone' => '+33 6 00 00 00 10', 'hire_date' => '2022-03-01',
                'employment_type' => 'full_time', 'status' => 'active', 'gender' => 'male', 'nationality' => 'IT',
            ]),
            Employee::firstOrCreate(['email' => 'sophie.martin@widehalo.com'], [
                'user_id' => $sophie->id, 'department_id' => $depts[1]->id,
                'job_position_id' => $positions[1]->id,
                'employee_number' => 'WH-002', 'first_name' => 'Sophie', 'last_name' => 'Martin',
                'phone' => '+33 6 00 00 00 11', 'hire_date' => '2023-01-15',
                'employment_type' => 'full_time', 'status' => 'active', 'gender' => 'female', 'nationality' => 'FR',
            ]),
            Employee::firstOrCreate(['email' => 'amara.diallo@widehalo.com'], [
                'user_id' => $amara->id, 'department_id' => $depts[4]->id,
                'job_position_id' => $positions[2]->id,
                'employee_number' => 'WH-003', 'first_name' => 'Amara', 'last_name' => 'Diallo',
                'phone' => '+33 6 00 00 00 12', 'hire_date' => '2021-09-01',
                'employment_type' => 'full_time', 'status' => 'active', 'gender' => 'female', 'nationality' => 'SN',
            ]),
            Employee::firstOrCreate(['email' => 'chen.wei@widehalo.com'], [
                'user_id' => $chen->id, 'department_id' => $depts[3]->id,
                'job_position_id' => $positions[3]->id,
                'employee_number' => 'WH-004', 'first_name' => 'Chen', 'last_name' => 'Wei',
                'phone' => '+33 6 00 00 00 13', 'hire_date' => '2020-06-15',
                'employment_type' => 'full_time', 'status' => 'active', 'gender' => 'male', 'nationality' => 'CN',
            ]),
            Employee::firstOrCreate(['email' => 'marc.dubois@widehalo.com'], [
                'user_id' => $marc->id, 'department_id' => $depts[2]->id,
                'job_position_id' => $positions[4]->id,
                'employee_number' => 'WH-005', 'first_name' => 'Marc', 'last_name' => 'Dubois',
                'phone' => '+33 6 00 00 00 14', 'hire_date' => '2023-07-01',
                'employment_type' => 'full_time', 'status' => 'active', 'gender' => 'male', 'nationality' => 'FR',
            ]),
        ];

        LeaveRequest::firstOrCreate(
            ['employee_id' => $employees[1]->id, 'start_date' => Carbon::now()->addDays(20)->toDateString()],
            [
                'leave_type_id' => $ltCP->id,
                'end_date' => Carbon::now()->addDays(28)->toDateString(),
                'days' => 7, 'reason' => 'Vacances d\'été', 'status' => 'pending',
            ]
        );
        LeaveRequest::firstOrCreate(
            ['employee_id' => $employees[0]->id, 'start_date' => Carbon::now()->subDays(5)->toDateString()],
            [
                'leave_type_id' => $ltRTT->id,
                'end_date' => Carbon::now()->subDays(3)->toDateString(),
                'days' => 2, 'reason' => 'RTT planifié', 'status' => 'approved',
                'approved_by' => $employees[2]->id, 'approved_at' => now()->subDays(6),
            ]
        );

        // ── Inventory ──────────────────────────────────────────────────────────

        $unit   = Unit::firstOrCreate(['name' => 'Unité'],      ['symbol' => 'u',  'type' => 'unit']);
        $unitKg = Unit::firstOrCreate(['name' => 'Kilogramme'], ['symbol' => 'kg', 'type' => 'weight']);

        $catSoft = Category::firstOrCreate(['name' => 'Logiciels'],  ['slug' => 'logiciels',  'description' => 'Licences et abonnements SaaS']);
        $catHard = Category::firstOrCreate(['name' => 'Matériel'],   ['slug' => 'materiel',   'description' => 'Équipements informatiques']);
        $catFood = Category::firstOrCreate(['name' => 'Épicerie'],   ['slug' => 'epicerie',   'description' => 'Produits alimentaires']);

        $warehouse = Warehouse::firstOrCreate(['code' => 'WH-01'], [
            'name' => 'Entrepôt Principal', 'address' => '12 rue de la Logistique',
            'city' => 'Lyon', 'country' => 'FR', 'is_active' => true,
        ]);

        $products = [
            Product::firstOrCreate(['sku' => 'WH-ERP-STD'], [
                'category_id' => $catSoft->id, 'unit_id' => $unit->id,
                'name' => 'WideHalo ERP Standard', 'description' => 'Licence annuelle 10 utilisateurs',
                'type' => 'service', 'cost_price' => 0, 'sale_price' => 4800,
                'currency' => 'EUR', 'is_active' => true,
            ]),
            Product::firstOrCreate(['sku' => 'WH-ERP-PRO'], [
                'category_id' => $catSoft->id, 'unit_id' => $unit->id,
                'name' => 'WideHalo ERP Pro', 'description' => 'Licence annuelle utilisateurs illimités',
                'type' => 'service', 'cost_price' => 0, 'sale_price' => 12000,
                'currency' => 'EUR', 'is_active' => true,
            ]),
            Product::firstOrCreate(['sku' => 'LAPTOP-PRO-14'], [
                'category_id' => $catHard->id, 'unit_id' => $unit->id,
                'name' => 'Laptop Pro 14"', 'description' => 'Ordinateur portable professionnel',
                'type' => 'storable', 'cost_price' => 850, 'sale_price' => 1290,
                'currency' => 'EUR', 'reorder_point' => 5, 'reorder_qty' => 10, 'is_active' => true,
            ]),
            Product::firstOrCreate(['sku' => 'CAFE-BIO-1KG'], [
                'category_id' => $catFood->id, 'unit_id' => $unitKg->id,
                'name' => 'Café Bio 1kg', 'description' => 'Café arabica biologique certifié',
                'type' => 'storable', 'cost_price' => 8.50, 'sale_price' => 18.90,
                'currency' => 'EUR', 'reorder_point' => 20, 'reorder_qty' => 50, 'is_active' => true,
            ]),
        ];

        foreach ([[$products[2], 28], [$products[3], 150]] as [$product, $qty]) {
            Stock::firstOrCreate(
                ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
                ['quantity' => $qty, 'reserved_quantity' => 0, 'avg_cost' => $product->cost_price]
            );
        }

        // ── Accounting ─────────────────────────────────────────────────────────

        $salesAccount = ChartOfAccount::firstOrCreate(['code' => '706000'], [
            'name' => 'Ventes de produits et services', 'type' => 'revenue', 'is_active' => true,
        ]);

        $journal = Journal::firstOrCreate(['code' => 'VTE'], [
            'name' => 'Journal des Ventes', 'type' => 'sale', 'currency' => 'EUR',
        ]);

        $inv1 = Invoice::firstOrCreate(['number' => 'FAC-2026-0001'], [
            'type' => 'invoice', 'journal_id' => $journal->id,
            'partner_id' => $acme->id, 'partner_type' => 'customer',
            'invoice_date' => Carbon::now()->subDays(30)->toDateString(),
            'due_date' => Carbon::now()->addDays(30)->toDateString(),
            'currency' => 'EUR', 'subtotal' => 4800, 'tax_amount' => 960,
            'total' => 5760, 'amount_due' => 5760, 'status' => 'sent',
            'created_by' => $chen->id,
        ]);
        if ($inv1->wasRecentlyCreated) {
            InvoiceLine::create([
                'invoice_id' => $inv1->id, 'product_id' => $products[0]->id,
                'description' => 'WideHalo ERP Standard — Licence 2026',
                'account_id' => $salesAccount->id,
                'quantity' => 1, 'unit_price' => 4800,
                'discount_percent' => 0, 'tax_percent' => 20,
                'subtotal' => 4800, 'tax_amount' => 960, 'total' => 5760,
            ]);
        }

        $inv2 = Invoice::firstOrCreate(['number' => 'FAC-2026-0002'], [
            'type' => 'invoice', 'journal_id' => $journal->id,
            'partner_id' => $globaltrade->id, 'partner_type' => 'customer',
            'invoice_date' => Carbon::now()->subDays(10)->toDateString(),
            'due_date' => Carbon::now()->addDays(20)->toDateString(),
            'currency' => 'EUR', 'subtotal' => 25800, 'tax_amount' => 5160,
            'total' => 30960, 'amount_due' => 0, 'status' => 'paid',
            'created_by' => $chen->id,
        ]);
        if ($inv2->wasRecentlyCreated) {
            InvoiceLine::create([
                'invoice_id' => $inv2->id, 'product_id' => $products[1]->id,
                'account_id' => $salesAccount->id,
                'description' => 'WideHalo ERP Pro — Déploiement complet',
                'quantity' => 1, 'unit_price' => 12000,
                'discount_percent' => 0, 'tax_percent' => 20,
                'subtotal' => 12000, 'tax_amount' => 2400, 'total' => 14400,
            ]);
            InvoiceLine::create([
                'invoice_id' => $inv2->id, 'product_id' => $products[2]->id,
                'account_id' => $salesAccount->id,
                'description' => 'Laptop Pro 14" × 11',
                'quantity' => 11, 'unit_price' => 1290,
                'discount_percent' => 0, 'tax_percent' => 20,
                'subtotal' => 14190, 'tax_amount' => 2838, 'total' => 17028,
            ]);
        }

        // ── Projects ───────────────────────────────────────────────────────────

        $project = Project::firstOrCreate(['code' => 'PRJ-001'], [
            'owner_id' => $luca->id, 'name' => 'Déploiement ERP — Acme Corporation',
            'description' => 'Migration complète du système de gestion vers WideHalo ERP.',
            'status' => 'active',
            'start_date' => Carbon::now()->subDays(15)->toDateString(),
            'end_date' => Carbon::now()->addDays(60)->toDateString(),
            'budget' => 85000, 'currency' => 'EUR', 'is_billable' => true,
        ]);

        Milestone::firstOrCreate(['project_id' => $project->id, 'name' => 'Kick-off et cadrage'], [
            'due_date' => Carbon::now()->subDays(10)->toDateString(), 'is_reached' => true,
        ]);
        Milestone::firstOrCreate(['project_id' => $project->id, 'name' => 'Livraison module CRM'], [
            'due_date' => Carbon::now()->addDays(10)->toDateString(), 'is_reached' => false,
        ]);
        Milestone::firstOrCreate(['project_id' => $project->id, 'name' => 'Mise en production'], [
            'due_date' => Carbon::now()->addDays(60)->toDateString(), 'is_reached' => false,
        ]);

        $taskDefs = [
            ['title' => 'Analyse des besoins métier',     'status' => 'done',        'priority' => 'high',   'assignee_id' => $luca->id,  'estimated_hours' => 8,  'logged_hours' => 9],
            ['title' => 'Configuration des modules',      'status' => 'in_progress', 'priority' => 'high',   'assignee_id' => $marc->id,  'estimated_hours' => 20, 'logged_hours' => 12],
            ['title' => 'Migration des données clients',  'status' => 'in_progress', 'priority' => 'high',   'assignee_id' => $marc->id,  'estimated_hours' => 16, 'logged_hours' => 4],
            ['title' => 'Formation utilisateurs CRM',     'status' => 'todo',        'priority' => 'medium', 'assignee_id' => $sophie->id,'estimated_hours' => 12, 'logged_hours' => 0],
            ['title' => 'Tests de recette',               'status' => 'todo',        'priority' => 'high',   'assignee_id' => $luca->id,  'estimated_hours' => 10, 'logged_hours' => 0],
        ];
        foreach ($taskDefs as $i => $t) {
            Task::firstOrCreate(['project_id' => $project->id, 'title' => $t['title']], array_merge($t, [
                'project_id' => $project->id, 'created_by' => $luca->id,
                'due_date' => Carbon::now()->addDays(10 + $i * 10)->toDateString(),
            ]));
        }

        // ── Helpdesk ───────────────────────────────────────────────────────────

        $sla  = SlaPolicy::firstOrCreate(['name' => 'Standard'], [
            'response_time_minutes' => 4 * 60, 'resolution_time_minutes' => 24 * 60, 'is_default' => true, 'business_hours' => null,
        ]);
        $team = Team::firstOrCreate(['name' => 'Support Technique'], [
            'is_active' => true, 'auto_assignment' => false,
        ]);

        $ticketDefs = [
            ['subject' => 'Impossible de se connecter au module POS',    'priority' => 'high',   'status' => 'open',        'channel' => 'email'],
            ['subject' => 'Erreur lors de l\'export PDF des factures',   'priority' => 'medium', 'status' => 'in_progress', 'channel' => 'web'],
            ['subject' => 'Demande d\'ajout d\'un utilisateur',          'priority' => 'low',    'status' => 'resolved',    'channel' => 'email'],
            ['subject' => 'Synchronisation WhatsApp interrompue',        'priority' => 'high',   'status' => 'open',        'channel' => 'whatsapp'],
        ];
        foreach ($ticketDefs as $t) {
            Ticket::firstOrCreate(['subject' => $t['subject']], array_merge($t, [
                'team_id' => $team->id, 'sla_id' => $sla->id,
                'assignee_id' => $luca->id, 'reporter_id' => $sophie->id,
                'contact_id' => $pierre->id,
                'description' => 'Description détaillée du problème signalé par le client.',
                'type' => 'incident',
            ]));
        }

    }
}
