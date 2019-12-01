<?php

namespace gov\pglu\tourism\dao;

interface VisitorDao {

    public function addComment(array $map);

    public function listComments($placeOfInterest);

    public function removeComment($id);

    public function listTaggedDestinations(array $destinations);

    public function listTaggedProducts(array $products);

    public function addComplaint(array $map);

    public function updateComplaint(array $map);

    public function listComplaints();
}