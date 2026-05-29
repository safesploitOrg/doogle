<?php

declare(strict_types=1);

namespace Doogle\Crawl;

use Closure;
use Doogle\Security\CrawlerSecurityPolicy;
use Throwable;

final class CliCrawlCommand
{
    public const EXIT_SUCCESS = 0;
    public const EXIT_FAILURE = 1;
    public const EXIT_REJECTED = 2;
    public const EXIT_CONFIGURATION_ERROR = 3;

    /** @var Closure(): CrawlService */
    private Closure $crawlServiceFactory;
    private UrlValidator $validator;

    /**
     * @param callable(): CrawlService $crawlServiceFactory
     */
    public function __construct(
        private readonly CrawlerSecurityPolicy $policy,
        callable $crawlServiceFactory,
        ?UrlValidator $validator = null,
    ) {
        $this->crawlServiceFactory = Closure::fromCallable($crawlServiceFactory);
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

        $reason = $this->validator->rejectionReason($url);

        if ($reason !== null) {
            $this->writeLine($stderr, 'Crawl rejected: ' . $reason);

            return self::EXIT_REJECTED;
        }

        try {
            $result = ($this->crawlServiceFactory)()->crawl(CrawlRequest::fromPolicy($url, $this->policy));
        } catch (Throwable $throwable) {
            $this->writeLine($stderr, 'Database/configuration failure: ' . $throwable->getMessage());

            return self::EXIT_CONFIGURATION_ERROR;
        }

        if (!$result->successful) {
            return $this->handleFailedResult($result, $stderr);
        }

        $this->writeLine($stdout, 'Crawl completed.');
        $this->writeLine($stdout, 'Pages discovered: ' . $result->pagesDiscovered);
        $this->writeLine($stdout, 'Pages indexed: ' . $result->pagesIndexed);
        $this->writeLine($stdout, 'Images indexed: ' . $result->imagesIndexed);
        $this->writeLine($stdout, 'URLs rejected: ' . $result->urlsRejected);

        $output = self::plainTextOutput($result->output);

        if ($output !== '') {
            $this->writeLine($stdout);
            $this->writeLine($stdout, $output);
        }

        return self::EXIT_SUCCESS;
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

    private static function plainTextOutput(string $output): string
    {
        $withLineBreaks = preg_replace('/<br\s*\/?>/i', PHP_EOL, $output) ?? $output;

        return trim(html_entity_decode(strip_tags($withLineBreaks), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }
}
