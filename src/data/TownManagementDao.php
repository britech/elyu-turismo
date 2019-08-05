<?php

namespace gov\pglu\tourism\dao;

interface TownManagementDao {
    
    public function addProduct(array $map);

    public function getProduct($id);

    public function updateProduct(array $map);

    public function removeProduct($id);

    public function listProducts($town);
}