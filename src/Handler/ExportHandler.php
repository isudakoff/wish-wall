<?php

declare(strict_types=1);

namespace WishWall\Handler;

use WishWall\DB;

final class ExportHandler
{
    public static function handle(): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->query('SELECT id, name, text, created_at, visible_at, COALESCE(meta, "") as meta FROM wishes ORDER BY id ASC');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="wishes_export.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['id', 'name', 'text', 'created_at', 'visible_at', 'meta']);
        while ($row = $stmt->fetch()) {
            fputcsv($out, $row);
        }
        fclose($out);
    }
}

