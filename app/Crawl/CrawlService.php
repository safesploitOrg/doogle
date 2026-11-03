<?php

declare(strict_types=1);

namespace Doogle\Crawl;

use Closure;
use DOMDocument;
use DOMElement;
use DOMNodeList;
use Doogle\Security\CrawlerSecurityPolicy;
use PDO;
use Throwable;

final class CrawlService
{
    /** @var Closure(CrawlRequest): string */
    private Closure $runner;

    /** @var Closure(string): string */
    private Closure $pageFetcher;

    /** @var array<string, true> */
    private array $alreadyCrawled = [];

    /** @var array<string, true> */
    private array $alreadyParsed = [];

    /** @var list<string> */
    private array $alreadyFoundImages = [];

    /** @var list<string> */
    private array $alreadyFoundVideos = [];

    /** @var list<string> */
    private array $output = [];

    private int $pagesCrawled = 0;

    public function __construct(
        private readonly PDO $pdo,
        private readonly CrawlerSecurityPolicy $policy,
        private readonly UrlValidator $validator,
        ?callable $runner = null,
        ?callable $pageFetcher = null,
    ) {
        $this->runner = Closure::fromCallable($runner ?? $this->runCrawler(...));
        $this->pageFetcher = Closure::fromCallable($pageFetcher ?? $this->fetchPage(...));
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
            videosIndexed: substr_count($output, '<b>video:</b>'),
        );
    }

    private function runCrawler(CrawlRequest $request): string
    {
        $this->alreadyCrawled = [];
        $this->alreadyParsed = [];
        $this->alreadyFoundImages = [];
        $this->alreadyFoundVideos = [];
        $this->output = [];
        $this->pagesCrawled = 0;

        $this->followLinks($request->startUrl);

        return implode('', $this->output);
    }

    private function followLinks(string $url): void
    {
        $queue = [[$url, 0]];

        while ($queue !== []) {
            if (!$this->policy->allowsMorePages($this->pagesCrawled)) {
                $this->record('SKIPPED: maximum pages per job reached');
                break;
            }

            $current = array_shift($queue);
            $currentUrl = (string) $current[0];
            $depth = (int) $current[1];

            if (isset($this->alreadyParsed[$currentUrl])) {
                continue;
            }

            $this->alreadyParsed[$currentUrl] = true;
            $reason = $this->validator->rejectionReason($currentUrl, $depth);

            if ($reason !== null) {
                $this->record("SKIPPED: {$currentUrl} ({$reason})");
                continue;
            }

            $links = $this->loadDocument($currentUrl)->getElementsByTagName('a');

            foreach ($links as $link) {
                if (!$link instanceof DOMElement) {
                    continue;
                }

                $href = $link->getAttribute('href');

                if ($this->shouldSkipHref($href)) {
                    continue;
                }

                $href = $this->createLink($href, $currentUrl);
                $nextDepth = $depth + 1;
                $reason = $this->validator->rejectionReason($href, $nextDepth);

                if ($reason !== null) {
                    $this->record("SKIPPED: {$href} ({$reason})");
                    continue;
                }

                if (!isset($this->alreadyCrawled[$href])) {
                    if (!$this->policy->allowsMorePages($this->pagesCrawled)) {
                        $this->record('SKIPPED: maximum pages per job reached');
                        break;
                    }

                    $this->alreadyCrawled[$href] = true;
                    $this->getDetails($href, $nextDepth);

                    if ($nextDepth < $this->policy->maxDepth() && !isset($this->alreadyParsed[$href])) {
                        $queue[] = [$href, $nextDepth];
                    }
                }

                $this->record($href);
            }
        }
    }

    private function getDetails(string $url, int $depth = 0): bool
    {
        $reason = $this->validator->rejectionReason($url, $depth);

        if ($reason !== null) {
            $this->record("SKIPPED: {$url} ({$reason})");
            return false;
        }

        if (!$this->policy->allowsMorePages($this->pagesCrawled)) {
            $this->record('SKIPPED: maximum pages per job reached');
            return false;
        }

        $this->pagesCrawled++;
        $document = $this->loadDocument($url);
        $title = $this->firstNodeValue($document->getElementsByTagName('title'));

        if ($title === '') {
            return false;
        }

        $description = '';
        $keywords = '';
        $metas = $document->getElementsByTagName('meta');

        foreach ($metas as $meta) {
            if (!$meta instanceof DOMElement) {
                continue;
            }

            if ($meta->getAttribute('name') === 'description') {
                $description = $meta->getAttribute('content');
            }

            if ($meta->getAttribute('name') === 'keywords') {
                $keywords = $meta->getAttribute('content');
            }
        }

        $description = str_replace("\n", '', $description);
        $keywords = str_replace("\n", '', $keywords);

        if ($this->linkExists($url)) {
            $this->record("{$url} already exists");
        } elseif ($this->insertLink($url, $title, $description, $keywords)) {
            $this->record("SUCCESS: {$url}");
        } else {
            $this->record("ERROR: Failed to insert {$url}");
        }

        $imageOutput = $this->indexImages($document, $url, $depth);
        $videoOutput = $this->indexVideos($document, $url, $depth, $title, $description);
        $this->record(
            "<b>URL:</b> {$url}, <b>Title:</b> {$title}, "
            . "<b>Description:</b> {$description}, <b>keywords:</b> {$keywords}"
        );

        if ($imageOutput !== '') {
            $this->record($imageOutput);
        }

        if ($videoOutput !== '') {
            $this->record($videoOutput);
        }

        return true;
    }

    private function indexImages(DOMDocument $document, string $url, int $depth): string
    {
        $imageOutput = '';
        $images = $document->getElementsByTagName('img');

        foreach ($images as $image) {
            if (!$image instanceof DOMElement) {
                continue;
            }

            $src = $image->getAttribute('src');
            $alt = $image->getAttribute('alt');
            $imageTitle = $image->getAttribute('title');

            if ($imageTitle === '' && $alt === '') {
                continue;
            }

            $src = $this->createLink($src, $url);
            $imageReason = $this->validator->rejectionReason($src, $depth);

            if ($imageReason !== null) {
                $this->record("SKIPPED: {$src} ({$imageReason})");
                continue;
            }

            if (!in_array($src, $this->alreadyFoundImages, true)) {
                $this->alreadyFoundImages[] = $src;

                if ($this->imageExists($src)) {
                    $this->record("{$src} already exists");
                } elseif ($this->insertImage($url, $src, $alt, $imageTitle)) {
                    $this->record("SUCCESS: {$src}");
                } else {
                    $this->record("ERROR: Failed to insert {$src}");
                }
            }

            $imageOutput = "<b>src:</b> <a href={$src}>{$src}</a>, "
                . "<b>alt:</b> {$alt}, <b>title:</b> {$imageTitle}, <b>url:</b> {$url}";
        }

        return $imageOutput;
    }

    private function indexVideos(
        DOMDocument $document,
        string $url,
        int $depth,
        string $pageTitle,
        string $pageDescription,
    ): string {
        $videoOutput = '';
        $thumbnailUrl = $this->normaliseOptionalUrl($this->firstMetaContent($document, [
            'og:image',
            'twitter:image',
        ]), $url, $depth);
        $metaTitle = $this->firstMetaContent($document, ['og:title', 'twitter:title']) ?: $pageTitle;
        $metaDescription = $this->firstMetaContent(
            $document,
            ['og:description', 'twitter:description']
        ) ?: $pageDescription;

        foreach ($this->videoCandidates($document, $url, $thumbnailUrl, $metaTitle, $metaDescription) as $candidate) {
            $videoUrl = $this->normaliseOptionalUrl($candidate['videoUrl'], $url, $depth);

            if ($videoUrl === '') {
                continue;
            }

            if (!in_array($videoUrl, $this->alreadyFoundVideos, true)) {
                $this->alreadyFoundVideos[] = $videoUrl;
                $title = $this->cleanText($candidate['title'] ?: $pageTitle);
                $description = $this->cleanText($candidate['description'] ?: $pageDescription);
                $source = $this->cleanText($candidate['source'] ?: $this->sourceFromUrl($videoUrl));

                if ($this->videoExists($videoUrl)) {
                    $this->record("{$videoUrl} already exists");
                } elseif (
                    $this->insertVideo($url, $videoUrl, $candidate['thumbnailUrl'], $title, $description, $source)
                ) {
                    $this->record("SUCCESS: {$videoUrl}");
                } else {
                    $this->record("ERROR: Failed to insert {$videoUrl}");
                }
            }

            $videoOutput = "<b>video:</b> <a href={$videoUrl}>{$videoUrl}</a>, "
                . "<b>title:</b> {$candidate['title']}, <b>url:</b> {$url}";
        }

        return $videoOutput;
    }

    /**
     * @return list<array{videoUrl: string, thumbnailUrl: string, title: string, description: string, source: string}>
     */
    private function videoCandidates(
        DOMDocument $document,
        string $pageUrl,
        string $thumbnailUrl,
        string $pageTitle,
        string $pageDescription,
    ): array {
        $candidates = [];

        foreach (['og:video', 'og:video:url', 'og:video:secure_url', 'twitter:player'] as $name) {
            $videoUrl = $this->firstMetaContent($document, [$name]);

            if ($videoUrl !== '') {
                $candidates[] = $this->videoCandidate($videoUrl, $thumbnailUrl, $pageTitle, $pageDescription, $name);
            }
        }

        foreach ($document->getElementsByTagName('video') as $video) {
            if (!$video instanceof DOMElement) {
                continue;
            }

            $title = $video->getAttribute('title') ?: $video->getAttribute('aria-label') ?: $pageTitle;
            $poster = $this->normaliseOptionalUrl($video->getAttribute('poster'), $pageUrl, 0) ?: $thumbnailUrl;

            if ($video->getAttribute('src') !== '') {
                $candidates[] = $this->videoCandidate(
                    $video->getAttribute('src'),
                    $poster,
                    $title,
                    $pageDescription,
                    'video'
                );
            }

            foreach ($video->getElementsByTagName('source') as $source) {
                if ($source instanceof DOMElement && $source->getAttribute('src') !== '') {
                    $candidates[] = $this->videoCandidate(
                        $source->getAttribute('src'),
                        $poster,
                        $title,
                        $pageDescription,
                        'video'
                    );
                }
            }
        }

        foreach ($document->getElementsByTagName('source') as $source) {
            if (!$source instanceof DOMElement || $source->getAttribute('src') === '') {
                continue;
            }

            if (str_starts_with(strtolower($source->getAttribute('type')), 'video/')) {
                $candidates[] = $this->videoCandidate(
                    $source->getAttribute('src'),
                    $thumbnailUrl,
                    $pageTitle,
                    $pageDescription,
                    'source'
                );
            }
        }

        foreach ($document->getElementsByTagName('iframe') as $iframe) {
            if (!$iframe instanceof DOMElement || $iframe->getAttribute('src') === '') {
                continue;
            }

            $src = $iframe->getAttribute('src');

            if ($this->isVideoLikeUrl($this->createLink($src, $pageUrl))) {
                $candidates[] = $this->videoCandidate(
                    $src,
                    $thumbnailUrl,
                    $iframe->getAttribute('title') ?: $pageTitle,
                    $pageDescription,
                    'iframe'
                );
            }
        }

        return $candidates;
    }

    /**
     * @return array{videoUrl: string, thumbnailUrl: string, title: string, description: string, source: string}
     */
    private function videoCandidate(
        string $videoUrl,
        string $thumbnailUrl,
        string $title,
        string $description,
        string $source,
    ): array {
        return [
            'videoUrl' => $videoUrl,
            'thumbnailUrl' => $thumbnailUrl,
            'title' => $this->cleanText($title),
            'description' => $this->cleanText($description),
            'source' => $this->cleanText($source),
        ];
    }

    private function loadDocument(string $url): DOMDocument
    {
        $document = new DOMDocument('1.0', 'utf-8');
        $html = '<?xml encoding="UTF-8">' . ($this->pageFetcher)($url);

        @$document->loadHTML($html);

        return $document;
    }

    private function fetchPage(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: ' . $this->policy->userAgent() . "\r\n",
                'timeout' => $this->policy->timeoutSeconds(),
                'ignore_errors' => true,
                'follow_location' => 0,
                'max_redirects' => 0,
            ],
        ]);

        $contents = @file_get_contents($url, false, $context, 0, $this->policy->maxResponseBytes());

        return is_string($contents) ? $contents : '';
    }

    private function linkExists(string $url): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM sites WHERE url = :url LIMIT 1');
        $statement->bindValue(':url', $url);
        $statement->execute();

        return $statement->fetchColumn() !== false;
    }

    private function imageExists(string $src): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM images WHERE imageUrl = :src LIMIT 1');
        $statement->bindValue(':src', $src);
        $statement->execute();

        return $statement->fetchColumn() !== false;
    }

    private function videoExists(string $videoUrl): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM videos WHERE videoUrl = :videoUrl LIMIT 1');
        $statement->bindValue(':videoUrl', $videoUrl);
        $statement->execute();

        return $statement->fetchColumn() !== false;
    }

    private function insertLink(string $url, string $title, string $description, string $keywords): bool
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO sites(url, title, description, keywords)
             VALUES(:url, :title, :description, :keywords)'
        );

        return $statement->execute([
            ':url' => $url,
            ':title' => $title,
            ':description' => $description,
            ':keywords' => $keywords,
        ]);
    }

    private function insertImage(string $url, string $src, string $alt, string $title): bool
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO images(siteUrl, imageUrl, alt, title)
             VALUES(:siteUrl, :imageUrl, :alt, :title)'
        );

        return $statement->execute([
            ':siteUrl' => $url,
            ':imageUrl' => $src,
            ':alt' => $alt,
            ':title' => $title,
        ]);
    }

    private function insertVideo(
        string $siteUrl,
        string $videoUrl,
        string $thumbnailUrl,
        string $title,
        string $description,
        string $source,
    ): bool {
        $statement = $this->pdo->prepare(
            'INSERT INTO videos(siteUrl, videoUrl, thumbnailUrl, title, description, source)
             VALUES(:siteUrl, :videoUrl, :thumbnailUrl, :title, :description, :source)'
        );

        return $statement->execute([
            ':siteUrl' => $siteUrl,
            ':videoUrl' => $videoUrl,
            ':thumbnailUrl' => $thumbnailUrl,
            ':title' => $title,
            ':description' => $description,
            ':source' => $source,
        ]);
    }

    private function createLink(string $src, string $url): string
    {
        return (new UrlNormalizer())->normalize($src, $url);
    }

    private function shouldSkipHref(string $href): bool
    {
        $href = trim($href);

        return $href === ''
            || strpos($href, '#') !== false
            || stripos($href, 'javascript:') === 0;
    }

    /**
     * @param list<string> $names
     */
    private function firstMetaContent(DOMDocument $document, array $names): string
    {
        foreach ($document->getElementsByTagName('meta') as $meta) {
            if (!$meta instanceof DOMElement) {
                continue;
            }

            $name = strtolower($meta->getAttribute('property') ?: $meta->getAttribute('name'));

            if (in_array($name, array_map('strtolower', $names), true)) {
                return trim($meta->getAttribute('content'));
            }
        }

        return '';
    }

    private function normaliseOptionalUrl(string $value, string $pageUrl, int $depth): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $normalised = $this->createLink($value, $pageUrl);

        return $this->validator->rejectionReason($normalised, $depth) === null ? $normalised : '';
    }

    private function isVideoLikeUrl(string $url): bool
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
        $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?: ''));

        if (preg_match('/\.(mp4|webm|ogv|ogg|mov|m4v|m3u8)$/', $path) === 1) {
            return true;
        }

        foreach (
            ['youtube.com', 'youtu.be', 'vimeo.com', 'dailymotion.com', 'twitch.tv', 'streamable.com'] as $videoHost
        ) {
            if ($host === $videoHost || str_ends_with($host, '.' . $videoHost)) {
                return true;
            }
        }

        return str_contains($host, 'video') || str_contains($path, '/embed/');
    }

    private function sourceFromUrl(string $url): string
    {
        $host = (string) (parse_url($url, PHP_URL_HOST) ?: '');

        return $host !== '' ? $host : 'video';
    }

    private function cleanText(string $value): string
    {
        return str_replace(["\n", "\r", "\t"], ' ', trim($value));
    }

    /**
     * @param DOMNodeList<\DOMNode> $nodes
     */
    private function firstNodeValue(DOMNodeList $nodes): string
    {
        if ($nodes->length === 0 || $nodes->item(0) === null) {
            return '';
        }

        return str_replace("\n", '', (string) $nodes->item(0)?->nodeValue);
    }

    private function record(string $line): void
    {
        $this->output[] = $line . '<br>';
    }
}
