<?php

namespace gov\pglu\tourism\dao;

use gov\pglu\tourism\dao\PoiManagementDao;

/**
 * @property \PDO $pdo
 */
class PoiManagementDaoImpl implements PoiManagementDao {

    private $pdo;

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
        } catch (\PDOException $ex) {
            $this->pdo->rollBack();
            throw $ex;
        }
    }
}