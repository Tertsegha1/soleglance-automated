<?php

namespace App\Notifications;

use App\Models\HealthAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class HealthAlertRaised extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public HealthAlert $alert)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $a = $this->alert;

        return (new MailMessage)
            ->subject('[SoleGlance] ' . $a->title())
            ->line("Public mentions of {$a->title()} rose to {$a->count} on {$a->window_end->toFormattedDateString()} (EARS {$a->method} statistic " . round($a->statistic, 1) . ').')
            ->line('This is an automated signal from public posts and news. It needs verification and is not a confirmed outbreak.')
            ->action('Review the signal', route('health.index', ['disease' => $a->disease, 'country' => $a->country]));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'health_alert',
            'message' => 'Health signal: ' . $this->alert->title() . " ({$this->alert->count} mentions)",
            'url' => route('health.index', ['disease' => $this->alert->disease, 'country' => $this->alert->country]),
        ];
    }
}
