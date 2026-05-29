<?php

declare(strict_types=1);

namespace Doogle\Crawl;

use Closure;
use Doogle\Security\CrawlerSecurityPolicy;
use PDO;
use Throwable;

final class CrawlService
{
    /** @var Closure(CrawlRequest): string */
    private Closure $runner;

    public function __construct(
        private readonly PDO $pdo,
        private readonly CrawlerSecurityPolicy $policy,
        private readonly UrlValidator $validator,
        ?callable $runner = null,
    ) {
        $this->runner = Closure::fromCallable($runner ?? $this->runLegacyCrawler(...));
    }

    public static function fromDefaults(PDO $pdo): self
    {
        $policy = CrawlerSecurityPolicy::fromEnvironment();

        return new self(
            pdo: $pdo,
            policy: $policy,
            validator: new UrlValidator($policy),
        );
    }

    public function crawl(CrawlRequest $request): CrawlResult
    {
        $reason = $this->validator->rejectionReason($request->startUrl);

        if ($reason !== null) {
            return CrawlResult::rejected($reason);
        }

        try {
            $output = ($this->runner)($request);
        } catch (Throwable $throwable) {
            return CrawlResult::failed($throwable->getMessage());
        }

        return new CrawlResult(
            successful: true,
            pagesDiscovered: max(1, substr_count($output, '<br>')),
            pagesIndexed: substr_count($output, '<b>URL:</b>'),
            imagesIndexed: substr_count($output, '<b>src:</b>'),
            urlsRejected: substr_count($output, 'SKIPPED:'),
            output: $output,
        );
    }

    private function runLegacyCrawler(CrawlRequest $request): string
    {
        require_once dirname(__DIR__, 2) . '/classes/Crawler.php';

        ob_start();

        try {
            $crawler = new \Crawler($this->pdo, $this->policy, $this->validator);
            $crawler->followLinks($request->startUrl);

            return (string) ob_get_clean();
        } catch (Throwable $throwable) {
            $output = (string) ob_get_clean();

            throw new \RuntimeException($throwable->getMessage() . ($output !== '' ? "\n" . $output : ''));
        }
    }
}
