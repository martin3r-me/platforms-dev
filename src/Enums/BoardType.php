<?php

namespace Platform\Dev\Enums;

enum BoardType: string
{
    case Feature = 'feature';
    case Bug = 'bug';
    case Custom = 'custom';
    case Inbox = 'inbox';

    public function label(): string
    {
        return match ($this) {
            self::Feature => 'Features',
            self::Bug => 'Bugs',
            self::Custom => 'Custom',
            self::Inbox => 'Inbox',
        };
    }
}
