<?php

namespace App\Repository;

use App\Domain\RippedFile;
use DateTimeImmutable;
use PDO;
use RuntimeException;

class RipperRepository
{
    public function __construct(private PDO $db) {}

    public function findByUrl(string $url): ?RippedFile
    {
        $stmt = $this->db->prepare('SELECT * FROM ripped_files WHERE url = ? LIMIT 1');
        $stmt->execute([$url]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findByVideoId(string $videoId): ?RippedFile
    {
        $stmt = $this->db->prepare('SELECT * FROM ripped_files WHERE video_id = ? LIMIT 1');
        $stmt->execute([$videoId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * @return RippedFile[]
     */
    public function findAll(): array
    {
        $stmt = $this->db->query('SELECT * FROM ripped_files ORDER BY created DESC');
        if ($stmt === false) {
            return [];
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $files = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $files[] = $this->hydrate($row);
            }
        }

        return $files;
    }

    public function insert(string $url, string $videoId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO ripped_files (url, video_id, created) VALUES (?, ?, ?)'
        );
        $stmt->execute([$url, $videoId, (new DateTimeImmutable())->format('Y-m-d H:i:s')]);
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

    /**
     * @param array<array-key, mixed> $row
     */
    private function hydrate(array $row): RippedFile
    {
        $id = $row['id'];
        $url = $row['url'];
        $videoId = $row['video_id'];
        $title = $row['title'];
        $thumbnail = $row['thumbnail'];
        $path = $row['path'];
        $created = $row['created'];

        if (!is_int($id) && !is_string($id)) {
            throw new RuntimeException('Unexpected type for ripped_files.id');
        }
        if (!is_string($url)) {
            throw new RuntimeException('Unexpected type for ripped_files.url');
        }
        if (!is_string($videoId)) {
            throw new RuntimeException('Unexpected type for ripped_files.video_id');
        }
        if ($title !== null && !is_string($title)) {
            throw new RuntimeException('Unexpected type for ripped_files.title');
        }
        if ($thumbnail !== null && !is_string($thumbnail)) {
            throw new RuntimeException('Unexpected type for ripped_files.thumbnail');
        }
        if ($path !== null && !is_string($path)) {
            throw new RuntimeException('Unexpected type for ripped_files.path');
        }
        if ($created !== null && !is_string($created)) {
            throw new RuntimeException('Unexpected type for ripped_files.created');
        }

        return new RippedFile(
            (int) $id,
            $url,
            $videoId,
            $title,
            $thumbnail,
            $path,
            $created !== null ? new DateTimeImmutable($created) : null,
        );
    }
}
