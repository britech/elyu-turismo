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


    public static function convertArrayToAutocompleteData(array $map, $keyName) {
        $result = [];
        foreach($map as $entry) {
            list($keyName => $value) = $entry;
            $result = array_merge($result, array($value => NULL));
        }
        return count($result) == 0 ? '{}' : json_encode($result);
    }
}