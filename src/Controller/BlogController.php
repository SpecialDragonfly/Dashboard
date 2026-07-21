<?php

namespace App\Controller;

use App\Domain\User;
use App\Service\BlogService;
use League\CommonMark\CommonMarkConverter;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Twig\Environment;

class BlogController extends AbstractController
{
    private CommonMarkConverter $markdown;

    public function __construct(
        private Environment $twig,
        private BlogService $blogService,
    ) {
        $this->markdown = new CommonMarkConverter(['html_input' => 'strip']);
    }

    /**
     * @param array<string, string> $args
     */
    public function index(Request $request, Response $response, array $args): Response
    {
        $posts = $this->blogService->getPublishedPosts();
        $vars = array_merge($this->baseVars(), ['posts' => $posts]);
        $response->getBody()->write($this->twig->render('blog/index.html.twig', $vars));
        return $response;
    }

    /**
     * @param array<string, string> $args
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $post = $this->blogService->getPost($args['slug']);

        if ($post === null || (!$post->isPublished())) {
            return $response->withStatus(404);
        }

        $rendered = $this->markdown->convert($post->getContent())->getContent();
        $vars = array_merge($this->baseVars(), ['post' => $post, 'content' => $rendered]);
        $response->getBody()->write($this->twig->render('blog/post.html.twig', $vars));
        return $response;
    }

    /**
     * @param array<string, string> $args
     */
    public function create(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');

        if ($request->getMethod() === 'POST') {
            if (!$user instanceof User) {
                return $response->withStatus(401);
            }

            $body = $request->getParsedBody();
            $body = is_array($body) ? $body : [];
            $title = isset($body['title']) && is_string($body['title']) ? trim($body['title']) : '';
            $content = isset($body['content']) && is_string($body['content']) ? trim($body['content']) : '';
            $tags = isset($body['tags']) && is_string($body['tags']) ? trim($body['tags']) : '';

            $post = $this->blogService->createPost(
                $user->getId(),
                $title,
                $content,
                $tags !== '' ? $tags : null,
                isset($body['published']),
            );
            return $response->withHeader('Location', '/blog/' . $post->getSlug())->withStatus(302);
        }

        $vars = array_merge($this->baseVars(), ['post' => null]);
        $response->getBody()->write($this->twig->render('blog/form.html.twig', $vars));
        return $response;
    }

    /**
     * @param array<string, string> $args
     */
    public function edit(Request $request, Response $response, array $args): Response
    {
        $post = $this->blogService->getPost($args['slug']);
        if ($post === null) {
            return $response->withStatus(404);
        }

        if ($request->getMethod() === 'POST') {
            $body = $request->getParsedBody();
            $body = is_array($body) ? $body : [];
            $title = isset($body['title']) && is_string($body['title']) ? trim($body['title']) : '';
            $content = isset($body['content']) && is_string($body['content']) ? trim($body['content']) : '';
            $tags = isset($body['tags']) && is_string($body['tags']) ? trim($body['tags']) : '';

            $updated = $this->blogService->updatePost(
                $args['slug'],
                $title,
                $content,
                $tags !== '' ? $tags : null,
                isset($body['published']),
            );
            $slug = $updated ? $updated->getSlug() : $args['slug'];
            return $response->withHeader('Location', '/blog/' . $slug)->withStatus(302);
        }

        $vars = array_merge($this->baseVars(), ['post' => $post]);
        $response->getBody()->write($this->twig->render('blog/form.html.twig', $vars));
        return $response;
    }

    /**
     * @param array<string, string> $args
     */
    public function upload(Request $request, Response $response, array $args): Response
    {
        $file = ($request->getUploadedFiles())['image'] ?? null;

        if (!$file instanceof UploadedFileInterface || $file->getError() !== UPLOAD_ERR_OK) {
            $response->getBody()->write(json_encode(['error' => 'Upload failed'], JSON_THROW_ON_ERROR));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $allowedExts  = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($file->getClientFilename() ?? '', PATHINFO_EXTENSION));

        if (!in_array($file->getClientMediaType(), $allowedMimes, true) || !in_array($ext, $allowedExts, true)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid file type'], JSON_THROW_ON_ERROR));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $filename  = date('Ymd') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $uploadDir = dirname(__DIR__, 2) . '/public/assets/uploads/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $file->moveTo($uploadDir . $filename);

        $response->getBody()->write(json_encode(['url' => '/assets/uploads/' . $filename], JSON_THROW_ON_ERROR));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * @param array<string, string> $args
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $this->blogService->deletePost($args['slug']);
        $response->getBody()->write(json_encode(['success' => true], JSON_THROW_ON_ERROR));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
