<?php

namespace gov\pglu\tourism\dao;

interface OpenDataDao {

    public function captureVisit($placeOfInterest);

    public function countVisitors(array $criteria);

    public function listTop5DestinationsByTown($town);

    public function listTop5Destinations();

    public function summarizeVisitors();

    public function countVisitorsByDestination($destination);

    public function summarizeVisitorsByTown($town);

    public function listDestinations(array $map);

    public function trackDownload(array $map);

    public function listDownloads(array $criteria);
}