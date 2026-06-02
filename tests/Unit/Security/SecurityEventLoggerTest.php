<?php

declare(strict_types=1);

namespace Doogle\Tests\Unit\Security;

use Doogle\Security\SecurityEventLogger;
use PHPUnit\Framework\TestCase;

final class SecurityEventLoggerTest extends TestCase
{
    public function testWritesJsonEventToConfiguredLogFile(): void
    {
        $logFile = sys_get_temp_dir() . '/doogle-security-' . bin2hex(random_bytes(8)) . '/security.log';
        $logger = new SecurityEventLogger($logFile);

        $logger->log('auth.failed', [
            'username' => 'admin',
            'remote_addr' => '127.0.0.1',
        ]);

        $contents = file_get_contents($logFile);

        self::assertIsString($contents);

        $entry = json_decode(trim($contents), true);

        self::assertIsArray($entry);
        self::assertSame('auth.failed', $entry['event']);
        self::assertSame('admin', $entry['context']['username']);
        self::assertSame('127.0.0.1', $entry['context']['remote_addr']);
    }
}
