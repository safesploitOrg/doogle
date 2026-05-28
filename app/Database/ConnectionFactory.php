<?php

declare(strict_types=1);

namespace Doogle\Database;

use Dotenv\Dotenv;
use PDO;

final class ConnectionFactory
{
    private const DEFAULTS = [
        'DB_HOST' => 'mysql_db',
        'DB_PORT' => '3306',
        'DB_DATABASE' => 'doogle',
        'DB_USERNAME' => 'doogle',
        'DB_PASSWORD' => 'PASSWORD_HERE',
        'DB_CHARSET' => 'utf8mb4',
        'DB_ERRMODE' => 'warning',
    ];

    /**
     * @param array<string, scalar|null> $environment
     * @param array<string, scalar|null> $defaults
     */
    public function __construct(
        private readonly array $environment = [],
        private readonly array $defaults = self::DEFAULTS,
    ) {
    }

    /**
     * @param array<string, scalar|null> $defaults
     */
    public static function fromProjectRoot(string $projectRoot, array $defaults = self::DEFAULTS): self
    {
        Dotenv::createImmutable($projectRoot)->safeLoad();

        return new self(self::readRuntimeEnvironment(), $defaults);
    }

    public function create(): PDO
    {
        return new PDO(
            $this->dsn(),
            $this->optionalString('DB_USERNAME'),
            $this->optionalString('DB_PASSWORD'),
            [
                PDO::ATTR_ERRMODE => $this->errorMode(),
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    public function dsn(): string
    {
        $explicitDsn = $this->optionalString('DB_DSN');

        if ($explicitDsn !== null) {
            return $explicitDsn;
        }

        return sprintf(
            'mysql:dbname=%s;host=%s;port=%s;charset=%s',
            $this->string('DB_DATABASE'),
            $this->string('DB_HOST'),
            $this->string('DB_PORT'),
            $this->string('DB_CHARSET')
        );
    }

    /**
     * @return array<string, scalar|null>
     */
    private static function readRuntimeEnvironment(): array
    {
        $environment = array_merge($_SERVER, $_ENV);

        foreach (array_keys(self::DEFAULTS) as $key) {
            $value = getenv($key);

            if ($value !== false) {
                $environment[$key] = $value;
            }
        }

        $dsn = getenv('DB_DSN');

        if ($dsn !== false) {
            $environment['DB_DSN'] = $dsn;
        }

        return $environment;
    }

    private function string(string $key): string
    {
        return (string) ($this->value($key) ?? '');
    }

    private function optionalString(string $key): ?string
    {
        $value = $this->value($key);

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function errorMode(): int
    {
        return match (strtolower($this->string('DB_ERRMODE'))) {
            'exception' => PDO::ERRMODE_EXCEPTION,
            'silent' => PDO::ERRMODE_SILENT,
            default => PDO::ERRMODE_WARNING,
        };
    }

    private function value(string $key): mixed
    {
        if (array_key_exists($key, $this->environment)) {
            return $this->environment[$key];
        }

        if (array_key_exists($key, $this->defaults)) {
            return $this->defaults[$key];
        }

        if (array_key_exists($key, self::DEFAULTS)) {
            return self::DEFAULTS[$key];
        }

        return null;
    }
}
