<?php

declare(strict_types=1);

namespace Doogle\Repository;

use PDO;

final class ImageRepository implements ImageSearchRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function countBySearchTerm(string $term): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) AS total
             FROM images
             WHERE (title LIKE :term
                OR alt LIKE :term)
               AND broken = 0'
        );

        $statement->bindValue(':term', '%' . $term . '%');
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $term, int $offset, int $limit): array
    {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM images
             WHERE (title LIKE :term
                OR alt LIKE :term)
               AND broken = 0
             ORDER BY clicks DESC
             LIMIT :fromLimit, :pageSize'
        );

        $statement->bindValue(':term', '%' . $term . '%');
        $statement->bindValue(':fromLimit', max(0, $offset), PDO::PARAM_INT);
        $statement->bindValue(':pageSize', max(0, $limit), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function incrementClicksByUrl(string $imageUrl): bool
    {
        $statement = $this->pdo->prepare('UPDATE images SET clicks = clicks + 1 WHERE imageUrl = :imageUrl');
        $statement->bindValue(':imageUrl', $imageUrl);

        return $statement->execute();
    }

    public function markBroken(string $imageUrl): bool
    {
        $statement = $this->pdo->prepare('UPDATE images SET broken = 1 WHERE imageUrl = :src');
        $statement->bindValue(':src', $imageUrl);

        return $statement->execute();
    }
}
