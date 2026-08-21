<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Support\Facades\Log;
use Modules\Core\Models\ImportJob;
use Modules\Core\Models\ImportRow;

class ImportExecutorService
{
    /**
     * Import a single row into the appropriate model.
     */
    public function importRow(ImportJob $job, ImportRow $row): bool
    {
        try {
            $data = $row->mapped_data ?? [];

            $recordId = match ($job->target_entity) {
                'contact'  => $this->importContact($data),
                'lead'     => $this->importLead($data),
                'product'  => $this->importProduct($data),
                'employee' => $this->importEmployee($data),
                'supplier' => $this->importSupplier($data),
                'invoice'  => $this->importInvoice($data),
                default    => throw new \InvalidArgumentException("Unknown entity: {$job->target_entity}"),
            };

            $row->update([
                'status'            => 'imported',
                'created_record_id' => $recordId,
                'error_message'     => null,
            ]);

            return true;
        } catch (\Throwable $e) {
            $row->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::warning('[Import] Row failed', [
                'job_id'    => $job->id,
                'row_index' => $row->row_index,
                'error'     => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Execute all pending rows for an import job.
     */
    public function executeImport(ImportJob $job): void
    {
        $job->update(['status' => 'importing']);

        /** @var \Illuminate\Database\Eloquent\Collection<int, ImportRow> $pendingRows */
        $pendingRows = $job->rows()->where('status', 'pending')->get();

        foreach ($pendingRows as $row) {
            $success = $this->importRow($job, $row);

            if ($success) {
                $job->increment('processed');
            } else {
                $job->increment('failed');
            }
        }

        $job->update(['status' => 'completed']);
    }

    /**
     * Rollback an import by deleting all created records.
     */
    public function rollbackImport(ImportJob $job): void
    {
        $importedRows = $job->rows()->where('status', 'imported')->whereNotNull('created_record_id')->get();

        foreach ($importedRows as $row) {
            try {
                $this->deleteRecord($job->target_entity, (int) $row->created_record_id);
                $row->update(['status' => 'skipped', 'created_record_id' => null]);
            } catch (\Throwable $e) {
                Log::warning('[Import] Rollback row failed', [
                    'job_id'    => $job->id,
                    'row_id'    => $row->id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        $job->update([
            'status'    => 'failed',
            'processed' => 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function importContact(array $data): int
    {
        $contact = \Modules\CRM\Models\Contact::create([
            'first_name' => $data['first_name'] ?? '',
            'last_name'  => $data['last_name'] ?? '',
            'email'      => $data['email'] ?? null,
            'phone'      => $data['phone'] ?? null,
            'job_title'  => $data['job_title'] ?? null,
            'status'     => $data['status'] ?? 'active',
        ]);

        return $contact->id;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function importLead(array $data): int
    {
        $lead = \Modules\CRM\Models\Lead::create([
            'title'  => $data['title'] ?? 'Imported Lead',
            'status' => $data['status'] ?? 'new',
            'source' => $data['source'] ?? 'import',
            'score'  => isset($data['score']) ? (int) $data['score'] : null,
        ]);

        return $lead->id;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function importProduct(array $data): int
    {
        $product = \Modules\Inventory\Models\Product::create([
            'name'       => $data['name'] ?? '',
            'sku'        => $data['sku'] ?? '',
            'sale_price' => $data['price'] ?? 0,
            'cost_price' => $data['price'] ?? 0,
            'description'=> $data['description'] ?? null,
            'currency'   => $data['currency'] ?? 'EUR',
            'type'       => 'storable',
        ]);

        return $product->id;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function importEmployee(array $data): int
    {
        $employee = \Modules\HR\Models\Employee::create([
            'first_name'      => $data['first_name'] ?? '',
            'last_name'       => $data['last_name'] ?? '',
            'email'           => $data['email'] ?? '',
            'job_title'       => $data['job_title'] ?? null,
            'hire_date'       => $data['hire_date'] ?? now()->toDateString(),
            'employment_type' => 'full_time',
            'status'          => 'active',
        ]);

        return $employee->id;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function importSupplier(array $data): int
    {
        $supplier = \Modules\Inventory\Models\Supplier::create([
            'name'          => $data['name'] ?? '',
            'email'         => $data['email'] ?? null,
            'phone'         => $data['phone'] ?? null,
            'country'       => $data['country'] ?? null,
            'currency'      => $data['currency'] ?? 'EUR',
            'payment_terms' => $data['payment_terms'] ?? null,
        ]);

        return $supplier->id;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function importInvoice(array $data): int
    {
        $invoice = \Modules\Accounting\Models\Invoice::create([
            'number'       => $data['number'] ?? '',
            'partner_name' => $data['customer_email'] ?? '',
            'type'         => 'customer_invoice',
            'total'        => isset($data['amount']) ? (float) $data['amount'] : 0,
            'tax_amount'   => isset($data['tax']) ? (float) $data['tax'] : 0,
            'subtotal'     => isset($data['amount']) ? (float) $data['amount'] : 0,
            'amount_due'   => isset($data['amount']) ? (float) $data['amount'] : 0,
            'currency'     => $data['currency'] ?? 'EUR',
            'invoice_date' => $data['date'] ?? now()->toDateString(),
            'due_date'     => $data['due_date'] ?? null,
            'status'       => 'draft',
        ]);

        return $invoice->id;
    }

    private function deleteRecord(string $entity, int $id): void
    {
        match ($entity) {
            'contact'  => \Modules\CRM\Models\Contact::find($id)?->forceDelete(),
            'lead'     => \Modules\CRM\Models\Lead::find($id)?->forceDelete(),
            'product'  => \Modules\Inventory\Models\Product::find($id)?->forceDelete(),
            'employee' => \Modules\HR\Models\Employee::find($id)?->delete(),
            'supplier' => \Modules\Inventory\Models\Supplier::find($id)?->delete(),
            'invoice'  => \Modules\Accounting\Models\Invoice::find($id)?->delete(),
            default    => null,
        };
    }
}
