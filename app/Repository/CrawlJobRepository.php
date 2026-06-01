<?php

declare(strict_types=1);

namespace Doogle\Repository;

use Doogle\Crawl\CrawlJob;
use Doogle\Crawl\CrawlResult;
use PDO;

final class CrawlJobRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(string $startUrl, ?int $requestedByUserId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO crawl_jobs (start_url, requested_by_user_id, status)
             VALUES (:start_url, :requested_by_user_id, :status)'
        );
        $statement->bindValue(':start_url', $startUrl);
        $this->bindNullableInt($statement, ':requested_by_user_id', $requestedByUserId);
        $statement->bindValue(':status', 'pending');
        $statement->execute();

        return (int) $this->pdo->lastInsertId();
    }

    public function markRunning(int $id): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE crawl_jobs
             SET status = :status, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        return $statement->execute([
            ':id' => $id,
            ':status' => 'running',
        ]);
    }

    public function markFromResult(int $id, CrawlResult $result): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE crawl_jobs
             SET status = :status,
                 pages_discovered = :pages_discovered,
                 pages_indexed = :pages_indexed,
                 images_indexed = :images_indexed,
                 urls_rejected = :urls_rejected,
                 error_message = :error_message,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        return $statement->execute([
            ':id' => $id,
            ':status' => $this->statusForResult($result),
            ':pages_discovered' => $result->pagesDiscovered,
            ':pages_indexed' => $result->pagesIndexed,
            ':images_indexed' => $result->imagesIndexed,
            ':urls_rejected' => $result->urlsRejected,
            ':error_message' => $result->errors !== [] ? implode("\n", $result->errors) : null,
        ]);
    }

    /**
     * @return list<CrawlJob>
     */
    public function recent(int $limit = 10): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id,
                    start_url,
                    requested_by_user_id,
                    status,
                    pages_discovered,
                    pages_indexed,
                    images_indexed,
                    urls_rejected,
                    error_message,
                    created_at,
                    updated_at
             FROM crawl_jobs
             ORDER BY id DESC
             LIMIT :limit'
        );
        $statement->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            static fn (array $row): CrawlJob => CrawlJob::fromRow($row),
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    private function statusForResult(CrawlResult $result): string
    {
        if ($result->successful) {
            return 'completed';
        }

        return $result->urlsRejected > 0 ? 'rejected' : 'failed';
    }

    private function bindNullableInt(\PDOStatement $statement, string $parameter, ?int $value): void
    {
        if ($value === null) {
            $statement->bindValue($parameter, null, PDO::PARAM_NULL);
            return;
        }

        $statement->bindValue($parameter, $value, PDO::PARAM_INT);
    }
}
