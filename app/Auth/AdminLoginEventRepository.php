<?php

declare(strict_types=1);

namespace Doogle\Auth;

use PDO;

final class AdminLoginEventRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function record(
        string $username,
        ?int $userId,
        bool $successful,
        string $failureReason,
        string $ipAddress,
    ): bool {
        $statement = $this->pdo->prepare(
            'INSERT INTO admin_login_events (user_id, username, successful, failure_reason, ip_address)
             VALUES (:user_id, :username, :successful, :failure_reason, :ip_address)'
        );

        return $statement->execute([
            ':user_id' => $userId,
            ':username' => substr($username, 0, 100),
            ':successful' => $successful ? 1 : 0,
            ':failure_reason' => substr($failureReason, 0, 100),
            ':ip_address' => substr($ipAddress, 0, 45),
        ]);
    }

    /**
     * @return list<AdminLoginEvent>
     */
    public function recent(int $limit = 25): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, user_id, username, successful, failure_reason, ip_address, created_at
             FROM admin_login_events
             ORDER BY id DESC
             LIMIT :limit'
        );
        $statement->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            static fn (array $row): AdminLoginEvent => AdminLoginEvent::fromRow($row),
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }
}
