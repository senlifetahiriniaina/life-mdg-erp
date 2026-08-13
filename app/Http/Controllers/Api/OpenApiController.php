<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class OpenApiController extends Controller
{
    public function spec(): Response
    {
        $spec = [
            'openapi' => '3.0.3',
            'info'    => [
                'title'       => 'WideHalo ERP API',
                'version'     => '1.0.0',
                'description' => 'Complete ERP API for WideHalo — CRM, HR, Inventory, Accounting, Manufacturing, POS, Ecommerce, BI, Email, Documents, Helpdesk, Projects, WhatsApp modules.',
                'contact'     => ['name' => 'WideHalo Support', 'email' => 'support@widehalo.com'],
            ],
            'servers' => [
                ['url' => config('app.url') . '/api/v1', 'description' => 'Production'],
            ],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'Sanctum'],
                ],
            ],
            'security' => [['bearerAuth' => []]],
            'tags' => [
                ['name' => 'Auth',          'description' => 'Authentication'],
                ['name' => 'CRM',           'description' => 'Customer Relationship Management'],
                ['name' => 'HR',            'description' => 'Human Resources'],
                ['name' => 'Inventory',     'description' => 'Inventory & Warehouse'],
                ['name' => 'Accounting',    'description' => 'Accounting & Finance'],
                ['name' => 'Manufacturing', 'description' => 'Manufacturing & Production'],
                ['name' => 'POS',           'description' => 'Point of Sale'],
                ['name' => 'Ecommerce',     'description' => 'E-Commerce'],
                ['name' => 'BI',            'description' => 'Business Intelligence'],
                ['name' => 'Email',         'description' => 'Email Marketing'],
                ['name' => 'Documents',     'description' => 'Document Management'],
                ['name' => 'Helpdesk',      'description' => 'Helpdesk & Support'],
                ['name' => 'Projects',      'description' => 'Project Management'],
                ['name' => 'WhatsApp',      'description' => 'WhatsApp Business'],
                ['name' => 'Webhooks',      'description' => 'Outbound Webhooks'],
            ],
            'paths' => $this->buildPaths(),
        ];

        return response(json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 200, [
            'Content-Type' => 'application/json',
        ]);
    }

    private function buildPaths(): array
    {
        return [
            '/auth/login'          => ['post' => ['tags' => ['Auth'], 'summary' => 'Authenticate user', 'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['email' => ['type' => 'string'], 'password' => ['type' => 'string']]]]]], 'responses' => ['200' => ['description' => 'Success']]]],
            '/crm/contacts'        => ['get' => ['tags' => ['CRM'], 'summary' => 'List contacts', 'responses' => ['200' => ['description' => 'Paginated contacts list']]], 'post' => ['tags' => ['CRM'], 'summary' => 'Create contact', 'responses' => ['201' => ['description' => 'Contact created']]]],
            '/hr/employees'        => ['get' => ['tags' => ['HR'], 'summary' => 'List employees', 'responses' => ['200' => ['description' => 'Paginated employees list']]]],
            '/accounting/invoices' => ['get' => ['tags' => ['Accounting'], 'summary' => 'List invoices', 'responses' => ['200' => ['description' => 'Paginated invoices list']]]],
            '/webhooks'            => ['get' => ['tags' => ['Webhooks'], 'summary' => 'List webhooks', 'responses' => ['200' => ['description' => 'List of registered webhooks']]], 'post' => ['tags' => ['Webhooks'], 'summary' => 'Register webhook', 'responses' => ['201' => ['description' => 'Webhook created']]]],
        ];
    }
}
