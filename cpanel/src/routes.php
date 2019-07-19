<?php

use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;
use gov\pglu\tourism\util\ApplicationUtils;

return function (App $app) {
    $container = $app->getContainer();
    $renderer = $container->renderer;
    $logger = $container->logger;

    $app->get('/', function(Request $request, Response $response, array $args) use ($container) {
        return $container->renderer->render($response, 'home.phtml', $args);
    });

    $app->get('/tags', function(Request $request, Response $response, array $args) use ($container) {
        $result = $container->tagService->getTags();

        return $container->renderer->render($response, 'tags.phtml', array(
            'tags' => ApplicationUtils::convertArrayToTagData($result, 'name'),
            'tagsBackend' => json_encode($result)
        ));
    })->setName('tag-list');
    
    $app->post('/api/tag/add', function(Request $request, Response $response, array $args) use ($container, $logger) {
        list('tag' => $tag) = $request->getParsedBody();
        $tagService = $container->tagService;
        try {
            $tagService->insertTag($tag);
            return $response->withJson(['message' => "{$tag} has been added to available tags", 'tags' => json_encode($tagService->getTags())], 200);
        } catch (\PDOException $ex) {
            $logger->error($ex);
            return $response->withJson(['message' => "Something went wrong, {$tag} has not been added."], 500);
        }
    });

    $app->delete('/api/tag/{id}', function(Request $request, Response $response, array $args) use ($container, $logger) {
        list('id' => $id) = $args;
        list('tag' => $tag) = $request->getParsedBody();
        $tagService = $container->tagService;
        try {
            $tagService->deleteTag($id);
            return $response->withJson(['message' => "{$tag} has been deleted", 'tags' => json_encode($tagService->getTags())], 200);
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
    })->setName('poi-list');

    $app->get('/poi/add', function(Request $request, Response $response, array $args) use($renderer, $container) {
        $classifications = $container->classificationService->getClassifications();
        $tags = $container->tagService->getTags();

        $args = array_merge($args, [ 'circuits' => ApplicationUtils::TOURISM_CIRCUITS, 
            'classifications' => ApplicationUtils::convertArrayToAutocompleteData($classifications, 'name'),
            'classificationsBackend' => json_encode($classifications),
            'tags' => ApplicationUtils::convertArrayToAutocompleteData($tags, 'name'),
            'tagsBackend' => json_encode($tags) ]);
        return $renderer->render($response, 'poi/create.phtml', $args);
    });

    $app->post('/api/poi/add', function(Request $request, Response $response, array $args) use ($logger, $container) {
        $body = $request->getParsedBody();
        $inputs = array_filter($body, function($key) {
            return strcasecmp('topicTags', $key) !=0 && strcasecmp('classifications', $key);
        }, ARRAY_FILTER_USE_KEY);

        list('topicTags' => $rawTags, 'classifications' => $rawClassifications, 'name' => $name) = $body;
        $inputs = array_merge($inputs, array('topicTags' => json_decode($rawTags)));
        $inputs = array_merge($inputs, array('classifications' => json_decode($rawClassifications)));

        $logger->debug("Inserting the entry => ".json_encode($inputs));
        
        try {
            $id = $container->poiManagementService->createPoi($inputs);
            return $response->withJson(array('message' => "Tourist Location {$name} has been added", 'id' => $id), 200);
        } catch (\PDOException $ex) {
            $logger->error($ex);
            return $response->withJson(array('message' => "Tourist Location {$name} cannot be added. Try again later"), 500);
        }
    });

        try {
            $container->poiManagementService->createPoi($inputs);
        } catch(\PDOException $ex) {
            $container->logger->error($ex);
        }

        return $response->withRedirect($container->router->pathFor('poi-list'));
    });
};
