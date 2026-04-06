<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\AuthTokenService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

class AuthController extends AbstractController
{
    public function __construct(
        private Environment $twig,
        private UserRepository $userRepository,
        private AuthTokenService $authTokenService,
    ) {}

    public function loginPage(Request $request, Response $response, array $args): Response
    {
        $response->getBody()->write(
            $this->twig->render('auth/login.html.twig', $this->baseVars())
        );
        return $response;
    }

    public function login(Request $request, Response $response, array $args): Response
    {
        $body = (array) $request->getParsedBody();
        $username = trim($body['username'] ?? '');
        $password = $body['password'] ?? '';

        $user = $this->userRepository->findByUsername($username);

        if ($user === null || !password_verify($password, $user->getPasswordHash())) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => 'Invalid credentials']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        $token = $this->authTokenService->generateToken($user->getId());
        $response->getBody()->write(json_encode(['success' => true, 'token' => $token]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function logout(Request $request, Response $response, array $args): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');
        if (str_starts_with($authHeader, 'Bearer ')) {
            $this->authTokenService->revokeToken(substr($authHeader, 7));
        }
        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
