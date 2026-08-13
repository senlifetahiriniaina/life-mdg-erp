<?php

namespace Modules\Reporting\Tests\Feature;

use Tests\TestCase;
use Modules\Reporting\Services\NlToSqlService;

class NlToSqlServiceTest extends TestCase
{
    private NlToSqlService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(NlToSqlService::class);
    }

    /** @test */
    public function it_converts_natural_language_to_sql()
    {
        $query = "Show all customers from Senegal";
        $result = $this->service->queryToSql($query, 'en');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('query', $result);
    }

    /** @test */
    public function it_returns_fallback_when_api_unavailable()
    {
        $this->app['config']->set('services.anthropic.key', null);

        $result = $this->service->queryToSql("Show customers", 'en');

        $this->assertFalse($result['enabled'] ?? false);
    }

    /** @test */
    public function it_supports_french_queries()
    {
        $query = "Affiche tous les clients";
        $result = $this->service->queryToSql($query, 'fr');

        $this->assertIsArray($result);
    }

    /** @test */
    public function it_validates_sql_safety()
    {
        $safeSql = "SELECT * FROM customers WHERE country = ?";
        $validation = $this->service->validateSql($safeSql, ['SN']);

        $this->assertTrue($validation['valid']);
    }

    /** @test */
    public function it_rejects_dangerous_sql()
    {
        $dangerousSql = "DROP TABLE customers";
        $validation = $this->service->validateSql($dangerousSql);

        $this->assertFalse($validation['valid']);
    }

    /** @test */
    public function it_validates_parameter_count()
    {
        $sql = "SELECT * FROM customers WHERE country = ? AND status = ?";
        $validation = $this->service->validateSql($sql, ['SN']); // Only 1 param, need 2

        $this->assertFalse($validation['valid']);
    }

    /** @test */
    public function it_executes_validated_query()
    {
        $sql = "SELECT COUNT(*) as count FROM users LIMIT 1";
        $result = $this->service->execute($sql, []);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('rows', $result);
    }

    /** @test */
    public function it_prevents_execution_of_unsafe_sql()
    {
        $sql = "DELETE FROM users";
        $result = $this->service->execute($sql, []);

        $this->assertFalse($result['success']);
    }

    /** @test */
    public function it_handles_query_execution_errors()
    {
        $sql = "SELECT * FROM nonexistent_table";
        $result = $this->service->execute($sql, []);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    /** @test */
    public function it_supports_multiple_locales()
    {
        $locales = ['en', 'fr', 'es', 'pt'];

        foreach ($locales as $locale) {
            $result = $this->service->queryToSql("Show data", $locale);
            $this->assertIsArray($result);
        }
    }
}
