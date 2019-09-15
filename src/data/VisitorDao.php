<?php

namespace gov\pglu\tourism\dao;

interface VisitorDao {

    public function addComment(array $map);

    public function listComment($placeOfInterest);

    public function removeComment($id);
}