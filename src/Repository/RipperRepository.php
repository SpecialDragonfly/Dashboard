<?php

namespace App\Repository;

use App\Domain\RippedFile;
use DateTimeImmutable;
use PDO;

class RipperRepository
{
    public function __construct(private PDO $db) {}

    public function findByUrl(string $url): ?RippedFile
    {
        $stmt = $this->db->prepare('SELECT * FROM ripped_files WHERE url = ? LIMIT 1');
        $stmt->execute([$url]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findByVideoId(string $videoId): ?RippedFile
    {
        $stmt = $this->db->prepare('SELECT * FROM ripped_files WHERE video_id = ? LIMIT 1');
        $stmt->execute([$videoId]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->db->query('SELECT * FROM ripped_files ORDER BY created_at DESC');
        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function insert(string $url, string $videoId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO ripped_files (url, video_id) VALUES (?, ?)'
        );
        $stmt->execute([$url, $videoId]);
    }

    public function updateTitle(string $videoId, string $title): void
    {
        $stmt = $this->db->prepare('UPDATE ripped_files SET title = ? WHERE video_id = ?');
        $stmt->execute([$title, $videoId]);
    }

    public function updateThumbnail(string $videoId, string $thumbnail): void
    {
        $stmt = $this->db->prepare('UPDATE ripped_files SET thumbnail = ? WHERE video_id = ?');
        $stmt->execute([$thumbnail, $videoId]);
    }

    public function updatePath(string $videoId, string $path): void
    {
        $stmt = $this->db->prepare('UPDATE ripped_files SET path = ? WHERE video_id = ?');
        $stmt->execute([$path, $videoId]);
    }

    private function hydrate(array $row): RippedFile
    {
        return new RippedFile(
            (int) $row['id'],
            $row['url'],
            $row['video_id'],
            $row['title'],
            $row['thumbnail'],
            $row['path'],
            new DateTimeImmutable($row['created_at']),
        );
    }
}
