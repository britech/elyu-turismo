<?php

namespace gov\pglu\tourism\dao;

interface OpenDataDao {

    public function captureVisit($placeOfInterest);

    public function countVisitors(array $criteria);
}