<?php

namespace App\Domain;

use DateTimeImmutable;

class BlogPost
{
    public function __construct(
        private int $id,
        private int $userId,
        private string $title,
        private string $slug,
        private string $content,
        private ?string $tags,
        private bool $published,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {}

    public function getId(): int { return $this->id; }
    public function getUserId(): int { return $this->userId; }
    public function getTitle(): string { return $this->title; }
    public function getSlug(): string { return $this->slug; }
    public function getContent(): string { return $this->content; }
    public function getTags(): ?string { return $this->tags; }
    public function isPublished(): bool { return $this->published; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): DateTimeImmutable { return $this->updatedAt; }

    /**
     * @return list<string>
     */
    public function getTagsArray(): array
    {
        if ($this->tags === null || $this->tags === '') {
            return [];
        }
        return array_map('trim', explode(',', $this->tags));
    }
}
