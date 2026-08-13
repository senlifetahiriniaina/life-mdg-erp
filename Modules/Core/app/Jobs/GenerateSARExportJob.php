<?php

declare(strict_types=1);

namespace Modules\Core\Jobs;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Shared\Jobs\BaseAsyncJob;
use ZipArchive;

/**
 * Generate Subject Access Request (SAR) export ZIP file.
 * GDPR Article 15 - Right of access.
 *
 * Runs asynchronously and stores the file for user download.
 * Files are retained for 30 days, then auto-deleted.
 *
 * Note: User is a system-wide entity. We use company_id = 0 as a system job indicator.
 */
class GenerateSARExportJob extends BaseAsyncJob
{
    public function __construct(public readonly int $userId, public readonly int $requestId)
    {
        // System GDPR job: user export is global, not company-scoped
        parent::__construct(0);
    }

    protected function execute(): void
    {
        $user = User::find($this->userId);
        if (! $user) {
            $this->updateRequestStatus('failed', 'User not found');

            return;
        }

        try {
            // Collect all personal data
            $data = $this->collectPersonalData($user);

            // Create JSON file
            $jsonPath = $this->createJsonExport($user, $data);

            // Create ZIP file
            $zipPath = $this->createZipArchive($user, $jsonPath, $data);

            // Store reference in database
            DB::table('gdpr_exports')->insert([
                'user_id' => $user->id,
                'request_id' => $this->requestId,
                'file_path' => $zipPath,
                'expires_at' => now()->addDays(30),
                'downloaded_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Update request status
            $this->updateRequestStatus('completed', 'Export ready for download');

            Log::info("SAR export generated for user {$user->id}", [
                'file_size' => Storage::disk('exports')->size($zipPath),
                'expires_at' => now()->addDays(30),
            ]);

        } catch (\Exception $e) {
            $this->updateRequestStatus('failed', $e->getMessage());
            Log::error("SAR export generation failed for user {$user->id}: {$e->getMessage()}");
            throw $e;
        }
    }

    private function collectPersonalData(User $user): array
    {
        $data = [];

        // 1. User profile
        $data['user'] = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'locale' => $user->locale,
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];

        // 2. HR Employee data
        $employee = DB::table('hr_employees')->where('user_id', $user->id)->first();
        if ($employee) {
            $data['employee'] = (array) $employee;
            // Include note about encrypted fields
            $data['employee']['_encrypted_fields'] = [
                'national_id_encrypted',
                'passport_number_encrypted',
                'bank_details_encrypted',
                'emergency_contacts_encrypted',
            ];
            $data['employee']['_note'] = 'PII fields are encrypted at rest. Decryption available upon verification of identity.';
        }

        // 3. CRM Contacts owned by user
        $data['crm_contacts'] = DB::table('crm_contacts')
            ->where('owner_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();

        // 4. CRM Leads
        $data['crm_leads'] = DB::table('crm_leads')
            ->where('owner_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();

        // 5. CRM Opportunities
        $data['crm_opportunities'] = DB::table('crm_opportunities')
            ->where('owner_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();

        // 6. Accounting Invoices
        $data['accounting_invoices'] = DB::table('acc_invoices')
            ->where('created_by', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(500)
            ->get()
            ->toArray();

        // 7. Ecommerce Orders
        $data['ecommerce_orders'] = DB::table('ecommerce_orders')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(500)
            ->get()
            ->toArray();

        // 8. Consent logs
        $data['consents'] = DB::table('consent_logs')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(1000)
            ->get()
            ->toArray();

        // 9. Activity logs
        $data['activity_logs'] = DB::table('activity_log')
            ->where('causer_id', $user->id)
            ->orWhere('subject_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(500)
            ->get()
            ->toArray();

        // 10. Audit logs
        $data['audit_logs'] = DB::table('audit_logs')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(500)
            ->get()
            ->toArray();

        // 11. Login history
        $data['login_history'] = DB::table('login_history')
            ->where('user_id', $user->id)
            ->orderBy('logged_in_at', 'desc')
            ->limit(100)
            ->get()
            ->toArray();

        // 12. Push tokens / devices
        $data['devices'] = DB::table('push_tokens')
            ->where('user_id', $user->id)
            ->get()
            ->toArray();

        return $data;
    }

    private function createJsonExport(User $user, array $data): string
    {
        $fileName = "sar_export_{$user->id}_".now()->format('Y-m-d_H-i-s').'.json';
        $content = json_encode([
            'export_metadata' => [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'exported_at' => now()->toIso8601String(),
                'gdpr_article' => 'Article 15 - Right of access',
                'valid_until' => now()->addDays(30)->toIso8601String(),
                'data_count' => array_reduce($data, fn ($carry, $items) => $carry + (is_array($items) ? count($items) : 1), 0),
            ],
            'personal_data' => $data,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        Storage::disk('exports')->put($fileName, $content);

        return $fileName;
    }

    private function createZipArchive(User $user, string $jsonPath, array $data): string
    {
        $zipName = "sar_export_{$user->id}_".now()->format('Y-m-d_H-i-s').'.zip';
        $zipPath = Storage::disk('exports')->path($zipName);

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            throw new \Exception("Cannot create ZIP archive: $zipPath");
        }

        // Add JSON file
        $jsonFullPath = Storage::disk('exports')->path($jsonPath);
        $zip->addFile($jsonFullPath, 'personal_data.json');

        // Add README
        $readme = $this->generateReadme($user);
        $zip->addFromString('README.txt', $readme);

        // Add CSV exports for easy viewing
        foreach ($data as $section => $items) {
            if (is_array($items) && ! empty($items)) {
                $csv = $this->generateCsv($items);
                $zip->addFromString("{$section}.csv", $csv);
            }
        }

        $zip->close();

        return $zipName;
    }

    private function generateReadme(User $user): string
    {
        return <<<'EOT'
SUBJECT ACCESS REQUEST EXPORT
=============================

This is your personal data export as per GDPR Article 15 (Right of Access).

CONTENTS:
---------
- personal_data.json: Complete data export in JSON format
- *.csv files: Data in CSV format for easy viewing in spreadsheet applications

METADATA:
---------
User ID: {$user->id}
User Email: {$user->email}
Export Date: {date('Y-m-d H:i:s')}
Valid Until: 30 days from export date
Generated by: WideHalo ERP

SECURITY:
---------
Some fields (national ID, passport, bank details) are encrypted at rest.
Contact support@widehalo.com to request decryption with identity verification.

DATA RETENTION:
---------------
This export will be automatically deleted after 30 days from generation.
Download and save this file if you need to retain it longer.

RIGHTS:
-------
You have the right to:
- Receive your data in a structured, commonly used, machine-readable format
- Data portability (Article 20)
- Erasure/deletion of your data (Article 17)
- Rectification of inaccurate data (Article 16)

For more information, see our Privacy Policy or contact: privacy@widehalo.com

EOT;
    }

    private function generateCsv(array $items): string
    {
        if (empty($items)) {
            return '';
        }

        $first = reset($items);
        if (! is_array($first) && ! is_object($first)) {
            return '';
        }

        // Convert objects to arrays
        $items = array_map(fn ($item) => (array) $item, $items);

        // Get all keys
        $allKeys = [];
        foreach ($items as $item) {
            $allKeys = array_unique(array_merge($allKeys, array_keys($item)));
        }

        // Build CSV
        $csv = implode(',', array_map(fn ($k) => '"'.str_replace('"', '""', $k).'"', $allKeys))."\n";

        foreach ($items as $item) {
            $row = [];
            foreach ($allKeys as $key) {
                $value = $item[$key] ?? '';
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value);
                }
                $row[] = '"'.str_replace('"', '""', (string) $value).'"';
            }
            $csv .= implode(',', $row)."\n";
        }

        return $csv;
    }

    private function updateRequestStatus(string $status, string $message = ''): void
    {
        DB::table('gdpr_requests')
            ->where('id', $this->requestId)
            ->update([
                'status' => $status,
                'completed_at' => $status === 'completed' ? now() : null,
                'failure_reason' => $message ?: null,
                'updated_at' => now(),
            ]);
    }
}
