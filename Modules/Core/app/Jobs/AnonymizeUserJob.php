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
 *
 * Chantier 32.1 found and fixed two real, previously-undocumented,
 * guaranteed-fatal bugs on this exact job — confirmed empirically (tinker,
 * with the app's real QUEUE_CONNECTION=sync driver) that `php artisan
 * gdpr:anonymize-user <id>` has never actually completed, on any input,
 * since it was written; both went undetected because zero test anywhere
 * covers this job or its console command:
 * 1. `parent::__construct(0)` — BaseAsyncJob (and every trait it uses)
 *    declares no constructor at all anywhere in its inheritance chain, and
 *    PHP fatals with "Cannot call constructor" when a subclass calls
 *    parent::__construct() and no parent constructor exists anywhere up the
 *    chain — this call was removed rather than routed to a real base
 *    constructor, since BaseAsyncJob never accepted a company/tenant id
 *    parameter to begin with.
 * 2. No handle() method — BaseAsyncJob doesn't define one either, so
 *    Laravel's queue dispatcher falls back to __invoke(), which doesn't
 *    exist, a guaranteed "Call to undefined method ...::__invoke()" fatal
 *    on every real dispatch. Fixed locally (see ExtractAndMapImportJob's
 *    docblock for why this wasn't fixed on the shared base class itself —
 *    28 other job classes across Accounting and other modules outside this
 *    chantier's scope share the same defect, flagged for a future chantier
 *    in CLAUDE.md's Chantier 32.1 entry).
 */
class AnonymizeUserJob extends BaseAsyncJob
{
    public function __construct(public readonly int $userId) {}

    public function handle(): void
    {
        $this->execute();
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
