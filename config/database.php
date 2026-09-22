<?php
/**
 * OEMS — PDO Database Connection (singleton)
 */
declare(strict_types=1);

require_once __DIR__ . '/app.php';

final class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_CHARSET
            );

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                ]);
            } catch (PDOException $e) {
                if (APP_DEBUG) {
                    throw $e;
                }
                http_response_code(500);
                exit('Database connection failed. Please contact the administrator.');
            }
        }

        return self::$instance;
    }

    private function __construct() {}
    private function __clone() {}
}

function db(): PDO
{
    return Database::getInstance();
}
