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

    /**
     * @param array $map
     */
    public function updatePoi(array $map);

    /**
     * @return array
     */
    public function listPoi();

    public function toggleDisplay($id, $val);

    public function toggleAr($id, $val);

    public function addSchedule(array $map, $id);

    public function listSchedules($id);

    public function getSchedule($id);

    public function removeSchedule($id);
}