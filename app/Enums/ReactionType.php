<?php

namespace App\Enums;

enum ReactionType: string
{
    case Like = 'like';
    case Love = 'love';
    case Haha = 'haha';
    case Wow = 'wow';
    case Sad = 'sad';
    case Angry = 'angry';

    public function emoji(): string
    {
        return match ($this) {
            self::Like => '👍',
            self::Love => '❤️',
            self::Haha => '😂',
            self::Wow => '😮',
            self::Sad => '😢',
            self::Angry => '😠',
        };
    }
}
