<?php

namespace Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        // Resolve module model factories: Modules\X\Models\Y -> Modules\X\Database\Factories\YFactory.
        // (Per-module providers each call guessFactoryNamesUsing and clobber one another; this
        //  authoritative resolver runs after they boot so every module model resolves correctly.)
        Factory::guessFactoryNamesUsing(function (string $modelName) {
            if (str_starts_with($modelName, 'Modules\\')) {
                $module = explode('\\', $modelName)[1] ?? null;
                $base = class_basename($modelName);
                if ($module) {
                    $factory = "Modules\\{$module}\\Database\\Factories\\{$base}Factory";
                    if (class_exists($factory)) {
                        return $factory;
                    }
                }
            }

            return 'Database\\Factories\\'.class_basename($modelName).'Factory';
        });

        // Disable foreign key constraints for SQLite testing
        if (config('database.default') === 'sqlite') {
            \DB::statement('PRAGMA foreign_keys=OFF');
        }
    }

    protected function tearDown(): void
    {
        if (config('database.default') === 'sqlite') {
            \DB::statement('PRAGMA foreign_keys=ON');
        }
        parent::tearDown();
    }

    protected function graphQL(string $query, array $variables = [], array $headers = [])
    {
        return $this->postJson('/graphql', [
            'query' => $query,
            'variables' => $variables,
        ], array_merge([
            'Accept' => 'application/json',
        ], $headers));
    }

    /**
     * Authenticate as a freshly-created user with the given role (class-test equivalent
     * of the Pest actingAsUser() helper).
     */
    protected function actingAsUser(string $role = 'admin'): \App\Models\User
    {
        if (\Spatie\Permission\Models\Permission::count() === 0) {
            // See tests/Pest.php's actingAsUser() for why: RolesAndPermissionsSeeder
            // is the seeder actually wired into DatabaseSeeder / used in production.
            $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        }

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = \App\Models\User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user, 'sanctum');

        return $user;
    }
}
