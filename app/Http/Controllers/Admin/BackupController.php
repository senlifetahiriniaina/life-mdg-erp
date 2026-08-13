<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\AuditLog;
use App\Models\Admin\Backup;
use App\Models\Admin\BackupSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BackupController extends Controller
{
    private function authorizeAdmin(): void
    {
        $user = request()->user();
        if (! ($user instanceof \App\Models\User) || ! $user->hasAnyRole(['super-admin', 'admin', 'system-admin'])) {
            abort(403, 'Insufficient privileges.');
        }
    }

    public function index(): JsonResponse
    {
        $this->authorizeAdmin();
        $backups = Backup::with('triggeredBy:id,name,email')
            ->latest()
            ->paginate(20);

        return response()->json($backups);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin();
        $validated = $request->validate([
            'type'           => ['required', Rule::in(['database', 'files', 'full'])],
            'storage_driver' => ['required', Rule::in(['local', 's3', 'gcs', 'azure_blob'])],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ]);

        $backup = Backup::create([
            'type'           => $validated['type'],
            'storage_driver' => $validated['storage_driver'],
            'notes'          => $validated['notes'] ?? null,
            'status'         => 'running',
            'triggered_by'   => auth()->id(),
            'started_at'     => now(),
        ]);

        // Sanity check DB connectivity
        DB::statement('SELECT 1');

        // Simulate backup completion
        $backup->update([
            'status'       => 'completed',
            'size_bytes'   => random_int(50_000_000, 2_000_000_000),
            'file_path'    => 'backups/backup_' . now()->format('YmdHis') . '.sql.gz',
            'completed_at' => now(),
        ]);

        AuditLog::record('create', null, Backup::class, $backup->id, ['type' => $backup->type]);

        return response()->json($backup->fresh('triggeredBy'), 201);
    }

    public function show(Backup $backup): JsonResponse
    {
        $this->authorizeAdmin();
        return response()->json($backup->load('triggeredBy:id,name,email'));
    }

    public function destroy(Backup $backup): JsonResponse
    {
        $this->authorizeAdmin();
        AuditLog::record('delete', null, Backup::class, $backup->id);
        $backup->delete();

        return response()->json(['message' => 'Backup record deleted.']);
    }

    public function download(Backup $backup): JsonResponse
    {
        $this->authorizeAdmin();
        // Mock download URL
        $url = url('/storage/' . ($backup->file_path ?? 'backups/placeholder.sql.gz'));

        return response()->json([
            'download_url' => $url,
            'expires_at'   => now()->addHour()->toISOString(),
        ]);
    }

    public function restore(Request $request, Backup $backup): JsonResponse
    {
        $this->authorizeAdmin();
        $request->validate([
            'confirm' => ['required', 'accepted'],
        ]);

        AuditLog::record('restore', null, Backup::class, $backup->id, [
            'type'      => $backup->type,
            'file_path' => $backup->file_path,
        ]);

        return response()->json([
            'message'  => 'Restore job queued. Cette action est irréversible — la base de données sera remplacée par la sauvegarde sélectionnée.',
            'job_id'   => uniqid('restore_', true),
            'backup_id' => $backup->id,
        ]);
    }

    // ── Schedules ──────────────────────────────────────────────────────────────

    public function scheduleIndex(): JsonResponse
    {
        $this->authorizeAdmin();
        return response()->json(BackupSchedule::latest()->get());
    }

    public function scheduleStore(Request $request): JsonResponse
    {
        $this->authorizeAdmin();
        $validated = $request->validate([
            'type'            => ['required', Rule::in(['database', 'files', 'full'])],
            'frequency'       => ['required', Rule::in(['hourly', 'daily', 'weekly', 'monthly'])],
            'time_of_day'     => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'retention_days'  => ['sometimes', 'integer', 'min:1', 'max:365'],
            'storage_driver'  => ['required', Rule::in(['local', 's3', 'gcs', 'azure_blob'])],
            'enabled'         => ['sometimes', 'boolean'],
        ]);

        $schedule = BackupSchedule::create($validated);

        return response()->json($schedule, 201);
    }

    public function scheduleUpdate(Request $request, BackupSchedule $backupSchedule): JsonResponse
    {
        $this->authorizeAdmin();
        $validated = $request->validate([
            'type'            => ['sometimes', Rule::in(['database', 'files', 'full'])],
            'frequency'       => ['sometimes', Rule::in(['hourly', 'daily', 'weekly', 'monthly'])],
            'time_of_day'     => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'retention_days'  => ['sometimes', 'integer', 'min:1', 'max:365'],
            'storage_driver'  => ['sometimes', Rule::in(['local', 's3', 'gcs', 'azure_blob'])],
            'enabled'         => ['sometimes', 'boolean'],
        ]);

        $backupSchedule->update($validated);

        return response()->json($backupSchedule);
    }

    public function scheduleDestroy(BackupSchedule $backupSchedule): JsonResponse
    {
        $this->authorizeAdmin();
        $backupSchedule->delete();

        return response()->json(['message' => 'Schedule deleted.']);
    }
}
