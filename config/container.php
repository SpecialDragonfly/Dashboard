<?php

use App\Controller\AuthController;
use App\Controller\BlogController;
use App\Controller\ChartsController;
use App\Controller\DashboardController;
use App\Controller\DividendController;
use App\Controller\EmpireController;
use App\Controller\PaletteController;
use App\Controller\RipperController;
use App\Middleware\OptionalAuthMiddleware;
use App\Middleware\TokenAuthMiddleware;
use App\Repository\BlogRepository;
use App\Repository\DividendRepository;
use App\Repository\RipperRepository;
use App\Repository\UserRepository;
use App\Service\AuthTokenService;
use App\Service\BlogService;
use App\Service\DividendCalendarInterface;
use App\Service\DividendService;
use App\Service\RipperService;
use App\Service\SnowballAnalyticsService;
use App\Service\YahooFinanceService;
use Psr\Container\ContainerInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Fetch a service from the container with its concrete type verified, so
 * PHPStan (and callers) see the real type instead of ContainerInterface::get()'s
 * generic `mixed` return.
 *
 * @template T of object
 * @param class-string<T> $class
 * @return T
 */
function containerGet(ContainerInterface $c, string $class): object
{
    $service = $c->get($class);
    if (!$service instanceof $class) {
        throw new RuntimeException(sprintf(
            'Container entry "%s" did not resolve to an instance of that class.',
            $class,
        ));
    }
    return $service;
}

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
    // DB_CONNECTION=mysql + DB_HOST/DB_NAME/DB_USER/DB_PASS/DB_PORT in .env switches
    // to MySQL (production, or local MariaDB via docker-compose). Same switch as
    // phinx.php, which the migrations run against. Defaults to SQLite (local dev).
    PDO::class => function () {
        // Falls back to getenv() since PHP CLI's default variables_order (no "E")
        // leaves $_ENV empty for shell-exported vars not loaded via .env.
        $env = function (string $key, string $default): string {
            $envValue = $_ENV[$key] ?? null;
            if (is_string($envValue)) {
                return $envValue;
            }
            $getenvValue = getenv($key);
            return $getenvValue !== false && $getenvValue !== '' ? $getenvValue : $default;
        };
        if ($env('DB_CONNECTION', 'sqlite') === 'mysql') {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $env('DB_HOST', '127.0.0.1'),
                $env('DB_PORT', '3306'),
                $env('DB_NAME', 'notquitehuman'),
            );
            $pdo = new PDO($dsn, $env('DB_USER', ''), $env('DB_PASS', ''));
        } else {
            $pdo = new PDO('sqlite:' . dirname(__DIR__) . '/var/data.db');
        }
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        return $pdo;
    },

    // -- Repositories --
    UserRepository::class     => fn(ContainerInterface $c) => new UserRepository(containerGet($c, PDO::class)),
    BlogRepository::class     => fn(ContainerInterface $c) => new BlogRepository(containerGet($c, PDO::class)),
    RipperRepository::class   => fn(ContainerInterface $c) => new RipperRepository(containerGet($c, PDO::class)),
    DividendRepository::class => fn(ContainerInterface $c) => new DividendRepository(containerGet($c, PDO::class)),

    // -- Services --
    AuthTokenService::class => fn(ContainerInterface $c) => new AuthTokenService(containerGet($c, PDO::class)),
    BlogService::class      => fn(ContainerInterface $c) => new BlogService(containerGet($c, BlogRepository::class)),
    YahooFinanceService::class => fn(ContainerInterface $c) => new YahooFinanceService(
        dirname(__DIR__) . '/var/yahoo-cache/',
        containerGet($c, DividendRepository::class),
    ),
    SnowballAnalyticsService::class => fn(ContainerInterface $c) => new SnowballAnalyticsService(
        dirname(__DIR__) . '/var/snowball-cache/',
    ),
    DividendCalendarInterface::class => fn(ContainerInterface $c) => containerGet($c, SnowballAnalyticsService::class),
    DividendService::class  => fn(ContainerInterface $c) => new DividendService(
        containerGet($c, DividendRepository::class),
        containerGet($c, YahooFinanceService::class),
        containerGet($c, DividendCalendarInterface::class),
        dirname(__DIR__) . '/src/Dividends/freetrade-shares.csv',
    ),
    RipperService::class    => fn(ContainerInterface $c) => new RipperService(
        containerGet($c, RipperRepository::class),
        dirname(__DIR__) . '/var/ripper/',
        dirname(__DIR__) . '/public/assets/ripper/thumbnails/',
    ),

    // -- Middleware --
    TokenAuthMiddleware::class => fn(ContainerInterface $c) => new TokenAuthMiddleware(
        containerGet($c, AuthTokenService::class),
        containerGet($c, UserRepository::class),
    ),
    OptionalAuthMiddleware::class => fn(ContainerInterface $c) => new OptionalAuthMiddleware(
        containerGet($c, AuthTokenService::class),
        containerGet($c, UserRepository::class),
    ),

    // -- Controllers --
    DashboardController::class => fn(ContainerInterface $c) => new DashboardController(
        containerGet($c, Environment::class),
        containerGet($c, BlogService::class),
    ),
    AuthController::class => fn(ContainerInterface $c) => new AuthController(
        containerGet($c, Environment::class),
        containerGet($c, UserRepository::class),
        containerGet($c, AuthTokenService::class),
    ),
    BlogController::class => fn(ContainerInterface $c) => new BlogController(
        containerGet($c, Environment::class),
        containerGet($c, BlogService::class),
    ),
    RipperController::class => fn(ContainerInterface $c) => new RipperController(
        containerGet($c, Environment::class),
        containerGet($c, RipperService::class),
    ),
    DividendController::class => fn(ContainerInterface $c) => new DividendController(
        containerGet($c, Environment::class),
        containerGet($c, DividendService::class),
    ),
    ChartsController::class => fn(ContainerInterface $c) => new ChartsController(
        containerGet($c, Environment::class),
        containerGet($c, DividendService::class),
    ),
    EmpireController::class => fn(ContainerInterface $c) => new EmpireController(
        containerGet($c, Environment::class),
    ),
    PaletteController::class => fn(ContainerInterface $c) => new PaletteController(
        containerGet($c, Environment::class),
    ),
];
