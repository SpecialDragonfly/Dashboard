<?php

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$builder = new ContainerBuilder();
$builder->addDefinitions(require dirname(__DIR__) . '/config/container.php');
try {
    $container = $builder->build();
} catch (Exception $e) {
    error_log(print_r($e, true));
}

AppFactory::setContainer($container);
$app = AppFactory::create();

(require dirname(__DIR__) . '/config/middleware.php')($app);
(require dirname(__DIR__) . '/config/routes.php')($app);

$app->run();
