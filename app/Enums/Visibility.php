<?php

namespace App\Enums;

enum Visibility: string
{
    case Public = 'public';
    case Friends = 'friends';
    case OnlyMe = 'only_me';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Friends => 'Friends',
            self::OnlyMe => 'Only me',
            self::Custom => 'Specific friends',
        };
    }
}
