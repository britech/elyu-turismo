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

    public function computeAverageVisitorRating(array $criteria) {
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
            SELECT name, AVG(rating) as rating
            FROM poirating poir
            JOIN placeofinterest poi ON poir.placeofinterest = poi.id
            WHERE {$conditional}
            GROUP BY poi.id
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

    public function __set($name, $value) {
        $this->$name = $value;
    }
}