<?php

namespace gov\pglu\tourism\dao;

interface VisitorDao {

    public function addComment(array $map);

    public function listComments($placeOfInterest);

    public function removeComment($id);
}