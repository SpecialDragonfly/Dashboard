<?php

namespace App\Controller;

use App\Service\RipperService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

class RipperController extends AbstractController
{
    public function __construct(
        private Environment $twig,
        private RipperService $ripperService,
    ) {}

    /**
     * @param array<string, string> $args
     */
    public function index(Request $request, Response $response, array $args): Response
    {
        $response->getBody()->write(
            $this->twig->render('ripper/index.html.twig', $this->baseVars())
        );
        return $response;
    }

    /**
     * @param array<string, string> $args
     */
    public function rip(Request $request, Response $response, array $args): Response
    {
        $body = (array) $request->getParsedBody();
        $url  = isset($body['url']) && is_string($body['url']) ? trim($body['url']) : '';

        if ($url === '') {
            $json = json_encode(['status' => 'error', 'message' => 'No URL provided']);
            $response->getBody()->write($json === false ? '' : $json);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $result = $this->ripperService->rip($url);
        $json   = json_encode($result);
        $response->getBody()->write($json === false ? '' : $json);
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * @param array<string, string> $args
     */
    public function history(Request $request, Response $response, array $args): Response
    {
        $json = json_encode($this->ripperService->getHistory());
        $response->getBody()->write($json === false ? '' : $json);
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * @param array<string, string> $args
     */
    public function download(Request $request, Response $response, array $args): Response
    {
        $videoId = $args['videoId'] ?? '';
        $path    = $this->ripperService->getDownloadPath($videoId);

        if ($path === null || !file_exists($path)) {
            return $response->withStatus(404);
        }

        $filename = $videoId . '.mp3';
        $response = $response
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Content-Length', (string) filesize($path));

        $body = $response->getBody();
        $fp   = fopen($path, 'rb');
        if ($fp === false) {
            return $response->withStatus(500);
        }
        while (!feof($fp)) {
            $chunk = fread($fp, 8192);
            if ($chunk !== false) {
                $body->write($chunk);
            }
        }
        fclose($fp);

        return $response;
    }

    /**
     * @param array<string, string> $args
     */
    public function stream(Request $request, Response $response, array $args): Response
    {
        $videoId = $args['videoId'] ?? '';
        $path    = $this->ripperService->getDownloadPath($videoId);

        if ($path === null || !file_exists($path)) {
            return $response->withStatus(404);
        }

        $response = $response
            ->withHeader('Content-Type', 'audio/mpeg')
            ->withHeader('Content-Length', (string) filesize($path));

        $body = $response->getBody();
        $fp   = fopen($path, 'rb');
        if ($fp === false) {
            return $response->withStatus(500);
        }
        while (!feof($fp)) {
            $chunk = fread($fp, 8192);
            if ($chunk !== false) {
                $body->write($chunk);
            }
        }
        fclose($fp);

        return $response;
    }
}
