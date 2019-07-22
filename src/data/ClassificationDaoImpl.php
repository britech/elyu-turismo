<?php
namespace gov\pglu\tourism\dao;

use gov\pglu\tourism\dao\ClassificationDao;

/**
 * @property \PDO $pdo
 */
class ClassificationDaoImpl implements ClassificationDao {

    private $pdo;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function insertClassification($classification) {
        try {
            $this->pdo->beginTransaction();
            $statement = $this->pdo->prepare('INSERT INTO classification(name) VALUES(:classification)');
            $statement->execute(array('classification' => $classification));
            $this->pdo->commit();
        } catch(\PDOException $ex) {
            $this->pdo->rollback();
            throw $ex;
        }
    }

    public function getClassifications() {
        try {
            $statement = $this->pdo->prepare('SELECT * FROM classification');
            $statement->execute();
            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            return $result;
        } catch(\PDOException $ex) {
            throw $ex;
        }    
    }

    public function deleteClassificationByName($name) {
        try {
            $this->pdo->beginTransaction();
            $statement = $this->pdo->prepare('DELETE FROM classification WHERE name=:name');
            $statement->execute(array('name' => $name));
            $this->pdo->commit();
        } catch (\PDOException $ex) {
            $this->pdo->rollback();
            throw $ex;
        }
    }
}