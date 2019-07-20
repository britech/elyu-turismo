<?php

use Slim\App;
use Slim\Views\PhpRenderer;
use Slim\Flash\Messages as FlashMessage;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Monolog\Handler\RotatingFileHandler;
use gov\pglu\tourism\dao\TagDaoImpl;
use gov\pglu\tourism\dao\ClassificationDaoImpl;
use gov\pglu\tourism\dao\PoiManagementDaoImpl;

return function (App $app) {
    $container = $app->getContainer();

    // view renderer
    $container['renderer'] = function ($c) {
        list('renderer' => $rendererSettings) = $c->settings;
        list('template_path' => $templatePath) = $rendererSettings;

        $renderer = new PhpRenderer($templatePath);
        $renderer->setLayout('layout.phtml');
        return $renderer;
    };

    $container['poiRenderer'] = function($c) {
        list('renderer' => $rendererSettings) = $c->settings;
        list('template_path' => $templatePath) = $rendererSettings;

        $renderer = new PhpRenderer($templatePath);
        $renderer->setLayout('layout_poi.phtml');
        return $renderer;
    };

    // flash messages component
    $container['flash'] = function($c) {
        return new FlashMessage();
    };

    // monolog
    $container['logger'] = function ($c) {
        $settings = $c->get('settings')['logger'];
        list('logger' => $loggerSettings) = $c->settings;
        list('name' => $appName, 'path' => $path, 'level' => $logLevel) = $loggerSettings;
        
        $logger = new Logger($appName);
        $logger->pushProcessor(new UidProcessor());
        $logger->pushHandler(new RotatingFileHandler($path, $logLevel));
        return $logger;
    };

    // database
    $container['database'] = function($c) {
        list('database' => $databaseSettings) = $c->settings;
        list('dsn' => $dsn, 'username' => $username, 'password' => $password) = $databaseSettings;

        $connection = new PDO($dsn, $username, $password);
        $connection->setAttribute(PDO::ATTR_PERSISTENT, true);
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $connection;
    };

    // Tourism Tags Layer
    $container['tagService'] = function($c) {
        return new TagDaoImpl($c->database);
    };

    // Tourist Place Classification Service Layer
    $container['classificationService'] = function($c) {
        return new ClassificationDaoImpl($c->database);
    };

    // POI Management Service
    $container['poiManagementService'] = function($c) {
        $service = new PoiManagementDaoImpl($c->database);
        $service->logger = $c->logger;
        return $service;
    };
};
