<?php

namespace gov\pglu\tourism\dao;

use gov\pglu\tourism\dao\PoiManagementDao;
use gov\pglu\tourism\util\ApplicationUtils;

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
                commuterguidewysiwyg=:commuterguidewysiwyg
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