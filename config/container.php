<?php

use App\Controller\AuthController;
use App\Controller\BlogController;
use App\Controller\DashboardController;
use App\Middleware\OptionalAuthMiddleware;
use App\Middleware\TokenAuthMiddleware;
use App\Repository\BlogRepository;
use App\Repository\UserRepository;
use App\Service\AuthTokenService;
use App\Service\BlogService;
use Psr\Container\ContainerInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

return [
    // -- Twig --
    Environment::class => function () {
        $loader = new FilesystemLoader(dirname(__DIR__) . '/templates');
        return new Environment($loader, [
            'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
            'cache' => ($_ENV['APP_ENV'] ?? 'dev') === 'prod'
                ? dirname(__DIR__) . '/var/cache/twig'
                : false,
        ]);
    },

    // -- Database --
    // Currently SQLite for local development. Will switch to MySQL before production.
    // When switching: replace DSN and add DB_HOST/DB_NAME/DB_USER/DB_PASS to .env.
    PDO::class => function () {
        $pdo = new PDO('sqlite:' . dirname(__DIR__) . '/var/data.db');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        return $pdo;
    },

    // -- Repositories --
    UserRepository::class => fn(ContainerInterface $c) => new UserRepository($c->get(PDO::class)),
    BlogRepository::class => fn(ContainerInterface $c) => new BlogRepository($c->get(PDO::class)),

    // -- Services --
    AuthTokenService::class => fn(ContainerInterface $c) => new AuthTokenService($c->get(PDO::class)),
    BlogService::class      => fn(ContainerInterface $c) => new BlogService($c->get(BlogRepository::class)),

    // -- Middleware --
    TokenAuthMiddleware::class => fn(ContainerInterface $c) => new TokenAuthMiddleware(
        $c->get(AuthTokenService::class),
        $c->get(UserRepository::class),
    ),
    OptionalAuthMiddleware::class => fn(ContainerInterface $c) => new OptionalAuthMiddleware(
        $c->get(AuthTokenService::class),
        $c->get(UserRepository::class),
    ),

    // -- Controllers --
    DashboardController::class => fn(ContainerInterface $c) => new DashboardController(
        $c->get(Environment::class),
        $c->get(BlogService::class),
    ),
    AuthController::class => fn(ContainerInterface $c) => new AuthController(
        $c->get(Environment::class),
        $c->get(UserRepository::class),
        $c->get(AuthTokenService::class),
    ),
    BlogController::class => fn(ContainerInterface $c) => new BlogController(
        $c->get(Environment::class),
        $c->get(BlogService::class),
    ),
];
