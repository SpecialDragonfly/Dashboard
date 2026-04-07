<?php

use App\Controller\AuthController;
use App\Controller\BlogController;
use App\Controller\DashboardController;
use App\Middleware\OptionalAuthMiddleware;
use App\Middleware\TokenAuthMiddleware;
use Slim\App;

return function (App $app) {
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
};
