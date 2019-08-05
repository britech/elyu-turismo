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
                list('imageName' => $imageName) = $result;
                $imageSrc = intval(getenv('USE_LOCAL_FILESYSTEM')) == ApplicationConstants::INDICATOR_NUMERIC_TRUE ? "/uploads/{$imageName}" : $imageName;

                $args = array_merge($args, [
                    'id' => $id, 
                    'result' => $result, 
                    'imageSrc' => $imageSrc
                ]);
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
            'imagebackend' => $imageBackend, 'name' => $name) = $body;

        list('image' => $image) = $request->getUploadedFiles();
        $filename = $container->fileUploadService->uploadFile([
            'file' => $image,
            'opts' => ['id' => $id],
            'name' => $name
        ]);

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
                ApplicationConstants::NOTIFICATION_KEY => $flash->getFirstMessage(ApplicationConstants::NOTIFICATION_KEY),
                'schedules' => $service->listSchedules($id)
            ]);
            return $container->poiRenderer->render($response, 'schedule/index.phtml', $args);
        } catch (\PDOException $ex) {
            $container->logger->error($ex);
            $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Something went wrong while loading Tourist Location info. Try again later');
            return $response->withRedirect($container->router->pathFor('poi-list'));
        }
    })->setName('schedules');

    $app->get('/cpanel/poi/{id}/schedule', function(Request $request, Response $response, array $args) use ($container) {
        list('id' => $id) = $args;
        $flash = $container->flash;
        $args = array_merge($args, [
            'id' => $id,
            ApplicationConstants::NOTIFICATION_KEY => $flash->getFirstMessage(ApplicationConstants::NOTIFICATION_KEY)
        ]);
        return $container->poiRenderer->render($response, 'schedule/create.phtml', $args);
    })->setName('create-schedule');

    $app->post('/cpanel/schedule/add', function(Request $request, Response $response, array $args) use ($container) {
        $body = $request->getParsedBody();
        list('open24h' => $openAllDay, 
            'open7d' => $openEveryday, 
            'day' => $days, 
            'date' => $date, 
            'openingTime' => $openingTime, 
            'closingTime' => $closingTime, 
            'id' => $id,
            'notes' => $notes) = $body;

        $flash = $container->flash;

        $hasNoDate = is_null($openEveryday) && (count($days) == 0 || strlen(trim($date)) == 0);  
        $hasNoTime = is_null($openAllDay) && strlen(trim($openingTime)) == 0 && strlen(trim($closingTime)) == 0;
        if ($hasNoDate && $hasNoTime) {
            $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Schedule not added due to insufficient data');
            return $response->withRedirect($container->router->pathFor('create-schedule', [
                'id' => $id
            ]));
        }

        try {
            $container->poiManagementService->addSchedule([
                'id' => $id,
                'open24h' => $openAllDay == 'on' ? ApplicationConstants::INDICATOR_NUMERIC_TRUE : ApplicationConstants::INDICATOR_NUMERIC_FALSE,
                'open7d' => $openEveryday == 'on' ? ApplicationConstants::INDICATOR_NUMERIC_TRUE : ApplicationConstants::INDICATOR_NUMERIC_FALSE,
                'days' => count($days) == 0 ? null : $days,
                'date' => strlen(trim($date)) == 0 || is_null($date) ? null : date('Y-m-d', strtotime($date)),
                'openingTime' => strlen(trim($openingTime)) == 0 || is_null($openingTime) ? null : date('H:i:s', strtotime($openingTime)),
                'closingTime' => strlen(trim($closingTime)) == 0 || is_null($closingTime) ? null : date('H:i:s', strtotime($closingTime)),
                'notes' => $notes
            ], $id);
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

    $app->get('/api/schedule/{id}', function(Request $request, Response $response, array $args) use ($container) {
        list('id' => $id) = $args;
        try {
            $result = $container->poiManagementService->getSchedule($id);
            if (is_null($result)) {
                return $response->withJson(['message' => 'No schedule found'], 404);
            }

            list('open24h' => $openAllDay, 
                'open7d' => $openEveryday, 
                'id' => $id, 
                'day' => $day, 
                'date' => $specificDate, 
                'openingtime' => $openingTime,
                'closingtime' => $closingTime,
                'placeofinterest' => $poi) = $result;

            return $response->withJson([
                'id' => $id,
                'poi' => $poi,
                'day' => $openEveryday == ApplicationConstants::INDICATOR_NUMERIC_TRUE ? "Everyday" : (is_null($day) ? date('M j, Y', strtotime($specificDate)) : $day),
                'operatingHours' => $openAllDay == ApplicationConstants::INDICATOR_NUMERIC_TRUE ? "24 hours" : (date('h:i a', strtotime($openingTime)).' - '. date('h:i a', strtotime($closingTime)))
            ], 200);
        } catch (\PDOException $ex) {
            $container->logger->error($ex);
            return $response->withJson(['message' => $ex->getMessage()], 500);
        }
    });

    $app->delete('/api/schedule/{id}', function(Request $request, Response $response, array $args) use ($container) {
        list('id' => $schedule) = $args;
        list('poi' => $poi) = $request->getParsedBody();
        try {
            $container->poiManagementService->removeSchedule($schedule);
            return $response->withJson(['url' => $container->router->pathFor('schedules', ['id' => $poi])], 200);
        } catch (\PDOException $ex) {
            $container->logger->error($ex);
            return $response->withJson(['message' => $ex->getMessage()], 500);
        }
    });

    $app->patch('/api/schedule/{id}', function(Request $request, Response $response, array $args) use ($container) {
        list('id' => $id) = $args;
        list('enabled' => $enabled, 'poi' => $poi) = $request->getParsedBody();
        try {
            $status = $enabled == ApplicationConstants::INDICATOR_NUMERIC_TRUE ? "enabled" : "disabled";
            $container->poiManagementService->toggleSchedule($id, $enabled);
            return $response->withJson(['message' => "Schedule has been {$status}", 'poi' => $poi], 200);
        } catch (\PDOException $ex) {
            $container->logger->error($ex);
            return $response->withJson(['message' => 'Something went wrong. Try again later'], 500);
        }
    });

    $app->get('/cpanel/poi/{id}/fees', function(Request $request, Response $response, array $args) use ($container) {
        list('id' => $id) = $args;
        $flash = $container->flash;
        $service = $container->poiManagementService;
        try {
            $result = $service->getPoi($id);
            list('name' => $name) = $result;
            $args = array_merge($args, [
                'url' => '/cpanel/fee',
                'name' => $name,
                'poi' => $id,
                'fees' => $service->listFees($id),
                ApplicationConstants::NOTIFICATION_KEY => $flash->getFirstMessage(ApplicationConstants::NOTIFICATION_KEY)
            ]);
            return $container->poiRenderer->render($response, 'fees.phtml', $args);
        } catch (\PDOException $ex) {
            $container->logger->error($ex);
            $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Something went wrong while loading Tourist Location info. Try again later');
            return $response->withRedirect($container->router->pathFor('poi-list'));
        }
    })->setName('fees');

    $app->post('/cpanel/fee', function(Request $request, Response $response, array $args) use ($container) {
        list('poi' => $poi, 'description' => $description, 'freePrice' => $freePrice, 'amount' => $amount) = $request->getParsedBody();
        $flash = $container->flash;

        if (is_null($freePrice) && strlen(trim($amount)) == 0) {
            $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Fee not added due to insufficient data');
            return $response->withRedirect($container->router->pathFor('fees', ['id' => $poi]));
        }

        try {
            $container->poiManagementService->addFee([
                'description' => strlen(trim($description)) == 0 ? null : $description,
                'freePrice' => strcasecmp('on', $freePrice) == 0 ? ApplicationConstants::INDICATOR_NUMERIC_TRUE : ApplicationConstants::INDICATOR_NUMERIC_FALSE,
                'amount' => strlen(trim($amount)) == 0 ? null : $amount
            ], $poi);
            $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Fee successfully added');
            return $response->withRedirect($container->router->pathFor('fees', ['id' => $poi]));
        } catch (\PDOException $ex) {
            $container->logger->error($ex);
            $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Something went wrong while processing your request. Try again later');
            return $response->withRedirect($container->router->pathFor('poi-list'));
        }
    });

    $app->get('/cpanel/fee/{id}', function(Request $request, Response $response, array $args) use ($container) {
        list('id' => $id) = $args;
        $flash = $container->flash;
        $service = $container->poiManagementService;
        try {
            $fee = $service->getFee($id);
            list('placeofinterest' => $poi) = $fee;

            $placeOfInterest = $service->getPoi($poi);
            list('name' => $name) = $placeOfInterest;

            $args = array_merge($args, [
                'url' => "/cpanel/fee/{$id}",
                'name' => $name,
                'poi' => $poi,
                'fees' => $service->listFees($poi),
                'updateMode' => true,
                'fee' => $fee,
                'id' => $poi,
                ApplicationConstants::NOTIFICATION_KEY => $flash->getFirstMessage(ApplicationConstants::NOTIFICATION_KEY)
            ]);
            return $container->poiRenderer->render($response, 'fees.phtml', $args);
        } catch (\PDOException $ex) {
            $container->logger->error($ex);
            $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Something went wrong while processing your request. Try again later');
            return $response->withRedirect($container->router->pathFor('poi-list'));
        }
    });

    $app->post('/cpanel/fee/{id}', function(Request $request, Response $response, array $args) use ($container) {
        list('id' => $id) = $args;
        list('poi' => $poi, 'description' => $description, 'freePrice' => $freePrice, 'amount' => $amount, 'enabled' => $enabled) = $request->getParsedBody();
        $flash = $container->flash;
        $service = $container->poiManagementService;

        try {
            $container->poiManagementService->updateFee([
                'description' => strlen(trim($description)) == 0 ? null : $description,
                'freePrice' => strcasecmp('on', $freePrice) == 0 ? ApplicationConstants::INDICATOR_NUMERIC_TRUE : ApplicationConstants::INDICATOR_NUMERIC_FALSE,
                'amount' => strlen(trim($amount)) == 0 ? null : $amount,
                'enabled' => strcasecmp('on', $enabled) == 0 ? ApplicationConstants::INDICATOR_NUMERIC_TRUE : ApplicationConstants::INDICATOR_NUMERIC_FALSE
            ], $id);
            $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Fee successfully updated');
            return $response->withRedirect($container->router->pathFor('fees', ['id' => $poi]));
        } catch(\PDOException $ex) {
            $container->logger->error($ex);
            $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Something went wrong while processing your request. Try again later');
            return $response->withRedirect($container->router->pathFor('poi-list'));
        }
    });

    $app->get('/api/fee/{id}', function(Request $request, Response $response, array $args) use ($container) {
        list('id' => $id) = $args;
        try {
            $result = $container->poiManagementService->getFee($id);
            if (is_null($result)) {
                return $response->withJson(['message' => 'No fee found'], 404);
            }

            list('description' => $description, 
                'amount' => $amount, 
                'freePrice' => $freePrice) = $result;

            return $response->withJson([
                'id' => $id,
                'description' => is_null($description) ? "N/A" : $description,
                'amount' => $freePrice == ApplicationConstants::INDICATOR_NUMERIC_TRUE ? "Free Admission" : "PHP ".number_format($amount, 2)
            ], 200);
        } catch (\PDOException $ex) {
            $container->logger->error($ex);
            return $response->withJson(['message' => $ex->getMessage()], 500);
        }
    });

    $app->delete('/api/fee/{id}', function(Request $request, Response $response, array $args) use ($container) {
        list('id' => $fee) = $args;
        list('poi' => $poi) = $request->getParsedBody();
        try {
            $container->poiManagementService->removeFee($fee);
            return $response->withJson(['url' => $container->router->pathFor('fees', ['id' => $poi])], 200);
        } catch (\PDOException $ex) {
            $container->logger->error($ex);
            return $response->withJson(['message' => $ex->getMessage()], 500);
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

    $app->get('/cpanel/towns/{type}', function(Request $request, Response $response, array $args) use ($container) {
        list('type' => $type) = $args;
        $args = array_merge($args, [
            'tourismCircuits' => ApplicationUtils::TOURISM_CIRCUITS,
            'type' => $type
        ]);
        return $container->townRenderer->render($response, 'town/index.phtml', $args);
    })->setName('town-landing');

    $app->get('/cpanel/town/{town}/pov', function(Request $request, Response $response, array $args) use ($container) {
        list('town' => $rawTownInput) = $args;
        $town = ucwords(implode(' ', explode('_', $rawTownInput)));
        $flash = $container->flash;

        try {
            $args = array_merge($args, [
                'tourismCircuits' => ApplicationUtils::TOURISM_CIRCUITS,
                'places' => $container->townManagementService->listPoi($town),
                'town' => $town,
                'url' => "/cpanel/pov",
                'type' => 'pov',
                'townLink' => $rawTownInput,
                ApplicationConstants::NOTIFICATION_KEY => $flash->getFirstMessage(ApplicationConstants::NOTIFICATION_KEY),
            ]);
            return $container->townRenderer->render($response, 'town/poi.phtml', $args);
        } catch (\Exception $ex) {
            $container->logger->error($ex);
            $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Something went wrong while processing your request. Try again later');
            return $response->withRedirect($container->router->pathFor('town-landing', ['type' => $town]));
        }
    })->setName('pov-list');

    $app->post('/cpanel/pov', function(Request $request, Response $response, array $args) use ($container) {
        $body = $request->getParsedBody();
        list('town' => $rawTown, 'name' => $name, 'latitude' => $latitude, 'longitude' => $longitude) = $body;
        $town = strtolower(implode('_', explode(' ', $rawTown)));
        try {
            $container->townManagementService->addPoi([
                'name' => $name,
                'town' => $rawTown,
                'latitude' => $latitude,
                'longitude' => $longitude
            ]);
        } catch (\Exception $ex) {
            $container->logger->error($ex);
            $container->flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Something went wrong while processing your request. Try again later');
        }
        return $response->withRedirect($container->router->pathFor('pov-list', ['town' => $town]));
    });

    $app->get('/cpanel/pov/{id}', function(Request $request, Response $response, array $args) use ($container) {
        list('id' => $id) = $args;
        try {
            $poi = $container->townManagementService->getPoi($id);
            list('town' => $town) = $poi;

            $args = array_merge($args, [
                'tourismCircuits' => ApplicationUtils::TOURISM_CIRCUITS,
                'places' => $container->townManagementService->listPoi($town),
                'town' => $town,
                'url' => "/cpanel/pov/{$id}",
                'type' => 'pov',
                'poi' => $poi,
                'updateMode' => true,
                'townLink' => strtolower(implode('_', explode(' ', $town))),
            ]);
            return $container->townRenderer->render($response, 'town/poi.phtml', $args);
        } catch (\Exception $ex) {
            $container->logger->error($ex);
            return $response->withRedirect($container->router->pathFor('town-landing', ['type' => 'pov']));
        }
    });

    $app->post('/cpanel/pov/{id}', function(Request $request, Response $response, array $args) use ($container) {
        list('id' => $id) = $args;
        list('town' => $rawTown, 'name' => $name, 'latitude' => $latitude, 'longitude' => $longitude, 'enabled' => $enabled) = $request->getParsedBody();
        try {
            $container->townManagementService->updatePoi([
                'name' => $name,
                'town' => $rawTown,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'id' => $id,
                'enabled' => strcasecmp('on', $enabled) == 0 ? ApplicationConstants::INDICATOR_NUMERIC_TRUE : ApplicationConstants::INDICATOR_NUMERIC_FALSE
            ]);
        } catch (\Exception $ex) {
            $container->logger->error($ex);
            $container->flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Something went wrong while processing your request. Try again later');
        }
        $town = strtolower(implode('_', explode(' ', $rawTown)));
        return $response->withRedirect($container->router->pathFor('pov-list', ['town' => $town]));
    });

    $app->get('/api/pov/{id}', function(Request $request, Response $response, array $args) use ($container) {
        list('id' => $id) = $args;
        try {
            $pov = $container->townManagementService->getPoi($id);
            return $response->withJson($pov, 200);
        } catch (\Exception $ex) {
            $container->logger->error($ex);
            return $response->withStatus(500);
        }
    });

    $app->delete('/api/pov/{id}', function(Request $request, Response $response, array $args) use ($container) {
        list('id' => $id) = $args;
        try {
            $container->townManagementService->removePoi($id);
            return $response->withJson(['message' => 'Point of interest removed'], 200);
        } catch (\Exception $ex) {
            $container->logger->error($ex);
            $container->flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Something went wrong while processing your request. Try again later');
            return $response->withStatus(500);
        }
    });

    $app->get('/cpanel/town/{town}/products', function(Request $request, Response $response, array $args) use ($container) {
        list('town' => $rawTownInput) = $args;
        $town = ucwords(implode(' ', explode('_', $rawTownInput)));
        $flash = $container->flash;

        try {
            $args = array_merge($args, [
                'tourismCircuits' => ApplicationUtils::TOURISM_CIRCUITS,
                'products' => $container->townManagementService->listProducts($town),
                'town' => $town,
                'url' => "/cpanel/product",
                'type' => 'products',
                'townLink' => $rawTownInput,
                ApplicationConstants::NOTIFICATION_KEY => $flash->getFirstMessage(ApplicationConstants::NOTIFICATION_KEY),
            ]);
            return $container->townRenderer->render($response, 'town/product.phtml', $args);
        } catch (\Exception $ex) {
            $container->logger->error($ex);
            $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Something went wrong while processing your request. Try again later');
            return $response->withRedirect($container->router->pathFor('town-landing', ['type' => $town]));
        }
    })->setName('product-list');

    $app->post('/cpanel/product', function(Request $request, Response $response, array $args) use ($container) {
        $body = $request->getParsedBody();
        list('town' => $rawTown, 'name' => $name, 'arLink' => $arLink) = $body;
        $town = strtolower(implode('_', explode(' ', $rawTown)));
        try {
            $container->townManagementService->addProduct([
                'name' => $name,
                'town' => $rawTown,
                'arLink' => $arLink
            ]);
        } catch (\Exception $ex) {
            $container->logger->error($ex);
            $container->flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Something went wrong while processing your request. Try again later');
        }
        return $response->withRedirect($container->router->pathFor('product-list', ['town' => $town]));
    });

    $app->get('/cpanel/product/{id}', function(Request $request, Response $response, array $args) use ($container) {
        list('id' => $id) = $args;
        try {
            $product = $container->townManagementService->getProduct($id);
            list('town' => $town) = $product;

            $args = array_merge($args, [
                'tourismCircuits' => ApplicationUtils::TOURISM_CIRCUITS,
                'products' => $container->townManagementService->listProducts($town),
                'town' => $town,
                'url' => "/cpanel/product/{$id}",
                'type' => 'product',
                'product' => $product,
                'updateMode' => true,
                'townLink' => strtolower(implode('_', explode(' ', $town))),
            ]);
            return $container->townRenderer->render($response, 'town/product.phtml', $args);
        } catch (\Exception $ex) {
            $container->logger->error($ex);
            return $response->withRedirect($container->router->pathFor('town-landing', ['type' => 'products']));
        }
    });

    $app->post('/cpanel/product/{id}', function(Request $request, Response $response, array $args) use ($container) {
        list('id' => $id) = $args;
        list('town' => $rawTown, 'name' => $name, 'arLink' => $arLink, 'enabled' => $enabled) = $request->getParsedBody();
        try {
            $container->townManagementService->updateProduct([
                'name' => $name,
                'town' => $rawTown,
                'arLink' => $arLink,
                'id' => $id,
                'enabled' => strcasecmp('on', $enabled) == 0 ? ApplicationConstants::INDICATOR_NUMERIC_TRUE : ApplicationConstants::INDICATOR_NUMERIC_FALSE
            ]);
        } catch (\Exception $ex) {
            $container->logger->error($ex);
            $container->flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Something went wrong while processing your request. Try again later');
        }
        $town = strtolower(implode('_', explode(' ', $rawTown)));
        return $response->withRedirect($container->router->pathFor('product-list', ['town' => $town]));
    });

    $app->get('/api/product/{id}', function(Request $request, Response $response, array $args) use ($container) {
        list('id' => $id) = $args;
        try {
            $product = $container->townManagementService->getProduct($id);
            return $response->withJson($product, 200);
        } catch (\Exception $ex) {
            $container->logger->error($ex);
            return $response->withStatus(500);
        }
    });

    $app->delete('/api/product/{id}', function(Request $request, Response $response, array $args) use ($container) {
        list('id' => $id) = $args;
        try {
            $container->townManagementService->removeProduct($id);
            return $response->withJson(['message' => 'Town product removed'], 200);
        } catch (\Exception $ex) {
            $container->logger->error($ex);
            $container->flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Something went wrong while processing your request. Try again later');
            return $response->withStatus(500);
        }
    });

    $app->get('/', function(Request $request, Response $response, array $args) use ($container) {
        $topDestinations = [];
        $towns = [];
        foreach(ApplicationUtils::TOURISM_CIRCUITS as $tourismCircuit => $townList) {
            $towns = array_merge($towns, $townList);
        }
        sort($towns, SORT_NATURAL);

        $inputData = [];
        $max = 0;
        try {
            $topDestinations = array_merge([], $container->openDataDao->listTop5Destinations());
            
            $summaryResult = $container->openDataDao->summarizeVisitors();
            foreach($towns as $town) {
                $result = array_filter($summaryResult, function($val) use ($town) {
                    list('town' => $resultTown) = $val;
                    return strcasecmp($town, $resultTown) == 0;
                });
                $this->logger->debug(json_encode($summaryResult));
                $this->logger->debug(json_encode($result));
                list($row) = $result;
                list('visitorCount' => $visitorCount) = $row;
                $count = is_null($visitorCount) ? 0 : intval($visitorCount);
                $inputData = array_merge($inputData, [$count]);
                $max += $count;
            }
        } catch (\PDOException $ex) {
            $container->logger->error($ex);
        }

        $args = array_merge($args, [
            'topDestinations' => $topDestinations,
            'towns' => json_encode($towns),
            'inputData' => json_encode($inputData),
            'maxCount' => $max
        ]);

        return $container->webRenderer->render($response, 'index.phtml', $args);
    });

    $app->get('/explore', function(Request $request, Response $response, array $args) use ($container) {
        return $container->exploreRenderer->render($response, 'explore.phtml', $args);
    })->setName('explore');

    $app->get('/places/{town}', function(Request $request, Response $response, array $args) use ($container) {
        list('town' => $town) = $args;

        $modifiedTown = ucwords(implode(' ', explode('_', $town)));
        $places = [];
        $points = [];
        $products = [];
        $placesBackend = [];
        $visitorCounts = [];
        $maxCount = 0;
        try {
            $places = array_merge([], $container->poiManagementService->listPoiByTown($modifiedTown));
            $points = array_merge([], $container->townManagementService->listPoi($modifiedTown));
            $products = array_merge([], $container->townManagementService->listProducts($modifiedTown));

            foreach($places as $place) {
                list('name' => $name) = $place;
                $placesBackend = array_merge($placesBackend, [$name]);
            }
            
            foreach($container->openDataDao->summarizeVisitorsByTown($modifiedTown) as $row) {
                list('name' => $name, 'visitorcount' => $visitorCount) = $row;
                $container->logger->debug(json_encode($row));
                $result = array_filter($placesBackend, function($val) use ($name) {
                    return strcasecmp($val, $name) == 0;
                }, ARRAY_FILTER_USE_BOTH);

                $count = count($result) == 0 ? 0 : $visitorCount;
                $maxCount += $count;
                $visitorCounts = array_merge($visitorCounts, [$count]);
            }
        } catch (\PDOException $ex) {
            $container->logger->error($ex);
        }

        $args = array_merge($args, [
            'town' => $modifiedTown,
            'places' => array_filter($places, function($val) use ($container) {
                $container->logger->debug(json_encode($val));
                return $val['displayable'] != 0;
            }, ARRAY_FILTER_USE_BOTH),
            'points' => array_filter($points, function($val) use ($container) {
                $container->logger->debug(json_encode($val));
                return $val['enabled'] != 0;
            }, ARRAY_FILTER_USE_BOTH),
            'products' => array_filter($products, function($val) use ($container) {
                $container->logger->debug(json_encode($val));
                return $val['enabled'] != 0;
            }, ARRAY_FILTER_USE_BOTH),
            'openData' => [
                'places' => json_encode($placesBackend),
                'values' => json_encode($visitorCounts),
                'maxCount' => $maxCount
            ]
        ]);
        return $container->exploreRenderer->render($response, 'places.phtml', $args);
    });

    $app->get('/place/{id}', function(Request $request, Response $response, array $args) use ($container) {
        list('id' => $id) = $args;

        try {
            $poi = $container->poiManagementService->getPoi($id);
            $schedules = $container->poiManagementService->listSchedules($id);
            $fees = $container->poiManagementService->listFees($id);
            $container->openDataDao->captureVisit($id);

            list('imageName' => $imageName) = $poi;
            $imageSrc = intval(getenv('USE_LOCAL_FILESYSTEM')) == ApplicationConstants::INDICATOR_NUMERIC_TRUE ? "/uploads/{$imageName}" : $imageName;
            
            $args = array_merge($args, [
                'poi' => $poi,
                'schedules' => $schedules,
                'fees' => $fees,
                'visitorCount' => $container->openDataDao->countVisitorsByDestination($id),
                'arLink' => strlen(trim($poi['arLink'])) == 0 ? '#' : trim($poi['arLink']),
                'imageSrc' => $imageSrc
            ]);

            if ($poi['displayable'] == ApplicationConstants::INDICATOR_NUMERIC_TRUE) {
                return $container->webRenderer->render($response, 'place.phtml', $args);
            } else {
                return $response->withRedirect($container->router->pathFor('explore'));
            }
        } catch (\PDOException $ex) {
            $container->logger->error($ex);
            return $response->withRedirect($container->router->pathFor('explore'));
        }
    });

    $app->get('/discover', function(Request $request, Response $response, array $args) use ($container) {
        $places = [];
        try {
            $places = array_merge([], $container->poiManagementService->listPoi());
        } catch (\PDOException $ex) {
            $container->logger->error($ex);
        }

        $args = array_merge($args, [
            'places' => array_filter($places, function($val) use ($container) {
                $container->logger->debug(json_encode($val));
                return $val['displayable'] != 0 && $val['arEnabled'] != 0;
            }, ARRAY_FILTER_USE_BOTH)
        ]);

        return $container->webRenderer->render($response, 'discover.phtml', $args);
    });

    $app->get('/open-data', function(Request $request, Response $response, array $args) use ($container) {
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

    $app->get('/about', function(Request $request, Response $response, array $args) use ($container) {
        return $container->exploreRenderer->render($response, 'about.phtml', $args);
    });
};
