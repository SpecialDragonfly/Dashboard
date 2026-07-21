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

    /**
     * @param array<string, mixed> $args
     */
    public function loginPage(Request $request, Response $response, array $args): Response
    {
        $response->getBody()->write(
            $this->twig->render('auth/login.html.twig', $this->baseVars())
        );
        return $response;
    }

    /**
     * @param array<string, mixed> $args
     */
    public function login(Request $request, Response $response, array $args): Response
    {
        $body = $request->getParsedBody();

        $username = '';
        $password = '';
        if (is_array($body)) {
            if (isset($body['username']) && is_string($body['username'])) {
                $username = trim($body['username']);
            }
            if (isset($body['password']) && is_string($body['password'])) {
                $password = $body['password'];
            }
        }

        $user = $this->userRepository->findByUsername($username);

        if ($user === null || !password_verify($password, $user->getPasswordHash())) {
            $response->getBody()->write(json_encode(['success' => false, 'error' => 'Invalid credentials'], JSON_THROW_ON_ERROR));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        $token = $this->authTokenService->generateToken($user->getId());
        $response->getBody()->write(json_encode(['success' => true, 'token' => $token], JSON_THROW_ON_ERROR));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Set-Cookie', 'nqh_token=' . $token . '; Path=/; HttpOnly; SameSite=Lax');
    }

    /**
     * @param array<string, mixed> $args
     */
    public function logout(Request $request, Response $response, array $args): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');
        if (str_starts_with($authHeader, 'Bearer ')) {
            $this->authTokenService->revokeToken(substr($authHeader, 7));
        }
        $response->getBody()->write(json_encode(['success' => true], JSON_THROW_ON_ERROR));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Set-Cookie', 'nqh_token=; Path=/; Expires=Thu, 01 Jan 1970 00:00:00 GMT; HttpOnly; SameSite=Lax');
    }
}
