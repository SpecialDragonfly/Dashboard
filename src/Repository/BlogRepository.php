<?php

namespace App\Repository;

use App\Domain\BlogPost;
use DateTimeImmutable;
use PDO;
use RuntimeException;

class BlogRepository
{
    public function __construct(private PDO $db) {}

    /**
     * @return list<BlogPost>
     */
    public function findPublished(): array
    {
        $stmt = $this->db->query(
            'SELECT * FROM blog_posts WHERE published = 1 ORDER BY created_at DESC'
        );
        if ($stmt === false) {
            throw new RuntimeException('Failed to query blog_posts');
        }

        return $this->hydrateAll($stmt);
    }

    /**
     * @return list<BlogPost>
     */
    public function findAll(): array
    {
        $stmt = $this->db->query('SELECT * FROM blog_posts ORDER BY created_at DESC');
        if ($stmt === false) {
            throw new RuntimeException('Failed to query blog_posts');
        }

        return $this->hydrateAll($stmt);
    }

    public function findBySlug(string $slug): ?BlogPost
    {
        $stmt = $this->db->prepare('SELECT * FROM blog_posts WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }
        return $this->hydrate($row);
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
        $post = $this->findBySlug($slug);
        if ($post === null) {
            throw new RuntimeException('Failed to load blog post after insert');
        }
        return $post;
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

    /**
     * @return list<BlogPost>
     */
    private function hydrateAll(\PDOStatement $stmt): array
    {
        $rows = $stmt->fetchAll();

        $posts = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $posts[] = $this->hydrate($row);
        }

        return $posts;
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function hydrate(array $row): BlogPost
    {
        $id = $row['id'];
        $userId = $row['user_id'];
        $title = $row['title'];
        $slug = $row['slug'];
        $content = $row['content'];
        $tags = $row['tags'];
        $createdAt = $row['created_at'];
        $updatedAt = $row['updated_at'];

        if (!is_numeric($id) || !is_numeric($userId)) {
            throw new RuntimeException('Unexpected non-numeric id/user_id in blog_posts row');
        }
        if (!is_string($title) || !is_string($slug) || !is_string($content)) {
            throw new RuntimeException('Unexpected non-string title/slug/content in blog_posts row');
        }
        if ($tags !== null && !is_string($tags)) {
            throw new RuntimeException('Unexpected non-string tags in blog_posts row');
        }
        if (!is_string($createdAt) || !is_string($updatedAt)) {
            throw new RuntimeException('Unexpected non-string created_at/updated_at in blog_posts row');
        }

        return new BlogPost(
            (int) $id,
            (int) $userId,
            $title,
            $slug,
            $content,
            $tags,
            (bool) $row['published'],
            new DateTimeImmutable($createdAt),
            new DateTimeImmutable($updatedAt),
        );
    }
}
