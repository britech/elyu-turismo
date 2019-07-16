<?php

use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

return function (App $app) {
    $container = $app->getContainer();

    $app->get('/', function(Request $request, Response $response, array $args) use ($container) {
        return $container->renderer->render($response, 'home.phtml', $args);
    });

    $app->get('/tags', function(Request $request, Response $response, array $args) use ($container) {
        return $container->renderer->render($response, 'tags/index.phtml', array(
            'tags' => $container->tagService->getTags()
        ));
    })->setName('tag-list');

    $app->post('/tag/add', function(Request $request, Response $response, array $args) use ($container) {
        list('tag' => $tag) = $request->getParsedBody();
        $container->tagService->insertTag($tag);
        return $response->withRedirect($this->router->pathFor('tag-list'));
    });
};
