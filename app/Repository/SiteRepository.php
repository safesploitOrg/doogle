<?php

declare(strict_types=1);

namespace Doogle\Repository;

use PDO;

final class SiteRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function countBySearchTerm(string $term): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) AS total
             FROM sites
             WHERE title LIKE :term
                OR url LIKE :term
                OR keywords LIKE :term
                OR description LIKE :term'
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
             FROM sites
             WHERE title LIKE :term
                OR url LIKE :term
                OR keywords LIKE :term
                OR description LIKE :term
             ORDER BY clicks DESC
             LIMIT :fromLimit, :pageSize'
        );

        $statement->bindValue(':term', '%' . $term . '%');
        $statement->bindValue(':fromLimit', max(0, $offset), PDO::PARAM_INT);
        $statement->bindValue(':pageSize', max(0, $limit), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function incrementClicks(int|string $id): bool
    {
        $statement = $this->pdo->prepare('UPDATE sites SET clicks = clicks + 1 WHERE id = :id');
        $statement->bindValue(':id', $id);

        return $statement->execute();
    }
}
