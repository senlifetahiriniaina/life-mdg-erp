<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Encryption\Encrypter;

return new class extends Migration
{
    /**
     * Migrate existing PII data to encrypted columns.
     * This runs AFTER the encryption columns are created.
     */
    public function up(): void
    {
        $cipher = strtolower(config('app.cipher'));
        $key = config('app.key');

        if (!$cipher || !$key) {
            throw new \Exception('Encryption key not configured. Set APP_KEY in .env');
        }

        // Handle base64: prefix
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        $encrypter = new Encrypter($key, $cipher);

        // Migrate national_id
        if (Schema::hasColumn('hr_employees', 'national_id')) {
            DB::table('hr_employees')
                ->whereNotNull('national_id')
                ->where('national_id', '!=', '')
                ->update([
                    'national_id_encrypted' => DB::raw(
                        "CASE WHEN national_id IS NOT NULL THEN '" .
                        "' ELSE national_id_encrypted END"
                    ),
                ]);

            // Update with encrypted values (raw SQL approach for batch)
            $employees = DB::table('hr_employees')
                ->whereNotNull('national_id')
                ->where('national_id', '!=', '')
                ->get();

            foreach ($employees as $employee) {
                if ($employee->national_id) {
                    try {
                        $encrypted = $encrypter->encrypt($employee->national_id);
                        DB::table('hr_employees')
                            ->where('id', $employee->id)
                            ->update(['national_id_encrypted' => $encrypted]);
                    } catch (\Exception $e) {
                        \Log::warning("Failed to encrypt national_id for employee {$employee->id}: {$e->getMessage()}");
                    }
                }
            }
        }

        // Migrate passport_number
        if (Schema::hasColumn('hr_employees', 'passport_number')) {
            $employees = DB::table('hr_employees')
                ->whereNotNull('passport_number')
                ->where('passport_number', '!=', '')
                ->get();

            foreach ($employees as $employee) {
                if ($employee->passport_number) {
                    try {
                        $encrypted = $encrypter->encrypt($employee->passport_number);
                        DB::table('hr_employees')
                            ->where('id', $employee->id)
                            ->update(['passport_number_encrypted' => $encrypted]);
                    } catch (\Exception $e) {
                        \Log::warning("Failed to encrypt passport_number for employee {$employee->id}: {$e->getMessage()}");
                    }
                }
            }
        }

        // Migrate bank_details (JSON)
        if (Schema::hasColumn('hr_employees', 'bank_details')) {
            $employees = DB::table('hr_employees')
                ->whereNotNull('bank_details')
                ->where('bank_details', '!=', '')
                ->get();

            foreach ($employees as $employee) {
                if ($employee->bank_details) {
                    try {
                        $encrypted = $encrypter->encrypt($employee->bank_details);
                        DB::table('hr_employees')
                            ->where('id', $employee->id)
                            ->update(['bank_details_encrypted' => $encrypted]);
                    } catch (\Exception $e) {
                        \Log::warning("Failed to encrypt bank_details for employee {$employee->id}: {$e->getMessage()}");
                    }
                }
            }
        }

        // Migrate emergency_contacts (JSON)
        if (Schema::hasColumn('hr_employees', 'emergency_contacts')) {
            $employees = DB::table('hr_employees')
                ->whereNotNull('emergency_contacts')
                ->where('emergency_contacts', '!=', '')
                ->get();

            foreach ($employees as $employee) {
                if ($employee->emergency_contacts) {
                    try {
                        $encrypted = $encrypter->encrypt($employee->emergency_contacts);
                        DB::table('hr_employees')
                            ->where('id', $employee->id)
                            ->update(['emergency_contacts_encrypted' => $encrypted]);
                    } catch (\Exception $e) {
                        \Log::warning("Failed to encrypt emergency_contacts for employee {$employee->id}: {$e->getMessage()}");
                    }
                }
            }
        }

        \Log::info('Employee PII migration to encrypted columns completed');
    }

    public function down(): void
    {
        // Note: Cannot safely restore original unencrypted data
        \Log::warning('Reversing PII encryption migration - encrypted data will be cleared');

        DB::table('hr_employees')->update([
            'national_id_encrypted' => null,
            'passport_number_encrypted' => null,
            'bank_details_encrypted' => null,
            'emergency_contacts_encrypted' => null,
        ]);
    }
};
