<?php

declare(strict_types=1);

namespace WishWall;

use DateTimeImmutable;

final class Util
{
    public static function nowIso(): string
    {
        return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    }

    public static function tomorrowIso(): string
    {
        return (new DateTimeImmutable('tomorrow 10:00'))->format('Y-m-d H:i:s');
    }

    public static function clean(string $s, int $max = 500): string
    {
        $s = trim($s);
        $s = preg_replace('/\s+/', ' ', $s);
        if (mb_strlen($s) > $max) {
            $s = mb_substr($s, 0, $max);
        }
        return $s;
    }
}

