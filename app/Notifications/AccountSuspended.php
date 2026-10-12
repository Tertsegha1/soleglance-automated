<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountSuspended extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ?string $reason)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your SoleGlance account has been suspended')
            ->line('An administrator has suspended your SoleGlance account.')
            ->when($this->reason, fn ($m) => $m->line("Reason: {$this->reason}"))
            ->line('If you believe this is a mistake, reply to this email.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'suspended',
            'message' => 'Your account has been suspended.' . ($this->reason ? " Reason: {$this->reason}" : ''),
            'url' => null,
        ];
    }
}
