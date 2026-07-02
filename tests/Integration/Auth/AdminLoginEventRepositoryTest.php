<?php

declare(strict_types=1);

namespace Doogle\Tests\Integration\Auth;

use Doogle\Auth\AdminLoginEvent;
use Doogle\Auth\AdminLoginEventRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class AdminLoginEventRepositoryTest extends TestCase
{
    private PDO $pdo;
    private AdminLoginEventRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE admin_login_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER DEFAULT NULL,
                username VARCHAR(100) NOT NULL DEFAULT "",
                successful INTEGER NOT NULL DEFAULT 0,
                failure_reason VARCHAR(100) NOT NULL DEFAULT "",
                ip_address VARCHAR(45) NOT NULL DEFAULT "",
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )'
        );
        $this->repository = new AdminLoginEventRepository($this->pdo);
    }

    public function testRecordsAndReturnsRecentLoginEvents(): void
    {
        self::assertTrue($this->repository->record('admin', 1, true, '', '127.0.0.1'));
        self::assertTrue($this->repository->record('admin', null, false, 'password', '203.0.113.10'));

        $events = $this->repository->recent(10);

        self::assertCount(2, $events);
        self::assertContainsOnlyInstancesOf(AdminLoginEvent::class, $events);
        self::assertFalse($events[0]->successful);
        self::assertSame('password', $events[0]->failureReason);
        self::assertSame('203.0.113.10', $events[0]->ipAddress);
        self::assertTrue($events[1]->successful);
        self::assertSame(1, $events[1]->userId);
    }

    public function testRecordTruncatesBoundedFields(): void
    {
        self::assertTrue($this->repository->record(
            str_repeat('a', 120),
            1,
            false,
            str_repeat('b', 120),
            str_repeat('1', 60)
        ));

        $event = $this->repository->recent(1)[0];

        self::assertSame(100, strlen($event->username));
        self::assertSame(100, strlen($event->failureReason));
        self::assertSame(45, strlen($event->ipAddress));
    }
}
