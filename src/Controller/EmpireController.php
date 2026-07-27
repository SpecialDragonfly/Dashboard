<?php

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

class EmpireController extends AbstractController
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    /**
     * @param array<string, string> $args
     */
    public function index(Request $request, Response $response, array $args): Response
    {
        $vars = $this->baseVars();
        $response->getBody()->write($this->twig->render('empire/index.html.twig', $vars));
        return $response;
    }

    /**
     * @param array<string, string> $args
     */
    public function print(Request $request, Response $response, array $args): Response
    {
        $table = $args['table'] ?? '';
        $headings = ['ring' => 'Ring prices', 'rounded' => 'Rounded prices'];

        if (!isset($headings[$table])) {
            return $response->withStatus(404);
        }

        $vars = ['mode' => $table, 'heading' => $headings[$table]];
        $response->getBody()->write($this->twig->render('empire/print.html.twig', $vars));
        return $response;
    }
}
