<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return function (ContainerConfigurator $config) {
    $services = $config->services();

    // Default behaviour
    $services->defaults()
        ->autowire(false)
        ->autoconfigure(false)
	->public(false);

    $db = service('pdo.connection');

    $services->set(App\Controller\HomeController::class)
        ->args(['$twig' => service('twig')])
	->public();

    $services->set('pdo.connection', PDO::class)
        ->args([
            'sqlite:%kernel.project_dir%/var/data.db'
        ])
        ->call('setAttribute', [PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION])
	->call('setAttribute', [PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC]);

    $services->set(App\Controller\DbTestController::class)
        ->args([
            '$db' => service('pdo.connection')
        ])
	->public();

    $services->set(App\Repository\BlogRepository::class)
        ->args([
            '$db' => service('pdo.connection')
	]);

    $services->set(App\Service\BlogService::class)
        ->args([
            '$blogRepository' => service(App\Repository\BlogRepository::class)
	]);

    $services->set(App\Controller\BlogController::class)
        ->args([
            '$twig' => service('twig'),
            '$blogService' => service(App\Service\BlogService::class)
        ])
        ->public();
};
