<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Core - Global Search
 *
 * Cross-module full-text search.
 */
class GlobalSearchController extends Controller
{
    private const LIMIT_PER_TYPE = 5;

    /**
     * Global search across all enabled modules.
     *
     * @queryParam q string required Search query (min 2 chars). Example: Dupont
     * @queryParam modules string Comma-separated module filter. Example: CRM,Helpdesk
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:200'],
            'modules' => ['nullable', 'string'],
        ]);

        $q = $request->q;
        $filter = $request->modules ? explode(',', $request->modules) : null;
        $results = [];

        if (! $filter || in_array('CRM', $filter, true)) {
            $results['contacts'] = $this->searchContacts($q);
            $results['accounts'] = $this->searchAccounts($q);
        }

        if (! $filter || in_array('Inventory', $filter, true)) {
            $results['products'] = $this->searchProducts($q);
        }

        if (! $filter || in_array('Accounting', $filter, true)) {
            $results['invoices'] = $this->searchInvoices($q);
        }

        if (! $filter || in_array('Helpdesk', $filter, true)) {
            $results['tickets'] = $this->searchTickets($q);
        }

        if (! $filter || in_array('Projects', $filter, true)) {
            $results['projects'] = $this->searchProjects($q);
            $results['tasks'] = $this->searchTasks($q);
        }

        if (! $filter || in_array('HR', $filter, true)) {
            $results['employees'] = $this->searchEmployees($q);
        }

        $total = collect($results)->sum(fn ($r) => count($r));

        return response()->json([
            'query' => $q,
            'total' => $total,
            'results' => $results,
        ]);
    }

    // ── Per-module search helpers ─────────────────────────────────────────────

    private function searchContacts(string $q): array
    {
        if (! DB::getSchemaBuilder()->hasTable('crm_contacts')) {
            return [];
        }

        return DB::table('crm_contacts')
            ->where(fn ($qb) => $qb
                ->where('first_name', 'like', "%{$q}%")
                ->orWhere('last_name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%"))
            ->whereNull('deleted_at')
            ->select('id', DB::raw("CONCAT(first_name, ' ', last_name) as label"), 'email', DB::raw("'contact' as type"), DB::raw("'/crm/contacts/' + id as url"))
            ->limit(self::LIMIT_PER_TYPE)
            ->get()->toArray();
    }

    private function searchAccounts(string $q): array
    {
        if (! DB::getSchemaBuilder()->hasTable('crm_accounts')) {
            return [];
        }

        return DB::table('crm_accounts')
            ->where('name', 'like', "%{$q}%")
            ->whereNull('deleted_at')
            ->select('id', 'name as label', DB::raw("'account' as type"))
            ->limit(self::LIMIT_PER_TYPE)
            ->get()->toArray();
    }

    private function searchProducts(string $q): array
    {
        if (! DB::getSchemaBuilder()->hasTable('inventory_products')) {
            return [];
        }

        return DB::table('inventory_products')
            ->where(fn ($qb) => $qb->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%"))
            ->whereNull('deleted_at')
            ->select('id', 'name as label', 'sku', DB::raw("'product' as type"))
            ->limit(self::LIMIT_PER_TYPE)
            ->get()->toArray();
    }

    private function searchInvoices(string $q): array
    {
        if (! DB::getSchemaBuilder()->hasTable('acc_invoices')) {
            return [];
        }

        return DB::table('acc_invoices')
            ->where(fn ($qb) => $qb->where('number', 'like', "%{$q}%")->orWhere('partner_name', 'like', "%{$q}%"))
            ->whereNull('deleted_at')
            ->select('id', 'number as label', 'partner_name', 'status', DB::raw("'invoice' as type"))
            ->limit(self::LIMIT_PER_TYPE)
            ->get()->toArray();
    }

    private function searchTickets(string $q): array
    {
        if (! DB::getSchemaBuilder()->hasTable('hd_tickets')) {
            return [];
        }

        return DB::table('hd_tickets')
            ->where(fn ($qb) => $qb->where('subject', 'like', "%{$q}%")->orWhere('description', 'like', "%{$q}%"))
            ->whereNull('deleted_at')
            ->select('id', 'subject as label', 'status', DB::raw("'ticket' as type"))
            ->limit(self::LIMIT_PER_TYPE)
            ->get()->toArray();
    }

    private function searchProjects(string $q): array
    {
        if (! DB::getSchemaBuilder()->hasTable('prj_projects')) {
            return [];
        }

        return DB::table('prj_projects')
            ->where(fn ($qb) => $qb->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%"))
            ->whereNull('deleted_at')
            ->select('id', 'name as label', 'status', DB::raw("'project' as type"))
            ->limit(self::LIMIT_PER_TYPE)
            ->get()->toArray();
    }

    private function searchTasks(string $q): array
    {
        if (! DB::getSchemaBuilder()->hasTable('prj_tasks')) {
            return [];
        }

        return DB::table('prj_tasks')
            ->where('title', 'like', "%{$q}%")
            ->whereNull('deleted_at')
            ->select('id', 'title as label', 'status', DB::raw("'task' as type"))
            ->limit(self::LIMIT_PER_TYPE)
            ->get()->toArray();
    }

    private function searchEmployees(string $q): array
    {
        if (! DB::getSchemaBuilder()->hasTable('hr_employees')) {
            return [];
        }

        return DB::table('hr_employees')
            ->where(fn ($qb) => $qb
                ->where('first_name', 'like', "%{$q}%")
                ->orWhere('last_name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%"))
            ->whereNull('deleted_at')
            ->select('id', DB::raw("CONCAT(first_name, ' ', last_name) as label"), 'email', DB::raw("'employee' as type"))
            ->limit(self::LIMIT_PER_TYPE)
            ->get()->toArray();
    }
}
