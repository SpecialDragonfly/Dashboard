<?php

namespace App\Service;

use App\Domain\BlogPost;
use App\Repository\BlogRepository;

class BlogService
{
    public function __construct(private BlogRepository $repo) {}

    public function getPublishedPosts(): array
    {
        return $this->repo->findPublished();
    }

    public function getAllPosts(): array
    {
        return $this->repo->findAll();
    }

    public function getPost(string $slug): ?BlogPost
    {
        return $this->repo->findBySlug($slug);
    }

    public function createPost(int $userId, string $title, string $content, ?string $tags, bool $published): BlogPost
    {
        $slug = $this->generateSlug($title);
        return $this->repo->create($userId, $title, $slug, $content, $tags, $published);
    }

    public function updatePost(string $slug, string $title, string $content, ?string $tags, bool $published): ?BlogPost
    {
        $post = $this->repo->findBySlug($slug);
        if ($post === null) {
            return null;
        }

        $newSlug = $this->generateSlug($title, $post->getId());
        $this->repo->update($post->getId(), $title, $newSlug, $content, $tags, $published);
        return $this->repo->findBySlug($newSlug);
    }

    public function deletePost(string $slug): bool
    {
        $post = $this->repo->findBySlug($slug);
        if ($post === null) {
            return false;
        }
        $this->repo->delete($post->getId());
        return true;
    }

    private function generateSlug(string $title, ?int $excludeId = null): string
    {
        $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title), '-'));
        $slug = $base;
        $n = 2;
        while ($this->repo->slugExists($slug, $excludeId)) {
            $slug = "$base-$n";
            $n++;
        }
        return $slug;
    }
}
