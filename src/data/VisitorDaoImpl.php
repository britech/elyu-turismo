<?php

namespace gov\pglu\tourism\dao;

use gov\pglu\tourism\dao\VisitorDao;
use gov\pglu\tourism\util\ApplicationConstants;

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

    public function listTaggedDestinations(array $destinations) {
        if (count($destinations) == 0) {
            return [];
        }

        $useLocalFileSystem  = intval(getenv('USE_LOCAL_FILESYSTEM')) == ApplicationConstants::INDICATOR_NUMERIC_TRUE;

        $input = implode(",", $destinations);
        $query = <<<QUERY
            SELECT
                id,
                name,
                description,
                imageName,
                CASE
                    WHEN arEnabled = 1 AND LENGTH(TRIM(arLink)) > 0 THEN '1'
                    ELSE '0'
                END as arEnabled,
                arLink,
                displayable as enabled
            FROM placeofinterest
            WHERE id IN ($input)
QUERY;
        try {
            $this->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
            $statement = $this->pdo->prepare($query);
            $statement->execute();
            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            array_walk($result, function(&$row) use ($useLocalFileSystem) {
                list('imageName' => $image, 'arEnabled' => $arEnabled, 'enabled' => $enabled) = $row;
                if (strlen(trim($image)) == 0) {
                    return;
                }
                $row = array_merge($row, [
                   'imageSrc' => $useLocalFileSystem ? "/uploads/{$image}" : $image,
                   'arEnabled' => boolval(strcasecmp(ApplicationConstants::INDICATOR_NUMERIC_TRUE, $arEnabled) == 0),
                   'enabled' => boolval($enabled)
                ]);
            });
            return $result;
        } catch (\PDOException $ex) {
            throw $ex;
        } finally {
            $this->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        }
    }

    public function listTaggedProducts(array $products) {
        if (count($products) == 0) {
            return [];
        }

        $useLocalFileSystem  = intval(getenv('USE_LOCAL_FILESYSTEM')) == ApplicationConstants::INDICATOR_NUMERIC_TRUE;

        $input = implode(",", $products);
        $query = <<<QUERY
            SELECT
                id,
                name,
                description,
                imageFile,
                arLink,
                CASE
                    WHEN LENGTH(TRIM(arLink)) > 0 THEN '1'
                    ELSE '0'
                END as arEnabled,
                enabled
            FROM townproduct
            WHERE id IN ($input)
QUERY;
        try {
            $this->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
            $statement = $this->pdo->prepare($query);
            $statement->execute();
            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            array_walk($result, function(&$row) use ($useLocalFileSystem) {
                list('imageFile' => $image, 'arEnabled' => $arEnabled, 'enabled' => $enabled) = $row;
                if (strlen(trim($image)) == 0) {
                    return;
                }
                $row = array_merge($row, [
                   'imageSrc' => $useLocalFileSystem ? "/uploads/{$image}" : $image,
                   'arEnabled' => boolval(strcasecmp(ApplicationConstants::INDICATOR_NUMERIC_TRUE, $arEnabled) == 0),
                   'enabled' => boolval($enabled)
                ]);
            });
            return $result;
        } catch (\PDOException $ex) {
            throw $ex;
        } finally {
            $this->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        }
    }

    public function addComplaint(array $map) {
        $query = <<<INSERT_QUERY
        INSERT INTO complaint(poi, 
            description,
            name,
            emailAddress,
            mobileNumber)
            VALUES(
                :poi,
                :description,
                :name,
                :emailAddress,
                :mobileNumber)
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
    public function __set($name, $value) {
        $this->$name = $value;
    }
}