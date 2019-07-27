<?php

use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;
use gov\pglu\tourism\util\ApplicationUtils;
use gov\pglu\tourism\util\ApplicationConstants;
use gov\pglu\tourism\util\FileUtils;

return function (App $app) {
    $container = $app->getContainer();
    $logger = $container->logger;

    $app->get('/cpanel', function(Request $request, Response $response, array $args) use ($container) {
        return $container->cpanelRenderer->render($response, 'index.phtml', $args);
    });

    $app->get('/cpanel/tags', function(Request $request, Response $response, array $args) use ($container) {
        $result = $container->tagService->getTags();

        return $container->cpanelRenderer->render($response, 'tags.phtml', array(
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

    $app->get('/cpanel/classifications', function(Request $request, Response $response, array $args) use ($container) {
        $result = $container->classificationService->getClassifications();
        $classifications = array();
        foreach($result as $tag) {
            $classifications = array_merge($classifications, array(array('tag' => $tag['name'])));
        }
        return $container->cpanelRenderer->render($response, 'classifications.phtml', array(
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

    $app->get('/cpanel/poi', function(Request $request, Response $response, array $args) use ($container) {
        $flash = $container->flash;
        try {
            $result = $container->poiManagementService->listPoi();
            $args = array_merge($args, ['poiList' => $result]);
        } catch (\PDOException $ex) {
            $container->logger->error($ex);
        }
        $args = array_merge($args, [ApplicationConstants::NOTIFICATION_KEY => $flash->getFirstMessage(ApplicationConstants::NOTIFICATION_KEY)]);
        return $container->cpanelRenderer->render($response, 'poi/index.phtml', $args);
    })->setName('poi-list');

    $app->get('/cpanel/poi/add', function(Request $request, Response $response, array $args) use($container) {
        $classifications = $container->classificationService->getClassifications();
        $tags = $container->tagService->getTags();

        $args = array_merge($args, [ 'circuits' => ApplicationUtils::TOURISM_CIRCUITS, 
            'classifications' => ApplicationUtils::convertArrayToAutocompleteData($classifications, 'name'),
            'classificationsBackend' => json_encode($classifications),
            'tags' => ApplicationUtils::convertArrayToAutocompleteData($tags, 'name'),
            'tagsBackend' => json_encode($tags) ]);
        return $container->cpanelRenderer->render($response, 'poi/create.phtml', $args);
    });

    $app->post('/api/poi/add', function(Request $request, Response $response, array $args) use ($logger, $container) {
        $body = $request->getParsedBody();
        $inputs = array_filter($body, function($key) {
            return strcasecmp('topicTags', $key) !=0 && strcasecmp('classifications', $key) != 0;
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

    $app->get('/cpanel/poi/{id}', function(Request $request, Response $response, array $args) use ($container) {
        list('id' => $id) = $args;
        $renderer = $container->poiRenderer;
        $flash = $container->flash;
        try {
            $result = $container->poiManagementService->getPoi($id);
            if (count($result) == 0) {
                $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Place of interest not found!');
                return $response->withRedirect($container->router->pathFor('poi-list'));
            } else {
                list('town' => $town) = $result;
                $args = array_merge($args, [ 'id' => $id, 'result' => $result ]);
                return $renderer->render($response, 'poi/info.phtml', $args);
            }
        } catch (\PDOException $ex) {
            $container->logger->error($ex);
            $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Something went wrong while loading Tourist Location info. Try again later');
            return $response->withRedirect($container->router->pathFor('poi-list'));
        }
    })->setName('poi-info');

    $app->get('/cpanel/poi/{id}/edit', function(Request $request, Response $response, array $args) use ($container) {
        list('id' => $id) = $args;
        $renderer = $container->poiRenderer;
        $flash = $container->flash;
        try {
            $classifications = $container->classificationService->getClassifications();
            $tags = $container->tagService->getTags();
            $result = $container->poiManagementService->getPoi($id);

            if (count($result) == 0) {
                $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Place of interest not found!');
                return $response->withRedirect($container->router->pathFor('poi-list'));
            } else {
                list('classifications' => $assignedClassifications, 'topicTags' => $assignedTags) = $result;

                $args = array_merge($args, [ 'id' => $id, 
                    'result' => $result, 
                    'circuits' => ApplicationUtils::TOURISM_CIRCUITS ,
                    'classifications' => ApplicationUtils::convertArrayToAutocompleteData($classifications, 'name'),
                    'classificationsBackend' => json_encode($classifications),
                    'assignedClassifications' => ApplicationUtils::convertArrayToTagData($assignedClassifications, 'name'),
                    'assignedClassificationsBackend' => ApplicationUtils::convertTagResultToBackendReferences($assignedClassifications, 'id'),
                    'tags' => ApplicationUtils::convertArrayToAutocompleteData($tags, 'name'),
                    'tagsBackend' => json_encode($tags),
                    'assignedTags' => ApplicationUtils::convertArrayToTagData($assignedTags, 'name'),
                    'assignedTagsBackend' => ApplicationUtils::convertTagResultToBackendReferences($assignedTags, 'id'), ]
                );
                return $renderer->render($response, 'poi/edit_poi.phtml', $args);
            }
        } catch (\PDOException $ex) {
            $container->logger->error($ex);
            $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Something went wrong while loading Tourist Location info. Try again later');
            return $response->withRedirect($container->router->pathFor('poi-list'));
        }
    });

    $app->post('/cpanel/poi/{id}/edit', function(Request $request, Response $response, array $args) use ($container) {
        list('id' => $id) = $args;

        $body = $request->getParsedBody();
        $inputs = array_filter($body, function($key) {
            return strcasecmp('topicTags', $key) !=0 && strcasecmp('classifications', $key) != 0 
                && strcasecmp('commuterguidewysiwyg', $key) != 0
                && strcasecmp('commuterguide', $key) != 0
                && strcasecmp('descriptionwysiwyg', $key)  != 0
                && strcasecmp('description', $key)  != 0
                && strcasecmp('imagebackend', $key) != 0;
        }, ARRAY_FILTER_USE_KEY);

        list('topicTags' => $rawTags, 'classifications' => $rawClassifications, 
            'descriptionwysiwyg' => $rawDescriptionWysiwyg, 
            'description' => $rawDescription,
            'commuterguidewysiwyg' => $rawCommuterWysiWyg,
            'commuterguide' => $rawCommuterGuide,
            'imagebackend' => $imageBackend) = $body;

        list('image' => $image) = $request->getUploadedFiles();
        $filename = FileUtils::uploadFile($image, ['id' => $id, 'directory' => $container->settings['uploadPath']]);

        $inputs = array_merge($inputs, [ 'topicTags' => json_decode($rawTags),
            'classifications' => json_decode($rawClassifications),
            'descriptionwysiwyg' => strlen(trim($rawDescriptionWysiwyg)) == 0 ? null : json_encode(json_decode($rawDescriptionWysiwyg, true)),
            'description' => strlen(trim($rawDescription)) == 0 ? null : $rawDescription,
            'commuterguidewysiwyg' => strlen(trim($rawCommuterWysiWyg)) == 0 ? null : json_encode(json_decode($rawCommuterWysiWyg, true)),
            'commuterguide' => strlen(trim($rawCommuterGuide)) == 0 ? null : $rawCommuterGuide,
            'id' => $id,
            'imagename' => is_null($filename) ? $imageBackend : $filename
        ]);

        $container->logger->debug("Update tourist location => ".json_encode($inputs));
        try {
            $container->poiManagementService->updatePoi($inputs);
            return $response->withRedirect($container->router->pathFor('poi-info', [
                'id' => $id
            ]));
        } catch (\PDOException $ex) {
            $container->logger->error($ex);
            $container->flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Something went wrong. Try again later');
            return $response->withRedirect($container->router->pathFor('poi-list'));
        }
    });

    $app->get('/cpanel/poi/{id}/schedules', function(Request $request, Response $response, array $args) use ($container) {
        list('id' => $id) = $args;
        $flash = $container->flash;
        $service = $container->poiManagementService;
        try {
            $result = $service->getPoi($id);
            list('name' => $name) = $result;
            $args = array_merge($args, [
                'name' => $name,
                ApplicationConstants::NOTIFICATION_KEY => $flash->getFirstMessage(ApplicationConstants::NOTIFICATION_KEY)
            ]);
            return $container->poiRenderer->render($response, 'poi/schedule.phtml', $args);
        } catch (\PDOException $ex) {
            $container->logger->error($ex);
            $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Something went wrong while loading Tourist Location info. Try again later');
            return $response->withRedirect($container->router->pathFor('poi-list'));
        }
    })->setName('schedules');

    $app->post('/cpanel/schedule/add', function(Request $request, Response $response, array $args) use ($container) {
        $body = $request->getParsedBody();
        list('open247' => $open247, 'day' => $days, 'date' => $date, 'openingTime' => $openingTime, 'closingTime' => $closingTime, 'id' => $id) = $body;
        $flash = $container->flash;

        $hasNoDate = is_null($open247) && (count($days) == 0 || strlen(trim($date)) == 0);  
        $hasNoTime = strlen(trim($openingTime)) == 0 && strlen(trim($closingTime)) == 0;
        if ($hasNoDate && $hasNoTime) {
            $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Schedule not added due to insufficient data');
            return $response->withRedirect($container->router->pathFor('schedules', [
                'id' => $id
            ]));
        }

        $map = ['id' => $id];
        if (array_key_exists('open247', $body)) {
            $map = array_merge($map, ['open247' => $open247 == 'on' ? 1 : 0]);
        } else {
            $map = array_merge($map, [
                'openingTime' => date('H:i:s', strtotime($openingTime)),
                'closingTime' => date('H:i:s', strtotime($closingTime)),
                'days' => count($days) == 0 ? null : $days,
                'date' => strlen(trim($date)) == 0 ? null : date('Y-m-d', strtotime($date))
            ]);
        }

        try {
            $container->poiManagementService->addSchedule($map, $id);
            $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Schedule successfully added.');
            return $response->withRedirect($container->router->pathFor('schedules', [
                'id' => $id
            ]));
        } catch (\Exception $ex) {
            $container->logger->error($ex);
            $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Something went wrong while loading Tourist Location info. Try again later');
            return $response->withRedirect($container->router->pathFor('poi-list'));
        }
    });

    $app->get('/cpanel/poi/{id}/admin', function(Request $request, Response $response, array $args) use ($container) {
        list('id' => $id) = $args;
        $flash = $container->flash;
        try {
            $result = $container->poiManagementService->getPoi($id);
            list('arEnabled' => $arEnabled, 'displayable' => $displayable, 'name' => $name) = $result;

            $args = array_merge($args, [
                'arEnabled' => $arEnabled, 
                'displayable' => $displayable,
                'name' => $name
            ]);

            return $container->poiRenderer->render($response, 'poi/admin.phtml', $args);
        } catch (\PDOException $ex) {
            $container->logger->error($ex);
            $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Something went wrong while loading Tourist Location info. Try again later');
            return $response->withRedirect($container->router->pathFor('poi-list'));
        }
    })->setName('poi-admin');

    $app->patch('/api/poi/{id}/toggle/{field}/{indicator}', function(Request $request, Response $response, array $args) use ($container) {
        list('id' => $id, 'field' => $field, 'indicator' => $indicator) = $args;

        $service = $container->poiManagementService;
        try {
            if ($field == 'display') {
                $service->toggleDisplay($id, $indicator);
                return $response->withJson([
                    'message' => $indicator ? "Place of interest is now available." : "Place of interest will not be displayed in the website."
                ]);
            } else if ($field == 'ar') {
                $service->toggleAr($id, $indicator);
                return $response->withJson([
                    'message' => $indicator ? "AR has been activated." : "AR is now disabled."
                ]);
            } else {
                return $response->withStatus(400);
            }
        } catch (\PDOException $ex) {
            $container->logger->error($ex);
            return $response->withJson([
                'message' => 'Something went wrong. Try again later.'
            ], 500);
        }
    });

    $app->get('/', function(Request $request, Response $response, array $args) use ($container) {
        return $container->webRenderer->render($response, 'index.phtml', $args);
    });

    $app->get('/explore', function(Request $request, Response $response, array $args) use ($container) {
        return $container->webRenderer->render($response, 'explore.phtml', $args);
    });

    $app->get('/open-data', function(Request $request, Response $response, array $args) use ($container) {
        return $container->openDataRenderer->render($response, 'index.phtml', $args);
    });

    $app->get('/open-data/rest', function(Request $request, Response $response, array $args) use ($container) {
        return $container->openDataRenderer->render($response, 'rest.phtml', $args);
    });
    
    $app->get('/open-data/csv', function(Request $request, Response $response, array $args) use ($container) {
        $poiList = $container->poiManagementService->listPoi();

        $args = array_merge($args, ['reportTypes' => ApplicationConstants::REPORT_TYPES, 
            'poiList' => ApplicationUtils::convertArrayToAutocompleteData($poiList, 'name'),
            'poiListBackend' => json_encode($poiList) ]);
        return $container->openDataRenderer->render($response, 'csv.phtml', $args);
    });

    $app->post('/open-data/csv', function(Request $request, Response $response, array $args) use ($container) {
        $logger = $container->logger;

        $criteriaMap = array_filter($request->getParsedBody(), function($value) {
            return strlen($value) != 0;
        }, ARRAY_FILTER_USE_BOTH);

        if (array_key_exists('places', $criteriaMap)) {
            $criteriaMap = array_merge($criteriaMap, ['places' => json_decode($criteriaMap['places'])]);
        }

        $logger->debug("Criteria Map: ".json_encode($criteriaMap));
        list('reportType' => $reportType) = $request->getParsedBody();
        $csvGenerationSetting = $container->csvGenerationSetting;
        $filename = "{$csvGenerationSetting->destination}/" . time() . "_{$reportType}.csv";
        try {
            $rows = [];
            if ($reportType == 'visitorCount') {
                $rows = array_merge($rows, $container->csvOpenDataService->countVisitors($criteriaMap));
            } else {
                $rows = array_merge($rows, $container->csvOpenDataService->computeAverageVisitorRating($criteriaMap));
            }
            $logger->debug("Result: ".json_encode($rows));

            $file = fopen($filename, 'w');
            foreach($rows as $row) {
                fputcsv($file, $row);
            }
            fclose($file);

            $baseName = basename($filename);
            return $response->withRedirect("{$csvGenerationSetting->httpPath}/{$baseName}");
        } catch (\PDOException $ex) {
            $logger->error($ex);
            return $response->withStatus(500, $ex->getMessage());
        }
    });

    $app->get('/open-data/csv/poi', function(Request $request, Response $response, array $args) use ($container) {
        $logger = $container->logger;
        $csvGenerationSetting = $container->csvGenerationSetting;
        
        $filename = "{$csvGenerationSetting->destination}/" . time() . "_poi-list.csv";
        try {
            $rows = $container->csvOpenDataService->listPoi();
            $file = fopen($filename, 'w');
            foreach($rows as $row) {
                fputcsv($file, $row);
            }
            fclose($file);

            $baseName = basename($filename);
            return $response->withRedirect("{$csvGenerationSetting->httpPath}/{$baseName}");
        } catch (\Exception $ex) {
            $logger->error($ex);
            return $response->withStatus(500, $ex->getMessage());
        }
    });
};
