<?php

namespace gov\pglu\tourism\dao;

use gov\pglu\tourism\dao\PoiManagementDao;
use gov\pglu\tourism\util\ApplicationUtils;
use gov\pglu\tourism\util\ApplicationConstants;

/**
 * @property \PDO $pdo
 * @property \Monolog\Logger $logger;
 */
class PoiManagementDaoImpl implements PoiManagementDao {

    private $pdo;
    private $logger;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function createPoi(array $map) {
        $poiSetupInput = array_filter($map, function($key) { 
            return strcasecmp('topicTags', $key) != 0 && strcasecmp('classifications', $key) != 0 && strcasecmp('action', $key) != 0;  }, ARRAY_FILTER_USE_KEY);
        
        list('topicTags' => $topicTags, 'classifications' => $classifications) = $map;

        try {
            $this->pdo->beginTransaction();
            $this->pdo->prepare('INSERT INTO placeofinterest(name, address, town, latitude, longitude) VALUE(:name, :address, :town, :latitude, :longitude)')->execute($poiSetupInput);

            $poiId = $this->pdo->lastInsertId();
            foreach($classifications as $classification) {
                $this->pdo->prepare('INSERT INTO poiclassification VALUES(:placeOfInterest, :classification)')->execute(array('placeOfInterest' => $poiId, 'classification' => $classification));
            }

            foreach($topicTags as $topicTag) {
                $this->pdo->prepare('INSERT INTO poitag VALUES(:placeOfInterest, :topicTag)')->execute(array('placeOfInterest' => $poiId, 'topicTag' => $topicTag));
            }

            $this->pdo->commit();
            return $poiId;
        } catch (\PDOException $ex) {
            $this->pdo->rollBack();
            throw $ex;
        }
    }

    public function getPoi($id) {
        $query = <<< QUERY
        SELECT poi.name,
            description,
            descriptionwysiwyg,
            commuterguide,
            commuterguidewysiwyg,
            address,
            town,
            latitude,
            longitude,
            arEnabled,
            displayable,
            imageName,
            GROUP_CONCAT(DISTINCT(CONCAT(classification.id, '=', classification.name)) SEPARATOR '|') as classifications,
            GROUP_CONCAT(DISTINCT(CONCAT(tag.id, '=', tag.name)) SEPARATOR '|') as topicTags
        FROM placeofinterest poi
            JOIN poiclassification poic ON poic.placeofinterest = poi.id
            JOIN classification classification ON classification.id = poic.classification
            JOIN poitag poit ON poit.placeofinterest = poi.id
            JOIN topictag tag ON tag.id = poit.tag
        WHERE poi.id = :id
QUERY;

        try {
            $statement = $this->pdo->prepare($query);
            $statement->execute(['id' => $id]);
            
            $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
            $this->logger->debug('Rows => '.json_encode($rows));

            if (count($rows) == 0 || is_null($rows[0]['name'])) {
                return null;
            }

            list($attributes) = $rows;

            $resultMap = array_filter($attributes, function($key) {
                return strcasecmp('classifications', $key) != 0 && strcasecmp('topicTags', $key) != 0;
            }, ARRAY_FILTER_USE_KEY);

            list('classifications' => $rawClassifications, 'topicTags' => $rawTags, 'town' => $town) = $attributes;
            $resultMap = array_merge($resultMap, ['classifications' => $this->createObjectMapArray($rawClassifications), 
                'topicTags' => $this->createObjectMapArray($rawTags),
                'tourismCircuit' => ApplicationUtils::getTourismCircuit($town)
            ]);

            return $resultMap;
        } catch (\PDOException $ex) {
            throw $ex;
        }
    }

    public function updatePoi(array $map) {
        $poiQuery = <<< QUERY
        UPDATE placeofinterest
            SET name=:name,
                description=:description,
                commuterguide=:commuterguide,
                address=:address,
                town=:town,
                latitude=:latitude,
                longitude=:longitude,
                descriptionwysiwyg=:descriptionwysiwyg,
                commuterguidewysiwyg=:commuterguidewysiwyg,
                imageName=:imagename
            WHERE id=:id
QUERY;

    $poiFields = array_filter($map, function($key) { 
        return strcasecmp('topicTags', $key) != 0 && strcasecmp('classifications', $key) != 0 && strcasecmp('action', $key) != 0;  
    }, ARRAY_FILTER_USE_KEY);
    
    list('topicTags' => $topicTags, 'classifications' => $classifications, 'id' => $id) = $map;

        try {
            $this->pdo->beginTransaction();
            
            $statement = $this->pdo->prepare($poiQuery);
            $statement->execute($poiFields);
            
            $this->pdo->prepare('DELETE FROM poiclassification WHERE placeofinterest=:id')->execute(['id' => $id]);
            $this->pdo->prepare('DELETE FROM poitag WHERE placeofinterest=:id')->execute(['id' => $id]);

            foreach($classifications as $classification) {
                $this->pdo->prepare('INSERT INTO poiclassification VALUES(:placeOfInterest, :classification)')->execute(array('placeOfInterest' => $id, 'classification' => $classification));
            }

            foreach($topicTags as $topicTag) {
                $this->pdo->prepare('INSERT INTO poitag VALUES(:placeOfInterest, :topicTag)')->execute(array('placeOfInterest' => $id, 'topicTag' => $topicTag));
            }

            $this->pdo->commit();
        } catch (\PDOException $ex) {
            $this->pdo->rollBack();
            throw $ex;
        }
    }

    public function listPoi() {
        $query = <<< QUERY
            SELECT id, name, address, latitude, longitude, town
            FROM placeofinterest
QUERY;
        try {
            $statement = $this->pdo->prepare($query);
            $statement->execute();
            
            $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
            array_walk($rows, function(&$row) {
                list('town' => $town) = $row;
                $row = array_merge($row, [
                    'tourismCircuit' => ApplicationUtils::getTourismCircuit($town)
                ]);
            });
            $this->logger->debug(json_encode($rows));
            return $rows;
        } catch (\PDOException $ex) {
            throw $ex;
        }
    }

    public function toggleDisplay($id, $val) {
        try {
            $this->pdo->beginTransaction();
            
            $statement = $this->pdo->prepare('UPDATE placeofinterest SET displayable=:indicator WHERE id=:id');
            $statement->execute([
                'indicator' => $val,
                'id' => $id
            ]);
            
            $this->pdo->commit();
        } catch(\PDOException $ex) {
            $this->pdo->rollBack();
            throw $ex;
        }
    }

    public function toggleAr($id, $val) {
        try {
            $this->pdo->beginTransaction();
            
            $statement = $this->pdo->prepare('UPDATE placeofinterest SET arEnabled=:indicator WHERE id=:id');
            $statement->execute([
                'indicator' => $val,
                'id' => $id
            ]);
            
            $this->pdo->commit();
        } catch(\PDOException $ex) {
            $this->pdo->rollBack();
            throw $ex;
        }
    }

    public function addSchedule(array $map, $id) {
        $query = [];
        list('open24h' => $openAllDay, 
            'open7d' => $openEveryday, 
            'days' => $days, 
            'date' => $date, 
            'openingTime' => $openingTime, 
            'closingTime' => $closingTime,
            'notes' => $notes) = $map;

        if ($openAllDay == ApplicationConstants::INDICATOR_NUMERIC_TRUE && $openEveryday == ApplicationConstants::INDICATOR_NUMERIC_TRUE) {
            $query = array_merge($query, [[
                'definition' => 'INSERT INTO poischedule(placeofinterest, open24h, open7d, notes) VALUES(:placeOfInterest, :openAllDay, :openEveryday, :notes)',
                'params' => [
                    'placeOfInterest' => $id,
                    'openAllDay' => 1,
                    'openEveryday' => 1,
                    'notes' => $notes
                ]
            ]]);
        } else if($openEveryday == ApplicationConstants::INDICATOR_NUMERIC_TRUE) {
            $query = array_merge($query, [[
                'definition' => 'INSERT INTO poischedule(placeofinterest, open7d, openingtime, closingtime, notes) VALUES(:placeOfInterest, :openEveryday, :openingTime, :closingTime, :notes)',
                'params' => [
                    'placeOfInterest' => $id,
                    'openEveryday' => 1,
                    'openingTime' => $openingTime,
                    'closingTime' => $closingTime,
                    'notes' => $notes
                ]
            ]]);
        }

        if (array_key_exists('days', $map) && !is_null($days)) {
            foreach($days as $day) {
                $query = array_merge($query, [[
                    'definition' => "INSERT INTO poischedule(placeofinterest, day, openingtime, closingtime, open24h, notes) VALUES(:placeOfInterest, :day, :openingTime, :closingTime, :openAllDay, :notes)",
                    'params' => [
                        'placeOfInterest' => $id,
                        'day' => $day,
                        'openingTime' => $openingTime,
                        'closingTime' => $closingTime,
                        'openAllDay' => $openAllDay == ApplicationConstants::INDICATOR_NUMERIC_TRUE ? 1 : 0,
                        'notes' => $notes
                    ]
                ]]);
            }
        }

        if (array_key_exists('date', $map) && !is_null($date)) {
            $query = array_merge($query, [[
                'definition' => "INSERT INTO poischedule(placeofinterest, date, openingtime, closingtime, open24h, notes) VALUES(:placeOfInterest, :date, :openingTime, :closingTime, :openAllDay, :notes)",
                'params' => [
                    'placeOfInterest' => $id,
                    'date' => $date,
                    'openingTime' => $openingTime,
                    'closingTime' => $closingTime,
                    'openAllDay' => $openAllDay == ApplicationConstants::INDICATOR_NUMERIC_TRUE ? 1 : 0,
                    'notes' => $notes
                ]
            ]]);
        }

        if (count($query) == 0) {
            throw new \Exception('No queries available for processing');
        }
        
        try {
            $this->pdo->beginTransaction();
            foreach($query as $entry) {
                list('definition' => $definition, 'params' => $params) = $entry;
                $statement = $this->pdo->prepare($definition);
                $this->logger->debug("INSERT QUERY: {$definition}");
                $this->logger->debug("Params: ". json_encode($params));
                $statement->execute($params);
            }
            $this->pdo->commit();
        } catch (\PDOException $ex) {
            throw $ex;
        }
    }

    public function listSchedules($id) {
        $query = <<< QUERY
            SELECT id, 
                day,
                DATE_FORMAT(date, '%M %e, %Y') as specificDate,
                CONCAT(TIME_FORMAT(openingtime, '%h:%i %p'), ' - ', TIME_FORMAT(closingtime, '%h:%i %p')) as operatingHours,
                open24h as openAllDay,
                open7d as openEveryday,
                notes,
                enabled
            FROM poischedule
            WHERE placeofinterest=:poi
QUERY;
        try {
            $statement = $this->pdo->prepare($query);
            $statement->execute(['poi' => $id]);
            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            $this->logger->debug(json_encode($result));
            return $result;
        } catch (\PDOException $ex) {
            throw $ex;
        }
    }

    public function getSchedule($id) {
        $query = <<< QUERY
            SELECT id, 
                day,
                date,
                openingtime,
                closingtime,
                open24h,
                open7d,
                notes,
                enabled,
                placeofinterest
            FROM poischedule
            WHERE id=:id
QUERY;
        try {
            $statement = $this->pdo->prepare($query);
            $statement->execute(['id' => $id]);
            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            $this->logger->debug(json_encode($result));

            return count($result) == 0 ? null : $result[0];
        } catch (\PDOException $ex) {
            throw $ex;
        }
    }

    public function removeSchedule($id) {
        try {
            $this->pdo->beginTransaction();
            $statement = $this->pdo->prepare('DELETE FROM poischedule WHERE id=:id');
            $statement->execute(['id' => $id]);
            $this->pdo->commit();
        } catch (\PDOException $ex) {
            $this->pdo->rollBack();
            throw $ex;
        }
    }

    public function toggleSchedule($id, $indicator) {
        try {
            $this->pdo->beginTransaction();
            $statement = $this->pdo->prepare('UPDATE poischedule SET enabled=:enabled WHERE id=:id');
            $statement->execute([
                'enabled' => $indicator,
                'id' => $id
            ]);
            $this->pdo->commit();
        } catch (\PDOException $ex) {
            $this->pdo->rollBack();
            throw $ex;
        }
    }

    public function addFee(array $map, $id) {
        $params = array_merge($map, [
            'poi' => $id
        ]);

        try {
            $this->pdo->beginTransaction();
            $statement = $this->pdo->prepare('INSERT INTO poifee(description, amount, freePrice, placeofinterest) VALUES(:description, :amount, :freePrice, :poi)');
            $statement->execute($params);
            $this->pdo->commit();
        } catch (\PDOException $ex) {
            $this->pdo->rollBack();
            throw $ex;
        }
    }

    public function listFees($poi) {
        try {
            $statement = $this->pdo->prepare('SELECT * FROM poifee WHERE placeofinterest=:poi');
            $statement->execute(['poi' => $poi]);
            return $statement->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $ex) {
            throw $ex;
        }
    }

    public function getFee($id) {
        try {
            $statement = $this->pdo->prepare('SELECT * FROM poifee WHERE id=:id');
            $statement->execute(['id' => $id]);
            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            return count($result) == 0 ? null : $result[0];
        } catch (\PDOException $ex) {
            throw $ex;
        }
    }

    public function updateFee(array $map, $id) {
        $params = array_merge($map, [
            'id' => $id
        ]);
        
        $query = <<<QUERY
            UPDATE poifee
            SET description=:description,
                freePrice=:freePrice,
                amount=:amount,
                enabled=:enabled
            WHERE id=:id
QUERY;
        try {
            $this->pdo->beginTransaction();
            $statement = $this->pdo->prepare($query);
            $statement->execute($params);
            $this->pdo->commit();
        } catch (\PDOException $ex) {
            $this->pdo->rollBack();
            throw $ex;
        }
    }

    public function removeFee($id) {
        try {
            $this->pdo->beginTransaction();
            $statement = $this->pdo->prepare('DELETE FROM poifee WHERE id=:id');
            $statement->execute(['id' => $id]);
            $this->pdo->commit();
        } catch(\PDOException $ex) {
            $this->pdo->rollBack();
            throw $ex;
        }
    }

    public function listPoiByTown($town) {
        $query = <<< QUERY
            SELECT id, name, description, displayable
            FROM placeofinterest WHERE town=:town
QUERY;
        try {
            $statement = $this->pdo->prepare($query);
            $statement->execute(['town' => $town]);
            
            $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
            array_walk($rows, function(&$row) {
                list('town' => $town) = $row;
                $row = array_merge($row, [
                    'tourismCircuit' => ApplicationUtils::getTourismCircuit($town)
                ]);
            });
            $this->logger->debug(json_encode($rows));
            return $rows;
        } catch (\PDOException $ex) {
            throw $ex;
        }
    }

    public function getPoiByName($name) {
        $query = <<< QUERY
        SELECT poi.name,
            description,
            descriptionwysiwyg,
            commuterguide,
            commuterguidewysiwyg,
            address,
            town,
            latitude,
            longitude,
            arEnabled,
            displayable,
            imageName,
            GROUP_CONCAT(DISTINCT(CONCAT(classification.id, '=', classification.name)) SEPARATOR '|') as classifications,
            GROUP_CONCAT(DISTINCT(CONCAT(tag.id, '=', tag.name)) SEPARATOR '|') as topicTags
        FROM placeofinterest poi
            JOIN poiclassification poic ON poic.placeofinterest = poi.id
            JOIN classification classification ON classification.id = poic.classification
            JOIN poitag poit ON poit.placeofinterest = poi.id
            JOIN topictag tag ON tag.id = poit.tag
        WHERE MATCH(poi.name) AGAINST (:name)
QUERY;

        try {
            $statement = $this->pdo->prepare($query);
            $statement->execute(['name' => $name]);
            
            $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
            $this->logger->debug('Rows => '.json_encode($rows));

            if (count($rows) == 0 || is_null($rows[0]['name'])) {
                return null;
            }

            list($attributes) = $rows;

            $resultMap = array_filter($attributes, function($key) {
                return strcasecmp('classifications', $key) != 0 && strcasecmp('topicTags', $key) != 0;
            }, ARRAY_FILTER_USE_KEY);

            list('classifications' => $rawClassifications, 'topicTags' => $rawTags, 'town' => $town) = $attributes;
            $resultMap = array_merge($resultMap, ['classifications' => $this->createObjectMapArray($rawClassifications), 
                'topicTags' => $this->createObjectMapArray($rawTags),
                'tourismCircuit' => ApplicationUtils::getTourismCircuit($town)
            ]);

            return $resultMap;
        } catch (\PDOException $ex) {
            throw $ex;
        }
    }

    private function createObjectMapArray($entries) {
        return array_map(function($val) {
            list($id, $name) = explode('=', $val);
            return ['id' => $id, 'name' => $name];
        }, explode('|', $entries));
    }

    public function __set($name, $value) {
        $this->$name = $value;
    }

    public function __get($name) {
        return $this->$name;
    }
}