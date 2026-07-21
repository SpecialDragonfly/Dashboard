<?php

namespace App\Repository;

use App\Domain\BlogPost;
use DateTimeImmutable;
use PDO;

class BlogRepository
{
    public function __construct(private PDO $db) {}

    public function findPublished(): array
    {
        $stmt = $this->db->query(
            'SELECT * FROM blog_posts WHERE published = 1 ORDER BY created_at DESC'
        );
        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findAll(): array
    {
        $stmt = $this->db->query('SELECT * FROM blog_posts ORDER BY created_at DESC');
        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findBySlug(string $slug): ?BlogPost
    {
        $stmt = $this->db->prepare('SELECT * FROM blog_posts WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $stmt = $this->db->prepare('SELECT 1 FROM blog_posts WHERE slug = ? AND id != ? LIMIT 1');
            $stmt->execute([$slug, $excludeId]);
        } else {
            $stmt = $this->db->prepare('SELECT 1 FROM blog_posts WHERE slug = ? LIMIT 1');
            $stmt->execute([$slug]);
        }
        return (bool) $stmt->fetch();
    }

    public function create(int $userId, string $title, string $slug, string $content, ?string $tags, bool $published): BlogPost
    {
        $stmt = $this->db->prepare(
            'INSERT INTO blog_posts (user_id, title, slug, content, tags, published)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $title, $slug, $content, $tags, (int) $published]);
        $id = (int) $this->db->lastInsertId();
        return $this->findBySlug($slug);
    }

    public function update(int $id, string $title, string $slug, string $content, ?string $tags, bool $published): void
    {
        $stmt = $this->db->prepare(
            'UPDATE blog_posts
             SET title = ?, slug = ?, content = ?, tags = ?, published = ?, updated_at = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $title, $slug, $content, $tags, (int) $published,
            (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM blog_posts WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function hydrate(array $row): BlogPost
    {
        return new BlogPost(
            (int) $row['id'],
            (int) $row['user_id'],
            $row['title'],
            $row['slug'],
            $row['content'],
            $row['tags'],
            (bool) $row['published'],
            new DateTimeImmutable($row['created_at']),
            new DateTimeImmutable($row['updated_at']),
        );
    }
}
