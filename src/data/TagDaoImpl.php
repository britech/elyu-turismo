<?php

namespace gov\pglu\tourism\dao;

use gov\pglu\tourism\dao\TagDao;

/**
 * @property \PDO $pdo
 */
class TagDaoImpl implements TagDao {

    private $pdo;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function insertTag($tag) {
        try {
            $this->pdo->beginTransaction();
            $statement = $this->pdo->prepare('INSERT INTO topictag(name) VALUES(:tag)');
            $statement->execute(array('tag' => $tag));
            $this->pdo->commit();
        } catch(\PDOException $ex) {
            $this->pdo->rollback();
            throw $ex;
        }
    }

    public function getTags() {
        try {
            $statement = $this->pdo->prepare('SELECT * FROM topictag');
            $statement->execute();
            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            return $result;
        } catch(\PDOException $ex) {
            throw $ex;
        }     
    }

    public function deleteTag($id) {
        try {
            $this->pdo->beginTransaction();
            $statement = $this->pdo->prepare('DELETE FROM topictag WHERE id=:id');
            $statement->execute(array('id' => $id));
            $this->pdo->commit();
        } catch (\PDOException $ex) {
            $this->pdo->rollback();
            throw $ex;
        }
    }
}