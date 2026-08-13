<?php
declare(strict_types=1);
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminRolesSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'admin.servers.view','admin.servers.manage',
            'admin.backups.view','admin.backups.create','admin.backups.restore',
            'admin.users.view','admin.users.create','admin.users.update','admin.users.delete',
            'admin.roles.view','admin.roles.assign',
            'admin.modules.view','admin.modules.toggle',
            'admin.audit.view',
            'admin.security.manage',
            'admin.billing.view','admin.billing.manage',
        ];
        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $rolePermissions = [
            'system-admin'   => ['admin.servers.view','admin.servers.manage','admin.backups.view','admin.backups.create','admin.backups.restore','admin.audit.view'],
            'security-admin' => ['admin.audit.view','admin.security.manage','admin.users.view'],
            'billing-admin'  => ['admin.billing.view','admin.billing.manage','admin.users.view'],
            'support-admin'  => ['admin.users.view'],
            'content-admin'  => ['admin.modules.view'],
            'tenant-admin'   => ['admin.modules.view','admin.modules.toggle'],
        ];
        foreach ($rolePermissions as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($perms);
        }
    }
}
