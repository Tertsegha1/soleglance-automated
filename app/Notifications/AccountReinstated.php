<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountReinstated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your SoleGlance account has been reinstated')
            ->line('Your SoleGlance account is active again.')
            ->action('Open SoleGlance', route('feed'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'reinstated',
            'message' => 'Your account has been reinstated.',
            'url' => route('feed'),
        ];
    }
}
