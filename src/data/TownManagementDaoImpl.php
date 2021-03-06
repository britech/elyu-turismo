<?php

namespace gov\pglu\tourism\dao;

use Monolog\Logger;
use gov\pglu\tourism\dao\TownManagementDao;

/**
 * @property \PDO $pdo
 * @property Logger $logger
 */
class TownManagementDaoImpl implements TownManagementDao {

    private $pdo;
    private $logger;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    public function addProduct(array $map) {
        try {
            $this->pdo->beginTransaction();
            
            $statement = $this->pdo->prepare("INSERT INTO townproduct(name, arLink, town, description, imageFile, images, photoCredit) VALUES(:name, :arLink, :town, :description, :imageFile, :images, :photoCredit)");
            $statement->execute($map);

            $this->pdo->commit();
        } catch (\PDOException $ex) {
            $this->pdo->rollBack();
            throw $ex;
        }
    }

    public function getProduct($id) {
        try {
            $statement = $this->pdo->prepare("SELECT * FROM townproduct WHERE id=:id");
            $statement->execute(['id' => $id]);

            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            return count($result) == 0 ? null : $result[0];
        } catch (\PDOException $ex) {
            throw $ex;
        }
    }

    public function updateProduct(array $map) {
        try {
            $this->pdo->beginTransaction();
            
            $statement = $this->pdo->prepare("UPDATE townproduct SET name=:name, arLink=:arLink, town=:town, enabled=:enabled, description=:description, imageFile=:imageFile, images=:images, photoCredit=:photoCredit WHERE id=:id");
            $statement->execute($map);

            $this->pdo->commit();
        } catch (\PDOException $ex) {
            $this->pdo->rollBack();
            throw $ex;
        }
    }

    public function removeProduct($id) {
        try {
            $this->pdo->beginTransaction();
            
            $statement = $this->pdo->prepare("DELETE FROM townproduct WHERE id=:id");
            $statement->execute(['id' => $id]);

            $this->pdo->commit();
        } catch (\PDOException $ex) {
            $this->pdo->rollBack();
            throw $ex;
        }
    }

    public function listProducts(array $criteria) {
        $whereClause = !array_key_exists('town', $criteria) ? "" : "WHERE town=:town";
        $params = !array_key_exists('town', $criteria) ? [] : ['town' => $criteria['town']];
        try {
            $statement = $this->pdo->prepare("SELECT * FROM townproduct {$whereClause} ORDER by name ASC");
            $statement->execute($params);
            return $statement->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $ex) {
            throw $ex;
        }
    }

    public function listTowns() {
        try {
            $this->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
            $statement = $this->pdo->prepare("SELECT id, name, tourismCircuit FROM town ORDER BY name");
            $statement->execute();
            return $statement->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $ex) {
            throw $ex;
        } finally {
            $this->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        }
    }

    public function loadTown($id) {
        try {
            $this->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
            $statement = $this->pdo->prepare("SELECT * FROM town WHERE id=:id");
            $statement->execute([ 'id' => $id ]);

            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            $this->logger->debug("Result: ".json_encode($result));

            return count($result) == 0 ? null : $result[0];
        } catch (\PDOException $ex) {
            throw $ex;
        } finally {
            $this->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        }
    }

    public function loadTownByName($name) {
        try {
            $this->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
            $statement = $this->pdo->prepare("SELECT * FROM town WHERE LOWER(name)=LOWER(:name)");
            $statement->execute([ 'name' => $name ]);

            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            $this->logger->debug("Result: ".json_encode($result));

            return count($result) == 0 ? null : $result[0];
        } catch (\PDOException $ex) {
            throw $ex;
        } finally {
            $this->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        }
    }

    public function updateTown(array $input) {
        $query = <<<UPDATE_QUERY
            UPDATE town
            SET description=:description,
                commuterGuide=:commuterGuide,
                otherDetails=:otherDetails,
                bannerImage=:bannerImage,
                linkImage=:linkImage,
                photoCredits=:photoCredits
            WHERE id=:id
UPDATE_QUERY;

        try {
            $this->pdo->beginTransaction();
            $statement = $this->pdo->prepare($query);
            $statement->execute($input);
            $this->pdo->commit();
        } catch (\PDOException $ex) {
            $this->pdo->rollBack();
            throw $ex;
        }
    }

    public function __set($name, $value) {
        $this->$name = $value;
    }
}