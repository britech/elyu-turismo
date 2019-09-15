<?php

use Slim\App;
use Slim\Views\PhpRenderer;
use Slim\Flash\Messages as FlashMessage;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Monolog\Handler\StreamHandler;
use gov\pglu\tourism\dao\TagDaoImpl;
use gov\pglu\tourism\dao\ClassificationDaoImpl;
use gov\pglu\tourism\dao\PoiManagementDaoImpl;
use gov\pglu\tourism\dao\OpenDataDaoImpl;
use gov\pglu\tourism\service\OpenDataServiceCsvImpl;
use gov\pglu\tourism\dao\TownManagementDaoImpl;
use gov\pglu\tourism\util\ApplicationConstants;
use gov\pglu\tourism\service\FileUploadServiceDefaultImpl;
use gov\pglu\tourism\service\FileUploadServiceImgurImpl;
use gov\pglu\tourism\dao\VisitorDaoImpl;

return function (App $app) {
    $container = $app->getContainer();

    // view renderer
    $container['renderer'] = function ($c) {
        list('renderer' => $rendererSettings) = $c->settings;
        list('template_path' => $templatePath) = $rendererSettings;
        
        return new PhpRenderer("{$templatePath}/");
    };

    $container['poiRenderer'] = function($c) {
        list('renderer' => $rendererSettings) = $c->settings;
        list('template_path' => $templatePath) = $rendererSettings;

        $renderer = new PhpRenderer("{$templatePath}/cpanel/");
        $renderer->setLayout('layout_poi.phtml');
        return $renderer;
    };

    $container['townRenderer'] = function($c) {
        list('renderer' => $rendererSettings) = $c->settings;
        list('template_path' => $templatePath) = $rendererSettings;

        $renderer = new PhpRenderer("{$templatePath}/cpanel/");
        $renderer->setLayout('layout_town.phtml');
        return $renderer;
    };

    $container['cpanelRenderer'] = function($c) {
        list('renderer' => $rendererSettings) = $c->settings;
        list('template_path' => $templatePath) = $rendererSettings;

        $renderer = new PhpRenderer("{$templatePath}/cpanel/");
        $renderer->setLayout('layout.phtml');
        return $renderer;
    };

    $container['webRenderer'] = function($c) {
        list('renderer' => $rendererSettings) = $c->settings;
        list('template_path' => $templatePath) = $rendererSettings;

        return new PhpRenderer("{$templatePath}/web/");
    };

    $container['exploreRenderer'] = function($c) {
        list('renderer' => $rendererSettings) = $c->settings;
        list('template_path' => $templatePath) = $rendererSettings;
        
        $renderer = new PhpRenderer("{$templatePath}/web/");
        $renderer->setLayout('layout.phtml');

        return $renderer;
    };

    $container['openDataRenderer'] = function($c) {
        list('renderer' => $rendererSettings) = $c->settings;
        list('template_path' => $templatePath) = $rendererSettings;

        $renderer = new PhpRenderer("{$templatePath}/web/open-data");
        $renderer->setLayout('layout.phtml');
        return $renderer; 
    };

    // flash messages component
    $container['flash'] = function($c) {
        return new FlashMessage();
    };

    // monolog
    $container['logger'] = function ($c) {
        $logger = new Logger('tourism-app');
        $logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
        return $logger;
    };

    // database
    $container['database'] = function($c) {
        list('DATABASE_HOST' => $host, 'DATABASE_PORT' => $port, 'DATABASE_TARGET' => $database, 
            'DATABASE_USER' => $username, 'DATABASE_PASSWORD' => $password) = getenv();

        $resolvedPort = is_null($port) ? '3306' : $port;
        $dsn = "mysql:host={$host};port={$resolvedPort};dbname={$database}";
        
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

    $container['openDataDao'] = function($c) {
        $dao = new OpenDataDaoImpl($c->database);
        $dao->logger = $c->logger;
        return $dao;
    };

    $container['csvOpenDataService'] = function($c) {
        return new OpenDataServiceCsvImpl($c->poiManagementService, $c->openDataDao);
    };

    $container['csvGenerationSetting'] = function($c) {
        list('csvGeneration' => $settings) = $c->settings;
        return (object) $settings;
    };

    $container['townManagementService'] = function($c) {
        return new TownManagementDaoImpl($c->database);
    };

    $container['fileUploadService'] = function($c) {
        $useLocalFilesystem = getenv('USE_LOCAL_FILESYSTEM') == ApplicationConstants::INDICATOR_NUMERIC_TRUE;
        if ($useLocalFilesystem) {
            $service = new FileUploadServiceDefaultImpl();
            $service->logger = $c->logger;
            return $service;
        } else {
            $service = new FileUploadServiceImgurImpl();
            $service->logger = $c->logger;
            return $service;
        }
    };

    $container['visitorService'] = function($c) {
        return new VisitorDaoImpl($c->database);
    };
};
