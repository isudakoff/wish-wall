<?php

declare(strict_types=1);

namespace WishWall;

use PDO;

final class DB
{
    private static ?PDO $pdo = null;
    private static string $file;

    public static function init(string $file): void
    {
        self::$file = $file;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dir = dirname(self::$file);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        self::$pdo = new PDO('sqlite:' . self::$file, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        self::$pdo->exec('PRAGMA journal_mode=WAL;');
        self::$pdo->exec('PRAGMA busy_timeout=3000;');
        self::initDb();

        return self::$pdo;
    }

    private static function initDb(): void
    {
        $sql = <<<SQL
        CREATE TABLE IF NOT EXISTS wishes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            text TEXT NOT NULL,
            created_at TEXT NOT NULL,
            visible_at TEXT NOT NULL,
            meta TEXT
        );
        SQL;
        self::$pdo->exec($sql);
        self::$pdo->exec('CREATE INDEX IF NOT EXISTS idx_wishes_visible_id ON wishes(visible_at, id)');
    }
}

