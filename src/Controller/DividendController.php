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

    /**
     * @param array<string, mixed> $args
     */
    public function index(Request $request, Response $response, array $args): Response
    {
        $response->getBody()->write(
            $this->twig->render('dividends/index.html.twig', $this->baseVars())
        );
        return $response;
    }

    /**
     * @param array<string, mixed> $args
     */
    public function portfolio(Request $request, Response $response, array $args): Response
    {
        $response->getBody()->write(json_encode($this->dividendService->getPortfolio(), JSON_THROW_ON_ERROR));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * @param array<string, mixed> $args
     */
    public function addStock(Request $request, Response $response, array $args): Response
    {
        $parsedBody = $request->getParsedBody();
        $body = is_array($parsedBody) ? $parsedBody : [];

        $symbol = '';
        if (isset($body['symbol']) && is_string($body['symbol'])) {
            $symbol = strtoupper(preg_replace('/[^A-Za-z0-9.\-_]/', '', $body['symbol']) ?? '');
        }

        $name = '';
        if (isset($body['name']) && is_string($body['name'])) {
            $name = preg_replace('/[^A-Za-z0-9 &\'().,\-_]/', '', $body['name']) ?? '';
        }

        $qty = 0.0;
        if (isset($body['quantity']) && is_scalar($body['quantity'])) {
            $qty = (float) $body['quantity'];
        }

        $price = 0.0;
        if (isset($body['price']) && is_scalar($body['price'])) {
            $price = (float) $body['price'];
        }

        if ($symbol === '' || $name === '' || $qty <= 0 || $price <= 0) {
            $response->getBody()->write(json_encode(['error' => 'Invalid input'], JSON_THROW_ON_ERROR));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $result = $this->dividendService->addStock($symbol, $name, $qty, $price);
        $response->getBody()->write(json_encode($result, JSON_THROW_ON_ERROR));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * @param array<string, mixed> $args
     */
    public function deleteStock(Request $request, Response $response, array $args): Response
    {
        $symbolArg = $args['symbol'] ?? '';
        $symbol = '';
        if (is_string($symbolArg)) {
            $symbol = preg_replace('/[^A-Za-z0-9.\-_]/', '', $symbolArg) ?? '';
        }
        if ($symbol === '') {
            return $response->withStatus(400);
        }
        $this->dividendService->deleteStock($symbol);
        return $response->withStatus(204);
    }

    /**
     * @param array<string, mixed> $args
     */
    public function updateStock(Request $request, Response $response, array $args): Response
    {
        $parsedBody = $request->getParsedBody();
        $body = is_array($parsedBody) ? $parsedBody : [];

        $symbol = '';
        if (isset($body['symbol']) && is_string($body['symbol'])) {
            $symbol = preg_replace('/[^A-Za-z0-9.\-_]/', '', $body['symbol']) ?? '';
        }

        $exDiv = '';
        if (isset($body['exDiv']) && is_string($body['exDiv'])) {
            $exDiv = trim($body['exDiv']);
        }

        $dividend = '';
        if (isset($body['dividend']) && is_string($body['dividend'])) {
            $dividend = trim($body['dividend']);
        }

        if ($symbol === '') {
            return $response->withStatus(400);
        }

        $this->dividendService->updateStock($symbol, $exDiv, $dividend);
        return $response->withStatus(204);
    }

    /**
     * @param array<string, mixed> $args
     */
    public function prices(Request $request, Response $response, array $args): Response
    {
        $rawSymbols = $request->getQueryParams()['symbols'] ?? '';
        $raw = is_string($rawSymbols) ? $rawSymbols : '';

        $symbols = array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            static fn(string $s): bool => preg_match('/^[A-Za-z0-9.\-_]+$/', $s) === 1
        ));

        $response->getBody()->write(json_encode($this->dividendService->getPrices($symbols), JSON_THROW_ON_ERROR));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * @param array<string, mixed> $args
     */
    public function upcoming(Request $request, Response $response, array $args): Response
    {
        $response->getBody()->write(json_encode(
            $this->dividendService->getUpcomingDividends(),
            JSON_THROW_ON_ERROR
        ));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * @param array<string, mixed> $args
     */
    public function addPayment(Request $request, Response $response, array $args): Response
    {
        $parsedBody = $request->getParsedBody();
        $body = is_array($parsedBody) ? $parsedBody : [];

        $symbolId = 0;
        if (isset($body['symbolId']) && is_scalar($body['symbolId'])) {
            $symbolId = (int) $body['symbolId'];
        }

        $date = '';
        if (isset($body['date']) && is_string($body['date'])) {
            $date = trim($body['date']);
        }

        $amount = 0;
        if (isset($body['amount']) && is_scalar($body['amount'])) {
            $amount = (int) $body['amount'];
        }

        if ($symbolId <= 0 || $date === '' || $amount <= 0) {
            $response->getBody()->write(json_encode(['error' => 'Invalid input'], JSON_THROW_ON_ERROR));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $this->dividendService->addPayment($symbolId, $date, $amount);
        return $response->withStatus(204);
    }
}
