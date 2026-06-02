<?php

declare(strict_types=1);

namespace Doogle\Tests\Unit\Crawl;

use Doogle\Crawl\CliCrawlCommand;
use Doogle\Crawl\CrawlRequest;
use Doogle\Crawl\CrawlService;
use Doogle\Crawl\UrlValidator;
use Doogle\Repository\CrawlJobRepository;
use Doogle\Security\CrawlerSecurityPolicy;
use Doogle\Security\PrivateNetworkBlocker;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CliCrawlCommandTest extends TestCase
{
    public function testMissingUrlReturnsUsageErrorWithoutCreatingService(): void
    {
        $created = false;
        $command = new CliCrawlCommand(
            new CrawlerSecurityPolicy(),
            function () use (&$created): CrawlService {
                $created = true;
                throw new RuntimeException('service should not be created');
            }
        );

        [$exitCode, $stdout, $stderr] = $this->runCommand($command, ['bin/crawl']);

        self::assertSame(CliCrawlCommand::EXIT_FAILURE, $exitCode);
        self::assertFalse($created);
        self::assertSame('', $stdout);
        self::assertStringContainsString('Usage:', $stderr);
    }

    public function testRejectedUrlReturnsRejectedExitCodeWithoutCreatingService(): void
    {
        $created = false;
        $policy = new CrawlerSecurityPolicy();
        $command = new CliCrawlCommand(
            $policy,
            function () use (&$created): CrawlService {
                $created = true;
                throw new RuntimeException('service should not be created');
            },
            $this->validator($policy)
        );

        [$exitCode, $stdout, $stderr] = $this->runCommand($command, ['bin/crawl', 'http://127.0.0.1']);

        self::assertSame(CliCrawlCommand::EXIT_REJECTED, $exitCode);
        self::assertFalse($created);
        self::assertSame('', $stdout);
        self::assertStringContainsString('private or reserved network host', $stderr);
    }

    public function testSuccessfulCrawlPrintsSummaryAndPlainTextCrawlerOutput(): void
    {
        $policy = new CrawlerSecurityPolicy(allowPrivateNetworks: true);
        $command = $this->commandWithRunner(
            $policy,
            static fn (CrawlRequest $request): string => 'SUCCESS<br><b>URL:</b> ' . $request->startUrl . '<br>'
        );

        [$exitCode, $stdout, $stderr] = $this->runCommand($command, ['bin/crawl', 'https://example.com/']);

        self::assertSame(CliCrawlCommand::EXIT_SUCCESS, $exitCode);
        self::assertSame('', $stderr);
        self::assertStringContainsString('Crawl completed.', $stdout);
        self::assertStringContainsString('Pages indexed: 1', $stdout);
        self::assertStringContainsString('URL: https://example.com/', $stdout);
    }

    public function testSuccessfulCliCrawlRecordsCompletedCrawlJob(): void
    {
        $policy = new CrawlerSecurityPolicy(allowPrivateNetworks: true);
        $pdo = $this->createCrawlJobPdo();
        $command = $this->commandWithRunner(
            $policy,
            static fn (CrawlRequest $request): string => 'SUCCESS<br><b>URL:</b> ' . $request->startUrl . '<br>',
            new CrawlJobRepository($pdo)
        );

        [$exitCode] = $this->runCommand($command, ['bin/crawl', 'https://example.com/']);
        $jobs = (new CrawlJobRepository($pdo))->recent();

        self::assertSame(CliCrawlCommand::EXIT_SUCCESS, $exitCode);
        self::assertCount(1, $jobs);
        self::assertSame('https://example.com/', $jobs[0]->startUrl);
        self::assertNull($jobs[0]->requestedByUserId);
        self::assertSame('completed', $jobs[0]->status);
        self::assertSame(1, $jobs[0]->pagesIndexed);
    }

    public function testRejectedCliCrawlRecordsRejectedCrawlJob(): void
    {
        $created = false;
        $policy = new CrawlerSecurityPolicy();
        $pdo = $this->createCrawlJobPdo();
        $command = new CliCrawlCommand(
            $policy,
            function () use (&$created): CrawlService {
                $created = true;
                throw new RuntimeException('service should not be created');
            },
            $this->validator($policy),
            static fn (): CrawlJobRepository => new CrawlJobRepository($pdo)
        );

        [$exitCode] = $this->runCommand($command, ['bin/crawl', 'http://127.0.0.1']);
        $jobs = (new CrawlJobRepository($pdo))->recent();

        self::assertSame(CliCrawlCommand::EXIT_REJECTED, $exitCode);
        self::assertFalse($created);
        self::assertCount(1, $jobs);
        self::assertSame('http://127.0.0.1', $jobs[0]->startUrl);
        self::assertNull($jobs[0]->requestedByUserId);
        self::assertSame('rejected', $jobs[0]->status);
        self::assertSame('private or reserved network host', $jobs[0]->errorMessage);
    }

    public function testCrawlerFailureReturnsFailureExitCode(): void
    {
        $policy = new CrawlerSecurityPolicy(allowPrivateNetworks: true);
        $command = $this->commandWithRunner(
            $policy,
            static function (): string {
                throw new RuntimeException('runner failed');
            }
        );

        [$exitCode, $stdout, $stderr] = $this->runCommand($command, ['bin/crawl', 'https://example.com/']);

        self::assertSame(CliCrawlCommand::EXIT_FAILURE, $exitCode);
        self::assertSame('', $stdout);
        self::assertStringContainsString('Crawl failed.', $stderr);
        self::assertStringContainsString('runner failed', $stderr);
    }

    public function testServiceFactoryFailureReturnsConfigurationError(): void
    {
        $policy = new CrawlerSecurityPolicy(allowPrivateNetworks: true);
        $command = new CliCrawlCommand(
            $policy,
            static fn (): CrawlService => throw new RuntimeException('database unavailable'),
            $this->validator($policy)
        );

        [$exitCode, $stdout, $stderr] = $this->runCommand($command, ['bin/crawl', 'https://example.com/']);

        self::assertSame(CliCrawlCommand::EXIT_CONFIGURATION_ERROR, $exitCode);
        self::assertSame('', $stdout);
        self::assertStringContainsString('Database/configuration failure: database unavailable', $stderr);
    }

    /**
     * @param callable(CrawlRequest): string $runner
     */
    private function commandWithRunner(
        CrawlerSecurityPolicy $policy,
        callable $runner,
        ?CrawlJobRepository $crawlJobRepository = null,
    ): CliCrawlCommand {
        $service = new CrawlService(
            new PDO('sqlite::memory:'),
            $policy,
            $this->validator($policy),
            $runner
        );

        return new CliCrawlCommand(
            $policy,
            static fn (): CrawlService => $service,
            $this->validator($policy),
            $crawlJobRepository !== null
                ? static fn (): CrawlJobRepository => $crawlJobRepository
                : null
        );
    }

    /**
     * @param array<int, string> $argv
     * @return array{0: int, 1: string, 2: string}
     */
    private function runCommand(CliCrawlCommand $command, array $argv): array
    {
        $stdout = '';
        $stderr = '';

        $exitCode = $command->run(
            $argv,
            static function (string $message) use (&$stdout): void {
                $stdout .= $message;
            },
            static function (string $message) use (&$stderr): void {
                $stderr .= $message;
            }
        );

        return [$exitCode, $stdout, $stderr];
    }

    private function validator(CrawlerSecurityPolicy $policy): UrlValidator
    {
        return new UrlValidator(
            $policy,
            new PrivateNetworkBlocker(static fn (string $host): array => [])
        );
    }

    private function createCrawlJobPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE crawl_jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                start_url VARCHAR(512) NOT NULL,
                requested_by_user_id INTEGER NULL,
                status VARCHAR(20) NOT NULL DEFAULT "pending",
                pages_discovered INTEGER NOT NULL DEFAULT 0,
                pages_indexed INTEGER NOT NULL DEFAULT 0,
                images_indexed INTEGER NOT NULL DEFAULT 0,
                urls_rejected INTEGER NOT NULL DEFAULT 0,
                error_message TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )'
        );

        return $pdo;
    }
}
