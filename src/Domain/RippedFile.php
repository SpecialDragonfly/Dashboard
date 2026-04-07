<?php

namespace App\Domain;

use DateTimeImmutable;

class RippedFile
{
    public function __construct(
        private int $id,
        private string $url,
        private string $videoId,
        private ?string $title,
        private ?string $thumbnail,
        private ?string $path,
        private DateTimeImmutable $createdAt,
    ) {}

    public function getId(): int { return $this->id; }
    public function getUrl(): string { return $this->url; }
    public function getVideoId(): string { return $this->videoId; }
    public function getTitle(): ?string { return $this->title; }
    public function getThumbnail(): ?string { return $this->thumbnail; }
    public function getPath(): ?string { return $this->path; }
    public function isReady(): bool { return $this->path !== null; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
}
