<?php

namespace gov\pglu\tourism\dao;

interface UserManagementDao {

    public function createUser(array $map);

    public function listUsers($exclusion);

    public function getUserById($id);

    public function getUserByUsername($username);

    public function updatePassword(array $map);

    public function removeUser($id);
}