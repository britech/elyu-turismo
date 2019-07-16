<?php

namespace gov\pglu\tourism\dao;

interface ClassificationDao {

    /**
     * @param String $classification
     */
    public function insertClassification($classification);

    /**
     * @return array
     */
    public function getClassifications();

    /**
     * @param String $name
     */
    public function deleteClassificationByName($name);
}