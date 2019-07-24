<?php

namespace gov\pglu\tourism\service;

use gov\pglu\tourism\service\OpenDataService;
use gov\pglu\tourism\dao\PoiManagementDaoImpl as PoiManagementDao;

/**
 * @property PoiManagementDao $poiManagementDao
 */
class OpenDataServiceCsvImpl implements OpenDataService {

    private $poiManagementDao;

    public function __construct(PoiManagementDao $poiManagementDao) {
        $this->poiManagementDao = $poiManagementDao;
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
        return [];
    }

    public function computeVisitorAverageRating(array $criteriaMap) {
        return [];
    }
}