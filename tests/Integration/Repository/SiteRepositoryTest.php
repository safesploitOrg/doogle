<?php

declare(strict_types=1);

namespace Doogle\Tests\Integration\Repository;

use Doogle\Repository\SiteRepository;
use Doogle\Search\RankingSettings;
use PDO;
use PHPUnit\Framework\TestCase;

final class SiteRepositoryTest extends TestCase
{
    private PDO $pdo;
    private SiteRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE sites (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                url VARCHAR(512) NOT NULL,
                title VARCHAR(512) NOT NULL,
                description VARCHAR(512) NOT NULL,
                keywords VARCHAR(512) NOT NULL,
                clicks INTEGER NOT NULL DEFAULT 0
            )'
        );

        $this->repository = new SiteRepository($this->pdo);
    }

    public function testCountsSitesBySearchTerm(): void
    {
        $this->insertSite('https://example.com/linux', 'Linux Guide', 'Install Linux', 'linux,server', 2);
        $this->insertSite('https://example.com/php', 'PHP Guide', 'Install PHP', 'php,server', 5);

        self::assertSame(1, $this->repository->countBySearchTerm('linux'));
        self::assertSame(2, $this->repository->countBySearchTerm('server'));
    }

    public function testSearchReturnsSitesOrderedByClicksWhenRelevanceIsEqual(): void
    {
        $this->insertSite('https://example.com/one', 'Linux One', 'Linux', 'linux', 3);
        $this->insertSite('https://example.com/two', 'Linux Two', 'Linux', 'linux', 9);

        $results = $this->repository->search('linux', 0, 20);

        self::assertSame('https://example.com/two', $results[0]['url']);
        self::assertSame('https://example.com/one', $results[1]['url']);
        self::assertArrayHasKey('rankingScore', $results[0]);
    }

    public function testSearchRanksTitleMatchesAboveHigherClickedDescriptionMatches(): void
    {
        $this->insertSite(
            'https://example.com/title',
            'Linux Security Guide',
            'Hardening notes',
            'security',
            1
        );
        $this->insertSite(
            'https://example.com/description',
            'Security Notes',
            'Linux hardening checklist',
            'security',
            90
        );

        $results = $this->repository->search('linux', 0, 20);

        self::assertSame('https://example.com/title', $results[0]['url']);
        self::assertSame('https://example.com/description', $results[1]['url']);
    }

    public function testSearchCapsClickBoostSoClicksCannotDominateStrongerRelevance(): void
    {
        $this->insertSite('https://example.com/exact', 'linux', '', '', 0);
        $this->insertSite('https://example.com/clicked', 'Clicked Result', 'linux', '', 10000);

        $results = $this->repository->search('linux', 0, 20);

        self::assertSame('https://example.com/exact', $results[0]['url']);
        self::assertSame('https://example.com/clicked', $results[1]['url']);
        self::assertGreaterThan((float) $results[1]['rankingScore'], (float) $results[0]['rankingScore']);
    }

    public function testSearchCanUseCustomRankingWeights(): void
    {
        $this->insertSite('https://example.com/title', 'Linux Security Guide', 'Hardening notes', 'security', 1);
        $this->insertSite(
            'https://example.com/description',
            'Security Notes',
            'Linux hardening checklist',
            'security',
            1
        );

        $repository = new SiteRepository(
            $this->pdo,
            new RankingSettings([
                RankingSettings::settingKey('sites', 'description_partial') => 500,
            ])
        );

        $results = $repository->search('linux', 0, 20);

        self::assertSame('https://example.com/description', $results[0]['url']);
    }

    public function testSearchAppliesOffsetAndLimit(): void
    {
        $this->insertSite('https://example.com/one', 'Linux One', 'Linux', 'linux', 3);
        $this->insertSite('https://example.com/two', 'Linux Two', 'Linux', 'linux', 9);

        $results = $this->repository->search('linux', 1, 1);

        self::assertCount(1, $results);
        self::assertSame('https://example.com/one', $results[0]['url']);
    }

    public function testIncrementClicksUpdatesSite(): void
    {
        $id = $this->insertSite('https://example.com/one', 'Linux One', 'Linux', 'linux', 3);

        self::assertTrue($this->repository->incrementClicks($id));

        $clicks = $this->pdo
            ->query('SELECT clicks FROM sites WHERE id = ' . $id)
            ->fetchColumn();

        self::assertSame(4, (int) $clicks);
    }

    private function insertSite(
        string $url,
        string $title,
        string $description,
        string $keywords,
        int $clicks,
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO sites (url, title, description, keywords, clicks)
             VALUES (:url, :title, :description, :keywords, :clicks)'
        );

        $statement->execute([
            ':url' => $url,
            ':title' => $title,
            ':description' => $description,
            ':keywords' => $keywords,
            ':clicks' => $clicks,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
