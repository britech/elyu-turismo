<?php

namespace gov\pglu\tourism\dao;

use gov\pglu\tourism\dao\UserManagementDao;
use Monolog\Logger;

/**
 * @property \PDO $pdo
 * @property Logger $logger
 */
class UserManagementDaoImpl implements UserManagementDao {

    private $pdo;
    private $logger;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function createUser(array $map) {
        list('username' => $username, 'password' => $password) = $map;

        try {
            $this->pdo->beginTransaction();
            $this->pdo->prepare('INSERT INTO user(username, password) VALUES(:username, :password)')->execute([
                'username' => $username,
                'password' => password_hash($password, PASSWORD_DEFAULT)
            ]);
            $this->pdo->commit();
        } catch (\PDOException $ex) {
            $this->pdo->rollBack();
            throw $ex;
        }
    }

    public function listUsers($exclusion) {
        try {
            $statement = $this->pdo->prepare('SELECT id, username WHERE id <> :id');
            $statement->execute(['id' => $exclusion]);
            return $statement->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $ex) {
            throw $ex;
        }
    }

    public function updatePassword(array $map) {
        list('id' => $id, 'password' => $password) = $map;
        try {
            $this->pdo->beginTransaction();
            $this->pdo->prepare('UPDATE user SET password=:password WHERE id=:id')->execute([
                'id' => $id,
                'password' => password_hash($password, PASSWORD_DEFAULT)
            ]);
            $this->pdo->commit();
        } catch (\PDOException $ex) {
            $this->pdo->rollBack();
            throw $ex;
        }
    }

    public function removeUser($id) {
        try {
            $this->pdo->beginTransaction();
            $this->pdo->prepare('DELETE FROM user WHERE id=:id')->execute(['id' => $id]);
            $this->pdo->commit();
        } catch (\PDOException $ex) {
            $this->pdo->rollBack();
            throw $ex;
        }
    }

    public function getUserById($id) {
        try {
            $statement = $this->pdo->prepare('SELECT * WHERE id=:id');
            $statement->execute(['id' => $id]);
            $rows = $statement->fetchAll(\PDO::FETCH_ASSOC); 
            return count($rows) == 0 ? null : $rows[0];
        } catch (\PDOException $ex) {
            throw $ex;
        }
    }

    public function getUserByUsername($username) {
        try {
            $statement = $this->pdo->prepare('SELECT * WHERE username=:username');
            $statement->execute(['username' => $username]);
            $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
            return count($rows) == 0 ? null : $rows[0];
        } catch (\PDOException $ex) {
            throw $ex;
        }
    }

    public function __set($name, $value) {
        $this->$name = $value;
    }
}