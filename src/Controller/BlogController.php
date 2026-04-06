<?php

namespace App\Controller;

use App\Service\BlogService;
use League\CommonMark\CommonMarkConverter;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
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

    public function index(Request $request, Response $response, array $args): Response
    {
        $posts = $this->blogService->getPublishedPosts();
        $vars = array_merge($this->baseVars(), ['posts' => $posts]);
        $response->getBody()->write($this->twig->render('blog/index.html.twig', $vars));
        return $response;
    }

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

    public function create(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');

        if ($request->getMethod() === 'POST') {
            $body = (array) $request->getParsedBody();
            $post = $this->blogService->createPost(
                $user->getId(),
                trim($body['title'] ?? ''),
                trim($body['content'] ?? ''),
                trim($body['tags'] ?? '') ?: null,
                isset($body['published']),
            );
            return $response->withHeader('Location', '/blog/' . $post->getSlug())->withStatus(302);
        }

        $vars = array_merge($this->baseVars(), ['post' => null]);
        $response->getBody()->write($this->twig->render('blog/form.html.twig', $vars));
        return $response;
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $post = $this->blogService->getPost($args['slug']);
        if ($post === null) {
            return $response->withStatus(404);
        }

        if ($request->getMethod() === 'POST') {
            $body = (array) $request->getParsedBody();
            $updated = $this->blogService->updatePost(
                $args['slug'],
                trim($body['title'] ?? ''),
                trim($body['content'] ?? ''),
                trim($body['tags'] ?? '') ?: null,
                isset($body['published']),
            );
            $slug = $updated ? $updated->getSlug() : $args['slug'];
            return $response->withHeader('Location', '/blog/' . $slug)->withStatus(302);
        }

        $vars = array_merge($this->baseVars(), ['post' => $post]);
        $response->getBody()->write($this->twig->render('blog/form.html.twig', $vars));
        return $response;
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $this->blogService->deletePost($args['slug']);
        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
