<?php

namespace gov\pglu\tourism\service;

use gov\pglu\tourism\service\OpenDataService;
use gov\pglu\tourism\dao\PoiManagementDao;
use gov\pglu\tourism\dao\OpenDataDao;

/**
 * @property PoiManagementDao $poiManagementDao
 * @property OpenDataDao $openDataDao
 */
class OpenDataServiceCsvImpl implements OpenDataService {

    private $poiManagementDao;
    private $openDataDao;

    public function __construct(PoiManagementDao $poiManagementDao, OpenDataDao $openDataDao) {
        $this->poiManagementDao = $poiManagementDao;
        $this->openDataDao = $openDataDao;
    }

    public function listPoi() {
        $csvRows = [['Name', 'Address', 'Latitude', 'Longitude', 'Tourism Circuit', 'Town']];
        try {
            $rows = $this->poiManagementDao->listPoi();
            foreach($rows as $row) {
                list('name' => $name, 
                    'address'=> $address, 
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'tourismCircuit' => $tourismCircuit,
                    'town' => $town) = $row;
                
                $csvRows = array_merge($csvRows, [[$name, $address, $latitude, $longitude, $tourismCircuit, $town]]);
            }
            return $csvRows;
        } catch (\PDOException $ex) {
            throw $ex;
        }
    }

    public function countVisitors(array $criteriaMap) {
        $csvRows = [['Name', 'Visitor Count']];
        try {
            $rows = $this->openDataDao->countVisitors($criteriaMap);
            foreach($rows as $row) {
                list('name' => $name, 'visitorcount' => $visitorCount) = $row;
                $csvRows = array_merge($csvRows, [[$name, $visitorCount]]);
            }
            return $csvRows;
        } catch (\PDOException $ex) {
            throw $ex;
        }
        return [];
    }

    public function computeAverageVisitorRating(array $criteriaMap) {
        return [];
    }
}