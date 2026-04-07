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

    public function index(Request $request, Response $response, array $args): Response
    {
        $response->getBody()->write(
            $this->twig->render('ripper/index.html.twig', $this->baseVars())
        );
        return $response;
    }

    public function rip(Request $request, Response $response, array $args): Response
    {
        $body = (array) $request->getParsedBody();
        $url  = trim($body['url'] ?? '');

        if ($url === '') {
            $response->getBody()->write(json_encode(['status' => 'error', 'message' => 'No URL provided']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $result = $this->ripperService->rip($url);
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function history(Request $request, Response $response, array $args): Response
    {
        $response->getBody()->write(json_encode($this->ripperService->getHistory()));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function download(Request $request, Response $response, array $args): Response
    {
        $videoId = $args['videoId'];
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
        while (!feof($fp)) {
            $body->write(fread($fp, 8192));
        }
        fclose($fp);

        return $response;
    }

    public function stream(Request $request, Response $response, array $args): Response
    {
        $videoId = $args['videoId'];
        $path    = $this->ripperService->getDownloadPath($videoId);

        if ($path === null || !file_exists($path)) {
            return $response->withStatus(404);
        }

        $response = $response
            ->withHeader('Content-Type', 'audio/mpeg')
            ->withHeader('Content-Length', (string) filesize($path));

        $body = $response->getBody();
        $fp   = fopen($path, 'rb');
        while (!feof($fp)) {
            $body->write(fread($fp, 8192));
        }
        fclose($fp);

        return $response;
    }
}
