<?php

declare(strict_types=1);

use WishWall\DB;

require __DIR__ . '/vendor/autoload.php';

DB::init(__DIR__ . '/data/wishes.sqlite');

