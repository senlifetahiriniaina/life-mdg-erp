<?php

declare(strict_types=1);

namespace Modules\BI\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\BI\Models\BiAlert;
use Modules\BI\Models\BiAlertEvent;

class BiAlertNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly BiAlert $alert,
        private readonly BiAlertEvent $event,
    ) {}

    /** @return list<string> */
    public function via(mixed $notifiable): array
    {
        $channels = [];

        if (in_array('email', $this->alert->channels, true)) {
            $channels[] = 'mail';
        }

        if (in_array('in_app', $this->alert->channels, true)) {
            $channels[] = 'database';
        }

        return $channels === [] ? ['mail'] : $channels;
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[BI Alert] '.$this->alert->name)
            ->line("Alert: **{$this->alert->name}**")
            ->line("Metric: {$this->alert->metric_name}")
            ->line("Current value: {$this->event->value}")
            ->line("Threshold: {$this->alert->condition_type} {$this->alert->threshold}")
            ->line("Triggered at: {$this->event->triggered_at?->toDateTimeString()}");
    }

    /** @return array<string, mixed> */
    public function toArray(mixed $notifiable): array
    {
        return [
            'alert_id' => $this->alert->id,
            'alert_name' => $this->alert->name,
            'event_id' => $this->event->id,
            'value' => $this->event->value,
            'threshold' => $this->alert->threshold,
            'condition' => $this->alert->condition_type,
            'metric' => $this->alert->metric_name,
            'triggered' => $this->event->triggered_at?->toIso8601String(),
        ];
    }
}
