<?php

use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

return function (App $app) {
    $container = $app->getContainer();

    $app->add(function(Request $request, Response $response, $next) use ($container) {
        $container->logger->debug("Request Body: {$request->getBody()}");
        $response = $next($request, $response);
        return $response;
    });
};
