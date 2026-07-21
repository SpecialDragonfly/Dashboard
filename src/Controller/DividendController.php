<?php

namespace App\Controller;

use App\Service\DividendService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

class DividendController extends AbstractController
{
    public function __construct(
        private Environment $twig,
        private DividendService $dividendService,
    ) {}

    public function index(Request $request, Response $response, array $args): Response
    {
        $response->getBody()->write(
            $this->twig->render('dividends/index.html.twig', $this->baseVars())
        );
        return $response;
    }

    public function portfolio(Request $request, Response $response, array $args): Response
    {
        $response->getBody()->write(json_encode($this->dividendService->getPortfolio()));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function addStock(Request $request, Response $response, array $args): Response
    {
        $body = (array) $request->getParsedBody();
        $symbol = strtoupper(preg_replace('/[^A-Za-z0-9.\-_]/', '', $body['symbol'] ?? ''));
        $name = preg_replace('/[^A-Za-z0-9 &\'().,\-_]/', '', $body['name'] ?? '');
        $qty     = (float) ($body['quantity'] ?? 0);
        $price   = (float) ($body['price']    ?? 0);

        if ($symbol === '' || $name === '' || $qty <= 0 || $price <= 0) {
            $response->getBody()->write(json_encode(['error' => 'Invalid input']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $result = $this->dividendService->addStock($symbol, $name, $qty, $price);
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function deleteStock(Request $request, Response $response, array $args): Response
    {
        $symbol = preg_replace('/[^A-Za-z0-9.\-_]/', '', $args['symbol'] ?? '');
        if ($symbol === '') {
            return $response->withStatus(400);
        }
        $this->dividendService->deleteStock($symbol);
        return $response->withStatus(204);
    }

    public function updateStock(Request $request, Response $response, array $args): Response
    {
        $body     = (array) $request->getParsedBody();
        $symbol   = preg_replace('/[^A-Za-z0-9.\-_]/', '', $body['symbol']   ?? '');
        $exDiv    = trim($body['exDiv']    ?? '');
        $dividend = trim($body['dividend'] ?? '');

        if ($symbol === '') {
            return $response->withStatus(400);
        }

        $this->dividendService->updateStock($symbol, $exDiv, $dividend);
        return $response->withStatus(204);
    }

    public function prices(Request $request, Response $response, array $args): Response
    {
        $raw     = $request->getQueryParams()['symbols'] ?? '';
        $symbols = array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            fn($s) => preg_match('/^[A-Za-z0-9.\-_]+$/', $s)
        ));

        $response->getBody()->write(json_encode($this->dividendService->getPrices($symbols)));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function upcoming(Request $request, Response $response, array $args): Response
    {
        $response->getBody()->write(json_encode(
            $this->dividendService->getUpcomingDividends()
        ));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function uploadCookies(Request $request, Response $response, array $args): Response
    {
        $files = $request->getUploadedFiles();
        $file  = $files['cookies'] ?? null;

        if ($file === null || $file->getError() !== UPLOAD_ERR_OK) {
            $response->getBody()->write(json_encode(['error' => 'No cookie file uploaded']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $content = (string) $file->getStream();
        $saved   = $this->dividendService->saveYahooCookies($content);

        if (!$saved) {
            $response->getBody()->write(json_encode(['error' => 'Cookie file did not look like a valid cookie string']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function addPayment(Request $request, Response $response, array $args): Response
    {
        $body     = (array) $request->getParsedBody();
        $symbolId = (int) ($body['symbolId'] ?? 0);
        $date     = trim($body['date']   ?? '');
        $amount   = (int) ($body['amount'] ?? 0);

        if ($symbolId <= 0 || $date === '' || $amount <= 0) {
            $response->getBody()->write(json_encode(['error' => 'Invalid input']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $this->dividendService->addPayment($symbolId, $date, $amount);
        return $response->withStatus(204);
    }
}
