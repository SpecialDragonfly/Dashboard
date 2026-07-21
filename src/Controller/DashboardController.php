<?php

namespace App\Controller;

use App\Service\BlogService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

class DashboardController extends AbstractController
{
    public function __construct(
        private Environment $twig,
        private BlogService $blogService,
    ) {}

    /**
     * @param array<string, mixed> $args
     */
    public function index(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $vars = $this->baseVars();
        if ($user != null) {
            $vars['user'] = $user;
        }
        $vars['posts'] = $user !== null
            ? $this->blogService->getAllPosts()
            : $this->blogService->getPublishedPosts();
        $response->getBody()->write(
            $this->twig->render('dashboard/index.html.twig', $vars)
        );
        return $response;
    }
}
