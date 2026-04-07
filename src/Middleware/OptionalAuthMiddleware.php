<?php

namespace App\Middleware;

use App\Repository\UserRepository;
use App\Service\AuthTokenService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class OptionalAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthTokenService $authTokenService,
        private UserRepository $userRepository,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authHeader = $request->getHeaderLine('Authorization');
        $rawToken = str_starts_with($authHeader, 'Bearer ')
            ? substr($authHeader, 7)
            : ($request->getCookieParams()['nqh_token'] ?? null);

        if ($rawToken !== null) {
            $userId = $this->authTokenService->validateToken($rawToken);

            if ($userId !== null) {
                $user = $this->userRepository->findById($userId);
                if ($user !== null) {
                    $request = $request->withAttribute('user', $user);
                }
            }
        }

        return $handler->handle($request);
    }
}
