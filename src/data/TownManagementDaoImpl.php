<?php

namespace gov\pglu\tourism\dao;

use gov\pglu\tourism\dao\TownManagementDao;

/**
 * @property \PDO $pdo
 */
class TownManagementDaoImpl implements TownManagementDao {

    private $pdo;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    public function addProduct(array $map) {
        try {
            $this->pdo->beginTransaction();
            
            $statement = $this->pdo->prepare("INSERT INTO townproduct(name, arLink, town) VALUES(:name, :arLink, :town)");
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
            
            $statement = $this->pdo->prepare("UPDATE townproduct SET name=:name, arLink=:arLink, town=:town, enabled=:enabled WHERE id=:id");
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

    public function listProducts($town) {
        try {
            $statement = $this->pdo->prepare("SELECT * FROM townproduct WHERE town=:town");
            $statement->execute(['town' => $town]);
            return $statement->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $ex) {
            throw $ex;
        }
    }
}