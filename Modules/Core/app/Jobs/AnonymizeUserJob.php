<?php

declare(strict_types=1);

namespace Modules\Core\Jobs;

use App\Models\PushToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\CRM\Models\Contact;
use Modules\HR\Models\Employee;
use Modules\Shared\Jobs\BaseAsyncJob;
use Spatie\Activitylog\Models\Activity;

/**
 * Anonymise all personal data for a user (GDPR right to erasure — art. 17).
 *
 * Runs asynchronously on the queue so the HTTP response stays fast.
 * Each step is wrapped in its own try/catch so a partial failure does not
 * prevent the rest of the anonymisation from running.
 *
 * Note: User is a system-wide entity. Company_id context is not applicable.
 * We use company_id = 0 as a system job indicator.
 */
class AnonymizeUserJob extends BaseAsyncJob
{
    public function __construct(public readonly int $userId)
    {
        // System job: user anonymization is global, not company-scoped
        parent::__construct(0);
    }

    protected function execute(): void
    {
        $user = User::find($this->userId);

        if (! $user) {
            return; // Already deleted
        }

        $anon = 'deleted_'.Str::random(12);

        DB::transaction(function () use ($user, $anon): void {
            // 1. Anonymise the User record
            $user->update([
                'name' => $anon,
                'email' => "{$anon}@deleted.invalid",
                'password' => bcrypt(Str::random(64)),
            ]);

            // 2. CRM — owned contacts: anonymise PII but keep business data
            $this->tryStep('CRM Contacts', fn () => Contact::where('owner_id', $user->id)->update([
                'first_name' => 'Deleted',
                'last_name' => 'User',
                'email' => null,
                'phone' => null,
                'mobile' => null,
                'linkedin_url' => null,
            ])
            );

            // 3. HR — employee record
            $this->tryStep('HR Employee', fn () => Employee::where('user_id', $user->id)->update([
                'first_name' => 'Deleted',
                'last_name' => 'Employee',
                'email' => "{$anon}@deleted.invalid",
                'phone' => null,
                'national_id' => null,
                'passport_number' => null,
                'emergency_contacts' => null,
                'bank_details' => null,
                'address' => null,
            ])
            );

            // 4. Ecommerce orders — anonymise shipping address / email
            $this->tryStep('Ecommerce Orders', fn () => DB::table('ecommerce_orders')
                ->where('user_id', $user->id)
                ->update([
                    'customer_name' => 'Deleted User',
                    'customer_email' => "{$anon}@deleted.invalid",
                    'shipping_address' => null,
                    'billing_address' => null,
                ])
            );

            // 5. Activity log — purge causer entries
            $this->tryStep('Activity Log', fn () => Activity::where('causer_id', $user->id)
                ->where('causer_type', get_class($user))
                ->delete()
            );

            // 6. Push tokens — deregister all devices
            $this->tryStep('Push Tokens', fn () => PushToken::where('user_id', $user->id)->delete()
            );

            // 7. Soft-delete the user
            $user->delete();
        });

        Log::info('[GDPR] User anonymised', ['user_id' => $this->userId]);
    }

    /** Run a step and log failures without interrupting the overall anonymisation. */
    private function tryStep(string $name, \Closure $step): void
    {
        try {
            $step();
        } catch (\Throwable $e) {
            Log::warning("[GDPR] Anonymisation step '{$name}' failed", [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
