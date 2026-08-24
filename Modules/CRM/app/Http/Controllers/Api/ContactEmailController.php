<?php

namespace Modules\CRM\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CRM\Models\Contact;
use Modules\CRM\Services\ContactEmailService;

/**
 * @group CRM - Contact Communications
 */
class ContactEmailController extends Controller
{
    public function __construct(private readonly ContactEmailService $emailService) {}

    /**
     * Send welcome email to contact
     *
     * Sends a welcome email to the contact using the configured email template.
     * Requires contact to have an email address.
     *
     * @urlParam contact int required The contact ID. Example: 1
     *
     * @response 200 scenario="Success" {"success": true, "message": "Welcome email sent"}
     * @response 400 scenario="No email" {"success": false, "message": "Contact has no email address"}
     */
    public function sendWelcome(Contact $contact): JsonResponse
    {
        $this->authorize('update', $contact);

        if (! $contact->email) {
            return response()->json([
                'success' => false,
                'message' => 'Contact has no email address',
            ], 400);
        }

        $sent = $this->emailService->sendWelcomeEmail($contact);

        return response()->json([
            'success' => $sent,
            'message' => $sent ? 'Welcome email sent' : 'Failed to send email',
        ], $sent ? 200 : 500);
    }

    /**
     * Send custom email to contact
     *
     * Sends a custom email to the contact using a specified template.
     *
     * @urlParam contact int required The contact ID. Example: 1
     *
     * @bodyParam template_code string required Template code (e.g., contact.update_notification). Example: contact.update_notification
     * @bodyParam subject string Optional subject line override.
     * @bodyParam body string Optional body override.
     *
     * @response 200 scenario="Success" {"success": true, "message": "Email sent"}
     */
    public function sendCustom(Request $request, Contact $contact): JsonResponse
    {
        $this->authorize('update', $contact);

        $data = $request->validate([
            'template_code' => ['required', 'string'],
            'subject' => ['sometimes', 'string'],
            'body' => ['sometimes', 'string'],
        ]);

        if (! $contact->email) {
            return response()->json([
                'success' => false,
                'message' => 'Contact has no email address',
            ], 400);
        }

        $sent = $this->emailService->sendNotificationEmail(
            $contact,
            $data['template_code'],
            [
                'subject' => $data['subject'] ?? null,
                'body' => $data['body'] ?? null,
            ]
        );

        return response()->json([
            'success' => $sent,
            'message' => $sent ? 'Email sent' : 'Failed to send email',
        ], $sent ? 200 : 500);
    }

    /**
     * Send bulk email to contacts
     *
     * Sends the same email to multiple contacts based on a template.
     *
     * @bodyParam contact_ids array required Array of contact IDs. Example: [1, 2, 3]
     * @bodyParam template_code string required Template code. Example: contact.campaign_announcement
     *
     * @response 200 scenario="Success" {"success": true, "sent": 3, "message": "Bulk email sent to 3 contacts"}
     */
    public function sendBulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contact_ids' => ['required', 'array'],
            'contact_ids.*' => ['integer'],
            'template_code' => ['required', 'string'],
        ]);

        // Chantier 32.15: contact_ids was never scoped to the caller's own company — a user
        // could pass another company's contact ids and have real emails sent to them. Filtered
        // down to only the ids that are genuinely this caller's own contacts.
        $ownContactIds = Contact::whereIn('id', $data['contact_ids'])
            ->where('company_id', $request->user()->company_id)
            ->pluck('id')
            ->all();

        $sent = $this->emailService->sendBulkEmails(
            $ownContactIds,
            $data['template_code']
        );

        return response()->json([
            'success' => $sent > 0,
            'sent' => $sent,
            'message' => "Bulk email sent to {$sent} contacts",
        ]);
    }
}
