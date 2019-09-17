<?php

namespace gov\pglu\tourism\dao;

use gov\pglu\tourism\dao\VisitorDao;

class VisitorDaoImpl implements VisitorDao {

    private $pdo;
    private $logger;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function addComment(array $map) {
        $query = <<<INSERT_QUERY
            INSERT INTO poicomment(content, 
                contentWysiwyg,
                name,
                email,
                placeofinterest)
                VALUES(
                    :content,
                    :contentWysiwyg,
                    :name,
                    :email,
                    :placeOfInterest)
INSERT_QUERY;
        try {
            $this->pdo->beginTransaction();
            
            $statement = $this->pdo->prepare($query);
            $statement->execute($map);

            $this->pdo->commit();
        } catch (\PDOException $ex) {
            $this->pdo->rollBack();
            throw $ex;
        }
    }

    public function listComments($placeOfInterest) {
        $query = <<<QUERY
            SELECT id,
                content,
                contentWysiwyg,
                name,
                email,
                DATE_FORMAT(timestamp, "%b %e, %Y %l:%i %p") as timestamp
            FROM poicomment
            WHERE placeofinterest = :placeOfInterest
            ORDER by timestamp DESC
QUERY;
        try {
            $statement = $this->pdo->prepare($query);
            $statement->execute(['placeOfInterest' => $placeOfInterest]);
            return $statement->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $ex) {
            throw $ex;
        }
    }

    public function removeComment($id) {
        try {
            $this->pdo->beginTransaction();

            $statement = $this->pdo->prepare('DELETE FROM poicomment WHERE id=:id');
            $statement->execute(['id' => $id]);

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