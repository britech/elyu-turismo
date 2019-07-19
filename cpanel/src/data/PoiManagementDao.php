<?php

namespace gov\pglu\tourism\dao;

interface PoiManagementDao {

    /**
     * @return mixed $id
     */
    public function createPoi(array $map);
}