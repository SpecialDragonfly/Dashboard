<?php

use App\Controller\AuthController;
use App\Controller\BlogController;
use App\Controller\ChartsController;
use App\Controller\DashboardController;
use App\Controller\DividendController;
use App\Controller\RipperController;
use App\Middleware\OptionalAuthMiddleware;
use App\Middleware\TokenAuthMiddleware;
use Slim\App;

return function (App $app) {
    // -- Housekeeping --
    $app->get('/favicon.ico', function ($req, $res) { return $res->withStatus(204); });

    // -- Public --
    $app->get('/', [DashboardController::class, 'index'])->add(OptionalAuthMiddleware::class);
    $app->get('/login',  [AuthController::class, 'loginPage']);
    $app->post('/login', [AuthController::class, 'login']);
    $app->post('/logout',[AuthController::class, 'logout']);

    // -- Blog (public read) --
    $app->get('/blog',        [BlogController::class, 'index']);

    // -- Blog write (auth required) — /blog/new must come before /blog/{slug} --
    $app->post('/blog/upload',       [BlogController::class, 'upload'])->add(TokenAuthMiddleware::class);
    $app->get('/blog/new',          [BlogController::class, 'create'])->add(TokenAuthMiddleware::class);
    $app->post('/blog/new',         [BlogController::class, 'create'])->add(TokenAuthMiddleware::class);
    $app->get('/blog/{slug}/edit',  [BlogController::class, 'edit'])->add(TokenAuthMiddleware::class);
    $app->post('/blog/{slug}/edit', [BlogController::class, 'edit'])->add(TokenAuthMiddleware::class);
    $app->delete('/blog/{slug}',    [BlogController::class, 'delete'])->add(TokenAuthMiddleware::class);
    $app->get('/blog/{slug}',       [BlogController::class, 'show']);

    // -- Dividends (auth required) --
    $app->get('/dividends',                         [DividendController::class, 'index'])->add(TokenAuthMiddleware::class);
    $app->get('/dividends/portfolio',               [DividendController::class, 'portfolio'])->add(TokenAuthMiddleware::class);
    $app->post('/dividends/portfolio',              [DividendController::class, 'addStock'])->add(TokenAuthMiddleware::class);
    $app->put('/dividends/portfolio/{symbol}',      [DividendController::class, 'updateStock'])->add(TokenAuthMiddleware::class);
    $app->delete('/dividends/portfolio/{symbol}',   [DividendController::class, 'deleteStock'])->add(TokenAuthMiddleware::class);
    $app->get('/dividends/prices',                  [DividendController::class, 'prices'])->add(TokenAuthMiddleware::class);
    $app->get('/dividends/upcoming',                [DividendController::class, 'upcoming'])->add(TokenAuthMiddleware::class);
    $app->post('/dividends/payment',                [DividendController::class, 'addPayment'])->add(TokenAuthMiddleware::class);

    // -- Charts (auth required) --
    $app->get('/charts',        [ChartsController::class, 'index'])->add(TokenAuthMiddleware::class);
    $app->get('/charts/growth', [ChartsController::class, 'growth'])->add(TokenAuthMiddleware::class);

    // -- Ripper (auth required) --
    $app->get('/ripper',                      [RipperController::class, 'index'])->add(TokenAuthMiddleware::class);
    $app->post('/rip',                        [RipperController::class, 'rip'])->add(TokenAuthMiddleware::class);
    $app->get('/history',                     [RipperController::class, 'history'])->add(TokenAuthMiddleware::class);
    $app->get('/ripper/download/{videoId}',   [RipperController::class, 'download'])->add(TokenAuthMiddleware::class);
    $app->get('/ripper/stream/{videoId}',     [RipperController::class, 'stream'])->add(TokenAuthMiddleware::class);
};
