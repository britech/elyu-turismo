<?php

use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

return function (App $app) {
    $container = $app->getContainer();
    $renderer = $container->renderer;
    $logger = $container->logger;

    $app->get('/', function(Request $request, Response $response, array $args) use ($container) {
        return $container->renderer->render($response, 'home.phtml', $args);
    });

    $app->get('/tags', function(Request $request, Response $response, array $args) use ($container) {
        $result = $container->tagService->getTags();
        $tags = array();
        foreach($result as $tag) {
            $tags = array_merge($tags, array(array('tag' => $tag['name'])));
        }
        return $container->renderer->render($response, 'tags.phtml', array(
            'tags' => count($tags) == 0 ? '[]' : json_encode($tags)
        ));
    })->setName('tag-list');
    
    $app->post('/api/tag/add', function(Request $request, Response $response, array $args) use ($container, $logger) {
        list('tag' => $tag) = $request->getParsedBody();
        try {
            $container->tagService->insertTag($tag);
            return $response->withJson(['message' => "{$tag} has been added to available tags"], 200);
        } catch (\PDOException $ex) {
            $logger->error($ex);
            return $response->withJson(['message' => "Something went wrong, {$tag} has not been added."], 500);
        }
    });

    $app->delete('/api/tag/{tag}', function(Request $request, Response $response, array $args) use ($container, $logger) {
        list('tag' => $tag) = $args;
        try {
            $container->tagService->deleteTagByName($tag);
            return $response->withJson(['message' => "{$tag} has been deleted"], 200);
        } catch(\PDOException $ex) {
            $logger->error($ex);
            return $response->withJson(['message' => "Something went wrong, {$tag} has not been deleted"], 500);
        }
    });

    $app->get('/classifications', function(Request $request, Response $response, array $args) use ($container) {
        $result = $container->classificationService->getClassifications();
        $classifications = array();
        foreach($result as $tag) {
            $classifications = array_merge($classifications, array(array('tag' => $tag['name'])));
        }
        return $container->renderer->render($response, 'classifications.phtml', array(
            'classifications' => count($classifications) == 0 ? '[]' : json_encode($classifications)
        ));
    });

    $app->post('/api/classification/add', function(Request $request, Response $response, array $args) use ($container, $logger) {
        list('classification' => $classification) = $request->getParsedBody();
        try {
            $container->classificationService->insertClassification($classification);
            return $response->withJson(['message' => "{$classification} has been added to available tourist place classifications"], 200);
        } catch (\PDOException $ex) {
            $logger->error($ex);
            return $response->withJson(['message' => "Something went wrong, {$classification} has not been added."], 500);
        }
    });

    $app->delete('/api/classification/{name}', function(Request $request, Response $response, array $args) use ($container, $logger) {
        list('name' => $name) = $args;
        try {
            $container->classificationService->deleteClassificationByName($name);
            return $response->withJson(['message' => "{$name} has been deleted"], 200);
        } catch(\PDOException $ex) {
            $logger->error($ex);
            return $response->withJson(['message' => "Something went wrong, {$name} has not been deleted"], 500);
        }
    });

    $app->get('/poi', function(Request $request, Response $response, array $args) use ($renderer) {
        return $renderer->render($response, 'poi/index.phtml', $args);
    });

    $app->get('/poi/add', function(Request $request, Response $response, array $args) use($renderer) {
        return $renderer->render($response, 'poi/create.phtml', $args);
    });
};
