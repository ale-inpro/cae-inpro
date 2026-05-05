<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $cfg = require dirname(__DIR__, 2) . '/config/database.php';

        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            $cfg['host'],
            (int) $cfg['port'],
            $cfg['database']
        );

        self::$pdo = new PDO($dsn, (string) $cfg['username'], (string) $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return self::$pdo;
    }
}