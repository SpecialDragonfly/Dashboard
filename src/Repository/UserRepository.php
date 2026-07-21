<?php

namespace App\Repository;

use App\Domain\User;
use DateTimeImmutable;
use PDO;

class UserRepository
{
    public function __construct(private PDO $db) {}

    public function findByUsername(string $username): ?User
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    private function hydrate(array $row): User
    {
        return new User(
            (int) $row['id'],
            $row['username'],
            $row['password'],
            new DateTimeImmutable($row['created']),
        );
    }
}
