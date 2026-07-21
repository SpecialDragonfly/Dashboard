<?php

namespace App\Controller;

use App\Service\DividendService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

class ChartsController extends AbstractController
{
    public function __construct(
        private Environment $twig,
        private DividendService $dividendService,
    ) {}

    /**
     * @param array<string, mixed> $args
     */
    public function index(Request $request, Response $response, array $args): Response
    {
        $response->getBody()->write(
            $this->twig->render('charts/index.html.twig', $this->baseVars())
        );
        return $response;
    }

    /**
     * @param array<string, mixed> $args
     */
    public function growth(Request $request, Response $response, array $args): Response
    {
        $json = json_encode($this->dividendService->getPortfolioGrowth());
        if ($json === false) {
            $json = '[]';
        }
        $response->getBody()->write($json);
        return $response->withHeader('Content-Type', 'application/json');
    }
}
