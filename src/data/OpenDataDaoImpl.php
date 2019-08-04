<?php

namespace gov\pglu\tourism\dao;

use gov\pglu\tourism\dao\OpenDataDao;

/**
 * @property \PDO $pdo
 * @property \Monolog\Logger $logger
 */
class OpenDataDaoImpl implements OpenDataDao {

    private $pdo;
    private $logger;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function countVisitors(array $criteria) {
        $conditionals = [];
        if (array_key_exists('startDate', $criteria) && array_key_exists('endDate', $criteria)) {
            $startDate = "{$criteria['startDate']} 00:00:00";
            $endDate = "{$criteria['endDate']} 23:59:59";
            $condition = "(dateAdded >= '{$startDate}' AND dateAdded <= '{$endDate}')";
            $conditionals = array_merge($conditionals, [$condition]);
        } else if(array_key_exists('endDate', $criteria)) {
            $endDate = "{$criteria['endDate']} 23:59:59";
            $condition = "dateAdded <= '{$endDate}'";
            $conditionals = array_merge($conditionals, [$condition]);
        } else {
            $date = array_key_exists('startDate', $criteria) ? $criteria['startDate'] : date('Y-m-d');
            $startDate = "{$date} 00:00:00";
            $condition = "dateAdded >= '{$startDate}'";
            $conditionals = array_merge($conditionals, [$condition]);
        }

        if (array_key_exists('places', $criteria)) {
            $places = implode(",", $criteria['places']);
            $condition = "placeofinterest IN ({$places})";
            $conditionals = array_merge($conditionals, [$condition]);
        }

        $conditional = implode(' AND ', $conditionals);

        $query = <<< QUERY
            SELECT name, COUNT(id) as visitorcount
            FROM poivisit poiv
            JOIN placeofinterest poi ON poiv.placeofinterest = poi.id
            WHERE {$conditional}
            GROUP BY id
QUERY;

        $this->logger->debug("Executing query: {$query}");
        try {
            $statement = $this->pdo->prepare($query);
            $statement->execute();
            return $statement->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $ex) {
            throw $ex;
        }
     }

    public function captureVisit($placeOfInterest) {
        try {
            $this->pdo->beginTransaction();
            $statement = $this->pdo->prepare("INSERT INTO poivisit(placeofinterest) VALUES(:poi)");
            $statement->execute(['poi' => $placeOfInterest]);
            $this->pdo->commit();
        } catch (\PDOException $ex) {
            $this->pdo->rollBack();
            throw $ex;
        }
    }

    public function listTop5DestinationsByTown($town) {
        $query = <<< QUERY
            SELECT id, name, arEnabled, COUNT(id) as visitorCount
            FROM poivisit poiv
            JOIN placeofinterest poi ON poiv.placeofinterest = poi.id
            WHERE town=:town
            GROUP BY id
            ORDER BY visitorCount DESC
            LIMIT 5
QUERY;
        try {
            $statement = $this->pdo->prepare($query);
            $statement->execute(['town' => $town]);
            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            $this->logger->debug(json_encode($result));
            return $result;
        } catch (\PDOException $ex) {
            throw $ex;
        }
    }

    public function listTop5Destinations() {
        $query = <<< QUERY
            SELECT id, name, arEnabled, COUNT(id) as visitorCount
            FROM poivisit poiv
            JOIN placeofinterest poi ON poiv.placeofinterest = poi.id
            GROUP BY id
            ORDER BY visitorCount DESC
            LIMIT 5
QUERY;
        try {
            $statement = $this->pdo->prepare($query);
            $statement->execute();
            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            $this->logger->debug(json_encode($result));
            return $result;
        } catch (\PDOException $ex) {
            throw $ex;
        }
    }

    public function summarizeVisitors() {
        $query = <<< QUERY
            SELECT town, COUNT(id) as visitorCount
            FROM poivisit poiv
            JOIN placeofinterest poi ON poiv.placeofinterest = poi.id
            GROUP BY town
QUERY;
        try {
            $statement = $this->pdo->prepare($query);
            $statement->execute();
            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            $this->logger->debug(json_encode($result));
            return $result;
        } catch (\PDOException $ex) {
            throw $ex;
        }
    }

    public function countVisitorsByDestination($destination) {
        $query = <<< QUERY
            SELECT name, COUNT(id) as visitorcount
            FROM poivisit poiv
            JOIN placeofinterest poi ON poiv.placeofinterest = poi.id
            WHERE poi.id = :destination
            GROUP BY id
QUERY;

        $this->logger->debug("Executing query: {$query}");
        try {
            $statement = $this->pdo->prepare($query);
            $statement->execute(['destination' => $destination]);
            return $statement->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $ex) {
            throw $ex;
        }
    }

    public function summarizeVisitorsByTown($town) {
        $query = <<< QUERY
            SELECT name, COUNT(id) as visitorcount
            FROM poivisit poiv
            JOIN placeofinterest poi ON poiv.placeofinterest = poi.id
            WHERE town = :town
            GROUP BY id
            ORDER BY visitorCount DESC
QUERY;

        $this->logger->debug("Executing query: {$query}");
        try {
            $statement = $this->pdo->prepare($query);
            $statement->execute(['town' => $town]);
            return $statement->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $ex) {
            throw $ex;
        }
    }

    public function __set($name, $value) {
        $this->$name = $value;
    }
}