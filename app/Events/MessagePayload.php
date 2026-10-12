<?php

namespace App\Events;

use App\Models\Message;

final class MessagePayload
{
    public static function make(Message $message): array
    {
        $message->loadMissing('user');

        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'body' => $message->body,
            'user' => [
                'id' => $message->user->id,
                'name' => $message->user->name,
                'avatar' => $message->user->avatarUrl(),
            ],
            'created_at' => $message->created_at->toIso8601String(),
        ];
    }
}
