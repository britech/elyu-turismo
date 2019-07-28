<?php

namespace gov\pglu\tourism\dao;

interface OpenDataDao {

    public function countVisitors(array $criteria);

    public function computeAverageVisitorRating(array $criteria);
}