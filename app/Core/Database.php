<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Conexao PDO unica (Singleton) reutilizada por todos os Models.
 */
final class Database
{
    private static ?PDO $instancia = null;

    private function __construct() {}
    private function __clone() {}

    public static function getConnection(): PDO
    {
        if (self::$instancia === null) {
            $config = require dirname(__DIR__) . '/Config/config.php';
            $db     = $config['db'];

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $db['host'], $db['port'], $db['name'], $db['charset']
            );

            try {
                self::$instancia = new PDO($dsn, $db['user'], $db['pass'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                // Nao vaze credenciais/DSN na mensagem
                error_log('Falha na conexao com o banco: ' . $e->getMessage());
                throw new RuntimeException('Erro ao conectar ao banco de dados.');
            }
        }

        return self::$instancia;
    }
}
