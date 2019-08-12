<?php

namespace gov\pglu\tourism\util;

final class ApplicationUtils {

    const TOURISM_CIRCUITS = [
        'Northern' => ['Balaoan', 'Bangar', 'Luna', 'Santol', 'Sudipen'],
        'Central' => ['Bacnotan', 'San Fernando', 'San Gabriel', 'San Juan'],
        'Central Eastern' => ['Bauang', 'Bagulin', 'Burgos', 'Naguilian'],
        'South' => ['Agoo', 'Aringay', 'Caba', 'Santo Tomas'],
        'South Eastern' => ['Pugo', 'Rosario', 'Tubao']
    ];

    /**
     * @param array $input
     * @param String $keyName
     */
    public static function convertArrayToTagData(array $map, $keyName) {
        $result = [];
        foreach($map as $key => $value) {
            list($keyName => $tag) = $value;
            $result = array_merge($result, array(array('tag' => $tag)));
        }
        return count($result) == 0 ? '[]' : json_encode($result);
    }

    /**
     * @param array $map
     * @param String $keyName
     */
    public static function convertTagResultToBackendReferences(array $map, $keyName) {
        $result = [];
        foreach($map as $key => $value) {
            list($keyName => $reference) = $value;
            $result = array_merge($result, array($reference));
        }
        return count($result) == 0 ? '[]' : json_encode($result);
    }

    public static function convertArrayToAutocompleteData(array $map, $keyName) {
        $result = [];
        foreach($map as $entry) {
            list($keyName => $value) = $entry;
            $result = array_merge($result, array($value => NULL));
        }
        return count($result) == 0 ? '{}' : json_encode($result);
    }

    /**
     * @param String $town
     * @return String
     */
    public static function getTourismCircuit($town) {
        foreach (self::TOURISM_CIRCUITS as $circuit => $towns) {
            foreach($towns as $entry) {
                if (strcasecmp($town, $entry) == 0) {
                    return $circuit;
                }
            }
        }
    }

    public static function getVisitorCountByTown(array $inputArray, $town) {
        foreach($inputArray as $input) {
            list('town' => $inputTown, 'visitorCount' => $count) = $input;
            if (strcasecmp($town, $inputTown) == 0) {
                return $count;
            }
        }
        return 0;
    }

    public static function getVisitorCountByPoi(array $inputArray, array $map) {
        list('name' => $name, 'visitorcount' => $visitorCount) = $map;
        foreach($inputArray as $input) {
            if (strcasecmp($name, $input) == 0) {
                return $visitorCount;
            }
        }
        return 0;
    }
}