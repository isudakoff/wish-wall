<?php

declare(strict_types=1);

use WishWall\Router;
use WishWall\Handler\{WishHandler, WishesHandler, ExportHandler, SeedHandler};
use WishWall\View\WallView;

if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $file = __DIR__ . $path;
    if ($path !== '/' && is_file($file)) {
        return false;
    }
}

require __DIR__ . '/bootstrap.php';

$router = new Router();
$router->add('POST', '/api/wish', [WishHandler::class, 'handle']);
$router->add('GET', '/api/wishes', [WishesHandler::class, 'handle']);
$router->add('GET', '/export.csv', [ExportHandler::class, 'handle']);
$router->add('GET', '/seed', [SeedHandler::class, 'handle']);
$router->add('GET', '/', [WallView::class, 'render']);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$router->dispatch($method, $uri);

