<?php

declare(strict_types=1);

namespace Modules\Core\Jobs;

use App\Events\SarExportReady;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\CRM\Models\Contact;
use Modules\CRM\Models\Lead;
use Modules\Helpdesk\Models\Ticket;
use Modules\Shared\Jobs\BaseAsyncJob;
use Spatie\Activitylog\Models\Activity;

/**
 * Compile and store a GDPR Subject Access Request (SAR) export for the given user.
 *
 * The resulting JSON file is written to storage/app/private/sar/{userId}/export.json
 * and an `SarExportReady` event is dispatched so that downstream listeners can
 * notify the user (e.g. via e-mail).
 *
 * Note: User is a system-wide entity. We use company_id = 0 as a system job indicator.
 *
 * Chantier 32.1: had no handle() method — same missing-handle() defect
 * documented on ExtractAndMapImportJob/ExecuteImportJob/AnonymizeUserJob,
 * confirmed empirically (tinker) to fatal on every real dispatch with
 * "Call to undefined method SarExportJob::__invoke()". Undetected by the
 * pre-existing tests/Feature/Api/SarExportTest.php because that test uses
 * Queue::fake() and only ever asserts the job was pushed, never actually
 * runs it — see CLAUDE.md's Chantier 32.1 entry.
 */
class SarExportJob extends BaseAsyncJob
{
    public function __construct(public readonly User $user)
    {
    }

    public function handle(): void
    {
        $this->execute();
    }

    protected function execute(): void
    {
        $user = $this->user;

        $data = [
            'exported_at' => now()->toIso8601String(),
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'locale' => $user->locale ?? null,
                'created_at' => optional($user->created_at)->toIso8601String(),
            ],
            'crm_contacts' => $this->collectCrmContacts($user),
            'crm_leads' => $this->collectCrmLeads($user),
            'helpdesk_tickets' => $this->collectHelpdeskTickets($user),
            'activity_log' => $this->collectActivityLog($user),
            'sync_queue' => $this->collectSyncQueue($user),
        ];

        $path = "sar/{$user->id}/export.json";

        Storage::disk('local')->put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        Log::info('[GDPR] SAR export written', ['user_id' => $user->id, 'path' => $path]);

        SarExportReady::dispatch($user, $path);
    }

    /** @return array<int, array<string, mixed>> */
    private function collectCrmContacts(User $user): array
    {
        if (! class_exists(Contact::class)) {
            return [];
        }

        return Contact::where('created_by', $user->id)
            ->get(['first_name', 'last_name', 'email', 'phone'])
            ->toArray();
    }

    /** @return array<int, array<string, mixed>> */
    private function collectCrmLeads(User $user): array
    {
        if (! class_exists(Lead::class)) {
            return [];
        }

        return Lead::where('created_by', $user->id)
            ->get(['first_name', 'last_name', 'email'])
            ->toArray();
    }

    /** @return array<int, array<string, mixed>> */
    private function collectHelpdeskTickets(User $user): array
    {
        if (! class_exists(Ticket::class)) {
            return [];
        }

        return Ticket::where('reporter_id', $user->id)
            ->get(['subject', 'status', 'created_at'])
            ->toArray();
    }

    /** @return array<int, array<string, mixed>> */
    private function collectActivityLog(User $user): array
    {
        if (! class_exists(Activity::class)) {
            return [];
        }

        return Activity::where('causer_id', $user->id)
            ->where('causer_type', get_class($user))
            ->latest()
            ->limit(500)
            ->get(['description', 'subject_type', 'created_at'])
            ->toArray();
    }

    /** @return array<int, array<string, mixed>> */
    private function collectSyncQueue(User $user): array
    {
        return DB::table('sync_queue')
            ->where('user_id', $user->id)
            ->select(['entity_type', 'operation', 'created_at'])
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->toArray();
    }
}
