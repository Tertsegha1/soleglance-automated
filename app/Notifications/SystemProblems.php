<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SystemProblems extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param list<string> $problems */
    public function __construct(public array $problems)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject('[SoleGlance] ' . count($this->problems) . ' system issue(s) need attention');
        foreach ($this->problems as $p) {
            $mail->line('• ' . $p);
        }

        return $mail->action('Open model operations', route('admin.models'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'system',
            'message' => count($this->problems) . ' system issue(s): ' . \Illuminate\Support\Str::limit(implode(' ', $this->problems), 200),
            'url' => route('admin.models'),
        ];
    }
}
