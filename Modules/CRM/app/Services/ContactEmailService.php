<?php

namespace Modules\CRM\Services;

use Illuminate\Support\Facades\Mail;
use Modules\CRM\Mail\ContactNotificationMail;
use Modules\CRM\Models\Contact;

class ContactEmailService
{
    /**
     * Built-in notification templates, keyed by code.
     * Life MDG runs without the Email module, so templates are static
     * instead of database-driven (Modules\Email\Models\EmailTemplate).
     *
     * @var array<string, array{subject: string, body: string}>
     */
    private const TEMPLATES = [
        'contact.welcome' => [
            'subject' => 'Bienvenue chez {account_name}',
            'body' => '<p>Bonjour {contact_name},</p><p>Bienvenue ! Nous sommes ravis de vous compter parmi nos contacts chez {account_name}.</p>',
        ],
    ];

    public function sendWelcomeEmail(Contact $contact): bool
    {
        if (! $contact->email) {
            return false;
        }

        try {
            $template = self::TEMPLATES['contact.welcome'];

            Mail::send(new ContactNotificationMail(
                subjectLine: $this->renderTemplate($template['subject'], [
                    'account_name' => $contact->account?->name,
                ]),
                bodyHtml: $this->renderTemplate($template['body'], [
                    'contact_name' => $contact->full_name,
                    'account_name' => $contact->account?->name,
                    'contact_title' => $contact->job_title,
                ]),
            ));

            return true;
        } catch (\Exception $e) {
            report($e);

            return false;
        }
    }

    public function sendNotificationEmail(Contact $contact, string $event, array $data = []): bool
    {
        if (! $contact->email) {
            return false;
        }

        $templateCode = "contact.{$event}";
        $template = self::TEMPLATES[$templateCode] ?? [
            'subject' => ucfirst($event),
            'body' => '<p>Bonjour {contact_name},</p><p>Mise à jour : '.ucfirst($event).'.</p>',
        ];

        try {
            Mail::send(new ContactNotificationMail(
                subjectLine: $this->renderTemplate($template['subject'], array_merge([
                    'contact_name' => $contact->full_name,
                ], $data)),
                bodyHtml: $this->renderTemplate($template['body'], array_merge([
                    'contact_name' => $contact->full_name,
                ], $data)),
            ));

            return true;
        } catch (\Exception $e) {
            report($e);

            return false;
        }
    }

    protected function renderTemplate(string $template, array $data): string
    {
        foreach ($data as $key => $value) {
            $template = str_replace("{{$key}}", $value ?? '', $template);
        }

        return $template;
    }

    public function sendBulkEmails(array $contactIds, string $templateCode, array $data = []): int
    {
        $sent = 0;

        Contact::whereIn('id', $contactIds)
            ->where('status', 'active')
            ->chunk(50, function ($contacts) use (&$sent, $templateCode, $data) {
                foreach ($contacts as $contact) {
                    if ($this->sendNotificationEmail($contact, $templateCode, $data)) {
                        $sent++;
                    }
                }
            });

        return $sent;
    }
}
