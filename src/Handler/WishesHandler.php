<?php

declare(strict_types=1);

namespace WishWall\Handler;

use WishWall\DB;
use WishWall\Util;

final class WishesHandler
{
    public static function handle(): void
    {
        $since = isset($_GET['since']) ? max(0, (int) $_GET['since']) : 0;
        $now = Util::nowIso();
        $pdo = DB::pdo();

        if ($since > 0) {
            $stmt = $pdo->prepare('SELECT id, name, text, created_at FROM wishes WHERE visible_at <= ? AND id > ? ORDER BY id ASC');
            $stmt->execute([$now, $since]);
        } else {
            $stmt = $pdo->prepare('SELECT id, name, text, created_at FROM wishes WHERE visible_at <= ? ORDER BY id ASC');
            $stmt->execute([$now]);
        }

        $rows = $stmt->fetchAll();

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'items' => $rows]);
    }
}

