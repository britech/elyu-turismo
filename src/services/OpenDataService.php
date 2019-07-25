<?php

namespace gov\pglu\tourism\service;

interface OpenDataService {

    public function listPoi();

    public function countVisitors(array $criteriaMap);

    public function computeAverageVisitorRating(array $criteriaMap);
}