<?php

namespace Modules\CRM\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

/**
 * Phase 5: Advanced CRM Features Permissions Seeder
 *
 * Seeds permissions required for:
 * - Campaign Orchestration
 * - Workflow Visual Builder
 * - Revenue Intelligence
 * - Territory Management Advanced
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCampaignPermissions();
        $this->seedWorkflowPermissions();
        $this->seedTerritoryPermissions();
        $this->seedRevenueIntelligencePermissions();
    }

    private function seedCampaignPermissions(): void
    {
        $permissions = [
            'crm.campaigns.view'   => 'View CRM campaigns',
            'crm.campaigns.create' => 'Create CRM campaigns',
            'crm.campaigns.edit'   => 'Edit CRM campaigns',
            'crm.campaigns.delete' => 'Delete CRM campaigns',
            'crm.campaigns.launch' => 'Launch CRM campaigns',
            'crm.campaigns.pause'  => 'Pause CRM campaigns',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description]
            );
        }
    }

    private function seedWorkflowPermissions(): void
    {
        $permissions = [
            'crm.workflows.view'   => 'View CRM workflows',
            'crm.workflows.create' => 'Create CRM workflows',
            'crm.workflows.edit'   => 'Edit CRM workflows',
            'crm.workflows.delete' => 'Delete CRM workflows',
            'crm.workflows.activate' => 'Activate CRM workflows',
            'crm.workflows.deactivate' => 'Deactivate CRM workflows',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description]
            );
        }
    }

    private function seedTerritoryPermissions(): void
    {
        $permissions = [
            'crm.territories.manage'      => 'Manage territory quotas and alerts',
            'crm.territories.view_quotas' => 'View territory quotas',
            'crm.territories.set_quotas'  => 'Set territory quotas',
            'crm.territories.view_alerts' => 'View territory alerts',
            'crm.territories.create_alerts' => 'Create territory alerts',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description]
            );
        }
    }

    private function seedRevenueIntelligencePermissions(): void
    {
        $permissions = [
            'crm.revenue-intelligence.view'      => 'View revenue intelligence',
            'crm.revenue-intelligence.generate'  => 'Generate revenue insights',
            'crm.revenue-intelligence.view_trends' => 'View revenue trends',
            'crm.revenue-intelligence.view_anomalies' => 'View revenue anomalies',
            'crm.revenue-intelligence.detect_anomalies' => 'Detect revenue anomalies',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description]
            );
        }
    }
}
