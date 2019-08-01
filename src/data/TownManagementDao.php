<?php

namespace gov\pglu\tourism\dao;

interface TownManagementDao {

    public function addPoi(array $map);

    public function getPoi($id);

    public function updatePoi(array $map);

    public function removePoi($id);

    public function listPoi($town);

    public function addProduct(array $map);

    public function getProduct($id);

    public function updateProduct(array $map);

    public function removeProduct($id);

    public function listProducts($town);
}