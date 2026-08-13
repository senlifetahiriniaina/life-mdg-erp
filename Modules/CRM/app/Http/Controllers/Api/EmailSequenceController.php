<?php

declare(strict_types=1);

namespace Modules\CRM\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Models\Contact;
use Modules\CRM\Models\EmailSequence;
use Modules\CRM\Models\EmailSequenceEnrollment;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\SequenceStep;
use Modules\CRM\Services\EmailSequenceService;
use Modules\CRM\Services\SequenceService;

/**
 * @group CRM - Email Sequences
 */
class EmailSequenceController extends Controller
{
    public function __construct(
        private readonly EmailSequenceService $service,
        private readonly SequenceService $legacyService,
    ) {}

    // ── New v2 endpoints (crm/email-sequences) ────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $sequences = EmailSequence::withCount('enrollments')
            ->with('steps')
            ->latest()
            ->paginate(25);

        return response()->json($sequences);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,active,paused'],
            'trigger_type' => ['nullable', 'in:manual,contact_created,tag_added'],
            'trigger_config' => ['nullable', 'array'],
            // Legacy fields for backward compatibility
            'trigger' => ['nullable', 'string'],
            'trigger_conditions' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
            'steps' => ['nullable', 'array'],
            'steps.*.step_order' => ['nullable', 'integer'],
            'steps.*.order' => ['nullable', 'integer'],
            'steps.*.delay_hours' => ['nullable', 'integer'],
            'steps.*.delay_days' => ['nullable', 'integer'],
            'steps.*.subject' => ['required_with:steps', 'string'],
            'steps.*.body_html' => ['nullable', 'string'],
            'steps.*.body' => ['nullable', 'string'],
        ]);

        $steps = $data['steps'] ?? [];
        unset($data['steps']);

        $data['created_by'] = $request->user()->id;

        $sequence = $this->service->createSequence($data);

        // Create steps if provided (legacy support)
        foreach ($steps as $stepData) {
            $sequence->steps()->create([
                'order' => $stepData['order'] ?? $stepData['step_order'] ?? 1,
                'delay_days' => $stepData['delay_days'] ?? (int) (($stepData['delay_hours'] ?? 0) / 24),
                'subject' => $stepData['subject'],
                'body' => $stepData['body'] ?? $stepData['body_html'] ?? '',
            ]);
        }

        return response()->json($sequence->load('steps'), 201);
    }

    public function show(EmailSequence $sequence): JsonResponse
    {
        return response()->json(
            $sequence->loadCount('steps')->load('steps', 'creator')
        );
    }

    public function update(Request $request, EmailSequence $sequence): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,active,paused'],
            'trigger_type' => ['nullable', 'in:manual,contact_created,tag_added'],
            'trigger_config' => ['nullable', 'array'],
        ]);

        $sequence->update($data);

        return response()->json($sequence->fresh());
    }

    public function destroy(EmailSequence $sequence): JsonResponse
    {
        $sequence->delete();

        return response()->json(null, 204);
    }

    public function activate(EmailSequence $sequence): JsonResponse
    {
        $sequence->activate();

        return response()->json($sequence->fresh());
    }

    public function pause(EmailSequence $sequence): JsonResponse
    {
        $sequence->pause();

        return response()->json($sequence->fresh());
    }

    public function steps(EmailSequence $sequence): JsonResponse
    {
        return response()->json($sequence->steps()->orderBy('order')->get());
    }

    public function addStep(Request $request, EmailSequence $sequence): JsonResponse
    {
        $data = $request->validate([
            'delay_days' => ['nullable', 'integer', 'min:0'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'from_name' => ['nullable', 'string', 'max:100'],
            'from_email' => ['nullable', 'email', 'max:255'],
        ]);

        $step = $this->service->addStep($sequence, $data);

        return response()->json($step, 201);
    }

    public function updateStep(Request $request, EmailSequence $sequence, SequenceStep $step): JsonResponse
    {
        $data = $request->validate([
            'order' => ['nullable', 'integer', 'min:1'],
            'delay_days' => ['nullable', 'integer', 'min:0'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'from_name' => ['nullable', 'string', 'max:100'],
            'from_email' => ['nullable', 'email', 'max:255'],
        ]);

        $step->update($data);

        return response()->json($step->fresh());
    }

    public function deleteStep(EmailSequence $sequence, SequenceStep $step): JsonResponse
    {
        $step->delete();

        return response()->json(null, 204);
    }

    public function enroll(Request $request, EmailSequence $sequence): JsonResponse
    {
        $data = $request->validate([
            'contact_id' => ['required', 'integer', 'exists:crm_contacts,id'],
        ]);

        $enrollment = $this->service->enroll($sequence, (int) $data['contact_id']);

        return response()->json($enrollment, 201);
    }

    public function enrollments(EmailSequence $sequence): JsonResponse
    {
        return response()->json(
            $sequence->enrollments()->with('contact')->paginate(25)
        );
    }

    public function stats(EmailSequence $sequence): JsonResponse
    {
        return response()->json($this->service->getSequenceStats($sequence));
    }

    public function processDue(): JsonResponse
    {
        $count = $this->service->processDueEnrollments();

        return response()->json(['processed' => $count]);
    }

    // ── Legacy endpoints (crm/sequences) ─────────────────────────────────────

    /**
     * Legacy enroll — supports both contact_id and lead_id.
     */
    public function enrollLegacy(Request $request, EmailSequence $sequence): JsonResponse
    {
        $data = $request->validate([
            'contact_id' => ['nullable', 'integer'],
            'lead_id' => ['nullable', 'integer'],
        ]);

        if (isset($data['contact_id'])) {
            $subject = Contact::findOrFail($data['contact_id']);
        } else {
            $subject = Lead::findOrFail($data['lead_id']);
        }

        $enrollment = $this->legacyService->enroll($sequence, $subject);

        return response()->json($enrollment, 201);
    }

    public function unenroll(EmailSequenceEnrollment $enrollment): JsonResponse
    {
        $this->legacyService->unenroll($enrollment);

        return response()->json(['message' => 'Unenrolled successfully.']);
    }
}
