<?php

declare(strict_types=1);

namespace Doogle\Security;

final readonly class SecurityEventLogger
{
    public function __construct(private string $logFile = '')
    {
    }

    public static function fromEnvironment(): self
    {
        return new self((string) (getenv('DOOGLE_SECURITY_LOG') ?: ''));
    }

    /**
     * @param array<string, scalar|null> $context
     */
    public function log(string $event, array $context = []): void
    {
        $entry = [
            'time' => gmdate('c'),
            'event' => $event,
            'context' => $context,
        ];
        $line = (string) json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL;

        if ($this->logFile === '') {
            error_log($line);
            return;
        }

        $directory = dirname($this->logFile);

        if (!is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
