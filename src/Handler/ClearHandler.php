<?php

declare(strict_types=1);

namespace WishWall\Handler;

use WishWall\DB;

final class ClearHandler
{
    public static function handle(): void
    {
        try {
            $pdo = DB::pdo();
            
            // Получаем количество записей перед удалением
            $stmt = $pdo->query('SELECT COUNT(*) as count FROM wishes');
            $count = $stmt->fetch()['count'];
            
            if ($count > 0) {
                // Удаляем все записи из таблицы wishes
                $pdo->exec('DELETE FROM wishes');
                
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'ok' => true, 
                    'message' => "Удалено {$count} записей из базы данных",
                    'deleted_count' => $count
                ]);
            } else {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'ok' => true, 
                    'message' => 'База данных уже пуста',
                    'deleted_count' => 0
                ]);
            }
            
        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false, 
                'error' => 'Ошибка при очистке базы данных: ' . $e->getMessage()
            ]);
        }
    }
}
