<?php

namespace App\Service;

use App\Domain\RippedFile;
use App\Repository\RipperRepository;

class RipperService
{
    private const YTDLP = '/usr/local/bin/yt-dlp';

    public function __construct(
        private RipperRepository $repo,
        private string $downloadDir,
        private string $thumbnailDir,
    ) {}

    /**
     * @return array{status: string, message: string}
     */
    public function rip(string $url): array
    {
        $existing = $this->repo->findByUrl($url);

        if ($existing !== null) {
            if ($existing->getPath() !== null) {
                return ['status' => 'exists', 'message' => 'Already ripped as ' . $existing->getTitle()];
            }
            return ['status' => 'downloading', 'message' => 'Already downloading'];
        }

        $videoId = $this->fetchVideoId($url);
        if ($videoId === null) {
            return ['status' => 'error', 'message' => 'Could not resolve video ID — is the URL valid?'];
        }

        $this->repo->insert($url, $videoId);
        $this->spawnInfoDownload($url);
        $this->spawnAudioDownload($url);

        return ['status' => 'started', 'message' => 'Download started'];
    }

    public function cleanup(): void
    {
        if (!is_dir($this->downloadDir)) {
            return;
        }

        foreach (scandir($this->downloadDir) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $id  = pathinfo($file, PATHINFO_FILENAME);

            if ($ext === 'webp' || $ext === 'jpg' || $ext === 'png') {
                $dest = $this->thumbnailDir . $file;
                if (!is_dir($this->thumbnailDir)) {
                    mkdir($this->thumbnailDir, 0755, true);
                }
                rename($this->downloadDir . $file, $dest);
                $this->repo->updateThumbnail($id, '/assets/ripper/thumbnails/' . $file);

            } elseif ($ext === 'json') {
                $contents = file_get_contents($this->downloadDir . $file);
                $data = $contents !== false ? json_decode($contents, true) : null;
                if (
                    is_array($data)
                    && isset($data['id'], $data['title'])
                    && is_string($data['id'])
                    && is_string($data['title'])
                ) {
                    $this->repo->updateTitle($data['id'], $data['title']);
                    unlink($this->downloadDir . $file);
                }

            } elseif ($ext === 'mp3') {
                $this->repo->updatePath($id, $this->downloadDir . $file);
                foreach (['webm', 'm4a', 'opus', 'ogg', 'flac'] as $srcExt) {
                    $src = $this->downloadDir . $id . '.' . $srcExt;
                    if (file_exists($src)) {
                        unlink($src);
                    }
                }
            } elseif (in_array($ext, ['webm', 'm4a', 'opus', 'ogg', 'flac'])) {
                $mp3Path = $this->downloadDir . $id . '.mp3';
                if (!file_exists($mp3Path)) {
                    exec(sprintf(
                        'nohup ffmpeg -i %s -vn -ar 44100 -ac 2 -b:a 192k %s > /dev/null 2>&1 &',
                        escapeshellarg($this->downloadDir . $file),
                        escapeshellarg($mp3Path)
                    ));
                }
            }
        }
    }

    /**
     * @return array<array{video_id: string, title: ?string, thumbnail: ?string, ready: bool}>
     */
    public function getHistory(): array
    {
        $this->cleanup();
        return array_map(fn(RippedFile $f) => [
            'video_id'  => $f->getVideoId(),
            'title'     => $f->getTitle(),
            'thumbnail' => $f->getThumbnail(),
            'ready'     => $f->isReady(),
        ], $this->repo->findAll());
    }

    public function getDownloadPath(string $videoId): ?string
    {
        $file = $this->repo->findByVideoId($videoId);
        return $file?->getPath();
    }

    private function fetchVideoId(string $url): ?string
    {
        $cmd = sprintf('%s --print id %s 2>/dev/null', self::YTDLP, escapeshellarg($url));
        $output = [];
        exec($cmd, $output);
        $id = trim($output[0] ?? '');
        return $id !== '' ? $id : null;
    }

    private function spawnAudioDownload(string $url): void
    {
        $cmd = sprintf(
            'nohup %s -f bestaudio --extract-audio --audio-format mp3 --audio-quality 0 -o %s %s > /dev/null 2>&1 &',
            self::YTDLP,
            escapeshellarg($this->downloadDir . '%(id)s.%(ext)s'),
            escapeshellarg($url),
        );
        exec($cmd);
    }

    private function spawnInfoDownload(string $url): void
    {
        $cmd = sprintf(
            'nohup %s --write-thumbnail --write-info-json --skip-download -o %s %s > /dev/null 2>&1 &',
            self::YTDLP,
            escapeshellarg($this->downloadDir . '%(id)s.%(ext)s'),
            escapeshellarg($url),
        );
        exec($cmd);
    }
}
