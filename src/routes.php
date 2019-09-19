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
        $destinations = [];
        $towns = [];
        foreach(ApplicationUtils::TOURISM_CIRCUITS as $tourismCircuit => $townList) {
            $towns = array_merge($towns, $townList);
        }
        sort($towns, SORT_NATURAL);

        $inputData = [];
        $max = 0;
        try {
            $destinations = array_merge([], $container->openDataDao->listDestinations([]));
            
            $summaryResult = $container->openDataDao->summarizeVisitors();
            foreach($towns as $town) {
                $count = ApplicationUtils::getVisitorCountByTown($summaryResult, $town);
                $inputData = array_merge($inputData, [$count]);
                $max += $count;
            }
        } catch (\PDOException $ex) {
            $container->logger->error($ex);
        }

        $args = array_merge($args, [
            'destinations' => $destinations,
            'towns' => json_encode($towns),
            'inputData' => json_encode($inputData),
            'maxCount' => $max
        ]);
        
        return $container->cpanelRenderer->render($response, 'index.phtml', $args);
    })->setName('cpanel-home');

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
            $args = array_merge($args, ['poiList' => count($result) == 0 ? '[]' : json_encode($result)]);
        } catch (\PDOException $ex) {
            $container->logger->error($ex);
        }
        $args = array_merge($args, [ApplicationConstants::NOTIFICATION_KEY => $flash->getFirstMessage(ApplicationConstants::NOTIFICATION_KEY)]);
        return $container->cpanelRenderer->render($response, 'poi/index.phtml', $args);
    })->setName('poi-list');

    $app->delete('/api/poi/{id}', function(Request $request, Response $response, array $args) use ($container) {
        list('name' => $name) = $request->getParsedBody();
        list('id' => $id) = $args;
        $flash = $container->flash;
        $message = "";
        try {
            $container->poiManagementService->removePoi($id);
            $message = "Tourist Location {$name} has been removed.";
            $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, $message);
            return $response->withJson(array('message' => $message), 200);
        } catch (\PDOException $ex) {
            $container->logger->error($ex);
            $message = "Tourist Location {$name} cannot be removed. Try again later.";
            $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, $message);
            return $response->withJson(array('message' => $message), 500);
        }
    });

    $app->get('/cpanel/poi/add', function(Request $request, Response $response, array $args) use($container) {
        $classifications = $container->classificationService->getClassifications();
        $tags = $container->tagService->getTags();

        $args = array_merge($args, [ 
            'circuits' => ApplicationUtils::TOURISM_CIRCUITS, 
            'classifications' => ApplicationUtils::convertArrayToAutocompleteData($classifications, 'name'),
            'classificationsBackend' => json_encode($classifications),
            'tags' => ApplicationUtils::convertArrayToAutocompleteData($tags, 'name'),
            'tagsBackend' => json_encode($tags),
            'developmentLevels' => ApplicationConstants::DEVELOPMENT_TYPES,
            'contactTypes' => ApplicationConstants::CONTACT_TYPES
        ]);
        return $container->cpanelRenderer->render($response, 'poi/create.phtml', $args);
    });

    $app->post('/cpanel/poi/add', function(Request $request, Response $response, array $args) use ($container) {
        $body = $request->getParsedBody();
        $container->logger->debug(json_encode($body));
        list('name' => $name, 'classifications' => $rawClassifications, 'topicTags' => $rawTags, 
            'schedules' => $rawSchedules, 'fees' => $rawFees, 'contacts' => $rawContacts,
            'arLink' => $arLink, 'descriptionWysiwyg' => $rawDescription, 'commuterGuideWysiwyg' => $rawCommuterGuide) = $body;

        list('primaryImage' => $image, 'images' => $images) = $request->getUploadedFiles();
        $primaryImage = $container->fileUploadService->uploadFile([
            'file' => $image,
            'name' => $name
        ]);
        $imageList = [];
        foreach($images as $imageEntry) {
            $imageFile = $container->fileUploadService->uploadFile([
                'file' => $imageEntry,
                'name' => $name
            ]);
            $imageList = array_merge($imageList, [$imageFile]);
        }

        $input = array_filter($body, function($key) {
            return strcasecmp('topicTags', $key) !=0 && strcasecmp('classifications', $key) != 0
                && strcasecmp('schedules', $key) != 0 && strcasecmp('fees', $key) != 0
                && strcasecmp('contacts', $key) != 0 && strcasecmp('openingTime', $key) != 0 
                && strcasecmp('closingTime', $key) != 0 && strcasecmp('scheduleNotes', $key) != 0
                && strcasecmp('feeDescription', $key) != 0 && strcasecmp('amount', $key) != 0
                && strcasecmp('contactValue', $key) != 0 && strcasecmp('action', $key) != 0
                && strcasecmp('descriptionWysiwyg', $key) != 0 && strcasecmp('commuterGuideWysiwyg', $key) != 0;
        }, ARRAY_FILTER_USE_KEY);
        

        try {
            $poi = $container->poiManagementService->createPoi(array_merge($input, [
                'imageName' => $primaryImage,
                'images' => implode(',', $imageList),
                'classifications' => json_decode($rawClassifications, true),
                'topicTags' => json_decode($rawTags, true),
                'schedules' => json_decode($rawSchedules, true),
                'fees' => json_decode($rawFees, true),
                'contacts' => json_decode($rawContacts, true),
                'arEnabled' => strlen(trim($arLink)) == 0 ? ApplicationConstants::INDICATOR_NUMERIC_FALSE : ApplicationConstants::INDICATOR_NUMERIC_TRUE,
                'descriptionWysiwyg' => strlen(trim($rawDescription)) == 0 ? null : $rawDescription,
                'commuterGuideWysiwyg' => strlen(trim($rawCommuterGuide)) == 0 ? null : $rawCommuterGuide
            ]));
            return $response->withRedirect($container->router->pathFor('poi-info', ['id' => $poi]));
        } catch (\Exception $ex) {
            $container->logger->error($ex);
            $container->flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Something went wrong. Try again later');
            return $response->withRedirect($container->router->pathFor('poi-list'));
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
                list('imageName' => $imageName, 'images' => $images) = $result;
                $useLocalFileSystem = intval(getenv('USE_LOCAL_FILESYSTEM')) == ApplicationConstants::INDICATOR_NUMERIC_TRUE;
                $imageSrc = $useLocalFileSystem ? "/uploads/{$imageName}" : $imageName;
                
                $imageList = [];
                foreach(explode(',', $images) as $imageEntry) {
                    $file = $useLocalFileSystem ? "/uploads/{$imageEntry}" : $imageEntry;
                    $imageList = array_merge($imageList, [$file]);
                }
                $container->logger->debug(json_encode($imageList));

                $args = array_merge($args, [
                    'id' => $id, 
                    'result' => $result, 
                    'imageSrc' => $imageSrc,
                    'images' => $imageList
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

                $args = array_merge($args, [ 
                    'id' => $id, 
                    'result' => $result, 
                    'circuits' => ApplicationUtils::TOURISM_CIRCUITS ,
                    'classifications' => ApplicationUtils::convertArrayToAutocompleteData($classifications, 'name'),
                    'classificationsBackend' => json_encode($classifications),
                    'assignedClassifications' => ApplicationUtils::convertArrayToTagData($assignedClassifications, 'name'),
                    'assignedClassificationsBackend' => ApplicationUtils::convertTagResultToBackendReferences($assignedClassifications, 'id'),
                    'tags' => ApplicationUtils::convertArrayToAutocompleteData($tags, 'name'),
                    'tagsBackend' => json_encode($tags),
                    'assignedTags' => ApplicationUtils::convertArrayToTagData($assignedTags, 'name'),
                    'assignedTagsBackend' => ApplicationUtils::convertTagResultToBackendReferences($assignedTags, 'id'), 
                    'developmentLevels' => ApplicationConstants::DEVELOPMENT_TYPES   
                ]);
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
        $container->logger->debug(json_encode($body));
        $inputs = array_filter($body, function($key) {
            return strcasecmp('topicTags', $key) !=0 && strcasecmp('classifications', $key) != 0 
                && strcasecmp('commuterguidewysiwyg', $key) != 0
                && strcasecmp('commuterguide', $key) != 0
                && strcasecmp('descriptionwysiwyg', $key)  != 0
                && strcasecmp('description', $key)  != 0
                && strcasecmp('imagebackend', $key) != 0
                && strcasecmp('imagesbackend', $key);
        }, ARRAY_FILTER_USE_KEY);

        list('topicTags' => $rawTags, 'classifications' => $rawClassifications, 
            'descriptionwysiwyg' => $rawDescriptionWysiwyg, 
            'description' => $rawDescription,
            'commuterguidewysiwyg' => $rawCommuterWysiWyg,
            'commuterguide' => $rawCommuterGuide,
            'imagebackend' => $imageBackend,
            'imagesbackend' => $imagesBackend,
            'name' => $name,
            'arLink' => $arLink) = $body;

        list('image' => $image, 'images' => $images) = $request->getUploadedFiles();
        $primaryImage = $container->fileUploadService->uploadFile([
            'file' => $image,
            'name' => $name
        ]);
        $imageList = [];
        foreach($images as $imageEntry) {
            $imageFile = $container->fileUploadService->uploadFile([
                'file' => $imageEntry,
                'name' => $name
            ]);
            if (is_null($imageFile)) {
                continue;
            }
            $imageList = array_merge($imageList, [$imageFile]);
        }

        $inputs = array_merge($inputs, [ 
            'topicTags' => json_decode($rawTags),
            'classifications' => json_decode($rawClassifications),
            'descriptionwysiwyg' => strlen(trim($rawDescriptionWysiwyg)) == 0 ? null : json_encode(json_decode($rawDescriptionWysiwyg, true)),
            'description' => strlen(trim($rawDescription)) == 0 ? null : $rawDescription,
            'commuterguidewysiwyg' => strlen(trim($rawCommuterWysiWyg)) == 0 ? null : json_encode(json_decode($rawCommuterWysiWyg, true)),
            'commuterguide' => strlen(trim($rawCommuterGuide)) == 0 ? null : $rawCommuterGuide,
            'id' => $id,
            'imagename' => is_null($primaryImage) ? $imageBackend : $primaryImage,
            'images' => count($imageList) == 0 ? $imagesBackend : implode(',', $imageList),
            'arEnabled' => strlen(trim($arLink)) == 0 ? ApplicationConstants::INDICATOR_NUMERIC_FALSE : ApplicationConstants::INDICATOR_NUMERIC_TRUE
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

    $app->get('/cpanel/poi/{id}/contacts', function(Request $request, Response $response, array $args) use ($container) {
        list('id' => $id) = $args;
        $flash = $container->flash;
        $service = $container->poiManagementService;
        try {
            $result = $service->getPoi($id);
            list('name' => $name) = $result;
            $args = array_merge($args, [
                'url' => '/cpanel/contact',
                'name' => $name,
                'poi' => $id,
                'contacts' => $service->listContacts($id),
                'contactTypes' => ApplicationConstants::CONTACT_TYPES,
                ApplicationConstants::NOTIFICATION_KEY => $flash->getFirstMessage(ApplicationConstants::NOTIFICATION_KEY)
            ]);
            return $container->poiRenderer->render($response, 'contacts.phtml', $args);
        } catch (\PDOException $ex) {
            $container->logger->error($ex);
            $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Something went wrong while loading Tourist Location info. Try again later');
            return $response->withRedirect($container->router->pathFor('poi-list'));
        }
    })->setName('contacts');

    $app->post('/cpanel/contact', function(Request $request, Response $response, array $args) use ($container) {
        list('poi' => $poi, 'type' => $type, 'value' => $value) = $request->getParsedBody();
        $flash = $container->flash;
        try {
            $container->poiManagementService->addContact([
                'placeOfInterest' => $poi,
                'type' => $type,
                'value' => $value
            ]);
            $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Contact Detail successfully added');
            return $response->withRedirect($container->router->pathFor('contacts', ['id' => $poi]));
        } catch (\PDOException $ex) {
            $container->logger->error($ex);
            $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Something went wrong while processing your request. Try again later');
            return $response->withRedirect($container->router->pathFor('poi-list'));
        }
    });

    $app->get('/cpanel/contact/{id}', function(Request $request, Response $response, array $args) use ($container) {
        list('id' => $id) = $args;
        $flash = $container->flash;
        $service = $container->poiManagementService;
        try {
            $contact = $service->getContact($id);
            list('placeofinterest' => $poi) = $contact;
            $placeOfInterest = $service->getPoi($poi);
            if (is_null($contact) || is_null($placeOfInterest)) {
                $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Something went wrong while processing your request. Try again later');
                return $response->withRedirect($container->router->pathFor('poi-list'));
            }            
            list('name' => $name) = $placeOfInterest;

            $args = array_merge($args, [
                'url' => "/cpanel/contact/{$id}",
                'name' => $name,
                'poi' => $poi,
                'contacts' => $service->listContacts($poi),
                'updateMode' => true,
                'contact' => $contact,
                'id' => $poi,
                'contactTypes' => ApplicationConstants::CONTACT_TYPES,
                ApplicationConstants::NOTIFICATION_KEY => $flash->getFirstMessage(ApplicationConstants::NOTIFICATION_KEY)
            ]);
            return $container->poiRenderer->render($response, 'contacts.phtml', $args);
        } catch (\PDOException $ex) {
            $container->logger->error($ex);
            $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Something went wrong while processing your request. Try again later');
            return $response->withRedirect($container->router->pathFor('poi-list'));
        }
    });

    $app->post('/cpanel/contact/{id}', function(Request $request, Response $response, array $args) use ($container) {
        $container->logger->debug('hello@');
        list('id' => $id) = $args;
        list('poi' => $poi, 'type' => $type, 'value' => $value, 'enabled' => $enabled) = $request->getParsedBody();
        $flash = $container->flash;
        try {
            $container->poiManagementService->updateContact([
                'id' => $id,
                'type' => $type,
                'value' => $value,
                'enabled' => strcasecmp('on', $enabled) == 0 ? ApplicationConstants::INDICATOR_NUMERIC_TRUE : ApplicationConstants::INDICATOR_NUMERIC_FALSE
            ]);
            $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Contact successfully updated');
            return $response->withRedirect($container->router->pathFor('contacts', ['id' => $poi]));
        } catch(\PDOException $ex) {
            $container->logger->error($ex);
            $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Something went wrong while processing your request. Try again later');
            return $response->withRedirect($container->router->pathFor('poi-list'));
        }
    });

    $app->delete('/api/contact/{id}', function(Request $request, Response $response, array $args) use ($container) {
        list('id' => $contact) = $args;
        list('poi' => $poi) = $request->getParsedBody();
        try {
            $container->poiManagementService->removeContact($contact);
            return $response->withJson(['url' => $container->router->pathFor('contacts', ['id' => $poi])], 200);
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

    $app->get('/cpanel/products', function(Request $request, Response $response, array $args) use ($container) {
        $flash = $container->flash;
        try {
            $result = $container->townManagementService->listProducts([]);
            $products = [];
            foreach($result as $entry) {
                list('imageFile' => $primaryImage) = $entry;
                $imageSrc = intval(getenv('USE_LOCAL_FILESYSTEM')) == ApplicationConstants::INDICATOR_NUMERIC_TRUE ? "/uploads/{$primaryImage}" : $primaryImage;
                $product = array_merge($entry, ['imageFile' => $imageSrc]);
                $products = array_merge($products, [$product]);
            }
            $args = array_merge($args, [
                'products' => count($products) == 0 ? '[]' : json_encode($products),
                ApplicationConstants::NOTIFICATION_KEY => $flash->getFirstMessage(ApplicationConstants::NOTIFICATION_KEY),
            ]);
            return $container->cpanelRenderer->render($response, 'product/index.phtml', $args);
        } catch (\Exception $ex) {
            $container->logger->error($ex);
            $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Something went wrong while processing your request. Try again later');
            return $response->withRedirect($container->router->pathFor('cpanel-home'));
        }
    })->setName('product-list');

    $app->get('/cpanel/product/add', function(Request $request, Response $response, array $args) use ($container) {
        $args = array_merge($args, [
            'circuits' => ApplicationUtils::TOURISM_CIRCUITS,
            'formTitle' => 'Add a Product',
            'updateMode' => false,
            'url' => '/cpanel/product'
        ]);
        return $container->cpanelRenderer->render($response, 'product/form.phtml', $args);
    });

    $app->post('/cpanel/product', function(Request $request, Response $response, array $args) use ($container) {
        $body = $request->getParsedBody();
        list('town' => $town, 'name' => $name, 'arLink' => $arLink, 'description' => $description, 'photoCredit' => $photoCredit) = $body;
        try {
            list('image' => $image, 'images' => $images) = $request->getUploadedFiles();
            $primaryImage = $container->fileUploadService->uploadFile([
                'file' => $image,
                'name' => $name
            ]);

            $imageList = [];
            foreach($images as $imageEntry) {
                $filename = $container->fileUploadService->uploadFile([
                    'file' => $imageEntry,
                    'name' => $name,
                ]);
                $imageList = array_merge($imageList, [$filename]);
            }

            $container->townManagementService->addProduct([
                'name' => $name,
                'town' => $town,
                'arLink' => $arLink,
                'description' => $description,
                'imageFile' => $primaryImage,
                'images' => implode(',', $imageList),
                'photoCredit' => $photoCredit
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
            if(is_null($product)) {
                return $response->withRedirect($container->router->pathFor('product-list'));
            }

            list('imageFile' => $primaryImage, 'images' => $imageList) = $product;
            
            $useLocalFileSystem = intval(getenv('USE_LOCAL_FILESYSTEM')) == ApplicationConstants::INDICATOR_NUMERIC_TRUE;
            $imageSrc = $useLocalFileSystem ? "/uploads/{$primaryImage}" : $primaryImage;

            $images = [];
            foreach(explode(',', $imageList) as $imageEntry) {
                $image = $useLocalFileSystem ?  "/uploads/{$imageEntry}" : $imageEntry;
                $images = array_merge($images, [$image]);
            }

            $args = array_merge($args, [
                'product' => $product,
                'imageSrc' => $imageSrc,
                'images' => $images
            ]);
            return $container->cpanelRenderer->render($response, 'product/info.phtml', $args);
        } catch (\Exception $ex) {
            $container->logger->error($ex);
            return $response->withRedirect($container->router->pathFor('product-list'));
        }
    })->setName('product');

    $app->get('/cpanel/product/{id}/edit', function(Request $request, Response $response, array $args) use ($container) {
        list('id' => $id) = $args;
        try {
            $product = $container->townManagementService->getProduct($id);
            if(is_null($product)) {
                return $response->withRedirect($container->router->pathFor('product-list'));
            }

            $args = array_merge($args, [
                'product' => $product,
                'circuits' => ApplicationUtils::TOURISM_CIRCUITS,
                'formTitle' => 'Update a Product',
                'url' => "/cpanel/product/{$id}/edit",
                'updateMode' => true
            ]);
            return $container->cpanelRenderer->render($response, 'product/form.phtml', $args);
        } catch (\Exception $ex) {
            $container->logger->error($ex);
            return $response->withRedirect($container->router->pathFor('product-list'));
        }
    });

    $app->post('/cpanel/product/{id}/edit', function(Request $request, Response $response, array $args) use ($container) {
        list('id' => $id) = $args;
        list('town' => $town, 'name' => $name, 'arLink' => $arLink, 'description' => $description, 
            'photoCredit' => $photoCredit, 'enabled' => $enabled, 
            'imageBackend' => $imageBackend, 'imagesBackend' => $imagesBackend) = $request->getParsedBody();
        try {
            list('image' => $image, 'images' => $images) = $request->getUploadedFiles();
            $primaryImage = $container->fileUploadService->uploadFile([
                'file' => $image,
                'name' => $name
            ]);

            $imageList = [];
            foreach($images as $imageEntry) {
                $filename = $container->fileUploadService->uploadFile([
                    'file' => $imageEntry,
                    'name' => $name,
                ]);
                if (is_null($filename)) {
                    continue;
                }
                $imageList = array_merge($imageList, [$filename]);
            }

            $container->townManagementService->updateProduct([
                'id' => $id,
                'name' => $name,
                'town' => $town,
                'arLink' => $arLink,
                'description' => $description,
                'imageFile' => is_null($primaryImage) ? $imageBackend : $primaryImage,
                'images' => count($imageList) == 0 ? $imagesBackend : implode(',', $imageList),
                'photoCredit' => $photoCredit,
                'enabled' => strcasecmp($enabled, 'on') == 0 ? ApplicationConstants::INDICATOR_NUMERIC_TRUE : ApplicationConstants::INDICATOR_NUMERIC_FALSE
            ]);
            return $response->withRedirect($container->router->pathFor('product', ['id' => $id]));
        } catch (\Exception $ex) {
            $container->logger->error($ex);
            return $response->withRedirect($container->router->pathFor('product-list'));
        }
    });
    
    $app->delete('/api/product/{id}', function(Request $request, Response $response, array $args) use ($container) {
        list('id' => $id) = $args;
        list('name' => $name) = $request->getParsedBody();
        $flash = $container->flash;
        try {
            $container->townManagementService->removeProduct($id);
            $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, "Product {$name} has been removed.");
            return $response->withStatus(200);
        } catch (\Exception $ex) {
            $container->logger->error($ex);
            $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, 'Something went wrong while processing your request. Try again later');
            return $response->withStatus(500);
        }
    });

    $app->get('/', function(Request $request, Response $response, array $args) use ($container) {
        return $container->webRenderer->render($response, 'index.phtml', $args);
    });

    $app->get('/home', function(Request $request, Response $response, array $args) use ($container) {
        $allDestinations = [];

        try {
            $allDestinations = array_merge([], $container->poiManagementService->listPoi());
        } catch (\Exception $ex) {
            $container->logger->error($ex);
        }

        $args = array_merge($args, [
            'destinationAutocomplete' => ApplicationUtils::convertArrayToAutocompleteData($allDestinations, 'name'),
            'destinationsBackend' => count($allDestinations) == 0 ? '[]' : json_encode($allDestinations),
            'title' => 'Explore La Union',
            'showFooter' => false
        ]);

        return $container->exploreRenderer->render($response, 'home.phtml', $args);
    });

    $app->get('/explore', function(Request $request, Response $response, array $args) use ($container) {
        $topDestinations = [];
        $products = [];
        $allDestinations = [];

        try {
            $topDestinations = array_merge([], $container->openDataDao->listDestinations(['limit' => 5]));
            $products = array_merge([], $container->townManagementService->listProducts([]));
            $allDestinations = array_merge([], $container->poiManagementService->listPoi());
        } catch (\Exception $ex) {
            $container->logger->error($ex);
        }

        $args = array_merge($args, [
            'topDestinations' => $topDestinations,
            'products' => $products,
            'destinationAutocomplete' => ApplicationUtils::convertArrayToAutocompleteData($allDestinations, 'name'),
            'destinationsBackend' => count($allDestinations) == 0 ? '[]' : json_encode($allDestinations),
            'title' => 'Explore La Union'
        ]);

        return $container->exploreRenderer->render($response, 'explore.phtml', $args);
    })->setName('explore');

    $app->get('/places/{town}', function(Request $request, Response $response, array $args) use ($container) {
        list('town' => $town) = $args;

        $modifiedTown = ucwords(implode(' ', explode('_', $town)));
        $places = [];
        $products = [];
        $placesBackend = [];
        $visitorCounts = [];
        $topDestinations = [];
        $allProducts = [];
        $allDestinations = [];
        $maxCount = 0;
        $useLocalFileSystem = intval(getenv('USE_LOCAL_FILESYSTEM')) == ApplicationConstants::INDICATOR_NUMERIC_TRUE;
        try {
            $placesResult = array_merge([], $container->poiManagementService->listPoiByTown($modifiedTown));
            $productsResult = array_merge([], $container->townManagementService->listProducts(['town' => $modifiedTown]));
            $countResult = $container->openDataDao->summarizeVisitorsByTown($modifiedTown);

            $topDestinations = array_merge([], $container->openDataDao->listDestinations(['limit' => 5]));
            $allProducts = array_merge([], $container->townManagementService->listProducts([]));
            $allDestinations = array_merge([], $container->poiManagementService->listPoi());

            foreach($placesResult as $place) {
                list('name' => $name, 'imageName' => $primaryImage) = $place;
                $count = ApplicationUtils::getVisitorCountByPoi($countResult, $name);
                $maxCount += $count;
                $visitorCounts = array_merge($visitorCounts, [$count]);
                $placesBackend = array_merge($placesBackend, [$name]);
                
                $poiImage = $useLocalFileSystem ? "/uploads/{$primaryImage}" : $primaryImage;
                $place = array_merge($place, ['imageName' => $poiImage]);
                $places = array_merge($places, [$place]);
            }

            foreach($productsResult as $product) {
                list('imageFile' => $primaryImage) = $product;
                $productImage = $useLocalFileSystem ? "/uploads/{$primaryImage}" : $primaryImage;
                $product = array_merge($product, ['imageFile' => $productImage]);
                $products = array_merge($products, [$product]);
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
            'townProducts' => array_filter($products, function($val) use ($container) {
                $container->logger->debug(json_encode($val));
                return $val['enabled'] != 0;
            }, ARRAY_FILTER_USE_BOTH),
            'openData' => [
                'places' => json_encode($placesBackend),
                'values' => json_encode($visitorCounts),
                'maxCount' => $maxCount
            ],
            'topDestinations' => $topDestinations,
            'products' => $allProducts,
            'destinationAutocomplete' => ApplicationUtils::convertArrayToAutocompleteData($allDestinations, 'name'),
            'destinationsBackend' => count($allDestinations) == 0 ? '[]' : json_encode($allDestinations),
            'title' => "Explore {$modifiedTown}"
        ]);
        return $container->exploreRenderer->render($response, 'places.phtml', $args);
    });

    $app->get('/place/{id}', function(Request $request, Response $response, array $args) use ($container) {
        list('id' => $id) = $args;

        try {
            $poi = $container->poiManagementService->getPoi($id);
            $schedules = $container->poiManagementService->listSchedules($id);
            $fees = $container->poiManagementService->listFees($id);
            $contacts = $container->poiManagementService->listContacts($id);
            $container->openDataDao->captureVisit($id);

            list('imageName' => $imageName, 'images' => $images) = $poi;
            $useLocalFileSystem = intval(getenv('USE_LOCAL_FILESYSTEM')) == ApplicationConstants::INDICATOR_NUMERIC_TRUE;
            $imageSrc = $useLocalFileSystem ? "/uploads/{$imageName}" : $imageName;
            
            $imageList = [];
            foreach(explode(',', $images) as $imageEntry) {
                $file = $useLocalFileSystem ? "/uploads/{$imageEntry}" : $imageEntry;
                if (strlen(trim($file)) == 0) {
                    continue;
                }
                $imageList = array_merge($imageList, [$file]);
            }
            $allDestinations = array_merge([], $container->poiManagementService->listPoi());

            $args = array_merge($args, [
                'poi' => $poi,
                'schedules' => $schedules,
                'fees' => $fees,
                'contacts' => $contacts,
                'visitorCount' => $container->openDataDao->countVisitorsByDestination($id),
                'imageSrc' => $imageSrc,
                'images' => $imageList,
                'comments' => $container->visitorService->listComments($id),
                'topDestinations' => $container->openDataDao->listDestinations(['limit' => 5]),
                'products' => $container->townManagementService->listProducts([]),
                'destinationAutocomplete' => ApplicationUtils::convertArrayToAutocompleteData($allDestinations, 'name'),
                'destinationsBackend' => count($allDestinations) == 0 ? '[]' : json_encode($allDestinations),
                'title' => "Explore {$poi['town']}"
            ]);

            if ($poi['displayable'] == ApplicationConstants::INDICATOR_NUMERIC_TRUE) {
                return $container->exploreRenderer->render($response, 'destination.phtml', $args);
            } else {
                return $response->withRedirect($container->router->pathFor('explore'));
            }
        } catch (\PDOException $ex) {
            $container->logger->error($ex);
            return $response->withRedirect($container->router->pathFor('explore'));
        }
    })->setName('destination');

    $app->post('/place/{id}/comment', function(Request $request, Response $response, array $args) use ($container) {
        list('id' => $id) = $args;
        $body = $request->getParsedBody();
        $flash = $container->flash;
        try {
            $container->visitorService->addComment($body);
            $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, "Your comment has been added.");
        } catch (\Exception $ex) {
            $container->logger->error($ex);
            $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, "Something went wrong while processing your request. Please try again");
        } finally {
            return $response->withRedirect($container->router->pathFor('destination', ['id' => $id]));
        }
    });

    $app->get('/product/{id}', function(Request $request, Response $response, array $args) use ($container) {
        list('id' => $id) = $args;
        try {
            $product = $container->townManagementService->getProduct($id);
            $container->logger->debug(json_encode($product));
            list('imageFile' => $imageName, 'images' => $images) = $product;
            $useLocalFileSystem = intval(getenv('USE_LOCAL_FILESYSTEM')) == ApplicationConstants::INDICATOR_NUMERIC_TRUE;
            $imageSrc = $useLocalFileSystem ? "/uploads/{$imageName}" : $imageName;
            
            $imageList = [];
            foreach(explode(',', $images) as $imageEntry) {
                $file = $useLocalFileSystem ? "/uploads/{$imageEntry}" : $imageEntry;
                if (strlen(trim($file)) == 0) {
                    continue;
                }
                $imageList = array_merge($imageList, [$file]);
            }

            $allDestinations = array_merge([], $container->poiManagementService->listPoi());

            $args = array_merge($args, [
                'product' => $product,
                'imageSrc' => $imageSrc,
                'images' => $imageList,
                'topDestinations' => $container->openDataDao->listDestinations(['limit' => 5]),
                'products' => $container->townManagementService->listProducts([]),
                'destinationAutocomplete' => ApplicationUtils::convertArrayToAutocompleteData($allDestinations, 'name'),
                'destinationsBackend' => count($allDestinations) == 0 ? '[]' : json_encode($allDestinations),
                'title' => "Explore {$product['town']}"
            ]);

            return $container->exploreRenderer->render($response, 'product.phtml', $args);
        } catch (\Exception $ex) {
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
        $topDestinations = [];
        $products = [];
        $destinations = [];

        try {
            $topDestinations = array_merge([], $container->openDataDao->listDestinations(['limit' => 5]));
            $products = $container->townManagementService->listProducts([]);
            $destinations = array_merge([], $container->poiManagementService->listPoi());
        } catch (\Exception $ex) {
            $container->logger->error($ex);
        }

        $args = array_merge($args, [
            'title' => 'Open Data',
            'topDestinations' => $topDestinations,
            'products' => $products,
            'destinationAutocomplete' => ApplicationUtils::convertArrayToAutocompleteData($destinations, 'name'),
            'destinationsBackend' => count($destinations) == 0 ? '[]' : json_encode($destinations)
        ]);

        return $container->exploreRenderer->render($response, 'open-data/index.phtml', $args);
    })->setName('open-data-landing');

    $app->post('/open-data/poi', function(Request $request, Response $response, array $args) use ($container) {
        $logger = $container->logger;
        list('emailAddress' => $email, 'userConsent' => $userConsent, 
            'reportType' => $reportType) = $request->getParsedBody();
        
        if (strcasecmp('on', $userConsent) != 0) {
            $logger->warn('User consent not fulfilled!');
            return $response->withRedirect($container->router->pathFor('open-data-landing'));
        }

        $csvGenerationSetting = $container->csvGenerationSetting;
        $filename = "{$csvGenerationSetting->destination}/" . time() . "_tourist-destination-masterlist.csv";
        try {
            $rows = $container->csvOpenDataService->listPoi();
            $file = fopen($filename, 'w');
            foreach($rows as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
            $baseName = basename($filename);

            $container->openDataDao->trackDownload([
                'email' => $email,
                'reportType' => $reportType
            ]);

            return $response->withRedirect("{$csvGenerationSetting->httpPath}/{$baseName}");
        } catch (\Exception $ex) {
            $logger->error($ex);
            return $response->withRedirect($container->router->pathFor('open-data-landing'));
        }
    });

    $app->get('/open-data/hits', function(Request $request, Response $response, array $args) use ($container) {
        $topDestinations = [];
        $products = [];
        $destinations = [];
        try {
            $topDestinations = array_merge([], $container->openDataDao->listDestinations(['limit' => 5]));
            $products = $container->townManagementService->listProducts([]);
            $destinations = array_merge([], $container->poiManagementService->listPoi());
        } catch (\Exception $ex) {
            $container->logger->error($ex);
        }

        $args = array_merge($args, [
            'title' => 'Open Data',
            'topDestinations' => $topDestinations,
            'products' => $products,
            'destinationAutocomplete' => ApplicationUtils::convertArrayToAutocompleteData($destinations, 'name'),
            'destinationsBackend' => count($destinations) == 0 ? '[]' : json_encode($destinations),
            ApplicationConstants::NOTIFICATION_KEY => $container->flash->getFirstMessage(ApplicationConstants::NOTIFICATION_KEY)
        ]);

        return $container->exploreRenderer->render($response, 'open-data/hits.phtml', $args);
    })->setName('hits');

    $app->post('/open-data/hits', function(Request $request, Response $response, array $args) use ($container) {
        $logger = $container->logger;
        $flash = $container->flash;

        list('places' => $places, 'startDate' => $startDate, 'endDate' => $endDate, 
            'reportType' => $reportType, 'emailAddress' => $email, 
            'userConsent' => $userConsent) = $request->getParsedBody();

        if (strcasecmp('on', $userConsent) != 0) {
            $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, "User input is invalid. Please try again");
            return $response->withRedirect($container->router->pathFor('hits'));
        }

        $criteriaMap = [];
        if (strlen(trim($places)) != 0) {
            $criteriaMap = array_merge($criteriaMap, [
                'places' => json_decode($places)
            ]);
        }

        if (strlen(trim($startDate)) != 0) {
            $criteriaMap = array_merge($criteriaMap, [
                'startDate' => $startDate
            ]);
        }

        if (strlen(trim($endDate)) != 0) {
            $criteriaMap = array_merge($criteriaMap, [
                'endDate' => $endDate
            ]);
        }

        $rows = [];
        $csvGenerationSetting = $container->csvGenerationSetting;
        $filename = "{$csvGenerationSetting->destination}/" . time() . "_pageHits.csv";
        try {
            $rows = array_merge($rows, $container->csvOpenDataService->countVisitors($criteriaMap));
            $logger->debug("Result: ".json_encode($rows));

            $file = fopen($filename, 'w');
            foreach($rows as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
            $baseName = basename($filename);

            $container->openDataDao->trackDownload([
                'email' => $email,
                'reportType' => $reportType
            ]);

            return $response->withRedirect("{$csvGenerationSetting->httpPath}/{$baseName}");
        } catch (\Exception $ex) {
            $flash->addMessage(ApplicationConstants::NOTIFICATION_KEY, "Something went wrong while processing your request. Please try again");
            return $response->withRedirect($container->router->pathFor('hits'));
        }
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
        $topDestinations = [];
        $products = [];
        $destinations = [];
        try {
            $topDestinations = array_merge([], $container->openDataDao->listDestinations(['limit' => 5]));
            $products = $container->townManagementService->listProducts([]);
            $destinations = array_merge([], $container->poiManagementService->listPoi());
        } catch (\Exception $ex) {
            $container->logger->error($ex);
        }

        $args = array_merge($args, [
            'title' => 'ELYU Turismo',
            'topDestinations' => $topDestinations,
            'products' => $products,
            'destinationAutocomplete' => ApplicationUtils::convertArrayToAutocompleteData($destinations, 'name'),
            'destinationsBackend' => count($destinations) == 0 ? '[]' : json_encode($destinations),
        ]);

        return $container->exploreRenderer->render($response, 'about.phtml', $args);
    });

    $app->get('/privacy', function(Request $request, Response $response, array $args) use ($container) {
        $topDestinations = [];
        $products = [];
        $destinations = [];
        try {
            $topDestinations = array_merge([], $container->openDataDao->listDestinations(['limit' => 5]));
            $products = $container->townManagementService->listProducts([]);
            $destinations = array_merge([], $container->poiManagementService->listPoi());
        } catch (\Exception $ex) {
            $container->logger->error($ex);
        }

        $args = array_merge($args, [
            'title' => 'ELYU Turismo',
            'topDestinations' => $topDestinations,
            'products' => $products,
            'destinationAutocomplete' => ApplicationUtils::convertArrayToAutocompleteData($destinations, 'name'),
            'destinationsBackend' => count($destinations) == 0 ? '[]' : json_encode($destinations),
        ]);

        return $container->exploreRenderer->render($response, 'privacy.phtml', $args);
    });
};
