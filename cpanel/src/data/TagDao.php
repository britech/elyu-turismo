<?php

namespace gov\pglu\tourism\dao;

interface TagDao {

    /**
     * @param String $tag
     */
    public function insertTag($tag);

    /**
     * @return array The available tags
     */
    public function getTags();

    /**
     * @param String $name
     */
    public function deleteTagByName($name);
}