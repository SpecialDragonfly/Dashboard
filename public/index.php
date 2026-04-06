<?php

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$builder = new ContainerBuilder();
$builder->addDefinitions(require dirname(__DIR__) . '/config/container.php');
$container = $builder->build();

AppFactory::setContainer($container);
$app = AppFactory::create();

(require dirname(__DIR__) . '/config/middleware.php')($app);
(require dirname(__DIR__) . '/config/routes.php')($app);

$app->run();
