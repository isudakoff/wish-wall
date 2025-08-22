<?php

declare(strict_types=1);

namespace WishWall\Handler;

use WishWall\DB;
use WishWall\Util;

final class SeedHandler
{
    public static function handle(): void
    {
        $pdo = DB::pdo();
        $samples = [
            ['Иван', 'Любите и танцуйте до утра!'],
            ['Мария', 'Пусть каждый день будет как сегодня — с огоньком!'],
            ['Дима', 'Путешествий, смеха и терпения друг к другу!'],
            ['Аня', 'Дом — там, где вы вместе. Счастья!'],
            ['Лена', 'Миллион поцелуев и ноль ссор!'],
        ];
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO wishes(name, text, created_at, visible_at, meta) VALUES(?,?,?,?,NULL)');
        $now = Util::nowIso();
        foreach ($samples as [$n, $t]) {
            $stmt->execute([$n, $t, $now, $now]);
        }
        $pdo->commit();
        header('Location: /');
    }
}

