<?php

declare(strict_types=1);

namespace WishWall\Handler;

use WishWall\DB;
use WishWall\Util;

final class WishHandler
{
    public static function handle(): void
    {
        $input = file_get_contents('php://input') ?: '';
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_starts_with($ct, 'application/json')) {
            $data = json_decode($input, true) ?: [];
        } else {
            $data = $_POST ?: [];
            if (!$data && $input !== '') {
                parse_str($input, $data);
            }
        }

        $name = Util::clean(strval($data['name'] ?? ''));
        $text = Util::clean(strval($data['text'] ?? ''), 1000);
        $surprise = (int)($data['surprise'] ?? 0) === 1;

        if ($name === '' || $text === '') {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Name and text are required']);
            return;
        }

        $visibleAt = $surprise ? Util::tomorrowIso() : Util::nowIso();
        $meta = $surprise ? json_encode(['surprise' => true]) : null;

        $pdo = DB::pdo();
        $stmt = $pdo->prepare('INSERT INTO wishes(name, text, created_at, visible_at, meta) VALUES(?,?,?,?,?)');
        $stmt->execute([$name, $text, Util::nowIso(), $visibleAt, $meta]);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
    }
}

