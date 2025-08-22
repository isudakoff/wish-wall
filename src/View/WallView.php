<?php

declare(strict_types=1);

namespace WishWall\View;

final class WallView
{
    public static function render(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        require __DIR__ . '/../../views/wall.php';
    }
}

