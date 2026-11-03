<?php

namespace App\Enums;

/**
 * Fixed palette for ticket categories, in assignment order. Category badges always show the name too.
 */
enum CategoryColor: string
{
    case Blue = 'blue';
    case Amber = 'amber';
    case Emerald = 'emerald';
    case Violet = 'violet';
    case Rose = 'rose';
    case Cyan = 'cyan';
    case Orange = 'orange';
    case Slate = 'slate';

    /**
     * The next color in order for a workspace that already has $existingCount categories.
     */
    public static function forPosition(int $existingCount): self
    {
        $cases = self::cases();

        return $cases[$existingCount % count($cases)];
    }
}
