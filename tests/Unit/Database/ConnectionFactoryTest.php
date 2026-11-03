<?php

declare(strict_types=1);

namespace Doogle\Tests\Unit\Database;

use Doogle\Database\ConnectionFactory;
use PDO;
use PHPUnit\Framework\TestCase;

final class ConnectionFactoryTest extends TestCase
{
    public function testBuildsMysqlDsnFromEnvironment(): void
    {
        $factory = new ConnectionFactory([
            'DB_HOST' => 'db',
            'DB_PORT' => '3307',
            'DB_DATABASE' => 'doogle_test',
            'DB_CHARSET' => 'utf8mb4',
        ]);

        self::assertSame('mysql:dbname=doogle_test;host=db;port=3307;charset=utf8mb4', $factory->dsn());
    }

    public function testExplicitDsnOverridesMysqlParts(): void
    {
        $factory = new ConnectionFactory([
            'DB_DSN' => 'sqlite::memory:',
            'DB_HOST' => 'db',
            'DB_DATABASE' => 'doogle_test',
        ]);

        self::assertSame('sqlite::memory:', $factory->dsn());
    }

    public function testCreatesPdoWithoutExternalNetworkCalls(): void
    {
        $factory = new ConnectionFactory([
            'DB_DSN' => 'sqlite::memory:',
            'DB_USERNAME' => '',
            'DB_PASSWORD' => '',
            'DB_ERRMODE' => 'exception',
        ]);

        $pdo = $factory->create();

        self::assertInstanceOf(PDO::class, $pdo);
        self::assertSame(PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(PDO::ATTR_ERRMODE));
    }

    public function testLoadsEnvironmentFileFromProjectRoot(): void
    {
        $projectRoot = sys_get_temp_dir() . '/doogle-env-' . bin2hex(random_bytes(6));
        mkdir($projectRoot);

        try {
            file_put_contents(
                $projectRoot . '/.env',
                "DB_DSN=sqlite::memory:\nDB_USERNAME=\nDB_PASSWORD=\nDB_ERRMODE=exception\n"
            );

            $factory = ConnectionFactory::fromProjectRoot($projectRoot);
            $pdo = $factory->create();

            self::assertSame('sqlite::memory:', $factory->dsn());
            self::assertInstanceOf(PDO::class, $pdo);
        } finally {
            unset($_ENV['DB_DSN'], $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], $_ENV['DB_ERRMODE']);
            unset($_SERVER['DB_DSN'], $_SERVER['DB_USERNAME'], $_SERVER['DB_PASSWORD'], $_SERVER['DB_ERRMODE']);

            if (is_file($projectRoot . '/.env')) {
                unlink($projectRoot . '/.env');
            }

            if (is_dir($projectRoot)) {
                rmdir($projectRoot);
            }
        }
    }
}
