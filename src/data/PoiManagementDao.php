<?php

namespace gov\pglu\tourism\dao;

interface PoiManagementDao {

    /**
     * @return mixed $id
     */
    public function createPoi(array $map);

    /**
     * @param mixed $id
     * @return array
     */
    public function getPoi($id);

    public function getPoiByName($name);

    /**
     * @param array $map
     */
    public function updatePoi(array $map);

    /**
     * @return array
     */
    public function listPoi();

    public function listPoiByTown($town);

    public function toggleDisplay($id, $val);

    public function toggleAr($id, $val);

    public function addSchedule(array $map, $id);

    public function listSchedules($id);

    public function getSchedule($id);

    public function removeSchedule($id);

    public function toggleSchedule($id, $indicator);

    public function addFee(array $map, $id);

    public function listFees($poi);

    public function getFee($id);

    public function updateFee(array $map, $id);

    public function removeFee($id);
    
    public function removePoi($id);
}