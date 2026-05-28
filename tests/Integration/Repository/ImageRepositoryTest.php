<?php

declare(strict_types=1);

namespace Doogle\Tests\Integration\Repository;

use Doogle\Repository\ImageRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class ImageRepositoryTest extends TestCase
{
    private PDO $pdo;
    private ImageRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE images (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                siteUrl VARCHAR(512) NOT NULL,
                imageUrl VARCHAR(512) NOT NULL,
                alt VARCHAR(512) NOT NULL,
                title VARCHAR(512) NOT NULL,
                clicks INTEGER NOT NULL DEFAULT 0,
                broken INTEGER NOT NULL DEFAULT 0
            )'
        );

        $this->repository = new ImageRepository($this->pdo);
    }

    public function testCountsImagesBySearchTermAndExcludesBrokenImages(): void
    {
        $this->insertImage('https://example.com/site', 'https://example.com/linux.png', 'Linux', 'Linux Logo', 3, 0);
        $this->insertImage('https://example.com/site', 'https://example.com/broken.png', 'Linux', 'Broken Linux', 8, 1);

        self::assertSame(1, $this->repository->countBySearchTerm('linux'));
    }

    public function testSearchReturnsImagesOrderedByClicks(): void
    {
        $this->insertImage('https://example.com/site', 'https://example.com/one.png', 'Linux', 'Linux One', 3, 0);
        $this->insertImage('https://example.com/site', 'https://example.com/two.png', 'Linux', 'Linux Two', 9, 0);

        $results = $this->repository->search('linux', 0, 30);

        self::assertSame('https://example.com/two.png', $results[0]['imageUrl']);
        self::assertSame('https://example.com/one.png', $results[1]['imageUrl']);
    }

    public function testSearchAppliesOffsetAndLimit(): void
    {
        $this->insertImage('https://example.com/site', 'https://example.com/one.png', 'Linux', 'Linux One', 3, 0);
        $this->insertImage('https://example.com/site', 'https://example.com/two.png', 'Linux', 'Linux Two', 9, 0);

        $results = $this->repository->search('linux', 1, 1);

        self::assertCount(1, $results);
        self::assertSame('https://example.com/one.png', $results[0]['imageUrl']);
    }

    public function testIncrementClicksByUrlUpdatesImage(): void
    {
        $this->insertImage('https://example.com/site', 'https://example.com/one.png', 'Linux', 'Linux One', 3, 0);

        self::assertTrue($this->repository->incrementClicksByUrl('https://example.com/one.png'));

        $clicks = $this->pdo
            ->query("SELECT clicks FROM images WHERE imageUrl = 'https://example.com/one.png'")
            ->fetchColumn();

        self::assertSame(4, (int) $clicks);
    }

    public function testMarkBrokenUpdatesImage(): void
    {
        $this->insertImage('https://example.com/site', 'https://example.com/one.png', 'Linux', 'Linux One', 3, 0);

        self::assertTrue($this->repository->markBroken('https://example.com/one.png'));

        $broken = $this->pdo
            ->query("SELECT broken FROM images WHERE imageUrl = 'https://example.com/one.png'")
            ->fetchColumn();

        self::assertSame(1, (int) $broken);
    }

    private function insertImage(
        string $siteUrl,
        string $imageUrl,
        string $alt,
        string $title,
        int $clicks,
        int $broken,
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO images (siteUrl, imageUrl, alt, title, clicks, broken)
             VALUES (:siteUrl, :imageUrl, :alt, :title, :clicks, :broken)'
        );

        $statement->execute([
            ':siteUrl' => $siteUrl,
            ':imageUrl' => $imageUrl,
            ':alt' => $alt,
            ':title' => $title,
            ':clicks' => $clicks,
            ':broken' => $broken,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
