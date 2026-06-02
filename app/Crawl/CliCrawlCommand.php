<?php

declare(strict_types=1);

namespace Doogle\Crawl;

use Closure;
use Doogle\Repository\CrawlJobRepository;
use Doogle\Security\CrawlerSecurityPolicy;
use Doogle\Security\SecurityEventLogger;
use Throwable;

final class CliCrawlCommand
{
    public const EXIT_SUCCESS = 0;
    public const EXIT_FAILURE = 1;
    public const EXIT_REJECTED = 2;
    public const EXIT_CONFIGURATION_ERROR = 3;

    /** @var Closure(): CrawlService */
    private Closure $crawlServiceFactory;

    /** @var (Closure(): CrawlJobRepository)|null */
    private ?Closure $crawlJobRepositoryFactory;

    private UrlValidator $validator;

    /**
     * @param callable(): CrawlService $crawlServiceFactory
     * @param (callable(): CrawlJobRepository)|null $crawlJobRepositoryFactory
     */
    public function __construct(
        private readonly CrawlerSecurityPolicy $policy,
        callable $crawlServiceFactory,
        ?UrlValidator $validator = null,
        ?callable $crawlJobRepositoryFactory = null,
        private readonly ?SecurityEventLogger $logger = null,
    ) {
        $this->crawlServiceFactory = Closure::fromCallable($crawlServiceFactory);
        $this->crawlJobRepositoryFactory = $crawlJobRepositoryFactory !== null
            ? Closure::fromCallable($crawlJobRepositoryFactory)
            : null;
        $this->validator = $validator ?? new UrlValidator($policy);
    }

    /**
     * @param array<int, string> $argv
     */
    public function run(array $argv, ?callable $stdout = null, ?callable $stderr = null): int
    {
        $stdout ??= static function (string $message): void {
            fwrite(STDOUT, $message);
        };
        $stderr ??= static function (string $message): void {
            fwrite(STDERR, $message);
        };

        $url = trim((string) ($argv[1] ?? ''));

        if ($url === '' || $url === '--help' || $url === '-h') {
            $this->writeLine($stderr, 'Usage: php bin/crawl https://example.com');

            return self::EXIT_FAILURE;
        }

        $jobRepository = null;
        $jobId = null;

        if ($this->crawlJobRepositoryFactory !== null) {
            try {
                $jobRepository = ($this->crawlJobRepositoryFactory)();
                $jobId = $jobRepository->create($url, null);
                $jobRepository->markRunning($jobId);
            } catch (Throwable $throwable) {
                $this->writeLine($stderr, 'Crawl history unavailable. Run the crawl_jobs migration.');
                $this->log('cli_crawl.history_unavailable', ['url' => $url]);
                $jobRepository = null;
                $jobId = null;
            }
        }

        $reason = $this->validator->rejectionReason($url);

        if ($reason !== null) {
            $this->recordJobResult($jobRepository, $jobId, CrawlResult::rejected($reason), $stderr);
            $this->writeLine($stderr, 'Crawl rejected: ' . $reason);
            $this->log('cli_crawl.rejected', [
                'url' => $url,
                'reason' => $reason,
            ]);

            return self::EXIT_REJECTED;
        }

        try {
            $this->log('cli_crawl.requested', ['url' => $url]);
            $result = ($this->crawlServiceFactory)()->crawl(CrawlRequest::fromPolicy($url, $this->policy));
        } catch (Throwable $throwable) {
            $this->recordJobResult(
                $jobRepository,
                $jobId,
                CrawlResult::failed('Database/configuration failure: ' . $throwable->getMessage()),
                $stderr
            );
            $this->writeLine($stderr, 'Database/configuration failure: ' . $throwable->getMessage());
            $this->log('cli_crawl.configuration_failed', ['url' => $url]);

            return self::EXIT_CONFIGURATION_ERROR;
        }

        $this->recordJobResult($jobRepository, $jobId, $result, $stderr);

        if (!$result->successful) {
            $this->log('cli_crawl.failed', $this->resultContext($url, $result));

            return $this->handleFailedResult($result, $stderr);
        }

        $this->log('cli_crawl.completed', $this->resultContext($url, $result));
        $this->writeLine($stdout, 'Crawl completed.');
        $this->writeLine($stdout, 'Pages discovered: ' . $result->pagesDiscovered);
        $this->writeLine($stdout, 'Pages indexed: ' . $result->pagesIndexed);
        $this->writeLine($stdout, 'Images indexed: ' . $result->imagesIndexed);
        $this->writeLine($stdout, 'Videos indexed: ' . $result->videosIndexed);
        $this->writeLine($stdout, 'URLs rejected: ' . $result->urlsRejected);

        $output = self::plainTextOutput($result->output);

        if ($output !== '') {
            $this->writeLine($stdout);
            $this->writeLine($stdout, $output);
        }

        return self::EXIT_SUCCESS;
    }

    private function recordJobResult(
        ?CrawlJobRepository $repository,
        ?int $jobId,
        CrawlResult $result,
        callable $stderr,
    ): void {
        if ($repository === null || $jobId === null) {
            return;
        }

        try {
            $repository->markFromResult($jobId, $result);
        } catch (Throwable $throwable) {
            $this->writeLine($stderr, 'Crawl history could not be updated.');
            $this->log('cli_crawl.history_update_failed', ['job_id' => $jobId]);
        }
    }

    private function handleFailedResult(CrawlResult $result, callable $stderr): int
    {
        if ($result->urlsRejected > 0) {
            foreach ($result->errors ?: ['URL rejected by crawler policy.'] as $error) {
                $this->writeLine($stderr, 'Crawl rejected: ' . $error);
            }

            return self::EXIT_REJECTED;
        }

        $this->writeLine($stderr, 'Crawl failed.');

        foreach ($result->errors as $error) {
            $this->writeLine($stderr, $error);
        }

        return self::EXIT_FAILURE;
    }

    private function writeLine(callable $writer, string $line = ''): void
    {
        $writer($line . PHP_EOL);
    }

    /**
     * @param array<string, scalar|null> $context
     */
    private function log(string $event, array $context = []): void
    {
        $this->logger?->log($event, $context);
    }

    /**
     * @return array<string, scalar|null>
     */
    private function resultContext(string $url, CrawlResult $result): array
    {
        return [
            'url' => $url,
            'pages_indexed' => $result->pagesIndexed,
            'images_indexed' => $result->imagesIndexed,
            'videos_indexed' => $result->videosIndexed,
            'urls_rejected' => $result->urlsRejected,
        ];
    }

    private static function plainTextOutput(string $output): string
    {
        $withLineBreaks = preg_replace('/<br\s*\/?>/i', PHP_EOL, $output) ?? $output;

        return trim(html_entity_decode(strip_tags($withLineBreaks), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }
}
