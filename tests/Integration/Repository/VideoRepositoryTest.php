<?php

declare(strict_types=1);

namespace Doogle\Tests\Integration\Repository;

use Doogle\Repository\VideoRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class VideoRepositoryTest extends TestCase
{
    private PDO $pdo;
    private VideoRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE videos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                siteUrl VARCHAR(512) NOT NULL,
                videoUrl VARCHAR(512) NOT NULL,
                thumbnailUrl VARCHAR(512) NOT NULL DEFAULT "",
                title VARCHAR(512) NOT NULL DEFAULT "",
                description VARCHAR(512) NOT NULL DEFAULT "",
                source VARCHAR(100) NOT NULL DEFAULT "",
                clicks INTEGER NOT NULL DEFAULT 0
            )'
        );

        $this->repository = new VideoRepository($this->pdo);
    }

    public function testCountsVideosBySearchTerm(): void
    {
        $this->insertVideo('https://example.com/linux.mp4', 'Linux Kernel Talk', 'Conference recording', 3);
        $this->insertVideo('https://example.com/php.mp4', 'PHP Talk', 'Conference recording', 5);

        self::assertSame(1, $this->repository->countBySearchTerm('linux'));
        self::assertSame(2, $this->repository->countBySearchTerm('conference'));
    }

    public function testSearchReturnsVideosOrderedByClicksWhenRelevanceIsEqual(): void
    {
        $this->insertVideo('https://example.com/one.mp4', 'Linux Talk One', 'Linux', 2);
        $this->insertVideo('https://example.com/two.mp4', 'Linux Talk Two', 'Linux', 9);

        $results = $this->repository->search('linux', 0, 24);

        self::assertSame('https://example.com/two.mp4', $results[0]['videoUrl']);
        self::assertSame('https://example.com/one.mp4', $results[1]['videoUrl']);
        self::assertArrayHasKey('rankingScore', $results[0]);
    }

    public function testSearchRanksTitleMatchesAboveHigherClickedDescriptionMatches(): void
    {
        $this->insertVideo('https://example.com/title.mp4', 'Linux Security Talk', 'Hardening notes', 1);
        $this->insertVideo('https://example.com/description.mp4', 'Security Talk', 'Linux hardening notes', 90);

        $results = $this->repository->search('linux', 0, 24);

        self::assertSame('https://example.com/title.mp4', $results[0]['videoUrl']);
        self::assertSame('https://example.com/description.mp4', $results[1]['videoUrl']);
    }

    public function testSearchCanMatchVideoUrl(): void
    {
        $this->insertVideo('https://example.com/linux-demo.mp4', 'Demo', 'Video', 1);

        $results = $this->repository->search('linux', 0, 24);

        self::assertSame(1, $this->repository->countBySearchTerm('linux'));
        self::assertCount(1, $results);
        self::assertSame('https://example.com/linux-demo.mp4', $results[0]['videoUrl']);
    }

    public function testSearchAppliesOffsetAndLimit(): void
    {
        $this->insertVideo('https://example.com/one.mp4', 'Linux One', 'Linux', 3);
        $this->insertVideo('https://example.com/two.mp4', 'Linux Two', 'Linux', 9);

        $results = $this->repository->search('linux', 1, 1);

        self::assertCount(1, $results);
        self::assertSame('https://example.com/one.mp4', $results[0]['videoUrl']);
    }

    public function testIncrementClicksByUrlUpdatesVideo(): void
    {
        $this->insertVideo('https://example.com/one.mp4', 'Linux One', 'Linux', 3);

        self::assertTrue($this->repository->incrementClicksByUrl('https://example.com/one.mp4'));

        $clicks = $this->pdo
            ->query("SELECT clicks FROM videos WHERE videoUrl = 'https://example.com/one.mp4'")
            ->fetchColumn();

        self::assertSame(4, (int) $clicks);
    }

    private function insertVideo(string $videoUrl, string $title, string $description, int $clicks): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO videos (siteUrl, videoUrl, thumbnailUrl, title, description, source, clicks)
             VALUES (:siteUrl, :videoUrl, :thumbnailUrl, :title, :description, :source, :clicks)'
        );

        $statement->execute([
            ':siteUrl' => 'https://example.com/page',
            ':videoUrl' => $videoUrl,
            ':thumbnailUrl' => 'https://example.com/thumb.jpg',
            ':title' => $title,
            ':description' => $description,
            ':source' => 'video',
            ':clicks' => $clicks,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
