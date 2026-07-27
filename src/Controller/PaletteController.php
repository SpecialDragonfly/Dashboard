<?php

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

class PaletteController extends AbstractController
{
    public function __construct(
        private Environment $twig,
    ) {}

    /**
     * @param array<string, string> $args
     */
    public function index(Request $request, Response $response, array $args): Response
    {
        $response->getBody()->write(
            $this->twig->render('palette/preview.html.twig', $this->baseVars())
        );
        return $response;
    }
}
